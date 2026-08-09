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
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Nicht angemeldet.']);
        exit;
    }
    $me = Auth::currentUser();
    $canView = Presentation::canView($id, $me['id']);
    $canEdit = Presentation::canEdit($id, $me['id']);
    if (!$canView) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Kein Zugriff.']);
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

    $action = (string)($body['action'] ?? '');

    $remoteActions = ['step', 'laser', 'remote_heartbeat', 'config'];
    if (in_array($action, $remoteActions, true)) {
        // View-Recht reicht für Mobile-Remote
    } elseif ($action === 'present_heartbeat') {
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Present-Rechte.']);
            exit;
        }
    } elseif ($action === 'claim_leader') {
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Present-Rechte.']);
            exit;
        }
    } elseif ($action === 'stop' || $action === 'media') {
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Bearbeitungsrechte.']);
            exit;
        }
    } elseif ($action === '') {
        $source = (string)($body['source'] ?? 'present');
        if ($source === 'remote') {
            // Remote-Position (selten; Schritte bevorzugt)
        } elseif (!$canEdit) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Bearbeitungsrechte.']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion.']);
        exit;
    }

    if ($action === 'stop') {
        Presentation::clearLivePosition($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'present_heartbeat') {
        $clientId = (string)($body['client_id'] ?? '');
        $leader = Presentation::presentHeartbeat($id, $clientId, $me['id']);
        echo json_encode(['ok' => true] + $leader);
        exit;
    }

    if ($action === 'claim_leader') {
        $clientId = (string)($body['client_id'] ?? '');
        $clientKind = (string)($body['client_kind'] ?? 'present');
        if ($clientId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'client_id fehlt.']);
            exit;
        }
        $leader = Presentation::resolvePresentLeader($id, $clientId, $me['id'], true, $clientKind);
        echo json_encode(['ok' => true] + $leader);
        exit;
    }

    if ($action === 'remote_heartbeat') {
        $clientId = (string)($body['client_id'] ?? '');
        $leader = Presentation::remoteHeartbeat($id, $clientId, $me['id']);
        echo json_encode(['ok' => true] + $leader);
        exit;
    }

    if ($action === 'config') {
        $partial = [];
        if (array_key_exists('showTimebar', $body)) {
            $partial['showTimebar'] = !empty($body['showTimebar']);
        }
        if ($partial) {
            Presentation::setLiveConfig($id, $partial);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'step') {
        $direction = (string)($body['direction'] ?? '');
        if (!in_array($direction, ['next', 'prev'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Richtung.']);
            exit;
        }
        $clientId = (string)($body['client_id'] ?? '');
        $result = Presentation::setLiveStep($id, $direction, $clientId, $me['id']);
        echo json_encode(['ok' => true] + $result);
        exit;
    }

    if ($action === 'media') {
        $mediaId = (string)($body['media_id'] ?? '');
        $mediaAction = (string)($body['media_action'] ?? '');
        if ($mediaId === '' || !in_array($mediaAction, ['play', 'pause', 'stop', 'seek'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ungültiger Medien-Befehl.']);
            exit;
        }
        $mediaTime = array_key_exists('media_time', $body) ? (float)$body['media_time'] : null;
        if ($mediaAction === 'seek' && ($mediaTime === null || !is_finite($mediaTime) || $mediaTime < 0)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ungültige Medien-Position.']);
            exit;
        }
        Presentation::setLiveMediaCommand($id, $mediaId, $mediaAction, $mediaTime);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'laser') {
        $active = !empty($body['active']);
        $x = isset($body['x']) ? (float)$body['x'] : null;
        $y = isset($body['y']) ? (float)$body['y'] : null;
        $slideIndex = (int)($body['slide_index'] ?? 0);
        $color = (string)($body['color'] ?? '#ff0000');
        $size = (int)($body['size'] ?? 24);
        $trail = !empty($body['trail']);
        $clientId = (string)($body['client_id'] ?? '');
        $result = Presentation::setLiveLaser($id, $active, $x, $y, $slideIndex, $color, $size, $trail, $clientId, $me['id']);
        echo json_encode(['ok' => true] + $result);
        exit;
    }

    $index = (int)($body['index'] ?? 0);
    $frag = isset($body['frag']) && $body['frag'] !== null ? (int)$body['frag'] : null;
    $channel = ($body['channel'] ?? '') === 'editor' ? 'editor' : 'present';
    $source = (string)($body['source'] ?? 'present');
    if (!in_array($source, ['present', 'remote', 'editor'], true)) {
        $source = 'present';
    }
    $clientId = (string)($body['client_id'] ?? '');
    $result = Presentation::setLivePosition($id, $index, $frag, $channel, $source, $clientId, $me['id']);
    if ($source === 'remote') {
        Presentation::touchLiveSession($id, 'remote', $me['id']);
    }
    echo json_encode(['ok' => true] + $result);
    exit;
}

// Lesen (GET): angemeldet mit View-Recht oder gültiger öffentlicher Token
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
$full = !empty($_GET['full']);
if ($full) {
    $state = Presentation::getLiveFullState($id, $channel);
    echo json_encode(['ok' => true] + $state);
    exit;
}

$live = Presentation::getLivePosition($id, $channel);
echo json_encode(['ok' => true, 'live' => $live]);
