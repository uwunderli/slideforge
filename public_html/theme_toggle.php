<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$newTheme = ($me['theme'] ?? 'dark') === 'light' ? 'dark' : 'light';
Auth::setTheme($me['id'], $newTheme);

$redirect = $_GET['redirect'] ?? 'index.php';
// Nur relative Pfade zulassen (Open-Redirect vermeiden)
if (preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
    $redirect = 'index.php';
}
redirect($redirect !== '' ? $redirect : 'index.php');
