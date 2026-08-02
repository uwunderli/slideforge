<?php
if (!Auth::isLoggedIn() || !class_exists('Launcher')) {
    return;
}

if (!empty($_GET['hub_theme'])) {
    // Hub App-Shell hat bereits den Programm-Launcher
    return;
}

$hubUrl = function_exists('churchforge_hub_url') ? churchforge_hub_url() : 'https://bkbiel.ch';

if (!Auth::isViaHub()) {
    // Lokaler / Demo-Login: ein CF-Logo → Hub
    $iconSrc = class_exists('ModuleIcon')
        ? ModuleIcon::hubUrl()
        : (rtrim($hubUrl, '/') . '/assets/icons/hub.svg');
    echo '<nav class="cf-launcher cf-launcher--topbar cf-launcher--solo" aria-label="ChurchForge">';
    echo '<div class="cf-launcher-dock" role="list">';
    echo '<a class="cf-launcher-item" href="' . h($hubUrl . '/') . '" role="listitem" title="GemeindeSchmiede">';
    echo '<span class="cf-launcher-icon cf-icon-image" aria-hidden="true"><img src="' . h($iconSrc) . '" alt="" loading="lazy" decoding="async"></span>';
    echo '<span class="cf-launcher-label">Hub</span>';
    echo '</a>';
    echo '</div></nav>';
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
