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

$launcherTags = [];
if (class_exists('SharedAuth')) {
    $cfUser = SharedAuth::read();
    if (is_array($cfUser)) {
        $launcherTags = array_merge(
            is_array($cfUser['tags'] ?? null) ? $cfUser['tags'] : [],
            is_array($cfUser['groups'] ?? null) ? $cfUser['groups'] : []
        );
    }
}

Launcher::render('slideforge', $launcherTags, I18n::currentLang());
