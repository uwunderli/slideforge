<?php
/**
 * Openclipart: Suche (HTML) und Import von SVG-Cliparts in Präsentations-Assets.
 * @see https://openclipart.org/
 */
class OpenclipartClient
{
    private const SITE = 'https://openclipart.org';
    private const CACHE_TTL = 86400;
    private const MAX_SVG_SIZE = 2 * 1024 * 1024;
    private const ITEMS_PER_PAGE = 25;
    /** @var list<string> */
    private const ALLOWED_HOSTS = ['openclipart.org', 'www.openclipart.org'];

    public static function config(): array
    {
        $defaults = ['enabled' => true];
        return array_merge($defaults, Config::get('openclipart', []));
    }

    public static function enabled(): bool
    {
        return !empty(self::config()['enabled']);
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
            return ['ok' => true] + $cached + ['cached' => true];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $url = self::SITE . '/search/?' . http_build_query([
            'query' => mb_substr($query, 0, 100),
            'p' => $page,
        ], '', '&', PHP_QUERY_RFC3986);

        $html = self::httpRequest($url);
        if ($html === null) {
            return ['ok' => false, 'error' => 'request_failed'];
        }

        $parsed = self::parseSearchHtml($html);
        if ($parsed === null) {
            return ['ok' => false, 'error' => 'parse_failed'];
        }

        $totalPages = $parsed['totalPages'];
        $hits = $parsed['hits'];
        $total = $totalPages > 0
            ? (($totalPages - 1) * self::ITEMS_PER_PAGE) + (count($hits) < self::ITEMS_PER_PAGE && $page === $totalPages ? count($hits) : self::ITEMS_PER_PAGE)
            : count($hits);
        if ($page < $totalPages) {
            $total = $totalPages * self::ITEMS_PER_PAGE;
        }

        $payload = [
            'hits' => $hits,
            'total' => $total,
            'totalPages' => $totalPages,
        ];
        self::writeCache($cacheKey, $payload);

        return ['ok' => true] + $payload + ['cached' => false];
    }

    public static function importToPresentation(string $presentationId, string $clipartId): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $hit = self::normalizeClipartId($clipartId);
        if (!$hit) {
            return ['ok' => false, 'error' => 'invalid_clipart'];
        }

        $downloadUrl = $hit['svgURL'];
        if (!self::isAllowedUrl($downloadUrl)) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sf_oc_');
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

        $safeSlug = preg_replace('/[^a-z0-9_-]+/i', '-', $hit['slug']) ?: 'clipart';
        $filename = 'oc_' . $hit['id'] . '_' . $safeSlug . '_' . Storage::generateId(6) . '.svg';
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
            'clipartId' => $hit['id'],
        ];
    }

    public static function previewSvg(string $clipartId, string $color = '', int $height = 256): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $hit = self::normalizeClipartId($clipartId);
        if (!$hit) {
            return null;
        }

        $body = self::httpRequest($hit['svgURL']);
        if ($body === null) {
            return null;
        }

        $hex = SvgHelper::normalizeHex($color);
        if ($hex !== null) {
            $body = SvgHelper::tintSvg($body, $hex);
        }

        return $body;
    }

    /** @return array{hits: list<array>, totalPages: int}|null */
    private static function parseSearchHtml(string $html): ?array
    {
        $totalPages = 0;
        if (preg_match('/Page\s+\d+\s+of\s+(\d+)/i', $html, $m)) {
            $totalPages = max(0, (int)$m[1]);
        }

        $hits = [];
        if (preg_match_all(
            '/<a\s+href="\/detail\/(\d+)\/([^"]+)">\s*<img\s+src="([^"]+)"\s+alt="([^"]*)"\s*\/?>\s*<\/a>/i',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $id = $match[1];
                $slug = $match[2];
                $previewPath = $match[3];
                $name = html_entity_decode(trim($match[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!ctype_digit($id)) {
                    continue;
                }
                $previewUrl = str_starts_with($previewPath, 'http')
                    ? $previewPath
                    : self::SITE . $previewPath;
                $hits[] = [
                    'id' => $id,
                    'name' => $name !== '' ? $name : $slug,
                    'slug' => $slug,
                    'previewURL' => $previewUrl,
                    'svgURL' => self::SITE . '/download/' . $id,
                ];
            }
        }

        if (!$hits && $totalPages === 0 && stripos($html, 'class="gallery"') === false) {
            return null;
        }

        return ['hits' => $hits, 'totalPages' => $totalPages];
    }

    private static function normalizeClipartId(string $clipartId): ?array
    {
        $clipartId = trim($clipartId);
        if (preg_match('/^(?:oc:)?(\d+)$/', $clipartId, $m)) {
            $id = $m[1];
            return [
                'id' => $id,
                'slug' => 'clipart',
                'name' => 'clipart',
                'previewURL' => self::SITE . '/image/800px/' . $id,
                'svgURL' => self::SITE . '/download/' . $id,
            ];
        }
        return null;
    }

    private static function cacheKey(array $params): string
    {
        ksort($params);
        return hash('sha256', 'openclipart|' . json_encode($params));
    }

    private static function cachePath(string $key): string
    {
        $dir = EXPORT_CACHE_PATH . '/openclipart';
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
        return preg_match('#^/download/\d+(?:/[^/]+\.svg)?$#i', $path) === 1;
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
                CURLOPT_MAXREDIRS => 5,
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
