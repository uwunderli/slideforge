<?php
/**
 * Importiert eine aus Logos (Sermon Builder) exportierte HTML-Datei als SlideForge-Präsentation.
 */
class LogosSermonImporter
{
    /** @return array{slides: array, title: string, width: int, height: int, warnings: string[], layout_set_id?: string, footer_text?: string} */
    public static function import(string $html, ?string $templateId = null, ?string $layoutSetId = null): array
    {
        $blocks = self::parseBlocks($html);
        $title = self::extractTitle($blocks);
        $warnings = [];
        $footerText = '';

        if ($layoutSetId !== null && $layoutSetId !== '' && LayoutSet::isLayoutSet($layoutSetId)) {
            $built = self::buildSlidesFromLayoutSet($blocks, $layoutSetId);
            $slides = $built['slides'];
            $footerText = $built['footer_text'] ?? '';
            $resultLayoutSetId = $layoutSetId;
        } else {
            $template = SermonImportTemplate::find($templateId ?? '')
                ?? SermonImportTemplate::find(SermonImportTemplate::defaultId())
                ?? SermonImportTemplate::defaultTemplateFields('Predigt Standard');
            $slides = self::buildSlidesLegacy($blocks, $template);
            $resultLayoutSetId = null;
        }

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
        ];
        if ($resultLayoutSetId) {
            $out['layout_set_id'] = $resultLayoutSetId;
        }
        if ($footerText !== '') {
            $out['footer_text'] = $footerText;
        }
        return $out;
    }

    public static function isLogosExport(string $html): bool
    {
        if (preg_match('/<script[^>]+id=["\']slideforge-source-data["\']/i', $html)) {
            return false;
        }
        return (bool)preg_match(
            '/sermonpromptrichtextstyle|ref\.ly\/|Exportiert aus\s+<a[^>]+logos\.com/i',
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
            if (self::hasRefLyLink($node)) {
                $blocks[] = ['type' => 'scripture_inline', 'text' => $text];
                $inScriptureVerses = false;
                continue;
            }
            if (!$inScriptureVerses && self::isScriptureReferenceLine($node, $text)) {
                $blocks[] = ['type' => 'scripture_block', 'text' => $text, 'part' => 'ref'];
                $inScriptureVerses = true;
                continue;
            }
            if ($inScriptureVerses) {
                $blocks[] = ['type' => 'scripture_block', 'text' => $text, 'part' => 'verse'];
                continue;
            }
            if (self::isListParagraph($node, $text)) {
                $blocks[] = ['type' => 'list_item', 'text' => $text];
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

    /** @param list<array{type: string, text: string, level?: int, part?: string}> $blocks
     * @return array{slides: list<array>, footer_text: string}
     */
    private static function buildSlidesFromLayoutSet(array $blocks, string $setId): array
    {
        LayoutSet::syncLayoutMap($setId);
        $setMeta = Presentation::getMeta($setId) ?? [];
        $notesOrder = LayoutSet::notesOrder($setMeta);
        $breakLevels = [1, 2, 3, 4, 5];
        $zoneFor = fn(string $role) => LayoutSet::zoneForRole($setMeta, $role);

        $slides = [];
        $current = null;
        $notesBucket = [];
        $footerLines = [];

        $flushNotesToString = function () use (&$notesBucket, $notesOrder): string {
            if ($notesBucket === []) {
                return '';
            }
            $parts = [];
            foreach ($notesOrder as $type) {
                foreach ($notesBucket[$type] ?? [] as $line) {
                    $parts[] = $line;
                }
            }
            foreach ($notesBucket as $type => $lines) {
                if (!in_array($type, $notesOrder, true)) {
                    foreach ($lines as $line) {
                        $parts[] = $line;
                    }
                }
            }
            $notesBucket = [];
            return implode("\n\n", array_filter($parts));
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

        $openSlideWithRoles = function (string $layoutKey, array $contentByRole) use (&$current, $setId, $setMeta, $flushSlide, $flushNotesToString) {
            $flushSlide();
            $layout = null;
            if ($contentByRole !== []) {
                $role = (string)array_key_first($contentByRole);
                $layout = LayoutSet::findLayoutForRole($setId, $role, $setMeta);
                if ($layout !== null) {
                    $layoutKey = (string)($layout['layoutKey'] ?? $layoutKey);
                }
            }
            if ($layout === null) {
                $layout = LayoutSet::findLayout($setId, $layoutKey);
            }
            $pendingNotes = $flushNotesToString();
            $contentByRole = array_filter($contentByRole, fn($t) => trim((string)$t) !== '');
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

        $openSlideForZone = function (string $type, string $text) use ($setId, $setMeta, $openSlide, $zoneFor) {
            if ($zoneFor($type) !== 'slides') {
                return false;
            }
            $openSlide($type, $type, $text);
            return true;
        };

        $routeExtra = function (string $type, string $text) use (
            $zoneFor, $addNote, $addFooter, $openSlideForZone
        ) {
            $zone = $zoneFor($type);
            if ($zone === 'unused') {
                return;
            }
            if ($zone === 'footer') {
                $addFooter($text);
                return;
            }
            if ($zone === 'custom') {
                $addNote($type, $text);
                return;
            }
            if ($zone === 'slides') {
                $openSlideForZone($type, $text);
            }
        };

        $i = 0;
        $n = count($blocks);
        while ($i < $n) {
            $block = $blocks[$i];
            $type = $block['type'];

            if ($type === 'document_title') {
                $zone = $zoneFor('document_title');
                if ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    $addNote('document_title', $block['text']);
                } elseif ($zone === 'slides') {
                    $layout = LayoutSet::findLayoutForRole($setId, 'document_title', $setMeta);
                    $layoutKey = $layout ? (string)($layout['layoutKey'] ?? 'document_title') : LayoutSet::resolveLayoutKeyForRole($setId, 'document_title', $setMeta);
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
                $i++;
                continue;
            }

            if (str_starts_with($type, 'heading')) {
                $zone = $zoneFor($type);
                if ($zone === 'unused') {
                    $i++;
                    continue;
                }
                if ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    $addNote($type, $block['text']);
                } else {
                    $level = (int)($block['level'] ?? (int)substr($type, -1));
                    if (in_array($level, $breakLevels, true)) {
                        $openSlide($type, $type, $block['text']);
                    } else {
                        $addNote($type, $block['text']);
                    }
                }
                $i++;
                continue;
            }

            if ($type === 'scripture_block') {
                $refParts = [];
                $verseParts = [];
                while ($i < $n && $blocks[$i]['type'] === 'scripture_block') {
                    if (($blocks[$i]['part'] ?? '') === 'ref') {
                        $refParts[] = $blocks[$i]['text'];
                    } else {
                        $verseParts[] = $blocks[$i]['text'];
                    }
                    $i++;
                }
                $refText = trim(implode("\n", array_filter($refParts)));
                $verseText = trim(implode("\n\n", array_filter($verseParts)));
                $layout = LayoutSet::findLayoutForRole($setId, 'scripture_block', $setMeta);
                $layoutKey = $layout ? (string)($layout['layoutKey'] ?? 'scripture_block') : LayoutSet::resolveLayoutKeyForRole($setId, 'scripture_block', $setMeta);
                if ($zoneFor('scripture_block') === 'slides') {
                    $content = [];
                    if ($layout && LayoutSet::layoutHasRole($layout, 'scripture_ref')) {
                        if ($refText !== '') {
                            $content['scripture_ref'] = $refText;
                        }
                        if ($verseText !== '') {
                            $content['scripture_verse'] = $verseText;
                        }
                    } else {
                        $combined = trim(implode("\n\n", array_filter([$refText, $verseText])));
                        if ($combined !== '') {
                            $content['scripture_block'] = $combined;
                        }
                    }
                    if ($content !== []) {
                        $openSlideWithRoles($layoutKey, $content);
                    }
                } elseif ($zoneFor('scripture_block') === 'footer') {
                    $addFooter(trim(implode("\n\n", array_filter([$refText, $verseText]))));
                } elseif ($zoneFor('scripture_block') === 'custom') {
                    $combined = trim(implode("\n\n", array_filter([$refText, $verseText])));
                    if ($combined !== '') {
                        $addNote('scripture_block', $combined);
                    }
                }
                continue;
            }

            if (in_array($type, ['normal', 'list_item'], true)) {
                $zone = $zoneFor($type);
                if ($zone === 'slides') {
                    $openSlideForZone($type, $block['text']);
                } elseif ($zone === 'footer') {
                    $addFooter($block['text']);
                } elseif ($zone === 'custom') {
                    $addNote($type, $block['text']);
                }
                $i++;
                continue;
            }

            if (in_array($type, LayoutSet::LOGOS_ZONE_ROLES, true)) {
                $routeExtra($type, $block['text']);
                $i++;
                continue;
            }

            $addNote($type, $block['text']);
            $i++;
        }

        $flushSlide();
        return [
            'slides' => $slides,
            'footer_text' => implode("\n\n", $footerLines),
        ];
    }

    /** @param list<array{type: string, text: string, level?: int, part?: string}> $blocks */
    private static function buildSlidesLegacy(array $blocks, array $template): array
    {
        $elements = SermonImportTemplate::elementMap($template);
        $breakLevels = $template['slideBreakLevels'] ?? [1, 2, 3, 4, 5];
        $slides = [];
        $titleSlide = null;
        $current = null;

        $flush = function () use (&$slides, &$current) {
            if ($current === null) {
                return;
            }
            if ($current['objects'] || trim($current['notes']) !== '') {
                $slides[] = $current;
            }
            $current = null;
        };

        $ensureSlide = function () use (&$current, $template) {
            if ($current !== null) {
                return;
            }
            $current = self::emptySlide($template);
        };

        $appendNotes = function (string $text) use (&$current, $ensureSlide) {
            $ensureSlide();
            $current['notes'] = trim($current['notes'] . ($current['notes'] !== '' ? "\n\n" : '') . $text);
        };

        $appendObject = function (string $type, string $text) use (&$current, $ensureSlide, $elements) {
            $ensureSlide();
            $obj = self::makeTextObject($text, $elements[$type] ?? ['target' => 'slide']);
            if ($obj) {
                $current['objects'][] = $obj;
            }
        };

        $i = 0;
        $n = count($blocks);
        while ($i < $n) {
            $block = $blocks[$i];
            $type = $block['type'];
            $placement = $elements[$type] ?? ['target' => 'skip'];
            $target = $placement['target'] ?? 'skip';

            if ($type === 'document_title' && !empty($template['createTitleSlide']) && $target === 'title_slide') {
                $titleSlide = self::emptySlide($template);
                $obj = self::makeTextObject($block['text'], $placement);
                if ($obj) {
                    $titleSlide['objects'][] = $obj;
                }
                $i++;
                continue;
            }

            if ($type === 'meta' || $target === 'skip') {
                $i++;
                continue;
            }

            if (str_starts_with($type, 'heading')) {
                $level = (int)($block['level'] ?? (int)substr($type, -1));
                if (in_array($level, $breakLevels, true)) {
                    $flush();
                    $current = self::emptySlide($template);
                } else {
                    $ensureSlide();
                }
                if ($target === 'slide') {
                    $appendObject($type, $block['text']);
                } elseif ($target === 'notes') {
                    $appendNotes($block['text']);
                }
                $i++;
                continue;
            }

            if ($type === 'scripture_block') {
                $parts = [];
                while ($i < $n && $blocks[$i]['type'] === 'scripture_block') {
                    $parts[] = $blocks[$i]['text'];
                    $i++;
                }
                $combined = implode("\n\n", array_filter($parts));
                if ($target === 'slide') {
                    $appendObject('scripture_block', $combined);
                } elseif ($target === 'notes') {
                    $appendNotes($combined);
                }
                continue;
            }

            if ($target === 'notes') {
                $appendNotes($block['text']);
            } elseif ($target === 'slide') {
                $appendObject($type, $block['text']);
            }

            $i++;
        }

        $flush();

        if ($titleSlide !== null) {
            array_unshift($slides, $titleSlide);
        }

        return $slides;
    }

    private static function emptySlide(array $template): array
    {
        return [
            'id' => Storage::generateId(4),
            'background' => $template['background'] ?? ['type' => 'color', 'value' => '#111111'],
            'transition' => 'slide',
            'autoAdvance' => 0,
            'notes' => '',
            'objects' => [],
        ];
    }

    private static function makeTextObject(string $text, array $placement): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $style = $placement;
        if (!empty($placement['textTemplateId'])) {
            $tpl = TextTemplate::resolve($placement['textTemplateId']);
            if ($tpl) {
                $style = array_merge($tpl, $placement);
            }
        }

        return [
            'id' => 'o' . bin2hex(random_bytes(4)),
            'type' => 'text',
            'rotation' => 0,
            'opacity' => 1,
            'animType' => 'none',
            'animOrder' => 1,
            'animAutoAdvance' => 0,
            'animDuration' => 0,
            'x' => (int)($style['x'] ?? 100),
            'y' => (int)($style['y'] ?? 100),
            'w' => (int)($style['w'] ?? 1720),
            'h' => (int)($style['h'] ?? 100),
            'text' => $text,
            'fontFamily' => $style['fontFamily'] ?? 'Open Sans',
            'fontSize' => (int)($style['fontSize'] ?? 65),
            'fontWeight' => ($style['fontWeight'] ?? '') === 'bold' ? 'bold' : 'normal',
            'italic' => !empty($style['italic']),
            'underline' => !empty($style['underline']),
            'strikethrough' => !empty($style['strikethrough']),
            'uppercase' => !empty($style['uppercase']),
            'smallCaps' => !empty($style['smallCaps']),
            'animPerLine' => false,
            'color' => $style['color'] ?? '#ffffff',
            'align' => in_array($style['align'] ?? '', ['left', 'center', 'right'], true) ? $style['align'] : 'left',
        ];
    }

    private static function nodeText(DOMElement $node): string
    {
        $text = $node->textContent ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        return trim($text);
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
