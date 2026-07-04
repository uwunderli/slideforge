<?php
require __DIR__ . '/../config.php';

Auth::requireLogin();

$data = (string)($_GET['data'] ?? '');
if ($data === '' || strlen($data) > 2000) {
    http_response_code(400);
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? '';
if (!RemoteQr::isAllowedRemoteUrl($data, $host)) {
    http_response_code(403);
    exit;
}

if (!RemoteQr::outputPng($data)) {
    http_response_code(500);
    exit;
}
