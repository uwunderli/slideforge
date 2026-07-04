<?php
/**
 * SVG-Hilfsfunktionen (Icon-Einfärbung).
 */
class SvgHelper
{
    public static function normalizeHex(string $color): ?string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m)) {
            $hex = strtolower($m[1]);
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            return '#' . $hex;
        }
        return null;
    }

    public static function tintSvg(string $svg, string $color): string
    {
        $hex = self::normalizeHex($color);
        if ($hex === null || stripos($svg, '<svg') === false) {
            return $svg;
        }

        $svg = preg_replace('/currentColor/i', $hex, $svg) ?? $svg;

        $replacements = [
            '/\bfill="#000000"/i' => 'fill="' . $hex . '"',
            '/\bfill="#000"/i' => 'fill="' . $hex . '"',
            '/\bfill="black"/i' => 'fill="' . $hex . '"',
            '/\bstroke="#000000"/i' => 'stroke="' . $hex . '"',
            '/\bstroke="#000"/i' => 'stroke="' . $hex . '"',
            '/\bstroke="black"/i' => 'stroke="' . $hex . '"',
        ];
        foreach ($replacements as $pattern => $replacement) {
            $svg = preg_replace($pattern, $replacement, $svg) ?? $svg;
        }

        // Icons ohne explizite fill-Attribute: fill am root-svg setzen
        if (!preg_match('/\bfill="/i', $svg)) {
            $svg = preg_replace('/<svg\b/i', '<svg fill="' . $hex . '"', $svg, 1) ?? $svg;
        }

        return $svg;
    }
}
