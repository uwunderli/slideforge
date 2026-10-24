<?php
/**
 * Importiert eine aus Logos (Sermon Builder) exportierte HTML-Datei als SlideForge-Präsentation.
 */
class LogosSermonImporter
{
    /** @return array{slides: array, title: string, width: int, height: int, warnings: string[], layout_set_id: string, footer_text?: string} */
    public static function import(string $html, ?string $layoutSetId = null, array $options = []): array
    {
        if ($layoutSetId === null || $layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
            throw new RuntimeException(t('import.layout_set_required'));
        }

        $blocks = self::parseBlocks($html);
        $title = self::extractTitle($blocks);
        $options = self::normalizeOptions($options);
        $built = self::buildSlidesFromLayoutSet($blocks, $layoutSetId, $options);
        $slides = self::applyHierarchyNotes($built['slides'], (bool)$options['hierarchyNotes'], (string)$options['hierarchyStyle']);
        $slides = self::annotateNextScripture($slides);
        $slides = self::applySkips($slides, $options['skipSlides'], $options['skipNotes'], $options['ownNotes']);
        $slides = self::annotateNextScripture($slides);
        $warnings = $built['warnings'] ?? [];

        if (empty($slides)) {
            $slides = [[
                'id' => Storage::generateId(4),
                'background' => ['type' => 'color', 'value' => '#111111'],
                'transition' => 'slide',
                'autoAdvance' => 0,
                'notes' => '',
                'objects' => [],
            ]];
            $warnings[] = t('import.logos_no_slides');
        }

        $out = [
            'slides' => $slides,
            'title' => $title,
            'width' => DEFAULT_SLIDE_WIDTH,
            'height' => DEFAULT_SLIDE_HEIGHT,
            'warnings' => $warnings,
            'layout_set_id' => $layoutSetId,
        ];
        if (($built['footer_text'] ?? '') !== '') {
            $out['footer_text'] = $built['footer_text'];
        }
        return $out;
    }

    /**
     * Dry-Run für Import-Vorschau (legt keine Präsentation an).
     *
     * @return array{title: string, slide_count: int, slides: list<array>, sections: list<array>, warnings: list<string>, footer_text: string}
     */
    public static function planImport(string $html, string $layoutSetId, array $options = []): array
    {
        if (!LayoutSet::isLayoutSet($layoutSetId)) {
            throw new RuntimeException(t('import.layout_set_required'));
        }
        $blocks = self::parseBlocks($html);
        $title = self::extractTitle($blocks);
        $setMeta = Presentation::getMeta($layoutSetId) ?? [];
        $width = max(100, (int)($setMeta['width'] ?? DEFAULT_SLIDE_WIDTH));
        $height = max(100, (int)($setMeta['height'] ?? DEFAULT_SLIDE_HEIGHT));
        $built = self::buildSlidesFromLayoutSet($blocks, $layoutSetId, self::normalizeOptions($options));
        $opt = self::normalizeOptions($options);
        /* Vor `applyHierarchyNotes` ablesen — die räumt das interne Feld weg. */
        $mergeInfo = self::scriptureMergeInfo($built['slides'], $layoutSetId, $setMeta);
        $built['slides'] = self::applyHierarchyNotes($built['slides'], (bool)$opt['hierarchyNotes'], (string)$opt['hierarchyStyle']);
        $built['slides'] = self::annotateNextScripture($built['slides']);
        $sections = $built['sections'] ?? [];
        $sectionSlideCount = array_sum(array_map(
            fn($s) => (int)($s['slide_count'] ?? 0),
            $sections
        ));

        $vorschau = self::summarizeSlidesForPreview($built['slides']);
        foreach ($mergeInfo as $i => $info) {
            if (isset($vorschau[$i])) {
                /* Bewusst ueberschreibend zusammenfuehren: `+=` wuerde vorhandene
                   Schluessel stehen lassen. Der Folienschluessel (`key`) und der
                   Zusammenlege-Schluessel (`mergeKey`) sind verschiedene Dinge. */
                $vorschau[$i] = array_merge($vorschau[$i], $info);
            }
        }

        return [
            'title' => $title,
            'slide_count' => count($built['slides']),
            'width' => $width,
            'height' => $height,
            'preamble_slide_count' => max(0, count($built['slides']) - $sectionSlideCount),
            'slides' => $vorschau,
            'sections' => $sections,
            'warnings' => $built['warnings'] ?? [],
            'footer_text' => $built['footer_text'] ?? '',
        ];
    }

    public static function isLogosExport(string $html): bool
    {
        if (preg_match('/<script[^>]+id=["\']slideforge-source-data["\']/i', $html)) {
            return false;
        }
        return (bool)preg_match(
            '/sermonpromptrichtextstyle|lighttextstyle|ref\.ly\/|Export(?:iert aus|ed from)\s+<a[^>]+logos\.com/i',
            $html
        );
    }

    /** @return list<array{type: string, text: string, level?: int}> */
    private static function parseBlocks(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return [];
        }

        $blocks = [];
        $seenDocumentTitle = false;
        $inScriptureVerses = false;

        foreach ($body->childNodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($node->nodeName);
            if ($tag === 'br') {
                continue;
            }
            if ($tag === 'div' && self::isFooter($node)) {
                continue;
            }
            if ($tag === 'style') {
                continue;
            }

            $text = self::nodeText($node);
            if ($text === '' && !in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5'], true)) {
                $inScriptureVerses = false;
                continue;
            }

            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5'], true)) {
                $level = (int)substr($tag, 1);
                if ($tag === 'h1' && !$seenDocumentTitle && self::isDocumentTitle($node)) {
                    $seenDocumentTitle = true;
                    $blocks[] = ['type' => 'document_title', 'text' => $text];
                    $inScriptureVerses = false;
                    continue;
                }
                if (!$seenDocumentTitle && $tag === 'h1') {
                    $seenDocumentTitle = true;
                }
                $blocks[] = ['type' => 'heading' . $level, 'text' => $text, 'level' => $level];
                $inScriptureVerses = false;
                continue;
            }

            if ($tag !== 'p') {
                $inScriptureVerses = false;
                continue;
            }

            if (!$seenDocumentTitle) {
                $blocks[] = ['type' => 'meta', 'text' => $text];
                continue;
            }

            $class = $node->getAttribute('class');
            if (str_contains($class, 'sermonpromptrichtextstyle')) {
                $blocks[] = ['type' => 'prompt', 'text' => $text];
                $inScriptureVerses = false;
                continue;
            }
            if (str_contains($class, 'lighttextstyle')) {
                $blocks[] = ['type' => 'lighttext', 'text' => $text];
                $inScriptureVerses = false;
                continue;
            }
            /* Aufzählung VOR der Bibelstellen-Erkennung prüfen: Ein Predigtpunkt bleibt
               ein Predigtpunkt, auch wenn in ihm eine Stelle verlinkt ist. Vorher gewann
               hasRefLyLink(), und in einer typischen Predigt verlor dadurch die Mehrheit
               der Punkte ihre Listen-Eigenschaft (gemessen: 25 von 35). Ein Aufzählungs-
               zeichen plus Einzug ist das eindeutigere Merkmal als ein Link irgendwo im
               Fliesstext. */
            if (self::isListParagraph($node, $text)) {
                $blocks[] = ['type' => 'list_item', 'text' => $text, 'bold' => self::boldLeadText($node)];
                $inScriptureVerses = false;
                continue;
            }
            if (self::hasRefLyLink($node)) {
                $blocks[] = ['type' => 'scripture_inline', 'text' => $text];
                $inScriptureVerses = false;
                continue;
            }
            if (self::isScriptureReferenceLine($node, $text)) {
                // text = ohne Übersetzungskürzel, text_full = mit; der Dialog wählt später aus.
                $blocks[] = [
                    'type' => 'scripture_block',
                    'text' => self::refTextWithoutLightText($node, $text),
                    'text_full' => $text,
                    'part' => 'ref',
                ];
                $inScriptureVerses = true;
                continue;
            }
            if ($inScriptureVerses) {
                $blocks[] = ['type' => 'scripture_block', 'text' => $text, 'part' => 'verse'];
                continue;
            }

            $blocks[] = ['type' => 'normal', 'text' => $text];
        }

        return $blocks;
    }

    /** @param list<array{type: string, text: string, level?: int, part?: string}> $blocks */
    private static function extractTitle(array $blocks): string
    {
        foreach ($blocks as $b) {
            if ($b['type'] === 'document_title' && trim($b['text']) !== '') {
                return trim($b['text']);
            }
        }
        foreach ($blocks as $b) {
            if (str_starts_with($b['type'], 'heading') && trim($b['text']) !== '') {
                return trim($b['text']);
            }
        }
        return t('import.default_title');
    }

    /**
     * Teilt Blöcke in Vorspann (vor erstem H1) und H1-Abschnitte.
     *
     * @param list<array{type: string, text: string, level?: int, part?: string}> $blocks
     * @return array{preamble: list<array>, sections: list<array{h1: string, blocks: list<array>}>}
     */
    private static function splitIntoSections(array $blocks): array
    {
        $preamble = [];
        $sections = [];
        $current = null;

        foreach ($blocks as $block) {
            if ($block['type'] === 'heading1') {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = ['h1' => $block['text'], 'blocks' => []];
                continue;
            }
            if ($current === null) {
                $preamble[] = $block;
            } else {
                $current['blocks'][] = $block;
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        return ['preamble' => $preamble, 'sections' => $sections];
    }

    /** @param list<array> $slides
     * @return list<array{layoutKey: string, label: string, preview: string, thumbHtml: string}>
     */
    /**
     * Was die Vorschau über das Zusammenlegen von Bibelstellen wissen muss.
     *
     * Je Folie: die Kennung der Stelle, ob sie bereits zusammengelegt ist (violetter
     * Rahmen, Knopf «Trennen») und ob sie mit der nächsten zusammenpasst (grüner
     * Rahmen, Knopf «Zusammenfassen»).
     *
     * Eine Stelle, die selbst schon auf mehrere Folien aufgeteilt werden musste, kommt
     * nicht in Frage: ihre Folgefolien tragen keine Kennung, der Nachbar passt also nie.
     *
     * @param list<array<string,mixed>> $slides
     * @return array<int, array{mergeKey:string, merged:bool, canMergeNext:bool}>
     */
    private static function scriptureMergeInfo(array $slides, string $setId, array $setMeta): array
    {
        $kap = LayoutSet::roleTextCapacity($setId, 'scripture_verse', $setMeta)
            ?? LayoutSet::roleTextCapacity($setId, 'scripture_block', $setMeta);
        $out = [];
        $anzahl = count($slides);
        for ($i = 0; $i < $anzahl; $i++) {
            $eigen = $slides[$i]['_scripture'] ?? null;
            if (!is_array($eigen) || ($eigen['key'] ?? '') === '') {
                continue;
            }
            $merged = !empty($eigen['merged']);
            $passt = false;
            if (!$merged && $kap !== null && isset($slides[$i + 1]['_scripture'])) {
                $naechste = $slides[$i + 1]['_scripture'];
                if (is_array($naechste) && empty($naechste['merged'])) {
                    $zeilen = self::geschaetzteZeilen([
                        (string)($eigen['ref'] ?? ''), (string)($eigen['verse'] ?? ''),
                        (string)($naechste['ref'] ?? ''), (string)($naechste['verse'] ?? ''),
                    ], (int)$kap['charsPerLine']);
                    $passt = $zeilen <= (int)$kap['lines'];
                }
            }
            $out[$i] = [
                'mergeKey' => (string)$eigen['key'],
                'merged' => $merged,
                'canMergeNext' => $passt,
            ];
        }
        return $out;
    }

    private static function summarizeSlidesForPreview(array $slides): array
    {
        $out = [];
        $keys = self::slideKeys($slides);
        foreach ($slides as $i => $slide) {
            $preview = '';
            foreach ($slide['objects'] ?? [] as $obj) {
                if (($obj['type'] ?? '') === 'text' && trim((string)($obj['text'] ?? '')) !== '') {
                    $preview = trim((string)$obj['text']);
                    break;
                }
            }
            if ($preview === '' && trim((string)($slide['notes'] ?? '')) !== '') {
                $preview = t('import.preview_notes') . ' ' . mb_substr(trim((string)$slide['notes']), 0, 60);
            }
            $out[] = [
                'key' => $keys[$i],
                'layoutKey' => (string)($slide['layoutKey'] ?? ''),
                'label' => (string)($slide['label'] ?? ''),
                'preview' => mb_substr($preview, 0, 120),
                'notes' => self::noteParts((string)($slide['notes'] ?? ''), $keys[$i]),
                'thumbHtml' => SlideRenderer::renderSlideThumbnailHtml($slide, null),
            ];
        }
        return $out;
    }

    /**
     * Schreibt auf jede Folie, die vor einer Bibelstelle steht, einen Hinweis in die
     * Notizen: welche Stelle kommt und wie sie anfängt. Der Vortragende sieht damit auf
     * der Vorschau seiner Konsole, was der nächste Klick bringt, statt sich erinnern zu
     * müssen — besonders bei mehreren Stellen kurz hintereinander.
     */
    /**
     * Schreibt den Gliederungspfad als Markdown-Überschriften an den Anfang der
     * Notizen: `# Abschnitt`, `## Punkt`, `### Unterpunkt`. Der Vortragende sieht damit
     * auf jeder Folie, wo er in der Predigt steht.
     *
     * Räumt in jedem Fall das interne Feld `_path` weg — es gehört nicht in die
     * gespeicherte Präsentation.
     */
    /**
     * Schreibt den Gliederungspfad vor die Notizen.
     *
     * Zwei Schreibweisen, weil in den Notizen die Breite reichlicher ist als die Höhe:
     * `headings` setzt jede Ebene als eigene Überschrift untereinander, `path` legt sie
     * als eine Zeile nebeneinander — „Hauptpunkt 1 > Unterpunkt 7 > Unterunterpunkt 2".
     */
    private static function applyHierarchyNotes(array $slides, bool $an, string $stil = 'headings'): array
    {
        $alsPfad = $stil === 'path';
        foreach ($slides as $i => $slide) {
            $pfad = is_array($slide['_path'] ?? null) ? $slide['_path'] : [];
            unset($slide['_path']);
            // Auch die Kennung der Bibelstelle ist nur für die Vorschau da.
            unset($slide['_scripture']);
            if ($an && $pfad !== []) {
                $teile = [];
                foreach (array_values($pfad) as $ebene => $titel) {
                    $titel = trim((string)$titel);
                    if ($titel === '') {
                        continue;
                    }
                    $teile[] = $alsPfad ? $titel : str_repeat('#', min(6, $ebene + 1)) . ' ' . $titel;
                }
                if ($teile !== []) {
                    $have = trim((string)($slide['notes'] ?? ''));
                    /* Fett, damit die Zeile sich vom Fliesstext abhebt — als Überschrift
                       ginge der Platzgewinn in der Höhe gleich wieder verloren. */
                    $kopf = $alsPfad
                        ? '**' . implode(' > ', $teile) . '**'
                        : implode("\n", $teile);
                    $slide['notes'] = $have === '' ? $kopf : $kopf . "\n\n" . $have;
                }
            }
            $slides[$i] = $slide;
        }
        return $slides;
    }

    private static function annotateNextScripture(array $slides): array
    {
        /* Zuerst eigene Ankündigungen entfernen: Der Durchgang läuft zweimal — einmal
           vor der Abwahl, damit Vorschau und Import dieselben Notizteile und damit
           dieselben Schlüssel haben, und einmal danach, damit keine abgewählte Stelle
           mehr angekündigt wird. Ohne dieses Aufräumen stünde sie doppelt. */
        $marke = trim(explode('{ref}', t('import.note_next_verse'))[0]);
        if ($marke !== '') {
            foreach ($slides as $i => $slide) {
                $notes = (string)($slide['notes'] ?? '');
                if ($notes === '' || !str_contains($notes, $marke)) {
                    continue;
                }
                $zeilen = array_values(array_filter(
                    preg_split('/\R/u', $notes) ?: [],
                    fn($z) => !str_starts_with(trim($z), $marke)
                ));
                $slides[$i]['notes'] = trim(implode("\n", $zeilen));
            }
        }

        $count = count($slides);
        for ($i = 1; $i < $count; $i++) {
            [$ref, $verse] = self::scriptureTexts($slides[$i]);
            if ($ref === '' && $verse === '') {
                continue;
            }
            $words = preg_split('/\s+/u', trim($verse)) ?: [];
            $anfang = trim(implode(' ', array_slice($words, 0, 6)));
            if ($ref === '' && $anfang === '') {
                continue;
            }
            $line = str_replace(
                ['{ref}', '{anfang}'],
                [$ref !== '' ? $ref : '…', $anfang],
                t('import.note_next_verse')
            );
            $have = trim((string)($slides[$i - 1]['notes'] ?? ''));
            $slides[$i - 1]['notes'] = $have === '' ? $line : $have . "\n" . $line;
        }
        return $slides;
    }

    /**
     * Stellenangabe und Verstext einer Folie, falls es eine Bibelstellen-Folie ist.
     * Sonst zwei leere Zeichenketten.
     *
     * @return array{0: string, 1: string}
     */
    private static function scriptureTexts(array $slide): array
    {
        $ref = '';
        $verse = '';
        $block = '';
        foreach ($slide['objects'] ?? [] as $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                continue;
            }
            $role = (string)($obj['setRole'] ?? '');
            $text = trim((string)($obj['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if ($role === 'scripture_ref') {
                $ref = $text;
            } elseif ($role === 'scripture_verse') {
                $verse = $text;
            } elseif ($role === 'scripture_block') {
                $block = $text;
            }
        }
        if ($ref === '' && $verse === '' && $block !== '') {
            // Zusammengelegte Fassung: erste Zeile ist die Stelle, der Rest der Text.
            $parts = preg_split('/\R+/u', $block, 2) ?: [];
            $ref = trim($parts[0] ?? '');
            $verse = trim($parts[1] ?? '');
        }
        return [$ref, $verse];
    }

    /**
     * Inhaltsbasierter Schlüssel einer geplanten Folie.
     *
     * Der Dialog rechnet die Vorschau bei jeder Einstellungsänderung neu; eine über die
     * Position vergebene Kennung würde dabei wandern, und eine abgeschaltete Folie träfe
     * nach dem Neurechnen die falsche. Über den Inhalt bleibt die Zuordnung stabil,
     * solange die Folie inhaltlich dieselbe ist.
     */
    /**
     * Schlüssel für eine ganze Folienliste, mit Zähler bei inhaltsgleichen Folien.
     *
     * Ein Bibelvers kann in einer Predigt mehrfach auftreten (bei Urs steht
     * „2. Könige 18,19" dreimal drin). Ohne Zähler bekämen diese Folien denselben
     * Schlüssel, und das Abwählen einer träfe alle.
     *
     * @return list<string>
     */
    private static function slideKeys(array $slides): array
    {
        $seen = [];
        $out = [];
        foreach ($slides as $slide) {
            $base = self::slideKey($slide);
            $n = ($seen[$base] = ($seen[$base] ?? 0) + 1);
            $out[] = $n === 1 ? $base : $base . '-' . $n;
        }
        return $out;
    }

    public static function slideKey(array $slide): string
    {
        $parts = [(string)($slide['layoutKey'] ?? '')];
        foreach ($slide['objects'] ?? [] as $obj) {
            if (($obj['type'] ?? '') === 'text') {
                $parts[] = trim((string)($obj['text'] ?? ''));
            }
        }
        return substr(sha1(implode("\x1f", $parts)), 0, 16);
    }

    /**
     * Zerlegt die Notizen einer Folie in einzeln abwählbare Teile: jeder Listenpunkt
     * für sich, dazwischenliegender Fliesstext je Absatz.
     *
     * @return list<array{key: string, text: string, list: bool}>
     */
    public static function noteParts(string $notes, string $scope = ''): array
    {
        $notes = trim($notes);
        if ($notes === '') {
            return [];
        }
        $out = [];
        $paragraph = [];
        $flush = function () use (&$paragraph, &$out, $scope): void {
            if ($paragraph === []) {
                return;
            }
            $text = trim(implode("\n", $paragraph));
            $paragraph = [];
            if ($text !== '') {
                $out[] = ['key' => substr(sha1($scope . 'p' . $text), 0, 12), 'text' => $text, 'list' => false];
            }
        };
        foreach (preg_split('/\R/u', $notes) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flush();
                continue;
            }
            // Markdown-Überschrift: eigener Teil, damit jede Gliederungsebene für sich
            // steht und einzeln abgewählt werden kann.
            if (preg_match('/^#{1,6}\s+\S/u', $trimmed)) {
                $flush();
                $out[] = ['key' => substr(sha1($scope . 'h' . $trimmed), 0, 12), 'text' => $trimmed, 'list' => false];
                continue;
            }
            if (preg_match('/^[-*\x{2022}]\s+(.*)$/u', $trimmed, $m)) {
                $flush();
                $item = trim($m[1]);
                if ($item !== '') {
                    $out[] = ['key' => substr(sha1($scope . 'l' . $item), 0, 12), 'text' => $item, 'list' => true];
                }
                continue;
            }
            $paragraph[] = $trimmed;
        }
        $flush();
        return $out;
    }

    /** Setzt die Notizen aus den verbliebenen Teilen wieder zusammen. */
    private static function notesFromParts(array $parts): string
    {
        $lines = [];
        foreach ($parts as $part) {
            $lines[] = !empty($part['list']) ? '- ' . $part['text'] : $part['text'];
        }
        return trim(implode("\n", $lines));
    }

    /**
     * Wendet die Abwahl aus dem Dialog an: ganze Folien fallen weg, einzelne
     * Notizteile ebenso. Beides über die inhaltsbasierten Schlüssel, damit es ein
     * Neurechnen der Vorschau übersteht.
     *
     * @param list<string> $skipSlides
     * @param list<string> $skipNotes
     */
    private static function applySkips(array $slides, array $skipSlides, array $skipNotes, array $ownNotes = []): array
    {
        if ($skipSlides === [] && $skipNotes === [] && $ownNotes === []) {
            return $slides;
        }
        $skipSlides = array_flip(array_map('strval', $skipSlides));
        $skipNotes = array_flip(array_map('strval', $skipNotes));

        $out = [];
        $keys = self::slideKeys($slides);
        foreach ($slides as $i => $slide) {
            if (isset($skipSlides[$keys[$i]])) {
                continue;
            }
            if ($skipNotes !== [] && trim((string)($slide['notes'] ?? '')) !== '') {
                $parts = array_values(array_filter(
                    self::noteParts((string)$slide['notes'], $keys[$i]),
                    fn(array $p) => !isset($skipNotes[$p['key']])
                ));
                $slide['notes'] = self::notesFromParts($parts);
            }
            $eigen = trim((string)($ownNotes[$keys[$i]] ?? ''));
            if ($eigen !== '') {
                $have = trim((string)($slide['notes'] ?? ''));
                $slide['notes'] = $have === '' ? $eigen : $have . "\n\n" . $eigen;
            }
            $out[] = $slide;
        }
        return $out;
    }

    /** @param list<array{type: string, text: string, level?: int, part?: string}> $blocks
     * @return array{slides: list<array>, footer_text: string, sections: list<array>, warnings: list<string>}
     */
    private static function buildSlidesFromLayoutSet(array $blocks, string $setId, array $options = []): array
    {
        LayoutSet::syncLayoutMap($setId);
        $setMeta = Presentation::getMeta($setId) ?? [];
        $settings = LayoutSet::logosImportSettings($setMeta);
        $notesOrder = LayoutSet::notesOrder($setMeta);
        $zoneOverrides = self::zoneOverrides($options);
        $zoneFor = fn(string $role) => $zoneOverrides[$role] ?? LayoutSet::zoneForRole($setMeta, $role);

        $slides = [];
        /* Gliederungspfad der gerade erzeugten Folie, je Ebene ein Eintrag. Wird beim
           Öffnen einer Folie mitgeschrieben, damit später `#`, `##`, `###` in die
           Notizen können — im Nachhinein liesse sich das nicht mehr zuordnen. */
        $path = [];
        $sectionSummaries = [];
        $warnings = [];
        $current = null;
        $notesBucket = [];
        $footerLines = [];

        $flushNotesToString = function () use (&$notesBucket, $notesOrder): string {
            if ($notesBucket === []) {
                return '';
            }
            $renderType = function (string $type, array $lines): string {
                $lines = array_values(array_filter(
                    array_map(fn($l) => trim((string)$l), $lines),
                    fn($l) => $l !== ''
                ));
                if ($lines === []) {
                    return '';
                }
                // Aufzählungen als eine Markdown-Liste — Zeilenumbruch, keine Leerzeile pro Punkt.
                if ($type === 'list_item') {
                    return self::formatListItemsMarkdown($lines);
                }
                return implode("\n\n", $lines);
            };
            $parts = [];
            $seen = [];
            foreach ($notesOrder as $type) {
                $seen[$type] = true;
                $block = $renderType($type, $notesBucket[$type] ?? []);
                if ($block !== '') {
                    $parts[] = $block;
                }
            }
            foreach ($notesBucket as $type => $lines) {
                if (empty($seen[$type])) {
                    $block = $renderType((string)$type, $lines);
                    if ($block !== '') {
                        $parts[] = $block;
                    }
                }
            }
            $notesBucket = [];
            return implode("\n\n", $parts);
        };

        $flushSlide = function () use (&$slides, &$current, $flushNotesToString) {
            if ($current === null) {
                return;
            }
            $extra = $flushNotesToString();
            if ($extra !== '') {
                $current['notes'] = trim(($current['notes'] ?? '') . (($current['notes'] ?? '') !== '' ? "\n\n" : '') . $extra);
            }
            if ($current['objects'] || trim($current['notes'] ?? '') !== '') {
                $slides[] = $current;
            }
            $current = null;
        };

        $openSlideWithRoles = function (string $layoutKey, array $contentByRole) use (
            &$current, $setId, $setMeta, $flushSlide, $flushNotesToString, &$path
        ) {
            $flushSlide();
            $contentByRole = array_filter(
                $contentByRole,
                fn($t) => trim((string)$t) !== '',
                ARRAY_FILTER_USE_BOTH
            );
            if ($contentByRole === []) {
                return;
            }

            $layout = null;
            if (count($contentByRole) > 1) {
                $layout = LayoutSet::bestLayoutForContent($setId, $contentByRole, $setMeta);
            }
            if ($layout === null) {
                $role = (string)array_key_first($contentByRole);
                $layout = LayoutSet::findLayoutForRole($setId, $role, $setMeta);
                if ($layout !== null) {
                    $layoutKey = (string)($layout['layoutKey'] ?? $layoutKey);
                }
            } elseif ($layout !== null) {
                $layoutKey = (string)($layout['layoutKey'] ?? $layoutKey);
            }
            if ($layout === null) {
                $layout = LayoutSet::findLayout($setId, $layoutKey);
            }

            $pendingNotes = $flushNotesToString();
            if ($layout) {
                $current = LayoutSet::fillSlideFromLayout($layout, $contentByRole, $pendingNotes, $setMeta);
            } else {
                $objects = [];
                foreach ($contentByRole as $role => $text) {
                    $objects[] = LayoutSet::makeRoleTextObject($role, $text, [], $setMeta);
                }
                $current = [
                    'id' => Storage::generateId(4),
                    'background' => ['type' => 'color', 'value' => '#111111'],
                    'transition' => 'slide',
                    'autoAdvance' => 0,
                    'notes' => $pendingNotes,
                    'layoutKey' => $layoutKey,
                    'objects' => $objects,
                ];
            }
            $current['_path'] = $path;
        };

        $openSlide = function (string $layoutKey, string $role, string $text) use ($setId, $setMeta, $openSlideWithRoles) {
            $resolvedKey = LayoutSet::resolveLayoutKeyForRole($setId, $role, $setMeta);
            $openSlideWithRoles($resolvedKey, [$role => $text]);
        };

        $addNote = function (string $type, string $text) use (&$notesBucket) {
            $text = trim($text);
            if ($text !== '') {
                $notesBucket[$type][] = $text;
            }
        };

        $addFooter = function (string $text) use (&$footerLines) {
            $text = trim($text);
            if ($text !== '') {
                $footerLines[] = $text;
            }
        };

        $routeBlock = function (array $block, bool $inSection = false) use (
            $setId, $setMeta, $zoneFor, $addNote, $addFooter, $openSlide, $openSlideWithRoles, &$slides
        ) {
            $type = $block['type'];

            if ($type === 'document_title') {
                $zone = $zoneFor('document_title');
                if ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    $addNote('document_title', $block['text']);
                } elseif ($zone === 'slides') {
                    $layout = LayoutSet::findLayoutForRole($setId, 'document_title', $setMeta);
                    $layoutKey = $layout
                        ? (string)($layout['layoutKey'] ?? 'document_title')
                        : LayoutSet::resolveLayoutKeyForRole($setId, 'document_title', $setMeta);
                    if ($layout) {
                        $slides[] = LayoutSet::fillSlideFromLayout($layout, ['document_title' => $block['text']], '', $setMeta);
                    } else {
                        $slides[] = [
                            'id' => Storage::generateId(4),
                            'background' => ['type' => 'color', 'value' => '#111111'],
                            'transition' => 'slide',
                            'autoAdvance' => 0,
                            'notes' => '',
                            'layoutKey' => $layoutKey,
                            'objects' => [LayoutSet::makeRoleTextObject('document_title', $block['text'], [], $setMeta)],
                        ];
                    }
                }
                return;
            }

            if (str_starts_with($type, 'heading')) {
                $zone = $zoneFor($type);
                if ($zone === 'unused') {
                    return;
                }
                if ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    $addNote($type, $block['text']);
                } elseif ($inSection && $type !== 'heading1') {
                    $openSlide($type, $type, $block['text']);
                } elseif (!$inSection) {
                    $openSlide($type, $type, $block['text']);
                }
                return;
            }

            if (in_array($type, ['normal', 'list_item', 'lighttext'], true)) {
                $zone = $zoneFor($type);
                if ($zone === 'slides') {
                    $openSlide($type, $type, $block['text']);
                } elseif ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    // In den Notizen genügt beim Aufzählungspunkt das Stichwort.
                    $noteText = $type === 'list_item'
                        ? self::listItemKeyword($block)
                        : $block['text'];
                    $addNote($type, $noteText);
                }
                return;
            }

            if (in_array($type, LayoutSet::LOGOS_ZONE_ROLES, true)) {
                $zone = $zoneFor($type);
                if ($zone === 'unused') {
                    return;
                }
                if ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    $addNote($type, $block['text']);
                } elseif ($zone === 'slides') {
                    $openSlide($type, $type, $block['text']);
                }
                return;
            }

            $addNote($type, $block['text']);
        };

        $keepTranslation = !empty($options['scriptureTranslation']);
        /* Schweizer Schreibweise: In der Schweiz gibt es das ß nicht, dort steht immer
           ss. Betroffen ist nur der Bibeltext - die eigenen Zeilen der Predigt sind
           ohnehin so geschrieben. Die Ersetzung darf pauschal sein, weil die Schweizer
           Regel keine Ausnahmen kennt. */
        $swissSs = !empty($options['swissSs']);
        $parseScriptureRun = function (array $sectionBlocks, int $start) use ($zoneFor, $keepTranslation, $swissSs): array {
            $ss = function (string $t) use ($swissSs): string {
                return $swissSs ? str_replace(['ß', 'ẞ'], ['ss', 'SS'], $t) : $t;
            };
            $n = count($sectionBlocks);
            $i = $start;
            $refParts = [];
            $verseParts = [];
            while ($i < $n && $sectionBlocks[$i]['type'] === 'scripture_block') {
                if (($sectionBlocks[$i]['part'] ?? '') === 'ref') {
                    if ($refParts !== [] || $verseParts !== []) {
                        break; // nächste Bibelstelle → eigener Lauf, eigene Folie
                    }
                    $refParts[] = $ss($keepTranslation
                        ? ($sectionBlocks[$i]['text_full'] ?? $sectionBlocks[$i]['text'])
                        : $sectionBlocks[$i]['text']);
                } else {
                    $verseParts[] = $ss($sectionBlocks[$i]['text']);
                }
                $i++;
            }
            return [
                'consumed' => $i - $start,
                'refText' => trim(implode("\n", array_filter($refParts))),
                'verseText' => trim(implode("\n\n", array_filter($verseParts))),
                'zone' => $zoneFor('scripture_block'),
            ];
        };

        $autoMerge = !empty($options['scriptureMerge']);
        $mergeListe = is_array($options['mergeScripture'] ?? null) ? $options['mergeScripture'] : [];
        $splitListe = is_array($options['splitScripture'] ?? null) ? $options['splitScripture'] : [];

        /** Stabile Kennung einer Bibelstelle — unabhängig davon, wie sie später auf
         *  Folien verteilt wird. Darüber merkt sich der Dialog die von Hand gewählten
         *  Zusammenlegungen. */
        $scriptureKey = static fn(string $ref, string $verse): string
            => substr(sha1(trim($ref) . "\x1f" . trim($verse)), 0, 16);

        /** Passen zwei Stellen zusammen auf eine Folie? Gemessen am Platz des Feldes. */
        $scripturePasst = function (array $a, array $b) use ($setId, $setMeta): bool {
            $kap = LayoutSet::roleTextCapacity($setId, 'scripture_verse', $setMeta)
                ?? LayoutSet::roleTextCapacity($setId, 'scripture_block', $setMeta);
            if ($kap === null) {
                return false;   // ohne Mass wird nicht geraten
            }
            $zeilen = self::geschaetzteZeilen(
                [$a['refText'], $a['verseText'], $b['refText'], $b['verseText']],
                (int)$kap['charsPerLine']
            );
            return $zeilen <= (int)$kap['lines'];
        };

        /**
         * Kommt die nächste Bibelstelle mit auf diese Folie? Von Hand gewählt gilt
         * immer; sonst nur, wenn die Automatik an ist **und** der gemessene Platz
         * reicht. Eine von Hand getrennte Stelle bleibt getrennt.
         *
         * @param list<array<string,mixed>> $bloecke
         * @return array<string,mixed>|null
         */
        $scriptureZweit = function (array $bloecke, int $nachher, array $run) use (
            $scriptureKey, $scripturePasst, $parseScriptureRun, $autoMerge, $mergeListe, $splitListe
        ): ?array {
            if ($nachher >= count($bloecke) || ($bloecke[$nachher]['type'] ?? '') !== 'scripture_block') {
                return null;
            }
            $key = $scriptureKey($run['refText'], $run['verseText']);
            if (in_array($key, $splitListe, true)) {
                return null;
            }
            $zweit = $parseScriptureRun($bloecke, $nachher);
            if (trim((string)$zweit['refText']) === '' && trim((string)$zweit['verseText']) === '') {
                return null;
            }
            if (in_array($key, $mergeListe, true)) {
                return $zweit;
            }
            return ($autoMerge && $scripturePasst($run, $zweit)) ? $zweit : null;
        };

        $emitScripture = function (
            string $refText,
            string $verseText,
            ?string $headingRole = null,
            ?string $headingText = null,
            ?array $zweite = null
        ) use (
            $setId, $setMeta, $zoneFor, $settings, $openSlideWithRoles, $addFooter, $addNote,
            &$current, $scriptureKey
        ) {
            $zone = $zoneFor('scripture_block');
            $combined = trim(implode("\n\n", array_filter([$refText, $verseText])));
            if ($combined === '') {
                return;
            }
            if ($zone === 'footer') {
                $addFooter($combined);
                return;
            }
            if ($zone === 'custom') {
                $addNote('scripture_block', $combined);
                return;
            }
            if ($zone !== 'slides') {
                return;
            }

            $layout = LayoutSet::findLayoutForRole($setId, 'scripture_block', $setMeta);
            $layoutKey = $layout
                ? (string)($layout['layoutKey'] ?? 'scripture_block')
                : LayoutSet::resolveLayoutKeyForRole($setId, 'scripture_block', $setMeta);
            $hasRefRole = $layout && LayoutSet::layoutHasRole($layout, 'scripture_ref');

            /* **Zwei Stellen auf einer Folie.** Dann gibt es keine getrennte Zeile für
               die Stellenangabe mehr — bei zwei Stellen wäre nicht zu erkennen, welche
               zu welchem Text gehört. Beide stehen darum fett über ihrem Vers im selben
               Feld, und es wird nicht noch einmal aufgeteilt: dass es zusammen passt,
               ist vorher gemessen worden (von Hand gewählt: die Person hat es so
               gewollt, auch wenn es knapp wird). */
            if ($zweite !== null) {
                $teile = [];
                foreach ([[$refText, $verseText], [(string)$zweite['refText'], (string)$zweite['verseText']]] as $paar) {
                    [$ref, $vers] = $paar;
                    $block = trim(implode("\n", array_filter([
                        $ref !== '' ? '**' . $ref . '**' : '',
                        $vers,
                    ])));
                    if ($block !== '') {
                        $teile[] = $block;
                    }
                }
                if ($teile === []) {
                    return;
                }
                /* Eine **sichtbare** Leerzeile zwischen den beiden Stellen (Urs,
                   24.10.: «mach zwischen den Bibelversen eine Zeile abstand»).
                   Eine leere Zeile allein genügt nicht: `Markdown::render()`
                   verschluckt sie, auch eine Zeile mit Leerzeichen. Ein geschütztes
                   Leerzeichen (U+00A0) bleibt stehen und ergibt die Zeile Abstand. */
                $zusammen = implode("\n\u{00A0}\n", $teile);
                $openSlideWithRoles(
                    $layoutKey,
                    [($hasRefRole ? 'scripture_verse' : 'scripture_block') => $zusammen]
                );
                if (is_array($current)) {
                    $current['_scripture'] = [
                        'key' => $scriptureKey($refText, $verseText),
                        'ref' => $refText,
                        'verse' => $verseText,
                        'merged' => true,
                    ];
                }
                return;
            }

            /* Lange Bibeltexte auf mehrere Folien verteilen. Ohne das stand der ganze
               Vers auf einer Folie - in Urs' Predigt bis zu 856 Zeichen, unlesbar. Die
               Zeichengrenze des Folien-Sets galt bisher nur für Fliesstext und fasste
               dort bloss Absätze zusammen; einen einzelnen langen Text trennte sie nie.
               Getrennt wird an Satzgrenzen, die Stellenangabe steht auf jeder Folie. */
            $max = $settings['textMaxCharacters'];
            $max = ($max === 'layout' || (int)$max <= 0) ? 0 : (int)$max;
            $budget = $max;
            if ($budget > 0 && !$hasRefRole && $refText !== '') {
                // Ohne eigenes Feld für die Stelle teilt sie sich die Folie mit dem Vers.
                $budget = max(120, $budget - mb_strlen($refText) - 2);
            }
            /* Zuerst versgenau versuchen - dann trägt jede Folie ihre richtige Spanne.
               Klappt das nicht, an Satzgrenzen trennen und die volle Stelle wiederholen. */
            $verseChunks = self::splitScriptureByVerses($refText, $verseText, $budget);
            if ($verseChunks === null) {
                $texts = ($budget > 0 && $verseText !== '')
                    ? self::splitTextIntoChunks($verseText, $budget)
                    : [$verseText];
                $verseChunks = array_map(fn(string $t) => ['ref' => $refText, 'text' => $t], $texts);
            }

            $buildContent = function (array $chunk) use ($hasRefRole): array {
                $content = [];
                $chunkRef = (string)($chunk['ref'] ?? '');
                $chunkText = (string)($chunk['text'] ?? '');
                if ($hasRefRole) {
                    if ($chunkRef !== '') {
                        $content['scripture_ref'] = $chunkRef;
                    }
                    if ($chunkText !== '') {
                        $content['scripture_verse'] = $chunkText;
                    }
                } else {
                    $content['scripture_block'] = trim(implode("\n\n", array_filter([$chunkRef, $chunkText])));
                }
                return $content;
            };

            $content = $buildContent($verseChunks[0]);
            $scriptureHeading = $settings['scriptureHeading'];
            if ($headingRole !== null && $headingText !== null && trim($headingText) !== '') {
                if ($scriptureHeading === 'always_combined') {
                    $content = [$headingRole => $headingText] + $content;
                } elseif ($scriptureHeading === 'combine_if_layout_fits') {
                    $try = [$headingRole => $headingText] + $content;
                    if (LayoutSet::bestLayoutForContent($setId, $try, $setMeta) !== null) {
                        $content = $try;
                    } else {
                        $openSlideWithRoles(
                            LayoutSet::resolveLayoutKeyForRole($setId, $headingRole, $setMeta),
                            [$headingRole => $headingText]
                        );
                    }
                } elseif ($scriptureHeading === 'scripture_always_separate') {
                    $openSlideWithRoles(
                        LayoutSet::resolveLayoutKeyForRole($setId, $headingRole, $setMeta),
                        [$headingRole => $headingText]
                    );
                }
            }

            $chunkTotal = count($verseChunks);
            /* Ist eine Stelle auf mehrere Folien verteilt, gehört das in die Notizen:
               Der Vortragende sieht dann, dass er gleich weiterklicken kann, statt zu
               meinen, der Vers sei zu Ende. */
            /* Direkt an die eben geöffnete Folie hängen, nicht über den Notiz-Eimer:
               der wird erst beim Öffnen der NÄCHSTEN Folie abgeräumt, der Hinweis stünde
               also eine Folie zu früh. */
            $partNote = function (int $n) use ($chunkTotal, &$current): void {
                if ($chunkTotal < 2 || !is_array($current)) {
                    return;
                }
                $key = $n < $chunkTotal ? 'import.note_verse_more' : 'import.note_verse_last';
                $line = str_replace(['{n}', '{total}'], [(string)$n, (string)$chunkTotal], t($key));
                $have = trim((string)($current['notes'] ?? ''));
                $current['notes'] = $have === '' ? $line : $have . "\n" . $line;
            };

            if ($content !== []) {
                $openSlideWithRoles($layoutKey, $content);
                $partNote(1);
                /* Kennung der Stelle an der Folie lassen: die Vorschau bietet darüber
                   das Zusammenlegen an. `applyHierarchyNotes` räumt das Feld weg,
                   bevor etwas gespeichert wird. */
                if (is_array($current)) {
                    $current['_scripture'] = [
                        'key' => $scriptureKey($refText, $verseText),
                        'ref' => $refText,
                        'verse' => $verseText,
                        'merged' => false,
                    ];
                }
            }
            // Folgeabschnitte des Verses: je eine weitere Folie, ohne Überschrift.
            foreach (array_slice($verseChunks, 1) as $idx => $chunk) {
                $more = $buildContent($chunk);
                if ($more !== []) {
                    $openSlideWithRoles($layoutKey, $more);
                    $partNote($idx + 2);
                }
            }
        };

        /**
         * Gliederung zusammengefasst: mehrere Unterpunkte **samt ihren Stichpunkten**
         * auf eine Folie, gefüllt nach dem Platz im Inhaltsfeld des Folien-Sets.
         *
         * Urs, 24.10.2026: «Die möchte ich nun gerne auf einigen Folien
         * zusammenfassen» · «Titel mit ihren Stichpunkten» · «Immer so viel wie Platz
         * da ist…» · «Die Überschrift ist dann immer die darüber liegende Folie».
         *
         * Zwei Dinge, die dabei nicht verhandelbar sind:
         * - **Ein Block wird nie getrennt.** Passt er nicht mehr, kommt er ganz auf
         *   die nächste Folie — ein Titel ohne seine Stichpunkte wäre wertlos.
         * - **Ohne Mass wird nicht geraten.** Kennt das Set keine Feldgrösse für die
         *   Rolle, bleibt es bei einem Punkt je Folie, also beim bisherigen Verhalten.
         *
         * Gemessen wird in **Zeilen**, nicht in Zeichen: Titel und Stichpunkte fangen
         * je eine neue Zeile an, da zählt die Zeichenzahl allein zu wenig. Die
         * Zeichenbreite geht als Umbruchschätzung ein (`roleTextCapacity`).
         *
         * @param list<array{titel:string, items:list<string>}> $gruppen
         */
        $emitOutlineSummary = function (
            array $gruppen,
            string $titel,
            string $titelRolle,
            string $unterRolle
        ) use ($setId, $setMeta, $openSlideWithRoles): void {
            $kapazitaet = LayoutSet::roleTextCapacity($setId, $unterRolle, $setMeta);
            $zeilenFrei = max(1, (int)($kapazitaet['lines'] ?? 1));
            $proZeile = max(10, (int)($kapazitaet['charsPerLine'] ?? 10));

            $textVon = function (array $g): string {
                $teile = ['**' . trim((string)$g['titel']) . '**'];
                $liste = self::formatListItemsMarkdown($g['items']);
                if ($liste !== '') {
                    $teile[] = $liste;
                }
                return implode("\n", $teile);
            };
            $zeilenVon = function (array $g) use ($proZeile): int {
                $n = 1; // die Titelzeile
                foreach ($g['items'] as $item) {
                    $laenge = mb_strlen('- ' . trim((string)$item));
                    $n += max(1, (int)ceil($laenge / $proZeile));
                }
                return $n;
            };

            $stapel = [];
            $belegt = 0;
            $abliefern = function () use (
                &$stapel, &$belegt, $titel, $titelRolle, $unterRolle, $openSlideWithRoles, $textVon
            ): void {
                if ($stapel === []) {
                    return;
                }
                /* Der Inhalt muss die ERSTE Rolle sein — dieselbe Falle wie bei der
                   Gliederungsübersicht: findet `bestLayoutForContent` für
                   «Überschrift + Inhalt» keine Vorlage, richtet sich die Notlösung
                   nach der ersten Rolle, und dann läge alles auf der reinen
                   Überschriften-Vorlage übereinander. */
                $inhalt = [$unterRolle => implode("\n\n", array_map($textVon, $stapel))];
                if ($titel !== '' && $titelRolle !== '') {
                    $inhalt[$titelRolle] = $titel;
                }
                $openSlideWithRoles($unterRolle, $inhalt);
                $stapel = [];
                $belegt = 0;
            };

            foreach ($gruppen as $g) {
                // Leerzeile zwischen zwei Blöcken, damit sie auseinander zu halten sind.
                $braucht = $zeilenVon($g) + ($stapel === [] ? 0 : 1);
                if ($stapel !== [] && $belegt + $braucht > $zeilenFrei) {
                    $abliefern();
                    $braucht = $zeilenVon($g);
                }
                $stapel[] = $g;
                $belegt += $braucht;
            }
            $abliefern();
        };

        $summaryVon = (int)($options['outlineSummary'] ?? 0);

        $processSection = function (array $section, bool $h1BereitsGezeigt = false) use (
            $settings, $zoneFor, $routeBlock, $openSlide, $openSlideWithRoles,
            $parseScriptureRun, $emitScripture, $setId, $setMeta, &$slides, $flushSlide,
            &$agendaOn, &$agendaColor, &$path, $emitOutlineSummary, $summaryVon, $options,
            $scriptureZweit
        ) {
            $h1 = trim((string)($section['h1'] ?? ''));
            $path = $h1 !== '' ? [$h1] : [];
            $blocks = $section['blocks'];
            $n = count($blocks);
            $pendingH1 = '';

            /* Hat die Hauptgliederung den Punkt schon angekündigt, entfällt die
               Titelfolie: Die Übersicht nennt den Punkt bereits und hebt ihn hervor.
               Zwei Folien mit demselben Namen hintereinander wären doppelt — dieselbe
               Überlegung wie bei den Unterpunkten weiter unten. */
            if ($h1BereitsGezeigt) {
                // nichts: weder eigene Titelfolie noch Überschrift auf der Folgefolie
            } elseif ($h1 !== '' && $zoneFor('heading1') === 'slides') {
                if ($settings['h1Opener'] === 'always_separate') {
                    $openSlide('heading1', 'heading1', $h1);
                } else {
                    $pendingH1 = $h1;
                }
            } elseif ($h1 !== '') {
                if ($zoneFor('heading1') === 'footer') {
                    $routeBlock(['type' => 'heading1', 'text' => $h1, 'level' => 1], true);
                } elseif ($zoneFor('heading1') === 'custom') {
                    $routeBlock(['type' => 'heading1', 'text' => $h1, 'level' => 1], true);
                }
            }

            /* Gliederungsübersicht: eine Listenfolie mit allen Unterpunkten des
               Abschnitts. Sie erscheint einmal schlicht und danach vor jedem Punkt
               erneut, wobei der laufende Punkt in der Akzentfarbe steht — so sehen die
               Zuhörer jederzeit, wo man gerade ist. Die hervorgehobene Fassung ersetzt
               die sonst übliche Titelfolie des Punktes; zwei Folien mit demselben
               Namen hintereinander wären doppelt. */
            $agendaItems = [];
            if ($agendaOn) {
                foreach ($blocks as $b) {
                    if (($b['type'] ?? '') === 'heading2' && trim((string)$b['text']) !== '') {
                        $agendaItems[] = trim((string)$b['text']);
                    }
                }
            }
            $emitAgenda = function (?string $active) use (
                $agendaItems, &$agendaColor, $h1, $openSlideWithRoles, &$pendingH1
            ): void {
                if ($agendaItems === []) {
                    return;
                }
                $zeilen = [];
                foreach ($agendaItems as $item) {
                    /* Der Strich muss VOR der Farbmarkierung stehen und am Zeilenanfang
                       bleiben: Markdown::render prüft je Zeile auf „- " und macht nur dann
                       einen Listenpunkt daraus. Stand „[color=…]- Text[/color]", sah der
                       Leser die Klammer und liess den Strich als Zeichen stehen. */
                    $zeile = '- ' . $item;
                    if ($active !== null && $item === $active) {
                        $zeile = '- [color=' . $agendaColor . ']' . $item . '[/color]';
                    }
                    $zeilen[] = $zeile;
                }
                /* Die Liste MUSS die erste Rolle sein: Für „Überschrift + Liste"
                   findet bestLayoutForContent in diesem Folien-Set keine Vorlage, und
                   die Notlösung richtet sich nach der ersten Rolle. Stand dort die
                   Überschrift, landete alles auf der reinen Überschriften-Vorlage —
                   ohne Listenfeld, und Titel und Liste lagen übereinander. */
                $content = ['list_item' => implode("\n", $zeilen)];
                if ($pendingH1 !== '') {
                    $content['heading1'] = $pendingH1;
                    $pendingH1 = '';
                } elseif ($h1 !== '') {
                    $content['heading2'] = $h1;
                }
                $openSlideWithRoles('list_item', $content);
            };
            if ($agendaOn && $agendaItems !== []) {
                $emitAgenda(null);
            }

            $i = 0;
            while ($i < $n) {
                $block = $blocks[$i];
                $type = $block['type'];

                /* Gliederungspfad nachführen, BEVOR die Überschrift verarbeitet wird —
                   die erzeugte Folie gehört bereits zur neuen Ebene. */
                if (preg_match('/^heading([2-5])$/', $type, $hm) && trim((string)$block['text']) !== '') {
                    $ebene = (int)$hm[1];
                    $path = array_slice($path, 0, $ebene - 1);
                    $path[$ebene - 1] = trim((string)$block['text']);
                    $path = array_values($path);
                }

                // Punkt-Titel durch die hervorgehobene Übersicht ersetzen.
                if ($agendaOn && $agendaItems !== [] && $type === 'heading2'
                    && trim((string)$block['text']) !== '') {
                    $emitAgenda(trim((string)$block['text']));
                    $i++;
                    continue;
                }

                if ($type === 'scripture_block') {
                    $run = $parseScriptureRun($blocks, $i);
                    $nachher = $i + max(1, $run['consumed']);
                    $zweit = $scriptureZweit($blocks, $nachher, $run);
                    $emitScripture($run['refText'], $run['verseText'], null, null, $zweit);
                    $i = $zweit === null ? $nachher : $nachher + max(1, (int)$zweit['consumed']);
                    continue;
                }

                if (str_starts_with($type, 'heading') && $type !== 'heading1'
                    && $i + 1 < $n && $blocks[$i + 1]['type'] === 'scripture_block'
                    && $settings['scriptureHeading'] !== 'scripture_always_separate') {
                    $run = $parseScriptureRun($blocks, $i + 1);
                    $emitScripture($run['refText'], $run['verseText'], $type, $block['text']);
                    $i += 1 + max(1, $run['consumed']);
                    continue;
                }

                if ($type === 'list_item' && $zoneFor('list_item') === 'slides') {
                    $items = [];
                    while ($i < $n && $blocks[$i]['type'] === 'list_item') {
                        $items[] = $blocks[$i]['text'];
                        $i++;
                    }
                    foreach (self::chunkListItemsByGrouping($items, $settings['listGrouping'], $setId, $setMeta) as $chunk) {
                        $content = [];
                        if ($pendingH1 !== '') {
                            $content['heading1'] = $pendingH1;
                            $pendingH1 = '';
                        }
                        $content['list_item'] = self::formatListItemsMarkdown($chunk);
                        $openSlideWithRoles('list_item', $content);
                    }
                    continue;
                }

                if ($type === 'normal' && $zoneFor('normal') === 'slides') {
                    $textMax = $settings['textMaxCharacters'];
                    if ($textMax === 'layout' || $textMax === 0 || $textMax === '0') {
                        $content = [];
                        if ($pendingH1 !== '') {
                            $content['heading1'] = $pendingH1;
                            $pendingH1 = '';
                        }
                        $content['normal'] = $block['text'];
                        $openSlideWithRoles('normal', $content);
                        $i++;
                        continue;
                    }
                    $parts = [];
                    $totalLen = 0;
                    while ($i < $n && $blocks[$i]['type'] === 'normal') {
                        $piece = trim($blocks[$i]['text']);
                        if ($piece === '') {
                            $i++;
                            continue;
                        }
                        $nextLen = $totalLen + ($totalLen > 0 ? 2 : 0) + mb_strlen($piece);
                        if ($parts !== [] && $nextLen > (int)$textMax) {
                            break;
                        }
                        $parts[] = $piece;
                        $totalLen = $nextLen;
                        $i++;
                    }
                    $merged = implode("\n\n", $parts);
                    $content = [];
                    if ($pendingH1 !== '') {
                        $content['heading1'] = $pendingH1;
                        $pendingH1 = '';
                    }
                    $content['normal'] = $merged;
                    $openSlideWithRoles('normal', $content);
                    continue;
                }

                /* Zusammengefasste Gliederung: ein Lauf gleichrangiger Unterpunkte
                   mit ihren Stichpunkten wird nach Platz auf Folien verteilt, statt
                   je Punkt eine Folie zu machen. Steht für diese Ebene die
                   Gliederungsübersicht an, hat sie Vorrang — ihr Zweig kommt im
                   Ablauf vor diesem. */
                /* «Zusammenfassen ab Ebene N» bringt die beiden Rollen selbst auf
                   Folien — sonst müsste Urs zusätzlich zwei Auswahlfelder umstellen,
                   und in seinem Set «Schlicht - Logos» stehen genau die beiden nicht
                   auf Folien (heading4 `unused`, Stichpunkte `custom`): die Angabe
                   hätte sichtbar nichts getan.
                   **Auch die Gliederungstiefe hält das nicht auf:** sie regelt, wie
                   tief Überschriften eine *eigene* Folie bekommen — hier bekommt
                   keine eine eigene, sie werden zusammengefasst. Wer «ab Ebene 4»
                   wählt, meint Ebene 4.
                   Hat er dagegen bei «Gliederung» oder «Aufzählungspunkte»
                   ausdrücklich «in die Notizen» oder «aus» gewählt, gilt seine Wahl. */
                $fuerFolien = function (string $rolle) use ($options): bool {
                    $wahl = (string)($options[$rolle === 'list_item' ? 'bullets' : 'outline'] ?? 'set');
                    return $wahl === 'set' || $wahl === 'slides';
                };
                if ($summaryVon >= 2 && preg_match('/^heading([2-5])$/', $type, $sm)
                    && (int)$sm[1] >= $summaryVon
                    && trim((string)$block['text']) !== ''
                    && $fuerFolien($type) && $fuerFolien('list_item')) {
                    $gruppen = [];
                    $j = $i;
                    while ($j < $n && ($blocks[$j]['type'] ?? '') === $type
                        && trim((string)$blocks[$j]['text']) !== '') {
                        $gTitel = trim((string)$blocks[$j]['text']);
                        $j++;
                        $gItems = [];
                        while ($j < $n && ($blocks[$j]['type'] ?? '') === 'list_item') {
                            $gItems[] = (string)$blocks[$j]['text'];
                            $j++;
                        }
                        $gruppen[] = ['titel' => $gTitel, 'items' => $gItems];
                    }
                    /* Unter zwei Punkten gibt es nichts zusammenzufassen — dieselbe
                       Schwelle wie bei der Übersicht. */
                    if (count($gruppen) >= 2) {
                        $ebene = (int)$sm[1];
                        $elternTitel = '';
                        $elternTyp = '';
                        for ($k = $i - 1; $k >= 0; $k--) {
                            $kTyp = (string)($blocks[$k]['type'] ?? '');
                            if (preg_match('/^heading([1-5])$/', $kTyp, $km)
                                && (int)$km[1] < $ebene
                                && trim((string)$blocks[$k]['text']) !== '') {
                                $elternTitel = trim((string)$blocks[$k]['text']);
                                $elternTyp = $kTyp;
                                break;
                            }
                        }
                        if ($elternTitel === '' && $h1 !== '') {
                            $elternTitel = $h1;
                            $elternTyp = 'heading1';
                        }
                        /* Eine wartende H1-Titelfolie zuerst abliefern, sonst stünde
                           sie hinter der Zusammenfassung. */
                        if ($pendingH1 !== '') {
                            $openSlide('heading1', 'heading1', $pendingH1);
                            $pendingH1 = '';
                        }
                        /* Die Folien gehören zur Ebene des Oberpunkts, nicht zum
                           zuerst gesehenen Unterpunkt — den hat die Pfadpflege oben
                           schon angehängt. */
                        array_pop($path);
                        $unterRolle = (string)array_key_first(
                            self::listBodyRoleForHeading($setId, $setMeta, $elternTyp !== '' ? $elternTyp : $type, 'x')
                        );
                        $emitOutlineSummary($gruppen, $elternTitel, $elternTyp, $unterRolle);
                        $i = $j;
                        continue;
                    }
                }

                if (str_starts_with($type, 'heading') && $type !== 'heading1' && $zoneFor($type) === 'slides') {
                    $content = [$type => $block['text']];
                    if ($pendingH1 !== '') {
                        $content = ['heading1' => $pendingH1] + $content;
                        $pendingH1 = '';
                    }

                    $j = $i + 1;
                    if ($j < $n && $blocks[$j]['type'] === 'list_item' && $zoneFor('list_item') === 'slides') {
                        $items = [];
                        while ($j < $n && $blocks[$j]['type'] === 'list_item') {
                            $items[] = $blocks[$j]['text'];
                            $j++;
                        }
                        foreach (self::chunkListItemsByGrouping($items, $settings['listGrouping'], $setId, $setMeta) as $chunkIdx => $chunk) {
                            $listText = self::formatListItemsMarkdown($chunk);
                            $slideContent = $chunkIdx === 0
                                ? $content + self::listBodyRoleForHeading($setId, $setMeta, $type, $listText)
                                : self::listBodyRoleForHeading($setId, $setMeta, $type, $listText);
                            $openSlideWithRoles($chunkIdx === 0 ? $type : 'list_item', $slideContent);
                        }
                        $i = $j;
                        continue;
                    }

                    if ($j < $n && $blocks[$j]['type'] === 'normal' && $zoneFor('normal') === 'slides') {
                        $content['normal'] = $blocks[$j]['text'];
                        $openSlideWithRoles($type, $content);
                        $i = $j + 1;
                        continue;
                    }
                    $openSlideWithRoles($type, $content);
                    $i++;
                    continue;
                }

                if ($pendingH1 !== '' && $zoneFor($type) === 'slides' && !str_starts_with($type, 'heading')) {
                    $openSlideWithRoles('heading1', ['heading1' => $pendingH1, $type => $block['text']]);
                    $pendingH1 = '';
                    $i++;
                    continue;
                }

                $routeBlock($block, true);
                $i++;
            }

            if ($pendingH1 !== '' && $zoneFor('heading1') === 'slides') {
                $openSlide('heading1', 'heading1', $pendingH1);
            }
            $flushSlide();
        };

        ['preamble' => $preamble, 'sections' => $sections] = self::splitIntoSections($blocks);

        $pi = 0;
        $pn = count($preamble);
        while ($pi < $pn) {
            $block = $preamble[$pi];
            if ($block['type'] === 'scripture_block') {
                $run = $parseScriptureRun($preamble, $pi);
                $nachher = $pi + max(1, $run['consumed']);
                $zweit = $scriptureZweit($preamble, $nachher, $run);
                $emitScripture($run['refText'], $run['verseText'], null, null, $zweit);
                $pi = $zweit === null ? $nachher : $nachher + max(1, (int)$zweit['consumed']);
                continue;
            }
            $routeBlock($block, false);
            $pi++;
        }

        $titleOverrides = $options['sectionTitles'] ?? [];
        $agendaFor = array_flip($options['agendaSections'] ?? []);
        $agendaColor = (string)($options['agendaColor'] ?? '#b7de45');
        $agendaOn = false;

        /* Übersicht über die HAUPTpunkte — dasselbe Vorgehen wie bei den Unterpunkten,
           eine Ebene höher: einmal schlicht am Anfang der Predigt, danach vor jedem
           Hauptpunkt erneut mit dem laufenden Punkt in der Akzentfarbe. Die
           hervorgehobene Fassung ersetzt die Titelfolie des Punktes. Angezeigt werden
           die im Dialog vergebenen Namen, nicht die aus dem Dokument. */
        $hauptAn = !empty($options['agendaMain']);
        $hauptTitel = [];
        if ($hauptAn) {
            foreach ($sections as $s) {
                $o = trim((string)($s['h1'] ?? ''));
                if ($o === '') {
                    continue;
                }
                $hauptTitel[] = trim((string)($titleOverrides[$o] ?? $o));
            }
            $hauptTitel = array_values(array_filter($hauptTitel, fn($t) => $t !== ''));
        }
        /* Unter zwei Punkten ist eine Übersicht keine Hilfe, sondern eine leere Folie. */
        $hauptAn = $hauptAn && count($hauptTitel) >= 2;

        $emitHauptAgenda = function (?string $active) use (
            $hauptTitel, &$agendaColor, $openSlideWithRoles
        ): void {
            $zeilen = [];
            foreach ($hauptTitel as $titel) {
                $zeilen[] = ($active !== null && $titel === $active)
                    ? '- [color=' . $agendaColor . ']' . $titel . '[/color]'
                    : '- ' . $titel;
            }
            /* Nur die Liste, ohne Überschrift: Der laufende Punkt steht schon in der
               Liste und stünde sonst doppelt auf der Folie. `list_item` ist zugleich
               die führende Rolle — siehe die Begründung bei der Unterpunkt-Übersicht. */
            $openSlideWithRoles('list_item', ['list_item' => implode("\n", $zeilen)]);
        };

        if ($hauptAn) {
            $flushSlide();
            /* Die schlichte Übersicht nur, wenn vorher schon etwas steht (Titelfolie,
               Einstiegsverse). Ohne Vorspann folgte ihr sofort dieselbe Liste mit
               hervorgehobenem erstem Punkt — zwei fast gleiche Folien hintereinander. */
            if ($slides !== []) {
                $emitHauptAgenda(null);
            }
        }

        foreach ($sections as $section) {
            /* Im Dialog umbenannter Abschnitt: Der neue Titel steht auf der
               Abschnittsfolie und in der Vorschau-Überschrift. */
            $origTitle = trim((string)($section['h1'] ?? ''));
            $agendaOn = $origTitle !== '' && isset($agendaFor[$origTitle]);
            if ($origTitle !== '' && isset($titleOverrides[$origTitle])) {
                $section['h1'] = $titleOverrides[$origTitle];
            }
            /* Vor dem Zählen abräumen: Die zuletzt geöffnete Folie liegt noch in $current
               und wandert erst beim nächsten Öffnen in $slides. Ohne dieses Abräumen
               zählte sie zum folgenden Abschnitt — in Urs' Predigt landete „2. Könige
               18,19" aus dem Einstieg sichtbar unter „Teil 1: Geschichtlicher Abriss". */
            $flushSlide();
            $before = count($slides);
            if ($hauptAn) {
                $emitHauptAgenda(trim((string)($section['h1'] ?? '')));
            }
            $processSection($section, $hauptAn);
            $sectionSummaries[] = [
                'title' => trim((string)($section['h1'] ?? '')),
                'original_title' => $origTitle,
                'slide_count' => count($slides) - $before,
            ];
        }

        $flushSlide();

        return [
            'slides' => $slides,
            'footer_text' => implode("\n\n", $footerLines),
            'sections' => $sectionSummaries,
            'warnings' => $warnings,
        ];
    }

    /** @return array<string, string> */
    private static function listBodyRoleForHeading(
        string $setId,
        array $setMeta,
        string $headingType,
        string $listText
    ): array {
        $headingType = LayoutSet::canonicalLogosRole($headingType);
        $withNormal = [$headingType => 'heading', 'normal' => $listText];
        if (LayoutSet::bestLayoutForContent($setId, $withNormal, $setMeta) !== null) {
            return ['normal' => $listText];
        }
        return ['list_item' => $listText];
    }

    /**
     * Wie viele Zeilen brauchen diese Absätze in einem Feld mit `$proZeile` Zeichen?
     *
     * Grobe, aber ehrliche Schätzung: je Absatz die Zeichen durch die Zeichen je Zeile,
     * aufgerundet, plus eine Leerzeile zwischen zwei Absätzen. Dieselbe Rechnung
     * benutzen die zusammengefasste Gliederung und die zusammengelegten Bibelstellen —
     * damit beide dasselbe «passt» meinen.
     *
     * @param list<string> $absaetze
     */
    private static function geschaetzteZeilen(array $absaetze, int $proZeile): int
    {
        $proZeile = max(10, $proZeile);
        $zeilen = 0;
        $erster = true;
        foreach ($absaetze as $absatz) {
            $absatz = trim((string)$absatz);
            if ($absatz === '') {
                continue;
            }
            if (!$erster) {
                $zeilen++; // Leerzeile dazwischen
            }
            $erster = false;
            foreach (explode("\n", $absatz) as $zeile) {
                $zeilen += max(1, (int)ceil(mb_strlen($zeile) / $proZeile));
            }
        }
        return $zeilen;
    }

    /** @param list<string> $items */
    public static function formatListItemsMarkdown(array $items): string
    {
        $lines = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            /* Nur das Listenzeichen weg — **die führende Nummer bleibt stehen.**
               Sie wegzunehmen war für numerierte Listen gedacht, zerlegte aber bei
               Logos-Predigten die Stellenangabe: «• 2. Chr 33,3: …» wurde zu
               «Chr 33,3: …». Die Bücher heissen nun einmal 1./2. Chronik, 2. Mose.
               Eine Nummer zu viel in einer numerierten Liste ist Kosmetik, eine
               fehlende Buchnummer ist ein Fehler.
               Am Listenzeichen zu erkennen, was gemeint ist, reicht nicht: in die
               Notizen kommt der Punkt über `listItemKeyword()`, und das Zeichen ist
               dort schon weg. */
            $item = preg_replace('/^[•›\-*]\s*/u', '', $item) ?? $item;
            $lines[] = '- ' . $item;
        }
        return implode("\n", $lines);
    }

    /**
     * @param list<string> $items
     * @return list<list<string>>
     */
    private static function chunkListItemsByGrouping(
        array $items,
        string|int $grouping,
        string $setId,
        array $setMeta
    ): array {
        if ($items === []) {
            return [];
        }
        if ($grouping === 0 || $grouping === '0') {
            return [$items];
        }
        if ($grouping === 'layout') {
            $slots = self::listItemSlotCount($setId, $setMeta);
            if ($slots <= 1) {
                return [$items];
            }
            return array_chunk($items, $slots);
        }
        if ((int)$grouping === 1) {
            return array_map(static fn($item) => [$item], $items);
        }
        return array_chunk($items, max(1, (int)$grouping));
    }

    private static function listItemSlotCount(string $setId, array $setMeta): int
    {
        $layout = LayoutSet::findLayoutForRole($setId, 'list_item', $setMeta);
        if ($layout === null) {
            return 1;
        }
        $count = 0;
        foreach ($layout['objects'] ?? [] as $obj) {
            $role = LayoutSet::canonicalLogosRole((string)($obj['setRole'] ?? $obj['logosRole'] ?? ''));
            if ($role === 'list_item') {
                $count++;
            }
        }
        return max(1, $count);
    }

    private static function nodeText(DOMElement $node): string
    {
        /* textContent kennt kein <br>: Logos setzt damit Zeilenumbrüche innerhalb eines
           Aufzählungspunktes, und ohne diese Behandlung klebten die Zeilen zusammen
           ("Gott LobenIm Leben"). Darum am <br> einen echten Umbruch einfügen. */
        $text = self::textWithLineBreaks($node);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Teilt einen langen Bibeltext versgenau, solange ein Versgrenzen-Verzeichnis da ist
     * und die Stelle sich zuordnen lässt. Ergebnis sind Abschnitte mit eigener, richtiger
     * Stellenangabe ("2. Könige 19,14–16" / "2. Könige 19,17–19").
     *
     * Gibt null zurück, wenn kein Verzeichnis vorliegt, die Stelle nicht lesbar ist oder
     * sich weniger als zwei Versanfänge finden liessen - dann bleibt es beim Trennen an
     * Satzgrenzen. Lieber ein Satzumbruch als eine falsche Versangabe auf der Folie.
     *
     * @return list<array{ref: string, text: string}>|null
     */
    private static function splitScriptureByVerses(string $refText, string $verseText, int $budget): ?array
    {
        if ($budget <= 0 || $refText === '' || $verseText === '' || !BibleVerseIndex::available()) {
            return null;
        }
        if (mb_strlen($verseText) <= $budget) {
            return null;
        }
        $parsed = BibleVerseIndex::parseReference($refText);
        if ($parsed === null) {
            return null;
        }
        $offsets = BibleVerseIndex::verseOffsets($verseText, $parsed);
        if (count($offsets) < 2) {
            return null;
        }

        // "2. Könige 19," als Vorspann behalten, damit die Schreibweise der Predigt bleibt.
        if (!preg_match('/^(.+?\s+\d+\s*[,:]\s*)/u', trim($refText), $pm)) {
            return null;
        }
        $prefix = $pm[1];
        $dash = str_contains($refText, "\u{2013}") ? "\u{2013}" : '-';

        // Aus den Versanfängen Abschnitte machen; Text vor dem ersten Treffer gehört dazu.
        $parts = [];
        $count = count($offsets);
        for ($i = 0; $i < $count; $i++) {
            $start = $i === 0 ? 0 : $offsets[$i]['offset'];
            $end = ($i + 1 < $count) ? $offsets[$i + 1]['offset'] : mb_strlen($verseText);
            $piece = trim(mb_substr($verseText, $start, $end - $start));
            if ($piece !== '') {
                $parts[] = ['verse' => $offsets[$i]['verse'], 'text' => $piece];
            }
        }
        if (count($parts) < 2) {
            return null;
        }

        // Verse zusammenfassen, solange sie in die Zeichengrenze passen.
        $out = [];
        $curText = '';
        $curFrom = null;
        $curTo = null;
        foreach ($parts as $part) {
            $candidate = $curText === '' ? $part['text'] : $curText . ' ' . $part['text'];
            if ($curText !== '' && mb_strlen($candidate) > $budget) {
                $out[] = ['from' => $curFrom, 'to' => $curTo, 'text' => $curText];
                $curText = $part['text'];
                $curFrom = $curTo = $part['verse'];
                continue;
            }
            $curText = $candidate;
            $curFrom ??= $part['verse'];
            $curTo = $part['verse'];
        }
        if ($curText !== '') {
            $out[] = ['from' => $curFrom, 'to' => $curTo, 'text' => $curText];
        }
        if (count($out) < 2) {
            return null;
        }

        return array_map(
            fn(array $c) => [
                'ref' => $prefix . ($c['from'] === $c['to'] ? $c['from'] : $c['from'] . $dash . $c['to']),
                'text' => $c['text'],
            ],
            $out
        );
    }

    /**
     * Teilt einen langen Text in Abschnitte von höchstens $max Zeichen. Getrennt wird
     * bevorzugt nach einem Satzzeichen, sonst an einer Wortgrenze - nie mitten im Wort.
     * Eine Bibel-API braucht es dafür nicht: Der Predigt-Export liefert den Wortlaut
     * bereits mit, nur eben am Stück.
     *
     * @return list<string>
     */
    public static function splitTextIntoChunks(string $text, int $max): array
    {
        $text = trim($text);
        if ($max <= 0 || $text === '' || mb_strlen($text) <= $max) {
            return [$text];
        }

        // In Sätze zerlegen; das Satzzeichen bleibt am Satz.
        $sentences = preg_split('/(?<=[.!?;:»"”])\s+/u', $text) ?: [$text];

        $chunks = [];
        $current = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }
            // Ein einzelner Satz, der die Grenze sprengt, wird an Wortgrenzen zerlegt.
            if (mb_strlen($sentence) > $max) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                foreach (self::splitByWords($sentence, $max) as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }
            $candidate = $current === '' ? $sentence : $current . ' ' . $sentence;
            if (mb_strlen($candidate) > $max) {
                $chunks[] = $current;
                $current = $sentence;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks === [] ? [$text] : $chunks;
    }

    /** @return list<string> */
    private static function splitByWords(string $text, int $max): array
    {
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $out = [];
        $current = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) > $max && $current !== '') {
                $out[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $out[] = $current;
        }
        return $out;
    }

    /**
     * Vorgabewerte der Dialog-Schalter. Leer gelassen heisst überall „wie im Layout-Set",
     * damit ein Import ohne Dialog sich genau wie bisher verhält.
     */
    public const DEFAULT_IMPORT_OPTIONS = [
        'outline' => 'set',      // set | slides | notes | off
        'scripture' => 'set',
        'bullets' => 'set',
        'outlineDepth' => 0,     // 0 = alle Ebenen, sonst 1..5
        // Kürzel wie „LU17": bisher immer entfernt, darum ist „aus" die Vorgabe.
        'scriptureTranslation' => false,
        // Schweizer Schreibweise: ß durch ss ersetzen, nur in Bibelversen.
        'swissSs' => false,
        // Inhaltsschlüssel abgewählter Folien und Notizteile aus dem Dialog.
        'skipSlides' => [],
        'skipNotes' => [],
        // Im Dialog getippte eigene Notizen, Folienschlüssel => Text.
        'ownNotes' => [],
        // Im Dialog umbenannte Abschnitte, ursprünglicher Titel => neuer Titel.
        'sectionTitles' => [],
        // Abschnitte mit Gliederungsübersicht (ursprüngliche Titel) und Akzentfarbe.
        'agendaSections' => [],
        'agendaMain' => false,
        'agendaColor' => '#b7de45',
        // Gliederungspfad als Markdown-Überschriften in die Notizen.
        'hierarchyNotes' => false,
        'hierarchyStyle' => 'headings',
        /* Ab welcher Gliederungsebene zusammengefasst wird: 0 = aus, sonst 2..5.
           Mehrere Unterpunkte samt ihren Stichpunkten kommen dann auf eine Folie,
           gefüllt nach dem Platz im Inhaltsfeld. Vorgabe aus, damit ein Import ohne
           Zutun aussieht wie bisher. Urs, 24.10.2026: «Können wir das nicht hier
           angeben? ‹zusammenfassen ab Gliederungspunkt› und dann die Ebene». */
        'outlineSummary' => 0,
        /* Zwei aufeinanderfolgende Bibelstellen auf einer Folie. Urs, 24.10.2026:
           «Wir machen das noch so, dass man zwei bibelvers Folien zusammen fassen
           kann» — und auf die Frage automatisch oder von Hand: «Automatisch und von
           Hand…». `scriptureMerge` ist die Automatik (nur wo der gemessene Platz
           reicht), `mergeScripture` sind die von Hand gewählten Stellen. */
        'scriptureMerge' => false,
        'mergeScripture' => [],
        /* Von Hand wieder getrennt — hebt die Automatik für genau diese Stelle auf
           («Trennen» an der violett umrandeten Folie). */
        'splitScripture' => [],
    ];

    /** Die drei Inhalte des Dialogs auf die Logos-Rollen abgebildet. */
    private const OPTION_ROLES = [
        'outline' => ['document_title', 'heading1', 'heading2', 'heading3', 'heading4', 'heading5'],
        'scripture' => ['scripture_block', 'scripture_ref', 'scripture_verse'],
        'bullets' => ['list_item'],
    ];

    /**
     * Wandelt die Dialog-Schalter in Rollen-Zonen um. Nur was der Dialog ausdrücklich
     * setzt, wird übersteuert - alles andere bleibt beim Layout-Set.
     *
     * @return array<string, string>
     */
    private static function zoneOverrides(array $options): array
    {
        $out = [];
        foreach (self::OPTION_ROLES as $key => $roles) {
            $choice = (string)($options[$key] ?? 'set');
            if ($choice === 'set' || $choice === '') {
                continue;
            }
            $zone = match ($choice) {
                'slides' => 'slides',
                'notes' => 'custom',
                'off' => 'unused',
                default => null,
            };
            if ($zone === null) {
                continue;
            }
            foreach ($roles as $role) {
                $out[$role] = $zone;
            }
        }

        // Gliederungstiefe: Überschriften unterhalb der gewählten Ebene entfallen.
        $depth = (int)($options['outlineDepth'] ?? 0);
        if ($depth >= 1 && $depth <= 5) {
            for ($level = $depth + 1; $level <= 5; $level++) {
                $out['heading' . $level] = 'unused';
            }
        }

        return $out;
    }

    /**
     * Nimmt rohe Dialogwerte entgegen und lässt nur Bekanntes durch, damit aus dem
     * Formular nichts Unerwartetes in den Aufbau gerät.
     */
    public static function normalizeOptions(array $raw): array
    {
        $out = self::DEFAULT_IMPORT_OPTIONS;
        foreach (['outline', 'scripture', 'bullets'] as $key) {
            $v = (string)($raw[$key] ?? '');
            if (in_array($v, ['set', 'slides', 'notes', 'off'], true)) {
                $out[$key] = $v;
            }
        }
        $depth = (int)($raw['outlineDepth'] ?? 0);
        $out['outlineDepth'] = ($depth >= 1 && $depth <= 5) ? $depth : 0;
        foreach (['skipSlides', 'skipNotes'] as $listKey) {
            $list = $raw[$listKey] ?? [];
            if (is_string($list)) {
                $list = $list === '' ? [] : explode(',', $list);
            }
            $out[$listKey] = is_array($list)
                ? array_values(array_filter(array_map(
                    fn($v) => preg_replace('/[^a-f0-9-]/', '', strtolower((string)$v)),
                    $list
                ), fn($v) => $v !== ''))
                : [];
        }
        if (array_key_exists('swissSs', $raw)) {
            $out['swissSs'] = filter_var($raw['swissSs'], FILTER_VALIDATE_BOOL);
        }
        if (array_key_exists('hierarchyNotes', $raw)) {
            $out['hierarchyNotes'] = filter_var($raw['hierarchyNotes'], FILTER_VALIDATE_BOOL);
        }
        if (array_key_exists('scriptureMerge', $raw)) {
            $out['scriptureMerge'] = filter_var($raw['scriptureMerge'], FILTER_VALIDATE_BOOL);
        }
        foreach (['mergeScripture', 'splitScripture'] as $listenKey) {
            $liste = $raw[$listenKey] ?? [];
            if (is_string($liste)) {
                $decoded = json_decode($liste, true);
                $liste = is_array($decoded) ? $decoded : ($liste === '' ? [] : explode(',', $liste));
            }
            $out[$listenKey] = is_array($liste)
                ? array_values(array_filter(array_map(
                    fn($v) => preg_replace('/[^a-f0-9]/', '', strtolower((string)$v)),
                    $liste
                ), fn($v) => $v !== ''))
                : [];
        }
        if (array_key_exists('outlineSummary', $raw)) {
            $ab = (int)$raw['outlineSummary'];
            // Ebene 1 ist der Abschnitt selbst — darunter gibt es nichts zusammenzufassen.
            $out['outlineSummary'] = ($ab >= 2 && $ab <= 5) ? $ab : 0;
        }
        if (array_key_exists('hierarchyStyle', $raw)) {
            $stil = strtolower(trim((string)$raw['hierarchyStyle']));
            $out['hierarchyStyle'] = in_array($stil, ['headings', 'path'], true) ? $stil : 'headings';
        }
        if (array_key_exists('scriptureTranslation', $raw)) {
            $out['scriptureTranslation'] = filter_var($raw['scriptureTranslation'], FILTER_VALIDATE_BOOL);
        }
        $own = $raw['ownNotes'] ?? [];
        if (is_string($own)) {
            $decoded = json_decode($own, true);
            $own = is_array($decoded) ? $decoded : [];
        }
        $titles = $raw['sectionTitles'] ?? [];
        if (is_string($titles)) {
            $decoded = json_decode($titles, true);
            $titles = is_array($decoded) ? $decoded : [];
        }
        $out['sectionTitles'] = [];
        if (is_array($titles)) {
            foreach ($titles as $alt => $neu) {
                $alt = trim((string)$alt);
                $neu = trim(preg_replace('/[\x00-\x1f]/u', ' ', (string)$neu) ?? '');
                if ($alt === '' || $neu === '') {
                    continue;
                }
                $out['sectionTitles'][$alt] = mb_substr($neu, 0, 200);
            }
        }
        if (array_key_exists('agendaMain', $raw)) {
            $out['agendaMain'] = filter_var($raw['agendaMain'], FILTER_VALIDATE_BOOL);
        }
        $ag = $raw['agendaSections'] ?? [];
        if (is_string($ag)) {
            $decoded = json_decode($ag, true);
            $ag = is_array($decoded) ? $decoded : [];
        }
        $out['agendaSections'] = [];
        if (is_array($ag)) {
            foreach ($ag as $t) {
                $t = trim((string)$t);
                if ($t !== '') {
                    $out['agendaSections'][] = $t;
                }
            }
        }
        $farbe = trim((string)($raw['agendaColor'] ?? ''));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $farbe)) {
            $out['agendaColor'] = strtolower($farbe);
        }
        $out['ownNotes'] = [];
        if (is_array($own)) {
            foreach ($own as $k => $v) {
                $key = preg_replace('/[^a-f0-9-]/', '', strtolower((string)$k));
                $text = trim((string)$v);
                if ($key === '' || $text === '') {
                    continue;
                }
                // Steuerzeichen raus, Länge begrenzen - das landet in einer Datei.
                $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', '', $text);
                $out['ownNotes'][$key] = mb_substr($text, 0, 2000);
            }
        }
        return $out;
    }

    /**
     * Das Stichwort eines Aufzählungspunktes für die Notizen. Bevorzugt die fette
     * Auszeichnung; fehlt sie, wird der Vorspann vor dem ersten Doppelpunkt genommen
     * und sonst die erste Zeile gekürzt. Urs: „Ich brauche einfach nur den Fetten Teil.
     * (Sonst ersaufe ich im Text)" - deshalb wird nie der ganze Punkt übernommen.
     *
     * @param array{text: string, bold?: string} $block
     */
    private static function listItemKeyword(array $block): string
    {
        $bold = trim((string)($block['bold'] ?? ''));
        if ($bold !== '') {
            return $bold;
        }
        $text = trim((string)$block['text']);
        $text = ltrim($text, "•›-\u{2013}\u{2014} \t");
        $text = trim(explode("\n", $text)[0]);
        $colon = mb_strpos($text, ':');
        if ($colon !== false && $colon <= 60) {
            $lead = trim(mb_substr($text, 0, $colon));
            if ($lead !== '') {
                return $lead;
            }
        }
        if (mb_strlen($text) <= 80) {
            return rtrim($text, '.');
        }
        $cut = mb_substr($text, 0, 80);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, ' ,.;') . '…';
    }

    /**
     * Der fett ausgezeichnete Vorspann eines Aufzählungspunktes, also das Stichwort,
     * mit dem Logos den Punkt überschreibt ("Grobe Einordnung:", "Krankheit (vor 703):").
     * Genau dieses Stichwort gehört in die Notizen - der Fliesstext dahinter ist
     * Ausformulierung für den Vortragenden und macht die Notizen unlesbar.
     * Leer, wenn der Punkt gar keine Fettung hat; der Aufrufer nimmt dann den ganzen Text.
     */
    private static function boldLeadText(DOMElement $node): string
    {
        $parts = [];
        $walk = function (DOMNode $n, bool $bold) use (&$walk, &$parts): void {
            if ($n instanceof DOMElement) {
                $tag = strtolower($n->nodeName);
                if ($tag === 'a') {
                    return; // Bibelstellen-Links gehören nicht ins Stichwort
                }
                if ($tag === 'b' || $tag === 'strong'
                    || str_contains(str_replace(' ', '', $n->getAttribute('style')), 'font-weight:bold')) {
                    $bold = true;
                }
            }
            if ($n instanceof DOMText) {
                if ($bold) {
                    $parts[] = $n->nodeValue ?? '';
                }
                return;
            }
            foreach ($n->childNodes as $child) {
                $walk($child, $bold);
            }
        };
        $walk($node, false);
        $text = html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text;
        return trim(rtrim(trim($text), ':'));
    }

    /** Wie textContent, aber <br> wird zu einem Zeilenumbruch statt verschluckt. */
    private static function textWithLineBreaks(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $node->nodeValue ?? '';
        }
        if ($node instanceof DOMElement && strtolower($node->nodeName) === 'br') {
            return "\n";
        }
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::textWithLineBreaks($child);
        }
        return $out;
    }

    /**
     * Text einer Bibelstellen-Referenzzeile ohne das Übersetzungskürzel
     * (Logos rendert dieses als <span class="lighttextstyle">, z. B. " LU17").
     */
    private static function refTextWithoutLightText(DOMElement $node, string $fallback): string
    {
        $clone = $node->cloneNode(true);
        if ($clone instanceof DOMElement) {
            $spans = [];
            foreach ($clone->getElementsByTagName('span') as $span) {
                $spans[] = $span;
            }
            foreach ($spans as $span) {
                if ($span->parentNode !== null
                    && str_contains($span->getAttribute('class'), 'lighttextstyle')) {
                    $span->parentNode->removeChild($span);
                }
            }
            $clean = self::nodeText($clone);
            if ($clean !== '') {
                return $clean;
            }
        }
        return $fallback;
    }

    private static function isFooter(DOMElement $node): bool
    {
        $style = $node->getAttribute('style');
        if (str_contains($style, 'mso-element:footer')) {
            return true;
        }
        return str_contains(self::nodeText($node), 'Exportiert aus')
            || str_contains(self::nodeText($node), 'Exported from');
    }

    private static function isDocumentTitle(DOMElement $node): bool
    {
        return str_contains($node->getAttribute('style'), 'border-bottom');
    }

    private static function hasRefLyLink(DOMElement $node): bool
    {
        foreach ($node->getElementsByTagName('a') as $a) {
            if (str_contains($a->getAttribute('href'), 'ref.ly/')) {
                return true;
            }
        }
        return false;
    }

    private static function isScriptureReferenceLine(DOMElement $node, string $text): bool
    {
        $style = $node->getAttribute('style');
        if (!str_contains($style, 'font-size:12pt') && !str_contains($style, 'font-size: 12pt')) {
            return false;
        }
        if (!str_contains($style, 'font-weight:bold') && !str_contains($style, 'font-weight: bold')) {
            return false;
        }
        if (self::hasRefLyLink($node)) {
            return false;
        }
        return (bool)preg_match('/\d+,\d+/u', $text)
            || (bool)preg_match('/\b\d+:\d+/u', $text)
            || (bool)preg_match('/\b[A-ZÄÖÜ][a-zäöüß]+(?:\s+\d+)?\s+\d+/u', $text);
    }

    private static function isListParagraph(DOMElement $node, string $text): bool
    {
        $style = $node->getAttribute('style');
        if (!str_contains($style, 'margin-left') && !str_contains($style, 'text-indent')) {
            return false;
        }
        return str_starts_with($text, '•')
            || str_starts_with($text, '›')
            || (bool)preg_match('/^\d+\./u', $text)
            || (bool)preg_match('/^[a-z]\./ui', $text);
    }
}
