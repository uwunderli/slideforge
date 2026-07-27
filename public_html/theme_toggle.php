<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$order = ['dark', 'light', 'system'];
$current = class_exists('SharedAuth') && isset($_COOKIE[SharedAuth::THEME_COOKIE])
    ? SharedAuth::themePref()
    : Auth::themePref($me);
$idx = array_search($current, $order, true);
$next = $order[($idx === false ? 0 : $idx + 1) % count($order)];

if (class_exists('SharedAuth')) {
    SharedAuth::issueThemeCookie($next);
}
Auth::setTheme($me['id'], $next);

$redirect = $_GET['redirect'] ?? 'index.php';
if (preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
    $redirect = 'index.php';
}
if (!preg_match('#^[a-zA-Z0-9_./?&=%\-]+$#', $redirect)) {
    $redirect = 'index.php';
}
redirect($redirect !== '' ? $redirect : 'index.php');
