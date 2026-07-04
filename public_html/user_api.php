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

    case 'set_spellcheck_before_present':
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            user_json_fail('Ungültiges CSRF-Token.', 403);
        }
        $beforePresent = !empty($body['before_present']);
        Auth::setSpellcheckBeforePresent($me['id'], $beforePresent);
        user_json_ok(['before_present' => $beforePresent]);
        break;

    case 'save_webdav_drive':
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            user_json_fail(t('webdav.error_csrf'), 403);
        }
        $result = Auth::saveWebdavDrive($me['id'], $body);
        if (!$result['ok']) {
            $map = [
                'invalid_label' => t('webdav.error_invalid_label'),
                'invalid_url' => t('webdav.error_invalid_url'),
                'https_required' => t('webdav.error_https_required'),
                'invalid_username' => t('webdav.error_invalid_username'),
                'password_required' => t('webdav.error_password_required'),
                'save_failed' => t('webdav.error_save'),
            ];
            user_json_fail($map[$result['error'] ?? ''] ?? t('webdav.error_generic'), 400);
        }
        user_json_ok(['drive' => $result['drive']]);
        break;

    case 'delete_webdav_drive':
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            user_json_fail(t('webdav.error_csrf'), 403);
        }
        $driveId = trim((string)($body['drive_id'] ?? ''));
        $result = Auth::deleteWebdavDrive($me['id'], $driveId);
        if (!$result['ok']) {
            user_json_fail(t('webdav.error_drive_not_found'), 404);
        }
        user_json_ok();
        break;

    case 'test_webdav_drive':
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            user_json_fail(t('webdav.error_csrf'), 403);
        }
        $driveId = trim((string)($body['drive_id'] ?? ''));
        $password = (string)($body['password'] ?? '');
        if ($driveId !== '' && $password === '') {
            $drive = Auth::getWebdavDriveCredentials($me['id'], $driveId);
        } else {
            $drive = [
                'url' => trim((string)($body['url'] ?? '')),
                'username' => trim((string)($body['username'] ?? '')),
                'password' => $password,
                'root_path' => WebdavClient::normalizeBrowsePath((string)($body['root_path'] ?? '')),
            ];
        }
        if (!$drive || ($drive['url'] ?? '') === '' || ($drive['username'] ?? '') === '') {
            user_json_fail(t('webdav.error_drive_not_found'), 404);
        }
        $test = WebdavClient::testConnection($drive);
        if (!$test['ok']) {
            $map = [
                'invalid_url' => t('webdav.error_invalid_url'),
                'https_required' => t('webdav.error_https_required'),
                'blocked_host' => t('webdav.error_blocked_host'),
                'auth_failed' => t('webdav.error_auth_failed'),
                'not_found' => t('webdav.error_not_found'),
                'request_failed' => t('webdav.error_connection'),
                'not_webdav' => t('webdav.error_not_webdav'),
            ];
            user_json_fail($map[$test['error'] ?? ''] ?? t('webdav.error_connection'), 502);
        }
        user_json_ok();
        break;

    default:
        user_json_fail('Unbekannte Aktion.', 400);
}
