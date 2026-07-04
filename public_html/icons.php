<?php
require __DIR__ . '/../config.php';
ini_set('display_errors', '0');

if (!Auth::isLoggedIn()) {
    if (($_GET['action'] ?? '') === 'preview') {
        http_response_code(401);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => t('iconify.error_auth')]);
    exit;
}
$me = Auth::currentUser();

$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';

if ($action === 'preview') {
    if (!IconifyClient::enabled()) {
        http_response_code(503);
        exit;
    }
    $iconId = trim((string)($_GET['iconId'] ?? ''));
    $color = trim((string)($_GET['color'] ?? ''));
    $height = (int)($_GET['height'] ?? 128);
    $svg = IconifyClient::previewSvg($iconId, $color, $height);
    if ($svg === null) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    echo $svg;
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function icons_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function icons_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

$id = $_GET['id'] ?? $body['id'] ?? '';

if (!in_array($action, ['search', 'import'], true)) {
    icons_json_fail(t('iconify.error_unknown_action'), 400);
}

if (!Presentation::exists($id)) {
    icons_json_fail(t('iconify.error_not_found'), 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    icons_json_fail(t('iconify.error_forbidden'), 403);
}

if (!IconifyClient::enabled()) {
    icons_json_fail(t('iconify.error_disabled'), 503);
}

if ($action === 'search') {
    $result = IconifyClient::search([
        'q' => (string)($body['q'] ?? ''),
        'page' => (int)($body['page'] ?? 1),
        'per_page' => (int)($body['per_page'] ?? 24),
        'prefix' => (string)($body['prefix'] ?? ''),
    ]);
    if (!$result['ok']) {
        $err = $result['error'] ?? 'unknown';
        if ($err === 'empty_query') {
            icons_json_fail(t('iconify.enter_query'), 400);
        }
        if ($err === 'request_failed') {
            icons_json_fail(t('iconify.error_api'), 502);
        }
        icons_json_fail(t('iconify.error_generic'), 502);
    }
    icons_json_ok([
        'hits' => $result['hits'] ?? [],
        'total' => $result['total'] ?? count($result['hits'] ?? []),
        'cached' => !empty($result['cached']),
    ]);
}

if (!in_array($perm, ['owner', 'edit'], true)) {
    icons_json_fail(t('iconify.error_edit'), 403);
}
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    icons_json_fail(t('iconify.error_csrf'), 403);
}

$iconId = trim((string)($body['iconId'] ?? ''));

$result = IconifyClient::importToPresentation($id, $iconId);
if (!$result['ok']) {
    $map = [
        'invalid_icon' => t('iconify.error_invalid_icon'),
        'invalid_url' => t('iconify.error_invalid_url'),
        'download_failed' => t('iconify.error_download'),
        'file_too_large' => t('iconify.error_too_large'),
        'unsupported_type' => t('iconify.error_type'),
        'save_failed' => t('iconify.error_save'),
    ];
    icons_json_fail($map[$result['error'] ?? ''] ?? t('iconify.error_generic'), 400);
}

icons_json_ok([
    'url' => $result['url'],
    'filename' => $result['filename'],
    'kind' => $result['kind'],
    'iconId' => $result['iconId'] ?? $iconId,
]);
