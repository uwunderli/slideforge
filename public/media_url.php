<?php
/**
 * Lädt ein Medium von einer eingegebenen Adresse in die Präsentation.
 * Antwortet immer JSON — auch bei fehlender Anmeldung (kein Redirect,
 * damit XHR-Aufrufe nicht in eine Anmelde-Umleitung laufen).
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function media_url_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (!Auth::isLoggedIn()) {
    media_url_fail(t('fromurl.error_auth'), 401);
}
$me = Auth::currentUser();
if ($me === null) {
    media_url_fail(t('fromurl.error_auth'), 401);
}

$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$id = (string)($body['id'] ?? $_POST['id'] ?? '');
$url = (string)($body['url'] ?? $_POST['url'] ?? '');

$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
    media_url_fail(t('fromurl.error_csrf'), 403);
}

if ($id === '' || Presentation::getMeta($id) === null) {
    media_url_fail(t('fromurl.error_not_found'), 404);
}
$perm = Presentation::checkPermission($id, $me['id']);
if (!in_array($perm, ['owner', 'edit'], true)) {
    media_url_fail(t('fromurl.error_edit'), 403);
}

$result = UrlMediaImporter::import($id, $url);
if (empty($result['ok'])) {
    $map = [
        'invalid_url' => t('fromurl.error_invalid_url'),
        'blocked_url' => t('fromurl.error_blocked'),
        'download_failed' => t('fromurl.error_download'),
        'file_too_large' => t('fromurl.error_too_large'),
        'unsupported_type' => t('fromurl.error_type'),
        'save_failed' => t('fromurl.error_save'),
        'temp_failed' => t('fromurl.error_save'),
    ];
    media_url_fail($map[$result['error'] ?? ''] ?? t('fromurl.error_generic'), 400);
}

echo json_encode([
    'ok' => true,
    'url' => $result['url'],
    'filename' => $result['filename'],
    'kind' => $result['kind'],
]);
