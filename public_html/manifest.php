<?php
require __DIR__ . '/../config.php';

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$prefix = $base === '' ? '' : $base;
$name = Config::siteTitle();
$short = mb_strlen($name) > 12 ? APP_NAME : $name;

$manifest = [
    'name' => $name,
    'short_name' => $short,
    'description' => APP_NAME . ' — presentations & mobile remote',
    'start_url' => $prefix . '/index.php',
    'scope' => $prefix . '/',
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#14171c',
    'theme_color' => '#428BBE',
    'lang' => I18n::currentLang(),
    'icons' => [
        [
            'src' => $prefix . '/assets/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $prefix . '/assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $prefix . '/assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
