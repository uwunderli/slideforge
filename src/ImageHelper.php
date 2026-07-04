<?php
/**
 * Bildbearbeitung: einfache Hintergrundentfernung für Folien-Assets.
 */
class ImageHelper
{
    private const MAX_PIXELS = 4096 * 4096;
    private const DEFAULT_TOLERANCE = 28;

    public static function removeBackgroundFromAsset(string $presentationId, string $filename): array
    {
        $filename = basename($filename);
        if ($filename === '' || preg_match('/[\/\\\\]/', $filename)) {
            return ['ok' => false, 'error' => 'invalid_filename'];
        }

        $assetsDir = Presentation::dir($presentationId) . '/assets';
        $path = $assetsDir . '/' . $filename;
        $realAssets = realpath($assetsDir);
        $realPath = realpath($path);
        if (!$realPath || !$realAssets || strpos($realPath, $realAssets) !== 0 || !is_file($realPath)) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        if ($ext === 'svg') {
            $body = self::removeSvgBackground((string)file_get_contents($realPath));
            $outExt = 'svg';
        } else {
            $png = self::removeRasterBackground($realPath);
            if ($png === null) {
                return ['ok' => false, 'error' => 'process_failed'];
            }
            $body = $png;
            $outExt = 'png';
        }

        $base = preg_replace('/(\.[^.]+)?$/', '', pathinfo($filename, PATHINFO_FILENAME)) ?: 'image';
        $base = preg_replace('/_nobg$/', '', $base);
        $newName = $base . '_nobg.' . $outExt;
        $dest = $assetsDir . '/' . $newName;
        if (file_put_contents($dest, $body) === false) {
            return ['ok' => false, 'error' => 'save_failed'];
        }

        return [
            'ok' => true,
            'filename' => $newName,
            'url' => 'asset.php?id=' . urlencode($presentationId) . '&file=' . urlencode($newName),
        ];
    }

    public static function removeSvgBackground(string $svg): string
    {
        if (stripos($svg, '<svg') === false) {
            return $svg;
        }

        // Offensichtliche Vollflächen-Hintergründe (typisch Openclipart)
        $svg = preg_replace_callback(
            '/<rect\b[^>]*\/?>/i',
            static function (array $m): string {
                $tag = $m[0];
                if (!self::tagHasLightFill($tag)) {
                    return $tag;
                }
                if (self::tagIsLargeRect($tag)) {
                    return '';
                }
                return $tag;
            },
            $svg
        ) ?? $svg;

        // Einige Cliparts: weißes Hintergrund-Path als erstes Element
        $svg = preg_replace_callback(
            '/<path\b[^>]*\/?>/i',
            static function (array $m): string {
                $tag = $m[0];
                if (!self::tagHasLightFill($tag)) {
                    return $tag;
                }
                if (preg_match('/\bd\s*=\s*["\']M\s*0[\s,]+0/i', $tag)) {
                    return '';
                }
                return $tag;
            },
            $svg,
            1
        ) ?? $svg;

        $svg = preg_replace('/<svg([^>]*)\sfill\s*=\s*["\'](?:#fff(?:fff)?|white)["\']/i', '<svg$1', $svg, 1) ?? $svg;

        return $svg;
    }

    public static function removeRasterBackground(string $path, int $tolerance = self::DEFAULT_TOLERANCE): ?string
    {
        if (extension_loaded('imagick')) {
            $result = self::removeRasterBackgroundImagick($path, $tolerance);
            if ($result !== null) {
                return $result;
            }
        }
        if (!extension_loaded('gd')) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $img = match ($ext) {
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            default => false,
        };
        if ($img === false) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0 || ($w * $h) > self::MAX_PIXELS) {
            imagedestroy($img);
            return null;
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);

        $samples = [
            self::rgbAt($img, 0, 0),
            self::rgbAt($img, $w - 1, 0),
            self::rgbAt($img, 0, $h - 1),
            self::rgbAt($img, $w - 1, $h - 1),
        ];
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $px = self::rgbAt($img, $x, $y);
                foreach ($samples as $sample) {
                    if (self::colorNear($px, $sample, $tolerance)) {
                        imagesetpixel($img, $x, $y, $transparent);
                        break;
                    }
                }
            }
        }

        ob_start();
        $ok = imagepng($img, null, 6);
        $png = ob_get_clean();
        imagedestroy($img);
        return ($ok && $png !== false) ? $png : null;
    }

    private static function removeRasterBackgroundImagick(string $path, int $tolerance): ?string
    {
        try {
            $im = new Imagick($path);
            $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            if ($w <= 0 || $h <= 0 || ($w * $h) > self::MAX_PIXELS) {
                $im->destroy();
                return null;
            }
            $pixel = $im->getImagePixelColor(0, 0);
            $im->transparentPaintImage(
                $pixel,
                0,
                max(0.01, $tolerance / 100),
                false
            );
            $im->setImageFormat('png');
            $blob = $im->getImageBlob();
            $im->destroy();
            return $blob !== '' ? $blob : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function tagHasLightFill(string $tag): bool
    {
        if (preg_match('/\bfill\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
            return self::isLightColor($m[1]);
        }
        if (preg_match('/\bstyle\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
            if (preg_match('/fill\s*:\s*([^;"\']+)/i', $m[1], $fill)) {
                return self::isLightColor(trim($fill[1]));
            }
        }
        return false;
    }

    private static function tagIsLargeRect(string $tag): bool
    {
        if (preg_match('/\bwidth\s*=\s*["\'](\d+(?:\.\d+)?)(?:px)?["\']/i', $tag, $w)
            && preg_match('/\bheight\s*=\s*["\'](\d+(?:\.\d+)?)(?:px)?["\']/i', $tag, $h)) {
            return (float)$w[1] >= 64 && (float)$h[1] >= 64;
        }
        if (preg_match('/\bwidth\s*=\s*["\']100%["\']/i', $tag) && preg_match('/\bheight\s*=\s*["\']100%["\']/i', $tag)) {
            return true;
        }
        return false;
    }

    private static function isLightColor(string $color): bool
    {
        $color = trim(strtolower($color));
        if (in_array($color, ['#fff', '#ffffff', 'white', '#fefefe', '#fdfdfd', '#fafafa'], true)) {
            return true;
        }
        if (preg_match('/^#([0-9a-f]{3})$/', $color, $m)) {
            $color = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        if (preg_match('/^#([0-9a-f]{6})$/', $color, $m)) {
            $r = hexdec(substr($m[1], 0, 2));
            $g = hexdec(substr($m[1], 2, 2));
            $b = hexdec(substr($m[1], 4, 2));
            return $r >= 245 && $g >= 245 && $b >= 245;
        }
        if (preg_match('/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/', $color, $m)) {
            return (int)$m[1] >= 245 && (int)$m[2] >= 245 && (int)$m[3] >= 245;
        }
        return false;
    }

    /** @return array{r: int, g: int, b: int} */
    private static function rgbAt($img, int $x, int $y): array
    {
        $c = imagecolorat($img, $x, $y);
        return [
            'r' => ($c >> 16) & 0xFF,
            'g' => ($c >> 8) & 0xFF,
            'b' => $c & 0xFF,
        ];
    }

    /** @param array{r: int, g: int, b: int} $a @param array{r: int, g: int, b: int} $b */
    private static function colorNear(array $a, array $b, int $tolerance): bool
    {
        return abs($a['r'] - $b['r']) <= $tolerance
            && abs($a['g'] - $b['g']) <= $tolerance
            && abs($a['b'] - $b['b']) <= $tolerance;
    }
}
