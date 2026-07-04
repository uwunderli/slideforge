<?php
require __DIR__ . '/../config.php';
// Bei JSON-Endpunkten dürfen keine HTML-Warnungen/Fehlerseiten in die Antwort rutschen.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function (Throwable $e) {
    error_log('SlideForge upload.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unerwarteter Serverfehler.']);
});

function upload_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (!Auth::isLoggedIn()) {
    upload_fail('Nicht angemeldet.', 401);
}
$me = Auth::currentUser();

$id = $_POST['id'] ?? '';
if (!Presentation::exists($id)) {
    upload_fail('Präsentation nicht gefunden.', 404);
}
if (!Presentation::canEdit($id, $me['id'])) {
    upload_fail('Keine Bearbeitungsrechte.', 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    upload_fail('Ungültiges CSRF-Token.', 403);
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $map = [
        UPLOAD_ERR_INI_SIZE => 'Datei ist grösser als vom Server erlaubt.',
        UPLOAD_ERR_FORM_SIZE => 'Datei ist zu gross.',
        UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
    ];
    upload_fail($map[$err] ?? 'Upload fehlgeschlagen.');
}

$kind = $_POST['kind'] ?? 'image'; // 'image', 'video' oder 'audio'
$file = $_FILES['file'];

$maxSize = $kind === 'video' ? MAX_VIDEO_SIZE : ($kind === 'audio' ? MAX_AUDIO_SIZE : MAX_IMAGE_SIZE);
if ($file['size'] > $maxSize) {
    upload_fail('Datei ist zu gross (max. ' . round($maxSize / 1024 / 1024) . ' MB).');
}

$allowedImage = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$allowedVideo = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
$allowedAudio = ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a'];
$allowed = $kind === 'video' ? $allowedVideo : ($kind === 'audio' ? $allowedAudio : $allowedImage);

$finfo = @finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    error_log('SlideForge upload.php: finfo_open() fehlgeschlagen - fileinfo-Extension evtl. nicht aktiviert.');
    upload_fail('Server kann Dateityp nicht prüfen (fileinfo-Extension fehlt). Bitte Administrator kontaktieren.', 500);
}
$mime = @finfo_file($finfo, $file['tmp_name']);

if ($mime === false || !isset($allowed[$mime])) {
    upload_fail('Nicht unterstützter Dateityp' . ($mime ? ': ' . h($mime) : '') . '. Erlaubt: ' . implode(', ', $allowed));
}

$ext = $allowed[$mime];
$filename = Storage::generateId(8) . '.' . $ext;
$assetsDir = Presentation::dir($id) . '/assets';
if (!is_dir($assetsDir)) {
    mkdir($assetsDir, 0770, true);
}
$destination = $assetsDir . '/' . $filename;

if (!@move_uploaded_file($file['tmp_name'], $destination)) {
    $err = error_get_last();
    error_log('SlideForge upload.php: move_uploaded_file fehlgeschlagen: ' . ($err['message'] ?? 'unbekannt') . ' (Ziel: ' . $destination . ')');
    upload_fail('Datei konnte nicht gespeichert werden (Berechtigungsproblem im assets/-Ordner?).', 500);
}

echo json_encode([
    'ok' => true,
    'filename' => $filename,
    'url' => 'asset.php?id=' . urlencode($id) . '&file=' . urlencode($filename),
]);
