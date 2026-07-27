<?php

class Launcher
{
    public static function render(string $currentModuleId, array $userTags, string $locale = 'de'): void
    {
        $items = self::modulesForUser($userTags, $locale);
        $hubUrl = churchforge_hub_url();

        echo '<nav class="cf-launcher cf-launcher--topbar" aria-label="ChurchForge Programme">';
        echo '<div class="cf-launcher-dock" role="list">';

        $hubActive = $currentModuleId === 'hub';
        echo '<a class="cf-launcher-item' . ($hubActive ? ' is-active' : '') . '" href="' . self::h($hubUrl . '/') . '" role="listitem" title="GemeindeSchmiede">';
        echo '<span class="cf-launcher-icon cf-icon-image" aria-hidden="true"><img src="' . self::h(ModuleIcon::hubUrl()) . '" alt="" loading="lazy" decoding="async"></span>';
        echo '<span class="cf-launcher-label">Hub</span>';
        echo '</a>';

        foreach ($items as $module) {
            $id = (string)($module['id'] ?? '');
            $active = $id === $currentModuleId;
            $href = (string)($module['href'] ?? '#');
            $label = (string)($module['label'] ?? $id);
            $external = !empty($module['external']);
            $icon = (string)($module['icon_key'] ?? 'default');

            $attrs = 'class="cf-launcher-item' . ($active ? ' is-active' : '') . '"';
            $attrs .= ' href="' . self::h($href) . '" role="listitem" title="' . self::h($label) . '"';
            if ($external && $id !== 'churchtools') {
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }

            echo '<a ' . $attrs . '>';
            echo ModuleIcon::launcherMarkup($module);
            echo '<span class="cf-launcher-label">' . self::h(self::shortLabel($label)) . '</span>';
            echo '</a>';
        }

        echo '</div></nav>';
    }

    /** @param list<string> $userTags
     *  @return list<array<string,mixed>>
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
            $visible[] = $module;
        }
        usort($visible, static fn($a, $b) => strcmp((string)($a['sort'] ?? $a['id']), (string)($b['sort'] ?? $b['id'])));
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

    /** @param array<string,mixed> $module */
    private static function moduleHref(array $module): string
    {
        if ((string)($module['id'] ?? '') === 'churchtools') {
            return churchforge_hub_url() . '/app/churchtools.php';
        }
        return (string)($module['url'] ?? '#');
    }

    /** @param array<string,mixed> $module
     *  @param list<string> $userTags
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
        $groups = $module['requireAnyGroup'] ?? null;
        if (!is_array($groups) || $groups === []) {
            return false;
        }
        return self::hasAnyTag($userTags, $groups);
    }

    /** @param list<string> $userTags
     *  @param list<string> $required
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

    private static function shortLabel(string $label): string
    {
        return preg_replace('/Schmiede$/', '', $label) ?? $label;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
