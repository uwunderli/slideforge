<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function dashboard_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function dashboard_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::isLoggedIn()) {
    dashboard_json_fail(t('media.error_auth'), 401);
}

$me = Auth::currentUser();
$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($action !== 'sections' && !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    dashboard_json_fail(t('pixabay.error_csrf'), 403);
}

try {
    switch ($action) {
        case 'sections':
            [$owned, $shared] = Presentation::listForUser($me['id']);
            $archived = !empty($_GET['archived']) || !empty($body['archived']);
            $sections = Dashboard::buildView($me['id'], $owned, $shared, $archived);
            dashboard_json_ok(['sections' => $sections]);

        case 'set_tab_view':
            $archived = !empty($body['archived']);
            $mode = Dashboard::setTabViewMode($me['id'], $archived, (string)($body['view_mode'] ?? 'grid'));
            dashboard_json_ok(['view_mode' => $mode]);

        case 'section_create':
            $title = trim((string)($body['title'] ?? ''));
            $section = Dashboard::createSection($me['id'], $title);
            dashboard_json_ok(['section' => $section]);

        case 'section_rename':
            $sectionId = (string)($body['section_id'] ?? '');
            $title = trim((string)($body['title'] ?? ''));
            $section = Dashboard::renameSection($me['id'], $sectionId, $title);
            dashboard_json_ok(['section' => $section]);

        case 'section_delete':
            $sectionId = (string)($body['section_id'] ?? '');
            Dashboard::deleteSection($me['id'], $sectionId);
            dashboard_json_ok();

        case 'section_reorder':
            $ids = $body['section_ids'] ?? [];
            if (!is_array($ids)) {
                dashboard_json_fail(t('dashboard.invalid_payload'));
            }
            Dashboard::reorderSections($me['id'], array_values(array_map('strval', $ids)));
            dashboard_json_ok();

        case 'section_prefs':
            $sectionId = (string)($body['section_id'] ?? '');
            $collapsed = array_key_exists('collapsed', $body) ? !empty($body['collapsed']) : null;
            $section = Dashboard::setSectionPrefs($me['id'], $sectionId, $collapsed);
            dashboard_json_ok(['section' => $section]);

        case 'item_reorder':
            $sectionId = (string)($body['section_id'] ?? '');
            $ids = $body['presentation_ids'] ?? [];
            if (!is_array($ids)) {
                dashboard_json_fail(t('dashboard.invalid_payload'));
            }
            Dashboard::reorderItems($me['id'], $sectionId, array_values(array_map('strval', $ids)));
            dashboard_json_ok();

        case 'item_move':
            $presentationId = (string)($body['presentation_id'] ?? '');
            $sectionId = (string)($body['section_id'] ?? '');
            $index = array_key_exists('sort_order', $body) ? (int)$body['sort_order'] : null;
            Dashboard::moveItem($me['id'], $presentationId, $sectionId, $index);
            dashboard_json_ok();

        default:
            dashboard_json_fail(t('pixabay.error_unknown_action'), 400);
    }
} catch (InvalidArgumentException $e) {
    dashboard_json_fail($e->getMessage());
} catch (Throwable $e) {
    dashboard_json_fail(t('dashboard.error_generic'), 500);
}
