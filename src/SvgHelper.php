<?php
/**
 * SVG-Hilfsfunktionen (Icon-Einfärbung).
 */
class SvgHelper
{
    /**
     * Entfernt aus einer SVG-Datei alles, was im Browser Code ausführen oder
     * nach aussen laden kann. Nötig, weil asset.php SVG als image/svg+xml auf
     * derselben Domain ausliefert: ein <script> darin liefe mit der Sitzung
     * jedes Betrachters, auch über öffentliche Links.
     *
     * @return string|null bereinigtes SVG, oder null wenn es kein brauchbares SVG ist
     */
    public static function sanitize(string $svg): ?string
    {
        if (stripos($svg, '<svg') === false) {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        // Externe Entitäten nicht auflösen (XXE).
        $ok = $doc->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }

        // Dokumenttyp-Deklarationen entfernen (Entity-Definitionen).
        while ($doc->doctype !== null && $doc->doctype->parentNode !== null) {
            $doc->doctype->parentNode->removeChild($doc->doctype);
        }

        $verboten = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'handler', 'set', 'animate'];
        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $el) {
            if (!($el instanceof DOMElement)) {
                continue;
            }
            if (in_array(strtolower($el->localName), $verboten, true)) {
                $el->parentNode?->removeChild($el);
                continue;
            }
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $name = strtolower($attr->nodeName);
                $value = trim((string)$attr->nodeValue);
                $flat = strtolower(preg_replace('/\s+/', '', $value) ?? $value);

                // Ereignis-Attribute (onload, onclick, …)
                if (str_starts_with($name, 'on')) {
                    $el->removeAttributeNode($attr);
                    continue;
                }
                // javascript:/vbscript:/data:text-URLs in beliebigen Attributen
                if (str_contains($flat, 'javascript:') || str_contains($flat, 'vbscript:')
                    || str_contains($flat, 'data:text/html')) {
                    $el->removeAttributeNode($attr);
                    continue;
                }
                // Verweise nur auf Fragmente (#id) oder eingebettete Bilddaten
                if (in_array($name, ['href', 'xlink:href'], true)) {
                    $erlaubt = str_starts_with($value, '#')
                        || preg_match('#^data:image/(png|jpeg|gif|webp);base64,#i', $value) === 1;
                    if (!$erlaubt) {
                        $el->removeAttributeNode($attr);
                    }
                    continue;
                }
                // @import in eingebettetem CSS
                if ($name === 'style' && str_contains($flat, '@import')) {
                    $el->removeAttributeNode($attr);
                }
            }
        }

        // <style>-Inhalte mit @import oder url() nach aussen entschärfen
        foreach (iterator_to_array($doc->getElementsByTagName('style')) as $styleEl) {
            $css = (string)$styleEl->textContent;
            if (stripos($css, '@import') !== false || preg_match('#url\(\s*[\'"]?(?!data:image/)[a-z]+:#i', $css) === 1) {
                $styleEl->parentNode?->removeChild($styleEl);
            }
        }

        $out = $doc->saveXML();
        return is_string($out) && stripos($out, '<svg') !== false ? $out : null;
    }
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
