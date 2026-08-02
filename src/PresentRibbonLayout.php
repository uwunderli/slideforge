<?php

class PresentRibbonLayout
{
    private const USER_KEY = 'present_ribbon_layout';

    /** @param array<string,bool> $flags */
    public static function buildContext(array $flags): array
    {
        return [
            'canBroadcast' => !empty($flags['canBroadcast']),
            'isOwner' => !empty($flags['isOwner']),
            'hasRemote' => !empty($flags['hasRemote']),
        ];
    }

    /**
     * Kontext beim Speichern: voller Katalog, nichts wegfiltern.
     *
     * @return array<string,bool>
     */
    public static function persistContext(): array
    {
        return [
            'canBroadcast' => true,
            'isOwner' => true,
            'hasRemote' => true,
        ];
    }

    /** @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>} */
    public static function defaultLayout(): array
    {
        require_once BASE_PATH . '/config/present_ribbon_default_layout.php';
        return present_ribbon_default_layout();
    }

    /** @return list<array<string,mixed>> */
    public static function catalog(): array
    {
        require_once BASE_PATH . '/config/present_ribbon_commands.php';
        return present_ribbon_command_catalog();
    }

    /**
     * @param array<string,bool> $context
     * @return list<array<string,mixed>>
     */
    public static function availableCatalog(array $context): array
    {
        $out = [];
        foreach (self::catalog() as $cmd) {
            if (self::isVisible($cmd, $context)) {
                $out[] = $cmd;
            }
        }
        return $out;
    }

    /**
     * @param array<string,bool> $context
     * @return array<string,array<string,mixed>>
     */
    public static function catalogIndex(array $context): array
    {
        $index = [];
        foreach (self::availableCatalog($context) as $cmd) {
            $index[(string)$cmd['id']] = $cmd;
        }
        $index['separator'] = ['id' => 'separator', 'kind' => 'separator', 'category' => 'layout', 'labelKey' => 'ribbon.separator'];
        $index['row_separator'] = ['id' => 'row_separator', 'kind' => 'row_separator', 'category' => 'layout', 'labelKey' => 'ribbon.row_separator'];
        return $index;
    }

    /**
     * @param array<string,mixed> $cmd
     * @param array<string,bool> $context
     */
    private static function isVisible(array $cmd, array $context): bool
    {
        $when = $cmd['visibleWhen'] ?? null;
        if (!is_array($when) || $when === []) {
            return true;
        }
        foreach ($when as $flag) {
            if (empty($context[(string)$flag])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,bool> $context
     * @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>,customized?:bool}
     */
    public static function getLayout(string $userId, array $context): array
    {
        $user = Auth::findById($userId);
        $stored = is_array($user[self::USER_KEY] ?? null) ? $user[self::USER_KEY] : null;
        $layout = self::sanitizeLayout($stored ?? self::defaultLayout(), $context);
        $layout['customized'] = $stored !== null;
        if ($stored !== null) {
            $forPersist = self::sanitizeLayout($stored, self::persistContext());
            $persisted = self::stripRuntimeKeys($forPersist);
            if (self::layoutItemsSignature($stored) !== self::layoutItemsSignature($persisted)) {
                self::saveLayout($userId, $persisted);
            }
        }
        return $layout;
    }

    /** @param array<string,mixed> $layout */
    private static function layoutItemsSignature(array $layout): string
    {
        $sig = [];
        foreach ($layout['tabs'] ?? [] as $tab) {
            $tid = (string)($tab['id'] ?? '');
            foreach ($tab['groups'] ?? [] as $group) {
                $gid = (string)($group['id'] ?? '');
                foreach ($group['items'] ?? [] as $item) {
                    $sig[] = $tid . '/' . $gid . '/' . (is_array($item) ? (string)($item['id'] ?? '') : (string)$item);
                }
            }
        }
        return implode('|', $sig);
    }

    /**
     * @param array<string,mixed> $layout
     * @param array<string,bool> $context
     * @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>}
     */
    public static function sanitizeLayout(array $layout, array $context): array
    {
        $catalog = self::catalogIndex($context);
        $version = (int)($layout['version'] ?? 1);
        if ($version !== 1 || !isset($layout['tabs']) || !is_array($layout['tabs'])) {
            $layout = self::defaultLayout();
        }

        $legacyIds = [
            'remote_qr' => 'widget:remote',
            'settings_display' => 'panel_ghost',
        ];
        $legacyExpand = [
            'settings_panels' => ['panel_next', 'panel_clock', 'panel_timer', 'panel_timebar', 'panel_media', 'panel_slides', 'panel_ghost', 'panel_laser'],
        ];

        $tabs = [];
        foreach (($layout['tabs'] ?? []) as $tab) {
            if (!is_array($tab) || empty($tab['id'])) {
                continue;
            }
            $tabLabel = trim((string)($tab['label'] ?? ''));
            $tabLabelKey = trim((string)($tab['labelKey'] ?? ''));
            if ($tabLabel === '' && $tabLabelKey === '') {
                continue;
            }
            /* Tab «settings»: Anzeigename Ansicht (nicht mehr Einstellungen). */
            if ((string)$tab['id'] === 'settings') {
                if ($tabLabelKey === '' || $tabLabelKey === 'editor.settings_menu') {
                    $tabLabelKey = 'ribbon.tab_view';
                }
                if ($tabLabel !== '' && ($tabLabel === t('editor.settings_menu') || $tabLabel === t('ribbon.tab_view'))) {
                    $tabLabel = '';
                }
            }

            $groups = [];
            foreach (($tab['groups'] ?? []) as $group) {
                if (!is_array($group) || empty($group['id'])) {
                    continue;
                }
                $groupLabel = trim((string)($group['label'] ?? ''));
                $groupLabelKey = trim((string)($group['labelKey'] ?? ''));
                if ($groupLabel === '' && $groupLabelKey === '') {
                    continue;
                }

                $items = [];
                foreach (($group['items'] ?? []) as $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($id !== '' && isset($legacyIds[$id])) {
                        $id = $legacyIds[$id];
                    }
                    if ($id !== '' && isset($legacyExpand[$id])) {
                        foreach ($legacyExpand[$id] as $expId) {
                            if (!isset($catalog[$expId])) {
                                continue;
                            }
                            $items[] = ['id' => $expId];
                        }
                        continue;
                    }
                    if ($id === '' || !isset($catalog[$id])) {
                        continue;
                    }
                    $entry = ['id' => $id];
                    if (is_array($item)) {
                        if (!empty($item['instanceId'])) {
                            $entry['instanceId'] = (string)$item['instanceId'];
                        }
                        if (!empty($item['gridSpan']) && is_array($item['gridSpan'])) {
                            $entry['gridSpan'] = [
                                'cols' => max(1, min(16, (int)($item['gridSpan']['cols'] ?? 1))),
                                'rows' => max(1, min(2, (int)($item['gridSpan']['rows'] ?? 1))),
                            ];
                        }
                        if (array_key_exists('showLabel', $item) && empty($item['showLabel'])) {
                            $entry['showLabel'] = false;
                        }
                    }
                    $items[] = $entry;
                }
                if ($items === []) {
                    continue;
                }
                $groupOut = [
                    'id' => (string)$group['id'],
                    'labelKey' => $groupLabelKey,
                    'label' => $groupLabel,
                    'items' => $items,
                ];
                if (!empty($group['rows']) && (int)$group['rows'] === 1) {
                    $groupOut['rows'] = 1;
                }
                $groups[] = $groupOut;
            }
            if ($groups === []) {
                continue;
            }
            $tabs[] = [
                'id' => (string)$tab['id'],
                'labelKey' => $tabLabelKey,
                'label' => $tabLabel,
                'groups' => $groups,
            ];
        }

        if ($tabs === []) {
            $settingsOnly = self::defaultLayout();
            $settingsOnly['tabs'] = array_values(array_filter(
                $settingsOnly['tabs'],
                static fn($t) => (string)($t['id'] ?? '') === 'settings'
            ));
            $settingsOnly['prefs']['activeTab'] = 'settings';
            return self::sanitizeLayout($settingsOnly, $context);
        }

        $prefs = is_array($layout['prefs'] ?? null) ? $layout['prefs'] : [];
        $active = (string)($prefs['activeTab'] ?? '');
        $tabIds = array_map(static fn($t) => (string)$t['id'], $tabs);
        if ($active === '' || !in_array($active, $tabIds, true)) {
            $prefs['activeTab'] = $tabIds[0];
        }

        return self::ensurePanelCommands([
            'version' => 1,
            'tabs' => $tabs,
            'prefs' => [
                'activeTab' => (string)$prefs['activeTab'],
                'collapsed' => !empty($prefs['collapsed']),
                'iconSize' => in_array(($prefs['iconSize'] ?? ''), ['small', 'medium', 'large'], true)
                    ? (string)$prefs['iconSize'] : 'medium',
                'showLabels' => ($prefs['showLabels'] ?? true) !== false,
            ],
        ], $catalog);
    }

    /**
     * Fehlende Panel-Icons in gespeicherten Layouts nachziehen.
     *
     * @param array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>} $layout
     * @param array<string,array<string,mixed>> $catalog
     * @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>}
     */
    private static function ensurePanelCommands(array $layout, array $catalog): array
    {
        $insertAfter = [
            'panel_timebar' => 'panel_timer',
            'panel_media' => 'panel_timebar',
            'panel_ghost' => 'panel_slides',
            'panel_laser' => 'panel_ghost',
            'settings_notes' => 'settings_laser',
        ];
        foreach ($insertAfter as $newId => $afterId) {
            if (!isset($catalog[$newId])) {
                continue;
            }
            $layout = self::insertCommandAfter($layout, $newId, $afterId);
        }
        /* Fortschritt/Navigation: aus Audience-Widget → eigene Befehle */
        if (isset($catalog['show_progress'])) {
            $layout = self::insertCommandBefore($layout, 'show_progress', 'widget:audience');
            if (!self::layoutHasCommand($layout, 'show_progress')) {
                $layout = self::insertCommandAfter($layout, 'show_progress', 'back_to_editor');
            }
        }
        if (isset($catalog['show_controls'])) {
            $layout = self::insertCommandAfter($layout, 'show_controls', 'show_progress');
            if (!self::layoutHasCommand($layout, 'show_controls')) {
                $layout = self::insertCommandBefore($layout, 'show_controls', 'widget:audience');
            }
        }
        return $layout;
    }

    /**
     * @param array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>} $layout
     */
    private static function layoutHasCommand(array $layout, string $cmdId): bool
    {
        foreach ($layout['tabs'] as $tab) {
            if (!is_array($tab['groups'] ?? null)) {
                continue;
            }
            foreach ($tab['groups'] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($id === $cmdId) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>} $layout
     * @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>}
     */
    private static function insertCommandBefore(array $layout, string $newId, string $beforeId): array
    {
        if (self::layoutHasCommand($layout, $newId)) {
            return $layout;
        }

        foreach ($layout['tabs'] as &$tab) {
            if (!is_array($tab['groups'] ?? null)) {
                continue;
            }
            foreach ($tab['groups'] as &$group) {
                $items = $group['items'] ?? [];
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $i => $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($id !== $beforeId) {
                        continue;
                    }
                    array_splice($items, $i, 0, [['id' => $newId]]);
                    $group['items'] = $items;
                    unset($group, $tab);
                    return $layout;
                }
            }
        }
        unset($group, $tab);
        return $layout;
    }

    /**
     * @param array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>} $layout
     * @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>}
     */
    private static function insertCommandAfter(array $layout, string $newId, string $afterId): array
    {
        foreach ($layout['tabs'] as $tab) {
            if (!is_array($tab['groups'] ?? null)) {
                continue;
            }
            foreach ($tab['groups'] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($id === $newId) {
                        return $layout;
                    }
                }
            }
        }

        foreach ($layout['tabs'] as &$tab) {
            if (!is_array($tab['groups'] ?? null)) {
                continue;
            }
            foreach ($tab['groups'] as &$group) {
                $items = $group['items'] ?? [];
                if (!is_array($items)) {
                    continue;
                }
                $afterIdx = null;
                foreach ($items as $i => $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($id === $afterId) {
                        $afterIdx = $i;
                    }
                }
                if ($afterIdx === null) {
                    continue;
                }
                array_splice($items, $afterIdx + 1, 0, [['id' => $newId]]);
                $group['items'] = $items;
                unset($group, $tab);
                return $layout;
            }
            unset($group);
        }
        unset($tab);
        return $layout;
    }

    /** @param array<string,mixed> $layout */
    public static function saveLayout(string $userId, array $layout): array
    {
        $layout = self::stripRuntimeKeys($layout);
        Storage::update(USERS_FILE, function (array $users) use ($userId, $layout) {
            foreach ($users as &$u) {
                if (($u['id'] ?? '') === $userId) {
                    $u[self::USER_KEY] = $layout;
                }
            }
            unset($u);
            return $users;
        }, []);
        return $layout;
    }

    public static function resetLayout(string $userId): void
    {
        Storage::update(USERS_FILE, function (array $users) use ($userId) {
            foreach ($users as &$u) {
                if (($u['id'] ?? '') === $userId) {
                    unset($u[self::USER_KEY]);
                }
            }
            unset($u);
            return $users;
        }, []);
    }

    /** @param array<string,mixed> $layout @return array<string,mixed> */
    private static function stripRuntimeKeys(array $layout): array
    {
        unset($layout['customized']);
        return [
            'version' => 1,
            'tabs' => $layout['tabs'] ?? [],
            'prefs' => [
                'activeTab' => (string)($layout['prefs']['activeTab'] ?? 'present'),
                'collapsed' => !empty($layout['prefs']['collapsed']),
                'iconSize' => in_array($layout['prefs']['iconSize'] ?? '', ['small', 'medium', 'large'], true)
                    ? (string)$layout['prefs']['iconSize']
                    : 'medium',
                'showLabels' => !array_key_exists('showLabels', $layout['prefs'] ?? [])
                    || !empty($layout['prefs']['showLabels']),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $layout
     * @param array<string,bool> $context
     * @return array<string,mixed>
     */
    public static function layoutForClient(array $layout, array $context): array
    {
        $layout = self::sanitizeLayout($layout, $context);
        $catalog = self::catalogIndex($context);
        $tabs = [];
        foreach ($layout['tabs'] as $tab) {
            $tabLabel = trim((string)($tab['label'] ?? ''));
            if ($tabLabel === '' && !empty($tab['labelKey'])) {
                $tabLabel = t((string)$tab['labelKey']);
            }
            $groups = [];
            foreach ($tab['groups'] as $group) {
                $groupLabel = trim((string)($group['label'] ?? ''));
                if ($groupLabel === '' && !empty($group['labelKey'])) {
                    $groupLabel = t((string)$group['labelKey']);
                }
                $items = [];
                foreach ($group['items'] as $item) {
                    $id = (string)($item['id'] ?? '');
                    if ($id === '' || !isset($catalog[$id])) {
                        continue;
                    }
                    $clientItem = ['id' => $id];
                    $labelKey = (string)($catalog[$id]['labelKey'] ?? $id);
                    if ($labelKey !== '') {
                        $clientItem['label'] = t($labelKey);
                    }
                    if (!empty($item['instanceId'])) {
                        $clientItem['instanceId'] = (string)$item['instanceId'];
                    }
                    if (!empty($item['gridSpan']) && is_array($item['gridSpan'])) {
                        $clientItem['gridSpan'] = [
                            'cols' => max(1, min(16, (int)($item['gridSpan']['cols'] ?? 1))),
                            'rows' => max(1, min(2, (int)($item['gridSpan']['rows'] ?? 1))),
                        ];
                    }
                    if (array_key_exists('showLabel', $item) && empty($item['showLabel'])) {
                        $clientItem['showLabel'] = false;
                    }
                    $items[] = $clientItem;
                }
                if ($items === []) {
                    continue;
                }
                $groupOut = [
                    'id' => (string)$group['id'],
                    'label' => $groupLabel !== '' ? $groupLabel : (string)$group['id'],
                    'items' => $items,
                ];
                if (!empty($group['rows']) && (int)$group['rows'] === 1) {
                    $groupOut['rows'] = 1;
                }
                $groups[] = $groupOut;
            }
            if ($groups === []) {
                continue;
            }
            $tabs[] = [
                'id' => (string)$tab['id'],
                'label' => $tabLabel !== '' ? $tabLabel : (string)$tab['id'],
                'groups' => $groups,
            ];
        }
        return [
            'version' => 1,
            'tabs' => $tabs,
            'prefs' => $layout['prefs'],
        ];
    }

    /**
     * @param array<string,bool> $context
     * @return list<array<string,mixed>>
     */
    public static function catalogForClient(array $context): array
    {
        $out = [];
        foreach (self::availableCatalog($context) as $cmd) {
            $entry = [
                'id' => $cmd['id'],
                'kind' => $cmd['kind'],
                'category' => $cmd['category'] ?? 'present',
                'label' => t((string)($cmd['labelKey'] ?? $cmd['id'])),
            ];
            if (!empty($cmd['icon'])) {
                $entry['icon'] = $cmd['icon'];
            } elseif (($cmd['kind'] ?? '') === 'widget') {
                $entry['icon'] = 'widget';
            } elseif (($cmd['kind'] ?? '') === 'separator') {
                $entry['icon'] = 'separator';
            } elseif (($cmd['kind'] ?? '') === 'row_separator') {
                $entry['icon'] = 'row_separator';
            }
            if (!empty($cmd['gridSpan']) && is_array($cmd['gridSpan'])) {
                $entry['gridSpan'] = [
                    'cols' => max(1, min(16, (int)($cmd['gridSpan']['cols'] ?? 1))),
                    'rows' => max(1, min(2, (int)($cmd['gridSpan']['rows'] ?? 1))),
                ];
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @param array<string,bool> $context
     * @return list<array<string,mixed>>
     */
    public static function commandDefsForClient(array $context): array
    {
        $out = [];
        foreach (self::availableCatalog($context) as $cmd) {
            $entry = [
                'id' => $cmd['id'],
                'kind' => $cmd['kind'],
                'label' => t((string)($cmd['labelKey'] ?? $cmd['id'])),
            ];
            if (($cmd['id'] ?? '') === 'back_to_editor') {
                $entry['title'] = t('present.back_to_editor');
            }
            foreach (['icon', 'domId', 'triggerId', 'settingsPanel', 'templateId', 'gridSpan', 'hrefKey', 'target'] as $key) {
                if (isset($cmd[$key]) && $cmd[$key] !== '' && $cmd[$key] !== []) {
                    $entry[$key] = $cmd[$key];
                }
            }
            $out[] = $entry;
        }
        return $out;
    }
}
