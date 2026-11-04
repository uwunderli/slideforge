<?php

/**
 * Traktandenliste -> Präsentation.
 *
 * Eingabe: Word (.docx), Markdown (.md) oder PDF. Alles wird zuerst zu einer
 * **Gliederung als Markdown-Text** gemacht (`extractOutline`), die der Benutzer in
 * der Vorschau von Hand korrigieren kann; erst diese Gliederung wird zu Folien
 * (`import`). Es gibt also genau einen Parser (`parseOutline`).
 *
 * Gliederung:
 *   # Titel
 *   ## Untertitel (z. B. Datum)        -- optional
 *   - Traktandum
 *     - Unterpunkt                      -- eingerückt
 *
 * Folien: Titelfolie, dann je Traktandum die Übersichtsfolie mit dem aktuellen
 * Punkt hervorgehoben (Farbe, fett oder beides), optional eine Inhaltsfolie mit
 * den Unterpunkten. Die Layouts kommen aus dem gewählten Folien-Set.
 */
class AgendaImporter
{
    public const DEFAULT_COLOR = '#f2a900';
    public const DEFAULT_DONE_COLOR = '#9e9e9e';

    /** @return array{highlight:string,color:string,heading:string,subSlides:bool,doneOn:bool,doneColor:string,insertAfter:int,agendaLayout:string,detailLayout:string} */
    public static function normalizeOptions(array $raw): array
    {
        $hl = (string)($raw['highlight'] ?? 'both');
        if (!in_array($hl, ['color', 'bold', 'both'], true)) {
            $hl = 'both';
        }
        $color = (string)($raw['color'] ?? self::DEFAULT_COLOR);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = self::DEFAULT_COLOR;
        }
        $heading = trim((string)($raw['heading'] ?? ''));
        return [
            'highlight' => $hl,
            'color' => $color,
            'heading' => $heading !== '' ? $heading : 'Traktanden',
            'subSlides' => !isset($raw['subSlides']) || (string)$raw['subSlides'] !== '0',
            // 1-basierte Nummer des Traktandums, hinter dem die Zusatzfolien (Finanzen) eingefügt werden; 0 = ans Ende
            'insertAfter' => max(0, (int)($raw['insertAfter'] ?? 0)),
            'doneOn' => !isset($raw['doneOn']) || (string)$raw['doneOn'] !== '0',
            'doneColor' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($raw['doneColor'] ?? '')) ? (string)$raw['doneColor'] : self::DEFAULT_DONE_COLOR,
            'agendaLayout' => preg_replace('/[^A-Za-z0-9_-]/', '', (string)($raw['agendaLayout'] ?? '')),
            'detailLayout' => preg_replace('/[^A-Za-z0-9_-]/', '', (string)($raw['detailLayout'] ?? '')),
        ];
    }

    /** Dateiendung -> Gliederungstext. */
    public static function extractOutline(string $path, string $origName): string
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            return self::outlineFromDocx($path);
        }
        $lines = match ($ext) {
            'pdf' => self::linesFromPdf($path),
            'md', 'markdown', 'txt' => self::linesFromText((string)file_get_contents($path)),
            default => throw new RuntimeException(t('agenda.unsupported_type')),
        };
        return self::linesToOutline($lines);
    }

    public static function isAgendaFile(string $origName): bool
    {
        return in_array(strtolower(pathinfo($origName, PATHINFO_EXTENSION)), ['docx', 'md', 'markdown', 'pdf'], true);
    }

    /**
     * Word mit Formatierung lesen: Traktandum = fetter Absatz in der grössten fetten Schrift,
     * alles Übrige (Aufzählungspunkte, Verantwortliche, Zahlen,
     * Notizen) = Kommentar zum Traktandum, nicht auf der Folie. Fehlt die Grössen-Unterscheidung, gilt jeder
     * Absatz als Traktandum (einfache Liste).
     */
    private static function outlineFromDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException(t('agenda.read_failed'));
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException(t('agenda.read_failed'));
        }
        $paras = [];
        preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $pm);
        foreach ($pm[0] as $p) {
            $list = (bool)preg_match('#<w:numPr>.*?<w:numId w:val="([1-9]\d*)"#s', $p);
            $defSz = preg_match('#<w:pPr>.*?<w:rPr>(.*?)</w:rPr>#s', $p, $dm) && preg_match('#<w:sz w:val="(\d+)"#', $dm[1], $z) ? (int)$z[1] : 0;
            $segs = [['text' => '', 'bold' => null, 'sz' => 0]];
            preg_match_all('#<w:r[ >].*?</w:r>#s', $p, $rm);
            foreach ($rm[0] as $r) {
                $rpr = preg_match('#<w:rPr>(.*?)</w:rPr>#s', $r, $x) ? $x[1] : '';
                $bold = (bool)preg_match('#<w:b/>|<w:b w:val="(?:1|true)"#', $rpr);
                $sz = preg_match('#<w:sz w:val="(\d+)"#', $rpr, $z) ? (int)$z[1] : $defSz;
                // Reihenfolge der Bausteine im Lauf beibehalten: Text, Tabulator, Umbruch
                preg_match_all('#<w:t[^>]*>([^<]*)</w:t>|<w:tab/>|<w:br/>#', $r, $tm, PREG_SET_ORDER);
                foreach ($tm as $tok) {
                    $last = count($segs) - 1;
                    if ($tok[0] === '<w:br/>') {
                        $segs[] = ['text' => '', 'bold' => null, 'sz' => 0];
                    } else {
                        $txt = $tok[0] === '<w:tab/>' ? ' ' : html_entity_decode($tok[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                        // Wechselt Fett/Grösse mitten im Absatz, beginnt ein neuer Abschnitt (Traktandum + Kommentar im selben Absatz)
                        if (trim($txt) !== '' && $segs[$last]['bold'] !== null && ($segs[$last]['bold'] !== $bold || $segs[$last]['sz'] !== $sz)) {
                            $segs[] = ['text' => '', 'bold' => null, 'sz' => 0];
                            $last++;
                        }
                        $segs[$last]['text'] .= $txt;
                        if (trim($txt) !== '' && $segs[$last]['bold'] === null) {
                            $segs[$last]['bold'] = $bold;
                            $segs[$last]['sz'] = $sz;
                        }
                    }
                }
            }
            if ($list) {
                // Aufzählungspunkt bleibt ein Stück, auch bei wechselnder Formatierung
                $segs = [['text' => implode(' ', array_column($segs, 'text')), 'bold' => false, 'sz' => 0]];
            }
            foreach ($segs as $s) {
                $text = trim(preg_replace('/\s+/u', ' ', $s['text']) ?? '');
                if ($text !== '') {
                    $paras[] = ['text' => $text, 'bold' => (bool)$s['bold'], 'sz' => (int)$s['sz'], 'list' => $list];
                }
            }
        }
        if ($paras === []) {
            throw new RuntimeException(t('agenda.empty'));
        }
        $title = array_shift($paras);
        $subtitle = '';
        if ($paras !== [] && self::looksLikeDate($paras[0]['text'])) {
            $subtitle = array_shift($paras)['text'];
        }
        $boldSizes = [];
        foreach ($paras as $p) {
            if ($p['bold'] && !$p['list'] && $p['sz'] > 0) {
                $boldSizes[] = $p['sz'];
            }
        }
        $maxSz = $boldSizes === [] ? 0 : max($boldSizes);
        $structured = $maxSz > 0 && count(array_unique($boldSizes)) > 0 && count(array_filter($paras, fn($p) => !$p['list'] && !($p['bold'] && $p['sz'] === $maxSz))) > 0;
        $out = ['# ' . self::cleanItem($title['text'])];
        if ($subtitle !== '') {
            $out[] = '## ' . $subtitle;
        }
        $haveItem = false;
        foreach ($paras as $p) {
            $isItem = $structured ? ($p['bold'] && !$p['list'] && $p['sz'] === $maxSz) : !$p['list'];
            if ($isItem) {
                $out[] = '- ' . self::cleanItem($p['text']);
                $haveItem = true;
            } elseif ($p['list'] && $haveItem) {
                $out[] = '  > • ' . self::cleanItem($p['text']);
            } elseif ($haveItem) {
                $out[] = '  > ' . $p['text'];
            }
        }
        return implode("\n", $out) . "\n";
    }

    /** @return list<array{level:int,text:string}> */
    private static function linesFromPdf(string $path): array
    {
        $bin = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));
        if ($bin === '') {
            throw new RuntimeException(t('agenda.pdf_no_tool'));
        }
        $raw = (string)shell_exec(escapeshellarg($bin) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        return self::linesFromText($raw);
    }

    /** Text/Markdown: Einrückung (2 Leerzeichen oder Tab) = eine Ebene. @return list<array{level:int,text:string}> */
    private static function linesFromText(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^\s*>\s?(.*)$/u', $line, $nm)) {
                $out[] = ['level' => 1, 'text' => trim($nm[1]), 'note' => true];
                continue;
            }
            $isHeading = (bool)preg_match('/^\s*#{1,6}\s/', $line);
            preg_match('/^[ \t]*/', $line, $ws);
            $indent = strlen(str_replace("\t", '    ', $ws[0]));
            $out[] = [
                'level' => $isHeading ? 0 : intdiv($indent, 2),
                'text' => trim($line),
                'heading' => $isHeading,
            ];
        }
        return $out;
    }

    /** Aus den Zeilen den Gliederungstext bauen: erste Zeile Titel, Datumszeile Untertitel, Rest Punkte. */
    private static function linesToOutline(array $lines): string
    {
        $clean = [];
        foreach ($lines as $l) {
            $text = !empty($l['note']) ? trim((string)$l['text']) : self::cleanItem((string)$l['text']);
            if ($text !== '') {
                $clean[] = ['level' => (int)$l['level'], 'text' => $text, 'heading' => !empty($l['heading']), 'note' => !empty($l['note'])];
            }
        }
        if ($clean === []) {
            throw new RuntimeException(t('agenda.empty'));
        }
        $outline = [];
        $title = array_shift($clean);
        $outline[] = '# ' . $title['text'];
        if ($clean !== [] && self::looksLikeDate($clean[0]['text'])) {
            $sub = array_shift($clean);
            $outline[] = '## ' . $sub['text'];
        }
        $base = $clean === [] ? 0 : min(array_column(array_filter($clean, fn($c) => !$c['note']) ?: $clean, 'level'));
        foreach ($clean as $l) {
            if ($l['note']) {
                $outline[] = '  > ' . $l['text'];
                continue;
            }
            $lvl = max(0, $l['level'] - $base);
            $outline[] = str_repeat('  ', min($lvl, 1)) . '- ' . $l['text'];
        }
        return implode("\n", $outline) . "\n";
    }

    /** Aufzählungs- und Nummernzeichen vorn weg, Überschriftzeichen weg. */
    private static function cleanItem(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^#{1,6}\s+/u', '', $text) ?? $text;
        $text = preg_replace('/^(?:[-*•›▪●○–]|\d{1,2}[.)])\s+/u', '', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function looksLikeDate(string $text): bool
    {
        return (bool)preg_match('/\d{1,2}\.\s*(?:\d{1,2}\.|[A-Za-zäöü]{3,})\s*\d{2,4}|\b20\d{2}\b.*\d{1,2}[.:]\d{2}\s*Uhr|\d{1,2}[.:]\d{2}\s*Uhr|^(?:Mo|Di|Mi|Do|Fr|Sa|So)[a-z]*,/iu', $text);
    }

    /** @return array{title:string,subtitle:string,items:list<array{title:string,sub:list<string>,notes:list<string>}>} */
    public static function parseOutline(string $outline): array
    {
        $title = '';
        $subtitle = '';
        $items = [];
        foreach (preg_split('/\R/u', $outline) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^#\s+(.+)$/u', $line, $m) && $title === '') {
                $title = trim($m[1]);
                continue;
            }
            if (preg_match('/^##\s+(.+)$/u', $line, $m) && $subtitle === '') {
                $subtitle = trim($m[1]);
                continue;
            }
            if (preg_match('/^\s*>\s?(.*)$/u', $line, $nm)) {
                if ($items !== [] && trim($nm[1]) !== '') {
                    $items[count($items) - 1]['notes'][] = trim($nm[1]);
                }
                continue;
            }
            $indented = (bool)preg_match('/^(?:\t|\s{2,})\S/u', $line);
            $text = self::cleanItem($line);
            if ($text === '') {
                continue;
            }
            if ($indented && $items !== []) {
                $items[count($items) - 1]['sub'][] = $text;
            } else {
                $items[] = ['title' => $text, 'sub' => [], 'notes' => []];
            }
        }
        if ($title === '' && $items !== []) {
            $title = 'Traktanden';
        }
        return ['title' => $title, 'subtitle' => $subtitle, 'items' => $items];
    }

    /**
     * Folienplan für die Vorschau (legt nichts an).
     * @return array{title:string,slide_count:int,slides:list<array{kind:string,label:string}>,warnings:list<string>}
     */
    public static function planImport(string $outline, string $layoutSetId, array $options = []): array
    {
        $built = self::build($outline, $layoutSetId, $options);
        $slides = [];
        foreach ($built['plan'] as $p) {
            $slides[] = $p;
        }
        return [
            'title' => $built['title'],
            'slide_count' => count($built['slides']),
            'width' => $built['width'],
            'height' => $built['height'],
            'slides' => $slides,
            'warnings' => $built['warnings'],
        ];
    }

    /**
     * @return array{slides:list<array>,title:string,width:int,height:int,warnings:list<string>,layout_set_id:string,insert_index:int}
     */
    public static function import(string $outline, string $layoutSetId, array $options = []): array
    {
        $b = self::build($outline, $layoutSetId, $options);
        return [
            'slides' => $b['slides'],
            'title' => $b['title'],
            'width' => $b['width'],
            'height' => $b['height'],
            'warnings' => $b['warnings'],
            'layout_set_id' => $layoutSetId,
            'insert_index' => $b['insert_index'],
        ];
    }

    /**
     * Layouts des Sets zur Auswahl: id (Folien-ID), Beschriftung, Anzahl Inhaltsfelder
     * (Listenpunkt/Text) und ob es eine Überschrift hat.
     * @return list<array{id:string,label:string,bodies:int,heading:bool}>
     */
    public static function layoutChoices(string $setId): array
    {
        $out = [];
        foreach (LayoutSet::layoutsInOrder($setId) as $slide) {
            $prep = $slide; // roh: prepareLayoutSlide() würde gleiche Rollen (zwei Spalten) zu einem Feld zusammenlegen
            [$heading, $bodies] = self::splitObjects($prep);
            if ($bodies === []) {
                continue;
            }
            $out[] = [
                'id' => (string)($slide['id'] ?? ''),
                'label' => LayoutSet::slideLabel($slide),
                'bodies' => count($bodies),
                'heading' => $heading !== null,
            ];
        }
        return $out;
    }

    /** @return array{0:?array,1:list<array>} erstes Überschriftsfeld, Inhaltsfelder von links nach rechts */
    private static function splitObjects(array $layout): array
    {
        $heading = null;
        $bodies = [];
        foreach ($layout['objects'] ?? [] as $idx => $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                continue;
            }
            $obj['_i'] = $idx;
            $role = LayoutSet::canonicalLogosRole((string)($obj["setRole"] ?? ""));
            if (str_starts_with($role, 'heading')) {
                if ($heading === null || (int)($obj['y'] ?? 0) < (int)($heading['y'] ?? 0)) {
                    $heading = $obj;
                }
            } elseif (in_array($role, ['list_item', 'normal'], true)) {
                $bodies[] = $obj;
            }
        }
        usort($bodies, fn($a, $b) => ((int)($a['x'] ?? 0) <=> (int)($b['x'] ?? 0)) ?: ((int)($a['y'] ?? 0) <=> (int)($b['y'] ?? 0)));
        return [$heading, $bodies];
    }

    /** Layout wählen: gewünschte ID, sonst das erste mit passender Anzahl Inhaltsfelder. */
    private static function pickLayout(string $setId, string $wantedId, int $bodies): ?array
    {
        $fallback = null;
        foreach (LayoutSet::layoutsInOrder($setId) as $slide) {
            $prep = $slide; // roh: prepareLayoutSlide() würde gleiche Rollen (zwei Spalten) zu einem Feld zusammenlegen
            [$heading, $b] = self::splitObjects($prep);
            if ($wantedId !== '' && (string)($slide['id'] ?? '') === $wantedId && $b !== []) {
                return $prep;
            }
            if ($wantedId === '' && $heading !== null) {
                $hasListItem = false;
                foreach ($b as $o) {
                    $hasListItem = $hasListItem || ($o['setRole'] ?? '') === 'list_item';
                }
                if (count($b) === $bodies && $hasListItem) {
                    return $prep;
                }
                if ($fallback === null && $b !== []) {
                    $fallback = $prep;
                }
            }
        }
        return $wantedId === '' ? $fallback : null;
    }

    /** Kommentar des Traktandums in die Notizen der Folie. */
    private static function withNotes(array $slide, string $notes): array
    {
        $slide['notes'] = $notes;
        return $slide;
    }

    /** Folie aus Layout: Überschrift ins Überschriftsfeld, Zeilen gleichmässig auf die Inhaltsfelder. */
    private static function fillColumns(array $layout, string $heading, array $lines, ?array $setMeta): array
    {
        [$hObj, $bodies] = self::splitObjects($layout);
        $cols = $bodies === [] ? 1 : count($bodies);
        $per = max(1, (int)ceil(count($lines) / $cols));
        $chunks = array_chunk($lines, $per);
        $objects = [];
        foreach ($layout['objects'] ?? [] as $idx => $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                $obj['layoutDecor'] = LayoutSet::decorKey($obj);
                $obj['id'] = Storage::generateId(4);
                $objects[] = $obj;
                continue;
            }
            if ($hObj !== null && $idx === $hObj['_i'] && $heading !== '') {
                $objects[] = LayoutSet::mergePlaceholderObject($obj, ['text' => $heading, 'setRole' => (string)($obj['setRole'] ?? 'heading2')]);
                continue;
            }
            foreach ($bodies as $k => $b) {
                if ($idx === $b['_i'] && !empty($chunks[$k])) {
                    $role = (string)($obj['setRole'] ?? 'normal');
                    $text = $role === 'list_item'
                        ? implode("\n", array_map(fn($l) => '- ' . $l, $chunks[$k]))
                        : implode("\n", $chunks[$k]);
                    $objects[] = LayoutSet::mergePlaceholderObject($obj, ['text' => $text, 'setRole' => $role]);
                }
            }
        }
        return [
            'id' => Storage::generateId(4),
            'background' => $layout['background'] ?? ['type' => 'color', 'value' => '#111111'],
            'transition' => 'slide',
            'autoAdvance' => 0,
            'notes' => '',
            'layoutKey' => (string)($layout['layoutKey'] ?? ''),
            'label' => $layout['label'] ?? null,
            'objects' => $objects,
        ];
    }

    private static function build(string $outline, string $layoutSetId, array $options): array
    {
        if (!LayoutSet::isLayoutSet($layoutSetId)) {
            throw new RuntimeException(t('import.layout_set_required'));
        }
        $opt = self::normalizeOptions($options);
        $data = self::parseOutline($outline);
        if ($data['items'] === []) {
            throw new RuntimeException(t('agenda.empty'));
        }
        $setMeta = Presentation::getMeta($layoutSetId) ?? [];
        $warnings = [];
        $slides = [];
        $plan = [];
        $record = function (array $slide, string $kind, string $label) use (&$slides, &$plan) {
            $slides[] = $slide;
            $plan[] = ['kind' => $kind, 'label' => $label, 'thumbHtml' => SlideRenderer::renderSlideThumbnailHtml($slide, null)];
        };

        $titleContent = ['document_title' => $data['title']];
        if ($data['subtitle'] !== '') {
            $titleContent['subtitle'] = $data['subtitle'];
        }
        $titleLayout = LayoutSet::bestLayoutForContent($layoutSetId, $titleContent, $setMeta);
        if ($titleLayout === null) {
            $warnings[] = t('agenda.no_layout', ['label' => $data['title']]);
            $titleLayout = ['objects' => []];
        }
        $record(LayoutSet::fillSlideFromLayout($titleLayout, $titleContent, '', $setMeta), 'title', $data['title']);

        $agendaLayout = self::pickLayout($layoutSetId, $opt['agendaLayout'], 2);
        $detailLayout = self::pickLayout($layoutSetId, $opt['detailLayout'], 1);
        if ($agendaLayout === null) {
            throw new RuntimeException(t('agenda.no_layout', ['label' => $opt['heading']]));
        }

        $n = count($data['items']);
        $insertAfter = $opt['insertAfter'] > 0 ? min($opt['insertAfter'], $n) : 0;
        $insertIndex = 0;
        foreach ($data['items'] as $i => $item) {
            $notes = implode("\n", $item['notes']);
            $record(
                self::withNotes(self::fillColumns($agendaLayout, $opt['heading'], self::agendaLines($data['items'], $i, $opt), $setMeta), $notes),
                'agenda',
                ($i + 1) . '. ' . $item['title']
            );
            if ($opt['subSlides'] && $item['sub'] !== []) {
                if ($detailLayout === null) {
                    $warnings[] = t('agenda.no_layout', ['label' => $item['title']]);
                } else {
                    $record(self::withNotes(self::fillColumns($detailLayout, $item['title'], $item['sub'], $setMeta), $notes), 'detail', $item['title']);
                }
            }
            if ($insertAfter === $i + 1) {
                $insertIndex = count($slides);
            }
        }
        if ($insertAfter === 0) {
            $insertIndex = count($slides);
        }
        return [
            'title' => $data['title'],
            'slides' => $slides,
            'plan' => $plan,
            'warnings' => $warnings,
            'width' => max(100, (int)($setMeta['width'] ?? DEFAULT_SLIDE_WIDTH)),
            'height' => max(100, (int)($setMeta['height'] ?? DEFAULT_SLIDE_HEIGHT)),
            'insert_index' => $insertIndex,
        ];
    }

    /**
     * Legt die Präsentation an. Eine Finanz-/Zusatz-PPTX wird hinter dem gewählten
     * Traktandum eingefügt (ihre Folien bleiben unverändert, meist Bilder).
     *
     * @return array{presentation_id:string,redirect:string,warnings:list<string>}
     */
    public static function createPresentation(
        string $userId,
        string $outline,
        string $layoutSetId,
        array $options,
        string $sectionId = '',
        ?string $extraPptxPath = null
    ): array {
        $result = self::import($outline, $layoutSetId, $options);
        $warnings = $result['warnings'];
        $newId = Presentation::create($userId, trim($result['title']) ?: t('import.default_title'), $result['width'], $result['height']);
        Presentation::updateMeta($newId, ['layout_set_id' => $layoutSetId]);
        $slides = $result['slides'];
        if ($extraPptxPath !== null && is_file($extraPptxPath)) {
            try {
                $extra = PptxImporter::import($extraPptxPath, $newId);
            } catch (Throwable $e) {
                Presentation::delete($newId);
                throw new RuntimeException(t('import.pptx_failed', ['error' => $e->getMessage()]));
            }
            $warnings = array_merge($warnings, $extra['warnings'] ?? []);
            array_splice($slides, (int)$result['insert_index'], 0, $extra['slides'] ?? []);
        }
        Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides ?: [Presentation::defaultSlide()]]);
        ImportJob::assignSection($userId, $newId, $sectionId);
        return [
            'presentation_id' => $newId,
            'redirect' => 'editor.php?id=' . urlencode($newId),
            'warnings' => array_values($warnings),
        ];
    }


    /** Alle Traktanden als Zeilen, das aktuelle hervorgehoben (Markdown-Auszeichnung des Editors). @return list<string> */
    private static function agendaLines(array $items, int $current, array $opt): array
    {
        $lines = [];
        foreach ($items as $i => $item) {
            $t = str_replace(['*', '['], ['', '('], $item['title']);
            if ($i < $current && $opt['doneOn']) {
                $t = '[color=' . $opt['doneColor'] . ']' . $t . '[/color]';
            }
            if ($i === $current) {
                if (in_array($opt['highlight'], ['color', 'both'], true)) {
                    $t = '[color=' . $opt['color'] . ']' . $t . '[/color]';
                }
                if (in_array($opt['highlight'], ['bold', 'both'], true)) {
                    $t = '**' . $t . '**';
                }
            }
            $lines[] = $t;
        }
        return $lines;
    }
}
