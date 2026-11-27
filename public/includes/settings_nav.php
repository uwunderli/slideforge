<?php
/**
 * Reiter der Einstellungen (3.8.3, Urs 27.11.: «Bei den Einstellungen müsste ich nun aber auch die Einstellungen machen können, die ich
 * zuvor gemacht habe» / «Also Vorlagen definieren. Schriftvorlagen, Dann Schriftarten etc.»). Eine Leiste über templates.php und
 * admin_settings.php, erreichbar über Hub › FolienSchmiede › Modul-Einstellungen (Katalog settingsPath /admin_settings.php) und über
 * die Startseite. Wer was ändern darf, entscheiden die Seiten selbst wie bisher (Admin vs. alle Angemeldeten).
 *
 * 3.9.3 (Urs 27.11.: «Mache hier noch die Unterpunkte in die Moduleinstellungen»): Die Liste meldet `SettingsNav` dem Hub, die Hub-
 * Seitenleiste zeigt sie als Unterpunkte. **Im Hub-Rahmen** blendet sich die Leiste hier aus (Klasse `sf-im-hub`, vor dem Zeichnen
 * gesetzt), ausserhalb bleibt sie. Überschrift = Name des Unterpunkts.
 *
 * Erwartet: $sfSetActive (Reiter-Kennung), $sfSetIsAdmin (bool).
 */
try {
    SettingsNav::syncIfChanged(); // nur bei geänderter Liste ein Aufruf an den Hub
} catch (Throwable) {
    // die Seite hängt nicht an der Meldung
}
$sfSetTabs = SettingsNav::tabs(); // eine Quelle, auch für die Hub-Seitenleiste (3.9.3)
?>
<script>try { if (window.parent !== window && window.parent === window.top) document.documentElement.classList.add('sf-im-hub'); } catch (e) { /* fremder Rahmen */ }</script>
<a href="index.php" class="back-link sf-set-back">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
<h1 style="margin-top:14px;"><?= h(SettingsNav::label((string)($sfSetActive ?? ''))) ?></h1>
<nav class="hub-list-tabs sf-set-tabs" aria-label="<?= h(t('admin.heading')) ?>">
  <?php foreach ($sfSetTabs as $sfKey => ['href' => $sfHref, 'label' => $sfLabel, 'admin' => $sfAdminOnly]): ?>
    <?php if ($sfAdminOnly && empty($sfSetIsAdmin)) continue; ?>
    <a href="<?= h($sfHref) ?>" class="hub-list-tab<?= ($sfSetActive ?? '') === $sfKey ? ' is-active' : '' ?>"<?= ($sfSetActive ?? '') === $sfKey ? ' aria-current="page"' : '' ?>><?= h($sfLabel) ?></a>
  <?php endforeach; ?>
</nav>
