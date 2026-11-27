<?php
require __DIR__ . '/../config.php';

$id = $_GET['id'] ?? '';
$file = $_GET['file'] ?? '';
$token = $_GET['token'] ?? '';

if (!Presentation::exists($id) || $file === '' || basename($file) !== $file) {
    http_response_code(404);
    exit;
}

// Zugriff: entweder angemeldet mit View-Recht, oder gültiger öffentlicher Token
$allowed = false;
if (Auth::isLoggedIn()) {
    $me = Auth::currentUser();
    if ($me && Presentation::canView($id, $me['id'])) {
        $allowed = true;
    }
}
if (!$allowed && $token !== '') {
    $acl = Presentation::getAcl($id);
    if (!empty($acl['public']['enabled']) && hash_equals($acl['public']['token'] ?? '', $token)) {
        $allowed = true;
    }
}
if (!$allowed) {
    http_response_code(403);
    exit;
}

$path = Presentation::dir($id) . '/assets/' . $file;
$realAssets = realpath(Presentation::dir($id) . '/assets');
$realPath = realpath($path);
if (!$realPath || !$realAssets || strpos($realPath, $realAssets) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
    'mp4' => 'video/mp4', 'webm' => 'video/webm',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

$tintColor = SvgHelper::normalizeHex((string)($_GET['color'] ?? ''));
if ($ext === 'svg' && $tintColor !== null) {
    $body = SvgHelper::tintSvg((string)file_get_contents($realPath), $tintColor);
    header('Content-Type: image/svg+xml');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, max-age=86400');
    echo $body;
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=86400');
header('Accept-Ranges: bytes');
readfile($realPath);
