<?php
declare(strict_types=1);

/**
 * Vorschaubild `thumb.png` der ersten Folie für die .cfs (FileForge-Kachel, Startseite). Ohne Browser und ohne neue Abhängigkeit:
 * GD zeichnet Hintergrund (Farbe, Verlauf als erste Farbe, Bild), Bilder der Folie und die übrigen Objekte schematisch
 * (wie die Schema-Vorschau `SlideRenderer::renderSlideSchematicThumbnailHtml`). Ohne GD gibt es kein Vorschaubild.
 */
final class CfsThumb
{
    private const W = 320;

    /** Schreibt `<präsentation>/thumb.png`; false = kein Vorschaubild (altes wird entfernt). */
    public static function make(string $id): bool
    {
        $dir = Presentation::dir($id);
        $out = $dir . '/thumb.png';
        $data = Storage::read($dir . '/slides.json', []);
        $meta = Storage::read($dir . '/meta.json', []);
        $slide = is_array($data['slides'][0] ?? null) ? $data['slides'][0] : null;
        if (!extension_loaded('gd') || $slide === null) {
            @unlink($out);
            return false;
        }
        $sw = max(1, (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH));
        $sh = max(1, (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT));
        $w = self::W;
        $h = max(1, (int)round($w * $sh / $sw));
        $f = $w / $sw;
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        $bg = is_array($slide['background'] ?? null) ? $slide['background'] : [];
        $col = self::color($img, (string)($bg['value'] ?? ''), [22, 26, 18]);
        imagefilledrectangle($img, 0, 0, $w, $h, $col);
        if (($bg['type'] ?? '') === 'image') {
            self::blit($img, $dir, (string)($bg['value'] ?? ''), 0, 0, $w, $h);
        }
        $line = imagecolorallocatealpha($img, 220, 235, 248, 10);
        $fill = imagecolorallocatealpha($img, 148, 194, 220, 90);
        $edge = imagecolorallocatealpha($img, 186, 220, 240, 10);
        $draw = static function (array $o, float $gx, float $gy) use (&$draw, $img, $dir, $f, $line, $fill, $edge): void {
            $x = (int)round(($gx + (float)($o['x'] ?? 0)) * $f);
            $y = (int)round(($gy + (float)($o['y'] ?? 0)) * $f);
            $ow = max(2, (int)round((float)($o['w'] ?? 40) * $f));
            $oh = max(2, (int)round((float)($o['h'] ?? 20) * $f));
            $type = (string)($o['type'] ?? '');
            if ($type === 'group') {
                foreach ((array)($o['children'] ?? []) as $c) {
                    if (is_array($c)) {
                        $draw($c, $gx + (float)($o['x'] ?? 0), $gy + (float)($o['y'] ?? 0));
                    }
                }
                return;
            }
            if ($type === 'image' && self::blit($img, $dir, (string)($o['src'] ?? ''), $x, $y, $ow, $oh)) {
                return;
            }
            if (($type === 'text' && $oh <= 12) || ($type === 'shape' && (string)($o['shapeType'] ?? '') === 'line')) {
                imagefilledrectangle($img, $x, $y + intdiv($oh, 2) - 1, $x + $ow, $y + intdiv($oh, 2) + 1, $line);
                return;
            }
            imagefilledrectangle($img, $x, $y, $x + $ow, $y + $oh, $fill);
            imagerectangle($img, $x, $y, $x + $ow, $y + $oh, $edge);
        };
        foreach ((array)($slide['objects'] ?? []) as $o) {
            if (is_array($o)) {
                $draw($o, 0.0, 0.0);
            }
        }
        $ok = imagepng($img, $out . '.part', 6) && @rename($out . '.part', $out);
        imagedestroy($img);
        if (!$ok) {
            @unlink($out . '.part');
        }
        return $ok;
    }

    /** @param array{int,int,int} $default */
    private static function color(GdImage $img, string $v, array $default): int
    {
        if (preg_match('/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', $v, $m) === 1) {
            $hex = strlen($m[1]) === 3 ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];
            return imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        }
        if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $v, $m) === 1) {
            return imagecolorallocate($img, min(255, (int)$m[1]), min(255, (int)$m[2]), min(255, (int)$m[3]));
        }
        return imagecolorallocate($img, ...$default);
    }

    /** Bild einer Präsentation (asset.php?id=…&file=…) in das Rechteck ziehen (füllend). */
    private static function blit(GdImage $img, string $dir, string $src, int $x, int $y, int $w, int $h): bool
    {
        if (preg_match('/asset\.php\?id=([a-f0-9]+)&(?:amp;)?file=([^"&\s]+)/', $src, $m) !== 1) {
            return false;
        }
        $name = rawurldecode(urldecode($m[2]));
        if ($name === '' || basename($name) !== $name || !Presentation::exists($m[1])) {
            return false;
        }
        $path = Presentation::dir($m[1]) . '/assets/' . $name;
        if (!is_file($path) || is_link($path) || (int)@filesize($path) > 30 * 1024 * 1024) {
            return false;
        }
        $s = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'png' => @imagecreatefrompng($path),
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$s instanceof GdImage) {
            return false;
        }
        $sw = imagesx($s);
        $sh = imagesy($s);
        $scale = max($w / max(1, $sw), $h / max(1, $sh));
        $cw = (int)round($w / $scale);
        $ch = (int)round($h / $scale);
        imagecopyresampled($img, $s, $x, $y, intdiv(max(0, $sw - $cw), 2), intdiv(max(0, $sh - $ch), 2), $w, $h, min($cw, $sw), min($ch, $sh));
        imagedestroy($s);
        return true;
    }
}
