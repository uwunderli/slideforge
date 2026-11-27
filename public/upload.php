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

$kind = $_POST['kind'] ?? 'image'; // 'image', 'video' oder 'audio'
$maxSize = $kind === 'video' ? MAX_VIDEO_SIZE : ($kind === 'audio' ? MAX_AUDIO_SIZE : MAX_IMAGE_SIZE);
$assetsDir = Presentation::dir($id) . '/assets';
if (!is_dir($assetsDir)) {
    mkdir($assetsDir, 0770, true);
}

/* Medien kommen aus dem FileForge-Dialog des Hubs (27.11.2026, Urs: «FF Piker verwenden, bei Bilder, Audio Video auch»;
   Hochladen vom Rechner bietet der Dialog selbst an). `ref` wird als die angemeldete Person über AppCall gelesen, das
   Recht prüft FileForge. Die Kopie landet in assets/ der Arbeitskopie und damit in der .cfs. */
$ffRef = (string)($_POST['ref'] ?? '');
if (FfFile::parseRef($ffRef) === null) {
    upload_fail('Keine Datei gewählt.');
}
$tmpFf = (string)tempnam(sys_get_temp_dir(), 'sf-ff-');   // nicht in assets/: sonst sähe die Medienliste die halbe Datei
register_shutdown_function(static function () use (&$tmpFf): void {
    if ($tmpFf !== '' && is_file($tmpFf)) {
        @unlink($tmpFf);
    }
});
$r = FfFile::read((string)$me['id'], $ffRef, '', $tmpFf);
if (!$r['ok']) {
    $ffMsg = [
        'too_large' => 'Datei ist zu gross (max. ' . round($maxSize / 1024 / 1024) . ' MB).',
        'no_access' => 'Kein Zugriff auf diese Datei.',
        'no_person' => 'Kein Zugriff auf diese Datei.',
        'busy' => 'FileForge ist gerade beschäftigt. Bitte gleich nochmals versuchen.',
    ];
    upload_fail($ffMsg[$r['error']] ?? 'Die Datei liess sich aus FileForge nicht holen.', $r['error'] === 'too_large' ? 400 : 502);
}
$file = ['tmp_name' => $tmpFf, 'size' => (int)@filesize($tmpFf)];

if ($file['size'] > $maxSize) {
    upload_fail('Datei ist zu gross (max. ' . round($maxSize / 1024 / 1024) . ' MB).');
}

$allowedImage = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
$allowedVideo = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
$allowedAudio = ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a'];
$allowed = $kind === 'video' ? $allowedVideo : ($kind === 'audio' ? $allowedAudio : $allowedImage);

$finfo = @finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    error_log('SlideForge upload.php: finfo_open() fehlgeschlagen - fileinfo-Extension evtl. nicht aktiviert.');
    upload_fail('Server kann Dateityp nicht prüfen (fileinfo-Extension fehlt). Bitte Administrator kontaktieren.', 500);
}
$mime = @finfo_file($finfo, $file['tmp_name']);

/* finfo meldet SVG je nach Inhalt als text/xml, application/xml oder text/plain. */
if ($kind !== 'video' && $kind !== 'audio' && is_string($mime)
    && in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], true)) {
    $head = (string)@file_get_contents($file['tmp_name'], false, null, 0, 512);
    if (stripos($head, '<svg') !== false) {
        $mime = 'image/svg+xml';
    }
}

if ($mime === false || !isset($allowed[$mime])) {
    upload_fail('Nicht unterstützter Dateityp' . ($mime ? ': ' . h($mime) : '') . '. Erlaubt: ' . implode(', ', $allowed));
}

$ext = $allowed[$mime];
$filename = Storage::generateId(8) . '.' . $ext;
$destination = $assetsDir . '/' . $filename;

/* SVG kann Skripte enthalten und wird von asset.php als image/svg+xml auf
   derselben Domain ausgeliefert — vor dem Speichern entschärfen. */
if ($mime === 'image/svg+xml') {
    $clean = SvgHelper::sanitize((string)@file_get_contents($file['tmp_name']));
    if ($clean === null) {
        upload_fail('Diese SVG-Datei konnte nicht verarbeitet werden.');
    }
    if (@file_put_contents($destination, $clean) === false) {
        upload_fail('Datei konnte nicht gespeichert werden.', 500);
    }
    @chmod($destination, 0640);
    echo json_encode([
        'ok' => true,
        'filename' => $filename,
        'url' => 'asset.php?id=' . urlencode($id) . '&file=' . urlencode($filename),
    ]);
    exit;
}

if (!@rename($tmpFf, $destination) && !(@copy($tmpFf, $destination) && @unlink($tmpFf))) {
    $err = error_get_last();
    error_log('SlideForge upload.php: Ablegen fehlgeschlagen: ' . ($err['message'] ?? 'unbekannt') . ' (Ziel: ' . $destination . ')');
    upload_fail('Datei konnte nicht gespeichert werden (Berechtigungsproblem im assets/-Ordner?).', 500);
}
$tmpFf = '';
@chmod($destination, 0640);

echo json_encode([
    'ok' => true,
    'filename' => $filename,
    'url' => 'asset.php?id=' . urlencode($id) . '&file=' . urlencode($filename),
]);
