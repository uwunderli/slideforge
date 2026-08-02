<?php

class Launcher
{
    public static function render(string $currentModuleId, array $userTags, string $locale = 'de', string $variant = 'topbar'): void
    {
        $items = self::modulesForUser($userTags, $locale);
        $hubUrl = churchforge_hub_url();
        $variantClass = $variant === 'shell' ? 'cf-launcher--shell' : 'cf-launcher--topbar';

        echo '<nav class="cf-launcher ' . $variantClass . '" aria-label="ChurchForge Programme">';
        echo '<div class="cf-launcher-dock" role="list">';

        $hubActive = $currentModuleId === 'hub';
        echo '<a class="cf-launcher-item' . ($hubActive ? ' is-active' : '') . '" href="' . self::h($hubUrl . '/') . '" role="listitem" title="GemeindeSchmiede">';
        echo self::hubIconMarkup();
        echo '<span class="cf-launcher-label">Hub</span>';
        echo '</a>';

        foreach ($items as $module) {
            $id = (string)($module['id'] ?? '');
            $active = $id === $currentModuleId;
            $href = (string)($module['href'] ?? '#');
            $label = (string)($module['label'] ?? $id);

            $attrs = 'class="cf-launcher-item' . ($active ? ' is-active' : '') . '"';
            $attrs .= ' href="' . self::h($href) . '" role="listitem" title="' . self::h($label) . '"';
            if (self::opensInNewTab($module)) {
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }

            echo '<a ' . $attrs . '>';
            echo self::moduleIconMarkup($module);
            echo '<span class="cf-launcher-label">' . self::h(self::shortLabel($label)) . '</span>';
            echo '</a>';
        }

        echo '</div></nav>';
    }

    /** @param array<string,mixed> $module */
    public static function opensInNewTab(array $module): bool
    {
        if (!empty($module['openInNewTab']) || !empty($module['open_external'])) {
            return true;
        }
        $id = (string)($module['id'] ?? '');
        $type = (string)($module['integrationType'] ?? '');
        if ($type === '' && class_exists('IntegrationStore')) {
            $type = IntegrationStore::typeOf($id);
        }
        return in_array($type, ['groupoffice', 'nextcloud'], true);
    }

    /**
     * @param list<string> $userTags
     * @return list<array<string,mixed>>
     */
    public static function modulesForUser(array $userTags, string $locale = 'de'): array
    {
        $modules = self::loadModules();
        $visible = [];

        foreach ($modules as $module) {
            if (!empty($module['hidden']) || !empty($module['comingSoon'])) {
                continue;
            }
            if (!self::userMaySee($module, $userTags)) {
                continue;
            }
            $labels = is_array($module['labels'] ?? null) ? $module['labels'] : [];
            $module['label'] = (string)($labels[$locale] ?? $labels['de'] ?? $module['id']);
            $module['href'] = self::moduleHref($module);
            $module['icon_key'] = (string)($module['icon'] ?? 'default');
            $module['external'] = !empty($module['external']);
            $module['open_external'] = self::opensInNewTab($module);
            $lookup = $module;
            unset($lookup['icon_url']);
            $module['icon_url'] = class_exists('ModuleIcon') ? ModuleIcon::resolveUrl($lookup) : self::fallbackIconUrl($lookup);
            $visible[] = $module;
        }

        $favorites = class_exists('UserPrefs') ? UserPrefs::favorites() : [];
        usort($visible, static function ($a, $b) use ($favorites) {
            $af = in_array((string)($a['id'] ?? ''), $favorites, true) ? 0 : 1;
            $bf = in_array((string)($b['id'] ?? ''), $favorites, true) ? 0 : 1;
            if ($af !== $bf) {
                return $af <=> $bf;
            }
            return strcmp((string)($a['sort'] ?? $a['id']), (string)($b['sort'] ?? $b['id']));
        });
        return $visible;
    }

    /** @return list<array<string,mixed>> */
    private static function loadModules(): array
    {
        $file = churchforge_modules_file();
        if (!is_readable($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true);
        $modules = $data['modules'] ?? [];
        return is_array($modules) ? $modules : [];
    }

    /**
     * @param array<string,mixed> $module
     * @param list<string> $userTags
     */
    private static function userMaySee(array $module, array $userTags): bool
    {
        if (class_exists('ModuleAccess')) {
            return ModuleAccess::userMaySee($module, $userTags);
        }
        if (!empty($module['managed']) && empty($module['shared'])) {
            $owner = (string)($module['owner'] ?? '');
            $key = class_exists('UserPrefs') ? UserPrefs::userKey() : '';
            return $key !== '' && $owner === $key;
        }
        if (class_exists('HubAuth') && HubAuth::isAdmin()) {
            return true;
        }
        // Admin-Tag aus SharedAuth-Cookie (ohne HubAuth in FolienSchmiede)
        if (self::hasAnyTag($userTags, ['Admin'])) {
            return true;
        }
        // Leere requireAnyGroup = für Hub-Nutzer sichtbar; sonst Gruppen-Match
        $groups = $module['requireAnyGroup'] ?? null;
        if (!is_array($groups) || $groups === []) {
            return true;
        }
        return self::hasAnyTag($userTags, $groups);
    }

    /**
     * @param list<string> $userTags
     * @param list<string> $required
     */
    private static function hasAnyTag(array $userTags, array $required): bool
    {
        $normalized = array_map(static fn($t) => mb_strtolower(trim((string)$t)), $userTags);
        foreach ($required as $tag) {
            if (in_array(mb_strtolower(trim((string)$tag)), $normalized, true)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $module */
    private static function moduleHref(array $module): string
    {
        $id = (string)($module['id'] ?? '');
        $type = (string)($module['integrationType'] ?? '');
        if ($type === '' && class_exists('IntegrationStore')) {
            $type = IntegrationStore::typeOf($id);
        }
        if ($type === 'churchtools' || $id === 'churchtools') {
            if (class_exists('ChurchToolsSso')) {
                $masterId = class_exists('HubConfig') ? HubConfig::masterInstanceId() : 'churchtools';
                if ($id === $masterId || $id === 'churchtools') {
                    return churchforge_hub_url() . ChurchToolsSso::shellUrl();
                }
            }
            return churchforge_hub_url() . '/app/embed.php?m=' . rawurlencode($id);
        }
        if (!empty($module['external']) && empty($module['openInNewTab'])) {
            if (in_array($type, ['groupoffice', 'nextcloud'], true)) {
                return churchforge_hub_url() . '/app/bridge.php?m=' . rawurlencode($id);
            }
            return churchforge_hub_url() . '/app/embed.php?m=' . rawurlencode($id);
        }

        return (string)($module['url'] ?? '#');
    }

    private static function hubIconMarkup(): string
    {
        if (class_exists('ModuleIcon')) {
            return '<span class="cf-launcher-icon cf-icon-image" aria-hidden="true"><img src="'
                . self::h(ModuleIcon::hubUrl()) . '" alt="" loading="lazy" decoding="async"></span>';
        }
        $url = rtrim(churchforge_hub_url(), '/') . '/assets/icons/hub.svg';
        return '<span class="cf-launcher-icon cf-icon-image" aria-hidden="true"><img src="'
            . self::h($url) . '" alt="" loading="lazy" decoding="async"></span>';
    }

    /** @param array<string,mixed> $module */
    private static function moduleIconMarkup(array $module): string
    {
        if (class_exists('ModuleIcon')) {
            return ModuleIcon::launcherMarkup($module);
        }
        $url = self::fallbackIconUrl($module);
        return '<span class="cf-launcher-icon cf-icon-image" aria-hidden="true"><img src="'
            . self::h($url) . '" alt="" loading="lazy" referrerpolicy="no-referrer" decoding="async"></span>';
    }

    /** @param array<string,mixed> $module */
    private static function fallbackIconUrl(array $module): string
    {
        $explicit = trim((string)($module['iconUrl'] ?? ''));
        if ($explicit !== '') {
            if (str_starts_with($explicit, 'http://') || str_starts_with($explicit, 'https://')) {
                return $explicit;
            }
            return rtrim(churchforge_hub_url(), '/') . $explicit;
        }
        $key = trim((string)($module['icon'] ?? $module['id'] ?? 'default'));
        if ($key === '') {
            $key = 'default';
        }
        return rtrim(churchforge_hub_url(), '/') . '/assets/icons/' . rawurlencode($key) . '.svg';
    }

    private static function shortLabel(string $label): string
    {
        return preg_replace('/Schmiede$/', '', $label) ?? $label;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
