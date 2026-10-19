<?php

/**
 * ChurchForge Shared Auth — eine Session für Hub + Module (*.bkbiel.ch etc.).
 * Cookie: cf_session (HS256 JWT). Secret: CHURCHFORGE_AUTH_SECRET oder auth-secret.txt.
 */
class SharedAuth
{
    public const COOKIE_NAME = 'cf_session';
    public const THEME_COOKIE = 'cf_theme';
    private const TTL_SECONDS = 1209600; // 14 Tage

    /** @param array<string,mixed> $user */
    public static function issueCookie(array $user): void
    {
        $personId = self::resolvePersonId($user);
        $payload = [
            'sub' => $personId > 0 ? (string)$personId : (string)($user['person_id'] ?? $user['sub'] ?? ''),
            'username' => (string)($user['username'] ?? ''),
            'display_name' => (string)($user['display_name'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'avatar_url' => (string)($user['avatar_url'] ?? ''),
            'tags' => array_values($user['tags'] ?? []),
            'groups' => array_values($user['groups'] ?? []),
            'provider' => (string)($user['provider'] ?? 'churchtools'),
            'ct_person_id' => $personId,
            'ct_login_token' => (string)($user['ct_login_token'] ?? ''),
            'iat' => time(),
            'exp' => time() + self::TTL_SECONDS,
        ];

        $token = self::encode($payload);
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => $payload['exp'],
            'path' => '/',
            'domain' => self::cookieDomain(),
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => self::cookieDomain(),
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }


    /** Preference: dark|light|system (not the resolved theme). */
    public static function issueThemeCookie(string $pref): void
    {
        $pref = self::normalizeThemePref($pref);
        setcookie(self::THEME_COOKIE, $pref, [
            'expires' => time() + self::TTL_SECONDS,
            'path' => '/',
            'domain' => self::cookieDomain(),
            'secure' => self::isSecureRequest(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::THEME_COOKIE] = $pref;
    }

    public static function themePref(): string
    {
        return self::normalizeThemePref((string)($_COOKIE[self::THEME_COOKIE] ?? 'dark'));
    }

    /** Resolved dark|light for server-side html[data-theme] (system → dark until JS). */
    public static function resolvedTheme(?string $pref = null): string
    {
        $pref = self::normalizeThemePref($pref ?? self::themePref());
        if ($pref === 'light') {
            return 'light';
        }
        return 'dark';
    }

    public static function normalizeThemePref(string $pref): string
    {
        $pref = strtolower(trim($pref));
        return in_array($pref, ['dark', 'light', 'system'], true) ? $pref : 'dark';
    }


    /** @return array<string,mixed>|null */
    public static function read(): ?array
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($raw === '') {
            return null;
        }
        return self::decode((string)$raw);
    }

    public static function hubUrl(): string
    {
        $url = trim((string)(getenv('CHURCHFORGE_HUB_URL') ?: ''));
        return $url !== '' ? rtrim($url, '/') : 'https://bkbiel.ch';
    }

    public static function shouldUseHubLogin(): bool
    {
        if (defined('DEMO_MODE') && DEMO_MODE) {
            return false;
        }

        $flag = strtolower(trim((string)(getenv('CHURCHFORGE_HUB_LOGIN') ?: '')));
        if (in_array($flag, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($flag, ['0', 'false', 'no'], true)) {
            return false;
        }

        return self::isSharedAuthHost(self::requestHost());
    }

    public static function redirectToHubLogin(string $returnTo = ''): void
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '') {
            $returnTo = (string)($_SERVER['REQUEST_URI'] ?? '/');
        }

        $url = self::hubUrl() . '/login.php';
        if (self::isAllowedReturnUrl($returnTo)) {
            $url .= '?return=' . rawurlencode(self::absoluteReturnUrl($returnTo));
        }

        header('Location: ' . $url);
        exit;
    }

    public static function isAllowedReturnUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        return self::isSharedAuthHost($host);
    }

    public static function absoluteReturnUrl(string $returnTo): string
    {
        if (str_starts_with($returnTo, 'http://') || str_starts_with($returnTo, 'https://')) {
            return $returnTo;
        }

        $scheme = self::isSecureRequest() ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . '/' . ltrim($returnTo, '/');
    }

    /** @return array<string,mixed>|null */
    public static function validateToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        return self::decode($token);
    }

    /**
     * FolienSchmiede: Session aus hub_session-Handoff oder Hub-Cookie übernehmen
     * (Auto-Provision wenn Auth::ensureFromHubPayload existiert).
     */
    public static function bootstrapSlideForge(): bool
    {
        if (!class_exists('Auth')) {
            return false;
        }
        // Bereits eingeloggt: Hub-Cookie / Hub-Pflicht → auth_via nachziehen
        if (Auth::isLoggedIn()) {
            $via = (string)($_SESSION['auth_via'] ?? '');
            if ($via !== 'churchforge_hub') {
                $existing = self::read();
                if (is_array($existing) && $existing !== []) {
                    $_SESSION['auth_via'] = 'churchforge_hub';
                } elseif (self::shouldUseHubLogin()) {
                    $_SESSION['auth_via'] = 'churchforge_hub';
                }
            }
            return true;
        }

        $fromHandoff = false;
        $payload = null;

        $handoff = trim((string)($_GET['hub_session'] ?? ''));
        if ($handoff !== '') {
            $payload = self::validateToken($handoff);
            $fromHandoff = $payload !== null;
        }
        if ($payload === null) {
            $payload = self::read();
        }
        if ($payload === null) {
            return false;
        }

        $user = null;
        if (method_exists('Auth', 'ensureFromHubPayload')) {
            $user = Auth::ensureFromHubPayload($payload);
        } else {
            $user = self::resolveExistingSlideForgeUser($payload);
        }
        if ($user === null) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['auth_via'] = 'churchforge_hub';

        if ($fromHandoff) {
            self::issueCookie($payload);
            self::redirectStripHandoff();
        }

        return true;
    }

    private static function redirectStripHandoff(): void
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return;
        }
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        unset($query['hub_session']);
        $path = (string)($parts['path'] ?? '/');
        $new = $path;
        if ($query !== []) {
            $new .= '?' . http_build_query($query);
        }
        if ($new !== $uri) {
            header('Location: ' . $new);
            exit;
        }
    }

    /** @param array<string,mixed> $payload */
    private static function resolvePersonId(array $user): int
    {
        foreach (['person_id', 'ct_person_id', 'sub', 'id'] as $key) {
            if (!array_key_exists($key, $user)) {
                continue;
            }
            $raw = $user[$key];
            if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
                $pid = (int)$raw;
                if ($pid > 0) {
                    return $pid;
                }
            }
        }
        return 0;
    }

    /** @param array<string,mixed> $payload */
    private static function resolveExistingSlideForgeUser(array $payload): ?array
    {
        $ctPersonId = self::resolvePersonId($payload);
        if ($ctPersonId > 0 && defined('USERS_FILE') && class_exists('Storage')) {
            $users = Storage::read(USERS_FILE, []);
            foreach ($users as $u) {
                if ((int)($u['ct_person_id'] ?? 0) === $ctPersonId) {
                    return $u;
                }
            }
        }

        $username = trim((string)($payload['username'] ?? ''));
        if ($username !== '' && method_exists('Auth', 'findByUsername')) {
            $user = Auth::findByUsername($username);
            if ($user !== null) {
                return $user;
            }
        }

        $email = trim((string)($payload['email'] ?? ''));
        if ($email !== '' && method_exists('Auth', 'findByEmail')) {
            $user = Auth::findByEmail($email);
            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $payload */
    private static function encode(array $payload): string
    {
        $header = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_UNICODE));
        $body = self::b64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sig = self::b64url(hash_hmac('sha256', $header . '.' . $body, self::secret(), true));
        return $header . '.' . $body . '.' . $sig;
    }

    /** @return array<string,mixed>|null */
    private static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;
        /* Fehlt der Schluessel, gilt KEIN Token: lieber niemand angemeldet als
           jemand mit einer Unterschrift, die wir nicht pruefen koennen. Die
           Ausnahme wird nur hier gefangen - beim Ausstellen (encode) soll sie
           durchschlagen, damit nie ein unpruefbares Cookie entsteht. */
        try {
            $key = self::secret();
        } catch (RuntimeException $e) {
            return null;
        }
        $expected = self::b64url(hash_hmac('sha256', $header . '.' . $body, $key, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $json = json_decode(self::b64urlDecode($body), true);
        if (!is_array($json) || ($json['exp'] ?? 0) < time()) {
            return null;
        }

        // S-1 Phase 1 (Entwurf ct-persoenlich v2, Review M2/M3): das CT-Token kann verschlüsselt kommen
        // (`ct_login_token_enc`). Für alle Leser wie bisher als `ct_login_token`; misslingt das Entschlüsseln, gibt
        // es kein Token — nie eine Abmeldung. Die alte Klartext-Form gilt weiter, bis Phase 2 überall steht.
        if (isset($json['ct_login_token_enc'])) {
            $klar = self::feldEntschluesseln((string)$json['ct_login_token_enc'], (string)($json['sub'] ?? ''));
            if (trim((string)($json['ct_login_token'] ?? '')) === '') {
                $json['ct_login_token'] = $klar;
            }
            unset($json['ct_login_token_enc']);
        }
        // GO ist weg (Urs 19.10.) — ein altes Feld wird nicht mehr weitergereicht (Review M1).
        unset($json['go_access_token']);
        return $json;
    }

    /**
     * Der Unterschrift-Schluessel. Hier stand bis 15.10.2026 ein fester Wert aus
     * dem Quelltext als Rueckfall; wer den Quelltext lesen konnte, konnte damit
     * Sitzungen faelschen - inklusive Admin ueber die Tags im Nutzinhalt
     * (SlideForge, 16.09., rund sieben Stunden). Darum: ohne echte Quelle KEIN
     * Schluessel und kein neuer Vorgabewert, auch kein abgeleiteter.
     *
     * @throws RuntimeException wenn weder Umgebungsvariable noch eine der
     *         Kandidatendateien einen nicht leeren Wert liefert. Die Meldung
     *         nennt nur die Zahl der Kandidaten, nie einen Pfad und nie einen
     *         Schluesselwert (die landen sonst im Webserver-Protokoll).
     */
    private static function secret(): string
    {
        $secret = trim((string)(getenv('CHURCHFORGE_AUTH_SECRET') ?: ''));
        if ($secret !== '') {
            return $secret;
        }

        $files = self::secretFiles();
        foreach ($files as $file) {
            if (is_readable($file)) {
                $raw = trim((string)file_get_contents($file));
                if ($raw !== '') {
                    return $raw;
                }
            }
        }

        self::logSecretMissing(count($files));
        throw new RuntimeException('secret.missing');
    }

    /** Eine Protokollzeile je Anfrage - ohne Schluesselwert, ohne Pfade. */
    private static function logSecretMissing(int $candidates): void
    {
        static $logged = false;
        if ($logged) {
            return;
        }
        $logged = true;
        error_log(sprintf(
            'SharedAuth secret.missing: kein Sitzungsschluessel gefunden (Umgebungsvariable leer, 0 von %d Kandidatendateien lesbar und nicht leer)',
            $candidates
        ));
    }

    /** @return list<string> */
    private static function secretFiles(): array
    {
        $paths = [];
        $shared = trim((string)(getenv('CHURCHFORGE_SHARED_PATH') ?: ''));
        if ($shared !== '') {
            $paths[] = rtrim($shared, '/') . '/auth-secret.txt';
        }
        if (defined('CHURCHFORGE_SHARED_PATH')) {
            $paths[] = CHURCHFORGE_SHARED_PATH . '/auth-secret.txt';
        }
        if (defined('HUB_BASE_PATH')) {
            $paths[] = HUB_BASE_PATH . '/data/gemeinde/auth-secret.txt';
            $paths[] = dirname(HUB_BASE_PATH) . '/shared/auth-secret.txt';
        }
        if (defined('BASE_PATH')) {
            $paths[] = dirname(BASE_PATH) . '/shared/auth-secret.txt';
            $paths[] = dirname(BASE_PATH) . '/hub/data/gemeinde/auth-secret.txt';
        }
        if (defined('DATA_PATH')) {
            $paths[] = DATA_PATH . '/auth-secret.txt';
        }
        $paths[] = '/data/www/shared/auth-secret.txt';
        return array_values(array_unique($paths));
    }

    private static function cookieDomain(): string
    {
        $configured = trim((string)(getenv('CHURCHFORGE_COOKIE_DOMAIN') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = self::requestHost();
        if ($host === 'localhost' || $host === '') {
            return '';
        }

        foreach (['.bkbiel.ch', '.wunder.li', '.fworsa.ch', '.churchforge.dev'] as $suffix) {
            $bare = ltrim($suffix, '.');
            if ($host === $bare || str_ends_with($host, $suffix)) {
                return $suffix;
            }
        }

        return '';
    }

    private static function requestHost(): string
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        return preg_replace('/:\d+$/', '', $host) ?? $host;
    }

    private static function isSharedAuthHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        foreach (['bkbiel.ch', 'wunder.li', 'fworsa.ch', 'churchforge.dev'] as $bare) {
            if ($host === $bare || str_ends_with($host, '.' . $bare)) {
                return true;
            }
        }
        return false;
    }

    private static function isSecureRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /**
     * Schlüssel für verschlüsselte Felder im JWT: HKDF aus dem **rohen** gemeinsamen Geheimnis, Zweck
     * `cf-session-feld-v1` — byte-gleich in jeder Fassung von SharedAuth (Review M3). Kein neuer Schlüssel.
     * Schutz gegen Protokolle, Proxys und Übergabe-Adressen; nicht gegen ein Modul mit dem Geheimnis.
     */
    private static function feldSchluessel(): string
    {
        return hash_hkdf('sha256', self::secret(), 32, 'cf-session-feld-v1');
    }

    /** `v1.<nonce>.<tag>.<daten>` (base64url), AES-256-GCM, AAD = `cf-session|<sub>` (Review N4). */
    public static function feldVerschluesseln(string $klar, string $sub): string
    {
        if ($klar === '') {
            return '';
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($klar, 'aes-256-gcm', self::feldSchluessel(), OPENSSL_RAW_DATA, $nonce, $tag, 'cf-session|' . $sub, 16);
        return $ct === false ? '' : 'v1.' . self::b64url($nonce) . '.' . self::b64url($tag) . '.' . self::b64url($ct);
    }

    private static function feldEntschluesseln(string $enc, string $sub): string
    {
        $teile = explode('.', $enc);
        if (count($teile) !== 4 || $teile[0] !== 'v1') {
            return '';
        }
        try {
            [$n, $t, $c] = [self::b64urlDecode($teile[1]), self::b64urlDecode($teile[2]), self::b64urlDecode($teile[3])];
            if (strlen($n) !== 12 || strlen($t) !== 16) {
                return '';
            }
            $klar = openssl_decrypt($c, 'aes-256-gcm', self::feldSchluessel(), OPENSSL_RAW_DATA, $n, $t, 'cf-session|' . $sub);
            return is_string($klar) ? $klar : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $text): string
    {
        $pad = strlen($text) % 4;
        if ($pad > 0) {
            $text .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($text, '-_', '+/'), true);
        return $raw === false ? '' : $raw;
    }
}
