<?php
/**
 * Benutzerverwaltung auf Basis von data/users.json
 */
class Auth
{
    /** Zwischenspeicher: User-ID, für die nach dem Speichern (ausserhalb des Storage::update-Locks) die Standard-Folienvorlagen angelegt werden sollen. */
    private static ?string $seedTemplatesFor = null;

    public static function register(string $username, string $email, string $password, string $inviteToken = ''): array
    {
        $username = trim($username);
        $email = trim($email);

        if (strlen($username) < 3) {
            return [false, 'Benutzername muss mindestens 3 Zeichen lang sein.'];
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            return [false, 'Benutzername darf nur Buchstaben, Zahlen, _ - . enthalten.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Bitte eine gültige E-Mail-Adresse angeben.'];
        }
        if (strlen($password) < 6) {
            return [false, 'Passwort muss mindestens 6 Zeichen lang sein.'];
        }

        // Registrierung ist entweder generell offen, oder es braucht einen gültigen Einladungslink.
        $existingCount = count(Storage::read(USERS_FILE, []));
        if ($existingCount > 0 && !Config::registrationEnabled()) {
            if ($inviteToken === '' || !InviteToken::isValid($inviteToken)) {
                return [false, 'Die Registrierung ist derzeit deaktiviert. Bitte einen gültigen Einladungslink verwenden.'];
            }
        }

        $result = [true, ''];
        $newUserId = null;
        Storage::update(USERS_FILE, function ($users) use ($username, $email, $password, &$result, &$newUserId) {
            foreach ($users as $u) {
                if (strcasecmp($u['username'], $username) === 0) {
                    $result = [false, 'Dieser Benutzername ist bereits vergeben.'];
                    return $users;
                }
                if (strcasecmp($u['email'], $email) === 0) {
                    $result = [false, 'Diese E-Mail-Adresse wird bereits verwendet.'];
                    return $users;
                }
            }
            $newUserId = Storage::generateId(6);
            $isFirstUser = empty($users);
            $emailVerified = $isFirstUser;
            $users[] = [
                'id' => $newUserId,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                // Der allererste registrierte User wird automatisch Administrator,
                // damit überhaupt jemand Zugriff auf die Einstellungen hat.
                'role' => $isFirstUser ? 'admin' : 'editor',
                'theme' => 'dark',
                'email_verified' => $emailVerified,
                'created_at' => date('c'),
            ];
            if ($isFirstUser) {
                self::$seedTemplatesFor = $newUserId;
            }
            return $users;
        }, []);

        if (self::$seedTemplatesFor !== null) {
            Presentation::seedDefaultTemplates(self::$seedTemplatesFor);
            self::$seedTemplatesFor = null;
        }

        if ($result[0] && $inviteToken !== '' && $newUserId !== null) {
            InviteToken::consume($inviteToken, $newUserId);
        }

        if ($result[0] && $newUserId !== null) {
            $user = self::findById($newUserId);
            if ($user && !EmailVerification::isVerified($user)) {
                $mail = EmailVerification::sendMail($user);
                if (!$mail['ok']) {
                    return [true, 'registration_pending_no_mail'];
                }
                return [true, 'registration_pending'];
            }
        }

        return $result;
    }

    public static function login(string $username, string $password): array
    {
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if (strcasecmp($u['username'], $username) === 0 || strcasecmp($u['email'], $username) === 0) {
                if (password_verify($password, $u['password_hash'])) {
                    if (!EmailVerification::isVerified($u)) {
                        return [false, 'email_not_verified'];
                    }
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['username'] = $u['username'];
                    return [true, ''];
                }
                return [false, 'Falsches Passwort.'];
            }
        }
        return [false, 'Kein Benutzer mit diesem Namen gefunden.'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect('login.php');
        }
        // Session zeigt auf einen User, der nicht (mehr) existiert
        // (z.B. weil data/users.json zurückgesetzt wurde) -> sauber ausloggen.
        if (self::currentUser() === null) {
            self::logout();
            redirect('login.php?expired=1');
        }
    }

    /** Gibt den aktuell eingeloggten User als Array zurück, oder null. */
    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        $user = self::findById($_SESSION['user_id']);
        // Session-ID veraltet (z.B. nach Restore/Migration von users.json): per Benutzername neu verknüpfen.
        if ($user === null && !empty($_SESSION['username'])) {
            $user = self::findByUsername((string)$_SESSION['username']);
            if ($user !== null) {
                $_SESSION['user_id'] = $user['id'];
            }
        }
        return $user;
    }

    public static function findById(string $id): ?array
    {
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if ($u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }

    public static function findByUsername(string $username): ?array
    {
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                return $u;
            }
        }
        return null;
    }

    public static function isAdmin(): bool
    {
        $u = self::currentUser();
        return $u !== null && ($u['role'] ?? 'editor') === 'admin';
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Nur für Administratoren zugänglich.');
        }
    }

    /** Eigene Profildaten ändern (Benutzername/E-Mail). Gibt [ok, message] zurück. */
    public static function updateProfile(string $userId, string $username, string $email): array
    {
        $username = trim($username);
        $email = trim($email);
        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            return [false, 'Ungültiger Benutzername.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Ungültige E-Mail-Adresse.'];
        }
        $result = [true, 'Profil gespeichert.'];
        Storage::update(USERS_FILE, function ($users) use ($userId, $username, $email, &$result) {
            foreach ($users as $u) {
                if ($u['id'] !== $userId && strcasecmp($u['username'], $username) === 0) {
                    $result = [false, 'Dieser Benutzername ist bereits vergeben.'];
                    return $users;
                }
                if ($u['id'] !== $userId && strcasecmp($u['email'], $email) === 0) {
                    $result = [false, 'Diese E-Mail-Adresse wird bereits verwendet.'];
                    return $users;
                }
            }
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['username'] = $username;
                    $u['email'] = $email;
                }
            }
            return $users;
        }, []);
        if ($result[0]) {
            $_SESSION['username'] = $username;
        }
        return $result;
    }

    public static function changePassword(string $userId, string $currentPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return [false, 'Neues Passwort muss mindestens 6 Zeichen lang sein.'];
        }
        $user = self::findById($userId);
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return [false, 'Aktuelles Passwort ist falsch.'];
        }
        Storage::update(USERS_FILE, function ($users) use ($userId, $newPassword) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
            return $users;
        }, []);
        return [true, 'Passwort geändert.'];
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

    public static function setAvatar(string $userId, ?string $filename): void
    {
        Storage::update(USERS_FILE, function ($users) use ($userId, $filename) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['avatar'] = $filename;
                }
            }
            return $users;
        }, []);
    }

    public static function setTheme(string $userId, string $theme): void
    {
        $theme = $theme === 'light' ? 'light' : 'dark';
        Storage::update(USERS_FILE, function ($users) use ($userId, $theme) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['theme'] = $theme;
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
        ];
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
        $timebarPx = max(72, min(220, $timebarPx));
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

    public static function setRole(string $userId, string $role): void
    {
        $role = $role === 'admin' ? 'admin' : 'editor';
        Storage::update(USERS_FILE, function ($users) use ($userId, $role) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['role'] = $role;
                }
            }
            return $users;
        }, []);
    }

    public static function countAdmins(): int
    {
        $count = 0;
        foreach (Storage::read(USERS_FILE, []) as $u) {
            if (($u['role'] ?? 'editor') === 'admin') {
                $count++;
            }
        }
        return $count;
    }

    /** Löscht einen User (Admin-Funktion). $alsoDeletePresentations löscht zusätzlich dessen eigene Präsentationen. */
    public static function deleteUser(string $userId, bool $alsoDeletePresentations = false): void
    {
        if ($alsoDeletePresentations && is_dir(PRESENTATIONS_PATH)) {
            foreach (scandir(PRESENTATIONS_PATH) as $pid) {
                if ($pid === '.' || $pid === '..') continue;
                $meta = Presentation::getMeta($pid);
                if ($meta && $meta['owner_id'] === $userId) {
                    Presentation::delete($pid);
                }
            }
        }
        Storage::update(USERS_FILE, function ($users) use ($userId) {
            return array_values(array_filter($users, fn($u) => $u['id'] !== $userId));
        }, []);
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

    /** @return list<array{id:string,label:string,url:string,username:string,root_path:string}> */
    public static function listWebdavDrivesPublic(array $user): array
    {
        $out = [];
        foreach ($user['webdav_drives'] ?? [] as $drive) {
            if (empty($drive['id']) || empty($drive['label']) || empty($drive['url'])) {
                continue;
            }
            $out[] = [
                'id' => (string)$drive['id'],
                'label' => (string)$drive['label'],
                'url' => (string)$drive['url'],
                'username' => (string)($drive['username'] ?? ''),
                'root_path' => (string)($drive['root_path'] ?? ''),
            ];
        }
        return $out;
    }

    /** @return array{url:string,username:string,password:string,root_path:string}|null */
    public static function getWebdavDriveCredentials(string $userId, string $driveId): ?array
    {
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if ($u['id'] !== $userId) {
                continue;
            }
            foreach ($u['webdav_drives'] ?? [] as $drive) {
                if (($drive['id'] ?? '') !== $driveId) {
                    continue;
                }
                $password = CryptoHelper::decrypt((string)($drive['password_enc'] ?? ''));
                if ($password === null) {
                    return null;
                }
                return [
                    'url' => trim((string)($drive['url'] ?? '')),
                    'username' => trim((string)($drive['username'] ?? '')),
                    'password' => $password,
                    'root_path' => WebdavClient::normalizeBrowsePath((string)($drive['root_path'] ?? '')),
                ];
            }
        }
        return null;
    }

    /** @return array{ok:bool,error?:string,drive?:array} */
    public static function saveWebdavDrive(string $userId, array $input): array
    {
        $label = trim((string)($input['label'] ?? ''));
        $url = trim((string)($input['url'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $rootPath = WebdavClient::normalizeBrowsePath((string)($input['root_path'] ?? ''));
        $driveId = trim((string)($input['id'] ?? ''));

        if ($label === '' || mb_strlen($label) > 80) {
            return ['ok' => false, 'error' => 'invalid_label'];
        }
        if (!self::isValidWebdavBaseUrl($url)) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return ['ok' => false, 'error' => 'https_required'];
        }
        if ($username === '' || mb_strlen($username) > 200) {
            return ['ok' => false, 'error' => 'invalid_username'];
        }

        $saved = null;
        Storage::update(USERS_FILE, function ($users) use ($userId, $driveId, $label, $url, $username, $password, $rootPath, &$saved) {
            foreach ($users as &$u) {
                if ($u['id'] !== $userId) {
                    continue;
                }
                if (!isset($u['webdav_drives']) || !is_array($u['webdav_drives'])) {
                    $u['webdav_drives'] = [];
                }
                $drives = &$u['webdav_drives'];
                $existingIdx = null;
                foreach ($drives as $idx => $drive) {
                    if (($drive['id'] ?? '') === $driveId && $driveId !== '') {
                        $existingIdx = $idx;
                        break;
                    }
                }
                if ($existingIdx === null && count($drives) >= 10) {
                    return $users;
                }

                if ($existingIdx !== null) {
                    $entry = $drives[$existingIdx];
                    $entry['label'] = $label;
                    $entry['url'] = $url;
                    $entry['username'] = $username;
                    $entry['root_path'] = $rootPath;
                    if ($password !== '') {
                        $entry['password_enc'] = CryptoHelper::encrypt($password);
                    }
                    $entry['updated_at'] = date('c');
                    $drives[$existingIdx] = $entry;
                    $saved = self::publicWebdavDrive($entry);
                } else {
                    if ($password === '') {
                        return $users;
                    }
                    $entry = [
                        'id' => 'wd_' . Storage::generateId(10),
                        'label' => $label,
                        'url' => $url,
                        'username' => $username,
                        'password_enc' => CryptoHelper::encrypt($password),
                        'root_path' => $rootPath,
                        'created_at' => date('c'),
                    ];
                    $drives[] = $entry;
                    $saved = self::publicWebdavDrive($entry);
                }
            }
            return $users;
        }, []);

        if ($saved === null) {
            if ($driveId === '' && $password === '') {
                return ['ok' => false, 'error' => 'password_required'];
            }
            return ['ok' => false, 'error' => 'save_failed'];
        }
        return ['ok' => true, 'drive' => $saved];
    }

    /** @return array{ok:bool,error?:string} */
    public static function deleteWebdavDrive(string $userId, string $driveId): array
    {
        if ($driveId === '') {
            return ['ok' => false, 'error' => 'invalid_drive'];
        }
        $deleted = false;
        Storage::update(USERS_FILE, function ($users) use ($userId, $driveId, &$deleted) {
            foreach ($users as &$u) {
                if ($u['id'] !== $userId) {
                    continue;
                }
                $before = count($u['webdav_drives'] ?? []);
                $u['webdav_drives'] = array_values(array_filter(
                    $u['webdav_drives'] ?? [],
                    static fn($d) => ($d['id'] ?? '') !== $driveId
                ));
                $deleted = count($u['webdav_drives']) < $before;
            }
            return $users;
        }, []);
        return $deleted ? ['ok' => true] : ['ok' => false, 'error' => 'not_found'];
    }

    /** @param array<string,mixed> $drive */
    private static function publicWebdavDrive(array $drive): array
    {
        return [
            'id' => (string)($drive['id'] ?? ''),
            'label' => (string)($drive['label'] ?? ''),
            'url' => (string)($drive['url'] ?? ''),
            'username' => (string)($drive['username'] ?? ''),
            'root_path' => (string)($drive['root_path'] ?? ''),
        ];
    }

    public static function isValidWebdavBaseUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (strtolower((string)$parts['scheme']) !== 'https') {
            return false;
        }
        $host = (string)$parts['host'];
        return $host !== '' && !str_contains($host, ' ');
    }
}
