<?php
/**
 * Demo-Instanz: feste Testkonten, 12h-Reset, SMTP gesperrt.
 */
class Demo
{
    public const RESET_INTERVAL_SECONDS = 12 * 3600;

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
        self::seedUsers();
        self::applyDemoSiteConfig();

        self::writeState(['next_reset_at' => time() + self::RESET_INTERVAL_SECONDS]);
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
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::rmTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function seedUsers(): void
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

        if ($adminId !== null) {
            Presentation::seedDefaultTemplates($adminId);
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
