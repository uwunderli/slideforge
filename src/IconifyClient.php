<?php
/**
 * Iconify API: Suche und Import von SVG-Icons in Präsentations-Assets.
 * @see https://iconify.design/docs/api/
 */
class IconifyClient
{
    private const SEARCH_API = 'https://api.iconify.design/search';
    private const SVG_API = 'https://api.iconify.design/';
    private const CACHE_TTL = 86400;
    private const MAX_SVG_SIZE = 512 * 1024;
    private const ALLOWED_HOSTS = ['api.iconify.design'];

    /** @var list<string> */
    private const ALLOWED_PREFIXES = [
        'mdi', 'fa6-solid', 'fa6-regular', 'lucide', 'tabler',
        'material-symbols', 'bi', 'ph', 'carbon', 'ri', 'simple-icons',
    ];

    public static function config(): array
    {
        $defaults = ['enabled' => true];
        return array_merge($defaults, Config::get('iconify', []));
    }

    public static function enabled(): bool
    {
        return !empty(self::config()['enabled']);
    }

    /** @return list<array{value: string, label: string}> */
    public static function collectionOptions(): array
    {
        return [
            ['value' => '', 'label' => 'all'],
            ['value' => 'mdi', 'label' => 'Material Design Icons'],
            ['value' => 'fa6-solid', 'label' => 'Font Awesome 6 Solid'],
            ['value' => 'fa6-regular', 'label' => 'Font Awesome 6 Regular'],
            ['value' => 'lucide', 'label' => 'Lucide'],
            ['value' => 'tabler', 'label' => 'Tabler Icons'],
            ['value' => 'material-symbols', 'label' => 'Material Symbols'],
            ['value' => 'bi', 'label' => 'Bootstrap Icons'],
            ['value' => 'ph', 'label' => 'Phosphor'],
            ['value' => 'carbon', 'label' => 'Carbon'],
            ['value' => 'ri', 'label' => 'Remix Icon'],
            ['value' => 'simple-icons', 'label' => 'Simple Icons'],
        ];
    }

    public static function search(array $params): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $query = trim((string)($params['q'] ?? ''));
        if ($query === '') {
            return ['ok' => false, 'error' => 'empty_query'];
        }

        $cacheKey = self::cacheKey($params);
        $cached = self::readCache($cacheKey);
        if ($cached !== null) {
            return ['ok' => true, 'hits' => $cached['hits'], 'total' => $cached['total'], 'cached' => true];
        }

        $limit = max(3, min(32, (int)($params['per_page'] ?? 24)));
        $start = max(0, ((int)($params['page'] ?? 1) - 1) * $limit);
        $prefix = trim((string)($params['prefix'] ?? ''));

        $q = [
            'query' => mb_substr($query, 0, 100),
            'limit' => $limit,
            'start' => $start,
        ];
        if ($prefix !== '' && self::isAllowedPrefix($prefix)) {
            $q['prefix'] = $prefix;
        }

        $url = self::SEARCH_API . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
        $json = self::httpGetJson($url);
        if ($json === null) {
            return ['ok' => false, 'error' => 'request_failed'];
        }

        $hits = [];
        foreach ($json['icons'] ?? [] as $iconId) {
            $normalized = self::normalizeIconId((string)$iconId);
            if ($normalized) {
                $hits[] = $normalized;
            }
        }

        $total = (int)($json['total'] ?? count($hits));
        self::writeCache($cacheKey, ['hits' => $hits, 'total' => $total]);

        return [
            'ok' => true,
            'hits' => $hits,
            'total' => $total,
            'cached' => false,
        ];
    }

    public static function importToPresentation(string $presentationId, string $iconId): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $hit = self::normalizeIconId($iconId);
        if (!$hit) {
            return ['ok' => false, 'error' => 'invalid_icon'];
        }

        $downloadUrl = $hit['svgURL'];
        if (!self::isAllowedUrl($downloadUrl)) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sf_ic_');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'temp_failed'];
        }

        $ok = self::httpDownload($downloadUrl, $tmp);
        if (!$ok) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'download_failed'];
        }

        $size = filesize($tmp) ?: 0;
        if ($size <= 0 || $size > self::MAX_SVG_SIZE) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'file_too_large'];
        }

        $head = (string)file_get_contents($tmp, false, null, 0, 256);
        if (stripos($head, '<svg') === false) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        $safeName = preg_replace('/[^a-z0-9_-]+/i', '-', $hit['name']) ?: 'icon';
        $filename = 'ic_' . preg_replace('/[^a-z0-9_-]+/i', '-', $hit['prefix']) . '_' . $safeName . '_' . Storage::generateId(6) . '.svg';
        $assetsDir = Presentation::dir($presentationId) . '/assets';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0770, true);
        }
        $destination = $assetsDir . '/' . $filename;

        if (!@rename($tmp, $destination)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'save_failed'];
        }

        return [
            'ok' => true,
            'filename' => $filename,
            'url' => 'asset.php?id=' . urlencode($presentationId) . '&file=' . urlencode($filename),
            'kind' => 'image',
            'iconId' => $hit['id'],
        ];
    }

    private static function normalizeIconId(string $iconId): ?array
    {
        $iconId = trim($iconId);
        if (!preg_match('/^([a-z0-9][a-z0-9-]*):([a-z0-9][a-z0-9._-]*)$/i', $iconId, $m)) {
            return null;
        }
        $prefix = strtolower($m[1]);
        $name = $m[2];
        if (!self::isAllowedPrefix($prefix)) {
            return null;
        }
        $path = rawurlencode($prefix) . '/' . rawurlencode($name) . '.svg';
        return [
            'id' => $prefix . ':' . $name,
            'prefix' => $prefix,
            'name' => $name,
            'previewURL' => self::SVG_API . $path . '?height=64',
            'svgURL' => self::SVG_API . $path,
        ];
    }

    private static function isAllowedPrefix(string $prefix): bool
    {
        return in_array(strtolower($prefix), self::ALLOWED_PREFIXES, true);
    }

    private static function cacheKey(array $params): string
    {
        ksort($params);
        return hash('sha256', 'iconify|' . json_encode($params));
    }

    private static function cachePath(string $key): string
    {
        $dir = EXPORT_CACHE_PATH . '/iconify';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return $dir . '/' . $key . '.json';
    }

    private static function readCache(string $key): ?array
    {
        $path = self::cachePath($key);
        if (!is_file($path)) {
            return null;
        }
        if (filemtime($path) < time() - self::CACHE_TTL) {
            @unlink($path);
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private static function writeCache(string $key, array $payload): void
    {
        @file_put_contents(self::cachePath($key), json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private static function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($host, self::ALLOWED_HOSTS, true)) {
            return false;
        }
        $path = (string)($parts['path'] ?? '');
        return str_ends_with(strtolower($path), '.svg');
    }

    private static function httpGetJson(string $url): ?array
    {
        $body = self::httpRequest($url);
        if ($body === null) {
            return null;
        }
        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }

    private static function httpDownload(string $url, string $destination): bool
    {
        $body = self::httpRequest($url);
        if ($body === null) {
            return false;
        }
        return file_put_contents($destination, $body) !== false;
    }

    private static function httpRequest(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'SlideForge/' . ASSET_VERSION,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }
            return $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 60,
                'header' => "User-Agent: SlideForge/" . ASSET_VERSION . "\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false ? $body : null;
    }
}
