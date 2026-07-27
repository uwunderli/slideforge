<?php
/**
 * Personalisiertes Dashboard: Bereiche und Zuordnung eigener Präsentationen (users.json).
 * Aktiv/Archiv werden über Tabs gefiltert; Bereichsstruktur ist in beiden Tabs identisch.
 */
class Dashboard
{
    private const SYSTEM_ACTIVE = 'active';
    private const SYSTEM_ARCHIVE = 'archive';
    public const SHARED_INBOX_ID = '__shared_inbox__';

    /** @return list<array<string,mixed>> */
    public static function getSectionsRaw(string $userId): array
    {
        $user = Auth::findById($userId);
        if (!$user) {
            return [];
        }
        return is_array($user['dashboard_sections'] ?? null) ? $user['dashboard_sections'] : [];
    }

    public static function tabViewMode(string $userId, bool $archivedTab): string
    {
        $user = Auth::findById($userId);
        $key = $archivedTab ? 'dashboard_view_archive' : 'dashboard_view_active';
        $mode = $user[$key] ?? 'grid';
        return in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    public static function setTabViewMode(string $userId, bool $archivedTab, string $mode): string
    {
        $mode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
        $key = $archivedTab ? 'dashboard_view_archive' : 'dashboard_view_active';
        Storage::update(USERS_FILE, function (array $users) use ($userId, $key, $mode) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u[$key] = $mode;
                }
            }
            unset($u);
            return $users;
        }, []);
        return $mode;
    }

    /**
     * @param list<array<string,mixed>> $owned
     * @param list<array<string,mixed>> $shared
     * @return list<array<string,mixed>>
     */
    public static function ensureSections(string $userId, array $owned, array $shared = []): array
    {
        $ownedById = self::indexPresentations($owned);
        $sharedById = self::indexPresentations($shared);

        $sections = self::saveSections($userId, function (array $sections) use ($ownedById, $sharedById) {
            return self::reconcileAllSections($sections, $ownedById, $sharedById);
        });
        self::pruneSharedArchived($userId, array_keys($sharedById));
        return $sections;
    }

    /** @param list<string> $validSharedIds */
    private static function pruneSharedArchived(string $userId, array $validSharedIds): void
    {
        $valid = array_flip($validSharedIds);
        Storage::update(USERS_FILE, function (array $users) use ($userId, $valid) {
            foreach ($users as &$u) {
                if ($u['id'] !== $userId) {
                    continue;
                }
                $ids = is_array($u['dashboard_shared_archived'] ?? null) ? $u['dashboard_shared_archived'] : [];
                $u['dashboard_shared_archived'] = array_values(array_filter(
                    $ids,
                    fn($id) => is_string($id) && $id !== '' && isset($valid[$id])
                ));
            }
            unset($u);
            return $users;
        }, []);
    }

    /** @return list<string> */
    public static function getSharedArchivedIds(string $userId): array
    {
        $user = Auth::findById($userId);
        if (!$user) {
            return [];
        }
        $ids = $user['dashboard_shared_archived'] ?? [];
        return is_array($ids) ? array_values(array_filter($ids, fn($id) => is_string($id) && $id !== '')) : [];
    }

    public static function isSharedArchived(string $userId, string $presentationId): bool
    {
        return in_array($presentationId, self::getSharedArchivedIds($userId), true);
    }

    public static function setSharedArchived(string $userId, string $presentationId, bool $archived): void
    {
        if (Presentation::checkPermission($presentationId, $userId) === null) {
            throw new InvalidArgumentException(t('dashboard.presentation_not_found'));
        }

        Storage::update(USERS_FILE, function (array $users) use ($userId, $presentationId, $archived) {
            foreach ($users as &$u) {
                if ($u['id'] !== $userId) {
                    continue;
                }
                $ids = is_array($u['dashboard_shared_archived'] ?? null) ? $u['dashboard_shared_archived'] : [];
                $ids = array_values(array_filter($ids, fn($id) => is_string($id) && $id !== ''));
                if ($archived) {
                    if (!in_array($presentationId, $ids, true)) {
                        $ids[] = $presentationId;
                    }
                } else {
                    $ids = array_values(array_filter($ids, fn($id) => $id !== $presentationId));
                }
                $u['dashboard_shared_archived'] = $ids;
            }
            unset($u);
            return $users;
        }, []);
    }

    /**
     * @param list<array<string,mixed>> $owned
     * @param list<array<string,mixed>> $shared
     * @return list<array<string,mixed>>
     */
    public static function buildView(string $userId, array $owned, array $shared, bool $archivedTab): array
    {
        $ownedById = self::indexPresentations($owned);
        $sharedById = self::indexPresentations($shared);
        $sections = self::ensureSections($userId, $owned, $shared);
        $archivedShared = array_flip(self::getSharedArchivedIds($userId));
        $out = [];

        foreach ($sections as $section) {
            if (!empty($section['is_shared_inbox']) && $shared === []) {
                continue;
            }

            $presentations = [];
            $activeCount = 0;
            $archivedCount = 0;

            foreach ($section['items'] as $pid) {
                $isOwned = isset($ownedById[$pid]);
                $isShared = isset($sharedById[$pid]);
                if (!$isOwned && !$isShared) {
                    continue;
                }
                $p = $isOwned ? $ownedById[$pid] : $sharedById[$pid];
                $archived = $isOwned ? !empty($p['archived']) : isset($archivedShared[$pid]);

                if ($archived) {
                    $archivedCount++;
                } else {
                    $activeCount++;
                }
                if ($archived !== $archivedTab) {
                    continue;
                }
                $presentations[] = $p + [
                    '_dashboard_owned' => $isOwned,
                    '_dashboard_archived' => $archived,
                ];
            }

            if (!empty($section['is_shared_inbox']) && $presentations === []) {
                continue;
            }

            $out[] = $section + [
                'presentations' => $presentations,
                'active_count' => $activeCount,
                'archived_count' => $archivedCount,
            ];
        }
        return $out;
    }

    public static function sectionTitle(array $section): string
    {
        if (!empty($section['is_shared_inbox'])) {
            return t('dashboard.shared_with_you');
        }
        if (!empty($section['is_default']) && trim((string)($section['title'] ?? '')) === '') {
            return t('dashboard.section_default');
        }
        $title = trim((string)($section['title'] ?? ''));
        return $title !== '' ? $title : t('dashboard.section_untitled');
    }

    public static function createSection(string $userId, string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException(t('dashboard.section_title_required'));
        }
        if (mb_strlen($title) > 80) {
            throw new InvalidArgumentException(t('dashboard.section_title_too_long'));
        }

        $created = null;
        self::saveSections($userId, function (array $sections) use ($title, &$created) {
            $sections = self::normalizeSections($sections);
            $maxOrder = 0;
            foreach ($sections as $s) {
                $maxOrder = max($maxOrder, (int)($s['sort_order'] ?? 0));
            }
            $created = self::makeSection(Storage::generateId(4), $title, $maxOrder + 1, false, false, []);
            $sections[] = $created;
            return $sections;
        });
        if ($created === null) {
            throw new RuntimeException(t('dashboard.section_save_failed'));
        }
        return $created;
    }

    public static function renameSection(string $userId, string $sectionId, string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException(t('dashboard.section_title_required'));
        }
        $updated = null;
        self::saveSections($userId, function (array $sections) use ($sectionId, $title, &$updated) {
            foreach ($sections as &$section) {
                if ($section['id'] !== $sectionId) {
                    continue;
                }
                if (!empty($section['is_shared_inbox'])) {
                    throw new InvalidArgumentException(t('dashboard.section_system_locked'));
                }
                $section['title'] = $title;
                $updated = $section;
            }
            unset($section);
            if ($updated === null) {
                throw new InvalidArgumentException(t('dashboard.section_not_found'));
            }
            return $sections;
        });
        return $updated;
    }

    public static function deleteSection(string $userId, string $sectionId): void
    {
        self::saveSections($userId, function (array $sections) use ($sectionId) {
            $found = null;
            foreach ($sections as $section) {
                if ($section['id'] === $sectionId) {
                    $found = $section;
                    break;
                }
            }
            if ($found === null) {
                throw new InvalidArgumentException(t('dashboard.section_not_found'));
            }
            if (!empty($found['is_shared_inbox'])) {
                throw new InvalidArgumentException(t('dashboard.section_system_locked'));
            }
            if (!empty($found['is_default'])) {
                throw new InvalidArgumentException(t('dashboard.section_default_locked'));
            }
            if (!empty($found['items'])) {
                throw new InvalidArgumentException(t('dashboard.section_not_empty'));
            }
            return array_values(array_filter($sections, fn($s) => $s['id'] !== $sectionId));
        });
    }

    /** @param list<string> $sectionIds */
    public static function reorderSections(string $userId, array $sectionIds): void
    {
        self::saveSections($userId, function (array $sections) use ($sectionIds) {
            $byId = [];
            $sharedInbox = null;
            foreach ($sections as $s) {
                if (!empty($s['is_shared_inbox'])) {
                    $sharedInbox = $s;
                    continue;
                }
                $byId[$s['id']] = $s;
            }
            $next = [];
            if ($sharedInbox !== null) {
                $next[] = $sharedInbox;
            }
            $order = 0;
            foreach ($sectionIds as $id) {
                if ($id === self::SHARED_INBOX_ID || !isset($byId[$id])) {
                    continue;
                }
                $byId[$id]['sort_order'] = $order++;
                $next[] = $byId[$id];
                unset($byId[$id]);
            }
            foreach ($byId as $s) {
                $s['sort_order'] = $order++;
                $next[] = $s;
            }
            return $next;
        });
    }

    public static function setSectionPrefs(string $userId, string $sectionId, ?bool $collapsed = null): array
    {
        $updated = null;
        self::saveSections($userId, function (array $sections) use ($sectionId, $collapsed, &$updated) {
            foreach ($sections as &$section) {
                if ($section['id'] !== $sectionId) {
                    continue;
                }
                if ($collapsed !== null) {
                    $section['collapsed'] = $collapsed;
                }
                $updated = $section;
            }
            unset($section);
            if ($updated === null) {
                throw new InvalidArgumentException(t('dashboard.section_not_found'));
            }
            return $sections;
        });
        return $updated;
    }

    /** @param list<string> $presentationIds */
    public static function reorderItems(string $userId, string $sectionId, array $presentationIds): void
    {
        self::saveSections($userId, function (array $sections) use ($sectionId, $presentationIds) {
            foreach ($sections as &$section) {
                if ($section['id'] !== $sectionId) {
                    continue;
                }
                $allowed = array_flip($section['items']);
                $next = [];
                foreach ($presentationIds as $pid) {
                    if (isset($allowed[$pid])) {
                        $next[] = $pid;
                        unset($allowed[$pid]);
                    }
                }
                foreach ($section['items'] as $pid) {
                    if (isset($allowed[$pid])) {
                        $next[] = $pid;
                    }
                }
                $section['items'] = $next;
                return $sections;
            }
            unset($section);
            throw new InvalidArgumentException(t('dashboard.section_not_found'));
        });
    }

    public static function moveItem(string $userId, string $presentationId, string $toSectionId, ?int $index = null): void
    {
        $perm = Presentation::checkPermission($presentationId, $userId);
        if ($perm === null || !Presentation::getMeta($presentationId)) {
            throw new InvalidArgumentException(t('dashboard.presentation_not_found'));
        }
        $isOwned = $perm === 'owner';

        self::saveSections($userId, function (array $sections) use ($presentationId, $toSectionId, $index, $isOwned) {
            $target = null;
            foreach ($sections as $s) {
                if ($s['id'] === $toSectionId) {
                    $target = $s;
                    break;
                }
            }
            if ($target === null) {
                throw new InvalidArgumentException(t('dashboard.section_not_found'));
            }
            if (!empty($target['is_shared_inbox']) && $isOwned) {
                throw new InvalidArgumentException(t('dashboard.cannot_move_owned_to_shared'));
            }

            foreach ($sections as &$section) {
                $section['items'] = array_values(array_filter(
                    $section['items'],
                    fn($pid) => $pid !== $presentationId
                ));
            }
            unset($section);

            foreach ($sections as &$section) {
                if ($section['id'] !== $toSectionId) {
                    continue;
                }
                $items = $section['items'];
                if ($index === null || $index < 0 || $index > count($items)) {
                    $items[] = $presentationId;
                } else {
                    array_splice($items, $index, 0, [$presentationId]);
                }
                $section['items'] = $items;
                return $sections;
            }
            unset($section);
            throw new InvalidArgumentException(t('dashboard.section_not_found'));
        });
    }

    /** Archiv-Flag ist unabhängig von der Bereichszuordnung. */
    public static function syncArchiveState(string $userId, string $presentationId, bool $archived): void
    {
        unset($userId, $presentationId, $archived);
    }

    public static function removePresentation(string $userId, string $presentationId): void
    {
        self::saveSections($userId, function (array $sections) use ($presentationId) {
            foreach ($sections as &$section) {
                $section['items'] = array_values(array_filter(
                    $section['items'],
                    fn($pid) => $pid !== $presentationId
                ));
            }
            unset($section);
            return $sections;
        });
    }

    /**
     * @param list<array<string,mixed>> $sections
     * @param array<string,array<string,mixed>> $ownedById
     * @param array<string,array<string,mixed>> $sharedById
     * @return list<array<string,mixed>>
     */
    private static function reconcileAllSections(array $sections, array $ownedById, array $sharedById): array
    {
        $sections = self::migrateSystemSections(self::normalizeSections($sections));

        $sharedInbox = null;
        $custom = [];
        foreach ($sections as $section) {
            if (!empty($section['is_shared_inbox']) || $section['id'] === self::SHARED_INBOX_ID) {
                $sharedInbox = $section;
                continue;
            }
            $custom[] = $section;
        }

        $custom = self::reconcileOwnedSections($custom, $ownedById);

        $placedShared = [];
        foreach ($custom as &$section) {
            $items = [];
            foreach ($section['items'] as $pid) {
                if (isset($ownedById[$pid])) {
                    $items[] = $pid;
                } elseif (isset($sharedById[$pid])) {
                    $items[] = $pid;
                    $placedShared[$pid] = true;
                }
            }
            $section['items'] = $items;
        }
        unset($section);

        $inboxItems = [];
        if ($sharedInbox !== null) {
            foreach ($sharedInbox['items'] as $pid) {
                if (isset($sharedById[$pid]) && empty($placedShared[$pid])) {
                    $inboxItems[] = $pid;
                    $placedShared[$pid] = true;
                }
            }
        }
        foreach (array_keys($sharedById) as $pid) {
            if (empty($placedShared[$pid])) {
                $inboxItems[] = $pid;
            }
        }

        $out = [];
        if ($inboxItems !== [] && $sharedById !== []) {
            $out[] = self::makeSharedInboxSection(
                $inboxItems,
                !empty($sharedInbox['collapsed'])
            );
        }

        usort($custom, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        foreach ($custom as $i => &$section) {
            $section['sort_order'] = $i;
        }
        unset($section);

        return array_merge($out, $custom);
    }

    /**
     * @param list<array<string,mixed>> $presentations
     * @return array<string,array<string,mixed>>
     */
    private static function indexPresentations(array $presentations): array
    {
        $byId = [];
        foreach ($presentations as $p) {
            if (!empty($p['id'])) {
                $byId[$p['id']] = $p;
            }
        }
        return $byId;
    }

    /** @param list<string> $items */
    private static function makeSharedInboxSection(array $items, bool $collapsed): array
    {
        return [
            'id' => self::SHARED_INBOX_ID,
            'title' => '',
            'sort_order' => -1000,
            'collapsed' => $collapsed,
            'is_default' => false,
            'is_shared_inbox' => true,
            'items' => array_values($items),
        ];
    }

    /**
     * @param list<array<string,mixed>> $sections
     * @param array<string,array<string,mixed>> $ownedById
     * @return list<array<string,mixed>>
     */
    private static function reconcileOwnedSections(array $sections, array $ownedById): array
    {
        if ($sections === []) {
            $sections[] = self::makeSection(
                Storage::generateId(4),
                '',
                0,
                false,
                true,
                array_keys($ownedById)
            );
        }

        $defaultIdx = self::findDefaultSectionIndex($sections);
        if ($defaultIdx === null) {
            $sections[] = self::makeSection(Storage::generateId(4), '', count($sections), false, true, []);
            $defaultIdx = count($sections) - 1;
        }

        $placed = [];
        foreach ($sections as &$section) {
            $items = [];
            foreach ($section['items'] as $pid) {
                if (isset($ownedById[$pid])) {
                    $items[] = $pid;
                    $placed[$pid] = true;
                } else {
                    $items[] = $pid;
                }
            }
            $section['items'] = $items;
        }
        unset($section);

        foreach ($ownedById as $pid => $p) {
            if (!empty($placed[$pid])) {
                continue;
            }
            $sections[$defaultIdx]['items'][] = $pid;
            $placed[$pid] = true;
        }

        usort($sections, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        foreach ($sections as $i => &$section) {
            $section['sort_order'] = $i;
        }
        unset($section);

        return $sections;
    }

    /**
     * @param list<array<string,mixed>> $sections
     * @return list<array<string,mixed>>
     */
    private static function migrateSystemSections(array $sections): array
    {
        $custom = [];
        $systemItems = [];
        $hasSystem = false;
        foreach ($sections as $s) {
            $sys = $s['system'] ?? null;
            if ($sys === self::SYSTEM_ACTIVE || $sys === self::SYSTEM_ARCHIVE) {
                $hasSystem = true;
                $systemItems = array_merge($systemItems, $s['items'] ?? []);
                continue;
            }
            unset($s['system']);
            $custom[] = $s;
        }
        if (!$hasSystem) {
            return $custom;
        }

        $inCustom = [];
        foreach ($custom as &$c) {
            foreach ($c['items'] as $pid) {
                $inCustom[$pid] = true;
            }
        }
        unset($c);

        if ($custom === []) {
            return [self::makeSection(
                Storage::generateId(4),
                '',
                0,
                false,
                true,
                array_values(array_unique($systemItems))
            )];
        }

        $defaultIdx = self::findDefaultSectionIndex($custom);
        if ($defaultIdx === null) {
            $custom[] = self::makeSection(Storage::generateId(4), '', 0, false, true, []);
            $defaultIdx = 0;
        }

        foreach (array_unique($systemItems) as $pid) {
            if (empty($inCustom[$pid])) {
                $custom[$defaultIdx]['items'][] = $pid;
            }
        }

        return $custom;
    }

    /** @param list<string> $items */
    private static function makeSection(
        string $id,
        string $title,
        int $sortOrder,
        bool $collapsed,
        bool $isDefault,
        array $items
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'sort_order' => $sortOrder,
            'collapsed' => $collapsed,
            'is_default' => $isDefault,
            'items' => array_values($items),
        ];
    }

    /** @param list<array<string,mixed>> $sections */
    private static function normalizeSections(array $sections): array
    {
        $out = [];
        foreach ($sections as $section) {
            if (empty($section['id'])) {
                continue;
            }
            $out[] = [
                'id' => (string)$section['id'],
                'title' => (string)($section['title'] ?? ''),
                'sort_order' => (int)($section['sort_order'] ?? 0),
                'collapsed' => !empty($section['collapsed']),
                'is_default' => !empty($section['is_default']),
                'is_shared_inbox' => !empty($section['is_shared_inbox']) || $section['id'] === self::SHARED_INBOX_ID,
                'items' => array_values(array_filter(
                    is_array($section['items'] ?? null) ? $section['items'] : [],
                    fn($id) => is_string($id) && $id !== ''
                )),
            ];
        }
        return $out;
    }

    /** @param list<array<string,mixed>> $sections */
    private static function findDefaultSectionIndex(array $sections): ?int
    {
        foreach ($sections as $i => $section) {
            if (!empty($section['is_default'])) {
                return $i;
            }
        }
        return $sections === [] ? null : 0;
    }

    /** @param callable(list<array<string,mixed>>): list<array<string,mixed>> $mutator */
    private static function saveSections(string $userId, callable $mutator): array
    {
        $result = [];
        Storage::update(USERS_FILE, function (array $users) use ($userId, $mutator, &$result) {
            foreach ($users as &$u) {
                if ($u['id'] !== $userId) {
                    continue;
                }
                $sections = self::normalizeSections(is_array($u['dashboard_sections'] ?? null) ? $u['dashboard_sections'] : []);
                $sections = $mutator($sections);
                $u['dashboard_sections'] = $sections;
                $result = $sections;
            }
            unset($u);
            return $users;
        }, []);
        return $result;
    }
}
