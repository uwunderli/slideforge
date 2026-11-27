<?php
/**
 * Personen und ihre Einstellungen (data/users.json). Anmeldung nur über den Hub (Hub-Pfad, 27.11.2026):
 * Die Sitzung kommt aus `SharedAuth` (Hub-Cookie bzw. hub_session), die Benutzer-ID ist der Personenschlüssel
 * des Hubs (`ct:<id>` bzw. `u:<name>`). Kein eigenes Login, keine Registrierung, keine Passwörter.
 * Administrator ist, wer im Hub in der Gruppe `admins` steht.
 */
class Auth
{
    private const HUB_SESSION_KEY = 'hub_user';

    /**
     * Sitzung vom Hub übernehmen (aus config.php, vor jeder Seite). Ohne Hub-Anmeldung bleibt niemand angemeldet.
     */
    public static function bootstrapHub(): void
    {
        if (!class_exists('SharedAuth')) {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_admin']);
            return;
        }
        $payload = SharedAuth::bootstrapModule(self::HUB_SESSION_KEY);
        if (!is_array($payload) || $payload === []) {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_admin']);
            return;
        }
        $user = self::ensureFromHubPayload($payload);
        if ($user === null) {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_admin']);
            return;
        }
        $_SESSION['user_id'] = (string)$user['id'];
        $_SESSION['username'] = (string)($user['username'] ?? '');
        $_SESSION['is_admin'] = self::payloadIsAdmin($payload);
    }

    /** Personenschlüssel des Hubs aus der Anmeldung (`ct:<id>` bzw. `u:<name>`), leer wenn keiner. */
    public static function personKeyFromPayload(array $payload): string
    {
        $ct = (int)($payload['ct_person_id'] ?? 0);
        if ($ct <= 0 && ctype_digit((string)($payload['sub'] ?? ''))) {
            $ct = (int)$payload['sub'];
        }
        if (class_exists('HubPersonKey')) {
            return HubPersonKey::fromUser(['person_id' => $ct, 'username' => (string)($payload['username'] ?? '')]);
        }
        $name = strtolower(trim((string)($payload['username'] ?? '')));
        return $ct > 0 ? 'ct:' . $ct : ($name !== '' ? 'u:' . $name : '');
    }

    /** @param array<string,mixed> $payload */
    private static function payloadIsAdmin(array $payload): bool
    {
        $groups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];
        foreach ($groups as $g) {
            if (strcasecmp(trim((string)$g), 'admins') === 0) {
                return true;
            }
        }
        return false;
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Jede Sitzung kommt vom Hub. */
    public static function isViaHub(): bool
    {
        return self::isLoggedIn();
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn() || self::currentUser() === null) {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_admin']);
            if (class_exists('SharedAuth')) {
                SharedAuth::requireModuleLogin(self::HUB_SESSION_KEY);
            }
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Login erforderlich — bitte über den Hub anmelden.';
            exit;
        }
    }

    /** Gibt den aktuell eingeloggten User als Array zurück, oder null. */
    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        return self::findById((string)$_SESSION['user_id']);
    }

    public static function findById(string $id): ?array
    {
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if ((string)($u['id'] ?? '') === $id) {
                return $u;
            }
        }
        return null;
    }

    public static function findByUsername(string $username): ?array
    {
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if (strcasecmp((string)($u['username'] ?? ''), $username) === 0) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Person aus der Hub-Anmeldung finden oder anlegen; ID = Personenschlüssel des Hubs.
     * Anzeigename, E-Mail und Bild werden bei jeder Anmeldung vom Hub übernommen.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public static function ensureFromHubPayload(array $payload): ?array
    {
        $key = self::personKeyFromPayload($payload);
        if ($key === '') {
            return null;
        }
        $username = trim((string)($payload['username'] ?? ''));
        $display = trim((string)($payload['display_name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }
        $avatarUrl = trim((string)($payload['avatar_url'] ?? ''));
        $role = self::payloadIsAdmin($payload) ? 'admin' : 'editor';
        $firstUser = false;
        Storage::update(USERS_FILE, function ($users) use ($key, $username, $display, $email, $avatarUrl, $role, &$firstUser) {
            $users = is_array($users) ? $users : [];
            foreach ($users as &$u) {
                if ((string)($u['id'] ?? '') !== $key) {
                    continue;
                }
                $u['username'] = $username !== '' ? $username : (string)($u['username'] ?? $key);
                if ($display !== '') {
                    $u['display_name'] = $display;
                }
                if ($email !== '') {
                    $u['email'] = $email;
                }
                $u['avatar_url'] = $avatarUrl;
                $u['role'] = $role;
                return $users;
            }
            unset($u);
            $firstUser = $users === [];
            $users[] = [
                'id' => $key,
                'username' => $username !== '' ? $username : $key,
                'email' => $email,
                'display_name' => $display !== '' ? $display : ($username !== '' ? $username : $key),
                'avatar_url' => $avatarUrl,
                'role' => $role,
                'auth_source' => 'churchforge_hub',
                'created_at' => date('c'),
            ];
            return $users;
        }, []);
        if ($firstUser) {
            Presentation::seedDefaultTemplates($key);
        }
        return self::findById($key);
    }

    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && !empty($_SESSION['is_admin']);
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Nur für Administratoren zugänglich.');
        }
    }

    public static function setLanguage(string $userId, string $language): void
    {
        if (!isset(I18n::SUPPORTED[$language])) {
            $language = 'de';
        }
        Storage::update(USERS_FILE, function ($users) use ($userId, $language) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['language'] = $language;
                }
            }
            return $users;
        }, []);
    }

    /** Standard-Reihenfolge und Sichtbarkeit der Steuerungsleiste im Präsentationsmodus. */
    public static function defaultPresentPanels(): array
    {
        return [
            ['id' => 'next', 'visible' => true],
            ['id' => 'clock', 'visible' => true],
            ['id' => 'timer', 'visible' => true],
            ['id' => 'media', 'visible' => true],
            ['id' => 'slides', 'visible' => true],
        ];
    }

    public static function normalizePresentPanels($panels): array
    {
        $allowed = array_column(self::defaultPresentPanels(), 'id');
        if (!is_array($panels)) {
            return self::defaultPresentPanels();
        }
        $byId = [];
        foreach ($panels as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? '';
            if (!in_array($id, $allowed, true) || isset($byId[$id])) {
                continue;
            }
            $byId[$id] = ['id' => $id, 'visible' => !array_key_exists('visible', $item) || (bool)$item['visible']];
        }
        $result = [];
        foreach ($panels as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? '';
            if (!isset($byId[$id])) {
                continue;
            }
            $seen = array_column($result, 'id');
            if (!in_array($id, $seen, true)) {
                $result[] = $byId[$id];
            }
        }
        foreach ($allowed as $id) {
            if (!in_array($id, array_column($result, 'id'), true)) {
                $result[] = $byId[$id] ?? ['id' => $id, 'visible' => true];
            }
        }
        return $result;
    }

    public static function getPresentPanels(?array $user = null): array
    {
        $user = $user ?? self::currentUser();
        return self::normalizePresentPanels($user['present_panels'] ?? null);
    }

    /** @return array<int, array{id: string, visible: bool}> */
    public static function setPresentPanels(string $userId, array $panels): array
    {
        $normalized = self::normalizePresentPanels($panels);
        Storage::update(USERS_FILE, function ($users) use ($userId, $normalized) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['present_panels'] = $normalized;
                }
            }
            return $users;
        }, []);
        return $normalized;
    }

    /** Spalten- und Modulgrössen im Präsentationsmodus (pro Benutzer). */
    public static function defaultPresentLayout(): array
    {
        return [
            'colWeights' => ['main' => 3, 'side' => 1],
            'timebarPx' => 110,
            'showTimebar' => true,
            'panelHeights' => [],
            'panelOpen' => [],
            'clockOrder' => Presentation::defaultClockOrder(),
            'timebarStops' => Presentation::defaultTimebarStops(),
            'laserPointerColor' => '#ff0000',
            'laserPointerSize' => 24,
            'laserPointerTrail' => false,
            'laserPointerEnabled' => true,
            'showSlideGhost' => true,
            'slideGhostOpacity' => 25,
            'notesMode' => 'carry',
            'notesCollapsed' => false,
            'mediaCollapsed' => false,
            'dockTab' => 'notes',
        ];
    }

    private static function normalizeNotesMode($mode): string
    {
        return in_array($mode, ['always_open', 'always_closed', 'carry'], true) ? $mode : 'carry';
    }

    private static function normalizeLaserPointerTrail($trail): bool
    {
        return (bool)$trail;
    }

    private static function normalizeLaserPointerColor($color): string
    {
        if (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }
        return '#ff0000';
    }

    private static function normalizeLaserPointerSize($size): int
    {
        $n = is_numeric($size) ? (int)$size : 24;
        return max(8, min(64, $n));
    }

    public static function normalizePresentLayout($layout): array
    {
        $defaults = self::defaultPresentLayout();
        if (!is_array($layout)) {
            return $defaults;
        }
        $weights = $defaults['colWeights'];
        if (isset($layout['colWeights']) && is_array($layout['colWeights'])) {
            $legacyNotes = isset($layout['colWeights']['notes']) ? (float)$layout['colWeights']['notes'] : 0;
            foreach (['main', 'side'] as $key) {
                if (isset($layout['colWeights'][$key])) {
                    $weights[$key] = max(0.15, min(20, (float)$layout['colWeights'][$key]));
                }
            }
            if ($legacyNotes > 0) {
                $weights['main'] = max(0.15, min(20, $weights['main'] + $legacyNotes * 0.65));
                $weights['side'] = max(0.15, min(20, $weights['side'] + $legacyNotes * 0.35));
            }
        }
        $timebarPx = isset($layout['timebarPx']) ? (int)$layout['timebarPx'] : $defaults['timebarPx'];
        $timebarPx = max(100, min(220, $timebarPx));
        $showTimebar = !array_key_exists('showTimebar', $layout) || (bool)$layout['showTimebar'];
        $allowedPanels = ['next', 'clock', 'timer', 'slides', 'media'];
        $panelHeights = [];
        if (isset($layout['panelHeights']) && is_array($layout['panelHeights'])) {
            foreach ($layout['panelHeights'] as $id => $h) {
                if (!in_array($id, $allowedPanels, true)) {
                    continue;
                }
                if ($id === 'next') {
                    continue;
                }
                $px = (int)$h;
                if ($px >= 72) {
                    $panelHeights[$id] = min(900, $px);
                }
            }
        }
        $panelOpen = [];
        if (isset($layout['panelOpen']) && is_array($layout['panelOpen'])) {
            foreach ($layout['panelOpen'] as $id => $open) {
                if (!in_array($id, $allowedPanels, true)) {
                    continue;
                }
                $panelOpen[$id] = (bool)$open;
            }
        }
        return [
            'colWeights' => $weights,
            'timebarPx' => $timebarPx,
            'showTimebar' => $showTimebar,
            'panelHeights' => $panelHeights,
            'panelOpen' => $panelOpen,
            'clockOrder' => Presentation::normalizeClockOrder($layout['clockOrder'] ?? null),
            'timebarStops' => Presentation::normalizeTimebarStops($layout['timebarStops'] ?? null),
            'laserPointerColor' => self::normalizeLaserPointerColor($layout['laserPointerColor'] ?? null),
            'laserPointerSize' => self::normalizeLaserPointerSize($layout['laserPointerSize'] ?? null),
            'laserPointerTrail' => self::normalizeLaserPointerTrail($layout['laserPointerTrail'] ?? null),
            'laserPointerEnabled' => array_key_exists('laserPointerEnabled', $layout)
                ? (bool)$layout['laserPointerEnabled']
                : $defaults['laserPointerEnabled'],
            'showSlideGhost' => array_key_exists('showSlideGhost', $layout)
                ? (bool)$layout['showSlideGhost']
                : $defaults['showSlideGhost'],
            'slideGhostOpacity' => max(5, min(80, (int)($layout['slideGhostOpacity'] ?? $defaults['slideGhostOpacity']))),
            'notesMode' => self::normalizeNotesMode($layout['notesMode'] ?? $defaults['notesMode']),
            'notesCollapsed' => array_key_exists('notesCollapsed', $layout)
                ? (bool)$layout['notesCollapsed']
                : $defaults['notesCollapsed'],
            'mediaCollapsed' => array_key_exists('mediaCollapsed', $layout)
                ? (bool)$layout['mediaCollapsed']
                : $defaults['mediaCollapsed'],
            'dockTab' => in_array(($layout['dockTab'] ?? null), ['notes', 'media'], true)
                ? $layout['dockTab']
                : $defaults['dockTab'],
        ];
    }

    public static function getPresentLayout(?array $user = null): array
    {
        $user = $user ?? self::currentUser();
        return self::normalizePresentLayout($user['present_layout'] ?? null);
    }

    public static function setPresentLayout(string $userId, array $layout): array
    {
        $user = self::findById($userId);
        $base = is_array($user['present_layout'] ?? null) ? $user['present_layout'] : [];
        $merged = array_merge($base, $layout);
        $normalized = self::normalizePresentLayout($merged);
        Storage::update(USERS_FILE, function ($users) use ($userId, $normalized) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['present_layout'] = $normalized;
                }
            }
            return $users;
        }, []);
        return $normalized;
    }

    /** @return array<int, array> alle User, absteigend nach Erstellungsdatum */
    public static function listAll(): array
    {
        $users = Storage::read(USERS_FILE, []);
        usort($users, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $users;
    }

    public static function spellcheckBrowserEnabled(?array $user = null): bool
    {
        $user = $user ?? self::currentUser();
        if (!$user) {
            return true;
        }
        return ($user['spellcheck_browser'] ?? true) !== false;
    }

    /** Sprachen für LanguageTool (inkl. Varianten wie de-CH, ohne eigene UI-Sprache). */
    public const SPELLCHECK_LANGUAGES = [
        'de' => 'Deutsch',
        'de-CH' => 'Deutsch (Schweiz)',
        'en' => 'English',
        'fr' => 'Français',
        'it' => 'Italiano',
        'rm' => 'Rumantsch',
    ];

    public static function isSpellcheckLanguage(string $lang): bool
    {
        return isset(self::SPELLCHECK_LANGUAGES[$lang]);
    }

    public static function spellcheckLanguage(?array $user = null): string
    {
        $user = $user ?? self::currentUser();
        if (!$user) {
            return 'de';
        }
        $override = trim((string)($user['spellcheck_lang'] ?? ''));
        if ($override !== '' && self::isSpellcheckLanguage($override)) {
            return $override;
        }
        $lang = trim((string)($user['language'] ?? 'de'));
        return isset(I18n::SUPPORTED[$lang]) ? $lang : 'de';
    }

    public static function spellcheckBeforePresent(?array $user = null): bool
    {
        $user = $user ?? self::currentUser();
        if (!$user) {
            return true;
        }
        return ($user['spellcheck_before_present'] ?? true) !== false;
    }

    public static function setSpellcheckPrefs(string $userId, bool $browserEnabled, string $langOverride, bool $beforePresent): void
    {
        $langOverride = trim($langOverride);
        if ($langOverride !== '' && !self::isSpellcheckLanguage($langOverride)) {
            $langOverride = '';
        }
        Storage::update(USERS_FILE, function ($users) use ($userId, $browserEnabled, $langOverride, $beforePresent) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['spellcheck_browser'] = $browserEnabled;
                    $u['spellcheck_lang'] = $langOverride;
                    $u['spellcheck_before_present'] = $beforePresent;
                }
            }
            return $users;
        }, []);
    }

    public static function setSpellcheckBeforePresent(string $userId, bool $beforePresent): void
    {
        Storage::update(USERS_FILE, function ($users) use ($userId, $beforePresent) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['spellcheck_before_present'] = $beforePresent;
                }
            }
            return $users;
        }, []);
    }

    public static function logosImporterEnabled(?array $user = null): bool
    {
        $user = $user ?? self::currentUser();
        if (!$user) {
            return false;
        }
        return !empty($user['logos_importer_enabled']);
    }

    public static function setLogosImporterEnabled(string $userId, bool $enabled): void
    {
        Storage::update(USERS_FILE, function ($users) use ($userId, $enabled) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['logos_importer_enabled'] = $enabled;
                }
            }
            return $users;
        }, []);
    }

    /** Logos-Zuordnung im Editor: standardmässig offen, danach Nutzerpräferenz. */
    public static function logosZonesAccordionOpen(?array $user = null): bool
    {
        $user = $user ?? self::currentUser();
        if (!$user) {
            return true;
        }
        if (!array_key_exists('logos_zones_accordion_open', $user)) {
            return true;
        }
        return (bool)$user['logos_zones_accordion_open'];
    }

    public static function setLogosZonesAccordionOpen(string $userId, bool $open): void
    {
        Storage::update(USERS_FILE, function ($users) use ($userId, $open) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['logos_zones_accordion_open'] = $open;
                }
            }
            return $users;
        }, []);
    }

    /**
     * Fenster-Stil und Akzent des Hubs, je Hell/Dunkel (Sand-Flackern beim Neuladen, Urs 27.11.2026, wie OF 16.10.):
     * cf-theme.js prüft die Nachricht des Hub-Rahmens, SlideForge merkt sich das Paar hier, damit schon das erste Bild stimmt.
     * Nur Listenwerte bzw. `#rrggbb`; sonst null.
     *
     * @return array{chrome:array{light:string,dark:string},accent:array{light:string,dark:string}}|null
     */
    public static function normalizeHubLook($look): ?array
    {
        if (!is_array($look) || !is_array($look['chrome'] ?? null) || !is_array($look['accent'] ?? null)) {
            return null;
        }
        $out = ['chrome' => [], 'accent' => []];
        foreach (['light', 'dark'] as $mode) {
            $c = (string)($look['chrome'][$mode] ?? '');
            $a = strtolower(trim((string)($look['accent'][$mode] ?? '')));
            if (!in_array($c, ['klassisch', 'flach'], true)) {
                return null;
            }
            if (!in_array($a, ['', 'blau', 'gruen', 'indigo', 'terracotta'], true) && preg_match('/^#[0-9a-f]{6}\z/', $a) !== 1) {
                return null;
            }
            $out['chrome'][$mode] = $c;
            $out['accent'][$mode] = $a;
        }
        return $out;
    }

    /** Gemerkter Hub-Look der Person (siehe normalizeHubLook), null wenn noch keiner. */
    public static function hubLook(?array $user = null): ?array
    {
        $user = $user ?? self::currentUser();
        return $user ? self::normalizeHubLook($user['hub_look'] ?? null) : null;
    }

    public static function setHubLook(string $userId, $look): ?array
    {
        $normalized = self::normalizeHubLook($look);
        if ($normalized === null) {
            return null;
        }
        Storage::update(USERS_FILE, function ($users) use ($userId, $normalized) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['hub_look'] = $normalized;
                }
            }
            return $users;
        }, []);
        return $normalized;
    }

    /** Mindestbreite der Raster-Miniaturen im Editor (px, pro Benutzer). */
    public static function editorGridThumbMin(?array $user = null): int
    {
        $user = $user ?? self::currentUser();
        if (!$user) {
            return 168;
        }
        return self::normalizeEditorGridThumbMin($user['editor_grid_thumb_min'] ?? null);
    }

    public static function normalizeEditorGridThumbMin($value): int
    {
        $n = is_numeric($value) ? (int)$value : 168;
        return max(100, min(360, $n));
    }

    public static function setEditorGridThumbMin(string $userId, int $thumbMin): int
    {
        $normalized = self::normalizeEditorGridThumbMin($thumbMin);
        Storage::update(USERS_FILE, function ($users) use ($userId, $normalized) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['editor_grid_thumb_min'] = $normalized;
                }
            }
            return $users;
        }, []);
        return $normalized;
    }

    /** Editor-Seitenleisten (Tabs/Subtabs/Objektpanel), pro Benutzer. */
    public static function defaultEditorUi(): array
    {
        return [
            'sideTab' => 'templates',
            'posSubtab' => 'layout',
            'formatSubtab' => 'text',
            /* Effekte-Reiter: zuletzt benutzter Untertab bei gewähltem Objekt (3.12.5): in | out | order */
            'fxSubtab' => 'in',
            'objectOpen' => true,
            'notesHeight' => 140,
            /* Spaltenbreiten der Editor-Trenner (HubSplit, Schlüssel ohne «split:»), Pixel. */
            'splits' => [],
            /* Eingeklappte Seitenleisten (HubSplit-Griff, Schlüssel wie bei splits), nur true-Einträge. */
            'collapsed' => [],
            /* Mitlaufen: Geräte spielen beim Folienwechsel die Effekte ab (3.11.0, Dialog «Auf Gerät»). */
            'mitAnim' => false,
            /* Zuletzt benutzte Einstellungen des Logos-Import-Dialogs. Bewusst nur die
               dokumentunabhängigen Werte - Abwahl einzelner Folien und eigene Notizen
               gehören zu einer bestimmten Predigt und werden nicht gemerkt. */
            'import' => [
                'sectionId' => '',
                'layoutSetId' => '',
                'outline' => 'set',
                'scripture' => 'set',
                'bullets' => 'set',
                'outlineDepth' => 0,
                'scriptureTranslation' => false,
                'agendaMain' => false,
                'outlineSummary' => 0,
                'scriptureMerge' => false,
                'hierarchyNotes' => false,
                'hierarchyStyle' => 'headings',
                'swissSs' => false,
                'agendaColor' => '#b7de45',
            ],
        ];
    }

    public static function normalizeEditorUi($ui): array
    {
        $defaults = self::defaultEditorUi();
        if (!is_array($ui)) {
            return $defaults;
        }
        $sideTabs = ['templates', 'format', 'position', 'effects', 'spell', 'media', 'clipboard'];
        $sideTab = (string)($ui['sideTab'] ?? $defaults['sideTab']);
        if (!in_array($sideTab, $sideTabs, true)) {
            $sideTab = $defaults['sideTab'];
        }
        $posSubtab = (($ui['posSubtab'] ?? '') === 'layers') ? 'layers' : 'layout';
        $formatSubtab = (string)($ui['formatSubtab'] ?? $defaults['formatSubtab']);
        if (!in_array($formatSubtab, ['text', 'templates', 'format'], true)) {
            $formatSubtab = $defaults['formatSubtab'];
        }
        $fxSubtab = (string)($ui['fxSubtab'] ?? $defaults['fxSubtab']);
        if (!in_array($fxSubtab, ['in', 'out', 'order'], true)) {
            $fxSubtab = $defaults['fxSubtab'];
        }
        return [
            'sideTab' => $sideTab,
            'posSubtab' => $posSubtab,
            'formatSubtab' => $formatSubtab,
            'fxSubtab' => $fxSubtab,
            'objectOpen' => array_key_exists('objectOpen', $ui)
                ? (bool)$ui['objectOpen']
                : $defaults['objectOpen'],
            /* Höhe des Notizfeldes in Pixeln, per Ziehen an der Trennlinie gesetzt. */
            'notesHeight' => max(60, min(900, (int)($ui['notesHeight'] ?? $defaults['notesHeight']))),
            'splits' => self::normalizeSplits($ui['splits'] ?? null),
            'collapsed' => self::normalizeCollapsed($ui['collapsed'] ?? null),
            'mitAnim' => !empty($ui['mitAnim']),
            'import' => self::normalizeImportUi($ui['import'] ?? null, $defaults['import']),
        ];
    }

    /**
     * Eingeklappte Spalten aus dem Browser: nur einfache Schlüssel, nur «true» wird gemerkt, höchstens 16 Einträge.
     */
    private static function normalizeCollapsed($collapsed): array
    {
        if (!is_array($collapsed)) {
            return [];
        }
        $out = [];
        foreach ($collapsed as $k => $v) {
            if (count($out) >= 16) {
                break;
            }
            $k = (string)$k;
            if (preg_match('/^[a-z0-9_]{1,40}$/', $k) && ($v === true || $v === 1 || $v === '1')) {
                $out[$k] = true;
            }
        }
        return $out;
    }

    /**
     * Trennerbreiten aus dem Browser: nur einfache Schlüssel, ganze Pixel, höchstens 16 Einträge.
     */
    private static function normalizeSplits($splits): array
    {
        if (!is_array($splits)) {
            return [];
        }
        $out = [];
        foreach ($splits as $k => $v) {
            if (count($out) >= 16) {
                break;
            }
            $k = (string)$k;
            if (!preg_match('/^[a-z0-9_]{1,40}$/', $k) || !is_numeric($v)) {
                continue;
            }
            $out[$k] = max(80, min(2000, (int)$v));
        }
        return $out;
    }

    /**
     * Nur bekannte Werte durchlassen: Das landet in der Benutzerdatei und kommt aus
     * dem Browser.
     */
    private static function normalizeImportUi($ui, array $defaults): array
    {
        if (!is_array($ui)) {
            return $defaults;
        }
        $out = $defaults;
        foreach (['sectionId', 'layoutSetId'] as $k) {
            $v = (string)($ui[$k] ?? '');
            if (preg_match('/^[a-zA-Z0-9_-]{0,64}$/', $v)) {
                $out[$k] = $v;
            }
        }
        foreach (['outline', 'scripture', 'bullets'] as $k) {
            $v = (string)($ui[$k] ?? '');
            if (in_array($v, ['set', 'slides', 'notes', 'off'], true)) {
                $out[$k] = $v;
            }
        }
        $d = (int)($ui['outlineDepth'] ?? 0);
        $out['outlineDepth'] = ($d >= 1 && $d <= 5) ? $d : 0;
        $out['scriptureTranslation'] = !empty($ui['scriptureTranslation']);
        $out['agendaMain'] = !empty($ui['agendaMain']);
        $out['scriptureMerge'] = !empty($ui['scriptureMerge']);
        $ab = (int)($ui['outlineSummary'] ?? 0);
        $out['outlineSummary'] = ($ab >= 2 && $ab <= 5) ? $ab : 0;
        $out['hierarchyNotes'] = !empty($ui['hierarchyNotes']);
        $stil = strtolower(trim((string)($ui['hierarchyStyle'] ?? '')));
        if (in_array($stil, ['headings', 'path'], true)) {
            $out['hierarchyStyle'] = $stil;
        }
        $out['swissSs'] = !empty($ui['swissSs']);
        $farbe = strtolower(trim((string)($ui['agendaColor'] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $farbe)) {
            $out['agendaColor'] = $farbe;
        }
        return $out;
    }

    public static function getEditorUi(?array $user = null): array
    {
        $user = $user ?? self::currentUser();
        return self::normalizeEditorUi($user['editor_ui'] ?? null);
    }

    public static function setEditorUi(string $userId, array $ui): array
    {
        $user = self::findById($userId);
        $base = is_array($user['editor_ui'] ?? null) ? $user['editor_ui'] : [];
        $normalized = self::normalizeEditorUi(array_merge($base, $ui));
        Storage::update(USERS_FILE, function ($users) use ($userId, $normalized) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['editor_ui'] = $normalized;
                }
            }
            return $users;
        }, []);
        return $normalized;
    }

}
