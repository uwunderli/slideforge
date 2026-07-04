<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (!Presentation::exists($id)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Schreiben: nur mit Bearbeitungsrecht + gültigem CSRF-Token (aus dem Präsentationsmodus).
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Nicht angemeldet.']);
        exit;
    }
    $me = Auth::currentUser();
    if (!Presentation::canEdit($id, $me['id'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Keine Bearbeitungsrechte.']);
        exit;
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    $token = $body['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ungültiges CSRF-Token.']);
        exit;
    }

    if (($body['action'] ?? '') === 'stop') {
        Presentation::clearLivePosition($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (($body['action'] ?? '') === 'media') {
        $mediaId = (string)($body['media_id'] ?? '');
        $mediaAction = (string)($body['media_action'] ?? '');
        if ($mediaId === '' || !in_array($mediaAction, ['play', 'pause', 'stop'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ungültiger Medien-Befehl.']);
            exit;
        }
        Presentation::setLiveMediaCommand($id, $mediaId, $mediaAction);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (($body['action'] ?? '') === 'laser') {
        $active = !empty($body['active']);
        $x = isset($body['x']) ? (float)$body['x'] : null;
        $y = isset($body['y']) ? (float)$body['y'] : null;
        $slideIndex = (int)($body['slide_index'] ?? 0);
        $color = (string)($body['color'] ?? '#ff0000');
        $size = (int)($body['size'] ?? 24);
        $trail = !empty($body['trail']);
        Presentation::setLiveLaser($id, $active, $x, $y, $slideIndex, $color, $size, $trail);
        echo json_encode(['ok' => true]);
        exit;
    }

    $index = (int)($body['index'] ?? 0);
    $frag = isset($body['frag']) && $body['frag'] !== null ? (int)$body['frag'] : null;
    $channel = ($body['channel'] ?? '') === 'editor' ? 'editor' : 'present';
    Presentation::setLivePosition($id, $index, $frag, $channel);
    echo json_encode(['ok' => true]);
    exit;
}

// Lesen (GET): entweder angemeldet mit View-Recht, oder gültiger öffentlicher Token -
// dieselbe Logik wie bei asset.php, damit auch der öffentliche Link mitfolgen kann.
$token = $_GET['token'] ?? '';
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
    echo json_encode(['ok' => false]);
    exit;
}

$channel = ($_GET['channel'] ?? '') === 'editor' ? 'editor' : 'present';
$live = Presentation::getLivePosition($id, $channel);
echo json_encode(['ok' => true, 'live' => $live]);
