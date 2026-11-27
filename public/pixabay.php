<?php
require __DIR__ . '/../config.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function pixabay_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function pixabay_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

if (!Auth::isLoggedIn()) {
    pixabay_json_fail(t('pixabay.error_auth'), 401);
}
$me = Auth::currentUser();

$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$id = $_GET['id'] ?? $body['id'] ?? '';

if (!in_array($action, ['search', 'import'], true)) {
    pixabay_json_fail(t('pixabay.error_unknown_action'), 400);
}

if (!Presentation::exists($id)) {
    pixabay_json_fail(t('pixabay.error_not_found'), 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    pixabay_json_fail(t('pixabay.error_forbidden'), 403);
}

if (!PixabayClient::enabled()) {
    pixabay_json_fail(t('pixabay.error_disabled'), 503);
}

if ($action === 'search') {
    $media = ($body['media'] ?? 'image') === 'video' ? 'video' : 'image';
    $lang = trim((string)($body['lang'] ?? ''));
    if ($lang === '') {
        $lang = substr((string)($me['language'] ?? 'de'), 0, 2);
    }
    $result = PixabayClient::search($media, [
        'q' => (string)($body['q'] ?? ''),
        'page' => (int)($body['page'] ?? 1),
        'per_page' => (int)($body['per_page'] ?? 20),
        'lang' => $lang,
        'image_type' => (string)($body['image_type'] ?? 'all'),
        'orientation' => (string)($body['orientation'] ?? 'all'),
        'video_type' => (string)($body['video_type'] ?? 'all'),
    ]);
    if (!$result['ok']) {
        $err = $result['error'] ?? 'unknown';
        if ($err === 'request_failed') {
            pixabay_json_fail(t('pixabay.error_api'), 502);
        }
        pixabay_json_fail(t('pixabay.error_generic'), 502);
    }
    pixabay_json_ok([
        'hits' => $result['hits'] ?? [],
        'total' => $result['total'] ?? count($result['hits'] ?? []),
        'cached' => !empty($result['cached']),
    ]);
}

if (!in_array($perm, ['owner', 'edit'], true)) {
    pixabay_json_fail(t('pixabay.error_edit'), 403);
}
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    pixabay_json_fail(t('pixabay.error_csrf'), 403);
}

$media = ($body['media'] ?? 'image') === 'video' ? 'video' : 'image';
$downloadUrl = trim((string)($body['downloadURL'] ?? ''));
$pixabayId = (int)($body['pixabayId'] ?? 0);

$result = PixabayClient::importToPresentation($id, $media, $downloadUrl, $pixabayId);
if (!$result['ok']) {
    $map = [
        'invalid_url' => t('pixabay.error_invalid_url'),
        'download_failed' => t('pixabay.error_download'),
        'file_too_large' => t('pixabay.error_too_large'),
        'unsupported_type' => t('pixabay.error_type'),
        'save_failed' => t('pixabay.error_save'),
    ];
    pixabay_json_fail($map[$result['error'] ?? ''] ?? t('pixabay.error_generic'), 400);
}

pixabay_json_ok([
    'url' => $result['url'],
    'filename' => $result['filename'],
    'kind' => $result['kind'],
]);
