<?php
/**
 * Site-weite Konfiguration (Titel, Logo, Rechtschreibung, Medienquellen), gespeichert in data/config.json. SMTP entfällt (3.8.3):
 * die FolienSchmiede verschickt keine Mails mehr.
 * Nur für Administratoren änderbar (siehe admin_settings.php).
 */
class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = Storage::read(SITE_CONFIG_FILE, [
                'site_title' => APP_NAME,
                'logo' => '',
                'languagetool' => [
                    'enabled' => true,
                    'api_url' => 'https://api.languagetool.org/v2/check',
                    'api_username' => '',
                    'api_key' => '',
                ],
                'pixabay' => [
                    'enabled' => true,
                    'api_key' => '',
                ],
            ]);
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function update(array $fields): array
    {
        $result = Storage::update(SITE_CONFIG_FILE, function ($cfg) use ($fields) {
            foreach ($fields as $k => $v) {
                $cfg[$k] = $v;
            }
            return $cfg;
        });
        self::$cache = $result;
        return $result;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function siteTitle(): string
    {
        $t = trim((string)self::get('site_title', APP_NAME));
        return $t !== '' ? $t : APP_NAME;
    }

    public static function logoUrl(): ?string
    {
        $logo = self::get('logo', '');
        if (!$logo) {
            return null;
        }
        return 'uploads/' . $logo;
    }

    public static function demoMode(): bool
    {
        return Demo::isActive();
    }

    /** Markenfarben einer frischen Installation (config.php; Übernahme erkennt daran «noch nie geändert»). @return list<array{name:string,hex:string}> */
    public static function defaultBrandColors(): array
    {
        return [
            ['name' => 'Weiss', 'hex' => '#ffffff'],
            ['name' => 'Grau', 'hex' => '#808080'],
            ['name' => 'Schwarz', 'hex' => '#000000'],
            ['name' => 'Brilliant Blau', 'hex' => '#3a6c8d'],
            ['name' => 'Blümchen Grün', 'hex' => '#87b42b'],
            ['name' => 'Gold', 'hex' => '#d1b633'],
            ['name' => 'Braun', 'hex' => '#85612d'],
            ['name' => 'Hellblau', 'hex' => '#94c2dc'],
            ['name' => 'Graublau', 'hex' => '#6a8aa1'],
            ['name' => 'Terracotta', 'hex' => '#d67d5d'],
            ['name' => 'Beere', 'hex' => '#862b6e'],
            ['name' => 'Taupe', 'hex' => '#917158'],
            ['name' => 'Himmelblau', 'hex' => '#61a8e0'],
            ['name' => 'Limette', 'hex' => '#97c764'],
            ['name' => 'Dunkelgrün', 'hex' => '#252e1b'],
            ['name' => 'Rostrot', 'hex' => '#64180b'],
            ['name' => 'Hellgrün', 'hex' => '#b7de45'],
            ['name' => 'Indigo', 'hex' => '#4449a5'],
        ];
    }

    public static function brandColors(): array
    {
        return self::get('brand_colors', []);
    }

    public static function languageTool(): array
    {
        $defaults = [
            'enabled' => true,
            'api_url' => 'https://api.languagetool.org/v2/check',
            'api_username' => '',
            'api_key' => '',
        ];
        return array_merge($defaults, self::get('languagetool', []));
    }

    public static function languageToolEnabled(): bool
    {
        return (bool)(self::languageTool()['enabled'] ?? true);
    }

    public static function pixabay(): array
    {
        $defaults = [
            'enabled' => true,
            'api_key' => '',
        ];
        return array_merge($defaults, self::get('pixabay', []));
    }

    public static function pixabayEnabled(): bool
    {
        $cfg = self::pixabay();
        return !empty($cfg['enabled']) && trim((string)($cfg['api_key'] ?? '')) !== '';
    }

    /** Optionale LAN-IP/Hostname für Remote-QR bei localhost-Zugriff (data/config.json). */
    public static function presentReachableHost(): string
    {
        return trim((string)self::get('present_reachable_host', ''));
    }

    public static function iconify(): array
    {
        $defaults = ['enabled' => true];
        return array_merge($defaults, self::get('iconify', []));
    }

    public static function iconifyEnabled(): bool
    {
        return !empty(self::iconify()['enabled']);
    }

    public static function openclipart(): array
    {
        $defaults = ['enabled' => true];
        return array_merge($defaults, self::get('openclipart', []));
    }

    public static function openclipartEnabled(): bool
    {
        return !empty(self::openclipart()['enabled']);
    }
}
