<?php
/**
 * Exportiert eine Präsentation als .odp (natives LibreOffice-/OpenOffice-Impress-Format,
 * OpenDocument). Eigenständig zusammengebaut wie PptxExporter, keine externe Bibliothek.
 *
 * Etwas GRÖSSERE Einschränkungen als beim PPTX-Export: ODF hat kein so reichhaltiges Set an
 * vorgefertigten Formen wie OOXML. Rechteck/Ellipse/Text/Bild/Linie werden exakt abgebildet;
 * unsere Formen (Stern/Pfeil/Klammer/Sprechblase/Dreieck) werden hier - anders als beim
 * PPTX-Export - als einfaches Rechteck mit gleicher Füllfarbe angenähert.
 * Keine Animationen/Übergänge, Video/Audio als beschrifteter Platzhalter (wie bei PPTX).
 */
class OdpExporter
{
    private const CM_PER_PX = 1 / 37.795275591; // 96 DPI: 1cm = 37.795... px

    private static function cm(float $px): string
    {
        return round($px * self::CM_PER_PX, 4) . 'cm';
    }

    private static function hex(string $color): string
    {
        $c = ltrim(trim($color), '#');
        return preg_match('/^[0-9a-fA-F]{6}$/', $c) ? '#' . strtolower($c) : '#3a6c8d';
    }

    public static function build(array $meta, array $slidesData, string $presentationId): string
    {
        $width = (int)($meta['width'] ?? 1920);
        $height = (int)($meta['height'] ?? 1080);
        $slides = $slidesData['slides'] ?? [];

        $tmpDir = sys_get_temp_dir() . '/sfodp_' . bin2hex(random_bytes(6));
        mkdir($tmpDir, 0770, true);
        mkdir($tmpDir . '/META-INF', 0770, true);
        mkdir($tmpDir . '/Pictures', 0770, true);

        $fontFiles = [];
        $fontFaceDecls = '';
        $customFonts = FontLibrary::customFontsForSlides($slidesData);
        if ($customFonts) {
            mkdir($tmpDir . '/Fonts', 0770, true);
            $fontIndex = 1;
            foreach ($customFonts as $font) {
                if (!FontLibrary::canEmbedInOdp($font['format'] ?? '')) {
                    continue;
                }
                $diskPath = FontLibrary::filePath($font);
                if (!$diskPath) {
                    continue;
                }
                $ext = $font['format'];
                $zipName = 'font' . $fontIndex . '.' . $ext;
                copy($diskPath, $tmpDir . '/Fonts/' . $zipName);
                $fontFiles[$zipName] = $font;
                $fontFaceDecls .= FontLibrary::odfFontFaceXml($font, 'Fonts/' . $zipName);
                $fontIndex++;
            }
        }

        $styleId = 1;
        $stylesXml = '';
        $pagesXml = '';
        $mediaFiles = [];

        foreach ($slides as $slide) {
            [$pageXml, $pageStyles] = self::renderPage($slide, $width, $height, $presentationId, $styleId, $mediaFiles);
            $pagesXml .= $pageXml;
            $stylesXml .= $pageStyles;
        }
        foreach ($mediaFiles as $zipName => $diskPath) {
            copy($diskPath, $tmpDir . '/Pictures/' . $zipName);
        }

        file_put_contents($tmpDir . '/mimetype', 'application/vnd.oasis.opendocument.presentation');
        file_put_contents($tmpDir . '/META-INF/manifest.xml', self::manifestXml($mediaFiles, $fontFiles));
        file_put_contents($tmpDir . '/meta.xml', self::metaXml($meta['title'] ?? 'Präsentation'));
        file_put_contents($tmpDir . '/styles.xml', self::stylesXml($width, $height, $fontFaceDecls));
        file_put_contents($tmpDir . '/content.xml', self::contentXml($width, $height, $stylesXml, $pagesXml));

        $zipPath = $tmpDir . '.odp';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        // mimetype MUSS als erste Datei, unkomprimiert, im Archiv stehen (ODF-Vorgabe)
        $zip->addFile($tmpDir . '/mimetype', 'mimetype');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        self::addDirToZip($zip, $tmpDir, '', ['mimetype']);
        $zip->close();

        self::cleanupDir($tmpDir);
        return $zipPath;
    }

    private static function addDirToZip(ZipArchive $zip, string $dir, string $prefix, array $skip = []): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $zipName = $prefix === '' ? $entry : $prefix . '/' . $entry;
            if (in_array($zipName, $skip, true)) continue;
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::addDirToZip($zip, $path, $zipName, $skip);
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

    private static function resolveAssetPath(string $url, string $presentationId): ?string
    {
        if (!preg_match('/[?&]file=([a-zA-Z0-9._-]+)/', $url, $m)) return null;
        $path = Presentation::dir($presentationId) . '/assets/' . $m[1];
        return is_file($path) ? $path : null;
    }

    private static function maybeTintSvgAsset(string $diskPath, array $obj): string
    {
        if (empty($obj['iconColor']) || strtolower(pathinfo($diskPath, PATHINFO_EXTENSION)) !== 'svg') {
            return $diskPath;
        }
        $tinted = SvgHelper::tintSvg((string)file_get_contents($diskPath), (string)$obj['iconColor']);
        $tmp = tempnam(sys_get_temp_dir(), 'sf_ic_');
        if ($tmp === false) {
            return $diskPath;
        }
        file_put_contents($tmp, $tinted);
        return $tmp;
    }

    private static function stripMarkdown(string $text): string
    {
        $text = preg_replace('/\[color=#[0-9a-fA-F]{6}\]|\[\/color\]|\[mark=#[0-9a-fA-F]{6}\]|\[\/mark\]|\[upper\]|\[\/upper\]|\[sc\]|\[\/sc\]/', '', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = preg_replace('/__(.+?)__/s', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text);
        $text = preg_replace('/\+\+(.+?)\+\+/s', '$1', $text);
        $text = preg_replace('/~~(.+?)~~/s', '$1', $text);
        $text = preg_replace('/`(.+?)`/s', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        return $text ?? '';
    }

    private static function renderPage(array $slide, int $slideW, int $slideH, string $presentationId, int &$styleId, array &$mediaFiles): array
    {
        $bg = $slide['background'] ?? null;
        $bgFillXml = '';
        if ($bg && ($bg['type'] ?? '') === 'color') {
            $bgFillXml = ' draw:fill="solid" draw:fill-color="' . self::hex($bg['value'] ?? '#111111') . '"';
        } elseif ($bg && ($bg['type'] ?? '') === 'gradient') {
            $bgFillXml = ' draw:fill="solid" draw:fill-color="' . self::hex('#111111') . '"'; // Verlauf im Seiten-Hintergrund wird zur Vereinfachung nicht abgebildet
        } else {
            $bgFillXml = ' draw:fill="solid" draw:fill-color="#111111"';
        }

        $pageStyleName = 'dp' . $styleId; $styleId++;
        $stylesXml = '<style:style style:name="' . $pageStyleName . '" style:family="drawing-page"><style:drawing-page-properties' . $bgFillXml . '/></style:style>';

        $shapesXml = '';
        foreach ($slide['objects'] ?? [] as $obj) {
            [$shapeXml, $shapeStyle] = self::renderObject($obj, $presentationId, $styleId, $mediaFiles);
            $shapesXml .= $shapeXml;
            $stylesXml .= $shapeStyle;
        }

        $pageXml = '<draw:page draw:style-name="' . $pageStyleName . '" draw:master-page-name="Standard">' . $shapesXml . '</draw:page>';
        return [$pageXml, $stylesXml];
    }

    private static function renderObject(array $obj, string $presentationId, int &$styleId, array &$mediaFiles): array
    {
        $type = $obj['type'] ?? 'rect';
        $x = self::cm($obj['x'] ?? 0);
        $y = self::cm($obj['y'] ?? 0);
        $w = self::cm(max(1, $obj['w'] ?? 100));
        $h = self::cm(max(1, $obj['h'] ?? 100));
        $rotDeg = (float)($obj['rotation'] ?? 0);
        $opacity = isset($obj['opacity']) ? (int)round(max(0, min(1, $obj['opacity'])) * 100) : 100;
        $styleName = 'gr' . $styleId; $styleId++;

        // ODF dreht um den Mittelpunkt über draw:transform (rotate in Radiant, mathematisch
        // GEGEN den Uhrzeigersinn) - unser System dreht IM Uhrzeigersinn, daher Vorzeichen drehen.
        $transform = '';
        if (abs($rotDeg) > 0.01) {
            $rad = -$rotDeg * M_PI / 180;
            $transform = ' draw:transform="rotate(' . round($rad, 5) . ') translate(' . $x . ',' . $y . ')"';
        }
        $posAttrs = $transform !== '' ? $transform : ' svg:x="' . $x . '" svg:y="' . $y . '"';

        // ---- Bild ----
        if ($type === 'image' && !empty($obj['src'])) {
            $diskPath = self::resolveAssetPath($obj['src'], $presentationId);
            if ($diskPath) {
                $diskPath = self::maybeTintSvgAsset($diskPath, $obj);
                $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION)) ?: 'png';
                $zipName = 'image' . $styleId . '.' . $ext;
                $mediaFiles[$zipName] = $diskPath;
                $style = '<style:style style:name="' . $styleName . '" style:family="graphic"><style:graphic-properties draw:opacity="' . $opacity . '%" draw:stroke="none"/></style:style>';
                $xml = '<draw:frame draw:style-name="' . $styleName . '"' . $posAttrs . ' svg:width="' . $w . '" svg:height="' . $h . '">' .
                    '<draw:image xlink:href="Pictures/' . $zipName . '" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/></draw:frame>';
                return [$xml, $style];
            }
        }

        // ---- Video/Audio Platzhalter ----
        if ($type === 'video' || $type === 'audio') {
            $label = h($type === 'video' ? 'Video' : 'Audio');
            $style = '<style:style style:name="' . $styleName . '" style:family="graphic"><style:graphic-properties draw:fill="solid" draw:fill-color="#000000" draw:stroke="solid" svg:stroke-color="#666666"/></style:style>';
            $xml = '<draw:rect draw:style-name="' . $styleName . '"' . $posAttrs . ' svg:width="' . $w . '" svg:height="' . $h . '"><text:p><text:span>' . $label . '</text:span></text:p></draw:rect>';
            return [$xml, $style];
        }

        // ---- Linie ----
        if ($type === 'shape' && ($obj['shapeType'] ?? '') === 'line') {
            $color = self::hex(($obj['stroke'] ?? '') !== 'transparent' && !empty($obj['stroke']) ? $obj['stroke'] : '#ffffff');
            $width = max(1, (float)($obj['strokeWidth'] ?? 3));
            $style = '<style:style style:name="' . $styleName . '" style:family="graphic"><style:graphic-properties draw:stroke="solid" svg:stroke-color="' . $color . '" svg:stroke-width="' . self::cm($width) . '"/></style:style>';
            $x1 = (float)($obj['x'] ?? 0); $y1 = (float)($obj['y'] ?? 0) + (float)($obj['h'] ?? 4) / 2;
            $x2 = $x1 + (float)($obj['w'] ?? 200); $y2 = $y1;
            $xml = '<draw:line draw:style-name="' . $styleName . '" svg:x1="' . self::cm($x1) . '" svg:y1="' . self::cm($y1) . '" svg:x2="' . self::cm($x2) . '" svg:y2="' . self::cm($y2) . '"/>';
            return [$xml, $style];
        }

        // ---- Text ----
        if ($type === 'text') {
            $raw = self::stripMarkdown($obj['text'] ?? '');
            if (!empty($obj['uppercase'])) $raw = mb_strtoupper($raw);
            $align = ['left' => 'start', 'center' => 'center', 'right' => 'end'][$obj['align'] ?? 'left'] ?? 'start';
            $sz = max(6, (int)round(($obj['fontSize'] ?? 32) * 0.75));
            $font = h($obj['fontFamily'] ?? 'Open Sans');
            $fontNameAttr = FontLibrary::findByFamily($obj['fontFamily'] ?? '') ? ' style:font-name="' . $font . '"' : '';
            $color = self::hex($obj['color'] ?? '#ffffff');
            $bold = ($obj['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';
            $italic = !empty($obj['italic']) ? 'italic' : 'normal';
            $underline = !empty($obj['underline']) ? ' style:text-underline-style="solid" style:text-underline-type="single"' : '';
            $strike = !empty($obj['strikethrough']) ? ' style:text-line-through-style="solid"' : '';

            $pStyleName = 'p' . $styleId . '_' . $styleId;
            $style = '<style:style style:name="' . $styleName . '" style:family="graphic"><style:graphic-properties draw:fill="none" draw:stroke="none" draw:auto-grow-height="false"/></style:style>' .
                '<style:style style:name="' . $pStyleName . '" style:family="paragraph"><style:paragraph-properties fo:text-align="' . $align . '"/>' .
                '<style:text-properties fo:font-family="' . $font . '"' . $fontNameAttr . ' fo:font-size="' . $sz . 'pt" fo:font-weight="' . $bold . '" fo:font-style="' . $italic . '" fo:color="' . $color . '"' . $underline . $strike . '/></style:style>';

            $lines = explode("\n", $raw);
            $paras = '';
            foreach ($lines as $line) {
                $paras .= '<text:p text:style-name="' . $pStyleName . '">' . h($line) . '</text:p>';
            }
            $xml = '<draw:frame draw:style-name="' . $styleName . '"' . $posAttrs . ' svg:width="' . $w . '" svg:height="' . $h . '"><draw:text-box>' . $paras . '</draw:text-box></draw:frame>';
            return [$xml, $style];
        }

        // ---- Rechteck / Ellipse / (angenäherte) Formen ----
        $fillXml = 'draw:fill="none"';
        $fillType = $obj['fillType'] ?? 'solid';
        if ($fillType === 'gradient') {
            // ODF-Gradienten brauchen einen eigenen benannten draw:gradient-Eintrag; zur
            // Vereinfachung wird hier die erste Farbe als Vollfarbe verwendet.
            $fillXml = 'draw:fill="solid" draw:fill-color="' . self::hex($obj['gradColor1'] ?? '#3a6c8d') . '"';
        } elseif ($fillType !== 'none' && isset($obj['fill'])) {
            $fillXml = 'draw:fill="solid" draw:fill-color="' . self::hex($obj['fill']) . '"';
        }
        $strokeXml = 'draw:stroke="none"';
        $strokeWidth = (float)($obj['strokeWidth'] ?? 0);
        if ($strokeWidth > 0 && ($obj['stroke'] ?? 'transparent') !== 'transparent') {
            $strokeXml = 'draw:stroke="solid" svg:stroke-color="' . self::hex($obj['stroke']) . '" svg:stroke-width="' . self::cm($strokeWidth) . '"';
        }
        $style = '<style:style style:name="' . $styleName . '" style:family="graphic"><style:graphic-properties ' . $fillXml . ' ' . $strokeXml . ' draw:opacity="' . $opacity . '%"/></style:style>';

        if ($type === 'ellipse') {
            $xml = '<draw:ellipse draw:style-name="' . $styleName . '"' . $posAttrs . ' svg:width="' . $w . '" svg:height="' . $h . '"/>';
        } else {
            // Rechteck UND alle angenäherten Formen (Stern/Pfeil/Klammer/Sprechblase/Dreieck)
            $xml = '<draw:rect draw:style-name="' . $styleName . '"' . $posAttrs . ' svg:width="' . $w . '" svg:height="' . $h . '"/>';
        }
        return [$xml, $style];
    }

    // ---------- Feststehende Gerüst-Dateien ----------

    private static function manifestXml(array $mediaFiles, array $fontFiles = []): string
    {
        $entries = '<manifest:file-entry manifest:full-path="/" manifest:version="1.2" manifest:media-type="application/vnd.oasis.opendocument.presentation"/>' .
            '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' .
            '<manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>' .
            '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>';
        $mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        foreach (array_keys($mediaFiles) as $name) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = $mimeMap[$ext] ?? 'application/octet-stream';
            $entries .= '<manifest:file-entry manifest:full-path="Pictures/' . h($name) . '" manifest:media-type="' . $mime . '"/>';
        }
        foreach ($fontFiles as $name => $font) {
            $mime = FontLibrary::odfFontMime($font['format'] ?? 'ttf');
            $entries .= '<manifest:file-entry manifest:full-path="Fonts/' . h($name) . '" manifest:media-type="' . $mime . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">' . $entries . '</manifest:manifest>';
    }

    private static function metaXml(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s');
        return '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:dc="http://purl.org/dc/elements/1.1/" office:version="1.2">' .
            '<office:meta><dc:title>' . h($title) . '</dc:title><meta:generator xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">SlideForge</meta:generator>' .
            '<dc:date>' . $now . '</dc:date></office:meta></office:document-meta>';
    }

    private static function stylesXml(int $width, int $height, string $fontFaceDecls = ''): string
    {
        $fontDeclsBlock = $fontFaceDecls !== ''
            ? '<office:font-face-decls>' . $fontFaceDecls . '</office:font-face-decls>'
            : '';
        return '<?xml version="1.0" encoding="UTF-8"?><office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" office:version="1.2">' .
            $fontDeclsBlock .
            '<office:styles/>' .
            '<office:master-styles><style:master-page style:name="Standard" style:page-layout-name="PM1" draw:style-name="dp0"/></office:master-styles>' .
            '</office:document-styles>';
    }

    private static function contentXml(int $width, int $height, string $automaticStyles, string $pages): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><office:document-content ' .
            'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" ' .
            'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" ' .
            'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" ' .
            'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" ' .
            'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" ' .
            'xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" ' .
            'xmlns:xlink="http://www.w3.org/1999/xlink" ' .
            'xmlns:presentation="urn:oasis:names:tc:opendocument:xmlns:presentation:1.0" ' .
            'office:version="1.2">' .
            '<office:automatic-styles>' .
            '<style:page-layout style:name="PM1"><style:page-layout-properties fo:page-width="' . self::cm($width) . '" fo:page-height="' . self::cm($height) . '" style:print-orientation="landscape"/></style:page-layout>' .
            '<style:style style:name="dp0" style:family="drawing-page"><style:drawing-page-properties draw:fill="solid" draw:fill-color="#111111"/></style:style>' .
            $automaticStyles .
            '</office:automatic-styles>' .
            '<office:body><office:presentation>' . $pages . '</office:presentation></office:body>' .
            '</office:document-content>';
    }
}
