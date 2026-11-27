<?php
declare(strict_types=1);

/**
 * Die Palette des Hubs (Markenfarben, Emoji-Gruppen) für die FolienSchmiede (3.9.2; Regel Urs 09.11.2026 «Markenfarben und Emojis: ein
 * Standard»: eine Quelle im Hub, keine App führt eine eigene Liste). Muster OfficeForge `HubIntegrations::palette()`.
 *
 * Signierter App-Aufruf `GET api/palette.php` (HubApp::hubGet, App-Schlüssel, kein Token), 10 Minuten in `data/cache/hub-palette.json`;
 * bei Hub-Ausfall weiter die (veraltete) Kopie, nach einem Fehlversuch 2 Minuten Ruhe. Leer = unbekannt: `HubColorPicker`
 * nimmt dann seine eingebaute Liste.
 */
final class SfPalette
{
    /** @return array{brandColors?:list<array<string,mixed>>,emojiGroups?:list<array<string,mixed>>} */
    public static function get(): array
    {
        static $memo = null;
        if (is_array($memo)) {
            return $memo;
        }
        $memo = [];
        $dir = EXPORT_CACHE_PATH;
        $file = $dir . '/hub-palette.json';
        $fail = $dir . '/hub-palette.fail';
        $j = is_readable($file) ? json_decode((string)@file_get_contents($file), true) : null;
        $cached = is_array($j) ? $j : [];
        $fresh = $cached !== [] && (time() - (int)@filemtime($file)) < 600;
        $pause = is_file($fail) && (time() - (int)@filemtime($fail)) < 120;
        if (!$fresh && !$pause) {
            $r = HubApp::hubGet('/api/palette.php', 6);
            $p = is_array($r['json'] ?? null) ? $r['json'] : [];
            if (!empty($r['ok']) && is_array($p['brandColors'] ?? null)) {
                $cached = ['brandColors' => $p['brandColors'], 'emojiGroups' => is_array($p['emojiGroups'] ?? null) ? $p['emojiGroups'] : []];
                if (@file_put_contents($file . '.tmp', json_encode($cached, JSON_UNESCAPED_UNICODE)) !== false) {
                    @rename($file . '.tmp', $file);
                }
                @unlink($fail);
            } else {
                @touch($fail);
            }
        }
        foreach (['brandColors', 'emojiGroups'] as $k) {
            if (is_array($cached[$k] ?? null) && $cached[$k] !== []) {
                $memo[$k] = $cached[$k];
            }
        }
        return $memo;
    }

    /** Markenfarben ohne Zeilenumbrüche der Palette, nur gültige Farben. @return list<array{name:string,hex:string}> */
    public static function brandColors(): array
    {
        $out = [];
        foreach (self::get()['brandColors'] ?? [] as $c) {
            if (!is_array($c) || !empty($c['break'])) {
                continue;
            }
            $hex = strtolower(trim((string)($c['hex'] ?? '')));
            if (preg_match('/^#[0-9a-f]{6}$/', $hex) === 1) {
                $out[] = ['name' => (string)($c['name'] ?? $hex), 'hex' => $hex];
            }
        }
        return $out;
    }

    /** Für die Seite: `<script type="application/json" id="hub-palette">` (liest HubColorPicker/HubEmojiPalette). */
    public static function scriptTag(): string
    {
        $p = self::get();
        if ($p === []) {
            return '';
        }
        return '<script type="application/json" id="hub-palette">'
            . json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
