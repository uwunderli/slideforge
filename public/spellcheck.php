<?php
require __DIR__ . '/../config.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function spell_json_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function spell_json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

if (!Auth::isLoggedIn()) {
    spell_json_fail('Nicht angemeldet.', 401);
}
$me = Auth::currentUser();

$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$id = $_GET['id'] ?? $body['id'] ?? '';

if ($action !== 'check') {
    spell_json_fail('Unbekannte Aktion.', 400);
}

if (!Presentation::exists($id)) {
    spell_json_fail('Präsentation nicht gefunden.', 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    spell_json_fail('Kein Zugriff.', 403);
}

$lang = trim((string)($body['language'] ?? ''));
if ($lang === '' || !Auth::isSpellcheckLanguage($lang)) {
    $lang = Auth::spellcheckLanguage($me);
}

$result = SpellCheckService::checkPresentation($id, $lang);
if (!$result['ok']) {
    $err = $result['error'] ?? 'unknown';
    if ($err === 'disabled') {
        spell_json_fail(t('spell.error_disabled'), 503);
    }
    spell_json_fail(t('spell.error_api', ['detail' => $err]), 502);
}

spell_json_ok([
    'issues' => $result['issues'] ?? [],
    'issueCount' => $result['issueCount'] ?? 0,
    'language' => $result['language'] ?? SpellCheckPlain::languageCode($lang),
]);
