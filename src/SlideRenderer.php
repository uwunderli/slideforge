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

    private static function arrowPoints(float $w, float $h, string $style): array
    {
        $base = [[0, 0.35], [0.6, 0.35], [0.6, 0.1], [1, 0.5], [0.6, 0.9], [0.6, 0.65], [0, 0.65]];
        $doubleH = [[0, 0.5], [0.2, 0.25], [0.2, 0.35], [0.8, 0.35], [0.8, 0.25], [1, 0.5], [0.8, 0.75], [0.8, 0.65], [0.2, 0.65], [0.2, 0.75]];
        $doubleV = [[0.5, 0], [0.25, 0.2], [0.35, 0.2], [0.35, 0.8], [0.25, 0.8], [0.5, 1], [0.75, 0.8], [0.65, 0.8], [0.65, 0.2], [0.75, 0.2]];
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
        if ($shapeType === 'arrow') return self::arrowPoints($w, $h, $obj['arrowStyle'] ?? 'right');
        if ($shapeType === 'bracket') return self::bracketPoints($w, $h, $obj['bracketStyle'] ?? 'curly-left');
        if ($shapeType === 'speech-bubble') return self::bubblePoints($w, $h, $obj['bubbleStyle'] ?? 'rect-left');
        if ($shapeType === 'line') return [[0, $h / 2], [$w, $h / 2]];
        $tpl = self::SHAPE_POINTS[$shapeType] ?? self::SHAPE_POINTS['triangle'];
        return array_map(fn($p) => [(float)$p[0] / 100 * $w, (float)$p[1] / 100 * $h], $tpl);
    }

    /**
     * Plant alle Animations-Schritte einer Folie und vergibt eindeutige, fortlaufende
     * Fragment-Indizes (1, 2, 3, …). Ohne das würden bei „Jede Zeile einzeln animieren"
     * zwei Textfelder mit überlappenden Klick-Nummern (z. B. links ab 1, rechts ab 2)
     * zur gleichen Zeit erscheinen – reveal.js gruppiert gleiche data-fragment-index-Werte.
     */
    private static function buildSlideFragmentPlan(array $objects): array
    {
        $units = [];
        foreach ($objects as $objIndex => $o) {
            if (($o['animType'] ?? 'none') === 'none') {
                continue;
            }
            $baseOrder = (int)($o['animOrder'] ?? 1);
            $ms = (int)($o['animAutoAdvance'] ?? 0);
            if (($o['type'] ?? '') === 'text' && !empty($o['animPerLine'])) {
                $lines = preg_split('/\n/', $o['text'] ?? '');
                foreach (array_keys($lines) as $lineIndex) {
                    if (!self::isAnimatablePerLineText($lines[$lineIndex])) {
                        continue;
                    }
                    $units[] = [
                        'objIndex' => $objIndex,
                        'lineIndex' => $lineIndex,
                        'sortOrder' => $baseOrder,
                        'subOrder' => $lineIndex,
                        'ms' => $ms,
                    ];
                }
            } else {
                $units[] = [
                    'objIndex' => $objIndex,
                    'lineIndex' => -1,
                    'sortOrder' => $baseOrder,
                    'subOrder' => 0,
                    'ms' => $ms,
                ];
            }
        }

        usort($units, function (array $a, array $b): int {
            if ($a['sortOrder'] !== $b['sortOrder']) {
                return $a['sortOrder'] <=> $b['sortOrder'];
            }
            if ($a['objIndex'] !== $b['objIndex']) {
                return $a['objIndex'] <=> $b['objIndex'];
            }
            return $a['subOrder'] <=> $b['subOrder'];
        });

        $fragmentIndex = [];
        $autoAdvanceForOrder = [];
        $firstUnitAutoMs = null;
        $hasManualStep = false;

        foreach ($units as $i => $u) {
            if ($u['ms'] <= 0) {
                $hasManualStep = true;
            }
            $revealIdx = $i + 1;
            $fragmentIndex[$u['objIndex']][$u['lineIndex']] = $revealIdx;
            if ($u['ms'] <= 0) {
                continue;
            }
            if ($i === 0) {
                // Erster Schritt nach Folienstart (ohne Klick), wenn animAutoAdvance > 0
                $firstUnitAutoMs = $u['ms'];
                continue;
            }
            $autoAdvanceForOrder[$revealIdx - 1] = $u['ms'];
        }

        return [
            'fragmentIndex' => $fragmentIndex,
            'autoAdvanceForOrder' => $autoAdvanceForOrder,
            'firstUnitAutoMs' => $firstUnitAutoMs,
            'animStepCount' => count($units),
            'wantsGhostPreview' => count($units) >= 2 && $hasManualStep,
        ];
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
                $attrs .= ' data-autoslide="' . ((int)$slide['autoAdvance'] * 1000) . '"';
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
            foreach ($objects as $objIndex => $obj) {
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
        $rendered = Markdown::render($line);
        $plain = trim(html_entity_decode(strip_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $plain !== '';
    }

    private static function fragmentAutoSlideAttr(array $plan, int $order): string
    {
        $autoAdvanceForOrder = $plan['autoAdvanceForOrder'] ?? [];
        if (isset($autoAdvanceForOrder[$order]) && (int)$autoAdvanceForOrder[$order] > 0) {
            return ' data-autoslide="' . (int)$autoAdvanceForOrder[$order] . '"';
        }
        // data-autoslide="0" nur wenn die Folie ein ganzes-Folie-AutoAdvance hat
        // (sonst erzeugt reveal.js nach dem letzten Fragment einen Leerschritt).
        if (!empty($plan['slideHasAutoAdvance'])) {
            return ' data-autoslide="0"';
        }
        return '';
    }

    private static function renderTextPerLine(array $obj, string $outerStyle, string $animType, array $plan, int $objIndex): string
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
            $rendered = Markdown::render($line);
            $linesHtml .= '<div class="sf-text-line fragment ' . h($animType) . '" data-fragment-index="' . $order . '"' . self::fragmentAutoSlideAttr($plan, $order) . ' style="' . $baseTextStyle . $durationCss . '">' . $rendered . '</div>';
        }

        $opacity = $obj['opacity'] ?? 1;
        return '<div style="' . $outerStyle . '"><div class="sf-object sf-text" style="width:100%; height:100%; box-sizing:border-box; opacity:' . $opacity . ';">' . $linesHtml . '</div></div>' . "\n";
    }

    private static function renderObject(array $obj, ?string $publicToken = null, array $plan = [], int $objIndex = 0): string
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
            foreach ($obj['children'] ?? [] as $child) {
                $childrenHtml .= self::renderObject($child, $publicToken, $plan, $objIndex);
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
            $content = Markdown::render($obj['text'] ?? '');
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
            $mediaId = 'data-media-id="' . h($obj['id'] ?? '') . '"';
            $extraAttrs = ' preload="auto"' . (!empty($obj['hideControls']) ? '' : ' controls') . (!empty($obj['loop']) ? ' loop' : '') . ' ' . $mediaId;
            if ($trigger === 'click') {
                $extraAttrs .= ' onclick="this.play()"';
            } elseif ($trigger === 'timed') {
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
        } else {
            $fillType = $obj['fillType'] ?? 'solid';
            if ($fillType === 'gradient') {
                $c1 = $obj['gradColor1'] ?? '#3a6c8d';
                $c2 = $obj['gradColor2'] ?? '#87b42b';
                $angle = $obj['gradAngle'] ?? 90;
                $fillCss = sprintf('linear-gradient(%sdeg, %s, %s)', h((string)$angle), h($c1), h($c2));
            } elseif ($fillType === 'none') {
                $fillCss = 'transparent';
            } else {
                $fillCss = h($obj['fill'] ?? '#cccccc');
            }
            $stroke = $obj['stroke'] ?? 'transparent';
            $strokeWidth = $obj['strokeWidth'] ?? 0;
            $innerStyle .= sprintf('background:%s; border:%dpx solid %s;', $fillCss, $strokeWidth, h($stroke));
            if ($type === 'ellipse') {
                $innerStyle .= 'border-radius:50%;';
            }
        }

        return '<div style="' . $outerStyle . '"><div class="sf-object' . $extraClass . $fragmentClass . '"' . $fragmentAttr . ' style="' . $innerStyle . '">' . $content . "</div></div>\n";
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
            return '<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="width:100%; height:100%; overflow:visible;">'
                . '<polyline points="' . $pointsAttr . '" fill="none" stroke="' . $lineColor . '" stroke-width="' . $lineWidth
                . '" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
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
                . '<stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>';
            $fillAttr = 'url(#' . $gradId . ')';
        } elseif ($fillType === 'none') {
            $fillAttr = 'none';
        } else {
            $fillAttr = h($obj['fill'] ?? '#cccccc');
        }

        return '<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="width:100%; height:100%; overflow:visible;">'
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
        foreach ($objects as $objIndex => $obj) {
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
        foreach ($objects as $objIndex => $obj) {
            $html .= self::renderObject($obj, $publicToken, $plan, $objIndex);
        }
        $html .= '</div>';
        return $html;
    }
}
