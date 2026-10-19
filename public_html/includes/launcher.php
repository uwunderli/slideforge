<?php
if (!Auth::isLoggedIn() || !class_exists('Launcher')) {
    return;
}

// Hub App-Shell (embed.php) liefert den Programm-Launcher — hier nicht doppelt
if (!empty($_GET['hub_theme'])) {
    return;
}

$hubUrl = function_exists('churchforge_hub_url') ? churchforge_hub_url() : 'https://bkbiel.ch';

// Solo-Logo nur Demo oder echter lokaler Login ohne Hub.
// Auf Hub-Pflicht-Hosts (Prod) immer voller Dock — sticky auth_via=local blockiert nicht.
$useFullDock = Auth::isViaHub();

if (!$useFullDock) {
    $iconSrc = class_exists('ModuleIcon')
        ? ModuleIcon::hubUrl()
        : (rtrim($hubUrl, '/') . '/assets/icons/hub.svg');
    echo '<nav class="cf-launcher cf-launcher--topbar cf-launcher--solo" aria-label="ChurchForge">';
    echo '<a class="cf-launcher-solo-logo" href="' . h($hubUrl . '/') . '" title="GemeindeSchmiede">';
    echo '<img src="' . h($iconSrc) . '" alt="GemeindeSchmiede" width="32" height="32" loading="lazy" decoding="async">';
    echo '</a>';
    echo '</nav>';
    return;
}

// Welche Apps die Person sieht, sagt der Hub (HubDock). Hier werden keine Tags und
// keine Gruppen mehr uebergeben -- das Modul entscheidet darueber nichts mehr
// (Regel Urs 19.10.2026).
Launcher::render('slideforge');
