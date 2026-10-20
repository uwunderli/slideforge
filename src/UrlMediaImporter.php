<?php
/**
 * Importiert ein Medium von einer frei eingegebenen Adresse in eine Präsentation.
 *
 * Bewusst getrennt von PixabayClient: dort schützt eine Host-Positivliste
 * (ALLOWED_HOSTS), die hier nicht anwendbar ist. Stattdessen prüft diese Klasse
 * jede Adresse einzeln, damit der Knopf kein Werkzeug wird, mit dem man vom
 * Server aus ins interne Netz greift.
 */
class UrlMediaImporter
{
    private const MAX_REDIRECTS = 3;
    private const TIMEOUT_SECONDS = 30;

    private const ALLOWED = [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'],
        'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
        'audio' => ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a'],
    ];

    /**
     * @return array{ok: bool, error?: string, url?: string, filename?: string, kind?: string}
     */
    public static function import(string $presentationId, string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'error' => 'invalid_url'];
        }

        $resolved = self::resolveSafely($url);
        if ($resolved === null) {
            return ['ok' => false, 'error' => 'blocked_url'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sf_url_');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'temp_failed'];
        }

        // Grösszügigste Grenze zum Laden; die endgültige Prüfung erfolgt
        // typabhängig, sobald der echte MIME-Typ bekannt ist.
        $hardLimit = max(MAX_IMAGE_SIZE, MAX_VIDEO_SIZE, MAX_AUDIO_SIZE);
        if (!self::download($resolved, $tmp, $hardLimit)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'download_failed'];
        }

        $size = filesize($tmp) ?: 0;
        if ($size <= 0) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'download_failed'];
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $tmp) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!is_string($mime)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'unsupported_type'];
        }
        // SVG wird von finfo je nach Inhalt als text/* oder XML gemeldet.
        if (in_array($mime, ['text/xml', 'application/xml', 'text/plain'], true)
            && self::looksLikeSvg($tmp)) {
            $mime = 'image/svg+xml';
        }

        $kind = self::kindForMime($mime);
        if ($kind === null) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'unsupported_type'];
        }

        $maxSize = $kind === 'video' ? MAX_VIDEO_SIZE : ($kind === 'audio' ? MAX_AUDIO_SIZE : MAX_IMAGE_SIZE);
        if ($size > $maxSize) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'file_too_large'];
        }

        /* SVG von fremden Adressen ebenso entschärfen wie beim Upload. */
        if ($mime === 'image/svg+xml') {
            $clean = SvgHelper::sanitize((string)@file_get_contents($tmp));
            if ($clean === null || @file_put_contents($tmp, $clean) === false) {
                @unlink($tmp);
                return ['ok' => false, 'error' => 'unsupported_type'];
            }
        }

        $ext = self::ALLOWED[$kind][$mime];
        $filename = 'url_' . Storage::generateId(6) . '.' . $ext;
        $assetsDir = Presentation::dir($presentationId) . '/assets';
        if (!is_dir($assetsDir) && !@mkdir($assetsDir, 0770, true)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'save_failed'];
        }

        $destination = $assetsDir . '/' . $filename;
        if (!@rename($tmp, $destination)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'save_failed'];
        }
        @chmod($destination, 0640);

        return [
            'ok' => true,
            'filename' => $filename,
            'url' => 'asset.php?id=' . urlencode($presentationId) . '&file=' . urlencode($filename),
            'kind' => $kind,
        ];
    }

    private static function kindForMime(string $mime): ?string
    {
        foreach (self::ALLOWED as $kind => $map) {
            if (isset($map[$mime])) {
                return (string)$kind;
            }
        }
        return null;
    }

    private static function looksLikeSvg(string $path): bool
    {
        $head = (string)@file_get_contents($path, false, null, 0, 512);
        return stripos($head, '<svg') !== false;
    }

    /**
     * Prüft die Adresse und folgt Umleitungen selbst, damit jede Zwischenstation
     * erneut geprüft wird. CURLOPT_FOLLOWLOCATION würde die Prüfung umgehen:
     * eine erlaubte Adresse könnte auf 127.0.0.1 oder 169.254.169.254 zeigen.
     */
    private static function resolveSafely(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (!self::isPublicHttpsUrl($url)) {
                return null;
            }
            $location = self::redirectTarget($url);
            if ($location === null) {
                return $url;
            }
            $url = self::absolutize($url, $location);
            if ($url === null) {
                return null;
            }
        }
        return null;
    }

    private static function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        if (strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        // Zugangsdaten in der Adresse sind ein Umgehungsmuster.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower(trim((string)($parts['host'] ?? '')));
        if ($host === '') {
            return false;
        }
        $port = (int)($parts['port'] ?? 443);
        if ($port !== 443) {
            return false;
        }

        foreach (self::hostAddresses($host) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        return self::hostAddresses($host) !== [];
    }

    /** @return list<string> */
    private static function hostAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $ips = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $rec) {
            if (!empty($rec['ipv6'])) {
                $ips[] = (string)$rec['ipv6'];
            }
        }
        return array_values(array_unique($ips));
    }

    /** Gibt das Location-Ziel zurück, wenn die Adresse umleitet, sonst null. */
    private static function redirectTarget(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'SlideForge/' . ASSET_VERSION,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($code >= 300 && $code < 400 && $location !== '') {
            return $location;
        }
        return null;
    }

    private static function absolutize(string $base, string $location): ?string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($base);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $prefix = 'https://' . $parts['host'];
        return $prefix . '/' . ltrim($location, '/');
    }

    private static function download(string $url, string $destination, int $maxBytes): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $fh = @fopen($destination, 'wb');
        if ($fh === false) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'SlideForge/' . ASSET_VERSION,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $expected, $loaded) use ($maxBytes) {
                // Abbruch, sobald die Grenze überschritten wird — auch wenn der
                // Server keine Content-Length meldet.
                return ($expected > $maxBytes || $loaded > $maxBytes) ? 1 : 0;
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($ok === false || $code < 200 || $code >= 300) {
            return false;
        }
        return true;
    }
}
