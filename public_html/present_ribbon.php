<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function present_ribbon_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function present_ribbon_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::isLoggedIn()) {
    present_ribbon_json_fail(t('media.error_auth'), 401);
}

$me = Auth::currentUser();
$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($action !== 'layout' && !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    present_ribbon_json_fail(t('pixabay.error_csrf'), 403);
}

/* Speichern immer mit vollem Katalog; Request-Kontext filtert nur die Antwort-Sichtbarkeit. */
$persistContext = PresentRibbonLayout::persistContext();
$context = PresentRibbonLayout::buildContext([
    'canBroadcast' => array_key_exists('canBroadcast', $body)
        ? !empty($body['canBroadcast'])
        : true,
    'isOwner' => array_key_exists('isOwner', $body)
        ? !empty($body['isOwner'])
        : true,
    'hasRemote' => array_key_exists('hasRemote', $body)
        ? !empty($body['hasRemote'])
        : true,
]);

try {
    switch ($action) {
        case 'layout':
            $layout = PresentRibbonLayout::getLayout($me['id'], $context);
            present_ribbon_json_ok([
                'layout' => PresentRibbonLayout::layoutForClient($layout, $context),
                'catalog' => PresentRibbonLayout::catalogForClient($context),
            ]);
            break;

        case 'save':
            $layoutIn = $body['layout'] ?? null;
            if (!is_array($layoutIn)) {
                present_ribbon_json_fail(t('ribbon.error_invalid_layout'));
            }
            $sanitized = PresentRibbonLayout::sanitizeLayout($layoutIn, $persistContext);
            PresentRibbonLayout::saveLayout($me['id'], $sanitized);
            $sanitized['customized'] = true;
            present_ribbon_json_ok(['layout' => PresentRibbonLayout::layoutForClient($sanitized, $context)]);
            break;

        case 'reset':
            PresentRibbonLayout::resetLayout($me['id']);
            $layout = PresentRibbonLayout::getLayout($me['id'], $context);
            present_ribbon_json_ok(['layout' => PresentRibbonLayout::layoutForClient($layout, $context)]);
            break;

        default:
            present_ribbon_json_fail(t('pixabay.error_unknown_action'), 400);
    }
} catch (InvalidArgumentException $e) {
    present_ribbon_json_fail($e->getMessage());
} catch (Throwable $e) {
    present_ribbon_json_fail(t('ribbon.error_generic'), 500);
}
