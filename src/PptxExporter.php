<?php
/**
 * Exportiert eine Präsentation als .pptx (PowerPoint / öffnet auch in LibreOffice Impress).
 * PPTX-Dateien sind ZIP-Archive voller XML (OOXML) - wir bauen die nötige Struktur von Hand
 * zusammen, ohne externe Bibliothek (wie der Rest dieser App).
 *
 * Bewusste Einschränkungen (siehe Ansage im Editor/Export-Dialog):
 * - Keine Animationen/Übergänge (PPTX-Animations-XML ist enorm komplex, würde den Rahmen
 *   sprengen) - alle Objekte erscheinen sofort sichtbar, wie im PDF-Export.
 * - Video/Audio-Objekte werden als beschrifteter Platzhalter-Rahmen exportiert, nicht als
 *   funktionierendes eingebettetes Medium.
 * - Teilformatierung innerhalb eines Textfelds (z.B. nur ein Wort farbig/fett per Markdown)
 *   geht verloren - exportiert wird die Formatierung des gesamten Textobjekts.
 * - Hintergrund-/Objekt-Videos werden wie unbekannte Hintergründe als dunkle Fläche exportiert.
 */
class PptxExporter
{
    private const EMU_PER_PX = 9525; // 914400 EMU/Zoll ÷ 96 px/Zoll

    private static function px(float $px): int
    {
        return (int)round($px * self::EMU_PER_PX);
    }

    private static function hex(string $color): string
    {
        $c = ltrim(trim($color), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $c)) {
            return '3A6C8D';
        }
        return strtoupper($c);
    }

    public static function build(array $meta, array $slidesData, string $presentationId): string
    {
        $width = (int)($meta['width'] ?? 1920);
        $height = (int)($meta['height'] ?? 1080);
        $slides = $slidesData['slides'] ?? [];

        $tmpDir = sys_get_temp_dir() . '/sfpptx_' . bin2hex(random_bytes(6));
        mkdir($tmpDir, 0770, true);
        mkdir($tmpDir . '/_rels', 0770, true);
        mkdir($tmpDir . '/ppt', 0770, true);
        mkdir($tmpDir . '/ppt/_rels', 0770, true);
        mkdir($tmpDir . '/ppt/slides', 0770, true);
        mkdir($tmpDir . '/ppt/slides/_rels', 0770, true);
        mkdir($tmpDir . '/ppt/slideLayouts', 0770, true);
        mkdir($tmpDir . '/ppt/slideLayouts/_rels', 0770, true);
        mkdir($tmpDir . '/ppt/slideMasters', 0770, true);
        mkdir($tmpDir . '/ppt/slideMasters/_rels', 0770, true);
        mkdir($tmpDir . '/ppt/theme', 0770, true);
        mkdir($tmpDir . '/ppt/media', 0770, true);
        mkdir($tmpDir . '/docProps', 0770, true);

        $embeddedFontRels = [];
        $customFonts = FontLibrary::customFontsForSlides($slidesData);
        if ($customFonts) {
            mkdir($tmpDir . '/ppt/fonts', 0770, true);
            $fontIndex = 1;
            foreach ($customFonts as $font) {
                if (!FontLibrary::canEmbedInPptx($font['format'] ?? '')) {
                    continue;
                }
                $diskPath = FontLibrary::filePath($font);
                if (!$diskPath) {
                    continue;
                }
                $guid = FontLibrary::generateGuid();
                $obfuscated = FontLibrary::obfuscateOdttf((string)file_get_contents($diskPath), $guid);
                $zipName = 'font' . $fontIndex . '.odttf';
                file_put_contents($tmpDir . '/ppt/fonts/' . $zipName, $obfuscated);
                $embeddedFontRels[] = [
                    'family' => $font['family'],
                    'target' => 'fonts/' . $zipName,
                ];
                $fontIndex++;
            }
        }

        $mediaIndex = 1;
        $mediaFiles = []; // Dateiname im ZIP => Pfad auf der Platte

        $slideCount = max(1, count($slides));
        $slideXmlFiles = [];
        for ($i = 0; $i < $slideCount; $i++) {
            $slide = $slides[$i] ?? ['objects' => [], 'background' => null];
            [$xml, $rels] = self::renderSlide($slide, $width, $height, $presentationId, $mediaIndex, $mediaFiles);
            $n = $i + 1;
            file_put_contents($tmpDir . "/ppt/slides/slide{$n}.xml", $xml);
            file_put_contents($tmpDir . "/ppt/slides/_rels/slide{$n}.xml.rels", $rels);
        }

        foreach ($mediaFiles as $zipName => $diskPath) {
            copy($diskPath, $tmpDir . '/ppt/media/' . $zipName);
        }

        file_put_contents($tmpDir . '/[Content_Types].xml', self::contentTypesXml($slideCount, $mediaFiles, $embeddedFontRels !== []));
        file_put_contents($tmpDir . '/_rels/.rels', self::rootRelsXml());
        file_put_contents($tmpDir . '/docProps/app.xml', self::appXml($slideCount));
        file_put_contents($tmpDir . '/docProps/core.xml', self::coreXml($meta['title'] ?? 'Präsentation'));
        file_put_contents($tmpDir . '/ppt/presentation.xml', self::presentationXml($width, $height, $slideCount, $embeddedFontRels));
        file_put_contents($tmpDir . '/ppt/_rels/presentation.xml.rels', self::presentationRelsXml($slideCount, $embeddedFontRels));
        file_put_contents($tmpDir . '/ppt/theme/theme1.xml', self::themeXml());
        file_put_contents($tmpDir . '/ppt/slideMasters/slideMaster1.xml', self::slideMasterXml());
        file_put_contents($tmpDir . '/ppt/slideMasters/_rels/slideMaster1.xml.rels', self::slideMasterRelsXml());
        file_put_contents($tmpDir . '/ppt/slideLayouts/slideLayout1.xml', self::slideLayoutXml());
        file_put_contents($tmpDir . '/ppt/slideLayouts/_rels/slideLayout1.xml.rels', self::slideLayoutRelsXml());

        $zipPath = $tmpDir . '.pptx';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        self::addDirToZip($zip, $tmpDir, '');
        $zip->close();

        self::cleanupDir($tmpDir);
        return $zipPath;
    }

    private static function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            $zipName = $prefix === '' ? $entry : $prefix . '/' . $entry;
            if (is_dir($path)) {
                self::addDirToZip($zip, $path, $zipName);
            } else {
                $zip->addFile($path, $zipName);
            }
        }
    }

    private static function cleanupDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? self::cleanupDir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    // ---------- Objekt -> Form-Zuordnung ----------

    private static function presetGeometry(array $obj): string
    {
        if (($obj['type'] ?? '') === 'rect') return 'rect';
        if (($obj['type'] ?? '') === 'ellipse') return 'ellipse';
        if (($obj['type'] ?? '') === 'text') return 'rect';
        if (($obj['type'] ?? '') === 'shape') {
            $st = $obj['shapeType'] ?? 'triangle';
            if ($st === 'triangle') return 'triangle';
            if ($st === 'line') return 'line';
            if ($st === 'star') {
                $n = (int)($obj['starPoints'] ?? 5);
                $known = [4, 5, 6, 7, 8, 10, 12, 16, 24, 32];
                $closest = 5;
                $bestDiff = PHP_INT_MAX;
                foreach ($known as $k) {
                    $diff = abs($k - $n);
                    if ($diff < $bestDiff) { $bestDiff = $diff; $closest = $k; }
                }
                return 'star' . $closest;
            }
            if ($st === 'arrow') {
                $map = ['right' => 'rightArrow', 'left' => 'leftArrow', 'up' => 'upArrow', 'down' => 'downArrow', 'double-h' => 'leftRightArrow', 'double-v' => 'upDownArrow'];
                return $map[$obj['arrowStyle'] ?? 'right'] ?? 'rightArrow';
            }
            if ($st === 'bracket') {
                $style = $obj['bracketStyle'] ?? 'round-left';
                $isRight = strpos($style, 'right') !== false;
                if (strpos($style, 'curly') === 0) return $isRight ? 'rightBrace' : 'leftBrace';
                return $isRight ? 'rightBracket' : 'leftBracket'; // rund wird wie eckig angenähert
            }
            if ($st === 'speech-bubble') {
                $style = $obj['bubbleStyle'] ?? 'rect-left';
                if ($style === 'oval') return 'wedgeEllipseCallout';
                if ($style === 'cloud') return 'cloudCallout';
                return 'wedgeRectCallout';
            }
        }
        return 'rect';
    }

    // ---------- Text ----------

    private static function stripMarkdown(string $text): string
    {
        $text = preg_replace('/\[color=#[0-9a-fA-F]{6}\]|\[\/color\]/', '', $text);
        $text = preg_replace('/\[mark=#[0-9a-fA-F]{6}\]|\[\/mark\]/', '', $text);
        $text = preg_replace('/\[upper\]|\[\/upper\]|\[sc\]|\[\/sc\]/', '', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = preg_replace('/__(.+?)__/s', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text);
        $text = preg_replace('/\+\+(.+?)\+\+/s', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/s', '$1', $text);
        $text = preg_replace('/`(.+?)`/s', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        return $text ?? '';
    }

    private static function textBodyXml(array $obj): string
    {
        $raw = self::stripMarkdown($obj['text'] ?? '');
        if (!empty($obj['uppercase'])) $raw = mb_strtoupper($raw);
        $lines = explode("\n", $raw);

        $align = ['left' => 'l', 'center' => 'ctr', 'right' => 'r'][$obj['align'] ?? 'left'] ?? 'l';
        $sz = max(100, (int)round(($obj['fontSize'] ?? 32) * 100 * 0.75)); // px -> pt (96dpi) -> Hundertstel-Pt
        $font = h($obj['fontFamily'] ?? 'Open Sans');
        $color = self::hex($obj['color'] ?? '#ffffff');
        $bold = ($obj['fontWeight'] ?? 'normal') === 'bold' ? '1' : '0';
        $italic = !empty($obj['italic']) ? '1' : '0';
        $underline = !empty($obj['underline']) ? ' u="sng"' : '';
        $strike = !empty($obj['strikethrough']) ? ' strike="sngStrike"' : '';

        $paras = '';
        foreach ($lines as $line) {
            $paras .= '<a:p><a:pPr algn="' . $align . '"/><a:r><a:rPr lang="de-DE" sz="' . $sz . '" b="' . $bold . '" i="' . $italic . '"' . $underline . $strike . '><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill><a:latin typeface="' . $font . '"/></a:rPr><a:t>' . h($line) . '</a:t></a:r></a:p>';
        }
        if ($paras === '') $paras = '<a:p><a:endParaRPr lang="de-DE"/></a:p>';

        return '<p:txBody><a:bodyPr wrap="square" lIns="0" tIns="0" rIns="0" bIns="0" anchor="t"><a:noAutofit/></a:bodyPr><a:lstStyle/>' . $paras . '</p:txBody>';
    }

    // ---------- Ein Objekt als <p:sp>/<p:pic>/<p:cxnSp> ----------

    private static function objectXml(array $obj, int $shapeId, string $presentationId, int &$mediaIndex, array &$mediaFiles, array &$rels, int &$relId): string
    {
        $type = $obj['type'] ?? 'rect';
        $x = self::px($obj['x'] ?? 0);
        $y = self::px($obj['y'] ?? 0);
        $w = self::px(max(1, $obj['w'] ?? 100));
        $h = self::px(max(1, $obj['h'] ?? 100));
        $rot = (int)round((($obj['rotation'] ?? 0)) * 60000);
        $opacity = isset($obj['opacity']) ? (int)round(max(0, min(1, $obj['opacity'])) * 100000) : 100000;
        $name = h(($type === 'text') ? 'Text' : ucfirst($type));

        $xfrm = '<a:xfrm rot="' . $rot . '"><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>';

        // ---- Bild ----
        if ($type === 'image' && !empty($obj['src'])) {
            $diskPath = self::resolveAssetPath($obj['src'], $presentationId);
            if ($diskPath) {
                $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION)) ?: 'png';
                $zipName = 'image' . $mediaIndex . '.' . $ext;
                $mediaFiles[$zipName] = $diskPath;
                $rId = 'rId' . $relId;
                $rels[] = ['id' => $rId, 'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', 'target' => '../media/' . $zipName];
                $relId++;
                $mediaIndex++;
                return '<p:pic><p:nvPicPr><p:cNvPr id="' . $shapeId . '" name="' . $name . '"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>' .
                    '<p:blipFill><a:blip r:embed="' . $rId . '"><a:alphaModFix amt="' . $opacity . '"/></a:blip><a:stretch><a:fillRect/></a:stretch></p:blipFill>' .
                    '<p:spPr>' . $xfrm . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>';
            }
        }

        // ---- Video/Audio: Platzhalter-Rahmen mit Beschriftung ----
        if ($type === 'video' || $type === 'audio') {
            $label = $type === 'video' ? 'Video' : 'Audio';
            return '<p:sp><p:nvSpPr><p:cNvPr id="' . $shapeId . '" name="' . $name . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
                '<p:spPr>' . $xfrm . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="000000"/></a:solidFill><a:ln><a:solidFill><a:srgbClr val="666666"/></a:solidFill></a:ln></p:spPr>' .
                '<p:txBody><a:bodyPr anchor="ctr"/><a:lstStyle/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="de-DE" sz="1800"><a:solidFill><a:srgbClr val="999999"/></a:solidFill></a:rPr><a:t>' . h($label) . '</a:t></a:r></a:p></p:txBody></p:sp>';
        }

        // ---- Linie ----
        if ($type === 'shape' && ($obj['shapeType'] ?? '') === 'line') {
            $color = self::hex(($obj['stroke'] ?? '') !== 'transparent' && !empty($obj['stroke']) ? $obj['stroke'] : '#ffffff');
            $width = max(1, (float)($obj['strokeWidth'] ?? 3));
            return '<p:cxnSp><p:nvCxnSpPr><p:cNvPr id="' . $shapeId . '" name="' . $name . '"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr>' .
                '<p:spPr>' . $xfrm . '<a:prstGeom prst="line"><a:avLst/></a:prstGeom><a:ln w="' . (int)round($width * 12700) . '"><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill></a:ln></p:spPr></p:cxnSp>';
        }

        // ---- Text ----
        if ($type === 'text') {
            return '<p:sp><p:nvSpPr><p:cNvPr id="' . $shapeId . '" name="' . $name . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>' .
                '<p:spPr>' . $xfrm . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>' .
                self::textBodyXml($obj) . '</p:sp>';
        }

        // ---- Rechteck / Ellipse / Formen ----
        $prst = self::presetGeometry($obj);
        $isOpenShape = $type === 'shape' && in_array($obj['shapeType'] ?? '', ['line', 'bracket'], true);
        $fillXml = '<a:noFill/>';
        if (!$isOpenShape) {
            $fillType = $obj['fillType'] ?? 'solid';
            if ($fillType === 'gradient') {
                $c1 = self::hex($obj['gradColor1'] ?? '#3a6c8d');
                $c2 = self::hex($obj['gradColor2'] ?? '#87b42b');
                $fillXml = '<a:gradFill><a:gsLst><a:gs pos="0"><a:srgbClr val="' . $c1 . '"><a:alphaModFix amt="' . $opacity . '"/></a:srgbClr></a:gs><a:gs pos="100000"><a:srgbClr val="' . $c2 . '"><a:alphaModFix amt="' . $opacity . '"/></a:srgbClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="1"/></a:gradFill>';
            } elseif ($fillType !== 'none') {
                $fillColor = self::hex($obj['fill'] ?? '#3a6c8d');
                $fillXml = '<a:solidFill><a:srgbClr val="' . $fillColor . '"><a:alphaModFix amt="' . $opacity . '"/></a:srgbClr></a:solidFill>';
            }
        }
        $lnXml = '<a:ln><a:noFill/></a:ln>';
        $strokeWidth = (float)($obj['strokeWidth'] ?? 0);
        if ($strokeWidth > 0 && (($obj['stroke'] ?? 'transparent') !== 'transparent' || $isOpenShape)) {
            $strokeColor = self::hex(($obj['stroke'] ?? '') !== 'transparent' && !empty($obj['stroke']) ? $obj['stroke'] : '#ffffff');
            $lnXml = '<a:ln w="' . (int)round($strokeWidth * 12700) . '"><a:solidFill><a:srgbClr val="' . $strokeColor . '"/></a:solidFill></a:ln>';
        }

        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $shapeId . '" name="' . $name . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>' .
            '<p:spPr>' . $xfrm . '<a:prstGeom prst="' . $prst . '"><a:avLst/></a:prstGeom>' . $fillXml . $lnXml . '</p:spPr></p:sp>';
    }

    private static function resolveAssetPath(string $url, string $presentationId): ?string
    {
        if (!preg_match('/[?&]file=([a-zA-Z0-9._-]+)/', $url, $m)) {
            return null;
        }
        $path = Presentation::dir($presentationId) . '/assets/' . $m[1];
        return is_file($path) ? $path : null;
    }

    // ---------- Folie ----------

    private static function renderSlide(array $slide, int $width, int $height, string $presentationId, int &$mediaIndex, array &$mediaFiles): array
    {
        $bg = $slide['background'] ?? null;
        $bgXml = '<p:bg><p:bgPr><a:solidFill><a:srgbClr val="111111"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>';
        $rels = [];
        $relId = 2;

        if ($bg) {
            if (($bg['type'] ?? '') === 'color') {
                $bgXml = '<p:bg><p:bgPr><a:solidFill><a:srgbClr val="' . self::hex($bg['value'] ?? '#111111') . '"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>';
            } elseif (($bg['type'] ?? '') === 'gradient') {
                preg_match_all('/#[0-9a-fA-F]{6}/', $bg['value'] ?? '', $m);
                $c1 = self::hex($m[0][0] ?? '#3a6c8d');
                $c2 = self::hex($m[0][1] ?? '#87b42b');
                $bgXml = '<p:bg><p:bgPr><a:gradFill><a:gsLst><a:gs pos="0"><a:srgbClr val="' . $c1 . '"/></a:gs><a:gs pos="100000"><a:srgbClr val="' . $c2 . '"/></a:gs></a:gsLst><a:lin ang="5400000" scaled="1"/></a:gradFill><a:effectLst/></p:bgPr></p:bg>';
            } elseif (($bg['type'] ?? '') === 'image') {
                $diskPath = self::resolveAssetPath($bg['value'] ?? '', $presentationId);
                if ($diskPath) {
                    $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION)) ?: 'png';
                    $zipName = 'image' . $mediaIndex . '.' . $ext;
                    $mediaFiles[$zipName] = $diskPath;
                    $rId = 'rId' . $relId;
                    $rels[] = ['id' => $rId, 'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', 'target' => '../media/' . $zipName];
                    $relId++;
                    $mediaIndex++;
                    $bgXml = '<p:bg><p:bgPr><a:blipFill rotWithShape="1"><a:blip r:embed="' . $rId . '"/><a:stretch><a:fillRect/></a:stretch></a:blipFill><a:effectLst/></p:bgPr></p:bg>';
                }
            }
        }

        $shapesXml = '';
        $shapeId = 2;
        foreach ($slide['objects'] ?? [] as $obj) {
            $shapesXml .= self::objectXml($obj, $shapeId, $presentationId, $mediaIndex, $mediaFiles, $rels, $relId);
            $shapeId++;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
            '<p:cSld>' . $bgXml . '<p:spTree>' .
            '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>' .
            '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>' .
            $shapesXml .
            '</p:spTree></p:cSld><p:clrMapOvr><a:overrideClrMapping bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2"/></p:clrMapOvr></p:sld>';

        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>';
        foreach ($rels as $r) {
            $relsXml .= '<Relationship Id="' . $r['id'] . '" Type="' . $r['type'] . '" Target="' . $r['target'] . '"/>';
        }
        $relsXml .= '</Relationships>';

        return [$xml, $relsXml];
    }

    // ---------- Feststehende Gerüst-XML-Dateien ----------

    private static function contentTypesXml(int $slideCount, array $mediaFiles, bool $hasEmbeddedFonts = false): string
    {
        $exts = [];
        foreach (array_keys($mediaFiles) as $name) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $exts[$ext] = true;
        }
        $extMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $defaults = '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        if ($hasEmbeddedFonts) {
            $defaults .= '<Default Extension="odttf" ContentType="application/vnd.openxmlformats-officedocument.obfuscatedFont"/>';
        }
        foreach (array_keys($exts) as $ext) {
            if (isset($extMap[$ext])) {
                $defaults .= '<Default Extension="' . $ext . '" ContentType="' . $extMap[$ext] . '"/>';
            }
        }
        $overrides = '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>' .
            '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>' .
            '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>' .
            '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>' .
            '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' .
            '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        for ($i = 1; $i <= $slideCount; $i++) {
            $overrides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . $defaults . $overrides . '</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>' .
            '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>' .
            '</Relationships>';
    }

    private static function appXml(int $slideCount): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>SlideForge</Application><Slides>' . $slideCount . '</Slides></Properties>';
    }

    private static function coreXml(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' .
            '<dc:title>' . h($title) . '</dc:title><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
    }

    private static function presentationXml(int $width, int $height, int $slideCount, array $embeddedFontRels = []): string
    {
        $sldIds = '';
        for ($i = 1; $i <= $slideCount; $i++) {
            $sldIds .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . (1 + $i) . '"/>';
        }
        $fontLst = '';
        if ($embeddedFontRels) {
            $themeRid = 2 + $slideCount;
            $fontLst = '<p:embeddedFontLst>';
            foreach ($embeddedFontRels as $i => $font) {
                $rId = $themeRid + 1 + $i;
                $fontLst .= '<p:embeddedFont typeface="' . h($font['family']) . '"><p:regular r:id="rId' . $rId . '"/></p:embeddedFont>';
            }
            $fontLst .= '</p:embeddedFontLst>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
            '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>' .
            '<p:sldIdLst>' . $sldIds . '</p:sldIdLst>' .
            $fontLst .
            '<p:sldSz cx="' . self::px($width) . '" cy="' . self::px($height) . '"/>' .
            '<p:notesSz cx="6858000" cy="9144000"/></p:presentation>';
    }

    private static function presentationRelsXml(int $slideCount, array $embeddedFontRels = []): string
    {
        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
        for ($i = 1; $i <= $slideCount; $i++) {
            $rels .= '<Relationship Id="rId' . (1 + $i) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
        }
        $themeRid = 2 + $slideCount;
        $rels .= '<Relationship Id="rId' . $themeRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>';
        foreach ($embeddedFontRels as $i => $font) {
            $rId = $themeRid + 1 + $i;
            $rels .= '<Relationship Id="rId' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font" Target="' . h($font['target']) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private static function slideMasterXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' .
            '<p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="111111"/></a:solidFill><a:effectLst/></p:bgPr></p:bg><p:spTree>' .
            '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>' .
            '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>' .
            '</p:spTree></p:cSld>' .
            '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>' .
            '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>' .
            '</p:sldMaster>';
    }

    private static function slideMasterRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>' .
            '</Relationships>';
    }

    private static function slideLayoutXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">' .
            '<p:cSld name="Leer"><p:spTree>' .
            '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>' .
            '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>' .
            '</p:spTree></p:cSld><p:clrMapOvr><a:overrideClrMapping bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2"/></p:clrMapOvr></p:sldLayout>';
    }

    private static function slideLayoutRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>' .
            '</Relationships>';
    }

    private static function themeXml(): string
    {
        // Minimales, aber gültiges Standard-Theme (Office-Grundfarben/-Schriften).
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="SlideForge">' .
            '<a:themeElements><a:clrScheme name="SlideForge">' .
            '<a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>' .
            '<a:dk2><a:srgbClr val="1F1F1F"/></a:dk2><a:lt2><a:srgbClr val="EEEEEE"/></a:lt2>' .
            '<a:accent1><a:srgbClr val="3A6C8D"/></a:accent1><a:accent2><a:srgbClr val="87B42B"/></a:accent2>' .
            '<a:accent3><a:srgbClr val="E0A030"/></a:accent3><a:accent4><a:srgbClr val="C05050"/></a:accent4>' .
            '<a:accent5><a:srgbClr val="7050A0"/></a:accent5><a:accent6><a:srgbClr val="509090"/></a:accent6>' .
            '<a:hlink><a:srgbClr val="3A6C8D"/></a:hlink><a:folHlink><a:srgbClr val="87B42B"/></a:folHlink>' .
            '</a:clrScheme>' .
            '<a:fontScheme name="SlideForge"><a:majorFont><a:latin typeface="Open Sans"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>' .
            '<a:minorFont><a:latin typeface="Open Sans"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme>' .
            '<a:fmtScheme name="SlideForge"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst>' .
            '<a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="12700"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst>' .
            '<a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>' .
            '<a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme>' .
            '</a:themeElements></a:theme>';
    }
}
