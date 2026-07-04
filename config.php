<?php
/**
 * Zentrale Konfiguration.
 * Diese Datei liegt bewusst AUSSERHALB von public_html und ist damit
 * vom Web aus nicht erreichbar (sofern nginx root = public_html).
 */

// Basisverzeichnisse
define('BASE_PATH', __DIR__);
define('DATA_PATH', BASE_PATH . '/data');
define('PRESENTATIONS_PATH', DATA_PATH . '/presentations');
define('USERS_FILE', DATA_PATH . '/users.json');
define('LANG_PATH', __DIR__ . '/lang');
define('SITE_CONFIG_FILE', DATA_PATH . '/config.json');
define('INVITES_FILE', DATA_PATH . '/invites.json');
define('TEXT_TEMPLATES_FILE', DATA_PATH . '/text_templates.json');
define('EXPORT_CACHE_PATH', DATA_PATH . '/cache');
// Logo liegt bewusst im Web-Root (öffentlich sichtbar, auch auf der Login-Seite)
define('PUBLIC_UPLOADS_PATH', __DIR__ . '/public_html/uploads');
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
define('ASSET_VERSION', '240');

// Fehleranzeige während der Entwicklung: Deprecated-Hinweise (z.B. durch neuere
// PHP-Versionen) landen nur noch im Server-Log, echte Fehler/Warnungen bleiben
// sichtbar. Für den Produktivbetrieb display_errors ganz auf 0 setzen.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Session starten (muss vor jedem Output passieren)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloading der eigenen Klassen in src/
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

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
        'registration_enabled' => true,
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
if (!file_exists(INVITES_FILE)) {
    file_put_contents(INVITES_FILE, json_encode([], JSON_PRETTY_PRINT));
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
if (!is_dir(PUBLIC_UPLOADS_PATH . '/avatars')) {
    mkdir(PUBLIC_UPLOADS_PATH . '/avatars', 0770, true);
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

// Migration: bestehende User gelten als E-Mail-bestätigt (Feature kam später dazu).
$__existingUsers = Storage::read(USERS_FILE, []);
$__emailVerifyMigration = false;
foreach ($__existingUsers as $__u) {
    if (!array_key_exists('email_verified', $__u)) {
        $__emailVerifyMigration = true;
        break;
    }
}
if ($__emailVerifyMigration) {
    Storage::update(USERS_FILE, function ($users) {
        foreach ($users as &$u) {
            if (!array_key_exists('email_verified', $u)) {
                $u['email_verified'] = true;
            }
        }
        return $users;
    }, []);
}
unset($__existingUsers, $__emailVerifyMigration, $__u);

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

// Verständliche Fehlermeldung statt rohem PHP-Fatal-Error bei Schreibproblemen
set_exception_handler(function (Throwable $e) {
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

// Sprache bestimmen: angemeldeter User > Cookie (z.B. Login-Seite vor Anmeldung) > Deutsch.
$__lang = 'de';
if (!empty($_SESSION['user_id'])) {
    $__u = Auth::findById($_SESSION['user_id']);
    if ($__u && !empty($__u['language'])) {
        $__lang = $__u['language'];
    }
} elseif (!empty($_COOKIE['sf_lang'])) {
    $__lang = $_COOKIE['sf_lang'];
}

if (Demo::isActive()) {
    Demo::maybeReset();
}

I18n::init($__lang);
unset($__lang, $__u);
