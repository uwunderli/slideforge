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
    echo json_encode(['ok' => false, 'error' => t('openclipart.error_auth')]);
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
    if (!OpenclipartClient::enabled()) {
        http_response_code(503);
        exit;
    }
    $clipartId = trim((string)($_GET['clipartId'] ?? ''));
    $color = trim((string)($_GET['color'] ?? ''));
    $svg = OpenclipartClient::previewSvg($clipartId, $color, 256);
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

function clipart_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function clipart_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

$id = $_GET['id'] ?? $body['id'] ?? '';

if (!in_array($action, ['search', 'import'], true)) {
    clipart_json_fail(t('openclipart.error_unknown_action'), 400);
}

if (!Presentation::exists($id)) {
    clipart_json_fail(t('openclipart.error_not_found'), 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    clipart_json_fail(t('openclipart.error_forbidden'), 403);
}

if (!OpenclipartClient::enabled()) {
    clipart_json_fail(t('openclipart.error_disabled'), 503);
}

if ($action === 'search') {
    $result = OpenclipartClient::search([
        'q' => (string)($body['q'] ?? ''),
        'page' => (int)($body['page'] ?? 1),
    ]);
    if (!$result['ok']) {
        $err = $result['error'] ?? 'unknown';
        if ($err === 'empty_query') {
            clipart_json_fail(t('openclipart.enter_query'), 400);
        }
        if ($err === 'request_failed' || $err === 'parse_failed') {
            clipart_json_fail(t('openclipart.error_api'), 502);
        }
        clipart_json_fail(t('openclipart.error_generic'), 502);
    }
    clipart_json_ok([
        'hits' => $result['hits'] ?? [],
        'total' => $result['total'] ?? count($result['hits'] ?? []),
        'totalPages' => $result['totalPages'] ?? 0,
        'cached' => !empty($result['cached']),
    ]);
}

if (!in_array($perm, ['owner', 'edit'], true)) {
    clipart_json_fail(t('openclipart.error_edit'), 403);
}
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    clipart_json_fail(t('openclipart.error_csrf'), 403);
}

$clipartId = trim((string)($body['clipartId'] ?? ''));

$result = OpenclipartClient::importToPresentation($id, $clipartId);
if (!$result['ok']) {
    $map = [
        'invalid_clipart' => t('openclipart.error_invalid_clipart'),
        'invalid_url' => t('openclipart.error_invalid_url'),
        'download_failed' => t('openclipart.error_download'),
        'file_too_large' => t('openclipart.error_too_large'),
        'unsupported_type' => t('openclipart.error_type'),
        'save_failed' => t('openclipart.error_save'),
    ];
    clipart_json_fail($map[$result['error'] ?? ''] ?? t('openclipart.error_generic'), 400);
}

clipart_json_ok([
    'url' => $result['url'],
    'filename' => $result['filename'],
    'kind' => $result['kind'],
    'clipartId' => $result['clipartId'] ?? $clipartId,
]);
