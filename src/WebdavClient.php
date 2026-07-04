<?php
/**
 * WebDAV-Client (PROPFIND, GET) mit SSRF-Schutz für User-konfigurierte Laufwerke.
 */
class WebdavClient
{
    private const CONNECT_TIMEOUT = 8;
    private const REQUEST_TIMEOUT = 25;
    private const MAX_IMPORT_SIZE = MAX_IMAGE_SIZE;
    private const PREVIEW_CACHE_TTL = 604800; // 7 Tage
    private const THUMB_MAX_PX = 280;
    private const THUMB_SOURCE_MAX = 10 * 1024 * 1024;
    private const MAX_SVG_PREVIEW = 512 * 1024;
    private const STREAM_TIMEOUT = 300;

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'];

    /** @var list<string> */
    private const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /** @var list<string> */
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'm4a'];

    /** @var list<string> */
    private const IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'image/bmp', 'image/avif', 'image/x-icon',
    ];

    /** @var list<string> */
    private const VIDEO_MIMES = ['video/mp4', 'video/webm'];

    /** @var list<string> */
    private const AUDIO_MIMES = [
        'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/mp4', 'audio/x-m4a',
    ];

    /**
     * @param array{url:string,username:string,password:string,root_path?:string} $drive
     */
    public static function listDirectory(array $drive, string $relativePath = ''): array
    {
        $baseUrl = self::buildResourceUrl($drive, $relativePath, true);
        if ($baseUrl === null) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:">'
            . '<d:prop><d:displayname/><d:getcontentlength/><d:getcontenttype/><d:resourcetype/></d:prop>'
            . '</d:propfind>';

        $response = self::request($baseUrl, $drive, 'PROPFIND', $xml, [
            'Depth: 1',
            'Content-Type: application/xml; charset=utf-8',
        ]);
        if (!$response['ok']) {
            return $response;
        }

        if ($response['code'] === 404) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($response['code'] < 200 || $response['code'] >= 300) {
            return ['ok' => false, 'error' => 'request_failed', 'http' => $response['code']];
        }

        $entries = self::parsePropfind($response['body'], $baseUrl, self::normalizeBrowsePath($relativePath));
        if ($entries === null) {
            return ['ok' => false, 'error' => 'parse_failed'];
        }

        return [
            'ok' => true,
            'path' => self::normalizeBrowsePath($relativePath),
            'entries' => $entries,
        ];
    }

    /**
     * @param array{url:string,username:string,password:string,root_path?:string} $drive
     */
    public static function downloadToTemp(array $drive, string $relativePath, int $maxSize = self::MAX_IMPORT_SIZE, string $mediaKind = 'image'): array
    {
        $fileUrl = self::buildResourceUrl($drive, $relativePath);
        if ($fileUrl === null) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sf_wd_');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'temp_failed'];
        }

        $result = self::download($fileUrl, $drive, $tmp, $maxSize, $mediaKind);
        if (!$result['ok']) {
            @unlink($tmp);
            return $result;
        }

        return ['ok' => true, 'path' => $tmp, 'size' => $result['size'], 'mime' => $result['mime']];
    }

    public static function importToPresentation(string $presentationId, array $drive, string $relativePath, int $declaredSize = 0): array
    {
        $name = basename(str_replace('\\', '/', $relativePath));
        if ($name === '' || $name === '.' || $name === '..') {
            return ['ok' => false, 'error' => 'invalid_path'];
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mediaKind = self::detectMediaKind($ext, '');
        if ($mediaKind === null) {
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        if ($declaredSize > 0 && $declaredSize > self::maxImportSizeForKind($mediaKind)) {
            return ['ok' => false, 'error' => 'file_too_large'];
        }

        $maxSize = self::maxImportSizeForKind($mediaKind);
        $dl = self::downloadToTemp($drive, $relativePath, $maxSize, $mediaKind);
        if (!$dl['ok']) {
            return $dl;
        }
        $tmp = $dl['path'];

        $mime = $dl['mime'] ?? '';
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = self::mimeFromExtension($ext);
        }
        if (self::detectMediaKind($ext, $mime) === null) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        if ($mediaKind === 'image' && ($ext === 'svg' || str_contains($mime, 'svg'))) {
            $head = (string)file_get_contents($tmp, false, null, 0, 256);
            if (stripos($head, '<svg') === false) {
                @unlink($tmp);
                return ['ok' => false, 'error' => 'unsupported_type'];
            }
        }

        $safeBase = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'media';
        $filename = 'wd_' . Storage::generateId(8) . '_' . mb_substr($safeBase, 0, 40) . '.' . $ext;
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
            'kind' => $mediaKind,
            'name' => $name,
        ];
    }

    /**
     * Medien-Vorschau streamen (Video/Audio in Lightbox).
     *
     * @param array{url:string,username:string,password:string,root_path?:string} $drive
     * @return array{ok:bool,error?:string,mime?:string,size?:int}
     */
    public static function streamMedia(array $drive, string $relativePath): array
    {
        $name = basename(str_replace('\\', '/', $relativePath));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mediaKind = self::detectMediaKind($ext, '');
        if ($mediaKind === null || $mediaKind === 'image') {
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        $fileUrl = self::buildResourceUrl($drive, $relativePath);
        if ($fileUrl === null) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }

        $maxSize = self::maxImportSizeForKind($mediaKind);
        $mime = self::mimeFromExtension($ext);
        $size = 0;

        $unsafe = self::isSafeRemoteUrl($fileUrl);
        if ($unsafe !== null) {
            return ['ok' => false, 'error' => $unsafe];
        }

        $ch = curl_init($fileUrl);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init'];
        }

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::STREAM_TIMEOUT,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC | CURLAUTH_DIGEST,
            CURLOPT_UNRESTRICTED_AUTH => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['User-Agent: SlideForge-WebDAV/1.0'],
            CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$mime) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $detected = trim(explode(';', substr($header, 13))[0]);
                    if ($detected !== '') {
                        $mime = $detected;
                    }
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$size, $maxSize) {
                $len = strlen($chunk);
                $size += $len;
                if ($size > $maxSize) {
                    return 0;
                }
                echo $chunk;
                if (function_exists('flush')) {
                    flush();
                }
                return $len;
            },
        ]);
        self::applyCurlCredentials($ch, $drive);

        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!$ok || $code < 200 || $code >= 300) {
            return ['ok' => false, 'error' => $size > $maxSize ? 'file_too_large' : 'download_failed'];
        }

        return ['ok' => true, 'mime' => $mime, 'size' => $size];
    }

    /**
     * Vorschau/Thumbnail mit Server-Cache (beschleunigt wiederholtes Durchsuchen).
     *
     * @param array{url:string,username:string,password:string,root_path?:string} $drive
     * @return array{bytes:string,mime:string,cached:bool}|null
     */
    public static function getPreview(string $userId, string $driveId, array $drive, string $relativePath): ?array
    {
        $path = self::normalizeBrowsePath($relativePath);
        if ($path === '') {
            return null;
        }

        $cacheFile = self::previewCachePath($userId, $driveId, $path);
        if (is_file($cacheFile) && filemtime($cacheFile) >= time() - self::PREVIEW_CACHE_TTL) {
            $bytes = file_get_contents($cacheFile);
            if ($bytes !== false && $bytes !== '') {
                return [
                    'bytes' => $bytes,
                    'mime' => self::previewCacheMime($cacheFile),
                    'cached' => true,
                ];
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $dl = self::downloadToTemp($drive, $path, self::MAX_SVG_PREVIEW);
            if (!$dl['ok']) {
                return null;
            }
            $bytes = file_get_contents($dl['path']);
            @unlink($dl['path']);
            if ($bytes === false || stripos($bytes, '<svg') === false) {
                return null;
            }
            self::writePreviewCache($cacheFile, $bytes);
            return ['bytes' => $bytes, 'mime' => 'image/svg+xml', 'cached' => false];
        }

        $dl = self::downloadToTemp($drive, $path, self::THUMB_SOURCE_MAX);
        if (!$dl['ok']) {
            return null;
        }
        $thumb = self::createThumbnail($dl['path'], self::THUMB_MAX_PX);
        @unlink($dl['path']);
        if ($thumb === null) {
            return null;
        }

        $jpgCache = preg_replace('/\.[^.]+$/', '', $cacheFile) . '.jpg';
        self::writePreviewCache($jpgCache, $thumb);

        return ['bytes' => $thumb, 'mime' => 'image/jpeg', 'cached' => false];
    }

    private static function previewCachePath(string $userId, string $driveId, string $path): string
    {
        $dir = EXPORT_CACHE_PATH . '/webdav';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $key = hash('sha256', $userId . '|' . $driveId . '|' . $path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' ? 'svg' : 'jpg';
        return $dir . '/' . $key . '.' . $ext;
    }

    private static function previewCacheMime(string $cacheFile): string
    {
        return str_ends_with(strtolower($cacheFile), '.svg') ? 'image/svg+xml' : 'image/jpeg';
    }

    private static function writePreviewCache(string $path, string $bytes): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        file_put_contents($path, $bytes, LOCK_EX);
    }

    private static function createThumbnail(string $sourcePath, int $maxSize): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $info = @getimagesize($sourcePath);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return null;
        }

        $src = match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false,
            default => @imagecreatefromstring((string)file_get_contents($sourcePath)),
        };
        if ($src === false) {
            return null;
        }

        $w = (int)$info[0];
        $h = (int)$info[1];
        $scale = min(1.0, $maxSize / max($w, $h));
        $tw = max(1, (int)round($w * $scale));
        $th = max(1, (int)round($h * $scale));

        if (function_exists('imagescale')) {
            $dst = imagescale($src, $tw, $th, IMG_BILINEAR_FIXED);
        } else {
            $dst = imagecreatetruecolor($tw, $th);
            if ($dst === false) {
                imagedestroy($src);
                return null;
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        }
        imagedestroy($src);
        if ($dst === false) {
            return null;
        }

        ob_start();
        imagejpeg($dst, null, 82);
        imagedestroy($dst);
        $jpeg = ob_get_clean();
        return $jpeg === false ? null : $jpeg;
    }

    public static function testConnection(array $drive): array
    {
        $url = self::buildResourceUrl($drive, '', true);
        if ($url === null) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }

        $xml = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>';
        $response = self::request($url, $drive, 'PROPFIND', $xml, [
            'Depth: 0',
            'Content-Type: application/xml; charset=utf-8',
        ]);
        if (!$response['ok']) {
            return $response;
        }
        if ($response['code'] === 401 || $response['code'] === 403) {
            return ['ok' => false, 'error' => 'auth_failed'];
        }
        if ($response['code'] === 404) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($response['code'] === 501) {
            return ['ok' => false, 'error' => 'not_webdav'];
        }
        if ($response['code'] < 200 || $response['code'] >= 300) {
            return ['ok' => false, 'error' => 'request_failed', 'http' => $response['code']];
        }
        return ['ok' => true];
    }

    public static function isMediaEntry(array $entry): bool
    {
        if (($entry['type'] ?? '') !== 'file') {
            return false;
        }
        $kind = (string)($entry['mediaKind'] ?? '');
        if ($kind !== '') {
            return in_array($kind, ['image', 'video', 'audio'], true);
        }
        return self::isImageEntry($entry);
    }

    public static function isImageEntry(array $entry): bool
    {
        if (($entry['type'] ?? '') !== 'file') {
            return false;
        }
        if (!empty($entry['previewable'])) {
            return true;
        }
        $name = (string)($entry['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($ext, self::IMAGE_EXTENSIONS, true);
    }

    /**
     * @param array{url:string,username:string,password:string,root_path?:string} $drive
     */
    private static function buildResourceUrl(array $drive, string $relativePath, bool $collection = false): ?string
    {
        $base = trim((string)($drive['url'] ?? ''));
        if ($base === '') {
            return null;
        }
        if (self::isSafeRemoteUrl($base) !== null) {
            return null;
        }

        $root = self::normalizeBrowsePath((string)($drive['root_path'] ?? ''));
        $rel = self::normalizeBrowsePath($relativePath);
        $segments = [];
        foreach ([self::urlPath($base), $root, $rel] as $part) {
            if ($part === '' || $part === '/') {
                continue;
            }
            foreach (explode('/', trim($part, '/')) as $seg) {
                if ($seg === '' || $seg === '.') {
                    continue;
                }
                if ($seg === '..') {
                    return null;
                }
                $segments[] = rawurlencode(rawurldecode($seg));
            }
        }

        $path = implode('/', $segments);
        $parts = parse_url($base);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $prefix = $parts['scheme'] . '://' . $parts['host'] . $port;
        if ($path === '') {
            return rtrim($prefix . ($parts['path'] ?? ''), '/') . '/';
        }
        $url = $prefix . '/' . $path;
        if ($collection || !self::pathLooksLikeFile($rel !== '' ? $rel : $root)) {
            $url = rtrim($url, '/') . '/';
        }
        return $url;
    }

    private static function pathLooksLikeFile(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $ext !== '' && self::detectMediaKind($ext, '') !== null;
    }

    public static function detectMediaKind(string $ext, string $mime): ?string
    {
        $ext = strtolower($ext);
        $mime = strtolower(trim(explode(';', $mime)[0]));
        if ($mime !== '' && in_array($mime, self::VIDEO_MIMES, true)) {
            return 'video';
        }
        if ($mime !== '' && in_array($mime, self::AUDIO_MIMES, true)) {
            return 'audio';
        }
        if ($mime !== '' && in_array($mime, self::IMAGE_MIMES, true)) {
            return 'image';
        }
        if (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }
        if (in_array($ext, self::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }
        if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            return 'image';
        }
        return null;
    }

    private static function maxImportSizeForKind(string $kind): int
    {
        return match ($kind) {
            'video' => MAX_VIDEO_SIZE,
            'audio' => MAX_AUDIO_SIZE,
            default => MAX_IMAGE_SIZE,
        };
    }

    private static function urlPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return is_string($path) ? trim($path, '/') : '';
    }

    public static function normalizeBrowsePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || $path === '/') {
            return '';
        }
        $parts = [];
        foreach (explode('/', trim($path, '/')) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $seg;
        }
        return implode('/', $parts);
    }

    private static function isSafeRemoteUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return 'invalid_url';
        }
        $scheme = strtolower((string)$parts['scheme']);
        if ($scheme !== 'https') {
            return 'https_required';
        }
        $host = strtolower((string)$parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'blocked_host';
        }
        foreach (self::resolveHostIps($host) as $ip) {
            if (self::isBlockedIp($ip)) {
                return 'blocked_host';
            }
        }
        return null;
    }

    /** @return list<string> */
    private static function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $rec) {
                if (!empty($rec['ip'])) {
                    $ips[] = $rec['ip'];
                } elseif (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        if (!$ips) {
            $a = gethostbyname($host);
            if ($a !== $host) {
                $ips[] = $a;
            }
        }
        return array_values(array_unique($ips));
    }

    private static function isBlockedIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        $lower = strtolower($ip);
        if ($lower === '::1' || str_starts_with($lower, 'fe80:') || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
            return true;
        }
        return false;
    }

    /**
     * @param array{username:string,password:string} $drive
     * @param list<string> $headers
     * @return array{ok:bool,error?:string,code?:int,body?:string,detail?:string}
     */
    private static function request(string $url, array $drive, string $method, ?string $body, array $headers): array
    {
        $unsafe = self::isSafeRemoteUrl($url);
        if ($unsafe !== null) {
            return ['ok' => false, 'error' => $unsafe];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init'];
        }

        $allHeaders = array_merge($headers, [
            'Accept: */*',
            'User-Agent: SlideForge-WebDAV/1.0',
        ]);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC | CURLAUTH_DIGEST,
            CURLOPT_UNRESTRICTED_AUTH => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        self::applyCurlCredentials($ch, $drive);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'error' => 'request_failed', 'detail' => $err];
        }

        if ($effectiveUrl !== '' && $effectiveUrl !== $url) {
            $redirectUnsafe = self::isSafeRemoteUrl($effectiveUrl);
            if ($redirectUnsafe !== null) {
                return ['ok' => false, 'error' => $redirectUnsafe];
            }
        }

        return ['ok' => true, 'code' => $code, 'body' => (string)$responseBody];
    }

    /** @param array{username:string,password:string} $drive */
    private static function applyCurlCredentials($ch, array $drive): void
    {
        $user = (string)($drive['username'] ?? '');
        $pass = (string)($drive['password'] ?? '');
        if ($user === '' && $pass === '') {
            return;
        }
        if (defined('CURLOPT_USERNAME')) {
            curl_setopt($ch, CURLOPT_USERNAME, $user);
            curl_setopt($ch, CURLOPT_PASSWORD, $pass);
            return;
        }
        curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
    }

    private static function downloadTimeoutForKind(string $mediaKind): int
    {
        return match ($mediaKind) {
            'video', 'audio' => self::STREAM_TIMEOUT,
            default => self::REQUEST_TIMEOUT,
        };
    }

    /**
     * @param array{username:string,password:string} $drive
     * @return array{ok:bool,error?:string,size?:int,mime?:string}
     */
    private static function download(string $url, array $drive, string $destination, int $maxSize, string $mediaKind = 'image'): array
    {
        $unsafe = self::isSafeRemoteUrl($url);
        if ($unsafe !== null) {
            return ['ok' => false, 'error' => $unsafe];
        }

        $fp = fopen($destination, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'error' => 'temp_failed'];
        }

        $size = 0;
        $mime = 'application/octet-stream';
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($fp);
            return ['ok' => false, 'error' => 'curl_init'];
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::downloadTimeoutForKind($mediaKind),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC | CURLAUTH_DIGEST,
            CURLOPT_UNRESTRICTED_AUTH => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['User-Agent: SlideForge-WebDAV/1.0'],
            CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$mime) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $mime = trim(substr($header, 13));
                    $mime = explode(';', $mime)[0];
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$size, $maxSize, $fp) {
                $len = strlen($chunk);
                $size += $len;
                if ($size > $maxSize) {
                    return 0;
                }
                return fwrite($fp, $chunk);
            },
        ]);
        self::applyCurlCredentials($ch, $drive);

        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $code < 200 || $code >= 300) {
            @unlink($destination);
            if ($size > $maxSize) {
                return ['ok' => false, 'error' => 'file_too_large'];
            }
            if ($code === 401 || $code === 403) {
                return ['ok' => false, 'error' => 'auth_failed'];
            }
            if ($code === 404) {
                return ['ok' => false, 'error' => 'not_found'];
            }
            return ['ok' => false, 'error' => 'download_failed', 'http' => $code];
        }

        if ($size <= 0) {
            @unlink($destination);
            return ['ok' => false, 'error' => 'download_failed'];
        }

        return ['ok' => true, 'size' => $size, 'mime' => $mime];
    }

    /** @return list<array>|null */
    private static function parsePropfind(string $xml, string $requestUrl, string $parentPath = ''): ?array
    {
        if ($xml === '') {
            return null;
        }
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return null;
        }
        $doc->registerXPathNamespace('d', 'DAV:');
        $responses = $doc->xpath('//d:response');
        if ($responses === false) {
            return null;
        }

        $requestPath = strtolower(rtrim(parse_url($requestUrl, PHP_URL_PATH) ?: '', '/'));
        $entries = [];

        foreach ($responses as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            $hrefNodes = $response->xpath('d:href');
            $href = isset($hrefNodes[0]) ? rawurldecode((string)$hrefNodes[0]) : '';
            $hrefPath = strtolower(rtrim(parse_url($href, PHP_URL_PATH) ?: $href, '/'));
            if ($hrefPath === $requestPath) {
                continue;
            }

            $name = basename(rtrim($href, '/'));
            if ($name === '') {
                continue;
            }

            $isCollection = false;
            $collNodes = $response->xpath('.//d:resourcetype/d:collection');
            if ($collNodes && count($collNodes) > 0) {
                $isCollection = true;
            }

            $size = 0;
            $lenNodes = $response->xpath('.//d:getcontentlength');
            if ($lenNodes && isset($lenNodes[0])) {
                $size = (int)(string)$lenNodes[0];
            }

            $mime = '';
            $mimeNodes = $response->xpath('.//d:getcontenttype');
            if ($mimeNodes && isset($mimeNodes[0])) {
                $mime = trim(explode(';', (string)$mimeNodes[0])[0]);
            }

            $relPath = self::hrefToRelativePath($href, $requestUrl);
            if ($relPath === null) {
                continue;
            }
            $fullPath = self::normalizeBrowsePath(
                ($parentPath !== '' ? $parentPath . '/' : '') . $relPath
            );

            if ($isCollection) {
                $entries[] = [
                    'name' => $name,
                    'path' => $fullPath,
                    'type' => 'directory',
                ];
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mediaKind = self::detectMediaKind($ext, $mime);
            if ($mediaKind === null) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'path' => $fullPath,
                'type' => 'file',
                'size' => $size,
                'mime' => $mime !== '' ? $mime : self::mimeFromExtension($ext),
                'mediaKind' => $mediaKind,
                'previewable' => $mediaKind === 'image',
            ];
        }

        usort($entries, static function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    private static function hrefToRelativePath(string $href, string $requestUrl): ?string
    {
        $hrefPath = rawurldecode(parse_url($href, PHP_URL_PATH) ?: $href);
        $basePath = parse_url($requestUrl, PHP_URL_PATH) ?: '';
        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && str_starts_with($hrefPath, $basePath)) {
            $rel = substr($hrefPath, strlen($basePath));
        } else {
            $rel = $hrefPath;
        }
        return self::normalizeBrowsePath($rel);
    }

    public static function mimeForExtension(string $ext): string
    {
        return self::mimeFromExtension($ext);
    }

    private static function mimeFromExtension(string $ext): string
    {
        return match (strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            default => 'application/octet-stream',
        };
    }
}
