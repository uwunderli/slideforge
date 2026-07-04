<?php
require __DIR__ . '/../config.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function (Throwable $e) {
    error_log('SlideForge admin_api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unerwarteter Serverfehler.']);
});

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Nur für Administratoren.']);
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

$action = $body['action'] ?? '';

if ($action === 'test_mail') {
    $to = trim($body['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Ungültige E-Mail-Adresse.']);
        exit;
    }
    $smtp = Config::smtp();
    if (empty($smtp['host'])) {
        echo json_encode(['ok' => false, 'error' => 'Kein SMTP-Server konfiguriert.']);
        exit;
    }
    $result = SmtpMailer::send(
        $smtp,
        $to,
        'Test-Mail von ' . Config::siteTitle(),
        "Das ist eine Test-Mail von " . Config::siteTitle() . ".\n\nWenn du diese Nachricht erhältst, ist der SMTP-Versand korrekt eingerichtet."
    );
    if ($result['ok']) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Versand fehlgeschlagen.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion.']);
