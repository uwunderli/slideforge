<?php
/**
 * Ribbon-Layout: Standard, Nutzer-Anpassung, Validierung.
 */
class RibbonLayout
{
    /** @param array<string,bool> $context */
    public static function buildContext(array $vars): array
    {
        $masterSlideEditing = !empty($vars['masterSlideEditing']);
        return [
            'canEdit' => !empty($vars['canEdit']),
            /* Masterfolie aus Präsentation: Befehle sichtbar (werden clientseitig deaktiviert). */
            'showPresentTab' => empty($vars['isTemplateMode']) || $masterSlideEditing,
            'showInsertSlides' => empty($vars['isTemplateMode']) || !empty($vars['isLayoutSetMode']),
            'layoutSetMode' => !empty($vars['isLayoutSetMode']),
            'masterSlideEditing' => $masterSlideEditing,
            'spellcheckEnabled' => !empty($vars['spellcheckEnabled']),
            'pixabayEnabled' => !empty($vars['pixabayEnabled']),
            'iconifyEnabled' => !empty($vars['iconifyEnabled']),
            'openclipartEnabled' => !empty($vars['openclipartEnabled']),
            'showMasterSlideNav' => !empty($vars['showMasterSlideNav']),
            'isOwner' => !empty($vars['isOwner']),
        ];
    }

    /**
     * Kontext zum Speichern/Migrieren: keine Sichtbarkeitsfilter nach Präsentationstyp.
     * Sonst würden z.B. Vorlagen-Editoren Präsentieren/Anzeige/Teilen dauerhaft aus dem User-Layout löschen.
     *
     * @return array<string,bool>
     */
    public static function persistContext(): array
    {
        return [
            'canEdit' => true,
            'showPresentTab' => true,
            'showInsertSlides' => true,
            'layoutSetMode' => true,
            'spellcheckEnabled' => true,
            'pixabayEnabled' => true,
            'iconifyEnabled' => true,
            'openclipartEnabled' => true,
            'showMasterSlideNav' => true,
            'isOwner' => true,
            'masterSlideEditing' => false,
        ];
    }

    /** @return array<string,mixed> */
    public static function defaultLayout(): array
    {
        require_once BASE_PATH . '/config/ribbon_default_layout.php';
        return ribbon_default_layout();
    }

    /** @return list<array<string,mixed>> */
    private static function catalog(): array
    {
        require_once BASE_PATH . '/config/ribbon_commands.php';
        return ribbon_command_catalog();
    }

    /** @param array<string,bool> $context @return list<array<string,mixed>> */
    public static function availableCatalog(array $context): array
    {
        return array_values(array_filter(self::catalog(), fn($cmd) => self::matchesContext($cmd['visibleWhen'] ?? [], $context)));
    }

    /** @param list<string> $rules @param array<string,bool> $context */
    private static function matchesContext(array $rules, array $context): bool
    {
        foreach ($rules as $rule) {
            if (empty($context[$rule])) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,bool> $context @return array<string,array<string,mixed>> */
    public static function catalogIndex(array $context): array
    {
        $index = [];
        foreach (self::availableCatalog($context) as $cmd) {
            $index[$cmd['id']] = $cmd;
        }
        return $index;
    }

    /** @param array<string,bool> $context */
    public static function getLayout(string $userId, array $context): array
    {
        $user = Auth::findById($userId);
        $stored = is_array($user['ribbon_layout'] ?? null) ? $user['ribbon_layout'] : null;
        $layout = self::sanitizeLayout($stored ?? self::defaultLayout(), $context);
        $layout['customized'] = $stored !== null;
        /* Migrationen dauerhaft zurückschreiben — immer mit persistContext,
           nie mit vorlagen-/feature-gefiltertem Request-Kontext. */
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

    /** @param array<string,mixed> $layout @param array<string,bool> $context @return array<string,mixed> */
    public static function sanitizeLayout(array $layout, array $context): array
    {
        $catalog = self::catalogIndex($context);
        $default = self::defaultLayout();
        $version = (int)($layout['version'] ?? 1);
        if ($version !== 1 || !isset($layout['tabs']) || !is_array($layout['tabs'])) {
            $layout = $default;
        }

        $tabs = [];
        $injectViewPreviews = false;
        foreach ($layout['tabs'] as $tab) {
            if (!is_array($tab) || empty($tab['id'])) {
                continue;
            }
            $tabId = (string)$tab['id'];
            $label = trim((string)($tab['label'] ?? ''));
            $labelKey = trim((string)($tab['labelKey'] ?? ''));
            if ($label === '' && $labelKey === '') {
                continue;
            }

            $groups = [];
            foreach ($tab['groups'] ?? [] as $group) {
                if (!is_array($group) || empty($group['id'])) {
                    continue;
                }
                $groupLabel = trim((string)($group['label'] ?? ''));
                $groupLabelKey = trim((string)($group['labelKey'] ?? ''));
                if ($groupLabel === '' && $groupLabelKey === '') {
                    continue;
                }

                $items = [];
                $legacyExpand = [
                    'widget:slide_background' => [
                        'bg:none',
                        'widget:slide_bg_color',
                        'bg:gradient',
                        'bg:image',
                        'bg:video',
                        'widget:slide_bg_preview',
                    ],
                    'widget:slide_timing' => [
                        'widget:slide_autoadvance',
                    ],
                    'widget:present_menu' => [
                        ['id' => 'widget:present_display', 'gridSpan' => ['cols' => 10, 'rows' => 2]],
                    ],
                    'present_display' => [
                        ['id' => 'widget:present_display', 'gridSpan' => ['cols' => 10, 'rows' => 2]],
                    ],
                    'widget:collab_menu' => [
                        ['id' => 'share', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'export', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                    ],
                ];
                $settingsLegacyIds = [
                    'settings_slides',
                    'widget:settings_slides',
                    'settings_presentation',
                    'widget:settings_presentation',
                ];
                $settingsDefaultItems = [
                    ['id' => 'widget:settings_title', 'gridSpan' => ['cols' => 6, 'rows' => 2]],
                    ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                    ['id' => 'widget:settings_size', 'gridSpan' => ['cols' => 3, 'rows' => 2]],
                    ['id' => 'widget:settings_margin', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
                    ['id' => 'row_separator'],
                    ['id' => 'widget:settings_duration', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
                    ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                    ['id' => 'widget:settings_spellcheck', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ['id' => 'widget:settings_layout_set', 'gridSpan' => ['cols' => 4, 'rows' => 2]],
                ];
                $settingsExpanded = false;
                $rawGroupItems = array_values($group['items'] ?? []);
                for ($gi = 0, $gLen = count($rawGroupItems); $gi < $gLen; $gi++) {
                    $item = $rawGroupItems[$gi];
                    $itemId = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($itemId === 'widget:present_menu') {
                        $injectViewPreviews = true;
                    }
                    if (in_array($itemId, $settingsLegacyIds, true)) {
                        if (!$settingsExpanded) {
                            foreach ($settingsDefaultItems as $exp) {
                                $expId = (string)($exp['id'] ?? '');
                                if ($expId === '' || !isset($catalog[$expId])) {
                                    continue;
                                }
                                $entry = ['id' => $expId];
                                if (!empty($exp['gridSpan']) && is_array($exp['gridSpan'])) {
                                    $entry['gridSpan'] = [
                                        'cols' => max(1, min(16, (int)($exp['gridSpan']['cols'] ?? 1))),
                                        'rows' => max(1, min(2, (int)($exp['gridSpan']['rows'] ?? 1))),
                                    ];
                                }
                                $items[] = $entry;
                            }
                            $settingsExpanded = true;
                        }
                        /* Folgende Legacy-Partner in derselben Gruppe überspringen. */
                        while (
                            $gi + 1 < $gLen
                            && in_array(
                                is_array($rawGroupItems[$gi + 1])
                                    ? (string)($rawGroupItems[$gi + 1]['id'] ?? '')
                                    : (string)$rawGroupItems[$gi + 1],
                                $settingsLegacyIds,
                                true
                            )
                        ) {
                            $gi++;
                        }
                        continue;
                    }
                    if ($itemId !== '' && isset($legacyExpand[$itemId])) {
                        foreach ($legacyExpand[$itemId] as $exp) {
                            $expId = is_array($exp) ? (string)($exp['id'] ?? '') : (string)$exp;
                            if ($expId === '' || !isset($catalog[$expId])) {
                                continue;
                            }
                            $entry = ['id' => $expId];
                            if (is_array($exp) && !empty($exp['gridSpan']) && is_array($exp['gridSpan'])) {
                                $entry['gridSpan'] = [
                                    'cols' => max(1, min(16, (int)($exp['gridSpan']['cols'] ?? 1))),
                                    'rows' => max(1, min(2, (int)($exp['gridSpan']['rows'] ?? 1))),
                                ];
                            }
                            $items[] = $entry;
                        }
                        continue;
                    }
                    if ($itemId === 'widget:settings_save') {
                        continue;
                    }
                    if ($itemId === '' || !isset($catalog[$itemId])) {
                        continue;
                    }
                    $itemEntry = ['id' => $itemId];
                    if (!empty($item['instanceId'])) {
                        $itemEntry['instanceId'] = (string)$item['instanceId'];
                    }
                    if (!empty($item['gridSpan']) && is_array($item['gridSpan'])) {
                        $itemEntry['gridSpan'] = [
                            'cols' => max(1, min(16, (int)($item['gridSpan']['cols'] ?? 1))),
                            'rows' => max(1, min(2, (int)($item['gridSpan']['rows'] ?? 1))),
                        ];
                    }
                    if ($itemId === 'widget:present_display') {
                        // Kompakte 3-Spalten-Anzeige (Fortschritt|Link|Bildschirm); alte Layouts mit 12+ verkleinern.
                        $cols = (int)($itemEntry['gridSpan']['cols'] ?? 10);
                        if ($cols >= 12) {
                            $cols = 10;
                        }
                        $itemEntry['gridSpan'] = [
                            'cols' => max(10, $cols),
                            'rows' => 2,
                        ];
                    }
                    if ($itemId === 'widget:settings_title') {
                        $cols = (int)($itemEntry['gridSpan']['cols'] ?? 6);
                        if ($cols < 6) {
                            $cols = 6;
                        }
                        $itemEntry['gridSpan'] = [
                            'cols' => $cols,
                            'rows' => 2,
                        ];
                    }
                    if ($itemId === 'widget:settings_size') {
                        $cols = (int)($itemEntry['gridSpan']['cols'] ?? 3);
                        if ($cols < 3) {
                            $cols = 3;
                        }
                        $itemEntry['gridSpan'] = [
                            'cols' => $cols,
                            'rows' => 2,
                        ];
                    }
                    if ($itemId === 'widget:settings_spellcheck') {
                        $itemEntry['gridSpan'] = [
                            'cols' => 2,
                            'rows' => 2,
                        ];
                    }
                    if (array_key_exists('showLabel', $item) && empty($item['showLabel'])) {
                        $itemEntry['showLabel'] = false;
                    }
                    $items[] = $itemEntry;
                }
                if ($items === []) {
                    continue;
                }

                $entry = ['id' => (string)$group['id'], 'items' => $items];
                if ($groupLabel !== '') {
                    $entry['label'] = $groupLabel;
                }
                if ($groupLabelKey !== '') {
                    $entry['labelKey'] = $groupLabelKey;
                }
                $groupRows = (int)($group['rows'] ?? 2);
                if ($groupRows === 1) {
                    $entry['rows'] = 1;
                }
                $groups[] = $entry;
            }

            if ($groups === []) {
                continue;
            }

            $tabEntry = ['id' => $tabId, 'groups' => $groups];
            if ($label !== '') {
                $tabEntry['label'] = $label;
            }
            if ($labelKey !== '') {
                $tabEntry['labelKey'] = $labelKey;
            }
            $tabs[] = $tabEntry;
        }

        if ($tabs === []) {
            return self::sanitizeLayout($default, $context);
        }

        $tabs = self::migrateLegacyStartGroups($tabs);
        $tabs = self::migrateDesignApplySettingsGroup($tabs);
        $tabs = self::migratePresentModeLabel($tabs);
        $tabs = self::migrateCollabShare($tabs);
        $tabs = self::migratePresentPreviewToView($tabs, $injectViewPreviews);
        $tabs = self::migrateRestorePresentTabDefaults($tabs);

        $prefs = is_array($layout['prefs'] ?? null) ? $layout['prefs'] : [];
        $tabIds = array_column($tabs, 'id');
        $activeTab = (string)($prefs['activeTab'] ?? 'start');
        if (!in_array($activeTab, $tabIds, true)) {
            $activeTab = $tabIds[0];
        }

        return [
            'version' => 1,
            'tabs' => $tabs,
            'prefs' => [
                'activeTab' => $activeTab,
                'collapsed' => !empty($prefs['collapsed']),
                'iconSize' => in_array($prefs['iconSize'] ?? '', ['small', 'medium', 'large'], true)
                    ? (string)$prefs['iconSize']
                    : 'medium',
                'showLabels' => !array_key_exists('showLabels', $prefs) || !empty($prefs['showLabels']),
            ],
        ];
    }

    /** @param list<array<string,mixed>> $tabs @return list<array<string,mixed>> */
    private static function migrateLegacyStartGroups(array $tabs): array
    {
        foreach ($tabs as &$tab) {
            if (($tab['id'] ?? '') !== 'start') {
                continue;
            }
            $groups = $tab['groups'] ?? [];
            $byId = [];
            foreach ($groups as $group) {
                if (!empty($group['id'])) {
                    $byId[(string)$group['id']] = $group;
                }
            }
            $legacyIds = ['g_undo', 'g_clipboard', 'g_arrange'];
            $hasLegacy = array_intersect($legacyIds, array_keys($byId)) !== [];
            if (!$hasLegacy || isset($byId['g_edit'])) {
                continue;
            }

            $mergedItems = [];
            foreach ($legacyIds as $legacyId) {
                foreach ($byId[$legacyId]['items'] ?? [] as $item) {
                    $mergedItems[] = $item;
                }
            }

            // SoftMaker-Default: Zwischenablage + Anordnen = eine Gruppe „Bearbeiten“
            if ($mergedItems === []) {
                continue;
            }

            $newGroups = [];
            $merged = false;
            foreach ($groups as $group) {
                $gid = (string)($group['id'] ?? '');
                if (in_array($gid, $legacyIds, true)) {
                    if (!$merged) {
                        $newGroups[] = [
                            'id' => 'g_edit',
                            'labelKey' => 'ribbon.group_edit',
                            'items' => $mergedItems,
                        ];
                        $merged = true;
                    }
                    continue;
                }
                $newGroups[] = $group;
            }
            $tab['groups'] = $newGroups;
        }
        unset($tab);

        return $tabs;
    }

    /**
     * SoftMaker: «Auf Auswahl / Auf alle anwenden» in Gruppe «Einstellungen» (Entwurf).
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migrateDesignApplySettingsGroup(array $tabs): array
    {
        foreach ($tabs as &$tab) {
            if (($tab['id'] ?? '') !== 'design') {
                continue;
            }
            $groups = $tab['groups'] ?? [];
            if ($groups === []) {
                continue;
            }

            $applyAllItem = null;
            $applySelectedItem = null;
            $hasSettingsGroup = false;
            foreach ($groups as $gi => $group) {
                $gid = (string)($group['id'] ?? '');
                if ($gid === 'g_design_settings') {
                    $hasSettingsGroup = true;
                }
                $kept = [];
                foreach ($group['items'] ?? [] as $item) {
                    $id = (string)($item['id'] ?? '');
                    if ($id === 'apply_transition_all') {
                        if ($applyAllItem === null) {
                            $applyAllItem = $item;
                        }
                        if ($gid !== 'g_design_settings') {
                            continue;
                        }
                    }
                    if ($id === 'apply_transition_selected') {
                        if ($applySelectedItem === null) {
                            $applySelectedItem = $item;
                        }
                        if ($gid !== 'g_design_settings') {
                            continue;
                        }
                    }
                    $kept[] = $item;
                }
                $groups[$gi]['items'] = $kept;
            }

            if ($applyAllItem === null) {
                $applyAllItem = [
                    'id' => 'apply_transition_all',
                    'gridSpan' => ['cols' => 1, 'rows' => 2],
                ];
            }
            if ($applySelectedItem === null) {
                $applySelectedItem = [
                    'id' => 'apply_transition_selected',
                    'gridSpan' => ['cols' => 1, 'rows' => 2],
                ];
            }

            if ($hasSettingsGroup) {
                foreach ($groups as $gi => $group) {
                    if ((string)($group['id'] ?? '') !== 'g_design_settings') {
                        continue;
                    }
                    $otherItems = [];
                    foreach ($group['items'] ?? [] as $item) {
                        $id = (string)($item['id'] ?? '');
                        if ($id === 'apply_transition_all' || $id === 'apply_transition_selected') {
                            continue;
                        }
                        $otherItems[] = $item;
                    }
                    /* Reihenfolge: Auswahl → Alle */
                    $groups[$gi]['items'] = array_merge($otherItems, [$applySelectedItem, $applyAllItem]);
                    if (empty($groups[$gi]['label']) && empty($groups[$gi]['labelKey'])) {
                        $groups[$gi]['labelKey'] = 'ribbon.group_settings';
                    }
                }
            } else {
                $insertAt = count($groups);
                foreach ($groups as $gi => $group) {
                    $gid = (string)($group['id'] ?? '');
                    if ($gid === 'g_timing' || $gid === 'g_transition') {
                        $insertAt = $gi + 1;
                    }
                }
                array_splice($groups, $insertAt, 0, [[
                    'id' => 'g_design_settings',
                    'labelKey' => 'ribbon.group_settings',
                    'items' => [$applySelectedItem, $applyAllItem],
                ]]);
            }

            /* Leere Gruppen (z.B. Timing ohne Inhalt) entfernen */
            $groups = array_values(array_filter($groups, static function (array $group): bool {
                return !empty($group['items']);
            }));

            $tab['groups'] = $groups;
        }
        unset($tab);

        return $tabs;
    }

    /**
     * Vorschau/Fenster/Lokal von Präsentation→Anzeige nach Ansicht verschieben;
     * Optionen-Button → Widget «Anzeige».
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migratePresentPreviewToView(array $tabs, bool $injectDefaults): array
    {
        $moveIds = ['preview_tab', 'preview_window', 'present_local'];
        $defaults = [
            'preview_tab' => ['id' => 'preview_tab', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
            'preview_window' => ['id' => 'preview_window', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
            'present_local' => ['id' => 'present_local', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ];
        $moved = [];

        foreach ($tabs as &$tab) {
            if (($tab['id'] ?? '') !== 'present' || !isset($tab['groups']) || !is_array($tab['groups'])) {
                continue;
            }
            foreach ($tab['groups'] as &$group) {
                if (!is_array($group)) {
                    continue;
                }
                $newItems = [];
                foreach ($group['items'] ?? [] as $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if (in_array($id, $moveIds, true)) {
                        $moved[$id] = is_array($item) ? $item : $defaults[$id];
                        continue;
                    }
                    $newItems[] = $item;
                }
                $group['items'] = $newItems;
            }
            unset($group);
            $tab['groups'] = array_values(array_filter(
                $tab['groups'],
                static fn($g) => is_array($g) && !empty($g['items'])
            ));
        }
        unset($tab);

        $existing = [];
        foreach ($tabs as $tab) {
            foreach ($tab['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if (in_array($id, $moveIds, true)) {
                        $existing[$id] = true;
                    }
                }
            }
        }

        $toAdd = [];
        foreach ($moveIds as $id) {
            if (isset($existing[$id])) {
                continue;
            }
            if (isset($moved[$id])) {
                $toAdd[] = $moved[$id];
            } elseif ($injectDefaults) {
                $toAdd[] = $defaults[$id];
            }
        }

        if ($toAdd === []) {
            return $tabs;
        }

        $added = false;
        foreach ($tabs as &$tab) {
            if (($tab['id'] ?? '') !== 'view' || !isset($tab['groups']) || !is_array($tab['groups'])) {
                continue;
            }
            $groups = &$tab['groups'];
            $foundPreview = false;
            foreach ($groups as &$group) {
                if (($group['id'] ?? '') !== 'g_preview') {
                    continue;
                }
                if (!isset($group['items']) || !is_array($group['items'])) {
                    $group['items'] = [];
                }
                foreach ($toAdd as $item) {
                    $group['items'][] = $item;
                }
                $foundPreview = true;
                $added = true;
                break;
            }
            unset($group);
            if (!$foundPreview) {
                $insertAt = 0;
                foreach ($groups as $i => $g) {
                    if (($g['id'] ?? '') === 'g_view') {
                        $insertAt = $i + 1;
                        break;
                    }
                }
                array_splice($groups, $insertAt, 0, [[
                    'id' => 'g_preview',
                    'labelKey' => 'ribbon.group_preview',
                    'items' => $toAdd,
                ]]);
                $added = true;
            }
            unset($groups);
            break;
        }
        unset($tab);

        if (!$added) {
            $tabs[] = [
                'id' => 'view',
                'labelKey' => 'ribbon.tab_view',
                'groups' => [[
                    'id' => 'g_preview',
                    'labelKey' => 'ribbon.group_preview',
                    'items' => $toAdd,
                ]],
            ];
        }

        return $tabs;
    }

    /**
     * Stellt Präsentation-Standardbefehle wieder her, die durch kontextgefiltertes
     * Persistieren (z.B. Vorlagen-Editor) aus dem User-Layout verschwunden sind.
     *
     * Heuristik: Einstellungen vorhanden, aber Präsentieren/Anzeige fehlen.
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migrateRestorePresentTabDefaults(array $tabs): array
    {
        $have = [];
        foreach ($tabs as $tab) {
            foreach ($tab['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $id = (string)($item['id'] ?? '');
                    if ($id !== '') {
                        $have[$id] = true;
                    }
                }
            }
        }
        if (empty($have['widget:settings_title'])) {
            return $tabs;
        }
        $needsRestore = empty($have['present_mode'])
            || empty($have['widget:present_display'])
            || empty($have['share'])
            || empty($have['export'])
            || empty($have['widget:settings_duration'])
            || empty($have['widget:settings_layout_set']);
        if (!$needsRestore) {
            return $tabs;
        }

        $defaultPresent = null;
        foreach (self::defaultLayout()['tabs'] as $tab) {
            if (($tab['id'] ?? '') === 'present') {
                $defaultPresent = $tab;
                break;
            }
        }
        if ($defaultPresent === null) {
            return $tabs;
        }

        $presentIdx = null;
        foreach ($tabs as $ti => $tab) {
            if (($tab['id'] ?? '') === 'present') {
                $presentIdx = $ti;
                break;
            }
        }
        if ($presentIdx === null) {
            $tabs[] = $defaultPresent;
            return $tabs;
        }

        $groupIndex = [];
        foreach ($tabs[$presentIdx]['groups'] ?? [] as $gi => $group) {
            $groupIndex[(string)($group['id'] ?? '')] = $gi;
        }

        foreach ($defaultPresent['groups'] ?? [] as $defGroup) {
            $gid = (string)($defGroup['id'] ?? '');
            if ($gid === '') {
                continue;
            }
            $missing = [];
            foreach ($defGroup['items'] ?? [] as $defItem) {
                $id = (string)($defItem['id'] ?? '');
                if ($id === '' || $id === 'separator' || $id === 'row_separator') {
                    continue;
                }
                if (!empty($have[$id])) {
                    continue;
                }
                $missing[] = $defItem;
                $have[$id] = true;
            }
            if ($missing === []) {
                continue;
            }
            if (!isset($groupIndex[$gid])) {
                $tabs[$presentIdx]['groups'][] = [
                    'id' => $gid,
                    'labelKey' => $defGroup['labelKey'] ?? '',
                    'items' => $missing,
                ];
                $groupIndex[$gid] = count($tabs[$presentIdx]['groups']) - 1;
                continue;
            }
            $gi = $groupIndex[$gid];
            $tabs[$presentIdx]['groups'][$gi]['items'] = array_merge(
                $tabs[$presentIdx]['groups'][$gi]['items'] ?? [],
                $missing
            );
        }

        return $tabs;
    }

    /**
     * Präsentationsmodus: Label anzeigen (kein riesiges Icon-only in der Tall-Zelle).
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migratePresentModeLabel(array $tabs): array
    {
        foreach ($tabs as &$tab) {
            foreach ($tab['groups'] ?? [] as &$group) {
                foreach ($group['items'] ?? [] as &$item) {
                    if (($item['id'] ?? '') !== 'present_mode') {
                        continue;
                    }
                    unset($item['showLabel']);
                    if (empty($item['gridSpan']) || !is_array($item['gridSpan'])) {
                        $item['gridSpan'] = ['cols' => 2, 'rows' => 2];
                    } else {
                        $item['gridSpan']['cols'] = max(2, (int)($item['gridSpan']['cols'] ?? 2));
                        $item['gridSpan']['rows'] = 2;
                    }
                }
                unset($item);
            }
            unset($group);
        }
        unset($tab);

        return $tabs;
    }

    /**
     * Zusammenarbeit: «Teilen» wieder einfügen, falls nur noch Export übrig ist
     * (Ribbon-API speicherte früher ohne isOwner und strich den Befehl).
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migrateCollabShare(array $tabs): array
    {
        foreach ($tabs as &$tab) {
            if (($tab['id'] ?? '') !== 'present') {
                continue;
            }
            foreach ($tab['groups'] ?? [] as &$group) {
                if (($group['id'] ?? '') !== 'g_collab') {
                    continue;
                }
                $hasShare = false;
                $hasExport = false;
                foreach ($group['items'] ?? [] as $item) {
                    $id = (string)($item['id'] ?? '');
                    if ($id === 'share') {
                        $hasShare = true;
                    }
                    if ($id === 'export') {
                        $hasExport = true;
                    }
                }
                if ($hasShare || !$hasExport) {
                    continue;
                }
                $newItems = [];
                foreach ($group['items'] ?? [] as $item) {
                    if (($item['id'] ?? '') === 'export') {
                        $newItems[] = [
                            'id' => 'share',
                            'gridSpan' => ['cols' => 1, 'rows' => 2],
                        ];
                    }
                    $newItems[] = $item;
                }
                $group['items'] = $newItems;
            }
            unset($group);
        }
        unset($tab);

        return $tabs;
    }

    /** @param array<string,mixed> $layout */
    public static function saveLayout(string $userId, array $layout): array
    {
        $layout = self::stripRuntimeKeys($layout);
        Storage::update(USERS_FILE, function (array $users) use ($userId, $layout) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['ribbon_layout'] = $layout;
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
                if ($u['id'] === $userId) {
                    unset($u['ribbon_layout']);
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
                'activeTab' => (string)($layout['prefs']['activeTab'] ?? 'start'),
                'collapsed' => !empty($layout['prefs']['collapsed']),
                'iconSize' => in_array($layout['prefs']['iconSize'] ?? '', ['small', 'medium', 'large'], true)
                    ? (string)$layout['prefs']['iconSize']
                    : 'medium',
                'showLabels' => !array_key_exists('showLabels', $layout['prefs'] ?? [])
                    || !empty($layout['prefs']['showLabels']),
            ],
        ];
    }

    /** @param array<string,bool> $context @return list<array<string,mixed>> */
    public static function catalogForClient(array $context): array
    {
        $out = [];
        foreach (self::availableCatalog($context) as $cmd) {
            $entry = [
                'id' => $cmd['id'],
                'kind' => $cmd['kind'],
                'category' => $cmd['category'],
                'label' => t($cmd['labelKey']),
            ];
            if (!empty($cmd['icon'])) {
                $entry['icon'] = $cmd['icon'];
            } elseif ($cmd['kind'] === 'widget') {
                $entry['icon'] = 'widget';
            } elseif ($cmd['kind'] === 'separator') {
                $entry['icon'] = 'separator';
            } elseif ($cmd['kind'] === 'row_separator') {
                $entry['icon'] = 'row_separator';
            }
            if (!empty($cmd['gridSpan']) && is_array($cmd['gridSpan'])) {
                $entry['gridSpan'] = [
                    'cols' => max(1, min(16, (int)($cmd['gridSpan']['cols'] ?? 1))),
                    'rows' => max(1, min(2, (int)($cmd['gridSpan']['rows'] ?? 1))),
                ];
            }
            if (!empty($cmd['tileable'])) {
                $entry['tileable'] = true;
            }
            $out[] = $entry;
        }
        return $out;
    }

    /** @param array<string,mixed> $layout @param array<string,bool> $context @return array<string,mixed> */
    public static function layoutForClient(array $layout, array $context): array
    {
        $catalog = self::catalogIndex($context);
        $tabs = [];
        foreach ($layout['tabs'] as $tab) {
            $tabLabel = !empty($tab['label']) ? (string)$tab['label'] : t((string)($tab['labelKey'] ?? $tab['id']));
            $groups = [];
            foreach ($tab['groups'] as $group) {
                $groupLabel = !empty($group['label']) ? (string)$group['label'] : t((string)($group['labelKey'] ?? $group['id']));
                $items = [];
                foreach ($group['items'] as $item) {
                    $id = (string)$item['id'];
                    if (!isset($catalog[$id])) {
                        continue;
                    }
                    $clientItem = ['id' => $id, 'label' => t($catalog[$id]['labelKey'])];
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
                $groups[] = [
                    'id' => (string)$group['id'],
                    'label' => $groupLabel,
                    'items' => $items,
                ];
                if (!empty($group['rows']) && (int)$group['rows'] === 1) {
                    $groups[count($groups) - 1]['rows'] = 1;
                }
            }
            if ($groups === []) {
                continue;
            }
            $tabs[] = [
                'id' => (string)$tab['id'],
                'label' => $tabLabel,
                'groups' => $groups,
            ];
        }

        return [
            'version' => 1,
            'tabs' => $tabs,
            'prefs' => [
                'activeTab' => (string)($layout['prefs']['activeTab'] ?? 'start'),
                'collapsed' => !empty($layout['prefs']['collapsed']),
                'iconSize' => in_array($layout['prefs']['iconSize'] ?? '', ['small', 'medium', 'large'], true)
                    ? (string)$layout['prefs']['iconSize']
                    : 'medium',
                'showLabels' => !array_key_exists('showLabels', $layout['prefs'] ?? [])
                    || !empty($layout['prefs']['showLabels']),
            ],
            'customized' => !empty($layout['customized']),
        ];
    }

    /** @param array<string,bool> $context @return list<array<string,mixed>> */
    public static function commandDefsForClient(array $context): array
    {
        $out = [];
        foreach (self::availableCatalog($context) as $cmd) {
            $entry = [
                'id' => $cmd['id'],
                'kind' => $cmd['kind'],
                'label' => t($cmd['labelKey']),
            ];
            foreach (['icon', 'domId', 'tool', 'mediaAction', 'triggerId', 'settingsPanel', 'shortcut', 'templateId', 'bgType', 'hrefKey', 'target'] as $key) {
                if (!empty($cmd[$key])) {
                    $entry[$key] = $cmd[$key];
                }
            }
            if (!empty($cmd['disabled'])) {
                $entry['disabled'] = true;
            }
            if (!empty($cmd['tileable'])) {
                $entry['tileable'] = true;
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
}
