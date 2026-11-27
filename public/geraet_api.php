<?php
declare(strict_types=1);

/**
 * **Auf Gerät zeigen** (Urs, 27.11.2026; `Geraete`, Hub `api/anzeige.php`).
 *   GET  ?action=liste                         → {ok, geraete:[{id,name,icon,symbol,darf_aendern,…,zeigt}], icons:[…], anmelden:<Hub-Adresse Geräte>}
 *   POST {action:umbenennen, geraet, name} · {action:icon, geraet, icon} → {ok}   (3.9.9, Hub ≥ 2.34.3; Recht prüft der Hub)
 *   POST {action:zeigen, geraet, id, mit?}     → {ok}   Präsentation (Arbeitskopie `id`) auf dem Gerät zeigen; mit=1: folgt dem Editor (3.8.0)
 *   POST {action:leeren, geraet}               → {ok}
 *   POST {action:neuladen, geraet}             → {ok}   Gerät lädt die Seite neu (3.6.6, Hub ≥ 2.33.11)
 *   POST {action:koppeln, code, name, dauer}   → {ok, geraet}   Gerät mit dem Code von device.php koppeln (3.4.0, Hub ≥ 2.33.8)
 * Nur angemeldet; zeigen nur, wer die Präsentation sehen darf. Wer das Gerät bespielen darf, prüft der Hub.
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$out = static function (array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if (!Auth::isLoggedIn() || ($me = Auth::currentUser()) === null) {
    $out(['ok' => false, 'error' => 'login'], 401);
}
$person = (string)$me['id'];
$messages = [
    'recht' => 'Dieses Gerät darfst du nicht ändern oder bespielen.',
    'geraet' => 'Das Gerät ist nicht mehr angemeldet.',
    'no_app_call' => 'Der Hub ist nicht erreichbar.',
    'unreachable' => 'Der Hub ist nicht erreichbar.',
    'code' => 'Der Code stimmt nicht oder ist abgelaufen.',
    'zu_viele' => 'Zu viele falsche Codes. Bitte in einigen Minuten nochmals.',
    'eingabe' => 'Angaben unvollständig.',
    'unauthorized' => 'Der Hub hat den Aufruf abgelehnt.',
    'unknown_op' => 'Der Hub kennt diese Funktion noch nicht (Hub-Update nötig).',
];
$fail = static function (string $e) use ($out, $messages): never {
    $out(['ok' => false, 'error' => $e, 'message' => $messages[$e] ?? 'Das ging nicht (' . $e . ').']);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $r = Geraete::liste($person);
    if (!$r['ok']) {
        $fail($r['error'] !== '' ? $r['error'] : 'unreachable');
    }
    $out(['ok' => true, 'geraete' => $r['geraete'], 'icons' => $r['icons'], 'anmelden' => '/admin/settings.php?tab=geraete']);
}

$body = json_decode((string)file_get_contents('php://input', false, null, 0, 8192), true);
$body = is_array($body) ? $body : [];
$token = (string)($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
    $out(['ok' => false, 'error' => 'csrf'], 403);
}
$action = (string)($body['action'] ?? '');
if ($action === 'koppeln') {
    $code = preg_replace('/\D/', '', (string)($body['code'] ?? '')) ?? '';
    $dauer = (string)($body['dauer'] ?? 'heute');
    if (strlen($code) !== 6) {
        $fail('code');
    }
    if (!in_array($dauer, ['heute', 'dauerhaft'], true)) {
        $fail('eingabe');
    }
    $r = Geraete::koppeln($person, $code, mb_substr(trim((string)($body['name'] ?? '')), 0, 60), $dauer);
    $r['ok'] ? $out(['ok' => true, 'geraet' => $r['geraet']]) : $fail($r['error'] !== '' ? $r['error'] : 'unreachable');
}
$geraet = (string)($body['geraet'] ?? '');
if (preg_match('/^g[0-9a-f]{12}\z/', $geraet) !== 1) {
    $fail('geraet');
}
if ($action === 'zeigen') {
    $id = (string)($body['id'] ?? '');
    if (!Presentation::exists($id) || !Presentation::canView($id, $person)) {
        $out(['ok' => false, 'error' => 'no_access', 'message' => 'Kein Zugriff auf diese Präsentation.'], 403);
    }
    $meta = Presentation::getMeta($id);
    $mit = !empty($body['mit']);
    $titel = (string)($meta['title'] ?? '');
    $r = Geraete::zeigen($person, $geraet, $id, $mit ? $titel . ' (Mitlaufen)' : $titel, $mit);
    $r['ok'] ? $out(['ok' => true]) : $fail($r['error']);
}
if ($action === 'umbenennen' || $action === 'icon') {
    $wert = $action === 'icon' ? (string)($body['icon'] ?? '') : trim((string)($body['name'] ?? ''));
    if ($wert === '' || ($action === 'icon' && preg_match('/^[a-z]{1,24}\z/', $wert) !== 1)) {
        $fail('eingabe');
    }
    $r = Geraete::aendern($person, $geraet, $action === 'icon' ? 'icon' : 'name', mb_substr($wert, 0, 60));
    $r['ok'] ? $out(['ok' => true]) : $fail($r['error'] !== '' ? $r['error'] : 'unreachable');
}
if ($action === 'neuladen') {
    $r = Geraete::neuladen($person, $geraet);
    $r['ok'] ? $out(['ok' => true]) : $fail($r['error']);
}
if ($action === 'leeren') {
    $r = Geraete::zeigen($person, $geraet, '', '');
    $r['ok'] ? $out(['ok' => true]) : $fail($r['error']);
}
$out(['ok' => false, 'error' => 'bad_request'], 400);
