<?php
require __DIR__ . '/../config.php';

$lang = $_GET['lang'] ?? 'de';
if (!isset(I18n::SUPPORTED[$lang])) {
    $lang = 'de';
}

if (Auth::isLoggedIn()) {
    $me = Auth::currentUser();
    Auth::setLanguage($me['id'], $lang);
} else {
    setcookie('sf_lang', $lang, time() + 60 * 60 * 24 * 365, '/');
}

$redirect = $_GET['redirect'] ?? 'index.php';
// Nur relative Pfade erlauben (kein Open-Redirect)
if (preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
    $redirect = 'index.php';
}
redirect($redirect);
