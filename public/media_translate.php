<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => t('media.error_auth')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    $body = [];
}

$text = trim((string)($body['text'] ?? ''));
if ($text === '') {
    echo json_encode(['ok' => false, 'error' => t('media.translate_empty')]);
    exit;
}
if (mb_strlen($text) > 200) {
    echo json_encode(['ok' => false, 'error' => t('media.translate_too_long')]);
    exit;
}

try {
    $from = I18n::currentLang();
    $translated = MediaTranslate::toEnglish($text, $from);
    echo json_encode([
        'ok' => true,
        'translated' => $translated,
        'from' => $from,
        'to' => 'en',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
