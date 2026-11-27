<?php
/**
 * Zeitstrahl-Objekt (type 'timeline', SlideForge 3.12.48) als SVG für Präsentation, Vorschau und Filmstreifen.
 *
 * Die Berechnung ist GLEICH aufgebaut wie in public/assets/js/sf-timeline.js (layout, wrap, bubblePath):
 * dieselben Zahlen, derselbe Zeilenumbruch nach geschätzter Zeichenbreite. Wer hier etwas ändert, ändert es dort mit.
 * Alle Texte gehen durch h(); Farben nur als #rrggbb (normalize), Schriftname nur nach festem Muster.
 */
class TimelineRenderer
{
    private const SIDES = ['auto', 'top', 'bottom'];
    private const SHAPES = ['rect', 'round', 'pill', 'speech'];
    private const DASHES = ['solid', 'dashed', 'dotted'];

    private static function num($v, float $def, float $min, float $max): float
    {
        $n = is_numeric($v) ? (float)$v : $def;
        if (!is_finite($n)) {
            $n = $def;
        }
        return max($min, min($max, $n));
    }

    private static function str($v, int $max = 2000): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        return mb_substr((string)$v, 0, $max);
    }

    private static function color($v, string $def): string
    {
        return (is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v)) ? $v : $def;
    }

    private static function r1(float $v): float
    {
        return round($v, 1);
    }

    private static function f(float $v): string
    {
        $s = rtrim(rtrim(number_format(round($v, 1), 1, '.', ''), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }

    public static function normalize(array $d): array
    {
        $out = [];
        $out['cats'] = [];
        $cats = is_array($d['cats'] ?? null) ? array_values($d['cats']) : [['id' => 'c1', 'name' => '', 'color' => '#4a90d9']];
        foreach (array_slice($cats, 0, 20) as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $id = (is_string($c['id'] ?? null) && preg_match('/^[a-z0-9_-]{1,24}$/i', $c['id'])) ? $c['id'] : 'c' . ($i + 1);
            $out['cats'][] = ['id' => $id, 'name' => self::str($c['name'] ?? '', 80), 'color' => self::color($c['color'] ?? null, '#4a90d9')];
        }
        if (!$out['cats']) {
            $out['cats'] = [['id' => 'c1', 'name' => '', 'color' => '#4a90d9']];
        }
        $ids = array_column($out['cats'], 'id');
        $out['events'] = [];
        $evs = is_array($d['events'] ?? null) ? array_values($d['events']) : [];
        foreach (array_slice($evs, 0, 60) as $i => $e) {
            $e = is_array($e) ? $e : [];
            $out['events'][] = [
                'label' => self::str($e['label'] ?? '', 200),
                'sub' => self::str($e['sub'] ?? '', 200),
                'title' => self::str($e['title'] ?? '', 300),
                'text' => self::str($e['text'] ?? '', 2000),
                'cat' => in_array($e['cat'] ?? null, $ids, true) ? $e['cat'] : $ids[0],
                'side' => in_array($e['side'] ?? null, self::SIDES, true) ? $e['side'] : 'auto',
            ];
        }
        $out['showLegend'] = !empty($d['showLegend']);
        $b = is_array($d['bubble'] ?? null) ? $d['bubble'] : [];
        $a = is_array($d['axis'] ?? null) ? $d['axis'] : [];
        $f = is_array($d['font'] ?? null) ? $d['font'] : [];
        $out['bubble'] = [
            'shape' => in_array($b['shape'] ?? null, self::SHAPES, true) ? $b['shape'] : 'round',
            'borderWidth' => self::num($b['borderWidth'] ?? null, 3, 0, 30),
            'borderDash' => in_array($b['borderDash'] ?? null, self::DASHES, true) ? $b['borderDash'] : 'solid',
            'fillAlpha' => self::num($b['fillAlpha'] ?? null, 0.2, 0, 1),
            'radius' => self::num($b['radius'] ?? null, 14, 0, 200),
        ];
        $out['axis'] = [
            'color' => self::color($a['color'] ?? null, '#ffffff'),
            'width' => self::num($a['width'] ?? null, 6, 1, 40),
            'arrow' => array_key_exists('arrow', $a) ? !empty($a['arrow']) : true,
            'dot' => self::num($a['dot'] ?? null, 24, 0, 120),
        ];
        $fam = (string)($f['family'] ?? '');
        $out['font'] = [
            'label' => self::num($f['label'] ?? null, 32, 8, 200),
            'title' => self::num($f['title'] ?? null, 28, 8, 200),
            'text' => self::num($f['text'] ?? null, 24, 8, 200),
            'family' => preg_match('/^[A-Za-z0-9 _-]{1,60}$/', $fam) ? $fam : 'Open Sans',
            'color' => self::color($f['color'] ?? null, '#ffffff'),
            'labelColor' => self::color($f['labelColor'] ?? null, '#f5b041'),
        ];
        return $out;
    }

    /** Zeilenumbruch nach geschätzter Zeichenbreite (fett 0.6, sonst 0.55 der Schriftgrösse), wie SfTimeline.wrap. */
    public static function wrap(string $text, float $maxW, float $size, bool $bold): array
    {
        if (preg_replace('/\s+/u', '', $text) === '') {
            return [];
        }
        $maxChars = max(1, (int)floor($maxW / ($size * ($bold ? 0.6 : 0.55))));
        $lines = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        foreach (explode("\n", $text) as $para) {
            $words = array_values(array_filter(preg_split('/\s+/u', $para) ?: [], static fn($w) => $w !== ''));
            if (!$words) {
                $lines[] = '';
                continue;
            }
            $line = '';
            foreach ($words as $w) {
                while (mb_strlen($w) > $maxChars) {
                    if ($line !== '') {
                        $lines[] = $line;
                        $line = '';
                    }
                    $lines[] = mb_substr($w, 0, $maxChars);
                    $w = mb_substr($w, $maxChars);
                }
                if ($w === '') {
                    continue;
                }
                if ($line === '') {
                    $line = $w;
                } elseif (mb_strlen($line) + 1 + mb_strlen($w) <= $maxChars) {
                    $line .= ' ' . $w;
                } else {
                    $lines[] = $line;
                    $line = $w;
                }
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    private static function rgba(string $hex, float $a): string
    {
        $z = hexdec(substr($hex, 1));
        return sprintf('rgba(%d,%d,%d,%s)', ($z >> 16) & 255, ($z >> 8) & 255, $z & 255, self::fa($a));
    }

    private static function fa(float $a): string
    {
        $s = rtrim(rtrim(number_format(round($a, 3), 3, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    }

    /** Umriss einer Blase, wie SfTimeline bubblePath. */
    public static function bubblePath(float $x, float $y, float $w, float $h, float $r, ?array $tail): string
    {
        $r = max(0, min($r, $w / 2, $h / 2));
        $f = [self::class, 'f'];
        $p = [];
        $arc = static function (float $ex, float $ey) use (&$p, $r, $f) {
            if ($r > 0) {
                $p[] = 'A' . $f($r) . ' ' . $f($r) . ' 0 0 1 ' . $f($ex) . ' ' . $f($ey);
            }
        };
        $p[] = 'M' . $f($x + $r) . ' ' . $f($y);
        if ($tail && $tail['side'] === 'top') {
            $p[] = 'L' . $f($tail['cx'] - $tail['half']) . ' ' . $f($y);
            $p[] = 'L' . $f($tail['cx']) . ' ' . $f($tail['tip']);
            $p[] = 'L' . $f($tail['cx'] + $tail['half']) . ' ' . $f($y);
        }
        $p[] = 'L' . $f($x + $w - $r) . ' ' . $f($y);
        $arc($x + $w, $y + $r);
        $p[] = 'L' . $f($x + $w) . ' ' . $f($y + $h - $r);
        $arc($x + $w - $r, $y + $h);
        if ($tail && $tail['side'] === 'bottom') {
            $p[] = 'L' . $f($tail['cx'] + $tail['half']) . ' ' . $f($y + $h);
            $p[] = 'L' . $f($tail['cx']) . ' ' . $f($tail['tip']);
            $p[] = 'L' . $f($tail['cx'] - $tail['half']) . ' ' . $f($y + $h);
        }
        $p[] = 'L' . $f($x + $r) . ' ' . $f($y + $h);
        $arc($x, $y + $h - $r);
        $p[] = 'L' . $f($x) . ' ' . $f($y + $r);
        $arc($x + $r, $y);
        $p[] = 'Z';
        return implode(' ', $p);
    }

    private static function dashOf(string $style, float $bw): ?array
    {
        if ($style === 'dashed') {
            return [self::r1($bw * 3), self::r1($bw * 2)];
        }
        if ($style === 'dotted') {
            return [0.1, self::r1($bw * 2)];
        }
        return null;
    }

    /** Zeichenteile wie SfTimeline.layout. */
    public static function layout(array $d, float $W, float $H): array
    {
        $d = self::normalize($d);
        $W = max(40, $W);
        $H = max(40, $H);
        $f = $d['font'];
        $ax = $d['axis'];
        $b = $d['bubble'];
        $catOf = [];
        foreach ($d['cats'] as $c) {
            $catOf[$c['id']] = $c;
        }
        $out = [];
        $n = count($d['events']);
        $legendH = ($d['showLegend'] && $d['cats']) ? round($f['text'] * 1.8) : 0;
        $areaH = $H - $legendH;
        $axisY = self::r1($areaH / 2);
        $aw = $ax['width'];
        $arrowLen = $ax['arrow'] ? max(14, $aw * 3.5) : 0;
        $slot = $n ? ($W - $arrowLen) / $n : $W;
        $dotR = $ax['dot'] / 2;
        $gap = max(12, $f['text'] * 0.6);
        $stem = max($dotR + 24, $areaH * 0.12);
        $bwid = $b['borderWidth'];
        $dash = self::dashOf($b['borderDash'], $bwid);

        $evs = [];
        foreach ($d['events'] as $i => $e) {
            $side = ($e['side'] === 'top' || $e['side'] === 'bottom') ? $e['side'] : ($i % 2 === 0 ? 'top' : 'bottom');
            $evs[] = ['e' => $e, 'i' => $i, 'cx' => $slot * ($i + 0.5), 'side' => $side, 'cat' => $catOf[$e['cat']] ?? $d['cats'][0]];
        }

        $axisEnd = $W - $arrowLen;
        $out[] = ['k' => 'line', 'x1' => 0, 'y1' => $axisY, 'x2' => self::r1($ax['arrow'] ? $axisEnd + 1 : $W), 'y2' => $axisY, 'stroke' => $ax['color'], 'sw' => $aw];
        if ($ax['arrow']) {
            $ah = $arrowLen * 0.6;
            $out[] = ['k' => 'path', 'd' => 'M' . self::f($axisEnd) . ' ' . self::f($axisY - $ah) . ' L' . self::f($W) . ' ' . self::f($axisY)
                . ' L' . self::f($axisEnd) . ' ' . self::f($axisY + $ah) . ' Z', 'fill' => $ax['color'], 'stroke' => null, 'sw' => 0, 'dash' => null];
        }

        foreach ($evs as $v) {
            $near = INF;
            foreach ($evs as $o) {
                if ($o['i'] !== $v['i'] && $o['side'] === $v['side']) {
                    $near = min($near, abs($o['cx'] - $v['cx']));
                }
            }
            $bw = max(60, min($near - $gap, $W * 0.4));
            $bx = max(0, min($W - $bw, $v['cx'] - $bw / 2));
            $pad = round($f['text'] * 0.55);
            $tw = $bw - 2 * $pad;
            $tLines = self::wrap($v['e']['title'], $tw, $f['title'], true);
            $xLines = self::wrap($v['e']['text'], $tw, $f['text'], false);
            $lhT = $f['title'] * 1.2;
            $lhX = $f['text'] * 1.25;
            $contentH = count($tLines) * $lhT + (($tLines && $xLines) ? $f['text'] * 0.35 : 0) + count($xLines) * $lhX;
            $bh = max($contentH + 2 * $pad, $f['text'] * 1.6);
            if ($v['side'] === 'top') {
                $by = max(0, $axisY - $stem - $bh);
                if ($by + $bh > $axisY - $dotR - 4) {
                    $by = $axisY - $dotR - 4 - $bh;
                }
            } else {
                $by = $axisY + $stem;
                if ($by + $bh > $areaH) {
                    $by = max($axisY + $dotR + 4, $areaH - $bh);
                }
            }
            $edge = $v['side'] === 'top' ? $by + $bh : $by;
            $col = $v['cat']['color'];
            $radius = $b['shape'] === 'rect' ? 0 : ($b['shape'] === 'pill' ? min($bh, $bw) / 2 : $b['radius']);
            $tail = null;
            $lineFrom = $edge;
            if ($b['shape'] === 'speech') {
                $room = abs($axisY - $edge) - $dotR;
                $th = min(max(8, $room) * 0.6, 40);
                $half = min($bw * 0.08, 18) + 6;
                $r = min($radius, $bw / 2, $bh / 2);
                $tcx = max($bx + $r + $half, min($bx + $bw - $r - $half, $v['cx']));
                $tip = $v['side'] === 'top' ? $edge + $th : $edge - $th;
                $tail = ['side' => $v['side'] === 'top' ? 'bottom' : 'top', 'cx' => $tcx, 'half' => $half, 'tip' => $tip];
                $lineFrom = $tip;
            }
            $out[] = ['k' => 'line', 'x1' => self::r1($v['cx']), 'y1' => self::r1($lineFrom), 'x2' => self::r1($v['cx']), 'y2' => $axisY, 'stroke' => $col, 'sw' => max(1, $bwid ?: 2)];
            $out[] = ['k' => 'path', 'd' => self::bubblePath($bx, $by, $bw, $bh, $radius, $tail), 'fill' => self::rgba($col, $b['fillAlpha']),
                'stroke' => $bwid > 0 ? $col : null, 'sw' => $bwid, 'dash' => $dash];
            $ty = $by + ($bh - $contentH) / 2;
            foreach ($tLines as $ln) {
                $out[] = ['k' => 'text', 'x' => self::r1($bx + $pad), 'y' => self::r1($ty + ($lhT - $f['title']) / 2), 'w' => self::r1($tw), 'text' => $ln,
                    'size' => $f['title'], 'bold' => true, 'color' => $f['color'], 'align' => 'center'];
                $ty += $lhT;
            }
            if ($tLines && $xLines) {
                $ty += $f['text'] * 0.35;
            }
            foreach ($xLines as $ln) {
                $out[] = ['k' => 'text', 'x' => self::r1($bx + $pad), 'y' => self::r1($ty + ($lhX - $f['text']) / 2), 'w' => self::r1($tw), 'text' => $ln,
                    'size' => $f['text'], 'bold' => false, 'color' => $f['color'], 'align' => 'center'];
                $ty += $lhX;
            }

            $lw = $slot * 0.96;
            $lLines = self::wrap($v['e']['label'], $lw, $f['label'], true);
            $sSize = round($f['label'] * 0.65);
            $sLines = self::wrap($v['e']['sub'], $lw, $sSize, false);
            $blockH = count($lLines) * $f['label'] * 1.15 + count($sLines) * $sSize * 1.2;
            $off = $dotR + max(8, $f['label'] * 0.3);
            $ly = $v['side'] === 'top' ? $axisY + $off : $axisY - $off - $blockH;
            foreach ($lLines as $ln) {
                $out[] = ['k' => 'text', 'x' => self::r1($v['cx'] - $lw / 2), 'y' => self::r1($ly + $f['label'] * 0.075), 'w' => self::r1($lw), 'text' => $ln,
                    'size' => $f['label'], 'bold' => true, 'color' => $f['labelColor'], 'align' => 'center'];
                $ly += $f['label'] * 1.15;
            }
            foreach ($sLines as $ln) {
                $out[] = ['k' => 'text', 'x' => self::r1($v['cx'] - $lw / 2), 'y' => self::r1($ly + $sSize * 0.1), 'w' => self::r1($lw), 'text' => $ln,
                    'size' => $sSize, 'bold' => false, 'color' => $f['labelColor'], 'align' => 'center'];
                $ly += $sSize * 1.2;
            }
        }

        if ($dotR > 0) {
            foreach ($evs as $v) {
                $out[] = ['k' => 'circle', 'cx' => self::r1($v['cx']), 'cy' => $axisY, 'r' => self::r1($dotR), 'fill' => $v['cat']['color'],
                    'stroke' => $ax['color'], 'sw' => max(1, round($aw * 0.4))];
            }
        }

        if ($legendH) {
            $ls = $f['text'] * 0.9;
            $sw = round($ls * 0.9);
            $gapL = round($ls * 1.2);
            $items = [];
            foreach ($d['cats'] as $c) {
                $items[] = ['c' => $c, 'w' => $sw + 8 + mb_strlen($c['name']) * $ls * 0.55];
            }
            $total = array_sum(array_column($items, 'w')) + $gapL * max(0, count($items) - 1);
            $lx = max(0, ($W - $total) / 2);
            $lyc = $areaH + $legendH / 2;
            foreach ($items as $it) {
                $out[] = ['k' => 'path', 'd' => self::bubblePath($lx, $lyc - $sw / 2, $sw, $sw, 3, null),
                    'fill' => self::rgba($it['c']['color'], max(0.35, $b['fillAlpha'])), 'stroke' => $it['c']['color'], 'sw' => 2, 'dash' => null];
                $out[] = ['k' => 'text', 'x' => self::r1($lx + $sw + 8), 'y' => self::r1($lyc - $ls / 2), 'w' => self::r1($it['w'] - $sw - 8 + 4), 'text' => $it['c']['name'],
                    'size' => $ls, 'bold' => false, 'color' => $f['color'], 'align' => 'left'];
                $lx += $it['w'] + $gapL;
            }
        }
        return ['font' => $f['family'], 'parts' => $out];
    }

    /** SVG über die ganze Objektfläche (viewBox in Objektpunkten, skaliert mit der Folie). */
    public static function render(array $obj, float $w, float $h): string
    {
        $w = max(40, $w);
        $h = max(40, $h);
        $res = self::layout($obj, $w, $h);
        $fam = h($res['font']);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::f($w) . ' ' . self::f($h) . '" width="100%" height="100%"'
            . ' preserveAspectRatio="none" style="display:block; overflow:visible;" font-family="' . $fam . ', sans-serif">';
        foreach ($res['parts'] as $p) {
            if ($p['k'] === 'line') {
                $svg .= '<line x1="' . self::f($p['x1']) . '" y1="' . self::f($p['y1']) . '" x2="' . self::f($p['x2']) . '" y2="' . self::f($p['y2'])
                    . '" stroke="' . h($p['stroke']) . '" stroke-width="' . self::f($p['sw']) . '"/>';
            } elseif ($p['k'] === 'path') {
                $attr = ' fill="' . h($p['fill'] ?? 'none') . '"';
                if (!empty($p['stroke'])) {
                    $attr .= ' stroke="' . h($p['stroke']) . '" stroke-width="' . self::f($p['sw']) . '" stroke-linejoin="round"';
                    if (!empty($p['dash'])) {
                        $attr .= ' stroke-dasharray="' . self::f($p['dash'][0]) . ' ' . self::f($p['dash'][1]) . '"';
                        if ($p['dash'][0] < 1) {
                            $attr .= ' stroke-linecap="round"';
                        }
                    }
                }
                $svg .= '<path d="' . h($p['d']) . '"' . $attr . '/>';
            } elseif ($p['k'] === 'circle') {
                $svg .= '<circle cx="' . self::f($p['cx']) . '" cy="' . self::f($p['cy']) . '" r="' . self::f($p['r']) . '" fill="' . h($p['fill'])
                    . '" stroke="' . h($p['stroke']) . '" stroke-width="' . self::f($p['sw']) . '"/>';
            } else {
                $center = $p['align'] === 'center';
                $tx = $center ? $p['x'] + $p['w'] / 2 : $p['x'];
                $svg .= '<text x="' . self::f($tx) . '" y="' . self::f($p['y'] + $p['size'] / 2) . '" font-size="' . self::f($p['size']) . '"'
                    . ($p['bold'] ? ' font-weight="bold"' : '') . ' fill="' . h($p['color']) . '" text-anchor="' . ($center ? 'middle' : 'start') . '"'
                    . ' dominant-baseline="central" xml:space="preserve">' . h($p['text']) . '</text>';
            }
        }
        return $svg . '</svg>';
    }
}
