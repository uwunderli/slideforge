<?php
/** @var string $pageTitle */
/** @var string|null $headerPresentationTitle */
/** @var string|null $headerPresentationContext edit|present */
$currentUser = Auth::currentUser();
// Darstellung folgt dem Hub (Cookie des Hubs), keine eigene Wahl mehr (Hub-Pfad, 27.11.2026).
$themePref = class_exists('SharedAuth') ? SharedAuth::themePref() : 'dark';
$theme = class_exists('SharedAuth') ? SharedAuth::resolvedTheme($themePref) : 'dark';
$siteTitle = Config::siteTitle();
$logoUrl = Config::logoUrl();
$currentLang = I18n::currentLang();
$webBase = sf_base();
$webPrefix = $webBase === '' ? '' : $webBase;
$faviconSvg = $webPrefix . '/assets/icons/slides.svg';
?>
<!DOCTYPE html>
<html lang="<?= h($currentLang) ?>"<?= sf_theme_attrs($themePref, $theme) ?>>
<head>
<meta charset="UTF-8">
<?= sf_theme_boot() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?= $theme === 'light' ? '#f6f3ec' : '#171b1f' ?>">
<title><?= h($pageTitle ?? $siteTitle) ?> · <?= h($siteTitle) ?></title>
<?php if ($logoUrl): ?>
<link rel="icon" href="<?= h($logoUrl) ?>">
<?php else: ?>
<link rel="icon" href="<?= h($faviconSvg) ?>?v=<?= ASSET_VERSION ?>" type="image/svg+xml">
<?php endif; ?>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="assets/css/mobile.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="assets/css/sf-tokens.css?v=<?= ASSET_VERSION ?>"><?php /* Farbton aus dem Hub-Design der Person (cf-theme.js → data-dialog-accent) */ ?>
<?php /* Hub-Bausteine: EINE Datei statt sieben mitgefuehrter Kopien (hub/docs/DESIGN.md).
         Mit Hub-Anbindung kommt sie vom Hub, ohne Anbindung aus der byte-gleichen Kopie
         unter assets/vendor/hub/css/ - beides loest hub_asset() selbst auf (Hub-Fix
         880725a9: Rueckfall ueber SLIDEFORGE_ROOT bzw. DOCUMENT_ROOT). Reihenfolge wie
         bisher: NACH style.css, damit die Hub-Regeln gewinnen. */ ?>
<?php /* function_exists nur als Schutz gegen eine fehlende Bibliothek: ohne
         shared/bootstrap.php gaebe ein nackter Aufruf einen Fatal Error auf JEDER
         Seite statt nur fehlendem Stil. Keine Standort-Logik - die macht hub_asset(). */ ?>
<link rel="stylesheet" href="<?= h(function_exists('hub_asset') ? hub_asset('css/hub-kit.css') : 'assets/vendor/hub/css/hub-kit.css?v=' . rawurlencode((string)ASSET_VERSION)) ?>">
<?php /* Eigene Abweichungen, bewusst NACH dem Kit und bewusst keine Hub-Kopien:
         slideforge-float-dialog.css     Vorgabegroesse der Schwebedialoge */ ?>
<link rel="stylesheet" href="assets/css/slideforge-float-dialog.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="assets/css/sf-ff.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="assets/css/hub-ribbon.css?v=<?= ASSET_VERSION ?>"><?php /* Menüband-Stil, Kopie aus FileForge (Hub style.css), wie Dossier/FileForge */ ?>
<?php /* Schlichter Hub-Stil (Vorgabe 17.10., _berichte/hub/vorgabe-schlichtes-design-apps):
         ZULETZT geladen - nach den eigenen Stilen UND nach dem Kit. Sie setzt nur Groesse,
         Gewicht, Rundung, Rahmen und Luft neu, keine Farben und kein Verhalten. Wer sie
         davor laedt, sieht klotzig aus; das war der Fehler im Lab.
         Kommt bewusst vom Hub und wird NICHT mitgefuehrt (eine Quelle, hub/docs/DESIGN.md).
         Ohne shared/bootstrap.php gibt es die Funktion nicht - dann bleibt die Seite
         schlicht ungeschminkt statt mit einem Fatal Error stehen. */ ?>
<?php if (function_exists('hub_asset')): ?>
<link rel="stylesheet" href="<?= h(hub_asset('shared/cf-fine.css')) ?>">
<?php endif; ?>
<?php /* cf-theme.js vom Hub wie FileForge (3.5.1): sf_hub_asset() nahm früher die eigene, veraltete Kopie zuerst; deren Stand kannte
         Fenster-Stil/Akzent nicht, die Hub-Nachricht (auch freie Farbe #rrggbb) blieb wirkungslos → Sand. */ ?>
<script src="<?= h(sf_hub_asset('shared/cf-theme.js')) ?>"></script>
<script src="assets/js/mobile-detect.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/modal-backdrop.js?v=<?= ASSET_VERSION ?>"></script>
<script src="<?= h(sf_hub_asset('js/hub-page-busy.js')) ?>"></script>
<script>
window.SF_DIALOG_I18N = <?= json_encode([
    'ok' => t('common.ok'),
    'cancel' => t('common.cancel'),
], JSON_UNESCAPED_UNICODE) ?>;
window.HUB_FLOAT_DIALOG_I18N = <?= json_encode([
    'minimize' => t('dialog.minimize'),
    'maximize' => t('dialog.maximize'),
    'restore' => t('dialog.restore'),
    'close' => t('dialog.close'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= h(sf_hub_asset('js/hub-float-dialog.js')) ?>"></script>
<script src="assets/js/sf-float-bridge.js?v=<?= ASSET_VERSION ?>"></script>
<script src="<?= h(sf_hub_asset('js/hub-confirm.js')) ?>"></script>
<script src="<?= h(sf_hub_asset('js/hub-secure-delete.js')) ?>"></script>
<script src="<?= h(sf_hub_asset('shared/cf-status.js')) ?>"></script>
<script src="assets/js/ui-dialog.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/sf-hub-boot.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/number-input-wheel.js?v=<?= ASSET_VERSION ?>"></script>
</head>
<body<?= !empty($bodyClass) ? ' class="' . h($bodyClass) . '"' : '' ?><?= ($currentUser && Mobile::isMobileClient()) ? ' data-sf-mobile-server="1"' : '' ?><?= client_is_touch_tablet() ? ' data-sf-tablet="1"' : '' ?><?= !empty($_GET['hub_theme']) ? ' data-sf-hub-embedded="1"' : '' ?>>
<div id="cf-status-host" class="cf-status-host" aria-live="polite"></div>
<?php if (Config::demoMode()): ?>
<div class="demo-banner" role="status">
  <div><?= h(t('demo.banner')) ?></div>
  <div class="demo-banner-meta">
    <?= h(t('demo.next_reset')) ?>:
    <span id="demoResetCountdown" data-reset-at="<?= (int)Demo::nextResetAt() ?>">--:--:--</span>
  </div>
</div>
<script src="assets/js/demo.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<?php if ($currentUser && !empty($hideTopbar)): ?>
<?php /* Editor: keine Kopfleiste, mehr Platz fuer die Arbeitsflaeche (Tablet) */ ?>
<?php elseif ($currentUser): ?>
<header class="topbar">
  <div class="topbar-main">
  <a href="index.php" class="brand">
    <?php if ($logoUrl): ?>
      <img src="<?= h($logoUrl) ?>" alt="<?= h($siteTitle) ?>" class="brand-logo">
    <?php else: ?>
      <span class="dot"></span>
    <?php endif; ?>
    <?= h($siteTitle) ?>
  </a>
  <?php if (!empty($headerPresentationTitle)): ?>
  <div class="topbar-context">
    <span class="topbar-context-sep" aria-hidden="true">|</span>
    <?php if (!empty($headerPresentationContext)): ?>
    <span class="topbar-context-label"><?= h($headerPresentationContext === 'present' ? t('header.context_present') : t('header.context_edit')) ?></span>
    <?php endif; ?>
    <span class="topbar-context-title" title="<?= h($headerPresentationTitle) ?>"><?= h($headerPresentationTitle) ?></span>
  </div>
  <?php endif; ?>
  </div>
</header>
<?php endif; ?>
