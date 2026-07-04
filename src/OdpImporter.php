<?php
/**
 * Importiert eine .odp-Datei (LibreOffice Impress / OpenDocument Präsentation) in unser
 * Folien-Objektmodell. Spiegelt die Struktur von OdpExporter/PptxImporter: häufige Fälle
 * abdecken, Rest überspringen und als Warnung melden.
 *
 * Unterstützt: Text, Rechtecke, Ellipsen, Bilder, Linien, Gruppen, einfache Hintergründe.
 * Bekannte Lücken: Tabellen, Diagramme, Animationen/Übergänge, Video/Audio, komplexe
 * Pfade/Custom-Shapes (werden als Rechteck angenähert), Vererbung in ODF-Stilvorlagen
 * nur teilweise aufgelöst.
 */
class OdpImporter
{
    private const PX_PER_CM = 37.795275591;

    /** @return array{slides: array, width: int, height: int, warnings: string[]} */
    public static function import(string $filePath, string $presentationId): array
    {
        $warnings = [];
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(t('import.odp_open_failed'));
        }

        $contentRaw = $zip->getFromName('content.xml');
        if ($contentRaw === false) {
            $zip->close();
            throw new RuntimeException(t('import.odp_invalid'));
        }
        $contentDom = self::loadXml($contentRaw);
        $stylesRaw = $zip->getFromName('styles.xml');
        $stylesDom = $stylesRaw !== false ? self::loadXml($stylesRaw) : null;

        $graphicStyles = [];
        $paragraphStyles = [];
        $drawingPageStyles = [];
        $pageLayouts = [];
        self::collectStyles($contentDom, $graphicStyles, $paragraphStyles, $drawingPageStyles, $pageLayouts);
        if ($stylesDom) {
            self::collectStyles($stylesDom, $graphicStyles, $paragraphStyles, $drawingPageStyles, $pageLayouts);
        }

        [$width, $height] = self::resolvePageSize($contentDom, $pageLayouts);

        $pages = [];
        foreach ($contentDom->getElementsByTagName('page') as $page) {
            if (($page->localName ?? '') === 'page') {
                $pages[] = $page;
            }
        }
        if (empty($pages)) {
            $zip->close();
            throw new RuntimeException(t('import.odp_no_slides'));
        }

        $mediaIndex = 1;
        $slides = [];
        foreach ($pages as $idx => $page) {
            $slideNum = $idx + 1;
            try {
                $slides[] = self::parsePage($page, $zip, $graphicStyles, $paragraphStyles, $drawingPageStyles, $presentationId, $mediaIndex, $warnings, $slideNum);
            } catch (Throwable $e) {
                $warnings[] = t('import.odp_slide_failed', ['n' => $slideNum, 'error' => $e->getMessage()]);
                $slides[] = ['id' => 'slide' . bin2hex(random_bytes(4)), 'background' => null, 'objects' => [], 'notes' => '', 'transition' => 'slide', 'autoAdvance' => 0];
            }
        }

        $zip->close();
        return ['slides' => $slides, 'width' => $width, 'height' => $height, 'warnings' => $warnings];
    }

    private static function loadXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        return $dom;
    }

    /** @param array<string,array<string,mixed>> $pageLayouts */
    private static function resolvePageSize(DOMDocument $contentDom, array $pageLayouts): array
    {
        $width = 1920;
        $height = 1080;
        foreach ($contentDom->getElementsByTagName('page-layout') as $pl) {
            foreach ($pl->childNodes as $child) {
                if (($child->localName ?? '') !== 'page-layout-properties') {
                    continue;
                }
                $w = self::parseLength($child->getAttribute('fo:page-width'));
                $h = self::parseLength($child->getAttribute('fo:page-height'));
                if ($w > 0) {
                    $width = max(100, (int)round($w));
                }
                if ($h > 0) {
                    $height = max(100, (int)round($h));
                }
                return [$width, $height];
            }
        }
        foreach ($pageLayouts as $layout) {
            if (!empty($layout['width']) && !empty($layout['height'])) {
                return [max(100, (int)round($layout['width'])), max(100, (int)round($layout['height']))];
            }
        }
        return [$width, $height];
    }

    private static function collectStyles(DOMDocument $dom, array &$graphicStyles, array &$paragraphStyles, array &$drawingPageStyles, array &$pageLayouts): void
    {
        foreach ($dom->getElementsByTagName('style') as $style) {
            if (!($style instanceof DOMElement)) {
                continue;
            }
            $name = $style->getAttribute('style:name');
            if ($name === '') {
                continue;
            }
            $family = $style->getAttribute('style:family');

            if ($family === 'graphic') {
                foreach ($style->childNodes as $child) {
                    if (($child->localName ?? '') === 'graphic-properties') {
                        $graphicStyles[$name] = self::parseGraphicProperties($child);
                    }
                }
            } elseif ($family === 'paragraph') {
                $para = [];
                foreach ($style->childNodes as $child) {
                    $ln = $child->localName ?? '';
                    if ($ln === 'paragraph-properties') {
                        $para['align'] = $child->getAttribute('fo:text-align');
                    }
                    if ($ln === 'text-properties') {
                        $para = array_merge($para, self::parseTextProperties($child));
                    }
                }
                if ($para) {
                    $paragraphStyles[$name] = $para;
                }
            } elseif ($family === 'drawing-page') {
                foreach ($style->childNodes as $child) {
                    if (($child->localName ?? '') === 'drawing-page-properties') {
                        $drawingPageStyles[$name] = self::parseDrawingPageProperties($child);
                    }
                }
            } elseif ($family === 'page-layout') {
                foreach ($style->childNodes as $child) {
                    if (($child->localName ?? '') === 'page-layout-properties') {
                        $pageLayouts[$name] = [
                            'width' => self::parseLength($child->getAttribute('fo:page-width')),
                            'height' => self::parseLength($child->getAttribute('fo:page-height')),
                        ];
                    }
                }
            }
        }
    }

    /** @return array<string,mixed> */
    private static function parseGraphicProperties(DOMNode $node): array
    {
        if (!($node instanceof DOMElement)) {
            return [];
        }
        return [
            'fill' => $node->getAttribute('draw:fill'),
            'fillColor' => self::normalizeColor($node->getAttribute('draw:fill-color') ?: '#888888'),
            'stroke' => $node->getAttribute('draw:stroke'),
            'strokeColor' => self::normalizeColor($node->getAttribute('svg:stroke-color') ?: '#ffffff'),
            'strokeWidth' => self::parseLength($node->getAttribute('svg:stroke-width')),
            'opacity' => self::parseOpacity($node->getAttribute('draw:opacity')),
        ];
    }

    /** @return array<string,mixed> */
    private static function parseDrawingPageProperties(DOMNode $node): array
    {
        if (!($node instanceof DOMElement)) {
            return [];
        }
        return [
            'fill' => $node->getAttribute('draw:fill'),
            'fillColor' => self::normalizeColor($node->getAttribute('draw:fill-color') ?: '#111111'),
        ];
    }

    /** @return array<string,mixed> */
    private static function parseTextProperties(DOMNode $node): array
    {
        if (!($node instanceof DOMElement)) {
            return [];
        }
        $fontSize = 32;
        $sz = $node->getAttribute('fo:font-size');
        if ($sz !== '' && preg_match('/^([\d.]+)pt$/', $sz, $m)) {
            $fontSize = max(6, (int)round(((float)$m[1]) / 0.75));
        }
        return [
            'fontFamily' => $node->getAttribute('fo:font-family') ?: 'Open Sans',
            'fontSize' => $fontSize,
            'fontWeight' => $node->getAttribute('fo:font-weight') === 'bold' ? 'bold' : 'normal',
            'italic' => $node->getAttribute('fo:font-style') === 'italic',
            'underline' => $node->getAttribute('style:text-underline-style') !== '' && $node->getAttribute('style:text-underline-style') !== 'none',
            'strikethrough' => $node->getAttribute('style:text-line-through-style') !== '' && $node->getAttribute('style:text-line-through-style') !== 'none',
            'color' => self::normalizeColor($node->getAttribute('fo:color') ?: '#ffffff'),
        ];
    }

    private static function parseLength(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0.0;
        }
        $value = trim($value);
        if (preg_match('/^(-?[\d.]+)(cm|mm|in|pt|px)?$/', $value, $m)) {
            $n = (float)$m[1];
            $unit = $m[2] ?? 'cm';
            return match ($unit) {
                'cm' => $n * self::PX_PER_CM,
                'mm' => $n * self::PX_PER_CM / 10,
                'in' => $n * 96,
                'pt' => $n * 96 / 72,
                'px' => $n,
                default => $n * self::PX_PER_CM,
            };
        }
        return 0.0;
    }

    private static function parseOpacity(?string $value): float
    {
        if ($value === null || $value === '') {
            return 1.0;
        }
        if (str_ends_with($value, '%')) {
            return max(0, min(1, (float)rtrim($value, '%') / 100));
        }
        return max(0, min(1, (float)$value));
    }

    private static function normalizeColor(string $color): string
    {
        $c = ltrim(trim($color), '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $c)) {
            return strtoupper($c);
        }
        return '888888';
    }

    /** @return array<string,mixed> */
    private static function resolveGraphicStyle(?string $styleName, array $graphicStyles): array
    {
        if ($styleName === '' || !isset($graphicStyles[$styleName])) {
            return [
                'fill' => 'none', 'fillColor' => '888888', 'stroke' => 'none',
                'strokeColor' => 'ffffff', 'strokeWidth' => 0.0, 'opacity' => 1.0,
            ];
        }
        return $graphicStyles[$styleName];
    }

    /** @return array{0:float,1:float,2:float,3:float,4:float} x,y,w,h,rotation */
    private static function elementGeometry(DOMElement $el): array
    {
        $transform = $el->getAttribute('draw:transform');
        if ($transform !== '') {
            [$x, $y, $rot] = self::parseTransform($transform);
            $w = self::parseLength($el->getAttribute('svg:width'));
            $h = self::parseLength($el->getAttribute('svg:height'));
            return [$x, $y, max(2, $w), max(2, $h), $rot];
        }
        return [
            self::parseLength($el->getAttribute('svg:x')),
            self::parseLength($el->getAttribute('svg:y')),
            max(2, self::parseLength($el->getAttribute('svg:width'))),
            max(2, self::parseLength($el->getAttribute('svg:height'))),
            0.0,
        ];
    }

    /** @return array{0:float,1:float,2:float} */
    private static function parseTransform(string $transform): array
    {
        $x = 0.0;
        $y = 0.0;
        $rot = 0.0;
        if (preg_match('/rotate\(([-\d.]+)\)/', $transform, $m)) {
            $rot = -((float)$m[1]) * 180 / M_PI;
        }
        if (preg_match('/translate\(([^,]+),([^)]+)\)/', $transform, $m)) {
            $x = self::parseLength(trim($m[1]));
            $y = self::parseLength(trim($m[2]));
        }
        return [$x, $y, $rot];
    }

    private static function parsePage(DOMElement $page, ZipArchive $zip, array $graphicStyles, array $paragraphStyles, array $drawingPageStyles, string $presentationId, int &$mediaIndex, array &$warnings, int $slideNum): array
    {
        $pageStyle = $page->getAttribute('draw:style-name');
        $background = null;
        if ($pageStyle !== '' && isset($drawingPageStyles[$pageStyle])) {
            $bg = $drawingPageStyles[$pageStyle];
            if (($bg['fill'] ?? '') === 'solid') {
                $background = ['type' => 'color', 'value' => '#' . ($bg['fillColor'] ?? '111111')];
            }
        }

        $objects = [];
        self::walkNodes($page, 0, 0, $zip, $graphicStyles, $paragraphStyles, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);

        return [
            'id' => 'slide' . bin2hex(random_bytes(4)),
            'background' => $background,
            'objects' => $objects,
            'notes' => '',
            'transition' => 'slide',
            'autoAdvance' => 0,
        ];
    }

    private static function walkNodes(DOMNode $parent, float $groupOffX, float $groupOffY, ZipArchive $zip, array $graphicStyles, array $paragraphStyles, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        foreach ($parent->childNodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            $local = $node->localName ?? '';
            if ($local === 'g') {
                self::parseGroup($node, $groupOffX, $groupOffY, $zip, $graphicStyles, $paragraphStyles, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
            } elseif ($local === 'frame') {
                self::parseFrame($node, $groupOffX, $groupOffY, $zip, $graphicStyles, $paragraphStyles, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
            } elseif ($local === 'rect') {
                self::parseRect($node, $groupOffX, $groupOffY, $graphicStyles, $paragraphStyles, $objects, $warnings, $slideNum);
            } elseif ($local === 'ellipse' || $local === 'circle') {
                self::parseEllipse($node, $groupOffX, $groupOffY, $graphicStyles, $objects);
            } elseif ($local === 'line') {
                self::parseLine($node, $groupOffX, $groupOffY, $graphicStyles, $objects);
            } elseif (in_array($local, ['custom-shape', 'path', 'polygon', 'polyline', 'connector'], true)) {
                self::parseApproxShape($node, $groupOffX, $groupOffY, $graphicStyles, $objects, $warnings, $slideNum, $local);
            } elseif (in_array($local, ['object', 'object-ole'], true)) {
                $warnings[] = t('import.odp_unsupported_object', ['n' => $slideNum, 'type' => $local]);
            }
        }
    }

    private static function parseGroup(DOMElement $g, float $groupOffX, float $groupOffY, ZipArchive $zip, array $graphicStyles, array $paragraphStyles, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        $gx = self::parseLength($g->getAttribute('svg:x'));
        $gy = self::parseLength($g->getAttribute('svg:y'));
        $transform = $g->getAttribute('draw:transform');
        if ($transform !== '') {
            [$tx, $ty] = self::parseTransform($transform);
            $gx += $tx;
            $gy += $ty;
        }
        self::walkNodes($g, $groupOffX + $gx, $groupOffY + $gy, $zip, $graphicStyles, $paragraphStyles, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
    }

    private static function parseFrame(DOMElement $frame, float $groupOffX, float $groupOffY, ZipArchive $zip, array $graphicStyles, array $paragraphStyles, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        foreach ($frame->childNodes as $child) {
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $local = $child->localName ?? '';
            if ($local === 'image') {
                self::parseImage($frame, $child, $groupOffX, $groupOffY, $zip, $graphicStyles, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
                return;
            }
            if ($local === 'text-box') {
                self::parseTextBox($frame, $child, $groupOffX, $groupOffY, $graphicStyles, $paragraphStyles, $objects);
                return;
            }
        }
    }

    private static function parseImage(DOMElement $frame, DOMElement $imageNode, float $groupOffX, float $groupOffY, ZipArchive $zip, array $graphicStyles, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        $href = $imageNode->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        if ($href === '') {
            $href = $imageNode->getAttribute('xlink:href');
        }
        if ($href === '') {
            $warnings[] = t('import.odp_image_failed', ['n' => $slideNum]);
            return;
        }
        $mediaPath = ltrim($href, '/');
        $data = $zip->getFromName($mediaPath);
        if ($data === false) {
            $warnings[] = t('import.odp_media_missing', ['path' => $mediaPath]);
            return;
        }
        $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION)) ?: 'png';
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $warnings[] = t('import.odp_unsupported_media', ['ext' => $ext]);
            return;
        }
        $filename = 'odp_img' . $mediaIndex . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $mediaIndex++;
        $assetsDir = Presentation::dir($presentationId) . '/assets';
        file_put_contents($assetsDir . '/' . $filename, $data);

        [$x, $y, $w, $h, $rot] = self::elementGeometry($frame);
        $style = self::resolveGraphicStyle($frame->getAttribute('draw:style-name'), $graphicStyles);
        $objects[] = [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'image',
            'x' => round($groupOffX + $x), 'y' => round($groupOffY + $y),
            'w' => round($w), 'h' => round($h), 'rotation' => round($rot, 1),
            'opacity' => $style['opacity'],
            'src' => 'asset.php?id=' . urlencode($presentationId) . '&file=' . urlencode($filename),
        ];
    }

    private static function parseTextBox(DOMElement $frame, DOMElement $textBox, float $groupOffX, float $groupOffY, array $graphicStyles, array $paragraphStyles, array &$objects): void
    {
        [$x, $y, $w, $h, $rot] = self::elementGeometry($frame);
        $style = self::resolveGraphicStyle($frame->getAttribute('draw:style-name'), $graphicStyles);

        $lines = [];
        $textProps = [
            'fontFamily' => 'Open Sans', 'fontSize' => 32, 'fontWeight' => 'normal',
            'italic' => false, 'underline' => false, 'strikethrough' => false,
            'color' => 'ffffff', 'align' => 'left',
        ];
        foreach ($textBox->getElementsByTagName('p') as $p) {
            if (($p->localName ?? '') !== 'p') {
                continue;
            }
            $pStyleName = $p->getAttribute('text:style-name');
            if ($pStyleName !== '' && isset($paragraphStyles[$pStyleName])) {
                $ps = $paragraphStyles[$pStyleName];
                $textProps = array_merge($textProps, $ps);
                $alignMap = ['start' => 'left', 'center' => 'center', 'end' => 'right', 'justify' => 'left'];
                if (isset($ps['align'])) {
                    $textProps['align'] = $alignMap[$ps['align']] ?? 'left';
                }
            }
            $lineText = trim($p->textContent ?? '');
            if ($lineText !== '' || empty($lines)) {
                $lines[] = $lineText;
            }
        }
        $text = implode("\n", $lines);
        if (trim($text) === '') {
            return;
        }

        $objects[] = [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'text',
            'x' => round($groupOffX + $x), 'y' => round($groupOffY + $y),
            'w' => round($w), 'h' => round($h), 'rotation' => round($rot, 1),
            'opacity' => $style['opacity'],
            'text' => $text,
            'fontFamily' => $textProps['fontFamily'],
            'fontSize' => (int)$textProps['fontSize'],
            'fontWeight' => $textProps['fontWeight'],
            'italic' => !empty($textProps['italic']),
            'underline' => !empty($textProps['underline']),
            'strikethrough' => !empty($textProps['strikethrough']),
            'color' => '#' . ($textProps['color'] ?? 'ffffff'),
            'align' => $textProps['align'],
        ];
    }

    private static function parseRect(DOMElement $rect, float $groupOffX, float $groupOffY, array $graphicStyles, array $paragraphStyles, array &$objects, array &$warnings, int $slideNum): void
    {
        $hasText = false;
        foreach ($rect->getElementsByTagName('p') as $p) {
            if (($p->localName ?? '') === 'p' && trim($p->textContent ?? '') !== '') {
                $hasText = true;
                break;
            }
        }
        if ($hasText) {
            $fakeFrame = $rect;
            foreach ($rect->childNodes as $child) {
                if (($child->localName ?? '') === 'text-box') {
                    self::parseTextBox($rect, $child, $groupOffX, $groupOffY, $graphicStyles, $paragraphStyles, $objects);
                    return;
                }
            }
            // Text direkt in draw:rect (z.B. Video-Platzhalter) – als Form mit Beschriftung ignorieren
        }

        [$x, $y, $w, $h, $rot] = self::elementGeometry($rect);
        $style = self::resolveGraphicStyle($rect->getAttribute('draw:style-name'), $graphicStyles);
        $objects[] = self::shapeObject($groupOffX + $x, $groupOffY + $y, $w, $h, $rot, $style, 'rect');
    }

    private static function parseEllipse(DOMElement $ellipse, float $groupOffX, float $groupOffY, array $graphicStyles, array &$objects): void
    {
        [$x, $y, $w, $h, $rot] = self::elementGeometry($ellipse);
        $style = self::resolveGraphicStyle($ellipse->getAttribute('draw:style-name'), $graphicStyles);
        $objects[] = self::shapeObject($groupOffX + $x, $groupOffY + $y, $w, $h, $rot, $style, 'ellipse');
    }

    private static function parseLine(DOMElement $line, float $groupOffX, float $groupOffY, array $graphicStyles, array &$objects): void
    {
        $x1 = self::parseLength($line->getAttribute('svg:x1'));
        $y1 = self::parseLength($line->getAttribute('svg:y1'));
        $x2 = self::parseLength($line->getAttribute('svg:x2'));
        $y2 = self::parseLength($line->getAttribute('svg:y2'));
        $style = self::resolveGraphicStyle($line->getAttribute('draw:style-name'), $graphicStyles);
        $strokeWidth = max(1, (int)round($style['strokeWidth'] ?: 3));
        $objects[] = [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'shape', 'shapeType' => 'line',
            'x' => round($groupOffX + min($x1, $x2)), 'y' => round($groupOffY + min($y1, $y2)),
            'w' => round(max(2, abs($x2 - $x1))), 'h' => round(max(2, abs($y2 - $y1))),
            'rotation' => 0, 'opacity' => $style['opacity'],
            'stroke' => '#' . ($style['strokeColor'] ?? 'ffffff'), 'strokeWidth' => $strokeWidth,
        ];
    }

    private static function parseApproxShape(DOMElement $node, float $groupOffX, float $groupOffY, array $graphicStyles, array &$objects, array &$warnings, int $slideNum, string $local): void
    {
        $warnings[] = t('import.odp_unsupported_shape', ['n' => $slideNum, 'type' => $local]);
        [$x, $y, $w, $h, $rot] = self::elementGeometry($node);
        $style = self::resolveGraphicStyle($node->getAttribute('draw:style-name'), $graphicStyles);
        $objects[] = self::shapeObject($groupOffX + $x, $groupOffY + $y, $w, $h, $rot, $style, 'rect');
    }

    /** @param array<string,mixed> $style */
    private static function shapeObject(float $x, float $y, float $w, float $h, float $rot, array $style, string $type): array
    {
        $obj = [
            'id' => 'o' . bin2hex(random_bytes(4)),
            'x' => round($x), 'y' => round($y), 'w' => round($w), 'h' => round($h),
            'rotation' => round($rot, 1), 'opacity' => $style['opacity'],
            'stroke' => 'transparent', 'strokeWidth' => 0,
        ];
        if ($type === 'ellipse') {
            $obj['type'] = 'ellipse';
        } else {
            $obj['type'] = 'rect';
        }
        if (($style['fill'] ?? '') === 'none') {
            $obj['fillType'] = 'none';
        } else {
            $obj['fillType'] = 'solid';
            $obj['fill'] = '#' . ($style['fillColor'] ?? '3A6C8D');
        }
        $strokeWidth = (float)($style['strokeWidth'] ?? 0);
        if (($style['stroke'] ?? '') === 'solid' && $strokeWidth > 0) {
            $obj['stroke'] = '#' . ($style['strokeColor'] ?? 'ffffff');
            $obj['strokeWidth'] = max(1, (int)round($strokeWidth));
        }
        return $obj;
    }
}
