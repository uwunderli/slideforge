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
<?php if ($currentUser && class_exists('Launcher')): ?>
<link rel="stylesheet" href="<?= h(churchforge_shared_asset_url('cf-launcher.css')) ?>?v=<?= ASSET_VERSION ?>">
<?php endif; ?>
<script src="assets/js/cf-theme.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/mobile-detect.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/modal-backdrop.js?v=<?= ASSET_VERSION ?>"></script>
<script>
window.SF_DIALOG_I18N = <?= json_encode([
    'ok' => t('common.ok'),
    'cancel' => t('common.cancel'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/ui-dialog.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/number-input-wheel.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/pwa.js?v=<?= ASSET_VERSION ?>" defer></script>
</head>
<body<?= !empty($bodyClass) ? ' class="' . h($bodyClass) . '"' : '' ?><?= ($currentUser && Mobile::isMobileClient()) ? ' data-sf-mobile-server="1"' : '' ?><?= client_is_touch_tablet() ? ' data-sf-tablet="1"' : '' ?>>
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
<?php if ($currentUser): ?>
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
  <div class="topbar-user">
    <?php require __DIR__ . '/launcher.php'; ?>
    <div class="topbar-lang-menu">
      <button type="button" class="topbar-icon-btn topbar-lang-trigger" id="langMenuTrigger" aria-expanded="false" aria-haspopup="true" title="<?= h(t('nav.language')) ?>">
        <?= I18n::flagSvg($currentLang) ?>
      </button>
      <div class="topbar-lang-dropdown" id="langMenuDropdown" hidden>
        <?php foreach (I18n::SUPPORTED as $code => $label): ?>
        <a href="lang_toggle.php?lang=<?= h($code) ?>&redirect=<?= $redirectUri ?>" class="topbar-lang-option<?= $currentLang === $code ? ' active' : '' ?>">
          <?= I18n::flagSvg($code) ?>
          <span><?= h($label) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <a href="theme_toggle.php?redirect=<?= $redirectUri ?>" class="topbar-icon-btn topbar-theme-btn" title="<?= h(
      $themePref === 'system' ? t('nav.theme_system') : ($theme === 'light' ? t('nav.theme_to_dark') : t('nav.theme_to_light'))
    ) ?>">
      <?php if ($themePref === 'system'): ?>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      <?php elseif ($theme === 'light'): ?>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <?php else: ?>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      <?php endif; ?>
    </a>
    <div class="user-menu">
      <button type="button" class="user-menu-trigger" id="userMenuTrigger" title="<?= h($currentUser['username']) ?>">
        <?php if (!empty($currentUser['avatar'])): ?>
          <img src="uploads/avatars/<?= h($currentUser['avatar']) ?>" alt="">
        <?php else: ?>
          <span><?= h(mb_strtoupper(mb_substr($currentUser['username'], 0, 1))) ?></span>
        <?php endif; ?>
      </button>
      <div class="user-menu-dropdown" id="userMenuDropdown">
        <div class="user-menu-name"><?= h($currentUser['username']) ?></div>
        <a href="templates.php"><?= h(t('nav.templates')) ?></a>
        <a href="import.php"><?= h(t('nav.import')) ?></a>
        <div class="user-menu-sep"></div>
        <a href="profile.php"><?= h(t('nav.profile')) ?></a>
        <?php if (Auth::isAdmin()): ?>
          <a href="admin_settings.php"><?= h(t('nav.settings')) ?></a>
        <?php endif; ?>
        <a href="logout.php"><?= h(t('nav.logout')) ?></a>
      </div>
    </div>
  </div>
</header>
<script>
(function () {
  const userTrigger = document.getElementById('userMenuTrigger');
  const userDropdown = document.getElementById('userMenuDropdown');
  const langTrigger = document.getElementById('langMenuTrigger');
  const langDropdown = document.getElementById('langMenuDropdown');

  function closeAllTopMenus() {
    userDropdown?.classList.remove('open');
    if (langDropdown) langDropdown.hidden = true;
    langTrigger?.setAttribute('aria-expanded', 'false');
  }

  if (userTrigger && userDropdown) {
    userTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = !userDropdown.classList.contains('open');
      closeAllTopMenus();
      if (willOpen) userDropdown.classList.add('open');
    });
  }

  if (langTrigger && langDropdown) {
    langTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = langDropdown.hidden;
      closeAllTopMenus();
      if (willOpen) {
        langDropdown.hidden = false;
        langTrigger.setAttribute('aria-expanded', 'true');
      }
    });
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('.topbar-lang-menu') || e.target.closest('.user-menu')) return;
    closeAllTopMenus();
  });
})();
</script>
<?php else: ?>
<div class="anon-lang-switch">
  <?php foreach (I18n::SUPPORTED as $code => $label): ?>
    <a href="lang_toggle.php?lang=<?= h($code) ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? 'login.php') ?>" class="<?= I18n::currentLang() === $code ? 'active' : '' ?>"><?= h($code) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
