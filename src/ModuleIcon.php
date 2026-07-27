<?php

/**
 * Favicons / App-Icons für externe Module.
 * Cache: public/assets/module-icons/{id}.{ext}
 */
class ModuleIcon
{
    private const TTL_SECONDS = 604800; // 7 Tage
    private const NEGATIVE_TTL = 86400;

    /** @param array<string,mixed> $module */
    public static function urlFor(array $module): string
    {
        return self::resolveUrl($module);
    }

    /**
     * Icon-URL: Favicon (extern) → Builtin-SVG → FolienSchmiede-Default.
     *
     * @param array<string,mixed> $module
     */
    public static function resolveUrl(array $module): string
    {
        $explicit = trim((string)($module['iconUrl'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        if (!empty($module['external'])) {
            $favicon = self::externalFaviconUrl($module);
            if ($favicon !== '') {
                return $favicon;
            }
        }

        $builtin = self::builtinUrl($module);
        if ($builtin !== '') {
            return $builtin;
        }

        return self::defaultUrl();
    }

    /** @param array<string,mixed> $module */
    private static function externalFaviconUrl(array $module): string
    {
        $explicit = trim((string)($module['iconUrl'] ?? ''));
        if ($explicit !== '' && preg_match('#^https?://#i', $explicit)) {
            return $explicit;
        }

        $id = self::safeId((string)($module['id'] ?? ''));
        $originUrl = trim((string)($module['url'] ?? ''));
        if ($id === '' || $originUrl === '' || !preg_match('#^https?://#i', $originUrl)) {
            return '';
        }

        if (!defined('HUB_PUBLIC_PATH')) {
            return rtrim(churchforge_hub_url(), '/') . '/assets/module-icon.php?id=' . rawurlencode($id);
        }

        $path = self::ensureCached($id, $originUrl);
        if ($path === '') {
            return '';
        }

        return self::publicUrl($id);
    }

    /** @param array<string,mixed> $module */
    public static function builtinUrl(array $module): string
    {
        $candidates = [
            self::safeId((string)($module['id'] ?? '')),
            self::safeId((string)($module['icon_key'] ?? $module['icon'] ?? '')),
        ];

        foreach ($candidates as $name) {
            if ($name === '' || $name === 'calendar') {
                continue; // calendar = CT-Emoji-Altname, nicht Builtin
            }
            $rel = '/assets/icons/' . $name . '.svg';
            if (self::iconFileExists($name)) {
                return self::assetUrl($rel);
            }
        }

        return '';
    }

    public static function defaultUrl(): string
    {
        return self::assetUrl('/assets/icons/default.svg');
    }

    public static function hubUrl(): string
    {
        return self::assetUrl('/assets/icons/hub.svg');
    }

    private static function assetUrl(string $path): string
    {
        $version = defined('HUB_ASSET_VERSION') ? HUB_ASSET_VERSION : '1';
        $url = $path . '?v=' . rawurlencode((string)$version);
        if (defined('HUB_PUBLIC_PATH')) {
            return $url;
        }
        return rtrim(churchforge_hub_url(), '/') . $url;
    }

    private static function iconFileExists(string $name): bool
    {
        if (!defined('HUB_PUBLIC_PATH')) {
            // Auf anderen Apps: bekannte Dateinamen annehmen
            static $known = [
                'hub', 'slides', 'default', 'signage', 'event', 'lab',
                'slideforge', 'signforge', 'eventforge',
                'stream', 'streamforge', 'clip', 'clipforge',
                'archive', 'archiveforge', 'site', 'siteforge',
                'post', 'postforge', 'design', 'designforge',
                'media', 'mediaforge', 'backup', 'backupforge',
                'office', 'officeforge', 'sync', 'syncforge',
            ];
            return in_array($name, $known, true);
        }
        return is_readable(HUB_PUBLIC_PATH . '/assets/icons/' . $name . '.svg');
    }

    /**
     * @param array<string,mixed> $module
     */
    public static function isFavicon(array $module): bool
    {
        $explicit = trim((string)($module['iconUrl'] ?? ''));
        // Eigene, gestaltete SVG-Icons (/assets/icons/*.svg) sind vollflächige Bilder,
        // keine externen Favicons — also ohne weissen Favicon-Rahmen darstellen.
        if ($explicit !== '' && !preg_match('#^https?://#i', $explicit) && str_contains($explicit, '/assets/icons/')) {
            return false;
        }
        if (!empty($module['external'])) {
            return self::externalFaviconUrl($module) !== '' || $explicit !== '';
        }
        $url = self::resolveUrl($module);
        return str_contains($url, 'module-icon.php') || str_contains($url, '/module-icons/');
    }

    /**
     * @param array<string,mixed> $module
     */
    public static function launcherMarkup(array $module): string
    {
        $url = self::resolveUrl($module);
        $class = self::isFavicon($module) ? 'cf-icon-favicon' : 'cf-icon-image';
        return '<span class="cf-launcher-icon ' . $class . '" aria-hidden="true">'
            . '<img src="' . self::h($url) . '" alt="" loading="lazy" referrerpolicy="no-referrer" decoding="async">'
            . '</span>';
    }

    /**
     * @param array<string,mixed> $module
     */
    public static function displayUrl(array $module): string
    {
        return self::resolveUrl($module);
    }

    /**
     * @param array<string,mixed> $module
     */
    public static function tileMarkup(array $module): string
    {
        $url = self::resolveUrl($module);
        $class = self::isFavicon($module) ? 'hub-tile-icon--favicon' : 'hub-tile-icon--image';
        return '<div class="hub-tile-icon ' . $class . '" aria-hidden="true">'
            . '<img src="' . self::h($url) . '" alt="" loading="lazy" referrerpolicy="no-referrer" decoding="async">'
            . '</div>';
    }

    public static function ensureForId(string $id): string
    {
        $id = self::safeId($id);
        if ($id === '') {
            return '';
        }

        $module = self::findModule($id);
        if ($module === null) {
            return '';
        }

        $url = trim((string)($module['url'] ?? ''));
        if ($url === '') {
            return '';
        }

        return self::ensureCached($id, $url);
    }

    /** @return array<string,mixed>|null */
    private static function findModule(string $id): ?array
    {
        $file = function_exists('churchforge_modules_file')
            ? churchforge_modules_file()
            : (defined('HUB_DEPLOYMENT_PATH') ? HUB_DEPLOYMENT_PATH . '/modules.json' : '');
        if ($file === '' || !is_readable($file)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($file), true);
        $modules = $data['modules'] ?? [];
        if (!is_array($modules)) {
            return null;
        }
        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }
            if (self::safeId((string)($module['id'] ?? '')) === $id) {
                return $module;
            }
        }
        return null;
    }

    private static function ensureCached(string $id, string $originUrl): string
    {
        $dir = self::cacheDir();
        if ($dir === '') {
            return '';
        }

        $metaFile = $dir . '/' . $id . '.json';
        $meta = self::readMeta($metaFile);
        $now = time();

        if (!empty($meta['path']) && is_file((string)$meta['path'])) {
            $age = $now - (int)($meta['fetched_at'] ?? 0);
            if ($age < self::TTL_SECONDS) {
                return (string)$meta['path'];
            }
        }

        if (!empty($meta['failed_at']) && ($now - (int)$meta['failed_at']) < self::NEGATIVE_TTL) {
            return is_file((string)($meta['path'] ?? '')) ? (string)$meta['path'] : '';
        }

        $source = self::discoverIconUrl($originUrl);
        if ($source === '') {
            self::writeMeta($metaFile, [
                'failed_at' => $now,
                'origin' => $originUrl,
                'path' => $meta['path'] ?? '',
            ]);
            return is_file((string)($meta['path'] ?? '')) ? (string)$meta['path'] : '';
        }

        $binary = self::download($source);
        if ($binary === null || $binary === '') {
            self::writeMeta($metaFile, [
                'failed_at' => $now,
                'origin' => $originUrl,
                'source' => $source,
                'path' => $meta['path'] ?? '',
            ]);
            return is_file((string)($meta['path'] ?? '')) ? (string)$meta['path'] : '';
        }

        $ext = self::extensionFor($source, $binary);
        $path = $dir . '/' . $id . '.' . $ext;
        // alte Varianten löschen
        foreach (glob($dir . '/' . $id . '.*') ?: [] as $old) {
            if (str_ends_with($old, '.json')) {
                continue;
            }
            @unlink($old);
        }
        if (@file_put_contents($path, $binary) === false) {
            return '';
        }

        self::writeMeta($metaFile, [
            'fetched_at' => $now,
            'origin' => $originUrl,
            'source' => $source,
            'path' => $path,
            'ext' => $ext,
        ]);

        return $path;
    }

    private static function discoverIconUrl(string $pageUrl): string
    {
        $parts = parse_url($pageUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $html = self::download($origin . '/');
        $candidates = [];

        if (is_string($html) && $html !== '') {
            if (preg_match_all('/<link\b[^>]*>/i', $html, $linkTags)) {
                foreach ($linkTags[0] as $full) {
                    if (!preg_match('/\brel=["\']([^"\']*)["\']/i', $full, $relMatch)) {
                        continue;
                    }
                    $rel = strtolower($relMatch[1]);
                    if (
                        !str_contains($rel, 'icon')
                        && !str_contains($rel, 'apple-touch-icon')
                    ) {
                        continue;
                    }
                    if (!preg_match('/\bhref=["\']([^"\']+)["\']/i', $full, $hrefMatch)) {
                        continue;
                    }
                    $href = html_entity_decode($hrefMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $size = 0;
                    if (preg_match('/\bsizes=["\'](\d+)/i', $full, $sizeMatch)) {
                        $size = (int)$sizeMatch[1];
                    } elseif (str_contains($rel, 'apple-touch-icon')) {
                        $size = 120;
                    } else {
                        $size = 32;
                    }
                    $candidates[] = [
                        'url' => self::absolutize($origin, $href),
                        'size' => $size,
                        'apple' => str_contains($rel, 'apple-touch-icon'),
                    ];
                }
            }
        }

        usort($candidates, static function ($a, $b) {
            if ($a['apple'] !== $b['apple']) {
                return $a['apple'] ? -1 : 1;
            }
            return $b['size'] <=> $a['size'];
        });

        foreach ($candidates as $candidate) {
            if ($candidate['url'] !== '') {
                return $candidate['url'];
            }
        }

        return $origin . '/favicon.ico';
    }

    private static function absolutize(string $origin, string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        return $origin . '/' . $href;
    }

    private static function download(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'header' => "User-Agent: ChurchForgeHub/1.0\r\nAccept: */*\r\n",
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            return is_string($raw) ? $raw : null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'ChurchForgeHub/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,image/*,*/*;q=0.8'],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            return null;
        }
        return (string)$raw;
    }

    private static function extensionFor(string $sourceUrl, string $binary): string
    {
        $path = strtolower((string)(parse_url($sourceUrl, PHP_URL_PATH) ?? ''));
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico'] as $ext) {
            if (str_ends_with($path, '.' . $ext)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }
        if (str_starts_with($binary, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($binary, 'GIF8')) {
            return 'gif';
        }
        if (str_starts_with($binary, 'RIFF') && str_contains(substr($binary, 0, 16), 'WEBP')) {
            return 'webp';
        }
        if (str_starts_with(ltrim($binary), '<svg') || str_starts_with(ltrim($binary), '<?xml')) {
            return 'svg';
        }
        return 'ico';
    }

    private static function cacheDir(): string
    {
        $candidates = [];
        if (defined('HUB_DEPLOYMENT_PATH')) {
            $candidates[] = HUB_DEPLOYMENT_PATH . '/module-icons';
        }
        if (defined('HUB_PUBLIC_PATH')) {
            $candidates[] = HUB_PUBLIC_PATH . '/assets/module-icons';
        }

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
                @chmod($dir, 0777);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                continue;
            }
            return $dir;
        }
        return '';
    }

    private static function publicUrl(string $id, string $ext = ''): string
    {
        $version = defined('HUB_ASSET_VERSION') ? HUB_ASSET_VERSION : '1';
        // Immer über Endpoint: Cache liegt ggf. unter data/, nicht unter public/
        return '/assets/module-icon.php?id=' . rawurlencode($id) . '&v=' . rawurlencode((string)$version);
    }

    /** @return array<string,mixed> */
    private static function readMeta(string $file): array
    {
        if (!is_readable($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string,mixed> $meta */
    private static function writeMeta(string $file, array $meta): void
    {
        @file_put_contents($file, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function safeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        return $id;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
