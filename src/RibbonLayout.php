<?php
/**
 * Ribbon-Layout: Standard, Nutzer-Anpassung, Validierung.
 */
class RibbonLayout
{
    /**
     * Neue Befehle ergänzt seit 3.12.0 das Hub-Verfahren (HubRibbon, Layout-Feld «known»: was es beim Speichern gab; Neues kommt an
     * seine Standardstelle, Entferntes bleibt weg). Die alten Nachrüstungen (Stand 1 «Folien löschen», Stand 2 «Abspielen», Präsentieren-
     * Vorgaben) laufen nur noch einmal für Layouts ohne «known» (Übernahme), danach nie wieder.
     */
    public const KNOWN_MAX = 2000;

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
            'ffFile' => !empty($vars['ffFile']),
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
            'ffFile' => true,
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
            if (self::layoutItemsSignature($stored) !== self::layoutItemsSignature($persisted) || !is_array($stored['known'] ?? null)) {
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
        $stand = (int)($layout['stand'] ?? 0);
        if ($version !== 1 || !isset($layout['tabs']) || !is_array($layout['tabs'])) {
            $layout = $default;
            $stand = 99;
        }

        $tabs = [];
        $injectViewPreviews = false;
        // 3.8.0: «Fenster» (preview_window) und «Lokal» (present_local) sind ein Knopf «Auf Gerät» (preview_device) geworden.
        $previewAliases = ['preview_window' => 'preview_device', 'present_local' => 'preview_device'];
        $previewDeviceSeen = false;
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
                        ['id' => 'show_progress', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'show_controls', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ],
                    'present_display' => [
                        ['id' => 'show_progress', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'show_controls', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ],
                    'widget:present_display' => [
                        ['id' => 'show_progress', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'show_controls', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ],
                    'widget:collab_menu' => [
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
                    if (isset($previewAliases[$itemId])) {
                        $itemId = $previewAliases[$itemId];
                        $item = ['id' => $itemId] + (is_array($item) && isset($item['gridSpan']) ? ['gridSpan' => $item['gridSpan']] : []);
                    }
                    if ($itemId === 'preview_device') {
                        if ($previewDeviceSeen) {
                            continue;
                        }
                        $previewDeviceSeen = true;
                    }
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
                    // Datei-Gruppe (FileForge, 3.1.0): feste Grösse wie in der Vorgabe. Gespeicherte Layouts aus 3.1.0–3.3.0
                    // trugen «Als Kopie»/«Als Vorlage» noch 2×1 → abgeschnittene Beschriftung (Urs 27.11.).
                    if (in_array($itemId, ['ff_save', 'ff_save_copy', 'ff_save_template'], true)) {
                        $itemEntry['gridSpan'] = ['cols' => 2, 'rows' => 2];
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
        $tabs = self::migratePresentPreviewToView($tabs, $injectViewPreviews);
        $known = self::knownList($layout['known'] ?? null);
        if ($known === null) {
            /* Übernahme (3.12.0): Layout ohne «known» bekommt die alten Nachrüstungen ein letztes Mal, danach gilt «known». */
            $tabs = self::migrateRestorePresentTabDefaults($tabs);
            if ($stand < 1 && isset($catalog['delete_slides'])) {
                $tabs = self::migrateAddDeleteSlides($tabs);
            }
            if ($stand < 2 && isset($catalog['play_slide'])) {
                $tabs = self::migrateAddPlaySlide($tabs);
            }
            $known = [];
        }
        /* bekannt = alles im vollen Katalog (Speicherkontext) + was schon bekannt war */
        foreach (self::catalogIndex(self::persistContext()) as $cid => $_c) {
            if ($cid !== 'separator' && $cid !== 'row_separator' && !in_array($cid, $known, true)) {
                $known[] = (string)$cid;
            }
        }

        $prefs = is_array($layout['prefs'] ?? null) ? $layout['prefs'] : [];
        $tabIds = array_column($tabs, 'id');
        $activeTab = (string)($prefs['activeTab'] ?? 'start');
        if (!in_array($activeTab, $tabIds, true)) {
            $activeTab = $tabIds[0];
        }

        return [
            'version' => 1,
            'tabs' => $tabs,
            'known' => array_slice($known, 0, self::KNOWN_MAX),
            'shortcuts' => self::shortcutMap($layout['shortcuts'] ?? null),
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

    /** «known» aus dem Layout: Liste von Befehls-IDs, null = fehlt (altes Layout). @return list<string>|null */
    public static function knownList(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $out = [];
        foreach ($raw as $id) {
            if (is_string($id) && $id !== '' && strlen($id) <= 120 && preg_match('/^[\w:.\-]+$/', $id)) {
                $out[] = $id;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Tastenkürzel der Person (HubRibbon, nur Abweichungen von der Vorgabe): Schlüssel Befehl / tab:<id> / ribbon:<name>,
     * Wert «Mod+Shift+G» o. ä. oder '' (bewusst leer).
     *
     * @return array<string,string>
     */
    public static function shortcutMap(mixed $raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $k => $v) {
            if (!is_string($k) || $k === '' || strlen($k) > 120 || !preg_match('/^(tab:|ribbon:)?[\w:.\-]+$/', $k)) {
                continue;
            }
            if (!is_string($v) || strlen($v) > 40) {
                continue;
            }
            if ($v !== '' && !preg_match('/^((Ctrl|Mod|Alt|Shift)\+)*[^\s+]{1,12}$/u', $v)) {
                continue;
            }
            $out[$k] = $v;
            if (count($out) >= 400) {
                break;
            }
        }
        return $out;
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
     * Vorschau/«Auf Gerät» (früher Fenster/Lokal) von Präsentation→Anzeige nach Ansicht verschieben;
     * Optionen-Button → Widget «Anzeige».
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migratePresentPreviewToView(array $tabs, bool $injectDefaults): array
    {
        $moveIds = ['preview_tab', 'preview_device'];
        $defaults = [
            'preview_tab' => ['id' => 'preview_tab', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
            'preview_device' => ['id' => 'preview_device', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
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
    /**
     * «Folien löschen» (3.7.0) in angepasste Menübänder nachrüsten: Einfügen › Folien, nach «Präsentation einfügen».
     * Nur einmal (Stand < 1); fehlt die Gruppe, hat sie jemand bewusst entfernt — dann nichts.
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migrateAddDeleteSlides(array $tabs): array
    {
        foreach ($tabs as $tab) {
            foreach ($tab['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if ((string)($item['id'] ?? '') === 'delete_slides') {
                        return $tabs;
                    }
                }
            }
        }
        foreach ($tabs as $ti => $tab) {
            if (($tab['id'] ?? '') !== 'insert') {
                continue;
            }
            foreach ($tab['groups'] ?? [] as $gi => $group) {
                if (($group['id'] ?? '') !== 'g_slides') {
                    continue;
                }
                $items = array_values($group['items'] ?? []);
                $at = count($items);
                foreach ($items as $i => $item) {
                    if (in_array((string)($item['id'] ?? ''), ['add_slide', 'media:insert_presentation'], true)) {
                        $at = $i + 1;
                    }
                }
                array_splice($items, $at, 0, [['id' => 'delete_slides', 'gridSpan' => ['cols' => 2, 'rows' => 2]]]);
                $tabs[$ti]['groups'][$gi]['items'] = $items;
                return $tabs;
            }
        }

        return $tabs;
    }

    /**
     * 3.11.0: «Abspielen» neben «Vorschau» in gespeicherte Menübänder einfügen (einmal, Stand 2).
     *
     * @param list<array<string,mixed>> $tabs
     * @return list<array<string,mixed>>
     */
    private static function migrateAddPlaySlide(array $tabs): array
    {
        foreach ($tabs as $tab) {
            foreach ($tab['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if ((string)($item['id'] ?? '') === 'play_slide') {
                        return $tabs;
                    }
                }
            }
        }
        foreach ($tabs as $ti => $tab) {
            foreach ($tab['groups'] ?? [] as $gi => $group) {
                $items = array_values($group['items'] ?? []);
                foreach ($items as $i => $item) {
                    if ((string)($item['id'] ?? '') === 'preview_tab') {
                        array_splice($items, $i + 1, 0, [['id' => 'play_slide', 'gridSpan' => ['cols' => 2, 'rows' => 2]]]);
                        $tabs[$ti]['groups'][$gi]['items'] = $items;
                        return $tabs;
                    }
                }
            }
        }

        return $tabs;
    }

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
            || (empty($have['show_progress']) && empty($have['show_controls']) && empty($have['widget:present_display']))
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

    /**
     * Nur activeTab/collapsed (und optional Appearance) speichern — ohne volles Customize.
     *
     * @param array<string,mixed> $prefsPartial
     * @return array<string,mixed> normalisierte Prefs
     */
    public static function patchPrefs(string $userId, array $prefsPartial): array
    {
        $user = Auth::findById($userId);
        $stored = is_array($user['ribbon_layout'] ?? null) ? $user['ribbon_layout'] : self::defaultLayout();
        $prefs = is_array($stored['prefs'] ?? null) ? $stored['prefs'] : [];
        if (isset($prefsPartial['activeTab']) && is_string($prefsPartial['activeTab']) && $prefsPartial['activeTab'] !== '') {
            $prefs['activeTab'] = $prefsPartial['activeTab'];
        }
        if (array_key_exists('collapsed', $prefsPartial)) {
            $prefs['collapsed'] = !empty($prefsPartial['collapsed']);
        }
        if (isset($prefsPartial['iconSize']) && in_array($prefsPartial['iconSize'], ['small', 'medium', 'large'], true)) {
            $prefs['iconSize'] = $prefsPartial['iconSize'];
        }
        if (array_key_exists('showLabels', $prefsPartial)) {
            $prefs['showLabels'] = !empty($prefsPartial['showLabels']);
        }
        $stored['prefs'] = $prefs;
        $sanitized = self::sanitizeLayout($stored, self::persistContext());
        self::saveLayout($userId, $sanitized);
        return $sanitized['prefs'];
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
            'known' => self::knownList($layout['known'] ?? null) ?? [],
            'shortcuts' => self::shortcutMap($layout['shortcuts'] ?? null),
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
            'known' => self::knownList($layout['known'] ?? null) ?? [],
            'shortcuts' => self::shortcutMap($layout['shortcuts'] ?? null),
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

    /**
     * Beim Speichern aus dem Browser: Befehle, die im aktuellen Kontext ausgeblendet sind (z. B. Datei-Gruppe ohne FileForge-Datei,
     * Teilen ohne Besitz), kennt der Browser nicht. Sie bleiben an ihrer bisherigen Stelle stehen, statt mit dem Speichern zu verschwinden.
     *
     * @param array<string,mixed> $in vom Browser
     * @param array<string,mixed>|null $stored bisher gespeichert
     * @param array<string,array<string,mixed>> $visible Katalog des aktuellen Kontexts
     * @return array<string,mixed>
     */
    public static function keepHiddenItems(array $in, ?array $stored, array $visible): array
    {
        if (!is_array($stored) || !is_array($stored['tabs'] ?? null) || !is_array($in['tabs'] ?? null)) {
            return $in;
        }
        $present = [];
        foreach ($in['tabs'] as $tab) {
            foreach (is_array($tab['groups'] ?? null) ? $tab['groups'] : [] as $group) {
                foreach (is_array($group['items'] ?? null) ? $group['items'] : [] as $item) {
                    $present[is_array($item) ? (string)($item['id'] ?? '') : (string)$item] = true;
                }
            }
        }
        foreach ($stored['tabs'] as $st) {
            $ti = null;
            foreach ($in['tabs'] as $i => $t) {
                if ((string)($t['id'] ?? '') === (string)($st['id'] ?? '')) {
                    $ti = $i;
                    break;
                }
            }
            if ($ti === null) {
                continue;
            }
            foreach (is_array($st['groups'] ?? null) ? $st['groups'] : [] as $sg) {
                $gi = null;
                foreach ($in['tabs'][$ti]['groups'] ?? [] as $j => $g) {
                    if ((string)($g['id'] ?? '') === (string)($sg['id'] ?? '')) {
                        $gi = $j;
                        break;
                    }
                }
                if ($gi === null) {
                    continue;
                }
                $prev = null;
                foreach (is_array($sg['items'] ?? null) ? $sg['items'] : [] as $item) {
                    $id = is_array($item) ? (string)($item['id'] ?? '') : (string)$item;
                    if ($id === '' || $id === 'separator' || $id === 'row_separator') {
                        continue;
                    }
                    if (isset($present[$id]) || isset($visible[$id])) {
                        if (isset($present[$id])) {
                            $prev = $id;
                        }
                        continue;
                    }
                    $items = array_values($in['tabs'][$ti]['groups'][$gi]['items'] ?? []);
                    $pos = $prev === null ? 0 : count($items);
                    if ($prev !== null) {
                        foreach ($items as $k => $it) {
                            if ((is_array($it) ? (string)($it['id'] ?? '') : (string)$it) === $prev) {
                                $pos = $k + 1;
                                break;
                            }
                        }
                    }
                    array_splice($items, $pos, 0, [is_array($item) ? $item : ['id' => $id]]);
                    $in['tabs'][$ti]['groups'][$gi]['items'] = $items;
                    $present[$id] = true;
                    $prev = $id;
                }
            }
        }
        return $in;
    }

    /** Standard-Layout für «Zurücksetzen» und das Einsetzen neuer Befehle im Browser (HubRibbon). @param array<string,bool> $context */
    public static function defaultForClient(array $context): array
    {
        $layout = self::sanitizeLayout(self::defaultLayout(), $context);
        unset($layout['known'], $layout['shortcuts']);
        return self::layoutForClient($layout, $context);
    }

    /**
     * Beschriftung zurück auf den Sprachschlüssel, wo sie der Vorgabe entspricht (Reiter/Gruppen aus dem Browser tragen die übersetzte
     * Beschriftung; so bleibt das Menüband beim Sprachwechsel übersetzt).
     *
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    public static function restoreLabelKeys(array $layout, array $default): array
    {
        $tabKeys = [];
        $groupKeys = [];
        foreach ($default['tabs'] ?? [] as $dt) {
            if (!empty($dt['labelKey'])) {
                $tabKeys[(string)$dt['id']] = (string)$dt['labelKey'];
            }
            foreach ($dt['groups'] ?? [] as $dg) {
                if (!empty($dg['labelKey'])) {
                    $groupKeys[(string)$dg['id']] = (string)$dg['labelKey'];
                }
            }
        }
        foreach ($layout['tabs'] ?? [] as $ti => $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $tid = (string)($tab['id'] ?? '');
            if (isset($tabKeys[$tid]) && trim((string)($tab['label'] ?? '')) === t($tabKeys[$tid])) {
                $layout['tabs'][$ti]['labelKey'] = $tabKeys[$tid];
                unset($layout['tabs'][$ti]['label']);
            }
            foreach ($tab['groups'] ?? [] as $gi => $group) {
                if (!is_array($group)) {
                    continue;
                }
                $gid = (string)($group['id'] ?? '');
                if (isset($groupKeys[$gid]) && trim((string)($group['label'] ?? '')) === t($groupKeys[$gid])) {
                    $layout['tabs'][$ti]['groups'][$gi]['labelKey'] = $groupKeys[$gid];
                    unset($layout['tabs'][$ti]['groups'][$gi]['label']);
                }
            }
        }
        return $layout;
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
            if (!empty($cmd['allowInInput'])) {
                $entry['allowInInput'] = true;
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
