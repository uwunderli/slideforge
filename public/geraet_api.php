<?php
declare(strict_types=1);

/**
 * **Auf Gerät zeigen** (Urs, 27.11.2026; `Geraete`, Hub `api/anzeige.php`).
 *   GET  ?action=liste                         → {ok, geraete:[{id,name,dauer,ablauf,freigabe,zeigt}], anmelden:<Hub-Adresse Geräte>}
 *   POST {action:zeigen, geraet, id}           → {ok}   Präsentation (Arbeitskopie `id`) auf dem Gerät zeigen
 *   POST {action:leeren, geraet}               → {ok}
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
    'recht' => 'Dieses Gerät darfst du nicht bespielen.',
    'geraet' => 'Das Gerät ist nicht mehr angemeldet.',
    'no_app_call' => 'Der Hub ist nicht erreichbar.',
    'unreachable' => 'Der Hub ist nicht erreichbar.',
];
$fail = static function (string $e) use ($out, $messages): never {
    $out(['ok' => false, 'error' => $e, 'message' => $messages[$e] ?? 'Das ging nicht (' . $e . ').']);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $r = Geraete::liste($person);
    if (!$r['ok']) {
        $fail($r['error'] !== '' ? $r['error'] : 'unreachable');
    }
    $out(['ok' => true, 'geraete' => $r['geraete'], 'anmelden' => '/admin/settings.php?tab=geraete']);
}

$body = json_decode((string)file_get_contents('php://input', false, null, 0, 8192), true);
$body = is_array($body) ? $body : [];
$token = (string)($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
    $out(['ok' => false, 'error' => 'csrf'], 403);
}
$geraet = (string)($body['geraet'] ?? '');
if (preg_match('/^g[0-9a-f]{12}\z/', $geraet) !== 1) {
    $fail('geraet');
}
$action = (string)($body['action'] ?? '');
if ($action === 'zeigen') {
    $id = (string)($body['id'] ?? '');
    if (!Presentation::exists($id) || !Presentation::canView($id, $person)) {
        $out(['ok' => false, 'error' => 'no_access', 'message' => 'Kein Zugriff auf diese Präsentation.'], 403);
    }
    $meta = Presentation::getMeta($id);
    $r = Geraete::zeigen($person, $geraet, $id, (string)($meta['title'] ?? ''));
    $r['ok'] ? $out(['ok' => true]) : $fail($r['error']);
}
if ($action === 'leeren') {
    $r = Geraete::zeigen($person, $geraet, '', '');
    $r['ok'] ? $out(['ok' => true]) : $fail($r['error']);
}
$out(['ok' => false, 'error' => 'bad_request'], 400);
