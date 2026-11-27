<?php
declare(strict_types=1);

/**
 * Datei aus FileForge in der FolienSchmiede öffnen (FileForge «Öffnen mit», `api/app-open.php`, App → App über den Hub).
 *
 *   POST {files:[ff:<hub>:<ablage>:<pfad>], title} → {ok, id, created, path:"/editor.php?id=…"}
 *
 * Nur mit gültiger Signatur einer im Hub registrierten App. Die Person (`_ap`) steht im signierten Pfad; ihr Recht an der Datei
 * prüft FileForge (stat/read als die Person). Genau eine Datei: .cfs, .pptx oder .odp.
 */
require dirname(__DIR__, 2) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = static function (int $code, string $error): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
};
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    $fail(405, 'method_not_allowed');
}
$raw = (string)file_get_contents('php://input', false, null, 0, 65537);
if (strlen($raw) > 65536) {
    $fail(413, 'too_large');
}
$headers = array_change_key_case(function_exists('getallheaders') ? (array)getallheaders() : [], CASE_LOWER);
$path = HubApp::hookPath();
$r = $path === '' ? ['ok' => false, 'error' => 'bad_path'] : HubApp::receive($headers, 'POST', $path, $raw);
if (empty($r['ok'])) {
    $fail(401, 'unauthorized');
}
$person = trim((string)($r['person'] ?? ''));
if ($person === '') {
    $fail(403, 'no_person');
}
$body = json_decode($raw, true);
$files = is_array($body) && is_array($body['files'] ?? null) ? array_values($body['files']) : [];
if (count($files) !== 1 || !is_string($files[0]) || FfFile::parseRef($files[0]) === null) {
    $fail(400, 'bad_request');
}
@set_time_limit(120);
$res = Workspace::open($person, $files[0], '');
if (empty($res['ok'])) {
    $code = match ((string)$res['error']) { 'no_access' => 403, 'bad_request', 'bad_type', 'not_cfs', 'ppt_unsupported' => 400, 'too_large' => 413, default => 502 };
    $fail($code, (string)$res['error']);
}
echo json_encode(['ok' => true, 'id' => $res['id'], 'created' => !empty($res['created']), 'path' => '/editor.php?id=' . rawurlencode((string)$res['id'])], JSON_UNESCAPED_UNICODE);
