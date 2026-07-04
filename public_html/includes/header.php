<?php
/** @var string $pageTitle */
/** @var string|null $headerPresentationTitle */
/** @var string|null $headerPresentationContext edit|present */
$currentUser = Auth::currentUser();
$theme = $currentUser['theme'] ?? 'dark';
$siteTitle = Config::siteTitle();
$logoUrl = Config::logoUrl();
$redirectUri = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
$currentLang = I18n::currentLang();
?>
<!DOCTYPE html>
<html lang="<?= h($currentLang) ?>" data-theme="<?= h($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? $siteTitle) ?> · <?= h($siteTitle) ?></title>
<?php if ($logoUrl): ?>
<link rel="icon" href="<?= h($logoUrl) ?>">
<?php endif; ?>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= ASSET_VERSION ?>">
</head>
<body<?= !empty($bodyClass) ? ' class="' . h($bodyClass) . '"' : '' ?>>
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
  <?php if (!empty($headerPresentationTitle) && !empty($headerPresentationContext)): ?>
  <div class="topbar-context">
    <span class="topbar-context-sep" aria-hidden="true">|</span>
    <span class="topbar-context-label"><?= h($headerPresentationContext === 'present' ? t('header.context_present') : t('header.context_edit')) ?></span>
    <span class="topbar-context-title" title="<?= h($headerPresentationTitle) ?>"><?= h($headerPresentationTitle) ?></span>
  </div>
  <?php endif; ?>
  </div>
  <div class="topbar-user">
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
    <a href="theme_toggle.php?redirect=<?= $redirectUri ?>" class="topbar-icon-btn topbar-theme-btn" title="<?= h($theme === 'light' ? t('nav.theme_to_dark') : t('nav.theme_to_light')) ?>">
      <?php if ($theme === 'light'): ?>
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
