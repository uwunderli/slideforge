<?php
declare(strict_types=1);

/**
 * Render-Haken für andere Apps (SignForge, 4.1.0): macht aus `slides.json` die Reveal-Abschnitte, wie sie der Presenter zeigt.
 * Reine Darstellung: liest und schreibt keine Präsentation, holt keine Medien, braucht keine Person. Darum darf jede App mit
 * eingetragenem Schlüssel aufrufen (signierter AppCall, `X-App-Sig`); die Rechte auf die Folien prüft der Aufrufer beim Lesen der Datei.
 *
 * POST {"slides":{"slides":[…]}, "width":1920, "height":1080} — Medienverweise schon als absolute URLs des Aufrufers.
 * Antwort 200 {ok, sections, fontsCss, fontsHead, runtimeJs}; Fehler 400 bad_request, 401 unauthorized, 405, 413 too_large.
 */
require dirname(__DIR__, 2) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const SF_RENDER_MAX = 8 * 1024 * 1024;

$fail = static function (int $status, string $error): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    $fail(405, 'method_not_allowed');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > SF_RENDER_MAX) {
    $fail(413, 'too_large');
}
$raw = (string)file_get_contents('php://input', false, null, 0, SF_RENDER_MAX + 1);
if (strlen($raw) > SF_RENDER_MAX) {
    $fail(413, 'too_large');
}
$headers = array_change_key_case(function_exists('getallheaders') ? (array)getallheaders() : [], CASE_LOWER);
$path = HubApp::hookPath();
$r = $path === '' || !isset($headers['x-app-sig']) ? ['ok' => false] : HubApp::receive($headers, 'POST', $path, $raw);
if (empty($r['ok']) || (string)($r['caller'] ?? '') === '') {
    $fail(401, 'unauthorized');
}

$in = json_decode($raw, true);
$slides = is_array($in) ? ($in['slides'] ?? null) : null;
if (!is_array($slides) || !isset($slides['slides']) || !is_array($slides['slides'])) {
    $fail(400, 'bad_request');
}

// Absolute Adresse der App: eigener Server (`hub.selfOrigin`) oder der Hub, darunter der Pfad der App (`/slideforge`).
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$appPath = preg_match('#^(/[a-z0-9_-]+)/public/#', $script, $m) === 1 ? $m[1] : rtrim(str_replace('\\', '/', dirname($script, 2)), '/');
$origin = HubApp::selfOrigin();
if ($origin === '' && function_exists('churchforge_hub_url')) {
    $origin = rtrim((string)churchforge_hub_url(), '/');
}
$fontsCss = $origin . $appPath . '/fonts.css.php?v=' . ASSET_VERSION;

try {
    SlideRenderer::resetInlineMedia();
    $sections = SlideRenderer::renderSections($slides, null);
    // Eingebettete Medien (data:-URIs) liegen als Textblöcke neben den Abschnitten und brauchen die Hydrierung im Laufzeit-Skript.
    $inline = SlideRenderer::getInlineMedia();
    foreach ($inline as $ref => $data) {
        $sections .= '<script type="text/plain" id="' . h((string)$ref) . '" data-mime="' . h((string)$data['mime']) . '">' . $data['b64'] . "</script>\n";
    }
    $hydrate = $inline !== [];
    $runtimeJs = Exporter::mediaRuntimeJs($hydrate) . "\n" . Exporter::mediaSlideBootJs($hydrate);
    $fontsHead = FontLibrary::googleFontsLinkTag() . "\n"
        . '<link rel="stylesheet" href="' . h(FontLibrary::hubCssUrl()) . '" crossorigin="anonymous">' . "\n"
        . '<link rel="stylesheet" href="' . h($fontsCss) . '">' . "\n";
} catch (Throwable) {
    $fail(400, 'bad_request');
}

echo json_encode([
    'ok' => true,
    'sections' => $sections,
    'fontsCss' => $fontsCss,
    'fontsHead' => $fontsHead,
    'runtimeJs' => $runtimeJs,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
