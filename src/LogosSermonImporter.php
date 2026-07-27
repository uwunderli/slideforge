<?php
/**
 * Importiert eine aus Logos (Sermon Builder) exportierte HTML-Datei als SlideForge-Präsentation.
 */
class LogosSermonImporter
{
    /** @return array{slides: array, title: string, width: int, height: int, warnings: string[], layout_set_id: string, footer_text?: string} */
    public static function import(string $html, ?string $layoutSetId = null): array
    {
        if ($layoutSetId === null || $layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
            throw new RuntimeException(t('import.layout_set_required'));
        }

        $blocks = self::parseBlocks($html);
        $title = self::extractTitle($blocks);
        $built = self::buildSlidesFromLayoutSet($blocks, $layoutSetId);
        $slides = $built['slides'];
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
    public static function planImport(string $html, string $layoutSetId): array
    {
        if (!LayoutSet::isLayoutSet($layoutSetId)) {
            throw new RuntimeException(t('import.layout_set_required'));
        }
        $blocks = self::parseBlocks($html);
        $title = self::extractTitle($blocks);
        $setMeta = Presentation::getMeta($layoutSetId) ?? [];
        $width = max(100, (int)($setMeta['width'] ?? DEFAULT_SLIDE_WIDTH));
        $height = max(100, (int)($setMeta['height'] ?? DEFAULT_SLIDE_HEIGHT));
        $built = self::buildSlidesFromLayoutSet($blocks, $layoutSetId);
        $sections = $built['sections'] ?? [];
        $sectionSlideCount = array_sum(array_map(
            fn($s) => (int)($s['slide_count'] ?? 0),
            $sections
        ));

        return [
            'title' => $title,
            'slide_count' => count($built['slides']),
            'width' => $width,
            'height' => $height,
            'preamble_slide_count' => max(0, count($built['slides']) - $sectionSlideCount),
            'slides' => self::summarizeSlidesForPreview($built['slides']),
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
    private static function summarizeSlidesForPreview(array $slides): array
    {
        $out = [];
        foreach ($slides as $slide) {
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
                'layoutKey' => (string)($slide['layoutKey'] ?? ''),
                'label' => (string)($slide['label'] ?? ''),
                'preview' => mb_substr($preview, 0, 120),
                'thumbHtml' => SlideRenderer::renderSlideThumbnailHtml($slide, null),
            ];
        }
        return $out;
    }

    /** @param list<array{type: string, text: string, level?: int, part?: string}> $blocks
     * @return array{slides: list<array>, footer_text: string, sections: list<array>, warnings: list<string>}
     */
    private static function buildSlidesFromLayoutSet(array $blocks, string $setId): array
    {
        LayoutSet::syncLayoutMap($setId);
        $setMeta = Presentation::getMeta($setId) ?? [];
        $settings = LayoutSet::logosImportSettings($setMeta);
        $notesOrder = LayoutSet::notesOrder($setMeta);
        $zoneFor = fn(string $role) => LayoutSet::zoneForRole($setMeta, $role);

        $slides = [];
        $sectionSummaries = [];
        $warnings = [];
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

        $openSlideWithRoles = function (string $layoutKey, array $contentByRole) use (
            &$current, $setId, $setMeta, $flushSlide, $flushNotesToString
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
                    $addNote($type, $block['text']);
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

        $parseScriptureRun = function (array $sectionBlocks, int $start) use ($zoneFor): array {
            $n = count($sectionBlocks);
            $i = $start;
            $refParts = [];
            $verseParts = [];
            while ($i < $n && $sectionBlocks[$i]['type'] === 'scripture_block') {
                if (($sectionBlocks[$i]['part'] ?? '') === 'ref') {
                    $refParts[] = $sectionBlocks[$i]['text'];
                } else {
                    $verseParts[] = $sectionBlocks[$i]['text'];
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

        $emitScripture = function (
            string $refText,
            string $verseText,
            ?string $headingRole = null,
            ?string $headingText = null
        ) use (
            $setId, $setMeta, $zoneFor, $settings, $openSlideWithRoles, $addFooter, $addNote
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

            $content = [];
            $layout = LayoutSet::findLayoutForRole($setId, 'scripture_block', $setMeta);
            $layoutKey = $layout
                ? (string)($layout['layoutKey'] ?? 'scripture_block')
                : LayoutSet::resolveLayoutKeyForRole($setId, 'scripture_block', $setMeta);

            if ($layout && LayoutSet::layoutHasRole($layout, 'scripture_ref')) {
                if ($refText !== '') {
                    $content['scripture_ref'] = $refText;
                }
                if ($verseText !== '') {
                    $content['scripture_verse'] = $verseText;
                }
            } else {
                $content['scripture_block'] = $combined;
            }

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

            if ($content !== []) {
                $openSlideWithRoles($layoutKey, $content);
            }
        };

        $processSection = function (array $section) use (
            $settings, $zoneFor, $routeBlock, $openSlide, $openSlideWithRoles,
            $parseScriptureRun, $emitScripture, $setId, $setMeta, &$slides, $flushSlide
        ) {
            $h1 = trim((string)($section['h1'] ?? ''));
            $blocks = $section['blocks'];
            $n = count($blocks);
            $pendingH1 = '';

            if ($h1 !== '' && $zoneFor('heading1') === 'slides') {
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

            $i = 0;
            while ($i < $n) {
                $block = $blocks[$i];
                $type = $block['type'];

                if ($type === 'scripture_block') {
                    $run = $parseScriptureRun($blocks, $i);
                    $emitScripture($run['refText'], $run['verseText']);
                    $i += max(1, $run['consumed']);
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
                $emitScripture($run['refText'], $run['verseText']);
                $pi += max(1, $run['consumed']);
                continue;
            }
            $routeBlock($block, false);
            $pi++;
        }

        foreach ($sections as $section) {
            $before = count($slides);
            $processSection($section);
            $sectionSummaries[] = [
                'title' => trim((string)($section['h1'] ?? '')),
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

    /** @param list<string> $items */
    public static function formatListItemsMarkdown(array $items): string
    {
        $lines = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $item = preg_replace('/^[•›\-*]\s*/u', '', $item) ?? $item;
            $item = preg_replace('/^\d+\.\s*/u', '', $item) ?? $item;
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
