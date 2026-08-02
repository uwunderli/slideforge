<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_check();

$me = Auth::currentUser();
$action = (string)($_POST['action'] ?? '');
$rawReturn = (string)($_POST['return'] ?? '');
$redirect = $rawReturn !== '' ? $rawReturn : (string)($_SERVER['HTTP_REFERER'] ?? 'index.php');
if (preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
    $redirect = 'index.php';
}
if (!preg_match('#^[a-zA-Z0-9_./?&=%\-]+$#', $redirect)) {
    $redirect = 'index.php';
}

if ($action === 'set_locale') {
    $lang = (string)($_POST['locale'] ?? 'de');
    if (!isset(I18n::SUPPORTED[$lang])) {
        $lang = 'de';
    }
    Auth::setLanguage($me['id'], $lang);
} elseif ($action === 'set_theme') {
    $theme = (string)($_POST['theme'] ?? 'dark');
    if (class_exists('SharedAuth')) {
        $theme = SharedAuth::normalizeThemePref($theme);
        SharedAuth::issueThemeCookie($theme);
    } elseif (!in_array($theme, ['dark', 'light', 'system'], true)) {
        $theme = 'dark';
    }
    Auth::setTheme($me['id'], $theme);
}

redirect($redirect !== '' ? $redirect : 'index.php');
