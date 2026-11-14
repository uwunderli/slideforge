<?php
/** @var string $pageTitle */
/** @var string|null $headerPresentationTitle */
/** @var string|null $headerPresentationContext edit|present */
$currentUser = Auth::currentUser();
$themePref = 'dark';
if (class_exists('SharedAuth') && isset($_COOKIE[SharedAuth::THEME_COOKIE])) {
    $themePref = SharedAuth::themePref();
} elseif ($currentUser) {
    $themePref = Auth::themePref($currentUser);
    if (class_exists('SharedAuth')) {
        SharedAuth::issueThemeCookie($themePref);
    }
}
$theme = class_exists('SharedAuth')
    ? SharedAuth::resolvedTheme($themePref)
    : (($themePref === 'light') ? 'light' : 'dark');
$siteTitle = Config::siteTitle();
$logoUrl = Config::logoUrl();
$redirectUri = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
$currentLang = I18n::currentLang();
$webBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$webPrefix = $webBase === '' ? '' : $webBase;
$pwaIcon = $webPrefix . '/assets/pwa/icon-180.png';
$faviconSvg = $webPrefix . '/assets/icons/slides.svg';
$manifestUrl = $webPrefix . '/manifest.php';
?>
<!DOCTYPE html>
<html lang="<?= h($currentLang) ?>" data-theme-pref="<?= h($themePref) ?>" data-theme="<?= h($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?= $theme === 'light' ? '#f4efe6' : '#428BBE' ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= h(Config::siteTitle()) ?>">
<link rel="manifest" href="<?= h($manifestUrl) ?>">
<link rel="apple-touch-icon" href="<?= h($pwaIcon) ?>">
<title><?= h($pageTitle ?? $siteTitle) ?> · <?= h($siteTitle) ?></title>
<?php if ($logoUrl): ?>
<link rel="icon" href="<?= h($logoUrl) ?>">
<?php else: ?>
<link rel="icon" href="<?= h($faviconSvg) ?>?v=<?= ASSET_VERSION ?>" type="image/svg+xml">
<link rel="icon" type="image/png" sizes="192x192" href="<?= h($webPrefix . '/assets/pwa/icon-192.png') ?>?v=<?= ASSET_VERSION ?>">
<?php endif; ?>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= ASSET_VERSION ?>">
<link rel="stylesheet" href="assets/css/mobile.css?v=<?= ASSET_VERSION ?>">
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
         slideforge-float-dialog.css     Vorgabegroesse der Schwebedialoge
         slideforge-user-menu-bridge.css Alt-Klassen .user-menu-* unseres Markups */ ?>
<link rel="stylesheet" href="assets/css/slideforge-float-dialog.css?v=<?= ASSET_VERSION ?>">
<?php if ($currentUser && class_exists('Launcher')): ?>
<link rel="stylesheet" href="<?= h(churchforge_shared_asset_url('cf-launcher.css')) ?>?v=<?= ASSET_VERSION ?>">
<?php endif; ?>
<?php if ($currentUser): ?>
<link rel="stylesheet" href="assets/css/slideforge-user-menu-bridge.css?v=<?= ASSET_VERSION ?>">
<?php endif; ?>
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
<script src="<?= h(sf_hub_asset('shared/cf-theme.js', 'assets/js/cf-theme.js')) ?>"></script>
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
<script src="assets/js/pwa.js?v=<?= ASSET_VERSION ?>" defer></script>
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
  <?php if (empty($_GET['hub_theme'])): ?>
  <div class="topbar-user">
    <?php require __DIR__ . '/launcher.php'; ?>
    <?php require __DIR__ . '/user_menu.php'; ?>
  </div>
  <?php endif; ?>
</header>
<?php if (empty($_GET['hub_theme'])): ?>
<script src="<?= h(sf_hub_asset('js/hub-user-menu.js')) ?>"></script>
<script>window.HubUserMenu && HubUserMenu.bindAll();</script>
<?php endif; ?>
<?php else: ?>
<div class="anon-lang-switch">
  <?php foreach (I18n::SUPPORTED as $code => $label): ?>
    <a href="lang_toggle.php?lang=<?= h($code) ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? 'login.php') ?>" class="<?= I18n::currentLang() === $code ? 'active' : '' ?>"><?= h($code) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
