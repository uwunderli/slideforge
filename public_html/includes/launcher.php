<?php
if (!Auth::isLoggedIn() || !class_exists('Launcher')) {
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
