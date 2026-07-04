<?php
/**
 * Pixabay API: Suche und Import von Bildern/Videos in Präsentations-Assets.
 * @see https://pixabay.com/api/docs/
 */
class PixabayClient
{
    private const IMAGE_API = 'https://pixabay.com/api/';
    private const VIDEO_API = 'https://pixabay.com/api/videos/';
    private const CACHE_TTL = 86400; // 24 h laut Pixabay-Richtlinie
    private const ALLOWED_HOSTS = ['pixabay.com', 'cdn.pixabay.com', 'www.pixabay.com'];

    public static function config(): array
    {
        $defaults = [
            'enabled' => true,
            'api_key' => '',
        ];
        return array_merge($defaults, Config::get('pixabay', []));
    }

    public static function enabled(): bool
    {
        $cfg = self::config();
        return !empty($cfg['enabled']) && trim((string)($cfg['api_key'] ?? '')) !== '';
    }

    public static function search(string $media, array $params): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $media = $media === 'video' ? 'video' : 'image';
        $cacheKey = self::cacheKey($media, $params);
        $cached = self::readCache($cacheKey);
        if ($cached !== null) {
            return ['ok' => true, 'hits' => $cached, 'cached' => true];
        }

        $query = self::buildQuery($media, $params);
        $url = ($media === 'video' ? self::VIDEO_API : self::IMAGE_API) . '?' . $query;
        $json = self::httpGetJson($url);
        if ($json === null) {
            return ['ok' => false, 'error' => 'request_failed'];
        }

        $hits = [];
        foreach ($json['hits'] ?? [] as $hit) {
            $normalized = $media === 'video'
                ? self::normalizeVideoHit($hit)
                : self::normalizeImageHit($hit);
            if ($normalized) {
                $hits[] = $normalized;
            }
        }

        self::writeCache($cacheKey, $hits);

        return [
            'ok' => true,
            'hits' => $hits,
            'total' => (int)($json['totalHits'] ?? count($hits)),
            'cached' => false,
        ];
    }

    public static function importToPresentation(string $presentationId, string $media, string $downloadUrl, int $pixabayId = 0): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $media = $media === 'video' ? 'video' : 'image';
        $downloadUrl = trim($downloadUrl);
        if ($downloadUrl === '' || !self::isAllowedUrl($downloadUrl)) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sf_px_');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'temp_failed'];
        }

        $ok = self::httpDownload($downloadUrl, $tmp);
        if (!$ok) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'download_failed'];
        }

        $size = filesize($tmp) ?: 0;
        $maxSize = $media === 'video' ? MAX_VIDEO_SIZE : MAX_IMAGE_SIZE;
        if ($size <= 0 || $size > $maxSize) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'file_too_large'];
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $tmp) : false;

        $allowedImage = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $allowedVideo = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        $allowed = $media === 'video' ? $allowedVideo : $allowedImage;

        if ($mime === false || !isset($allowed[$mime])) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        $ext = $allowed[$mime];
        $prefix = $pixabayId > 0 ? 'px' . $pixabayId : 'px';
        $filename = $prefix . '_' . Storage::generateId(6) . '.' . $ext;
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
            'kind' => $media,
        ];
    }

    private static function normalizeImageHit(array $hit): ?array
    {
        $id = (int)($hit['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $download = (string)($hit['largeImageURL'] ?? $hit['webformatURL'] ?? '');
        if ($download === '') {
            return null;
        }
        return [
            'id' => $id,
            'type' => 'image',
            'previewURL' => (string)($hit['previewURL'] ?? ''),
            'thumbnailURL' => (string)($hit['webformatURL'] ?? $hit['previewURL'] ?? ''),
            'downloadURL' => $download,
            'width' => (int)($hit['imageWidth'] ?? 0),
            'height' => (int)($hit['imageHeight'] ?? 0),
            'tags' => (string)($hit['tags'] ?? ''),
            'user' => (string)($hit['user'] ?? ''),
            'pageURL' => (string)($hit['pageURL'] ?? ''),
        ];
    }

    private static function normalizeVideoHit(array $hit): ?array
    {
        $id = (int)($hit['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $videos = $hit['videos'] ?? [];
        $pick = self::pickVideoVariant($videos);
        if (!$pick) {
            return null;
        }
        return [
            'id' => $id,
            'type' => 'video',
            'previewURL' => (string)($pick['thumbnail'] ?? ''),
            'thumbnailURL' => (string)($pick['thumbnail'] ?? ''),
            'downloadURL' => (string)($pick['url'] ?? ''),
            'width' => (int)($pick['width'] ?? 0),
            'height' => (int)($pick['height'] ?? 0),
            'duration' => (int)($hit['duration'] ?? 0),
            'tags' => (string)($hit['tags'] ?? ''),
            'user' => (string)($hit['user'] ?? ''),
            'pageURL' => (string)($hit['pageURL'] ?? ''),
        ];
    }

    /** @param array<string, array> $videos */
    private static function pickVideoVariant(array $videos): ?array
    {
        foreach (['medium', 'small', 'large', 'tiny'] as $key) {
            if (empty($videos[$key]['url'])) {
                continue;
            }
            $size = (int)($videos[$key]['size'] ?? 0);
            if ($size > 0 && $size <= MAX_VIDEO_SIZE) {
                return $videos[$key];
            }
        }
        foreach (['small', 'tiny', 'medium'] as $key) {
            if (!empty($videos[$key]['url'])) {
                return $videos[$key];
            }
        }
        return null;
    }

    private static function buildQuery(string $media, array $params): string
    {
        $cfg = self::config();
        $q = [
            'key' => trim((string)$cfg['api_key']),
            'safesearch' => 'true',
            'per_page' => max(3, min(40, (int)($params['per_page'] ?? 20))),
            'page' => max(1, (int)($params['page'] ?? 1)),
        ];

        $term = trim((string)($params['q'] ?? ''));
        if ($term !== '') {
            $q['q'] = mb_substr($term, 0, 100);
        }

        $lang = trim((string)($params['lang'] ?? 'de'));
        if (in_array($lang, ['cs', 'da', 'de', 'en', 'es', 'fr', 'id', 'it', 'hu', 'nl', 'no', 'pl', 'pt', 'ro', 'sk', 'fi', 'sv', 'tr', 'vi', 'th', 'bg', 'ru', 'el', 'ja', 'ko', 'zh'], true)) {
            $q['lang'] = $lang;
        }

        if ($media === 'image') {
            $imageType = (string)($params['image_type'] ?? 'all');
            if (in_array($imageType, ['all', 'photo', 'illustration', 'vector'], true)) {
                $q['image_type'] = $imageType;
            }
            $orientation = (string)($params['orientation'] ?? 'all');
            if (in_array($orientation, ['all', 'horizontal', 'vertical'], true) && $orientation !== 'all') {
                $q['orientation'] = $orientation;
            }
        } else {
            $videoType = (string)($params['video_type'] ?? 'all');
            if (in_array($videoType, ['all', 'film', 'animation'], true) && $videoType !== 'all') {
                $q['video_type'] = $videoType;
            }
        }

        return http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    private static function cacheKey(string $media, array $params): string
    {
        ksort($params);
        return hash('sha256', $media . '|' . json_encode($params));
    }

    private static function cachePath(string $key): string
    {
        $dir = EXPORT_CACHE_PATH . '/pixabay';
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

    private static function writeCache(string $key, array $hits): void
    {
        @file_put_contents(self::cachePath($key), json_encode($hits, JSON_UNESCAPED_UNICODE));
    }

    private static function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        return in_array($host, self::ALLOWED_HOSTS, true);
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
                CURLOPT_TIMEOUT => 120,
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
                'timeout' => 120,
                'header' => "User-Agent: SlideForge/" . ASSET_VERSION . "\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false ? $body : null;
    }
}
