<?php
/**
 * Zentrale Konfiguration.
 * Diese Datei liegt bewusst AUSSERHALB von public/ und ist damit vom Web aus nicht
 * erreichbar: SlideForge laeuft unter <Hub>/slideforge/ im Web-Share des Hubs, nginx
 * liefert dort nur /slideforge/public/ aus (Hub-Pfad, 27.11.2026).
 */

// Basisverzeichnisse
define('BASE_PATH', __DIR__);
/* Modulwurzel fuer die Hub-Bibliothek: hub_asset() sucht im Alleinbetrieb die
   mitgefuehrte Kopie unter der Modulwurzel (shared/bootstrap.php).
   Ohne die Konstante faellt es auf DOCUMENT_ROOT zurueck - das stimmt hier, haengt
   aber am Webserver; mit der Konstante ist der Pfad eindeutig. */
define('SLIDEFORGE_ROOT', __DIR__);
define('DATA_PATH', BASE_PATH . '/data');
define('PRESENTATIONS_PATH', DATA_PATH . '/presentations');
define('USERS_FILE', DATA_PATH . '/users.json');
define('LANG_PATH', __DIR__ . '/lang');
define('SITE_CONFIG_FILE', DATA_PATH . '/config.json');
define('TEXT_TEMPLATES_FILE', DATA_PATH . '/text_templates.json');
define('EXPORT_CACHE_PATH', DATA_PATH . '/cache');
// Logo liegt bewusst im Web-Root (öffentlich sichtbar, auch auf der Login-Seite)
define('PUBLIC_UPLOADS_PATH', __DIR__ . '/public/uploads');
define('FONTS_UPLOAD_PATH', PUBLIC_UPLOADS_PATH . '/fonts');
define('MAX_LOGO_SIZE', 3 * 1024 * 1024); // 3 MB
define('MAX_FONT_SIZE', 10 * 1024 * 1024); // 10 MB

// Standard-Foliengrösse für neue Präsentationen
define('DEFAULT_SLIDE_WIDTH', 1920);
define('DEFAULT_SLIDE_HEIGHT', 1080);

// Maximale Upload-Grössen für Hintergrund-Bilder/Videos
define('MAX_IMAGE_SIZE', 15 * 1024 * 1024);  // 15 MB
define('MAX_VIDEO_SIZE', 60 * 1024 * 1024);  // 60 MB
define('MAX_AUDIO_SIZE', 60 * 1024 * 1024);  // 60 MB (WAV ist unkomprimiert und wird schnell gross)

// App-Name (für Titel etc.)
define('APP_NAME', 'SlideForge');

// Demo-Instanz: Banner auf allen Seiten mit Header (Login, Dashboard, Editor …).
// Auf der öffentlichen Demo-Subdomain auf true setzen; Produktiv-Installation: false.
define('DEMO_MODE', false);

// Versionsnummer für CSS/JS-Cache-Busting: bei jedem Deployment mit
// geänderten assets/-Dateien hochzählen, damit Browser nicht die alte
// gecachte Version von style.css / editor.js weiterverwenden.
define('ASSET_VERSION', '708');

/** Seed-Ordnername des Standard-Folien-Sets (seed/layout-sets/<name>/). */
define('DEFAULT_LAYOUT_SET_SEED', 'schlicht');

// Fehleranzeige: Meldungstexte gehoeren ins Protokoll, nicht auf die Seite — sie
// nennen Dateipfade und Innereien. Deprecated-Hinweise bleiben ganz aussen vor.
// Zum Entwickeln mit SLIDEFORGE_DEBUG=1 wieder einschalten; auf Prod ist die
// Variable nicht gesetzt, dort gilt also 0. Siehe
// befund-fehleranzeige-prod-2026-10-18.md.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', getenv('SLIDEFORGE_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
// Hinweis: `display_startup_errors` hier zu setzen waere wirkungslos — die
// Startphase ist vorbei, wenn diese Zeile laeuft. Das gehoert in die Server-ini.

// Session: eigener Name und Pfad unter dem Hub (kein Mitlesen der Hub-Sitzung, keine Kollision mit
// anderen Apps auf demselben Host). Muss vor jedem Output passieren.
if (session_status() === PHP_SESSION_NONE) {
    $__secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('SFSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/slideforge/',
        'secure' => $__secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    unset($__secure);
}
sf_look_capture(); // Akzent/Fenster-Stil des Hubs aus der Rahmenadresse für spätere Seiten merken (wie FileForge ff_look_capture)

// Autoloading der eigenen Klassen in src/
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Hub-Bibliothek (SharedAuth, hub_asset, HubPersonKey): Pflicht. SlideForge laeuft nur noch im Hub.
$sharedPath = '';
foreach ([(string)(getenv('CHURCHFORGE_SHARED_PATH') ?: ''), dirname(BASE_PATH) . '/shared', '/data/www/shared', '/var/www/shared'] as $__dir) {
    $__dir = rtrim($__dir, '/');
    if ($__dir !== '' && is_readable($__dir . '/bootstrap.php')) {
        $sharedPath = $__dir;
        break;
    }
}
unset($__dir);
if ($sharedPath === '') {
    error_log('SlideForge: shared/bootstrap.php nicht gefunden');
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'SlideForge ist nicht vollständig installiert (Hub-Bibliothek fehlt).';
    exit;
}
if (!defined('CHURCHFORGE_SHARED_PATH')) {
    define('CHURCHFORGE_SHARED_PATH', $sharedPath);
}
require_once $sharedPath . '/bootstrap.php';

// Anmeldung nur ueber den Hub: Sitzung aus Hub-Cookie bzw. hub_session uebernehmen.
Auth::bootstrapHub();

Mobile::handleOverride();

// Sicherstellen, dass die Datenverzeichnisse existieren
if (!is_dir(PRESENTATIONS_PATH)) {
    mkdir(PRESENTATIONS_PATH, 0770, true);
}
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([], JSON_PRETTY_PRINT));
}
if (!file_exists(SITE_CONFIG_FILE)) {
    file_put_contents(SITE_CONFIG_FILE, json_encode([
        'site_title' => APP_NAME,
        'logo' => '',
        'smtp' => [
            'host' => '', 'port' => 587, 'encryption' => 'tls',
            'username' => '', 'password' => '', 'from_email' => '', 'from_name' => APP_NAME,
        ],
        'brand_colors' => [
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
        ],
    ], JSON_PRETTY_PRINT));
}
if (!file_exists(TEXT_TEMPLATES_FILE)) {
    file_put_contents(TEXT_TEMPLATES_FILE, json_encode([
        ['id' => 'title', 'name' => 'Titel', 'fontFamily' => 'Open Sans', 'fontSize' => 120, 'fontWeight' => 'bold', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false, 'color' => '#ffffff', 'align' => 'center', 'w' => 1270, 'h' => 90],
        ['id' => 'subtitle', 'name' => 'Untertitel', 'fontFamily' => 'PT Sans', 'fontSize' => 68, 'fontWeight' => 'normal', 'italic' => true, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false, 'color' => '#94c2dc', 'align' => 'center', 'w' => 1270, 'h' => 80],
        ['id' => '0b3aec509d2e', 'name' => 'Überschrift 1', 'fontFamily' => 'Open Sans', 'fontSize' => 86, 'fontWeight' => 'bold', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => true, 'color' => '#ffffff', 'align' => 'left', 'w' => 1720, 'h' => 100],
        ['id' => '6ccca4e48029', 'name' => 'Überschrift 2', 'fontFamily' => 'Open Sans', 'fontSize' => 74, 'fontWeight' => 'normal', 'italic' => true, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => true, 'color' => '#ffffff', 'align' => 'left', 'w' => 1720, 'h' => 60],
        ['id' => '982874cf3eb0', 'name' => 'Überschrift 3', 'fontFamily' => 'Open Sans', 'fontSize' => 68, 'fontWeight' => 'normal', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => true, 'color' => '#ffffff', 'align' => 'left', 'w' => 1720, 'h' => 60],
        ['id' => 'standard', 'name' => 'Text', 'fontFamily' => 'Open Sans', 'fontSize' => 65, 'fontWeight' => 'normal', 'italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false, 'color' => '#ffffff', 'align' => 'left', 'w' => 599, 'h' => 70],
    ], JSON_PRETTY_PRINT));
}
if (!is_dir(PUBLIC_UPLOADS_PATH)) {
    mkdir(PUBLIC_UPLOADS_PATH, 0770, true);
}
if (!is_dir(FONTS_UPLOAD_PATH)) {
    mkdir(FONTS_UPLOAD_PATH, 0770, true);
}
if (!is_dir(EXPORT_CACHE_PATH)) {
    mkdir(EXPORT_CACHE_PATH, 0770, true);
}

// Migration: User aus einer Version vor der Rollenverwaltung bekommen role/theme nachgetragen.
// Der zuerst angelegte User wird dabei automatisch Administrator.
$__existingUsers = Storage::read(USERS_FILE, []);
$__needsMigration = false;
foreach ($__existingUsers as $__u) {
    if (!isset($__u['role']) || !isset($__u['theme'])) {
        $__needsMigration = true;
        break;
    }
}
if ($__needsMigration) {
    Storage::update(USERS_FILE, function ($users) {
        foreach ($users as $i => &$u) {
            if (!isset($u['role'])) {
                $u['role'] = $i === 0 ? 'admin' : 'editor';
            }
            if (!isset($u['theme'])) {
                $u['theme'] = 'dark';
            }
        }
        return $users;
    }, []);
}
unset($__existingUsers, $__needsMigration, $__u);


// Migration: bestehende config.json (aus einer Version vor den Vorlagen) um
// brand_colors nachrüsten, falls das Feld fehlt.
$__cfg = Storage::read(SITE_CONFIG_FILE, []);
if (!isset($__cfg['brand_colors'])) {
    Storage::update(SITE_CONFIG_FILE, function ($cfg) {
        $cfg['brand_colors'] = [
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
        return $cfg;
    }, []);
}
unset($__cfg);

$__cfg = Storage::read(SITE_CONFIG_FILE, []);
if (!isset($__cfg['pixabay'])) {
    Storage::update(SITE_CONFIG_FILE, function ($cfg) {
        $cfg['pixabay'] = ['enabled' => true, 'api_key' => ''];
        return $cfg;
    }, []);
}
unset($__cfg);

// Migration: bestehende Textvorlagen (aus einer Version vor den erweiterten
// Textformaten) bekommen die neuen Felder mit sicheren Defaults nachgetragen.
$__textTemplates = Storage::read(TEXT_TEMPLATES_FILE, []);
$__ttNeedsMigration = false;
foreach ($__textTemplates as $__tt) {
    if (!array_key_exists('smallCaps', $__tt) || !array_key_exists('uppercase', $__tt)
        || !array_key_exists('italic', $__tt) || !array_key_exists('underline', $__tt)
        || !array_key_exists('strikethrough', $__tt)) {
        $__ttNeedsMigration = true;
        break;
    }
}
if ($__ttNeedsMigration) {
    Storage::update(TEXT_TEMPLATES_FILE, function ($list) {
        foreach ($list as &$t) {
            $t += ['italic' => false, 'underline' => false, 'strikethrough' => false, 'uppercase' => false, 'smallCaps' => false];
        }
        return $list;
    }, []);
}
unset($__textTemplates, $__ttNeedsMigration, $__tt);

TextTemplate::ensureFallback();

// Verständliche Fehlermeldung statt rohem PHP-Fatal-Error bei Schreibproblemen
set_exception_handler(function (Throwable $e) {
    // Zuerst ins Protokoll: diese Seite zeigt bewusst keine Innereien, und ohne
    // diese Zeile bliebe eine nicht gefangene Ausnahme voellig spurlos — gemessen
    // im Review vom 18.10. (Auflage A). Der Text geht nur ins Protokoll.
    error_log('SlideForge: nicht gefangene Ausnahme: ' . $e);
    http_response_code(500);
    if ($e instanceof RuntimeException) {
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Fehler</title></head><body style="font-family:sans-serif; max-width:600px; margin:60px auto;">';
        echo '<h1>Etwas ist schiefgelaufen</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
    } else {
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Fehler</title></head><body style="font-family:sans-serif; max-width:600px; margin:60px auto;">';
        echo '<h1>Unerwarteter Fehler</h1>';
        echo '<p>Bitte später erneut versuchen oder den Administrator kontaktieren.</p>';
        echo '</body></html>';
    }
});

// Einfacher CSRF-Schutz
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Ungültiges Formular-Token (CSRF). Bitte Seite neu laden und erneut versuchen.');
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Hub UI asset (V2.0a). Prefer local copies under public/assets/.
 * Paths: css/hub-confirm.css or shared/cf-status.js (no leading slash).
 */
function sf_hub_asset(string $hubPath, ?string $localPath = null): string
{
    $hubPath = ltrim(str_replace('\\', '/', $hubPath), '/');
    if ($localPath === null || $localPath === '') {
        if (str_starts_with($hubPath, 'shared/')) {
            $localPath = 'assets/js/' . substr($hubPath, 7);
        } else {
            $localPath = 'assets/' . $hubPath;
        }
    }
    $localPath = ltrim(str_replace('\\', '/', $localPath), '/');
    $file = BASE_PATH . '/public/' . $localPath;
    if (is_file($file)) {
        return $localPath . '?v=' . rawurlencode((string)ASSET_VERSION);
    }
    if (function_exists('hub_asset')) {
        return hub_asset($hubPath);
    }
    return $localPath . '?v=' . rawurlencode((string)ASSET_VERSION);
}

/**
 * Basispfad im Browser. Im Web-Share des Hubs bildet NPM /slideforge/ auf /slideforge/public/ ab;
 * SCRIPT_NAME nennt den inneren Pfad, der Browser braucht den aeusseren.
 */
function sf_base(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($script, '/slideforge/public/')) {
        return '/slideforge';
    }
    return rtrim(str_replace('\\', '/', dirname($script)), '/');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

/**
 * Verlängert das aktuelle Session-Cookie auf $days Tage (statt bis zum Schliessen des
 * Browsers) - für die "Angemeldet bleiben"-Option beim Login. Sendet dasselbe
 * Session-Cookie einfach mit neuer Ablaufzeit erneut, keine separate Token-Tabelle nötig.
 */
function extend_session_cookie(int $days = 30): void {
    $seconds = $days * 24 * 60 * 60;
    @ini_set('session.gc_maxlifetime', (string)$seconds); // serverseitige Sitzungsdatei soll nicht vorzeitig aufgeräumt werden
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + $seconds,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

/**
 * Ermittelt http/https zuverlässig auch hinter einem Reverse Proxy
 * (z.B. Nginx Proxy Manager), der intern nur HTTP mit dem Container spricht
 * und das ursprüngliche Schema über den X-Forwarded-Proto-Header meldet.
 */
function current_scheme(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($forwarded !== '') {
        // Header kann kommagetrennt mehrere Werte enthalten (Proxy-Kette) - ersten nehmen
        $first = trim(explode(',', $forwarded)[0]);
        if ($first === 'https' || $first === 'http') {
            return $first;
        }
    }
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
}

/** Tablet-Erkennung für Touch-Remote (Galaxy Tab, iPad, …). */
function client_is_touch_tablet(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') {
        return false;
    }
    if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
        return true;
    }
    // Samsung Galaxy Tab (SM-X710 = Tab S9, …) — meldet oft trotzdem „Mobile“ im UA
    if (preg_match('/\bSM-X\d{3}\b/i', $ua)) {
        return true;
    }
    if (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua)) {
        return true;
    }
    return false;
}

/** Prüft ob eine IPv4 vom WLAN/LAN aus erreichbar ist (kein Docker-Bridge-172.x). */
function is_wlan_reachable_ipv4(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
        return false;
    }
    // Docker-/Container-Bridges (172.16.0.0–172.31.255.255) sind am Tablet nicht erreichbar
    if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)) {
        return false;
    }
    return preg_match('/^(192\.168\.|10\.)/', $ip) === 1;
}

/** Hostname für URLs, die vom LAN erreichbar sein müssen (QR, Remote-Link). */
function http_request_host(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostOnly = strtolower(preg_replace('/:\d+$/', '', $host));
    if (!in_array($hostOnly, ['localhost', '127.0.0.1', '[::1]'], true)) {
        return $host;
    }
    $lan = detect_lan_ipv4();
    if (!$lan) {
        return $host;
    }
    if (preg_match('/:(\d+)$/', $host, $m)) {
        return $lan . ':' . $m[1];
    }
    return $lan;
}

/** LAN-IPv4 für localhost-Zugriffe (Docker: env SLIDEFORGE_PRESENT_HOST auf dem Host setzen). */
function detect_lan_ipv4(): ?string
{
    $env = getenv('SLIDEFORGE_PRESENT_HOST');
    if (is_string($env) && trim($env) !== '') {
        $ip = preg_replace('/:\d+$/', '', trim($env));
        return is_wlan_reachable_ipv4($ip) ? $ip : null;
    }
    $cfg = Config::presentReachableHost();
    if ($cfg !== '') {
        $ip = preg_replace('/:\d+$/', '', $cfg);
        return is_wlan_reachable_ipv4($ip) ? $ip : null;
    }
    if (!function_exists('shell_exec')) {
        return null;
    }
    $fromIfaces = detect_lan_ipv4_from_interfaces();
    if ($fromIfaces !== null) {
        return $fromIfaces;
    }
    $src = @shell_exec('ip -4 route get 1.1.1.1 2>/dev/null');
    if (is_string($src) && preg_match('/\bsrc\s+(\d+\.\d+\.\d+\.\d+)/', $src, $m)) {
        $ip = $m[1];
        if (is_wlan_reachable_ipv4($ip)) {
            return $ip;
        }
    }
    return null;
}

/** Sucht 192.168.x.x / 10.x.x.x auf lokalen Interfaces (funktioniert nur ausserhalb Docker). */
function detect_lan_ipv4_from_interfaces(): ?string
{
    $out = @shell_exec('ip -4 -o addr show scope global 2>/dev/null');
    if (!is_string($out) || $out === '') {
        return null;
    }
    $preferred = null;
    $fallback = null;
    foreach (preg_split('/\r?\n/', trim($out)) as $line) {
        if (!preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
            continue;
        }
        $ip = $m[1];
        if (!is_wlan_reachable_ipv4($ip)) {
            continue;
        }
        if (str_starts_with($ip, '192.168.')) {
            $preferred = $ip;
            break;
        }
        if ($fallback === null) {
            $fallback = $ip;
        }
    }
    return $preferred ?? $fallback;
}

$__lang = 'de';
if (!empty($_SESSION['user_id'])) {
    $__u = Auth::findById($_SESSION['user_id']);
    if ($__u && !empty($__u['language'])) {
        $__lang = $__u['language'];
    }
} elseif (!empty($_COOKIE['sf_lang'])) {
    $__lang = $_COOKIE['sf_lang'];
}

I18n::init($__lang);

if (Demo::isActive()) {
    Demo::maybeReset();
}

unset($__lang, $__u);

/**
 * Hub-Design schon serverseitig im <html> (1:1 wie FileForge ff_theme_attrs, 27.11.2026, Urs: «Design wird immer noch nicht recht
 * vom Hub übernommen»). Quelle: `hub_accent`/`hub_chrome` der Rahmenadresse (Hub app/embed.php), sonst der in der Sitzung gemerkte
 * Wert (`sf_look_capture`). Hell/Dunkel kommt wie bisher aus SharedAuth::themePref (Cookie des Hubs).
 */
function sf_theme_attrs(string $pref, string $theme): string
{
    $out = ' data-theme-pref="' . htmlspecialchars($pref, ENT_QUOTES) . '" data-theme="' . htmlspecialchars($theme, ENT_QUOTES) . '"';
    $look = sf_look_url() ?? (is_array($_SESSION['sf_hub_look'] ?? null) ? $_SESSION['sf_hub_look'] : null);
    if (!is_array($look)) {
        return $out;
    }
    $a = (string)($look['accent'] ?? '');
    if (($look['chrome'] ?? '') === 'flach') {
        $out .= ' data-dialog-chrome="flach"';
    }
    if (preg_match('/^#[0-9a-f]{6}\z/', $a) === 1) {
        [$hue, $chroma] = sf_accent_tone($a);
        $out .= ' data-dialog-accent="custom" style="--hub-base-hue: ' . $hue . '; --hub-chroma: ' . $chroma . '; --hub-dialog-accent: ' . $a . ';"';
    } elseif ($a !== '') {
        $out .= ' data-dialog-accent="' . $a . '"';
    }
    return $out;
}

/** @return array{chrome:string,accent:string}|null Fenster-Stil und Akzent aus der Rahmenadresse des Hubs, nur Listenwerte bzw. `#rrggbb`. */
function sf_look_url(): ?array
{
    if (!isset($_GET['hub_chrome']) && !isset($_GET['hub_accent'])) {
        return null;
    }
    $a = strtolower(trim((string)($_GET['hub_accent'] ?? '')));
    if (!in_array($a, ['', 'blau', 'gruen', 'indigo', 'terracotta'], true) && preg_match('/^#[0-9a-f]{6}\z/', $a) !== 1) {
        $a = '';
    }
    return ['chrome' => (string)($_GET['hub_chrome'] ?? '') === 'flach' ? 'flach' : 'klassisch', 'accent' => $a];
}

/** Merkt den Wert der Rahmenadresse in der Sitzung, damit Seitenwechsel ohne `hub_accent` (Editor, Einstellungen) ihn im ersten Bild haben. */
function sf_look_capture(): void
{
    $look = sf_look_url();
    if ($look !== null && session_status() === PHP_SESSION_ACTIVE && ($_SESSION['sf_hub_look'] ?? null) !== $look) {
        $_SESSION['sf_hub_look'] = $look;
    }
}

/** @return array{0:float,1:float} Farbton und Sättigungsfaktor (OKLCH) wie UserPrefs::accentTone() im Hub und cf-theme.js. */
function sf_accent_tone(string $hex): array
{
    $n = hexdec(substr($hex, 1, 6));
    $c = [];
    foreach ([($n >> 16) & 255, ($n >> 8) & 255, $n & 255] as $v) {
        $v /= 255;
        $c[] = $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }
    $l = (0.4122214708 * $c[0] + 0.5363325363 * $c[1] + 0.0514459929 * $c[2]) ** (1 / 3);
    $m = (0.2119034982 * $c[0] + 0.6806995451 * $c[1] + 0.1073969566 * $c[2]) ** (1 / 3);
    $s = (0.0883024619 * $c[0] + 0.2817188376 * $c[1] + 0.6299787005 * $c[2]) ** (1 / 3);
    $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
    $b = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
    $h = rad2deg(atan2($b, $a));
    return [round($h < 0 ? $h + 360 : $h, 1), round(min(1.0, sqrt($a * $a + $b * $b) / 0.08), 3)];
}

/**
 * Frühstart des Hub-Designs (wie FileForge ff_theme_boot, 27.11.2026): setzt data-dialog-accent/--hub-base-hue im <head>,
 * bevor CSS greift — aus hub_accent/hub_chrome der Rahmenadresse bzw. sessionStorage; danach übernimmt shared/cf-theme.js.
 */
function sf_theme_boot(): string
{
    return <<<'HTML'
  <script>(function () {
    try {
      if (window.parent === window) return;
      var K = 'sf_dialog_chrome', A = ['', 'blau', 'gruen', 'indigo', 'terracotta'], C = ['klassisch', 'flach'];
      // Freie Akzentfarbe `#rrggbb` (Hub 2.2.4, Markenfarben): wie cf-theme.js zulassen, sonst fiel jede Seite ohne
      // hub_accent in der Adresse bis zur Nachricht des Rahmens auf Sand zurück (Urs 10.11.).
      var okA = function (x) { x = String(x); return A.indexOf(x) >= 0 || /^#[0-9a-f]{6}$/.test(x); };
      // Farbton/Sättigung einer freien Farbe: dieselbe Rechnung wie accentTone() in cf-theme.js (OKLCH).
      var tone = function (hex) {
        var n = parseInt(hex.slice(1), 16), c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (x) { x /= 255; return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); });
        var l = Math.cbrt(0.4122214708 * c[0] + 0.5363325363 * c[1] + 0.0514459929 * c[2]);
        var m = Math.cbrt(0.2119034982 * c[0] + 0.6806995451 * c[1] + 0.1073969566 * c[2]);
        var s = Math.cbrt(0.0883024619 * c[0] + 0.2817188376 * c[1] + 0.6299787005 * c[2]);
        var a = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s, b = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s;
        var h = Math.atan2(b, a) * 180 / Math.PI;
        return [Math.round((h < 0 ? h + 360 : h) * 10) / 10, Math.round(Math.min(1, Math.sqrt(a * a + b * b) / 0.08) * 1000) / 1000];
      };
      var ok = function (v) {
        return !!v && typeof v === 'object' && !!v.chrome && !!v.accent && C.indexOf(String(v.chrome.light)) >= 0 && C.indexOf(String(v.chrome.dark)) >= 0
          && okA(v.accent.light) && okA(v.accent.dark);
      };
      var keep = function (v) {
        if (!ok(v)) return;
        try { sessionStorage.setItem(K, JSON.stringify({ chrome: { light: String(v.chrome.light), dark: String(v.chrome.dark) }, accent: { light: String(v.accent.light), dark: String(v.accent.dark) } })); } catch (e) {}
      };
      var q = new URLSearchParams(location.search), a = q.get('hub_accent'), c = q.get('hub_chrome'), v = null;
      if (a !== null || c !== null) {
        v = { chrome: { light: c || 'klassisch', dark: c || 'klassisch' }, accent: { light: (a || '').toLowerCase(), dark: (a || '').toLowerCase() } };
        keep(v);
      } else {
        v = JSON.parse(sessionStorage.getItem(K) || 'null');
      }
      // Ohne Adresse und ohne Merkwert (z. B. aus FileForge «Öffnen mit» geöffnet): Farbe vom Hub-Fenster übernehmen
      // (gleiche Herkunft hub.<domain>, also lesbar). Urs 27.11.: «dass das Design vom Hub kommt, also von der Einstellung des Users».
      if (!ok(v)) {
        try {
          var tr = window.top.document.documentElement, ta = tr.getAttribute('data-dialog-accent'), r0 = document.documentElement;
          if (ta) {
            r0.setAttribute('data-dialog-accent', ta);
            ['--hub-base-hue', '--hub-chroma', '--hub-dialog-accent'].forEach(function (k) { var x = tr.style.getPropertyValue(k); if (x) r0.style.setProperty(k, x); });
          }
          if (tr.getAttribute('data-dialog-chrome') === 'flach') r0.setAttribute('data-dialog-chrome', 'flach');
        } catch (e2) {}
      }
      if (ok(v)) {
        var r = document.documentElement, m = (document.cookie.match(/(?:^|;\s*)cf_appearance=([a-z]+)/) || [])[1] || 'dark';
        var t = m === 'system' ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : (m === 'light' ? 'light' : 'dark');
        if (/^#/.test(v.accent[t])) {
          var h = tone(v.accent[t]);
          r.setAttribute('data-dialog-accent', 'custom');
          r.style.setProperty('--hub-base-hue', String(h[0]));
          r.style.setProperty('--hub-chroma', String(h[1]));
          r.style.setProperty('--hub-dialog-accent', v.accent[t]);
        } else {
          ['--hub-base-hue', '--hub-chroma', '--hub-dialog-accent'].forEach(function (k) { r.style.removeProperty(k); });
          if (v.accent[t]) r.setAttribute('data-dialog-accent', v.accent[t]); else r.removeAttribute('data-dialog-accent');
        }
        if (v.chrome[t] === 'flach') r.setAttribute('data-dialog-chrome', 'flach'); else r.removeAttribute('data-dialog-chrome');
        window.__cfDialogChromePrefs = v;
      }
      window.addEventListener('message', function () {
        setTimeout(function () { keep(window.__cfDialogChromePrefs); }, 0); // erst nach der Prüfung durch cf-theme.js
      });
    } catch (e) {}
  })();</script>

HTML;
}
