<?php
require __DIR__ . '/../config.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function user_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function user_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

if (!Auth::isLoggedIn()) {
    user_json_fail('Nicht angemeldet.', 401);
}
$me = Auth::currentUser();

$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';

switch ($action) {
    case 'set_present_panels':
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            user_json_fail('Ungültiges CSRF-Token.', 403);
        }
        $panels = Auth::setPresentPanels($me['id'], $body['panels'] ?? []);
        user_json_ok(['panels' => $panels]);
        break;

    case 'set_present_layout':
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            user_json_fail('Ungültiges CSRF-Token.', 403);
        }
        $layout = Auth::setPresentLayout($me['id'], $body['layout'] ?? []);
        user_json_ok(['layout' => $layout]);
        break;

    default:
        user_json_fail('Unbekannte Aktion.', 400);
}
