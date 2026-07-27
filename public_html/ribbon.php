<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function ribbon_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function ribbon_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::isLoggedIn()) {
    ribbon_json_fail(t('media.error_auth'), 401);
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
    ribbon_json_fail(t('pixabay.error_csrf'), 403);
}

$context = RibbonLayout::buildContext([
    'canEdit' => true,
    'isTemplateMode' => !empty($body['templateMode']) || !empty($_GET['templateMode']),
    'isLayoutSetMode' => !empty($body['layoutSetMode']) || !empty($_GET['layoutSetMode']),
    'spellcheckEnabled' => Config::languageToolEnabled(),
    'pixabayEnabled' => Config::pixabayEnabled(),
    'iconifyEnabled' => Config::iconifyEnabled(),
    'openclipartEnabled' => Config::openclipartEnabled(),
    'showMasterSlideNav' => !empty($body['showMasterSlideNav']) || !empty($_GET['showMasterSlideNav']),
    /* Layout ist nutzerweit — Owner-Befehle (Teilen) nicht beim Speichern verwerfen.
       Sichtbarkeit filtert editor.php anhand der aktuellen Präsentation. */
    'isOwner' => true,
]);
$persistContext = RibbonLayout::persistContext();

try {
    switch ($action) {
        case 'layout':
            $layout = RibbonLayout::getLayout($me['id'], $context);
            ribbon_json_ok([
                'layout' => RibbonLayout::layoutForClient($layout, $context),
                'catalog' => RibbonLayout::catalogForClient($context),
            ]);
            break;

        case 'save':
            $layoutIn = $body['layout'] ?? null;
            if (!is_array($layoutIn)) {
                ribbon_json_fail(t('ribbon.error_invalid_layout'));
            }
            /* Speichern immer mit vollem Katalog — Vorlagen-Kontext darf nichts löschen. */
            $sanitized = RibbonLayout::sanitizeLayout($layoutIn, $persistContext);
            RibbonLayout::saveLayout($me['id'], $sanitized);
            $sanitized['customized'] = true;
            ribbon_json_ok(['layout' => RibbonLayout::layoutForClient($sanitized, $context)]);
            break;

        case 'reset':
            RibbonLayout::resetLayout($me['id']);
            $layout = RibbonLayout::getLayout($me['id'], $context);
            ribbon_json_ok(['layout' => RibbonLayout::layoutForClient($layout, $context)]);
            break;

        default:
            ribbon_json_fail(t('pixabay.error_unknown_action'), 400);
    }
} catch (InvalidArgumentException $e) {
    ribbon_json_fail($e->getMessage());
} catch (Throwable $e) {
    ribbon_json_fail(t('ribbon.error_generic'), 500);
}
