<?php
require __DIR__ . '/../config.php';
// Bei JSON-Endpunkten dürfen keine HTML-Warnungen/Fehlerseiten in die Antwort rutschen.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function (Throwable $e) {
    error_log('SlideForge api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unerwarteter Serverfehler.']);
});

function json_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function json_ok(array $data = []): void {
    echo json_encode(['ok' => true] + $data);
    exit;
}

if (!Auth::isLoggedIn()) {
    json_fail('Nicht angemeldet.', 401);
}
$me = Auth::currentUser();

// Eingaben einlesen: entweder JSON-Body (POST) oder Query-String (GET)
$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$id = $_GET['id'] ?? $body['id'] ?? '';

if (!Presentation::exists($id)) {
    json_fail('Präsentation nicht gefunden.', 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    json_fail('Kein Zugriff.', 403);
}
$canEdit = in_array($perm, ['owner', 'edit'], true);

// Alle verändernden Aktionen brauchen Edit-Recht + gültiges CSRF-Token
$mutating = ['save_slide', 'add_slide', 'delete_slide', 'duplicate_slide', 'reorder_slides', 'apply_slide_template', 'toggle_public_link', 'set_display_options', 'save_meta', 'delete_media_asset', 'cleanup_unused_media'];
if (in_array($action, $mutating, true)) {
    if (!$canEdit) {
        json_fail('Keine Bearbeitungsrechte.', 403);
    }
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_fail('Ungültiges CSRF-Token.', 403);
    }
}

// Folienvorlagen dürfen immer nur genau eine Folie haben.
$multiSlideActions = ['add_slide', 'delete_slide', 'duplicate_slide', 'reorder_slides'];
if (in_array($action, $multiSlideActions, true) && Presentation::isTemplate($id)) {
    json_fail('Folienvorlagen bestehen bewusst aus nur einer Folie.', 400);
}

switch ($action) {
    case 'get_slides':
        $slides = Presentation::getSlides($id);
        $meta = Presentation::getMeta($id);
        json_ok(['slides' => $slides['slides'], 'meta' => $meta, 'permission' => $perm]);
        break;

    case 'slide_thumbnails':
        $slidesData = Presentation::getSlides($id);
        $thumbnails = [];
        foreach ($slidesData['slides'] ?? [] as $slide) {
            $bg = $slide['background'] ?? null;
            $color = '#333333';
            if ($bg) {
                if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                    $color = $bg['value'] ?? '#333333';
                } elseif (($bg['type'] ?? '') === 'image') {
                    $color = '#222222';
                }
            }
            $thumbnails[] = [
                'id' => $slide['id'],
                'html' => SlideRenderer::renderSlideThumbnailHtml($slide, null),
                'color' => $color,
            ];
        }
        json_ok(['thumbnails' => $thumbnails]);
        break;

    case 'save_slide':
        $index = (int)($body['index'] ?? -1);
        $background = $body['background'] ?? ['type' => 'color', 'value' => '#111111'];
        $objects = $body['objects'] ?? [];
        $transition = $body['transition'] ?? null;
        $autoAdvance = isset($body['autoAdvance']) ? (int)$body['autoAdvance'] : null;
        $notes = isset($body['notes']) ? (string)$body['notes'] : null;
        $result = Presentation::saveSlide($id, $index, $background, $objects, $transition, $autoAdvance, $notes);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'add_slide':
        $afterIndex = isset($body['after_index']) ? (int)$body['after_index'] : null;
        $result = Presentation::addSlide($id, $afterIndex);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'duplicate_slide':
        $index = (int)($body['index'] ?? -1);
        $result = Presentation::duplicateSlide($id, $index);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'delete_slide':
        $index = (int)($body['index'] ?? -1);
        $result = Presentation::deleteSlide($id, $index);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'reorder_slides':
        $order = $body['order'] ?? [];
        $result = Presentation::reorderSlides($id, $order);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'apply_transition_all':
        $transition = (string)($body['transition'] ?? 'slide');
        $result = Presentation::applyTransitionToAll($id, $transition);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'list_slide_templates':
        [$mine, $shared] = Presentation::listTemplatesForUser($me['id']);
        json_ok(['mine' => $mine, 'shared' => $shared]);
        break;

    case 'apply_slide_template':
        $index = (int)($body['index'] ?? -1);
        $templateId = $body['template_id'] ?? '';
        if (!Presentation::canUseTemplate($templateId, $me['id'])) {
            json_fail('Keine Berechtigung für diese Vorlage.', 403);
        }
        $slide = Presentation::getTemplateSlideContent($templateId);
        if (!$slide) {
            json_fail('Vorlage ist leer oder ungültig.', 404);
        }
        $result = Presentation::saveSlide(
            $id, $index,
            $slide['background'] ?? ['type' => 'color', 'value' => '#111111'],
            $slide['objects'] ?? [],
            $slide['transition'] ?? 'slide'
        );
        json_ok(['slides' => $result['slides']]);
        break;

    case 'toggle_public_link':
        if ($perm !== 'owner') {
            json_fail('Nur der Eigentümer kann die Freigabe ändern.', 403);
        }
        $enabled = !empty($body['enabled']);
        $public = Presentation::setPublic($id, $enabled, 'view');
        $publicUrl = null;
        if ($enabled && !empty($public['token'])) {
            $scheme = current_scheme();
            $host = $_SERVER['HTTP_HOST'];
            $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            $publicUrl = "$scheme://$host$base/view.php?token=" . $public['token'];
        }
        json_ok(['enabled' => $enabled, 'url' => $publicUrl]);
        break;

    case 'set_display_options':
        $fields = [];
        if (array_key_exists('show_progress', $body)) {
            $fields['show_progress'] = !empty($body['show_progress']);
        }
        if (array_key_exists('show_controls', $body)) {
            $fields['show_controls'] = !empty($body['show_controls']);
        }
        Presentation::updateMeta($id, $fields);
        json_ok(['meta' => Presentation::getMeta($id)]);
        break;

    case 'save_meta':
        $fields = [];
        if (array_key_exists('title', $body)) {
            $title = trim((string)$body['title']);
            if ($title !== '') {
                $fields['title'] = $title;
            }
        }
        if ($fields) {
            Presentation::updateMeta($id, $fields);
        }
        json_ok(['meta' => Presentation::getMeta($id)]);
        break;

    case 'list_media':
        json_ok(['items' => Presentation::listMediaAssets($id)]);
        break;

    case 'delete_media_asset':
        $filename = basename((string)($body['filename'] ?? ''));
        $result = Presentation::deleteMediaAsset($id, $filename);
        if (!$result['ok']) {
            $messages = [
                'invalid_filename' => 'Ungültiger Dateiname.',
                'in_use' => 'Datei wird noch auf mindestens einer Folie verwendet.',
                'not_found' => 'Datei nicht gefunden.',
                'delete_failed' => 'Löschen fehlgeschlagen.',
            ];
            json_fail($messages[$result['error']] ?? 'Löschen fehlgeschlagen.', 400);
        }
        json_ok(['items' => Presentation::listMediaAssets($id)]);
        break;

    case 'cleanup_unused_media':
        $result = Presentation::cleanupUnusedMediaAssets($id);
        if (!$result['ok']) {
            $messages = [
                'delete_failed' => 'Einige Dateien konnten nicht gelöscht werden.',
            ];
            json_fail($messages[$result['error']] ?? 'Aufräumen fehlgeschlagen.', 400);
        }
        json_ok([
            'items' => Presentation::listMediaAssets($id),
            'deleted' => $result['deleted'] ?? [],
            'count' => (int)($result['count'] ?? 0),
        ]);
        break;

    default:
        json_fail('Unbekannte Aktion.', 400);
}
