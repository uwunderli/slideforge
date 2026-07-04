<?php
/**
 * Importiert eine .pptx-Datei (PowerPoint, öffnet auch aus LibreOffice Impress exportierte
 * PPTX-Dateien) in unser Folien-Objektmodell.
 *
 * WICHTIG - realistische Grenzen: PPTX-Dateien aus der freien Wildbahn sind sehr
 * unterschiedlich aufgebaut. Diese Klasse deckt die häufigsten Fälle ab (Rechtecke, Ellipsen,
 * einfache Formen, Text, Bilder, Verbindungslinien, gruppierte Objekte) - und überspringt
 * NICHT unterstützte Elemente statt beim ersten Problem komplett abzubrechen. Jedes
 * übersprungene/angenäherte Element landet als Warnung in $warnings, die dem Nutzer nach
 * dem Import angezeigt wird.
 *
 * Bekannte, bewusste Lücken:
 * - Tabellen, Diagramme, SmartArt: werden übersprungen (kein Gegenstück in unserem Modell).
 * - Theme-Farben (schemeClr wie "accent1") werden auf eine feste Standardpalette abgebildet,
 *   nicht exakt aus der Theme-Datei der Quelldatei aufgelöst - Farbton kann leicht abweichen.
 * - Nur die Formatierung des ERSTEN Text-Runs eines Textfelds wird übernommen (unser Modell
 *   kennt keine Teilformatierung pro Wort/Zeichen).
 * - Video/Audio-Objekte in der PPTX werden übersprungen.
 * - Animationen/Übergänge werden nicht importiert.
 */
class PptxImporter
{
    private const EMU_PER_PX = 9525;

    private const SCHEME_COLOR_FALLBACK = [
        'dk1' => '000000', 'tx1' => '000000', 'lt1' => 'FFFFFF', 'bg1' => 'FFFFFF',
        'dk2' => '1F1F1F', 'tx2' => '1F1F1F', 'lt2' => 'EEEEEE', 'bg2' => 'EEEEEE',
        'accent1' => '3A6C8D', 'accent2' => '87B42B', 'accent3' => 'E0A030',
        'accent4' => 'C05050', 'accent5' => '7050A0', 'accent6' => '509090',
        'hlink' => '3A6C8D', 'folHlink' => '87B42B',
    ];

    /** @return array{slides: array, width: int, height: int, warnings: string[]} */
    public static function import(string $filePath, string $presentationId): array
    {
        $warnings = [];
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(t('import.pptx_open_failed'));
        }

        $presXmlRaw = $zip->getFromName('ppt/presentation.xml');
        if ($presXmlRaw === false) {
            $zip->close();
            throw new RuntimeException(t('import.pptx_invalid'));
        }
        $presDom = self::loadXml($presXmlRaw);

        $width = 1920;
        $height = 1080;
        $sldSzList = $presDom->getElementsByTagName('sldSz');
        if ($sldSzList->length > 0) {
            $sldSz = $sldSzList->item(0);
            $width = max(100, (int)round(((int)$sldSz->getAttribute('cx')) / self::EMU_PER_PX));
            $height = max(100, (int)round(((int)$sldSz->getAttribute('cy')) / self::EMU_PER_PX));
        }

        // Reihenfolge der Folien über presentation.xml.rels auflösen
        $relMap = self::readRels($zip, 'ppt/_rels/presentation.xml.rels');
        $slideFiles = [];
        foreach ($presDom->getElementsByTagName('sldId') as $sldId) {
            $rId = $sldId->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            if (isset($relMap[$rId])) {
                $slideFiles[] = 'ppt/' . ltrim($relMap[$rId], '/');
            }
        }
        if (empty($slideFiles)) {
            for ($i = 1; $zip->locateName('ppt/slides/slide' . $i . '.xml') !== false; $i++) {
                $slideFiles[] = 'ppt/slides/slide' . $i . '.xml';
            }
        }
        if (empty($slideFiles)) {
            $zip->close();
            throw new RuntimeException(t('import.pptx_no_slides'));
        }

        $mediaIndex = 1;
        $slides = [];
        foreach ($slideFiles as $idx => $slidePath) {
            $slideNum = $idx + 1;
            try {
                $slides[] = self::parseSlide($zip, $slidePath, $width, $height, $presentationId, $mediaIndex, $warnings, $slideNum);
            } catch (Throwable $e) {
                $warnings[] = t('import.pptx_slide_failed', ['n' => $slideNum, 'error' => $e->getMessage()]);
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

    /** @return array<string,string> rId => Target */
    private static function readRels(ZipArchive $zip, string $relsPath): array
    {
        $raw = $zip->getFromName($relsPath);
        if ($raw === false) return [];
        $dom = self::loadXml($raw);
        $map = [];
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $map[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
        return $map;
    }

    private static function slideRelsPath(string $slidePath): string
    {
        $dir = dirname($slidePath);
        $base = basename($slidePath);
        return $dir . '/_rels/' . $base . '.rels';
    }

    private static function parseSlide(ZipArchive $zip, string $slidePath, int $slideWidth, int $slideHeight, string $presentationId, int &$mediaIndex, array &$warnings, int $slideNum): array
    {
        $raw = $zip->getFromName($slidePath);
        if ($raw === false) {
            throw new RuntimeException(t('import.pptx_missing_part', ['part' => $slidePath]));
        }
        $dom = self::loadXml($raw);
        $rels = self::readRels($zip, self::slideRelsPath($slidePath));
        $slideDir = dirname($slidePath);

        $background = self::parseBackground($dom, $zip, $rels, $slideDir, $presentationId, $mediaIndex, $warnings);

        $spTreeList = $dom->getElementsByTagName('spTree');
        $objects = [];
        if ($spTreeList->length > 0) {
            self::walkShapes($spTreeList->item(0), 0, 0, 1, 1, $zip, $rels, $slideDir, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
        }

        return [
            'id' => 'slide' . bin2hex(random_bytes(4)),
            'background' => $background,
            'objects' => $objects,
            'notes' => '',
            'transition' => 'slide',
            'autoAdvance' => 0,
        ];
    }

    private static function parseBackground(DOMDocument $dom, ZipArchive $zip, array $rels, string $slideDir, string $presentationId, int &$mediaIndex, array &$warnings): ?array
    {
        $bgList = $dom->getElementsByTagName('bg');
        if ($bgList->length === 0) return null;
        $bgPr = null;
        foreach ($bgList->item(0)->childNodes as $child) {
            if (($child->localName ?? '') === 'bgPr') { $bgPr = $child; break; }
        }
        if (!$bgPr) return null;

        foreach ($bgPr->childNodes as $node) {
            $local = $node->localName ?? '';
            if ($local === 'solidFill') {
                return ['type' => 'color', 'value' => '#' . self::resolveColor($node)];
            }
            if ($local === 'gradFill') {
                [$c1, $c2] = self::resolveGradientColors($node);
                return ['type' => 'gradient', 'value' => 'linear-gradient(90deg, #' . $c1 . ', #' . $c2 . ')'];
            }
            if ($local === 'blipFill') {
                $blip = $node->getElementsByTagName('blip')->item(0);
                if ($blip) {
                    $rId = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
                    $url = self::importMedia($zip, $rels, $rId, $slideDir, $presentationId, $mediaIndex, $warnings);
                    if ($url) return ['type' => 'image', 'value' => $url];
                }
            }
        }
        return null;
    }

    private static function resolveColor(DOMNode $fillNode): string
    {
        foreach ($fillNode->childNodes as $c) {
            if ($c->localName === 'srgbClr') {
                $val = $c->getAttribute('val');
                if (preg_match('/^[0-9a-fA-F]{6}$/', $val)) return strtoupper($val);
            }
            if ($c->localName === 'schemeClr') {
                $val = $c->getAttribute('val');
                return self::SCHEME_COLOR_FALLBACK[$val] ?? '888888';
            }
        }
        return '888888';
    }

    private static function resolveGradientColors(DOMNode $gradFill): array
    {
        $colors = [];
        $gsLst = $gradFill->getElementsByTagName('gs');
        foreach ($gsLst as $gs) {
            $colors[] = self::resolveColor($gs);
        }
        if (count($colors) < 2) $colors = ['3A6C8D', '87B42B'];
        return [$colors[0], $colors[count($colors) - 1]];
    }

    private static function importMedia(ZipArchive $zip, array $rels, string $rId, string $slideDir, string $presentationId, int &$mediaIndex, array &$warnings): ?string
    {
        if ($rId === '' || !isset($rels[$rId])) return null;
        $target = $rels[$rId];
        $mediaPath = self::resolvePath($slideDir, $target);
        $data = $zip->getFromName($mediaPath);
        if ($data === false) {
            $warnings[] = t('import.pptx_media_missing', ['path' => $mediaPath]);
            return null;
        }
        $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION)) ?: 'png';
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $warnings[] = t('import.pptx_unsupported_media', ['ext' => $ext]);
            return null;
        }
        $filename = 'pptx_img' . $mediaIndex . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $mediaIndex++;
        $assetsDir = Presentation::dir($presentationId) . '/assets';
        file_put_contents($assetsDir . '/' . $filename, $data);
        return 'asset.php?id=' . urlencode($presentationId) . '&file=' . urlencode($filename);
    }

    private static function resolvePath(string $baseDir, string $target): string
    {
        if (str_starts_with($target, '/')) return ltrim($target, '/');
        $parts = explode('/', $baseDir . '/' . $target);
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') continue;
            if ($part === '..') { array_pop($stack); continue; }
            $stack[] = $part;
        }
        return implode('/', $stack);
    }

    // ---------- Formen (rekursiv, damit Gruppen korrekt aufgelöst werden) ----------

    private static function walkShapes(DOMNode $spTree, float $groupOffX, float $groupOffY, float $scaleX, float $scaleY, ZipArchive $zip, array $rels, string $slideDir, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        foreach ($spTree->childNodes as $node) {
            $local = $node->localName ?? '';
            if ($local === 'sp') {
                self::parseSp($node, $groupOffX, $groupOffY, $scaleX, $scaleY, $objects, $warnings, $slideNum);
            } elseif ($local === 'pic') {
                self::parsePic($node, $groupOffX, $groupOffY, $scaleX, $scaleY, $zip, $rels, $slideDir, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
            } elseif ($local === 'cxnSp') {
                self::parseCxnSp($node, $groupOffX, $groupOffY, $scaleX, $scaleY, $objects);
            } elseif ($local === 'grpSp') {
                self::parseGroup($node, $groupOffX, $groupOffY, $scaleX, $scaleY, $zip, $rels, $slideDir, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
            } elseif (in_array($local, ['graphicFrame', 'contentPart'], true)) {
                $warnings[] = t('import.pptx_unsupported_object', ['n' => $slideNum, 'type' => $local === 'graphicFrame' ? t('import.pptx_type_table_chart') : $local]);
            }
        }
    }

    private static function xfrmOf(DOMNode $node): ?DOMNode
    {
        foreach ($node->childNodes as $c) {
            if (($c->localName ?? '') === 'spPr' || ($c->localName ?? '') === 'grpSpPr') {
                foreach ($c->childNodes as $c2) {
                    if (($c2->localName ?? '') === 'xfrm') return $c2;
                }
            }
        }
        return null;
    }

    /** Berechnet die absolute Position/Grösse/Rotation eines Objekts unter Berücksichtigung eines evtl. übergeordneten Gruppen-Koordinatenraums. */
    private static function resolveGeometry(?DOMNode $xfrm, float $groupOffX, float $groupOffY, float $scaleX, float $scaleY): array
    {
        $x = 0.0; $y = 0.0; $w = 100.0; $h = 100.0; $rot = 0.0;
        if ($xfrm) {
            $rot = ((int)$xfrm->getAttribute('rot')) / 60000;
            foreach ($xfrm->childNodes as $c) {
                if (($c->localName ?? '') === 'off') { $x = (float)$c->getAttribute('x'); $y = (float)$c->getAttribute('y'); }
                if (($c->localName ?? '') === 'ext') { $w = (float)$c->getAttribute('cx'); $h = (float)$c->getAttribute('cy'); }
            }
        }
        $px = $groupOffX + $x * $scaleX;
        $py = $groupOffY + $y * $scaleY;
        $pw = $w * $scaleX;
        $ph = $h * $scaleY;
        return [$px / self::EMU_PER_PX, $py / self::EMU_PER_PX, max(2, $pw / self::EMU_PER_PX), max(2, $ph / self::EMU_PER_PX), $rot];
    }

    private static function parseGroup(DOMNode $grpSp, float $groupOffX, float $groupOffY, float $scaleX, float $scaleY, ZipArchive $zip, array $rels, string $slideDir, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        $grpSpPr = null;
        foreach ($grpSp->childNodes as $c) { if (($c->localName ?? '') === 'grpSpPr') { $grpSpPr = $c; break; } }
        $xfrm = null;
        if ($grpSpPr) {
            foreach ($grpSpPr->childNodes as $c) { if (($c->localName ?? '') === 'xfrm') { $xfrm = $c; break; } }
        }
        $offX = 0.0; $offY = 0.0; $extCx = 1.0; $extCy = 1.0; $chOffX = 0.0; $chOffY = 0.0; $chExtCx = 1.0; $chExtCy = 1.0;
        if ($xfrm) {
            foreach ($xfrm->childNodes as $c) {
                $ln = $c->localName ?? '';
                if ($ln === 'off') { $offX = (float)$c->getAttribute('x'); $offY = (float)$c->getAttribute('y'); }
                if ($ln === 'ext') { $extCx = (float)$c->getAttribute('cx'); $extCy = (float)$c->getAttribute('cy'); }
                if ($ln === 'chOff') { $chOffX = (float)$c->getAttribute('x'); $chOffY = (float)$c->getAttribute('y'); }
                if ($ln === 'chExt') { $chExtCx = (float)$c->getAttribute('cx'); $chExtCy = (float)$c->getAttribute('cy'); }
            }
        }
        $childScaleX = $chExtCx != 0 ? ($extCx / $chExtCx) : 1.0;
        $childScaleY = $chExtCy != 0 ? ($extCy / $chExtCy) : 1.0;
        $newGroupOffX = $groupOffX + $offX * $scaleX - $chOffX * $childScaleX * $scaleX;
        $newGroupOffY = $groupOffY + $offY * $scaleY - $chOffY * $childScaleY * $scaleY;
        self::walkShapes($grpSp, $newGroupOffX, $newGroupOffY, $scaleX * $childScaleX, $scaleY * $childScaleY, $zip, $rels, $slideDir, $presentationId, $mediaIndex, $objects, $warnings, $slideNum);
    }

    private static function parseCxnSp(DOMNode $cxnSp, float $groupOffX, float $groupOffY, float $scaleX, float $scaleY, array &$objects): void
    {
        [$x, $y, $w, $h, $rot] = self::resolveGeometry(self::xfrmOf($cxnSp), $groupOffX, $groupOffY, $scaleX, $scaleY);
        $color = 'ffffff'; $strokeWidth = 3;
        foreach ($cxnSp->getElementsByTagName('ln') as $ln) {
            $wAttr = $ln->getAttribute('w');
            if ($wAttr !== '') $strokeWidth = max(1, round(((int)$wAttr) / 12700));
            foreach ($ln->childNodes as $c) {
                if (($c->localName ?? '') === 'solidFill') { $color = self::resolveColor($c); break; }
            }
            break;
        }
        $objects[] = [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'shape', 'shapeType' => 'line',
            'x' => round($x), 'y' => round($y), 'w' => round($w), 'h' => round($h), 'rotation' => round($rot, 1), 'opacity' => 1,
            'stroke' => '#' . $color, 'strokeWidth' => $strokeWidth,
        ];
    }

    private static function parsePic(DOMNode $pic, float $groupOffX, float $groupOffY, float $scaleX, float $scaleY, ZipArchive $zip, array $rels, string $slideDir, string $presentationId, int &$mediaIndex, array &$objects, array &$warnings, int $slideNum): void
    {
        $blip = $pic->getElementsByTagName('blip')->item(0);
        if (!$blip) return;
        $rId = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
        $url = self::importMedia($zip, $rels, $rId, $slideDir, $presentationId, $mediaIndex, $warnings);
        if (!$url) {
            $warnings[] = t('import.pptx_image_failed', ['n' => $slideNum]);
            return;
        }
        [$x, $y, $w, $h, $rot] = self::resolveGeometry(self::xfrmOf($pic), $groupOffX, $groupOffY, $scaleX, $scaleY);
        $objects[] = [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'image',
            'x' => round($x), 'y' => round($y), 'w' => round($w), 'h' => round($h), 'rotation' => round($rot, 1), 'opacity' => 1,
            'src' => $url,
        ];
    }

    private static function PRESET_MAP(): array
    {
        return [
            'rect' => ['type' => 'rect'], 'roundRect' => ['type' => 'rect'], 'ellipse' => ['type' => 'ellipse'],
            'triangle' => ['shapeType' => 'triangle'],
            'star4' => ['shapeType' => 'star', 'starPoints' => 4], 'star5' => ['shapeType' => 'star', 'starPoints' => 5],
            'star6' => ['shapeType' => 'star', 'starPoints' => 6], 'star7' => ['shapeType' => 'star', 'starPoints' => 7],
            'star8' => ['shapeType' => 'star', 'starPoints' => 8], 'star10' => ['shapeType' => 'star', 'starPoints' => 10],
            'star12' => ['shapeType' => 'star', 'starPoints' => 12], 'star16' => ['shapeType' => 'star', 'starPoints' => 16],
            'star24' => ['shapeType' => 'star', 'starPoints' => 24], 'star32' => ['shapeType' => 'star', 'starPoints' => 32],
            'rightArrow' => ['shapeType' => 'arrow', 'arrowStyle' => 'right'], 'leftArrow' => ['shapeType' => 'arrow', 'arrowStyle' => 'left'],
            'upArrow' => ['shapeType' => 'arrow', 'arrowStyle' => 'up'], 'downArrow' => ['shapeType' => 'arrow', 'arrowStyle' => 'down'],
            'leftRightArrow' => ['shapeType' => 'arrow', 'arrowStyle' => 'double-h'], 'upDownArrow' => ['shapeType' => 'arrow', 'arrowStyle' => 'double-v'],
            'leftBracket' => ['shapeType' => 'bracket', 'bracketStyle' => 'round-left'], 'rightBracket' => ['shapeType' => 'bracket', 'bracketStyle' => 'round-right'],
            'leftBrace' => ['shapeType' => 'bracket', 'bracketStyle' => 'curly-left'], 'rightBrace' => ['shapeType' => 'bracket', 'bracketStyle' => 'curly-right'],
            'wedgeRectCallout' => ['shapeType' => 'speech-bubble', 'bubbleStyle' => 'rect-left'],
            'wedgeEllipseCallout' => ['shapeType' => 'speech-bubble', 'bubbleStyle' => 'oval'],
            'cloudCallout' => ['shapeType' => 'speech-bubble', 'bubbleStyle' => 'cloud'],
            'line' => ['shapeType' => 'line'],
        ];
    }

    private static function parseSp(DOMNode $sp, float $groupOffX, float $groupOffY, float $scaleX, float $scaleY, array &$objects, array &$warnings, int $slideNum): void
    {
        [$x, $y, $w, $h, $rot] = self::resolveGeometry(self::xfrmOf($sp), $groupOffX, $groupOffY, $scaleX, $scaleY);

        // Textinhalt sammeln
        $txBody = null;
        foreach ($sp->childNodes as $c) { if (($c->localName ?? '') === 'txBody') { $txBody = $c; break; } }
        $hasVisibleText = false;
        $text = '';
        $firstRunProps = null;
        $align = 'left';
        if ($txBody) {
            $lines = [];
            foreach ($txBody->getElementsByTagName('p') as $p) {
                $pPr = null;
                foreach ($p->childNodes as $c) { if (($c->localName ?? '') === 'pPr') { $pPr = $c; break; } }
                if ($pPr) {
                    $a = $pPr->getAttribute('algn');
                    if ($a === 'ctr') $align = 'center'; elseif ($a === 'r') $align = 'right';
                }
                $runTexts = [];
                foreach ($p->getElementsByTagName('r') as $r) {
                    $tNode = $r->getElementsByTagName('t')->item(0);
                    if ($tNode) $runTexts[] = $tNode->textContent;
                    if (!$firstRunProps) {
                        foreach ($r->childNodes as $c) { if (($c->localName ?? '') === 'rPr') { $firstRunProps = $c; break; } }
                    }
                }
                $lines[] = implode('', $runTexts);
            }
            $text = implode("\n", $lines);
            $hasVisibleText = trim($text) !== '';
        }

        // Geometrie-Preset
        $geomList = [];
        foreach ($sp->childNodes as $c) { if (($c->localName ?? '') === 'spPr') { $geomList = $c->getElementsByTagName('prstGeom'); break; } }
        $prst = 'rect';
        if ($geomList instanceof DOMNodeList && $geomList->length > 0) {
            $prst = $geomList->item(0)->getAttribute('prst');
        }
        $map = self::PRESET_MAP();
        $isKnownShape = isset($map[$prst]);
        $shapeInfo = $map[$prst] ?? ['type' => 'rect'];

        // Ein reiner Textrahmen (Fill=none, kein bekanntes Formen-Preset ausser "rect") wird als Text-Objekt importiert
        $spPr = null;
        foreach ($sp->childNodes as $c) { if (($c->localName ?? '') === 'spPr') { $spPr = $c; break; } }
        $hasNoFill = false;
        $fillColor = null;
        $fillType = 'solid';
        $gradC1 = null; $gradC2 = null;
        if ($spPr) {
            foreach ($spPr->childNodes as $c) {
                $ln = $c->localName ?? '';
                if ($ln === 'noFill') $hasNoFill = true;
                if ($ln === 'solidFill') $fillColor = self::resolveColor($c);
                if ($ln === 'gradFill') { [$gradC1, $gradC2] = self::resolveGradientColors($c); $fillType = 'gradient'; }
            }
        }

        if ($hasVisibleText && $prst === 'rect' && ($hasNoFill || $fillColor === null)) {
            $sz = 32; $bold = false; $italic = false; $underline = false; $strike = false; $color = 'ffffff'; $fontFamily = 'Open Sans';
            if ($firstRunProps) {
                $szAttr = $firstRunProps->getAttribute('sz');
                if ($szAttr !== '') $sz = max(6, round(((int)$szAttr) / 100 / 0.75));
                $bold = $firstRunProps->getAttribute('b') === '1';
                $italic = $firstRunProps->getAttribute('i') === '1';
                $underline = $firstRunProps->getAttribute('u') !== '' && $firstRunProps->getAttribute('u') !== 'none';
                $strike = $firstRunProps->getAttribute('strike') !== '' && $firstRunProps->getAttribute('strike') !== 'noStrike';
                foreach ($firstRunProps->childNodes as $c) {
                    if (($c->localName ?? '') === 'solidFill') { $color = self::resolveColor($c); }
                    if (($c->localName ?? '') === 'latin') { $f = $c->getAttribute('typeface'); if ($f !== '') $fontFamily = $f; }
                }
            }
            $objects[] = [
                'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'text',
                'x' => round($x), 'y' => round($y), 'w' => round($w), 'h' => round($h), 'rotation' => round($rot, 1), 'opacity' => 1,
                'text' => $text, 'fontFamily' => $fontFamily, 'fontSize' => (int)$sz,
                'fontWeight' => $bold ? 'bold' : 'normal', 'italic' => $italic, 'underline' => $underline, 'strikethrough' => $strike,
                'color' => '#' . $color, 'align' => $align,
            ];
            return;
        }

        if (!$isKnownShape && $prst !== 'rect') {
            $warnings[] = t('import.pptx_unsupported_shape', ['n' => $slideNum, 'preset' => $prst]);
        }

        $obj = [
            'id' => 'o' . bin2hex(random_bytes(4)),
            'x' => round($x), 'y' => round($y), 'w' => round($w), 'h' => round($h), 'rotation' => round($rot, 1), 'opacity' => 1,
            'stroke' => 'transparent', 'strokeWidth' => 0,
        ];
        if (($shapeInfo['type'] ?? '') === 'rect') { $obj['type'] = 'rect'; }
        elseif (($shapeInfo['type'] ?? '') === 'ellipse') { $obj['type'] = 'ellipse'; }
        else {
            $obj['type'] = 'shape';
            $obj['shapeType'] = $shapeInfo['shapeType'] ?? 'triangle';
            if (isset($shapeInfo['starPoints'])) $obj['starPoints'] = $shapeInfo['starPoints'];
            if (isset($shapeInfo['arrowStyle'])) $obj['arrowStyle'] = $shapeInfo['arrowStyle'];
            if (isset($shapeInfo['bracketStyle'])) $obj['bracketStyle'] = $shapeInfo['bracketStyle'];
            if (isset($shapeInfo['bubbleStyle'])) $obj['bubbleStyle'] = $shapeInfo['bubbleStyle'];
        }
        if ($fillType === 'gradient' && $gradC1) {
            $obj['fillType'] = 'gradient'; $obj['gradColor1'] = '#' . $gradC1; $obj['gradColor2'] = '#' . $gradC2; $obj['gradAngle'] = 90;
        } elseif ($hasNoFill && $fillColor === null) {
            $obj['fillType'] = 'none';
        } else {
            $obj['fillType'] = 'solid'; $obj['fill'] = '#' . ($fillColor ?? '3A6C8D');
        }
        $objects[] = $obj;
    }
}
