<?php
declare(strict_types=1);

/**
 * Takt des Hubs (Job `slideforge.aufraeumen` in der Cronverwaltung, kein eigener Cron): entfernt Arbeitskopien, die seit
 * 14 Tagen niemand geöffnet hat (Ungespeichertes erst nach 28 Tagen), und alte Zwischendateien. POST, nur mit der Signatur
 * des Hub-Systembenutzers.
 */
require dirname(__DIR__, 2) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}
$raw = (string)file_get_contents('php://input', false, null, 0, 4097);
$headers = array_change_key_case(function_exists('getallheaders') ? (array)getallheaders() : [], CASE_LOWER);
$path = HubApp::hookPath();
$r = strlen($raw) > 4096 || $path === '' || !isset($headers['x-app-sig']) ? ['ok' => false] : HubApp::receive($headers, 'POST', $path, $raw);
if (empty($r['ok']) || ($r['caller'] ?? '') !== '__system__') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
ignore_user_abort(true);
@set_time_limit(600);
echo json_encode(['ok' => true] + Workspace::cleanup(14));
