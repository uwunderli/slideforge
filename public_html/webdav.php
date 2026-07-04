<?php
require __DIR__ . '/../config.php';
ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e) {
    error_log('SlideForge webdav.php: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => t('webdav.error_generic')]);
    exit;
});

if (!Auth::isLoggedIn()) {
    if (in_array(($_GET['action'] ?? ''), ['preview', 'stream'], true)) {
        http_response_code(401);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => t('webdav.error_auth')]);
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
    $driveId = trim((string)($_GET['drive_id'] ?? ''));
    $path = WebdavClient::normalizeBrowsePath((string)($_GET['path'] ?? ''));
    $drive = Auth::getWebdavDriveCredentials($me['id'], $driveId);
    if (!$drive || $path === '') {
        http_response_code(404);
        exit;
    }
    $preview = WebdavClient::getPreview($me['id'], $driveId, $drive, $path);
    if ($preview === null) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $preview['mime']);
    header('Cache-Control: private, max-age=' . (7 * 86400));
    if (!empty($preview['cached'])) {
        header('X-SF-Preview-Cache: hit');
    }
    echo $preview['bytes'];
    exit;
}

if ($action === 'stream') {
    $driveId = trim((string)($_GET['drive_id'] ?? ''));
    $path = WebdavClient::normalizeBrowsePath((string)($_GET['path'] ?? ''));
    $drive = Auth::getWebdavDriveCredentials($me['id'], $driveId);
    if (!$drive || $path === '') {
        http_response_code(404);
        exit;
    }
    $name = basename(str_replace('\\', '/', $path));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $kind = WebdavClient::detectMediaKind($ext, '');
    if ($kind === null || $kind === 'image') {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . WebdavClient::mimeForExtension($ext));
    header('Cache-Control: private, no-store');
    $result = WebdavClient::streamMedia($drive, $path);
    if (!$result['ok']) {
        if (!headers_sent()) {
            http_response_code($result['error'] === 'file_too_large' ? 413 : 502);
        }
        exit;
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function webdav_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function webdav_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

$driveId = trim((string)($body['drive_id'] ?? $_GET['drive_id'] ?? ''));
$drive = Auth::getWebdavDriveCredentials($me['id'], $driveId);
if (!$drive) {
    webdav_json_fail(t('webdav.error_drive_not_found'), 404);
}

if ($action === 'browse') {
    $path = WebdavClient::normalizeBrowsePath((string)($body['path'] ?? ''));
    $result = WebdavClient::listDirectory($drive, $path);
    if (!$result['ok']) {
        $map = [
            'invalid_path' => t('webdav.error_invalid_path'),
            'not_found' => t('webdav.error_not_found'),
            'auth_failed' => t('webdav.error_auth_failed'),
            'https_required' => t('webdav.error_https_required'),
            'blocked_host' => t('webdav.error_blocked_host'),
            'invalid_url' => t('webdav.error_invalid_url'),
            'parse_failed' => t('webdav.error_generic'),
            'request_failed' => t('webdav.error_connection'),
        ];
        webdav_json_fail($map[$result['error'] ?? ''] ?? t('webdav.error_generic'), 502);
    }
    webdav_json_ok([
        'path' => $result['path'],
        'entries' => $result['entries'],
    ]);
}

$id = $_GET['id'] ?? $body['id'] ?? '';
if (!in_array($action, ['import'], true)) {
    webdav_json_fail(t('webdav.error_unknown_action'), 400);
}

if (!Presentation::exists($id)) {
    webdav_json_fail(t('webdav.error_not_found'), 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    webdav_json_fail(t('webdav.error_forbidden'), 403);
}
if (!in_array($perm, ['owner', 'edit'], true)) {
    webdav_json_fail(t('webdav.error_edit'), 403);
}

$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    webdav_json_fail(t('webdav.error_csrf'), 403);
}

$path = WebdavClient::normalizeBrowsePath((string)($body['path'] ?? ''));
if ($path === '') {
    webdav_json_fail(t('webdav.error_invalid_path'), 400);
}

if ($action === 'import') {
    @set_time_limit(600);
}

$declaredSize = max(0, (int)($body['size'] ?? 0));
$result = WebdavClient::importToPresentation($id, $drive, $path, $declaredSize);
if (!$result['ok']) {
    $map = [
        'invalid_path' => t('webdav.error_invalid_path'),
        'unsupported_type' => t('webdav.error_type'),
        'file_too_large' => t('webdav.error_too_large'),
        'download_failed' => t('webdav.error_download'),
        'auth_failed' => t('webdav.error_auth_failed'),
        'not_found' => t('webdav.error_not_found'),
        'save_failed' => t('webdav.error_save'),
        'https_required' => t('webdav.error_https_required'),
        'blocked_host' => t('webdav.error_blocked_host'),
    ];
    webdav_json_fail($map[$result['error'] ?? ''] ?? t('webdav.error_generic'), 400);
}

webdav_json_ok([
    'url' => $result['url'],
    'filename' => $result['filename'],
    'kind' => $result['kind'],
    'name' => $result['name'] ?? '',
]);
