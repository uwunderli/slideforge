<?php
/**
 * Wandelt unser slides.json-Format in reveal.js <section>-Markup um.
 * Phase 1: Hintergründe (Farbe/Verlauf/Bild/Video) + Übergang pro Slide.
 * Phase 2 wird hier die Objekt-Ausgabe (Formen/Text/Animationen) ergänzen.
 */
class SlideRenderer
{
    /** @var array<string, array{mime:string, b64:string}> Eingebettete Medien für HTML-Export (Blob-Hydration) */
    private static array $inlineMedia = [];

    public static function resetInlineMedia(): void
    {
        self::$inlineMedia = [];
    }

    /** @return array<string, array{mime:string, b64:string}> */
    public static function getInlineMedia(): array
    {
        return self::$inlineMedia;
    }

    private static function registerInlineMedia(string $src, string $objId): ?string
    {
        if (strpos($src, 'data:') !== 0) {
            return null;
        }
        if (!preg_match('/^data:([^;]+);base64,(.+)$/s', $src, $m)) {
            return null;
        }
        $mime = $m[1];
        if ($mime === 'application/octet-stream') {
            if (strncmp($m[2], 'UklGR', 5) === 0) {
                $mime = 'audio/wav';
            } elseif (strncmp($m[2], 'ID3', 3) === 0 || strncmp($m[2], '//u', 3) === 0) {
                $mime = 'audio/mpeg';
            }
        }
        $ref = 'sf-media-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $objId);
        self::$inlineMedia[$ref] = ['mime' => $mime, 'b64' => $m[2]];
        return $ref;
    }
    // Muss exakt zu den Formel-Funktionen in editor.js passen (buildShapePoints() dort),
    // damit Editor-Vorschau und exportierter Foliensatz identisch aussehen.
    private const SHAPE_POINTS = [
        'triangle' => [[50, 0], [100, 100], [0, 100]],
    ];
    private const SHAPE_OPEN = ['line' => true, 'bracket' => true];

    private static function starPoints(float $w, float $h, int $n): array
    {
        $n = max(3, min(20, $n));
        $cx = $w / 2; $cy = $h / 2; $rx = $w / 2; $ry = $h / 2; $innerRatio = 0.5;
        $pts = [];
        for ($i = 0; $i < $n * 2; $i++) {
            $r = ($i % 2 === 0) ? 1 : $innerRatio;
            $angle = -M_PI / 2 + $i * M_PI / $n;
            $pts[] = [$cx + $r * $rx * cos($angle), $cy + $r * $ry * sin($angle)];
        }
        return $pts;
    }

    /** Spitze (Länge %, Vorgabe 40) und Schaft (Dicke %, Vorgabe 30) einstellbar (3.12.10); gleiche Rechnung wie editor.js arrowPoints. */
    /** Verlaufsfarbe mit Deckkraft 0–100 als rgba() (3.12.23); gleiche Rechnung wie editor.js gradRgba. */
    /**
     * Farbpunkte des Verlaufs (3.12.29), aufsteigend nach Position: [['c'=>'#hex','a'=>0–100,'p'=>0–100], …].
     * Ohne gradStops: zwei Punkte aus gradColor1/2 und gradAlpha1/2 bei 0 % und 100 %. Gleiche Regeln wie editor.js normGradStops.
     */
    private static function gradStopsOf(array $obj): array
    {
        $out = [];
        if (is_array($obj['gradStops'] ?? null)) {
            foreach (array_slice($obj['gradStops'], 0, 12) as $s) {
                if (!is_array($s)) continue;
                $c = (string)($s['c'] ?? '');
                $out[] = [
                    'c' => preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#3a6c8d',
                    'a' => is_numeric($s['a'] ?? null) ? (int)max(0, min(100, (float)$s['a'])) : 100,
                    'p' => is_numeric($s['p'] ?? null) ? (int)max(0, min(100, (float)$s['p'])) : 0,
                ];
            }
        }
        if (count($out) < 2) {
            $c1 = (string)($obj['gradColor1'] ?? '#3a6c8d');
            $c2 = (string)($obj['gradColor2'] ?? '#87b42b');
            $out = [
                ['c' => preg_match('/^#[0-9a-fA-F]{6}$/', $c1) ? $c1 : '#3a6c8d', 'a' => (int)max(0, min(100, (float)($obj['gradAlpha1'] ?? 100))), 'p' => 0],
                ['c' => preg_match('/^#[0-9a-fA-F]{6}$/', $c2) ? $c2 : '#87b42b', 'a' => (int)max(0, min(100, (float)($obj['gradAlpha2'] ?? 100))), 'p' => 100],
            ];
        }
        usort($out, fn($x, $y) => $x['p'] <=> $y['p']);
        return $out;
    }

    private static function gradSvgStops(array $obj): string
    {
        $html = '';
        foreach (self::gradStopsOf($obj) as $s) {
            $html .= '<stop offset="' . ($s['p'] / 100) . '" stop-color="' . $s['c'] . '" stop-opacity="' . self::gradOpacity($s['a']) . '"/>';
        }
        return $html;
    }

    private static function gradOpacity($alpha): string
    {
        $a = max(0, min(100, $alpha === null || $alpha === '' ? 100 : (float)$alpha)) / 100;
        return rtrim(rtrim(number_format($a, 3, '.', ''), '0'), '.') ?: '0';
    }

    private static function gradRgba(string $hex, $alpha): string
    {
        $a = max(0, min(100, $alpha === null || $alpha === '' ? 100 : (float)$alpha)) / 100;
        if (!preg_match('/^#?([0-9a-f]{6})$/i', trim($hex), $m)) {
            return h($hex);
        }
        $n = hexdec($m[1]);
        return sprintf('rgba(%d,%d,%d,%s)', ($n >> 16) & 255, ($n >> 8) & 255, $n & 255, rtrim(rtrim(number_format($a, 3, '.', ''), '0'), '.') ?: '0');
    }

    private static function arrowPoints(float $w, float $h, string $style, $head = 40, $shaft = 30): array
    {
        $L = max(5, min(95, (float)$head ?: 40)) / 100;
        $d = $L / 2;
        $sv = (float)$shaft ?: 30;
        $sh1 = min(0.8, max(0.05, $sv / 100)) / 2;
        $sh2 = min(0.5, max(0.05, $sv / 100)) / 2;
        $a = 1 - $L;
        $base = [[0, 0.5 - $sh1], [$a, 0.5 - $sh1], [$a, 0.1], [1, 0.5], [$a, 0.9], [$a, 0.5 + $sh1], [0, 0.5 + $sh1]];
        $lo = 0.5 - $sh2; $hi = 0.5 + $sh2;
        $doubleH = [[0, 0.5], [$d, 0.25], [$d, $lo], [1 - $d, $lo], [1 - $d, 0.25], [1, 0.5], [1 - $d, 0.75], [1 - $d, $hi], [$d, $hi], [$d, 0.75]];
        $doubleV = [[0.5, 0], [0.25, $d], [$lo, $d], [$lo, 1 - $d], [0.25, 1 - $d], [0.5, 1], [0.75, 1 - $d], [$hi, 1 - $d], [$hi, $d], [0.75, $d]];
        if ($style === 'left') $tpl = array_map(fn($p) => [1 - $p[0], $p[1]], $base);
        elseif ($style === 'up') $tpl = array_map(fn($p) => [$p[1], 1 - $p[0]], $base);
        elseif ($style === 'down') $tpl = array_map(fn($p) => [$p[1], $p[0]], $base);
        elseif ($style === 'double-h') $tpl = $doubleH;
        elseif ($style === 'double-v') $tpl = $doubleV;
        else $tpl = $base;
        return array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $tpl);
    }

    private static function sampleCubicBezier(
        float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, int $steps
    ): array {
        $pts = [];
        $n = max(2, $steps);
        for ($i = 0; $i <= $n; $i++) {
            $t = $i / $n;
            $u = 1 - $t;
            $a = $u * $u * $u;
            $b = 3 * $u * $u * $t;
            $c = 3 * $u * $t * $t;
            $d = $t * $t * $t;
            $pts[] = [$a * $x0 + $b * $x1 + $c * $x2 + $d * $x3, $a * $y0 + $b * $y1 + $c * $y2 + $d * $y3];
        }
        return $pts;
    }

    private static function bracketPoints(float $w, float $h, string $style): array
    {
        $isRight = strpos($style, 'right') !== false;
        $pts = [];
        if (strpos($style, 'square') === 0) {
            if ($isRight) { $pts = [[0, 0], [1, 0], [1, 1], [0, 1]]; }
            else { $pts = [[1, 0], [0, 0], [0, 1], [1, 1]]; }
        } elseif (strpos($style, 'round') === 0) {
            // Rund: elliptischer Halbbogen (typografische Klammer), Spitzen mit horizontaler Tangente
            $k = 0.55228475;
            $segs = $isRight
                ? [
                    [0.00, 0.00, $k, 0.00, 1.00, 0.5 - $k * 0.5, 1.00, 0.50],
                    [1.00, 0.50, 1.00, 0.5 + $k * 0.5, $k, 1.00, 0.00, 1.00],
                ]
                : [
                    [1.00, 0.00, 1 - $k, 0.00, 0.00, 0.5 - $k * 0.5, 0.00, 0.50],
                    [0.00, 0.50, 0.00, 0.5 + $k * 0.5, 1 - $k, 1.00, 1.00, 1.00],
                ];
            foreach ($segs as $si => $s) {
                $sampled = self::sampleCubicBezier($s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], 16);
                $start = $si === 0 ? 0 : 1;
                for ($i = $start, $len = count($sampled); $i < $len; $i++) {
                    $pts[] = $sampled[$i];
                }
            }
        } else {
            // Geschweift: typografische Accolade aus 4 kubischen Béziers (wie editor.js)
            $segs = $isRight
                ? [
                    [0.08, 0.00, 0.08, 0.06, 0.52, 0.10, 0.52, 0.28],
                    [0.52, 0.28, 0.52, 0.42, 0.95, 0.46, 1.00, 0.50],
                    [1.00, 0.50, 0.95, 0.54, 0.52, 0.58, 0.52, 0.72],
                    [0.52, 0.72, 0.52, 0.90, 0.08, 0.94, 0.08, 1.00],
                ]
                : [
                    [0.92, 0.00, 0.92, 0.06, 0.48, 0.10, 0.48, 0.28],
                    [0.48, 0.28, 0.48, 0.42, 0.05, 0.46, 0.00, 0.50],
                    [0.00, 0.50, 0.05, 0.54, 0.48, 0.58, 0.48, 0.72],
                    [0.48, 0.72, 0.48, 0.90, 0.92, 0.94, 0.92, 1.00],
                ];
            foreach ($segs as $si => $s) {
                $sampled = self::sampleCubicBezier($s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], 12);
                $start = $si === 0 ? 0 : 1;
                for ($i = $start, $len = count($sampled); $i < $len; $i++) {
                    $pts[] = $sampled[$i];
                }
            }
        }
        return array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $pts);
    }

    private static function bubblePoints(float $w, float $h, string $style): array
    {
        if ($style === 'rect-right') {
            $tpl = [[0, 0], [1, 0], [1, 0.75], [0.8, 0.75], [0.8, 1], [0.65, 0.75], [0, 0.75]];
            return array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $tpl);
        }
        if ($style === 'oval') {
            $n = 32; $cx = 0.5; $cy = 0.4; $rx = 0.48; $ry = 0.36;
            $notchStart = 195; $notchEnd = 235;
            $raw = []; $inserted = false;
            for ($i = 0; $i <= $n; $i++) {
                $deg = ($i / $n) * 360;
                if ($deg >= $notchStart && $deg <= $notchEnd) {
                    if (!$inserted) {
                        $raw[] = [0.34, 0.73]; $raw[] = [0.16, 1]; $raw[] = [0.30, 0.76];
                        $inserted = true;
                    }
                    continue;
                }
                $rad = ($deg - 90) * M_PI / 180;
                $raw[] = [$cx + $rx * cos($rad), $cy + $ry * sin($rad)];
            }
            return array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $raw);
        }
        if ($style === 'cloud') {
            $n = 48; $cx = 0.5; $cy = 0.42; $rx = 0.42; $ry = 0.32; $bumps = 6; $bumpAmp = 0.07;
            $notchStart = 205; $notchEnd = 235;
            $boundaryPoint = function ($deg) use ($cx, $cy, $rx, $ry, $bumps, $bumpAmp) {
                $t = ($deg / 360) * M_PI * 2;
                $r = 1 + $bumpAmp * sin($bumps * $t);
                return [$cx + $rx * $r * cos($t), $cy + $ry * $r * sin($t)];
            };
            $pts = []; $inserted = false;
            for ($i = 0; $i <= $n; $i++) {
                $deg = ($i / $n) * 360;
                if ($deg > $notchStart && $deg < $notchEnd) {
                    if (!$inserted) {
                        [$px1, $py1] = $boundaryPoint($notchStart);
                        [$px2, $py2] = $boundaryPoint($notchEnd);
                        $midX = ($px1 + $px2) / 2; $midY = ($py1 + $py2) / 2;
                        $dirX = $midX - $cx; $dirY = $midY - $cy;
                        $dirLen = sqrt($dirX * $dirX + $dirY * $dirY) ?: 1;
                        $tipX = $midX + ($dirX / $dirLen) * 0.32;
                        $tipY = $midY + ($dirY / $dirLen) * 0.32 + 0.16;
                        $pts[] = [$px1 * $w, $py1 * $h];
                        $pts[] = [$tipX * $w, $tipY * $h];
                        $pts[] = [$px2 * $w, $py2 * $h];
                        $inserted = true;
                    }
                    continue;
                }
                [$px, $py] = $boundaryPoint($deg);
                $pts[] = [$px * $w, $py * $h];
            }
            return $pts;
        }
        $tpl = [[0, 0], [1, 0], [1, 0.75], [0.35, 0.75], [0.2, 1], [0.2, 0.75], [0, 0.75]];
        return array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $tpl);
    }

    private static function shapePoints(string $shapeType, float $w, float $h, array $obj): array
    {
        // Rückwärtskompatibilität für ältere shapeType-Werte aus Präsentationen von vor diesem Update.
        if ($shapeType === 'arrow-thin' || $shapeType === 'arrow-thick') return self::arrowPoints($w, $h, 'right');
        if ($shapeType === 'wave-banner') {
            $tpl = [[0, 15], [25, 0], [50, 15], [75, 0], [100, 15], [100, 85], [75, 100], [50, 85], [25, 100], [0, 85]];
            return array_map(fn($p) => [$p[0] / 100 * $w, $p[1] / 100 * $h], $tpl);
        }
        if ($shapeType === 'star') return self::starPoints($w, $h, (int)($obj['starPoints'] ?? 5));
        if ($shapeType === 'arrow') return self::arrowPoints($w, $h, $obj['arrowStyle'] ?? 'right', $obj['arrowHead'] ?? 40, $obj['arrowShaft'] ?? 30);
        if ($shapeType === 'bracket') return self::bracketPoints($w, $h, $obj['bracketStyle'] ?? 'curly-left');
        if ($shapeType === 'speech-bubble') return self::bubblePoints($w, $h, $obj['bubbleStyle'] ?? 'rect-left');
        if ($shapeType === 'line') return [[0, $h / 2], [$w, $h / 2]];
        $tpl = self::SHAPE_POINTS[$shapeType] ?? self::SHAPE_POINTS['triangle'];
        return array_map(fn($p) => [(float)$p[0] / 100 * $w, (float)$p[1] / 100 * $h], $tpl);
    }

    /** Ausblende-Effekte (animExit.type) → CSS-Klasse sf-exit-<typ>. */
    private const EXIT_TYPES = ['fade', 'up', 'down', 'left', 'right', 'shrink', 'grow'];

    /**
     * Ausblende-Effekt eines Objekts, bereinigt; null = keiner.
     * Form im Datenmodell: animExit {type, order, duration, auto} (3.10.0).
     */
    public static function exitOf(array $o): ?array
    {
        $e = $o['animExit'] ?? null;
        if (!is_array($e)) {
            return null;
        }
        $type = (string)($e['type'] ?? 'none');
        if (!in_array($type, self::EXIT_TYPES, true)) {
            return null;
        }
        return [
            'type' => $type,
            'order' => max(1, (int)($e['order'] ?? 1)),
            'duration' => max(0, min(60000, (int)($e['duration'] ?? 0))),
            'auto' => max(0, min(3600000, (int)($e['auto'] ?? 0))),
        ];
    }

    /** Sammelt die Animations-Einheiten eines Objekts (auch der Kinder einer Gruppe). */
    private static function collectAnimUnits(array $o, string $key, array &$units): void
    {
        if (($o['type'] ?? '') === 'group') {
            foreach (array_values($o['children'] ?? []) as $ci => $child) {
                if (is_array($child)) {
                    self::collectAnimUnits($child, $key . '.' . $ci, $units);
                }
            }
        }
        $lastIn = null;
        if (($o['animType'] ?? 'none') !== 'none' && ($o['type'] ?? '') !== 'group') {
            $baseOrder = max(1, (int)($o['animOrder'] ?? 1));
            $ms = (int)($o['animAutoAdvance'] ?? 0);
            if (($o['type'] ?? '') === 'text' && !empty($o['animPerLine'])) {
                $lines = preg_split('/\n/', $o['text'] ?? '');
                $sub = 0;
                foreach (array_keys($lines) as $lineIndex) {
                    if (!self::isAnimatablePerLineText($lines[$lineIndex])) {
                        continue;
                    }
                    $units[] = ['key' => $key, 'kind' => 'in', 'lineIndex' => $lineIndex, 'order' => $baseOrder, 'sub' => $sub, 'ms' => $ms];
                    $lastIn = [$baseOrder, $sub];
                    $sub++;
                }
            } else {
                $units[] = ['key' => $key, 'kind' => 'in', 'lineIndex' => -1, 'order' => $baseOrder, 'sub' => 0, 'ms' => $ms];
                $lastIn = [$baseOrder, 0];
            }
        }
        $exit = self::exitOf($o);
        if ($exit !== null) {
            $order = $exit['order'];
            $sub = 0;
            // Ausblenden kommt immer nach dem letzten Einblende-Schritt desselben Objekts.
            if ($lastIn !== null && ($order < $lastIn[0] || ($order === $lastIn[0] && $sub <= $lastIn[1]))) {
                $order = $lastIn[0];
                $sub = $lastIn[1] + 1;
            }
            $units[] = ['key' => $key, 'kind' => 'exit', 'lineIndex' => -1, 'order' => $order, 'sub' => $sub, 'ms' => $exit['auto']];
        }
    }

    /**
     * Plant alle Animations-Schritte einer Folie und vergibt fortlaufende
     * Fragment-Indizes (1, 2, 3, …). Ein Schritt = eine Klick-Nummer: Objekte mit
     * derselben Nummer (Einblenden wie Ausblenden) kommen gleichzeitig, reveal.js
     * gruppiert gleiche data-fragment-index-Werte. Zeilen eines zeilenweise
     * animierten Textes bekommen Unterschritte (Nummer, Zeile) – so überlappen
     * zwei Textfelder nicht (links ab 1, rechts ab 2).
     */
    private static function buildSlideFragmentPlan(array $objects): array
    {
        $units = [];
        foreach (array_values($objects) as $objIndex => $o) {
            if (is_array($o)) {
                self::collectAnimUnits($o, (string)$objIndex, $units);
            }
        }

        usort($units, function (array $a, array $b): int {
            return [$a['order'], $a['sub']] <=> [$b['order'], $b['sub']];
        });

        $stepOf = [];
        $stepMs = [];
        $fragmentIndex = [];
        $exitIndex = [];
        foreach ($units as $u) {
            $k = $u['order'] . ':' . $u['sub'];
            if (!isset($stepOf[$k])) {
                $stepOf[$k] = count($stepOf) + 1;
                $stepMs[$stepOf[$k]] = 0;
            }
            $step = $stepOf[$k];
            if ($u['ms'] > $stepMs[$step]) {
                $stepMs[$step] = $u['ms'];
            }
            if ($u['kind'] === 'exit') {
                $exitIndex[$u['key']] = $step;
            } else {
                $fragmentIndex[$u['key']][$u['lineIndex']] = $step;
            }
        }

        $autoAdvanceForOrder = [];
        $firstUnitAutoMs = null;
        $hasManualStep = false;
        foreach ($stepMs as $step => $ms) {
            if ($ms <= 0) {
                $hasManualStep = true;
                continue;
            }
            if ($step === 1) {
                // Erster Schritt nach Folienstart (ohne Klick)
                $firstUnitAutoMs = $ms;
                continue;
            }
            $autoAdvanceForOrder[$step - 1] = $ms;
        }

        return [
            'fragmentIndex' => $fragmentIndex,
            'exitIndex' => $exitIndex,
            'autoAdvanceForOrder' => $autoAdvanceForOrder,
            'firstUnitAutoMs' => $firstUnitAutoMs,
            'animStepCount' => count($stepMs),
            'wantsGhostPreview' => count($stepMs) >= 2 && $hasManualStep,
            'hasExit' => $exitIndex !== [],
        ];
    }

    /**
     * CSS für Ausblende-Wrapper. reveal.js kennt nur fade-out als Ausgang; die
     * Richtungen und Grössen kommen von hier. Im Druck (PDF) bleibt alles sichtbar,
     * wie im Editor.
     */
    private static function exitCss(): string
    {
        $base = '.reveal .slides section .fragment.sf-exit';
        return '<style>'
            . $base . '{opacity:1;visibility:inherit;transform:none;}'
            . $base . '.visible{opacity:0;visibility:hidden;}'
            . $base . '.sf-exit-up.visible{transform:translateY(-40px);}'
            . $base . '.sf-exit-down.visible{transform:translateY(40px);}'
            . $base . '.sf-exit-left.visible{transform:translateX(-40px);}'
            . $base . '.sf-exit-right.visible{transform:translateX(40px);}'
            . $base . '.sf-exit-shrink.visible{transform:scale(0.7);}'
            . $base . '.sf-exit-grow.visible{transform:scale(1.3);}'
            . 'html.print-pdf ' . $base . ',html.reveal-print ' . $base
            . '{opacity:1 !important;visibility:inherit !important;transform:none !important;}'
            . '</style>';
    }

    /**
     * Legt um ein fertig gerendertes Objekt den Ausblende-Wrapper (äusseres Fragment).
     * $html ist das Objekt mit left/top 0 relativ zum Wrapper.
     */
    private static function wrapExit(string $html, array $exit, array $plan, string $key, $x, $y, $w, $h): string
    {
        $order = (int)($plan['exitIndex'][$key] ?? $exit['order']);
        $style = sprintf('position:absolute; left:%dpx; top:%dpx; width:%dpx; height:%dpx;', $x, $y, $w, $h);
        if ($exit['duration'] > 0) {
            $style .= 'transition-duration:' . (int)$exit['duration'] . 'ms !important;';
        }
        return '<div class="fragment sf-exit sf-exit-' . h($exit['type']) . '" data-fragment-index="' . $order . '"'
            . self::fragmentAutoSlideAttr($plan, $order) . ' style="' . $style . '">' . $html . "</div>\n";
    }

    public static function renderSections(array $slidesData, ?string $publicToken = null): string
    {
        $html = '';
        foreach ($slidesData['slides'] ?? [] as $slide) {
            $objects = $slide['objects'] ?? [];
            $plan = self::buildSlideFragmentPlan($objects);
            $plan['slideHasAutoAdvance'] = !empty($slide['autoAdvance']);

            $attrs = self::backgroundAttrs($slide['background'] ?? null, $publicToken);
            if (!empty($slide['transition'])) {
                $attrs .= ' data-transition="' . h($slide['transition']) . '"';
            }
            if (!empty($slide['autoAdvance'])) {
                // Nicht data-autoslide: das wertet reveal.js nativ aus (Boot-Pause greift nicht).
                $attrs .= ' data-sf-slide-autoslide="' . ((int)$slide['autoAdvance'] * 1000) . '"';
            } elseif (!empty($plan['firstUnitAutoMs'])) {
                $attrs .= ' data-sf-first-autoadvance="' . (int)$plan['firstUnitAutoMs'] . '"';
            }
            if (!empty($plan['wantsGhostPreview'])) {
                $attrs .= ' data-sf-ghost-preview="1"';
            }
            if (!empty($slide['presentDisabled'])) {
                $attrs .= ' data-sf-slide-disabled="1" class="sf-slide-present-disabled"';
            }
            $html .= "<section{$attrs} style=\"width:100%; height:100%;\">\n";
            if (!empty($plan['hasExit'])) {
                $html .= self::exitCss() . "\n";
            }
            foreach (array_values($objects) as $objIndex => $obj) {
                $html .= self::renderObject($obj, $publicToken, $plan, $objIndex);
            }
            $html .= "</section>\n";
        }
        return $html;
    }

    private static function backgroundAttrs(?array $bg, ?string $publicToken = null): string
    {
        if (!$bg) {
            return '';
        }
        switch ($bg['type'] ?? 'color') {
            case 'color':
                return ' data-background-color="' . h($bg['value'] ?? '#111111') . '"';
            case 'gradient':
                return ' data-background-gradient="' . h($bg['value'] ?? '') . '"';
            case 'image':
                return ' data-background-image="' . h(self::assetUrl($bg['value'] ?? '', $publicToken)) . '"';
            case 'video':
                return ' data-background-video="' . h(self::assetUrl($bg['value'] ?? '', $publicToken)) . '" data-background-video-loop data-background-video-muted';
            default:
                return '';
        }
    }

    private static function assetUrl(string $url, ?string $publicToken): string
    {
        if ($url === '' || $publicToken === null) {
            return $url;
        }
        if (strpos($url, 'asset.php?') === 0) {
            return $url . '&token=' . urlencode($publicToken);
        }
        return $url;
    }

    private static function textLineHeight(array $obj): float
    {
        $lh = (float)($obj['lineHeight'] ?? 1.2);
        return max(0.8, min(3.0, $lh));
    }

    private static function textLetterSpacing(array $obj): string
    {
        $em = (float)($obj['letterSpacing'] ?? 0);
        $em = max(-0.2, min(1.0, $em));
        return $em . 'em';
    }

    /** Leerzeilen / nur Leerzeichen: kein eigener Animationsschritt bei «Jede Zeile». */
    private static function isAnimatablePerLineText(string $line): bool
    {
        if (trim($line) === '') {
            return false;
        }
        $rendered = Markdown::render($line, false);
        $plain = trim(html_entity_decode(strip_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $plain !== '';
    }

    private static function fragmentAutoSlideAttr(array $plan, int $order): string
    {
        $autoAdvanceForOrder = $plan['autoAdvanceForOrder'] ?? [];
        if (isset($autoAdvanceForOrder[$order]) && (int)$autoAdvanceForOrder[$order] > 0) {
            // Nicht data-autoslide: reveal.js würde nativ weiterlaufen (Startfolie springt).
            return ' data-sf-fragment-autoslide="' . (int)$autoAdvanceForOrder[$order] . '"';
        }
        return '';
    }

    private static function renderTextPerLine(array $obj, string $outerStyle, string $animType, array $plan, string $objIndex): string
    {
        $fontFamily = $obj['fontFamily'] ?? 'Open Sans';
        $fontSize = $obj['fontSize'] ?? 32;
        $fontWeight = $obj['fontWeight'] ?? 'normal';
        $color = $obj['color'] ?? '#ffffff';
        $align = $obj['align'] ?? 'left';
        $fontStyleCss = !empty($obj['italic']) ? 'italic' : 'normal';
        $decoParts = [];
        if (!empty($obj['underline'])) $decoParts[] = 'underline';
        if (!empty($obj['strikethrough'])) $decoParts[] = 'line-through';
        $textDecoration = $decoParts ? implode(' ', $decoParts) : 'none';
        $textTransform = !empty($obj['uppercase']) ? 'uppercase' : 'none';
        $fontVariant = !empty($obj['smallCaps']) ? 'small-caps' : 'normal';
        $lineHeight = self::textLineHeight($obj);
        $letterSpacing = self::textLetterSpacing($obj);
        $baseTextStyle = sprintf(
            'font-family:%s,sans-serif; font-size:%dpx; font-weight:%s; font-style:%s; text-decoration:%s; text-transform:%s; font-variant:%s; color:%s; text-align:%s; white-space:pre-wrap; line-height:%s; letter-spacing:%s;',
            h($fontFamily), $fontSize, h($fontWeight), $fontStyleCss, $textDecoration, $textTransform, $fontVariant, h($color), h($align), $lineHeight, h($letterSpacing)
        );
        $durationCss = !empty($obj['animDuration']) ? 'transition-duration:' . (int)$obj['animDuration'] . 'ms !important;' : '';

        $autoAdvanceForOrder = $plan['autoAdvanceForOrder'] ?? [];
        $lineIndices = $plan['fragmentIndex'][$objIndex] ?? [];

        $lines = preg_split('/\n/', $obj['text'] ?? '');
        $linesHtml = '';
        foreach ($lines as $i => $line) {
            if (!self::isAnimatablePerLineText($line)) {
                // Abstand wie früher (&nbsp;), aber kein Fragment → kein Extra-Klick
                $linesHtml .= '<div class="sf-text-line sf-text-line-gap" aria-hidden="true" style="' . $baseTextStyle . '">&nbsp;</div>';
                continue;
            }
            $order = $lineIndices[$i] ?? ((int)($obj['animOrder'] ?? 1) + $i);
            $rendered = Markdown::render($line, false);
            $linesHtml .= '<div class="sf-text-line fragment ' . h($animType) . '" data-fragment-index="' . $order . '"' . self::fragmentAutoSlideAttr($plan, $order) . ' style="' . $baseTextStyle . $durationCss . '">' . $rendered . '</div>';
        }

        $opacity = $obj['opacity'] ?? 1;
        return '<div style="' . $outerStyle . '"><div class="sf-object sf-text" style="width:100%; height:100%; box-sizing:border-box; opacity:' . $opacity . ';">' . $linesHtml . '</div></div>' . "\n";
    }

    /**
     * Ein Objekt mit Ausblende-Effekt steckt in einem äusseren Fragment (sf-exit),
     * das Einblenden bleibt das innere Fragment: reveal.js schachtelt beide, so
     * tauscht ein Klick A gegen B, wenn A-aus und B-ein dieselbe Nummer haben.
     *
     * @param int|string $objKey Index in der Folie, bei Gruppen-Kindern «i.k»
     */
    private static function renderObject(array $obj, ?string $publicToken = null, array $plan = [], $objKey = 0): string
    {
        $key = (string)$objKey;
        $exit = self::exitOf($obj);
        if ($exit === null) {
            return self::renderObjectInner($obj, $publicToken, $plan, $key);
        }
        $inner = $obj;
        $inner['x'] = 0;
        $inner['y'] = 0;
        return self::wrapExit(
            self::renderObjectInner($inner, $publicToken, $plan, $key),
            $exit, $plan, $key,
            $obj['x'] ?? 0, $obj['y'] ?? 0, $obj['w'] ?? 100, $obj['h'] ?? 100
        );
    }

    private static function renderObjectInner(array $obj, ?string $publicToken, array $plan, string $objIndex): string
    {
        $autoAdvanceForOrder = $plan['autoAdvanceForOrder'] ?? [];
        $objectIndices = $plan['fragmentIndex'][$objIndex] ?? [];
        $x = $obj['x'] ?? 0;
        $y = $obj['y'] ?? 0;
        $w = $obj['w'] ?? 100;
        $h = $obj['h'] ?? 100;
        $rotation = $obj['rotation'] ?? 0;
        $opacity = $obj['opacity'] ?? 1;
        $type = $obj['type'] ?? 'rect';
        $animType = $obj['animType'] ?? 'none';

        if ($type === 'group') {
            $childrenHtml = '';
            foreach (array_values($obj['children'] ?? []) as $ci => $child) {
                if (is_array($child)) {
                    $childrenHtml .= self::renderObject($child, $publicToken, $plan, $objIndex . '.' . $ci);
                }
            }
            $outerStyle = sprintf(
                'position:absolute; left:%dpx; top:%dpx; width:%dpx; height:%dpx; transform:rotate(%sdeg); transform-origin:top left; opacity:%s; box-sizing:border-box;',
                $x, $y, $w, $h, $rotation, $opacity
            );
            if ($animType !== 'none') {
                $outerStyle = sprintf(
                    'position:absolute; left:%dpx; top:%dpx; width:%dpx; height:%dpx; transform:rotate(%sdeg); transform-origin:top left; box-sizing:border-box;',
                    $x, $y, $w, $h, $rotation
                );
            }
            return '<div class="sf-object-group" style="' . $outerStyle . '">' . $childrenHtml . "</div>\n";
        }

        // Äusserer Wrapper übernimmt Position/Grösse/Drehung (statisch).
        // WICHTIG: hier KEINE Opacity/Transform-Animation, sonst überschreibt unser
        // Inline-Style reveal.js' eigene Fragment-CSS (opacity/transform), die auf
        // demselben Element ansetzen würde.
        // transform-origin muss exakt zu Konva passen: Ellipsen drehen dort um ihre
        // Mitte (Konva.Ellipse-Ursprung = Zentrum), alle anderen Formen um die obere
        // linke Ecke (Konva-Ursprung = Top-Left) - sonst landen gedrehte Objekte im
        // Export an einer anderen Stelle als im Editor.
        $transformOrigin = ($type === 'ellipse') ? 'center' : 'top left';
        $outerStyle = sprintf(
            'position:absolute; left:%dpx; top:%dpx; width:%dpx; height:%dpx; transform:rotate(%sdeg); transform-origin:%s; box-sizing:border-box;',
            $x, $y, $w, $h, $rotation, $transformOrigin
        );

        // Sonderfall: zeilenweise animierter Text - jede Zeile wird ein eigenes Fragment
        // statt der ganze Textblock auf einmal. Eigener, in sich geschlossener Zweig, da
        // die normale "1 Fragment pro Objekt"-Logik hier nicht passt.
        if ($type === 'text' && !empty($obj['animPerLine']) && $animType !== 'none') {
            return self::renderTextPerLine($obj, $outerStyle, $animType, $plan, $objIndex);
        }

        // Innerer Container: hier greift ggf. die reveal.js-Fragment-Animation
        // (opacity/transform). Eigene Deckkraft nur setzen, wenn NICHT animiert,
        // sonst würde sie den Fade-Effekt dauerhaft überschreiben.
        $innerStyle = 'width:100%; height:100%; box-sizing:border-box;';
        /* Eigenes Aussehen (Füllung, Rand) getrennt halten: Bei einem animierten Objekt
           mit eigener Deckkraft muss es auf die Deckkraft-Ebene, nicht auf das
           Fragment-Element — sonst malt das Fragment die Farbe voll deckend, und die
           Deckkraft-Ebene darin bleibt leer. Genau das machte den roten Balken opak. */
        $selfStyle = '';
        if ($animType === 'none') {
            $innerStyle .= 'opacity:' . $opacity . ';';
        } elseif (!empty($obj['animDuration'])) {
            // Überschreibt reveal.js' Standard-Übergangsdauer für dieses Fragment gezielt.
            $ms = (int)$obj['animDuration'];
            $innerStyle .= 'transition-duration:' . $ms . 'ms !important;';
        }

        $fragmentClass = '';
        $fragmentAttr = '';
        if ($animType !== 'none') {
            $fragmentClass = ' fragment ' . h($animType);
            $order = $objectIndices[-1] ?? (int)($obj['animOrder'] ?? 1);
            $fragmentAttr = ' data-fragment-index="' . $order . '"';
            // Auto-Weiter nach diesem Fragment: nur setzen wenn > 0 ms. reveal.js wertet
            // data-autoslide="0" als explizites Stoppen und nutzt dann NICHT die Folien-
            // Verzögerung — deshalb Attribut weglassen statt 0.
            $fragmentAttr .= self::fragmentAutoSlideAttr($plan, $order);
        }

        $extraClass = '';
        $content = '';

        if ($type === 'text') {
            $fontFamily = $obj['fontFamily'] ?? 'Open Sans';
            $fontSize = $obj['fontSize'] ?? 32;
            $fontWeight = $obj['fontWeight'] ?? 'normal';
            $color = $obj['color'] ?? '#ffffff';
            $align = $obj['align'] ?? 'left';
            $fontStyleCss = !empty($obj['italic']) ? 'italic' : 'normal';
            $decoParts = [];
            if (!empty($obj['underline'])) $decoParts[] = 'underline';
            if (!empty($obj['strikethrough'])) $decoParts[] = 'line-through';
            $textDecoration = $decoParts ? implode(' ', $decoParts) : 'none';
            $textTransform = !empty($obj['uppercase']) ? 'uppercase' : 'none';
            $fontVariant = !empty($obj['smallCaps']) ? 'small-caps' : 'normal';
            $lineHeight = self::textLineHeight($obj);
            $letterSpacing = self::textLetterSpacing($obj);
            $innerStyle .= sprintf(
                'font-family:%s,sans-serif; font-size:%dpx; font-weight:%s; font-style:%s; text-decoration:%s; text-transform:%s; font-variant:%s; color:%s; text-align:%s; white-space:pre-wrap; line-height:%s; letter-spacing:%s;',
                h($fontFamily), $fontSize, h($fontWeight), $fontStyleCss, $textDecoration, $textTransform, $fontVariant, h($color), h($align), $lineHeight, h($letterSpacing)
            );
            $content = Markdown::render($obj['text'] ?? '', false);
            $extraClass = ' sf-text';
        } elseif ($type === 'image') {
            $src = self::assetUrl($obj['src'] ?? '', $publicToken);
            if (!empty($obj['iconColor']) && SvgHelper::normalizeHex((string)$obj['iconColor']) !== null) {
                $src .= (strpos($src, '?') !== false ? '&' : '?') . 'color=' . rawurlencode($obj['iconColor']);
            }
            $innerStyle .= 'overflow:visible; box-sizing:border-box;';
            $stroke = $obj['stroke'] ?? 'transparent';
            $strokeWidth = (float)($obj['strokeWidth'] ?? 0);
            $viewW = max(1, (int)$w);
            $viewH = max(1, (int)$h);
            $rectStroke = '';
            if ($strokeWidth > 0 && $stroke !== 'transparent' && $stroke !== '') {
                // Wie Konva: Strich zentriert auf der Objekt-Kante, Bild darunter in voller Grösse.
                $rectStroke = sprintf(
                    '<rect x="0" y="0" width="%d" height="%d" fill="none" stroke="%s" stroke-width="%s"/>',
                    $viewW,
                    $viewH,
                    h($stroke),
                    $strokeWidth
                );
            }
            $content = sprintf(
                '<svg viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" style="width:100%%;height:100%%;display:block;overflow:visible;">'
                . '<image href="%3$s" x="0" y="0" width="%1$d" height="%2$d" preserveAspectRatio="none"/>'
                . '%4$s'
                . '</svg>',
                $viewW,
                $viewH,
                h($src),
                $rectStroke
            );
        } elseif ($type === 'video' || $type === 'audio') {
            $src = self::assetUrl($obj['src'] ?? '', $publicToken);
            $trigger = $obj['playTrigger'] ?? 'manual';
            if (!in_array($trigger, ['manual', 'click', 'timed'], true)) {
                $trigger = 'manual';
            }
            $mediaId = 'data-media-id="' . h($obj['id'] ?? '') . '"';
            $extraAttrs = ' preload="auto"' . (!empty($obj['hideControls']) ? '' : ' controls')
                . (!empty($obj['loop']) ? ' loop' : '') . ' ' . $mediaId
                . ' data-play-trigger="' . h($trigger) . '"';
            if ($trigger === 'timed') {
                $delay = max(0, (int)($obj['playDelay'] ?? 0));
                $extraAttrs .= ' data-play-delay="' . $delay . '"';
            }
            $mediaRef = self::registerInlineMedia($src, (string)($obj['id'] ?? 'media'));
            if ($mediaRef) {
                $extraAttrs .= ' data-sf-media-ref="' . h($mediaRef) . '"';
                $srcAttr = '';
            } else {
                $srcAttr = ' src="' . h($src) . '"';
            }
            if ($type === 'video') {
                $innerStyle .= 'overflow:hidden;';
                $content = '<video' . $srcAttr . ' playsinline' . $extraAttrs . ' style="width:100%; height:100%; object-fit:cover; display:block;"></video>';
            } else {
                $content = '<audio' . $srcAttr . $extraAttrs . ' style="width:100%; height:100%; display:block;"></audio>';
            }
        } elseif ($type === 'shape') {
            $content = self::renderShapeSvg($obj);
        } elseif ($type === 'table') {
            $content = self::renderTable($obj, (float)$w, (float)$h);
        } else {
            $fillType = $obj['fillType'] ?? 'solid';
            if ($fillType === 'gradient') {
                $c1 = $obj['gradColor1'] ?? '#3a6c8d';
                $c2 = $obj['gradColor2'] ?? '#87b42b';
                $angle = $obj['gradAngle'] ?? 90;
                $teile = [];
                foreach (self::gradStopsOf($obj) as $st) {
                    $teile[] = self::gradRgba($st['c'], $st['a']) . ' ' . $st['p'] . '%';
                }
                $fillCss = sprintf('linear-gradient(%sdeg, %s)', h((string)$angle), implode(', ', $teile));
            } elseif ($fillType === 'none') {
                $fillCss = 'transparent';
            } else {
                $fillCss = h($obj['fill'] ?? '#cccccc');
            }
            $stroke = $obj['stroke'] ?? 'transparent';
            $strokeWidth = $obj['strokeWidth'] ?? 0;
            $selfStyle .= sprintf('background:%s; border:%dpx solid %s;', $fillCss, $strokeWidth, h($stroke));
            if ($type === 'ellipse') {
                $selfStyle .= 'border-radius:50%;';
            }
        }

        /* Eigene Deckkraft eines ANIMIERTEN Objekts gehört auf eine eigene Ebene:
           reveal.js animiert opacity auf dem Fragment-Element, ein eigener Wert
           dort würde entweder den Effekt überschreiben oder verloren gehen. */
        if ($animType !== 'none' && (float)$opacity < 1) {
            $content = '<div class="sf-own-opacity" style="width:100%; height:100%; box-sizing:border-box; opacity:'
                . $opacity . ';' . $selfStyle . '">' . $content . '</div>';
        } else {
            $innerStyle .= $selfStyle;
        }

        return '<div style="' . $outerStyle . '"><div class="sf-object' . $extraClass . $fragmentClass . '"' . $fragmentAttr . ' style="' . $innerStyle . '">' . $content . "</div></div>\n";
    }

    /**
     * WICHTIG: Das <svg> braucht `display:block`. Ohne das ist es ein Inline-Element
     * und setzt sich auf die Schriftlinie seines Elternteils — es rutscht dann um die
     * geerbte Zeilenhöhe nach unten, in der Predigt gemessene 29 Punkte bei 2 Punkten
     * Objekthöhe. Weil die geerbte Schriftgrösse je nach Ansicht verschieden ist,
     * stimmte die Lage mal und mal nicht — der Rahmen sass immer richtig, nur der
     * Inhalt darin nicht.
     */
    /**
     * Tabellen-Objekt als echte `<table>`.
     *
     * Eine echte Tabelle statt gezeichneter Linien, damit Zeilenhöhen sich nach dem
     * Inhalt richten und lange Zellen umbrechen, statt über den Rand zu laufen. Die
     * Zelltexte gehen durch `Markdown::render()` wie jeder andere Text — also mit
     * demselben Schutz gegen Fremd-Markup (dort wird zuerst escaped) und denselben
     * Auszeichnungen (`**fett**`, `[color=…]`).
     *
     * Spaltenbreiten sind Anteile (`colWidths`); fehlen sie, werden alle Spalten
     * gleich breit.
     */
    private static function renderTable(array $obj, float $w, float $h): string
    {
        $rows = is_array($obj['rows'] ?? null) ? array_values($obj['rows']) : [];
        if ($rows === []) {
            return '';
        }
        $spalten = 0;
        foreach ($rows as $zeile) {
            $spalten = max($spalten, is_array($zeile) ? count($zeile) : 0);
        }
        if ($spalten === 0) {
            return '';
        }

        $schrift = (float)($obj['fontSize'] ?? 32);
        $familie = (string)($obj['fontFamily'] ?? 'Open Sans');
        $farbe = (string)($obj['color'] ?? '#ffffff');
        $ausrichtung = (string)($obj['align'] ?? 'left');
        $rahmen = (string)($obj['borderColor'] ?? '#ffffff');
        $rahmenBreite = max(0.0, (float)($obj['borderWidth'] ?? 2));
        $innen = max(0.0, (float)($obj['cellPadding'] ?? 10));
        $zeilenhoehe = (float)($obj['lineHeight'] ?? 1.25);
        $kopfzeile = !empty($obj['headerRow']);
        $kopfSchrift = (string)($obj['headerColor'] ?? '');
        $kopfFuellung = $kopfzeile
            ? self::tabellenFuellung((string)($obj['headerFill'] ?? 'transparent'), $obj['headerFillOpacity'] ?? 1)
            : null;
        $streifenFuellung = !empty($obj['stripeRows'])
            ? self::tabellenFuellung((string)($obj['stripeFill'] ?? 'transparent'), $obj['stripeOpacity'] ?? 0.15)
            : null;

        $breiten = [];
        $rohBreiten = is_array($obj['colWidths'] ?? null) ? array_values($obj['colWidths']) : [];
        $summe = 0.0;
        for ($s = 0; $s < $spalten; $s++) {
            $wert = isset($rohBreiten[$s]) ? max(0.0, (float)$rohBreiten[$s]) : 0.0;
            $breiten[$s] = $wert;
            $summe += $wert;
        }
        if ($summe <= 0.0) {
            $breiten = array_fill(0, $spalten, 1.0);
            $summe = (float)$spalten;
        }

        /* Höhe **nicht** auf 100% zwingen: die Zeilen richten sich nach dem Inhalt,
           genau wie der Editor sie auf dem Canvas misst. Sonst stünde die Tabelle auf
           der Folie anders als im Editor. */
        $tabStil = sprintf(
            'width:100%%; border-collapse:collapse; table-layout:fixed;'
            . ' font-family:%s,sans-serif; font-size:%dpx; color:%s; text-align:%s; line-height:%s;',
            h($familie),
            (int)round($schrift),
            h($farbe),
            h($ausrichtung),
            h(rtrim(rtrim(number_format($zeilenhoehe, 2, '.', ''), '0'), '.') ?: '1.25')
        );
        $zellStil = sprintf(
            'padding:%dpx; vertical-align:top; word-wrap:break-word; overflow-wrap:break-word;',
            (int)round($innen)
        );
        if ($rahmenBreite > 0 && $rahmen !== '' && $rahmen !== 'transparent') {
            $zellStil .= sprintf('border:%dpx solid %s;', (int)round($rahmenBreite), h($rahmen));
        }

        $html = '<table style="' . $tabStil . '">';
        foreach ($breiten as $breite) {
            $html .= sprintf('<col style="width:%s%%;">', h((string)round($breite / $summe * 100, 4)));
        }
        foreach ($rows as $nr => $zeile) {
            $zeile = is_array($zeile) ? array_values($zeile) : [];
            $istKopf = $kopfzeile && $nr === 0;
            $zeilenStil = '';
            if ($istKopf && $kopfFuellung !== null) {
                $zeilenStil = ' style="background:' . h($kopfFuellung) . ';"';
            } elseif (!$istKopf && $streifenFuellung !== null
                && ($nr - ($kopfzeile ? 1 : 0)) % 2 === 1) {
                $zeilenStil = ' style="background:' . h($streifenFuellung) . ';"';
            }
            $html .= '<tr' . $zeilenStil . '>';
            for ($s = 0; $s < $spalten; $s++) {
                $text = (string)($zeile[$s] ?? '');
                $stil = $zellStil;
                if ($istKopf) {
                    $stil .= ' font-weight:bold;';
                    if ($kopfSchrift !== '' && $kopfSchrift !== 'transparent') {
                        $stil .= ' color:' . h($kopfSchrift) . ';';
                    }
                }
                $html .= '<td style="' . $stil . '">' . Markdown::render($text, false) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }

    /**
     * Füllfarbe einer Tabellenzeile aus Farbe und Füllstärke — dieselbe Rechnung wie
     * `tableFuellung()` im Editor, damit die Folie aussieht wie die Zeichenfläche.
     * `null` heisst: keine Fläche.
     */
    private static function tabellenFuellung(string $hex, $anteil): ?string
    {
        $farbe = trim($hex);
        if ($farbe === '' || $farbe === 'transparent') {
            return null;
        }
        $a = max(0.0, min(1.0, (float)($anteil ?? 1)));
        if ($a <= 0.0) {
            return null;
        }
        if ($a >= 1.0) {
            return $farbe;
        }
        if (preg_match('/^#?([0-9a-f]{6})$/i', $farbe, $m) !== 1) {
            return $farbe;
        }
        $z = hexdec($m[1]);
        return sprintf('rgba(%d,%d,%d,%s)', ($z >> 16) & 255, ($z >> 8) & 255, $z & 255, rtrim(rtrim(number_format($a, 3, '.', ''), '0'), '.'));
    }

    private static function renderShapeSvg(array $obj): string
    {
        $shapeType = $obj['shapeType'] ?? 'triangle';
        $isOpen = !empty(self::SHAPE_OPEN[$shapeType]);
        $points = self::shapePoints($shapeType, 100, 100, $obj);
        $pointsAttr = implode(' ', array_map(fn($p) => round($p[0], 3) . ',' . round($p[1], 3), $points));

        $stroke = $obj['stroke'] ?? 'transparent';
        $strokeWidth = $obj['strokeWidth'] ?? 0;
        $gradId = 'sfgrad-' . preg_replace('/[^a-zA-Z0-9]/', '', $obj['id'] ?? uniqid());

        if ($isOpen) {
            $lineColor = h(($obj['stroke'] ?? '') !== 'transparent' && !empty($obj['stroke']) ? $obj['stroke'] : '#ffffff');
            $lineWidth = $strokeWidth > 0 ? (float)$strokeWidth : 3;
            /* Offene Formen im ECHTEN Pixelraster des Objekts zeichnen, nicht in einem
               100x100-viewBox: Bei einer 1720x2-Linie streckt "preserveAspectRatio=none"
               die Achsen um 17.2 bzw. 0.02, die Strichstärke wird dadurch unbrauchbar.
               Das bisherige "non-scaling-stroke" heilte das, band die Stärke aber an
               GERÄTE-Pixel - die Linie blieb beim Herunterskalieren der Folie gleich dick
               und war in der Vorschau doppelt so breit wie im Editor. Mit echtem viewBox
               skaliert der Strich wie bei Konva mit der Folie mit. */
            $vbW = max(1.0, (float)($obj['w'] ?? 100));
            $vbH = max(1.0, (float)($obj['h'] ?? 100));
            $pts = self::shapePoints($shapeType, $vbW, $vbH, $obj);
            $ptsAttr = implode(' ', array_map(fn($p) => round($p[0], 3) . ',' . round($p[1], 3), $pts));
            return '<svg viewBox="0 0 ' . $vbW . ' ' . $vbH . '" preserveAspectRatio="none" style="width:100%; height:100%; display:block; overflow:visible;">'
                . '<polyline points="' . $ptsAttr . '" fill="none" stroke="' . $lineColor . '" stroke-width="' . $lineWidth
                . '" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }

        $defs = '';
        $fillType = $obj['fillType'] ?? 'solid';
        if ($fillType === 'gradient') {
            $c1 = h($obj['gradColor1'] ?? '#3a6c8d');
            $c2 = h($obj['gradColor2'] ?? '#87b42b');
            $angle = (float)($obj['gradAngle'] ?? 90);
            $rad = deg2rad($angle - 90);
            $dx = cos($rad); $dy = sin($rad);
            $x1 = 0.5 - $dx * 0.5; $y1 = 0.5 - $dy * 0.5;
            $x2 = 0.5 + $dx * 0.5; $y2 = 0.5 + $dy * 0.5;
            $defs = '<defs><linearGradient id="' . $gradId . '" x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '">'
                . self::gradSvgStops($obj) . '</linearGradient></defs>';
            $fillAttr = 'url(#' . $gradId . ')';
        } elseif ($fillType === 'none') {
            $fillAttr = 'none';
        } else {
            $fillAttr = h($obj['fill'] ?? '#cccccc');
        }

        return '<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="width:100%; height:100%; display:block; overflow:visible;">'
            . $defs
            . '<polygon points="' . $pointsAttr . '" fill="' . $fillAttr . '"'
            . ($strokeWidth > 0 ? ' stroke="' . h($stroke) . '" stroke-width="' . (float)$strokeWidth . '" vector-effect="non-scaling-stroke"' : '')
            . '/></svg>';
    }

    /**
     * Ghost-Layer im Präsentationsmodus: gleiche Fragment-Struktur wie die Live-Folie,
     * damit Schritt-für-Schritt-Animationen synchron einblendbar sind (nicht Endzustand).
     */
    public static function renderSlideGhostHtml(array $slide, ?string $publicToken = null): string
    {
        $bg = $slide['background'] ?? null;
        $bgStyle = 'background:#161a12;';
        if ($bg) {
            if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                $bgStyle = 'background:' . h($bg['value'] ?? '#161a12') . ';';
            } elseif (($bg['type'] ?? '') === 'image' && !empty($bg['value'])) {
                $src = self::assetUrl($bg['value'], $publicToken);
                $bgStyle = 'background-image:url(\'' . h($src) . '\'); background-size:cover; background-position:center;';
            }
        }
        $objects = $slide['objects'] ?? [];
        $plan = self::buildSlideFragmentPlan($objects);
        $html = '<div style="position:relative; width:100%; height:100%; box-sizing:border-box; ' . $bgStyle . ' overflow:hidden;">';
        foreach (array_values($objects) as $objIndex => $obj) {
            $html .= self::renderObject($obj, $publicToken, $plan, $objIndex);
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Statisches Vorschaubild einer einzelnen Folie (z.B. für den Filmstreifen im
     * Präsentationsmodus): zeigt alle Objekte im "Endzustand" nach allen Animationen,
     * da hier kein reveal.js/reveal.css läuft, das Fragmente anfangs ausblenden würde.
     * Hintergrund-Video wird aus Performance-Gründen nicht abgespielt, nur ein
     * dunkler Platzhalter gezeigt.
     */
    /**
     * Schematische Vorschau für Layout-Picker: Strich = einzeiliger Text / Linie,
     * Rechteck = Textblock oder Objekt.
     */
    public static function renderSlideSchematicThumbnailHtml(array $slide, int $slideW = 1920, int $slideH = 1080): string
    {
        $bg = $slide['background'] ?? null;
        $bgStyle = 'background:#161a12;';
        if ($bg) {
            if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                $bgStyle = 'background:' . h($bg['value'] ?? '#161a12') . ';';
            } elseif (($bg['type'] ?? '') === 'image' && !empty($bg['value'])) {
                $bgStyle = 'background:#222;';
            }
        }
        $html = '<div style="position:relative;width:100%;height:100%;box-sizing:border-box;' . $bgStyle . 'overflow:hidden;">';
        foreach ($slide['objects'] ?? [] as $obj) {
            $html .= self::renderSchematicObject($obj, $slideW, $slideH);
        }
        $html .= '</div>';
        return $html;
    }

    private static function isSingleLineTextObject(array $obj): bool
    {
        $text = str_replace("\r\n", "\n", (string)($obj['text'] ?? ''));
        if (str_contains($text, "\n")) {
            return false;
        }
        $fontSize = max(8, (int)($obj['fontSize'] ?? 32));
        $h = max(1, (int)($obj['h'] ?? (int)round($fontSize * 1.2)));
        return $h <= (int)ceil($fontSize * 1.75);
    }

    private static function renderSchematicObject(array $obj, int $slideW, int $slideH): string
    {
        if (($obj['type'] ?? '') === 'group') {
            $out = '';
            foreach ($obj['children'] ?? [] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $gx = (float)($obj['x'] ?? 0);
                $gy = (float)($obj['y'] ?? 0);
                $child['x'] = $gx + (float)($child['x'] ?? 0);
                $child['y'] = $gy + (float)($child['y'] ?? 0);
                $out .= self::renderSchematicObject($child, $slideW, $slideH);
            }
            return $out;
        }

        $x = max(0, (float)($obj['x'] ?? 0));
        $y = max(0, (float)($obj['y'] ?? 0));
        $w = max(2, (float)($obj['w'] ?? 40));
        $h = max(2, (float)($obj['h'] ?? 20));
        $left = $x / $slideW * 100;
        $top = $y / $slideH * 100;
        $width = $w / $slideW * 100;
        $height = $h / $slideH * 100;
        $box = sprintf(
            'position:absolute;left:%.3f%%;top:%.3f%%;width:%.3f%%;height:%.3f%%;box-sizing:border-box;',
            $left,
            $top,
            $width,
            $height
        );

        $type = $obj['type'] ?? '';
        $lineStyle = 'width:100%;height:3px;background:rgba(220,235,248,0.96);border-radius:2px;box-shadow:0 0 2px rgba(0,0,0,0.5);';
        $rectStyle = 'border:1.5px solid rgba(186,220,240,0.95);background:rgba(148,194,220,0.28);border-radius:2px;box-shadow:0 0 3px rgba(0,0,0,0.45);';
        if ($type === 'text') {
            if (self::isSingleLineTextObject($obj)) {
                return '<div style="' . $box . 'display:flex;align-items:center;padding:0 1px;">' .
                    '<div style="' . $lineStyle . '"></div></div>';
            }
            return '<div style="' . $box . $rectStyle . '"></div>';
        }
        if ($type === 'shape') {
            $shapeType = $obj['shapeType'] ?? 'triangle';
            if (!empty(self::SHAPE_OPEN[$shapeType]) || $shapeType === 'line') {
                return '<div style="' . $box . 'display:flex;align-items:center;padding:0 1px;">' .
                    '<div style="' . $lineStyle . '"></div></div>';
            }
        }
        return '<div style="' . $box . $rectStyle . '"></div>';
    }

    public static function renderSlideThumbnailHtml(array $slide, ?string $publicToken = null): string
    {
        $bg = $slide['background'] ?? null;
        $bgStyle = 'background:#161a12;';
        if ($bg) {
            if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                $bgStyle = 'background:' . h($bg['value'] ?? '#161a12') . ';';
            } elseif (($bg['type'] ?? '') === 'image' && !empty($bg['value'])) {
                $src = self::assetUrl($bg['value'], $publicToken);
                $bgStyle = 'background-image:url(\'' . h($src) . '\'); background-size:cover; background-position:center;';
            }
        }
        $objects = $slide['objects'] ?? [];
        $plan = self::buildSlideFragmentPlan($objects);
        $html = '<div style="position:relative; width:100%; height:100%; box-sizing:border-box; ' . $bgStyle . ' overflow:hidden;">';
        foreach (array_values($objects) as $objIndex => $obj) {
            $html .= self::renderObject($obj, $publicToken, $plan, $objIndex);
        }
        $html .= '</div>';
        return $html;
    }
}
