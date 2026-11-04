<?php

/**
 * Excel (.xlsx) -> Folien. Gedacht für Blätter mit gezeichneten Formen (Ellipsen, Rechtecke,
 * Linien, Bilder) und ein paar Zelltexten, z. B. eine Gliederungsskizze.
 *
 * Jedes Blatt wird eine Folie; vorgegeben ist nur das erste Blatt. Jede Form bleibt ein
 * eigenes Objekt, der Text in einer Form ein eigenes Textfeld darüber, damit sich alles
 * einzeln überarbeiten lässt. Zellen mit Inhalt werden Textfelder an der Zellposition.
 * Zellraster, Formeln und Diagramme werden nicht übernommen.
 */
class XlsxImporter
{
    private const EMU_PER_PX = 9525;
    private const MARGIN = 60;

    /**
     * @param list<int> $sheetIndexes 0-basierte Blätter; leer = nur das erste
     * @return array{slides:list<array>,width:int,height:int,warnings:list<string>}
     */
    public static function import(string $filePath, string $presentationId, int $slideW = DEFAULT_SLIDE_WIDTH, int $slideH = DEFAULT_SLIDE_HEIGHT, array $sheetIndexes = []): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException(t('import.xlsx_open_failed'));
        }
        try {
            $warnings = [];
            $sheets = self::sheetList($zip);
            if ($sheets === []) {
                throw new RuntimeException(t('import.xlsx_open_failed'));
            }
            $sheetIndexes = $sheetIndexes === [] ? [0] : $sheetIndexes;
            $theme = self::themeColors($zip);
            $strings = self::sharedStrings($zip);
            $fonts = self::cellFonts($zip);
            $slides = [];
            foreach ($sheetIndexes as $idx) {
                if (!isset($sheets[$idx])) {
                    continue;
                }
                $slides[] = self::buildSlide($zip, $sheets[$idx], $presentationId, $slideW, $slideH, $theme, $strings, $fonts, $warnings);
            }
            if ($slides === []) {
                throw new RuntimeException(t('import.xlsx_open_failed'));
            }
            return ['slides' => $slides, 'width' => $slideW, 'height' => $slideH, 'warnings' => array_values(array_unique($warnings))];
        } finally {
            $zip->close();
        }
    }

    /** @return list<array{name:string,path:string}> */
    private static function sheetList(ZipArchive $zip): array
    {
        $wb = (string)$zip->getFromName('xl/workbook.xml');
        $rels = self::rels($zip, 'xl/_rels/workbook.xml.rels');
        $out = [];
        if (preg_match_all('#<sheet\b[^>]*>#', $wb, $m)) {
            foreach ($m[0] as $tag) {
                if (!preg_match('#name="([^"]*)"#', $tag, $n) || !preg_match('#r:id="([^"]*)"#', $tag, $r)) {
                    continue;
                }
                $target = $rels[$r[1]] ?? '';
                if ($target !== '') {
                    $out[] = ['name' => html_entity_decode($n[1], ENT_QUOTES | ENT_XML1, 'UTF-8'), 'path' => self::resolve('xl', $target)];
                }
            }
        }
        return $out;
    }

    /** @return array<string,string> rId => Target */
    private static function rels(ZipArchive $zip, string $relsPath): array
    {
        $x = $zip->getFromName($relsPath);
        $out = [];
        if ($x !== false && preg_match_all('#<Relationship\b[^>]*>#', $x, $m)) {
            foreach ($m[0] as $tag) {
                if (preg_match('#Id="([^"]*)"#', $tag, $i) && preg_match('#Target="([^"]*)"#', $tag, $t)) {
                    $out[$i[1]] = $t[1];
                }
            }
        }
        return $out;
    }

    private static function resolve(string $baseDir, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        $parts = explode('/', $baseDir . '/' . $target);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '..') {
                array_pop($out);
            } elseif ($p !== '' && $p !== '.') {
                $out[] = $p;
            }
        }
        return implode('/', $out);
    }

    /** @return array<string,string> Themenfarbe => hex ohne # */
    private static function themeColors(ZipArchive $zip): array
    {
        $map = ['bg1' => 'FFFFFF', 'lt1' => 'FFFFFF', 'tx1' => '000000', 'dk1' => '000000', 'bg2' => 'EEECE1', 'tx2' => '1F497D'];
        $x = $zip->getFromName('xl/theme/theme1.xml');
        if ($x !== false && preg_match('#<a:clrScheme\b.*?</a:clrScheme>#s', $x, $cs)) {
            foreach (['accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'dk2', 'lt2'] as $n) {
                if (preg_match('#<a:' . $n . '>\s*<a:srgbClr val="([0-9A-Fa-f]{6})"#', $cs[0], $m)) {
                    $map[$n] = strtoupper($m[1]);
                }
            }
        }
        return $map;
    }

    /** @return list<string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $x = $zip->getFromName('xl/sharedStrings.xml');
        $out = [];
        if ($x !== false && preg_match_all('#<si>(.*?)</si>#s', $x, $m)) {
            foreach ($m[1] as $si) {
                preg_match_all('#<t[^>]*>([^<]*)</t>#', $si, $t);
                $out[] = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        return $out;
    }

    /** Schrift je Zellformat (cellXfs-Index): Grösse in pt, fett, Farbe. @return list<array{sz:float,b:bool,color:string}> */
    private static function cellFonts(ZipArchive $zip): array
    {
        $x = $zip->getFromName('xl/styles.xml');
        $fonts = [];
        if ($x === false) {
            return [];
        }
        if (preg_match('#<fonts\b[^>]*>(.*?)</fonts>#s', $x, $fm) && preg_match_all('#<font>(.*?)</font>#s', $fm[1], $fl)) {
            foreach ($fl[1] as $f) {
                $fonts[] = [
                    'sz' => preg_match('#<sz val="([\d.]+)"#', $f, $s) ? (float)$s[1] : 11.0,
                    'b' => (bool)preg_match('#<b\s*/>|<b val="1"#', $f),
                    'color' => preg_match('#<color rgb="[0-9A-Fa-f]{2}([0-9A-Fa-f]{6})"#', $f, $c) ? strtoupper($c[1]) : '000000',
                ];
            }
        }
        $xfs = [];
        if (preg_match('#<cellXfs\b[^>]*>(.*?)</cellXfs>#s', $x, $cx) && preg_match_all('#<xf\b[^>]*>#', $cx[1], $xl)) {
            foreach ($xl[0] as $tag) {
                $fid = preg_match('#fontId="(\d+)"#', $tag, $f) ? (int)$f[1] : 0;
                $xfs[] = $fonts[$fid] ?? ['sz' => 11.0, 'b' => false, 'color' => '000000'];
            }
        }
        return $xfs;
    }

    private static function buildSlide(ZipArchive $zip, array $sheet, string $presentationId, int $slideW, int $slideH, array $theme, array $strings, array $fonts, array &$warnings): array
    {
        $xml = (string)$zip->getFromName($sheet['path']);
        $dir = dirname($sheet['path']);
        $relsPath = $dir . '/_rels/' . basename($sheet['path']) . '.rels';
        $rels = self::rels($zip, $relsPath);

        // Spaltenbreiten und Zeilenhöhen -> EMU-Positionen der Zellen
        $defCol = preg_match('#defaultColWidth="([\d.]+)"#', $xml, $m) ? (float)$m[1] : 8.43;
        $defRow = preg_match('#defaultRowHeight="([\d.]+)"#', $xml, $m) ? (float)$m[1] : 15.0;
        $colW = [];
        if (preg_match_all('#<col\b[^>]*>#', $xml, $cm)) {
            foreach ($cm[0] as $tag) {
                if (preg_match('#min="(\d+)"#', $tag, $a) && preg_match('#max="(\d+)"#', $tag, $b) && preg_match('#width="([\d.]+)"#', $tag, $w)) {
                    for ($c = (int)$a[1]; $c <= min((int)$b[1], (int)$a[1] + 200); $c++) {
                        $colW[$c] = (float)$w[1];
                    }
                }
            }
        }
        $rowH = [];
        if (preg_match_all('#<row\b[^>]*>#', $xml, $rm)) {
            foreach ($rm[0] as $tag) {
                if (preg_match('#\br="(\d+)"#', $tag, $r) && preg_match('#\bht="([\d.]+)"#', $tag, $h)) {
                    $rowH[(int)$r[1]] = (float)$h[1];
                }
            }
        }
        $colX = static function (int $col) use ($colW, $defCol): float {
            $x = 0.0;
            for ($c = 1; $c < $col; $c++) {
                $x += round(($colW[$c] ?? $defCol) * 7 + 5) * self::EMU_PER_PX;
            }
            return $x;
        };
        $rowY = static function (int $row) use ($rowH, $defRow): float {
            $y = 0.0;
            for ($r = 1; $r < $row; $r++) {
                $y += ($rowH[$r] ?? $defRow) * 12700;
            }
            return $y;
        };

        $items = []; // Objekte in EMU-Koordinaten; später skaliert
        $texts = [];

        // Zelltexte
        if (preg_match_all('#<c\b([^>]*)>(.*?)</c>#s', $xml, $cells, PREG_SET_ORDER)) {
            foreach ($cells as $c) {
                if (!preg_match('#\br="([A-Z]+)(\d+)"#', $c[1], $ref)) {
                    continue;
                }
                $type = preg_match('#\bt="(\w+)"#', $c[1], $t) ? $t[1] : '';
                if (!preg_match('#<v>([^<]*)</v>#', $c[2], $v)) {
                    if ($type === 'inlineStr' && preg_match('#<t[^>]*>([^<]*)</t>#', $c[2], $iv)) {
                        $text = html_entity_decode($iv[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    } else {
                        continue;
                    }
                } else {
                    $text = $type === 's' ? ($strings[(int)$v[1]] ?? '') : ($type === 'str' ? html_entity_decode($v[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : $v[1]);
                }
                $text = trim($text);
                if ($text === '') {
                    continue;
                }
                $style = preg_match('#\bs="(\d+)"#', $c[1], $s) ? (int)$s[1] : 0;
                $font = $fonts[$style] ?? ['sz' => 11.0, 'b' => false, 'color' => '000000'];
                $col = 0;
                foreach (str_split($ref[1]) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $fsEmu = $font['sz'] * 12700;
                $wEmu = max(($colW[$col] ?? $defCol) * 7 * self::EMU_PER_PX, mb_strlen($text) * $fsEmu * 0.6 + $fsEmu);
                $texts[] = [
                    'kind' => 'cell', 'x' => $colX($col), 'y' => $rowY((int)$ref[2]), 'w' => $wEmu, 'h' => $fsEmu * 1.5,
                    'text' => $text, 'fs' => $font['sz'] * 96 / 72, 'bold' => $font['b'], 'color' => $font['color'], 'align' => 'left',
                ];
            }
        }

        // Zeichnung (Formen, Linien, Bilder)
        $drawing = null;
        if (preg_match('#<drawing\b[^>]*r:id="([^"]*)"#', $xml, $dm) && isset($rels[$dm[1]])) {
            $drawingPath = self::resolve($dir, $rels[$dm[1]]);
            $dx = $zip->getFromName($drawingPath);
            if ($dx !== false) {
                $drawing = ['xml' => $dx, 'rels' => self::rels($zip, dirname($drawingPath) . '/_rels/' . basename($drawingPath) . '.rels'), 'dir' => dirname($drawingPath)];
            }
        }
        if ($drawing) {
            self::parseDrawing($zip, $drawing, $presentationId, $theme, $items, $warnings);
        }

        // Alles in Folienmasse bringen: Begrenzungsrahmen -> einpassen, zentrieren
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach (array_merge($items, $texts) as $o) {
            $minX = min($minX, $o['x']);
            $minY = min($minY, $o['y']);
            $maxX = max($maxX, $o['x'] + $o['w']);
            $maxY = max($maxY, $o['y'] + $o['h']);
        }
        $objects = [];
        if (is_finite($minX)) {
            $bw = max(1.0, $maxX - $minX);
            $bh = max(1.0, $maxY - $minY);
            $scale = min(($slideW - 2 * self::MARGIN) / ($bw / self::EMU_PER_PX), ($slideH - 2 * self::MARGIN) / ($bh / self::EMU_PER_PX), 4.0);
            $offX = ($slideW - $bw / self::EMU_PER_PX * $scale) / 2;
            $offY = ($slideH - $bh / self::EMU_PER_PX * $scale) / 2;
            $px = static fn(float $emu): float => $emu / self::EMU_PER_PX * $scale;
            $toX = static fn(float $x): float => $offX + $px($x - $minX);
            $toY = static fn(float $y): float => $offY + $px($y - $minY);
            foreach ($items as $o) {
                $objects[] = self::finishItem($o, $toX, $toY, $px, $scale);
                if (!empty($o['label'])) {
                    $objects[] = self::textObject($o['label'], $toX($o['x']), $toY($o['y']), $px($o['w']), $px($o['h']), $scale);
                }
            }
            foreach ($texts as $o) {
                $objects[] = self::cellTextObject($o, $toX($o['x']), $toY($o['y']), $px($o['w']), $scale);
            }
        }
        return [
            'id' => 'slide' . bin2hex(random_bytes(4)),
            'background' => ['type' => 'color', 'value' => '#ffffff'],
            'objects' => $objects,
            'notes' => $sheet['name'],
            'transition' => 'slide',
            'autoAdvance' => 0,
        ];
    }

    private static function parseDrawing(ZipArchive $zip, array $drawing, string $presentationId, array $theme, array &$items, array &$warnings): void
    {
        $xml = $drawing['xml'];
        if (!preg_match_all('#<xdr:(?:twoCellAnchor|oneCellAnchor|absoluteAnchor)\b.*?</xdr:(?:twoCellAnchor|oneCellAnchor|absoluteAnchor)>#s', $xml, $anchors)) {
            return;
        }
        foreach ($anchors[0] as $a) {
            if (!preg_match('#<a:xfrm\b([^>]*)>\s*<a:off x="(-?\d+)" y="(-?\d+)"\s*/>\s*<a:ext cx="(\d+)" cy="(\d+)"#', $a, $xf)) {
                $warnings[] = t('import.xlsx_no_geometry');
                continue;
            }
            [$x, $y, $w, $h] = [(float)$xf[2], (float)$xf[3], (float)$xf[4], (float)$xf[5]];
            $flipH = str_contains($xf[1], 'flipH="1"');
            $flipV = str_contains($xf[1], 'flipV="1"');
            if (preg_match('#<xdr:cxnSp\b#', $a)) {
                $ln = self::lineProps($a, $theme);
                if ($ln['dash'] || $ln['arrow']) {
                    $warnings[] = t('import.xlsx_line_style');
                }
                $items[] = ['kind' => 'line', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'flipH' => $flipH, 'flipV' => $flipV, 'stroke' => $ln['color'], 'sw' => $ln['width']];
            } elseif (preg_match('#<xdr:pic\b#', $a)) {
                $src = self::importPicture($zip, $drawing, $a, $presentationId);
                if ($src === null) {
                    $warnings[] = t('import.xlsx_image_failed');
                    continue;
                }
                $items[] = ['kind' => 'image', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'src' => $src];
            } elseif (preg_match('#<xdr:sp\b#', $a)) {
                $prst = preg_match('#prstGeom prst="(\w+)"#', $a, $pg) ? $pg[1] : 'rect';
                $type = $prst === 'ellipse' ? 'ellipse' : 'rect';
                if (!in_array($prst, ['rect', 'ellipse', 'roundRect'], true)) {
                    $warnings[] = t('import.xlsx_shape_as_rect', ['preset' => $prst]);
                }
                $spPr = preg_match('#<xdr:spPr\b.*?</xdr:spPr>#s', $a, $sp) ? $sp[0] : '';
                $fill = null;
                if (preg_match('#</a:prstGeom>\s*<a:solidFill>(.*?)</a:solidFill>#s', $spPr, $fm)) {
                    $fill = self::colorOf($fm[1], $theme);
                }
                $ln = self::lineProps($a, $theme);
                $items[] = [
                    'kind' => $type, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
                    'fill' => $fill, 'noFill' => $fill === null && (bool)preg_match('#</a:prstGeom>\s*<a:noFill/>#', $spPr),
                    'stroke' => $ln['color'], 'sw' => $ln['width'], 'label' => self::shapeText($a, $theme),
                ];
            }
        }
    }

    /** Linienfarbe/-breite: eigene <a:ln>, sonst die dunkle Stilvorgabe von Excel. @return array{color:string,width:float,dash:bool,arrow:bool} */
    private static function lineProps(string $anchor, array $theme): array
    {
        $color = '1F2F3D';
        $width = 2.0;
        $dash = false;
        $arrow = false;
        if (preg_match('#<a:ln\b([^>]*)>(.*?)</a:ln>#s', $anchor, $ln)) {
            if (preg_match('#\bw="(\d+)"#', $ln[1], $w)) {
                $width = max(1.0, (float)$w[1] / 12700 * 96 / 72);
            }
            if (preg_match('#<a:solidFill>(.*?)</a:solidFill>#s', $ln[2], $f)) {
                $color = self::colorOf($f[1], $theme) ?? $color;
            }
            $dash = (bool)preg_match('#<a:prstDash val="(?!solid)#', $ln[2]);
            $arrow = (bool)preg_match('#<a:(?:head|tail)End type="(?!none)#', $ln[2]);
        } elseif (preg_match('#<a:lnRef idx="\d"><a:schemeClr val="(\w+)">(?:<a:shade val="(\d+)"/>)?#', $anchor, $lr)) {
            $base = $theme[$lr[1]] ?? $color;
            $shade = isset($lr[2]) ? ((int)$lr[2]) / 100000 : 1.0;
            $color = sprintf('%02X%02X%02X', ...array_map(fn($i) => (int)round(hexdec(substr($base, $i, 2)) * $shade), [0, 2, 4]));
        }
        return ['color' => $color, 'width' => $width, 'dash' => $dash, 'arrow' => $arrow];
    }

    private static function colorOf(string $fillXml, array $theme): ?string
    {
        if (preg_match('#<a:srgbClr val="([0-9A-Fa-f]{6})"#', $fillXml, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('#<a:sysClr val="\w+" lastClr="([0-9A-Fa-f]{6})"#', $fillXml, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('#<a:schemeClr val="(\w+)"#', $fillXml, $m)) {
            return $theme[$m[1]] ?? null;
        }
        return null;
    }

    /**
     * Text einer Form als Markdown (Farbe je Lauf, fett) mit Schriftgrösse (pt) und Ausrichtung.
     * @return array{text:string,fs:float,align:string}|null
     */
    private static function shapeText(string $anchor, array $theme): ?array
    {
        if (!preg_match('#<xdr:txBody>(.*?)</xdr:txBody>#s', $anchor, $tb) || !preg_match_all('#<a:p>(.*?)</a:p>#s', $tb[1], $ps)) {
            return null;
        }
        $lines = [];
        $fs = 0.0;
        foreach ($ps[1] as $p) {
            $line = '';
            if (preg_match_all('#<a:r>(.*?)</a:r>#s', $p, $rs)) {
                foreach ($rs[1] as $r) {
                    if (!preg_match('#<a:t>([^<]*)</a:t>#', $r, $t) || $t[1] === '') {
                        continue;
                    }
                    $txt = str_replace(['*', '['], ['', '('], html_entity_decode($t[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    $rpr = preg_match('#<a:rPr\b([^>]*)>(.*?)</a:rPr>|<a:rPr\b([^>]*)/>#s', $r, $rp) ? $rp : [];
                    $attrs = ($rp[1] ?? '') . ($rp[3] ?? '');
                    if ($fs === 0.0 && preg_match('#\bsz="(\d+)"#', $attrs, $sz)) {
                        $fs = (float)$sz[1] / 100;
                    }
                    $color = isset($rp[2]) && preg_match('#<a:solidFill>(.*?)</a:solidFill>#s', $rp[2], $cf) ? self::colorOf($cf[1], $theme) : null;
                    if ($color !== null && $color !== '000000') {
                        $txt = '[color=#' . strtolower($color) . ']' . $txt . '[/color]';
                    }
                    if (str_contains($attrs, ' b="1"') || str_starts_with(trim($attrs), 'b="1"')) {
                        $txt = '**' . $txt . '**';
                    }
                    $line .= $txt;
                }
            }
            $lines[] = trim($line);
        }
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }
        $text = trim(implode("\n", $lines));
        if ($text === '') {
            return null;
        }
        return ['text' => $text, 'fs' => ($fs > 0 ? $fs : 11.0) * 96 / 72, 'align' => 'center'];
    }

    private static function importPicture(ZipArchive $zip, array $drawing, string $anchor, string $presentationId): ?string
    {
        if (!preg_match('#r:embed="([^"]*)"#', $anchor, $m) || !isset($drawing['rels'][$m[1]])) {
            return null;
        }
        $path = self::resolve($drawing['dir'], $drawing['rels'][$m[1]]);
        $bytes = $zip->getFromName($path);
        if ($bytes === false) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return null;
        }
        $dir = Presentation::dir($presentationId) . '/assets';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $name = 'xlsx_' . bin2hex(random_bytes(5)) . '.' . $ext;
        file_put_contents($dir . '/' . $name, $bytes);
        return 'asset.php?id=' . urlencode($presentationId) . '&file=' . urlencode($name);
    }

    private static function finishItem(array $o, callable $toX, callable $toY, callable $px, float $scale): array
    {
        $id = 'o' . bin2hex(random_bytes(4));
        if ($o['kind'] === 'line') {
            $x1 = $o['flipH'] ? $o['x'] + $o['w'] : $o['x'];
            $y1 = $o['flipV'] ? $o['y'] + $o['h'] : $o['y'];
            $x2 = $o['flipH'] ? $o['x'] : $o['x'] + $o['w'];
            $y2 = $o['flipV'] ? $o['y'] : $o['y'] + $o['h'];
            $dx = $px($x2 - $x1);
            $dy = $px($y2 - $y1);
            $sw = max(2.0, $o['sw'] * $scale / 2);
            return [
                'id' => $id, 'type' => 'shape', 'shapeType' => 'line',
                'x' => round($toX($x1)), 'y' => round($toY($y1)), 'w' => max(2, (int)round(hypot($dx, $dy))), 'h' => (int)round($sw),
                'rotation' => round(rad2deg(atan2($dy, $dx)), 1), 'opacity' => 1,
                'stroke' => '#' . strtolower($o['stroke']), 'strokeWidth' => (int)round($sw),
            ];
        }
        $base = ['id' => $id, 'x' => round($toX($o['x'])), 'y' => round($toY($o['y'])), 'w' => max(2, (int)round($px($o['w']))), 'h' => max(2, (int)round($px($o['h']))), 'rotation' => 0, 'opacity' => 1];
        if ($o['kind'] === 'image') {
            return $base + ['type' => 'image', 'src' => $o['src']];
        }
        $obj = $base + ['type' => $o['kind'], 'stroke' => '#' . strtolower($o['stroke']), 'strokeWidth' => max(1, (int)round($o['sw'] * $scale / 2))];
        if (!empty($o['noFill'])) {
            $obj['fillType'] = 'none';
        } else {
            $obj['fillType'] = 'solid';
            $obj['fill'] = '#' . strtolower($o['fill'] ?? 'FFFFFF');
        }
        return $obj;
    }

    /** Textfeld über einer Form, senkrecht mittig geschätzt (das Textobjekt hat keine senkrechte Ausrichtung). */
    private static function textObject(array $label, float $x, float $y, float $w, float $h, float $scale): array
    {
        $fs = max(8, (int)round($label['fs'] * $scale));
        $lines = substr_count($label['text'], "\n") + 1;
        $blockH = (int)round($lines * $fs * 1.3);
        $inW = (int)round($w * 0.86);
        return [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'text',
            'x' => round($x + ($w - $inW) / 2), 'y' => round($y + ($h - $blockH) / 2), 'w' => $inW, 'h' => $blockH,
            'rotation' => 0, 'opacity' => 1,
            'text' => $label['text'], 'fontFamily' => 'Open Sans', 'fontSize' => $fs,
            'fontWeight' => 'normal', 'italic' => false, 'underline' => false, 'strikethrough' => false,
            'color' => '#000000', 'align' => $label['align'],
        ];
    }

    private static function cellTextObject(array $o, float $x, float $y, float $w, float $scale): array
    {
        $fs = max(8, (int)round($o['fs'] * $scale));
        return [
            'id' => 'o' . bin2hex(random_bytes(4)), 'type' => 'text',
            'x' => round($x), 'y' => round($y), 'w' => max(40, (int)round($w)), 'h' => (int)round($fs * 1.4),
            'rotation' => 0, 'opacity' => 1,
            'text' => str_replace(['*', '['], ['', '('], $o['text']), 'fontFamily' => 'Open Sans', 'fontSize' => $fs,
            'fontWeight' => $o['bold'] ? 'bold' : 'normal', 'italic' => false, 'underline' => false, 'strikethrough' => false,
            'color' => '#' . strtolower($o['color']), 'align' => 'left',
        ];
    }
}
