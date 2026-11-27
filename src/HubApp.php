<?php
declare(strict_types=1);

/**
 * App → App über den Hub (AppCall, signiert mit dem App-Schlüssel der FolienSchmiede; Muster ClipForge `HubApp`).
 * Keine eigenen Geheimnisse: Der Hub prüft, ob die Aufrufer-App diese App rufen darf; das Recht der handelnden Person
 * prüft die Ziel-App (FileForge über Seafile). Modul-Token in `data/.hub-token` (`cfm_`, legt der Hub ab), App-Schlüssel
 * entsteht beim ersten Aufruf als `data/.app-key` (0600).
 */
final class HubApp
{
    public static function load(string $class): bool
    {
        if (class_exists($class, false)) {
            return true;
        }
        $dir = defined('CHURCHFORGE_SHARED_PATH') ? (string)CHURCHFORGE_SHARED_PATH : '';
        $f = rtrim($dir, '/') . '/src/' . $class . '.php';
        if ($dir !== '' && is_readable($f)) {
            require_once $f;
        }
        return class_exists($class, false);
    }

    /** @return array<string,string> leer, wenn Hub-Adresse oder Modul-Token fehlt */
    public static function context(): array
    {
        $hub = Config::get('hub', []);
        $hub = is_array($hub) ? $hub : [];
        $base = trim((string)($hub['apiBaseUrl'] ?? ''));
        if ($base === '') {
            $base = trim((string)(getenv('CHURCHFORGE_HUB_URL') ?: ''));
        }
        if ($base === '' && self::load('SharedAuth') && method_exists('SharedAuth', 'hubApiBaseUrl')) {
            $base = (string)SharedAuth::hubApiBaseUrl();
        }
        if (preg_match('#^(https://|http://127\.0\.0\.1(:\d+)?(/|$))#i', $base) !== 1) {
            return [];
        }
        $base = rtrim($base, '/');
        $file = DATA_PATH . '/.hub-token';
        if (is_link($file) || !is_file($file) || !is_readable($file) || (fileperms($file) & 0002) !== 0) {
            return [];
        }
        $token = trim((string)file_get_contents($file));
        if (!str_starts_with($token, 'cfm_')) {
            return [];
        }
        $host = strtolower(trim((string)(parse_url($base, PHP_URL_HOST) ?: ''), '[]'));
        $public = trim((string)($hub['publicHost'] ?? ''));
        return ['dataDir' => DATA_PATH, 'selfId' => 'slideforge', 'hubBaseUrl' => $base, 'hubToken' => $token,
            'hostHeader' => in_array($host, ['127.0.0.1', 'localhost', '::1'], true) ? $public : ''];
    }

    /** Server-Adresse dieser App für den Hub (`hub.selfOrigin`); leer = der Hub nimmt den Hub-Pfad (`hubPath`). */
    public static function selfOrigin(): string
    {
        $hub = Config::get('hub', []);
        return is_array($hub) ? rtrim(trim((string)($hub['selfOrigin'] ?? '')), '/') : '';
    }

    public static function ensureKey(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $ctx = self::context();
            if ($ctx === [] || !self::load('AppKeyClient')) {
                return;
            }
            $r = AppKeyClient::ensure($ctx['dataDir'], $ctx['hubBaseUrl'], $ctx['hubToken'], $ctx['hostHeader'], self::selfOrigin());
            if (empty($r['ok'])) {
                error_log('SlideForge: App-Schlüssel nicht eingetragen: ' . (string)($r['error'] ?? '?'));
            }
        } catch (Throwable) {
            // bewusst still
        }
    }

    public static function ready(): bool
    {
        static $ready = null;
        if ($ready === null) {
            self::ensureKey();
            $ready = self::context() !== [] && is_file(DATA_PATH . '/.app-key') && self::load('AppKeyClient') && self::load('AppCall');
        }
        return $ready;
    }

    /**
     * @param array<string,mixed>|null $json
     * @return array{ok:bool,status:int,json:?array<string,mixed>,error:string}
     */
    public static function call(string $target, string $method, string $path, ?array $json = null, string $person = '', int $timeout = 20): array
    {
        if (!self::ready()) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'error' => 'no_app_call'];
        }
        return AppCall::send(self::context(), $target, strtoupper($method), $path, $json, $person, $timeout);
    }

    /** @return array{ok:bool,status:int,json:?array<string,mixed>,error:string} */
    public static function stream(string $target, string $path, string $file, string $person = '', int $timeout = 3600): array
    {
        if (!self::ready()) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'error' => 'no_app_call'];
        }
        return AppCall::sendStream(self::context(), $target, 'POST', $path, $file, $person, $timeout);
    }

    /**
     * Datei per signiertem GET in eine Datei laden (AppCall kennt nur JSON-Antworten). Die Server-Adresse der Ziel-App kommt aus
     * dem Zwischenspeicher, den ein vorheriger `call()` an dieselbe App angelegt hat (60 s; der Aufrufer fragt vorher `stat`).
     *
     * @return array{ok:bool,status:int,size:int,headers:array<string,string>,error:string}
     */
    public static function download(string $target, string $path, string $dest, string $person, int $maxBytes, int $timeout = 3600): array
    {
        $fail = static fn(string $e, int $s = 0): array => ['ok' => false, 'status' => $s, 'size' => 0, 'headers' => [], 'error' => $e];
        if (!self::ready() || preg_match('/^[a-z0-9_-]{1,64}$/', $target) !== 1 || $path === '' || $path[0] !== '/'
            || str_contains($path, '..') || preg_match('/[\s\x00-\x1f#]/', $path) === 1 || preg_match('/^[A-Za-z0-9._@:+-]{1,128}\z/', $person) !== 1) {
            return $fail('bad_request');
        }
        $cache = DATA_PATH . '/.app-call-origin-' . $target . '.json';
        $d = is_file($cache) && !is_link($cache) ? json_decode((string)@file_get_contents($cache), true) : null;
        $origin = is_array($d) && !empty($d['allowed']) ? (string)($d['origin'] ?? '') : '';
        if (preg_match('#^(https?)://([^\s/:]+)(:\d+)?(/[^\s]*)?$#i', $origin, $m) !== 1) {
            return $fail('target_unknown_or_denied');
        }
        $host = strtolower($m[2]);
        $internal = $host === 'localhost' || preg_match('/^127\./', $host) === 1 || !str_contains($host, '.');
        if (strtolower($m[1]) === 'http' && !$internal) {
            return $fail('target_unknown_or_denied');
        }
        $path .= (str_contains($path, '?') ? '&' : '?') . '_ap=' . rawurlencode($person);
        $ctx = self::context();
        $sig = AppKeyClient::headers($ctx['dataDir'], $ctx['selfId'], $target, 'GET', $path, '');
        if ($sig === []) {
            return $fail('no_app_key');
        }
        $h = ['Accept: application/octet-stream'];
        foreach ($sig as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        $fp = @fopen($dest, 'wb');
        if ($fp === false) {
            return $fail('not_writable');
        }
        $headers = [];
        $size = 0;
        $tooBig = false;
        $ch = curl_init(rtrim($origin, '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => max(10, $timeout),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers): int {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use ($fp, &$size, $maxBytes, &$tooBig): int {
                $size += strlen($chunk);
                if ($size > $maxBytes) {
                    $tooBig = true;
                    return 0;
                }
                return (int)fwrite($fp, $chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($tooBig) {
            @unlink($dest);
            return $fail('too_large', 413);
        }
        if ($status !== 200) {
            $err = '';
            if ($status > 0 && $size < 4096) {
                $j = json_decode((string)@file_get_contents($dest), true);
                $err = is_array($j) ? (string)($j['error'] ?? '') : '';
            }
            @unlink($dest);
            return $fail($err !== '' ? $err : ($status === 0 ? 'unreachable' : 'http_' . $status), $status);
        }
        return ['ok' => true, 'status' => 200, 'size' => $size, 'headers' => $headers, 'error' => ''];
    }

    /**
     * Aufgabe im Task Manager des Hubs melden (`api/tasks.php?op=report`, signiert mit dem App-Schlüssel, Ziel `hub`).
     *
     * @param array<string,mixed> $body
     * @return array{ok:bool,status:int,json:?array<string,mixed>,error:string}
     */
    public static function task(array $body, int $timeout = 8): array
    {
        return self::hub('/api/tasks.php?op=report', $body, $timeout);
    }

    /**
     * Signierter POST an den eigenen Hub (Ziel `hub`, App-Schlüssel, JSON-Körper), z. B. `api/anzeige.php` (Anzeige-Geräte, 27.11.2026).
     *
     * @param array<string,mixed> $body
     * @return array{ok:bool,status:int,json:?array<string,mixed>,error:string}
     */
    public static function hub(string $path, array $body, int $timeout = 8): array
    {
        $ctx = self::ready() ? self::context() : [];
        if ($ctx === [] || !method_exists('AppKeyClient', 'hubHeaders')) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'error' => 'no_app_call'];
        }
        $raw = (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig = AppKeyClient::hubHeaders($ctx['dataDir'], $ctx['selfId'], 'POST', $path, $raw);
        if ($sig === []) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'error' => 'no_app_key'];
        }
        $h = ['Accept: application/json', 'Content-Type: application/json'];
        foreach ($sig as $k => $v) {
            $h[] = is_int($k) ? (string)$v : $k . ': ' . $v;
        }
        if ($ctx['hostHeader'] !== '') {
            $h[] = 'Host: ' . $ctx['hostHeader'];
        }
        $ch = curl_init($ctx['hubBaseUrl'] . $path);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false]);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $j = is_string($resp) ? json_decode($resp, true) : null;
        $ok = $status >= 200 && $status < 300 && is_array($j) && !empty($j['ok']);
        return ['ok' => $ok, 'status' => $status, 'json' => is_array($j) ? $j : null, 'error' => $ok ? '' : ($status === 0 ? 'unreachable' : 'http_' . $status)];
    }

    /**
     * @param array<string,string> $headers
     * @return array{ok:bool,caller:string,person:string,error:string}
     */
    public static function receive(array $headers, string $method, string $path, string $body): array
    {
        if (!self::ready()) {
            return ['ok' => false, 'caller' => '', 'person' => '', 'error' => 'no_app_call'];
        }
        return AppCall::receive(self::context(), $headers, $method, $path, $body);
    }

    /** Eingehender Aufruf: Pfad ab `/hooks/` samt Query (so, wie die Signatur ihn deckt). */
    public static function hookPath(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $i = strpos($uri, '/hooks/');
        return $i === false ? '' : substr($uri, $i);
    }
}
