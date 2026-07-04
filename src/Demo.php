<?php
/**
 * Demo-Instanz: feste Testkonten, 12h-Reset, SMTP gesperrt.
 */
class Demo
{
    public const RESET_INTERVAL_SECONDS = 12 * 3600;

    /** Fester Token für die öffentliche Feature-Tour (README / Marketing). */
    public const FEATURE_TOUR_TOKEN = 'slideforge-tour';

    /** @var list<array{username:string,email:string,password:string,role:string}> */
    public const ACCOUNTS = [
        ['username' => 'admin', 'email' => 'admin@service7.ch', 'password' => 'admin', 'role' => 'admin'],
        ['username' => 'editor', 'email' => 'edit@service7.ch', 'password' => 'editor', 'role' => 'editor'],
    ];

    private static function stateFile(): string
    {
        return DATA_PATH . '/demo_state.json';
    }

    public static function isActive(): bool
    {
        return defined('DEMO_MODE') && DEMO_MODE;
    }

    public static function smtpLocked(): bool
    {
        return self::isActive();
    }

    /** @return array{next_reset_at:int} */
    private static function readState(): array
    {
        return Storage::read(self::stateFile(), ['next_reset_at' => 0]);
    }

    private static function writeState(array $state): void
    {
        Storage::write(self::stateFile(), $state);
    }

    public static function nextResetAt(): int
    {
        return (int)(self::readState()['next_reset_at'] ?? 0);
    }

    /** Prüft Intervall und setzt Demo-Daten bei Bedarf zurück. */
    public static function maybeReset(): void
    {
        if (!self::isActive()) {
            return;
        }
        $next = self::nextResetAt();
        $users = Storage::read(USERS_FILE, []);
        if ($next === 0 || time() >= $next || !self::hasSeedUsers($users)) {
            self::resetAll();
        }
    }

    /** @param array<int, array<string, mixed>> $users */
    private static function hasSeedUsers(array $users): bool
    {
        $need = array_column(self::ACCOUNTS, 'username');
        $found = [];
        foreach ($users as $u) {
            foreach ($need as $i => $name) {
                if (strcasecmp($u['username'] ?? '', $name) === 0) {
                    $found[$name] = true;
                }
            }
        }
        return count($found) === count($need);
    }

    public static function resetAll(): void
    {
        if (!self::isActive()) {
            return;
        }

        Storage::write(USERS_FILE, []);
        Storage::write(INVITES_FILE, []);

        if (is_dir(EXPORT_CACHE_PATH)) {
            foreach (glob(EXPORT_CACHE_PATH . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
        }

        if (is_dir(PRESENTATIONS_PATH)) {
            foreach (scandir(PRESENTATIONS_PATH) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                self::rmTree(PRESENTATIONS_PATH . '/' . $entry);
            }
        }

        self::clearUploads(PUBLIC_UPLOADS_PATH);
        self::seedDefaultTextTemplates();

        $adminId = self::seedUsers();
        if ($adminId !== null) {
            self::seedDefaultSlideTemplates($adminId);
            self::seedShowcasePresentation($adminId);
            self::seedFeatureTour($adminId);
        }

        self::applyDemoSiteConfig();

        self::writeState(['next_reset_at' => time() + self::RESET_INTERVAL_SECONDS]);
    }

    /** Textvorlagen wie bei einer frischen Installation (config.php-Defaults). */
    private static function seedDefaultTextTemplates(): void
    {
        Storage::write(TEXT_TEMPLATES_FILE, [
            ['id' => 'title', 'name' => 'Titel', 'fontFamily' => 'Open Sans', 'fontSize' => 120, 'fontWeight' => 'bold', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false, 'color' => '#ffffff', 'align' => 'center', 'w' => 1270, 'h' => 90],
            ['id' => 'subtitle', 'name' => 'Untertitel', 'fontFamily' => 'PT Sans', 'fontSize' => 68, 'fontWeight' => 'normal', 'italic' => true, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false, 'color' => '#94c2dc', 'align' => 'center', 'w' => 1270, 'h' => 80],
            ['id' => '0b3aec509d2e', 'name' => 'Überschrift 1', 'fontFamily' => 'Open Sans', 'fontSize' => 86, 'fontWeight' => 'bold', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => true, 'color' => '#ffffff', 'align' => 'left', 'w' => 1720, 'h' => 100],
            ['id' => '6ccca4e48029', 'name' => 'Überschrift 2', 'fontFamily' => 'Open Sans', 'fontSize' => 74, 'fontWeight' => 'normal', 'italic' => true, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => true, 'color' => '#ffffff', 'align' => 'left', 'w' => 1720, 'h' => 60],
            ['id' => '982874cf3eb0', 'name' => 'Überschrift 3', 'fontFamily' => 'Open Sans', 'fontSize' => 68, 'fontWeight' => 'normal', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => true, 'color' => '#ffffff', 'align' => 'left', 'w' => 1720, 'h' => 60],
            ['id' => 'standard', 'name' => 'Text', 'fontFamily' => 'Open Sans', 'fontSize' => 65, 'fontWeight' => 'normal', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false, 'color' => '#ffffff', 'align' => 'left', 'w' => 599, 'h' => 70],
        ]);
    }

    private static function seedDefaultSlideTemplates(string $adminId): void
    {
        $seedDir = BASE_PATH . '/seed/templates';
        if (!is_dir($seedDir)) {
            return;
        }
        Presentation::seedDefaultTemplates($adminId);
    }

    private static function clearUploads(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::clearUploads($path);
                if ($entry !== '.gitkeep') {
                    @rmdir($path);
                }
            } elseif ($entry !== '.gitkeep') {
                @unlink($path);
            }
        }
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        if (!is_readable($dir)) {
            @rmdir($dir);
            return;
        }
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::rmTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /** @return string|null Admin-User-ID nach dem Anlegen der Demo-Konten */
    private static function seedUsers(): ?string
    {
        $adminId = null;
        $now = date('c');

        Storage::update(USERS_FILE, function ($users) use (&$adminId, $now) {
            foreach (self::ACCOUNTS as $acc) {
                $users[] = [
                    'id' => Storage::generateId(6),
                    'username' => $acc['username'],
                    'email' => $acc['email'],
                    'password_hash' => password_hash($acc['password'], PASSWORD_DEFAULT),
                    'role' => $acc['role'],
                    'theme' => 'dark',
                    'email_verified' => true,
                    'created_at' => $now,
                ];
            }
            foreach ($users as $u) {
                if (($u['role'] ?? '') === 'admin') {
                    $adminId = $u['id'];
                    break;
                }
            }
            return $users;
        }, []);

        return $adminId;
    }

    private static function seedShowcasePresentation(string $adminId): void
    {
        $seedDir = BASE_PATH . '/seed/demo-showcase';
        if (!is_dir($seedDir)) {
            return;
        }
        $presId = Presentation::importSeedPresentation($adminId, $seedDir);
        if ($presId === null) {
            return;
        }
        $users = Storage::read(USERS_FILE, []);
        foreach ($users as $u) {
            if (strcasecmp($u['username'] ?? '', 'editor') === 0) {
                Presentation::addShare($presId, $u['id'], $u['username'], 'edit');
                break;
            }
        }
    }

    private static function seedFeatureTour(string $adminId): void
    {
        $base = BASE_PATH . '/seed/feature-tour';
        if (!is_dir($base)) {
            return;
        }
        foreach (['de', 'en', 'fr', 'it', 'rm'] as $lang) {
            $seedDir = $base . '/' . $lang;
            if (is_file($seedDir . '/meta.json') && is_file($seedDir . '/slides.json')) {
                Presentation::importSeedPresentation($adminId, $seedDir);
            }
        }
    }

    private static function applyDemoSiteConfig(): void
    {
        Storage::update(SITE_CONFIG_FILE, function ($cfg) {
            $cfg['registration_enabled'] = false;
            $cfg['smtp'] = [
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'from_email' => '',
                'from_name' => APP_NAME,
            ];
            return $cfg;
        }, []);
        Config::clearCache();
    }

    /** @return list<array{username:string,email:string,password:string,role:string,role_label:string}> */
    public static function accountRows(): array
    {
        $rows = [];
        foreach (self::ACCOUNTS as $acc) {
            $rows[] = $acc + [
                'role_label' => $acc['role'] === 'admin'
                    ? t('admin.role_admin')
                    : t('admin.role_editor'),
            ];
        }
        return $rows;
    }
}
