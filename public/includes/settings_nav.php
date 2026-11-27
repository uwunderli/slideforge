<?php
/**
 * Reiter der Einstellungen (3.8.3, Urs 27.11.: «Bei den Einstellungen müsste ich nun aber auch die Einstellungen machen können, die ich
 * zuvor gemacht habe» / «Also Vorlagen definieren. Schriftvorlagen, Dann Schriftarten etc.»). Eine Leiste über templates.php und
 * admin_settings.php, erreichbar über Hub › FolienSchmiede › Modul-Einstellungen (Katalog settingsPath /admin_settings.php) und über
 * die Startseite. Wer was ändern darf, entscheiden die Seiten selbst wie bisher (Admin vs. alle Angemeldeten).
 *
 * Erwartet: $sfSetActive (Reiter-Kennung), $sfSetIsAdmin (bool).
 */
$sfSetTabs = [
    'slides' => ['templates.php?tab=slides', t('tpl.tab_slides'), false],
    'texts' => ['templates.php?tab=texts', t('tpl.tab_texts'), false],
    'vorlageelemente' => ['templates.php?tab=vorlageelemente', t('tpl.tab_vorlageelemente'), false],
    'fonts' => ['templates.php?tab=fonts', t('tpl.tab_fonts'), false],
    'colors' => ['templates.php?tab=colors', t('tpl.tab_colors'), false],
    'general' => ['admin_settings.php?tab=general', t('admin.tab_general'), true],
    'spellcheck' => ['admin_settings.php?tab=spellcheck', t('admin.tab_spellcheck'), true],
    'media' => ['admin_settings.php?tab=media', t('admin.tab_media'), true],
];
?>
<a href="index.php" class="back-link">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
<h1 style="margin-top:14px;"><?= h(t('admin.heading')) ?></h1>
<nav class="hub-list-tabs sf-set-tabs" aria-label="<?= h(t('admin.heading')) ?>">
  <?php foreach ($sfSetTabs as $sfKey => [$sfHref, $sfLabel, $sfAdminOnly]): ?>
    <?php if ($sfAdminOnly && empty($sfSetIsAdmin)) continue; ?>
    <a href="<?= h($sfHref) ?>" class="hub-list-tab<?= ($sfSetActive ?? '') === $sfKey ? ' is-active' : '' ?>"<?= ($sfSetActive ?? '') === $sfKey ? ' aria-current="page"' : '' ?>><?= h($sfLabel) ?></a>
  <?php endforeach; ?>
</nav>
