<?php

/**
 * Die Programm-Leiste (App-Dock) im Kopf der Seite.
 *
 * **Diese Klasse entscheidet nichts.** Welche Apps eine Person sieht, sagt der Hub
 * (`HubDock` → `GET /api/dock.php`); hier wird die Liste nur gezeichnet. Vorher stand hier
 * eine eigene Auslegung von `modules.json` — sie kannte von den fuenf Wegen des Hubs
 * (persoenliche App, Administrator, `allowUsers`, `allowGroups`, `requireAnyGroup`) nur den
 * letzten, und was Urs in der Hub-Oberflaeche freigab, waere hier unsichtbar geblieben.
 *
 * Regel Urs 19.10.2026: «ACL's werden ueber die User definiert und stehen nicht fest im Code.
 * Wer was sehen darf, wird von Menschen! gesagt und nicht von der Maschine.»
 *
 * Ohne Auskunft des Hubs zeigt die Leiste **nur das Hub-Zeichen** — nie «vorsichtshalber
 * alles». Welche Lage zu welcher Anzeige fuehrt, steht in `HubDock`.
 */
class Launcher
{
    public static function render(string $currentModuleId, string $variant = 'topbar'): void
    {
        $dock = class_exists('HubDock') ? HubDock::current() : null;
        $variantClass = $variant === 'shell' ? 'cf-launcher--shell' : 'cf-launcher--topbar';

        echo '<nav class="cf-launcher ' . $variantClass . '" aria-label="ChurchForge Programme">';
        echo '<div class="cf-launcher-dock" role="list" data-hub-fav-dock>';

        echo self::hubEintrag($dock, $currentModuleId === 'hub');

        foreach (($dock['items'] ?? []) as $item) {
            $id = (string)$item['id'];
            $label = (string)($item['label'] ?? $id);
            $attrs = 'class="cf-launcher-item' . ($id === $currentModuleId ? ' is-active' : '') . '"';
            $attrs .= ' href="' . self::h((string)$item['href']) . '" role="listitem"';
            $attrs .= ' title="' . self::h($label) . '"';
            $attrs .= ' data-module-id="' . self::h($id) . '"';
            if (!empty($item['newTab'])) {
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }
            echo '<a ' . $attrs . '>';
            echo self::zeichen((string)($item['iconUrl'] ?? ''));
            echo '<span class="cf-launcher-label">' . self::h((string)($item['shortLabel'] ?? $label)) . '</span>';
            echo '</a>';
        }

        echo '</div></nav>';
    }

    /** @param array<string,mixed>|null $dock */
    private static function hubEintrag(?array $dock, bool $aktiv): string
    {
        $hub = is_array($dock['hub'] ?? null) ? $dock['hub'] : [];
        $href = trim((string)($hub['href'] ?? ''));
        if ($href === '') {
            $href = rtrim(churchforge_hub_url(), '/') . '/';
        }
        $icon = trim((string)($hub['iconUrl'] ?? ''));
        if ($icon === '') {
            $icon = class_exists('ModuleIcon')
                ? ModuleIcon::hubUrl()
                : rtrim(churchforge_hub_url(), '/') . '/assets/icons/hub.svg';
        }
        $label = trim((string)($hub['label'] ?? '')) !== '' ? (string)$hub['label'] : 'Hub';

        return '<a class="cf-launcher-item' . ($aktiv ? ' is-active' : '') . '"'
            . ' href="' . self::h($href) . '" role="listitem" title="GemeindeSchmiede"'
            . ' data-hub-home draggable="false">'
            . self::zeichen($icon)
            . '<span class="cf-launcher-label">' . self::h($label) . '</span>'
            . '</a>';
    }

    private static function zeichen(string $url): string
    {
        if ($url === '') {
            return '<span class="cf-launcher-icon" aria-hidden="true"></span>';
        }
        return '<span class="cf-launcher-icon cf-icon-image" aria-hidden="true"><img src="'
            . self::h($url) . '" alt="" loading="lazy" referrerpolicy="no-referrer" decoding="async"></span>';
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
