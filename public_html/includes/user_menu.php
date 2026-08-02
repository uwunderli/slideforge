<?php
/** @var array $currentUser */
/** @var string $themePref */
/** @var string $currentLang */
/** @var string $redirectUri */

if (empty($currentUser)) {
    return;
}

$username = (string)($currentUser['username'] ?? '');
$displayName = trim((string)($currentUser['display_name'] ?? ''));
if ($displayName === '') {
    $displayName = $username !== '' ? $username : '?';
}
$initialSource = $displayName !== '' ? $displayName : $username;
$initial = $initialSource !== '' ? mb_strtoupper(mb_substr($initialSource, 0, 1)) : '?';

$avatarUrl = '';
$cfSession = (class_exists('SharedAuth') ? SharedAuth::read() : null);
if (is_array($cfSession)) {
    $avatarUrl = trim((string)($cfSession['avatar_url'] ?? ''));
}
if ($avatarUrl === '' && !empty($currentUser['avatar'])) {
    $avatarUrl = 'uploads/avatars/' . (string)$currentUser['avatar'];
}

$returnPath = urldecode($redirectUri);
if ($returnPath === '' || preg_match('#^https?://#i', $returnPath) || str_starts_with($returnPath, '//')) {
    $returnPath = 'index.php';
}
?>
<div class="hub-user-menu user-menu" data-hub-user-menu>
  <button
    type="button"
    class="hub-user-chip user-menu-trigger"
    id="userMenuTrigger"
    data-hub-user-menu-trigger
    aria-expanded="false"
    aria-haspopup="true"
    title="<?= h($displayName) ?>"
  >
    <span class="hub-user-chip__name"><?= h($displayName) ?></span>
    <span class="hub-user-chip__avatar" aria-hidden="true">
      <?php if ($avatarUrl !== ''): ?>
        <img src="<?= h($avatarUrl) ?>" alt="" referrerpolicy="no-referrer">
      <?php else: ?>
        <span class="hub-user-chip__initial"><?= h($initial) ?></span>
      <?php endif; ?>
    </span>
  </button>
  <div class="hub-user-menu__dropdown user-menu-dropdown" id="userMenuDropdown" data-hub-user-menu-dropdown role="menu">
    <div class="hub-user-menu__name user-menu-name"><?= h($displayName) ?></div>

    <form method="post" action="prefs.php" class="hub-user-menu__pref user-menu-pref">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="set_locale">
      <input type="hidden" name="return" value="<?= h($returnPath) ?>">
      <label>
        <span><?= h(t('settings.language')) ?></span>
        <select name="locale" onchange="HTMLFormElement.prototype.submit.call(this.form)">
          <?php foreach (I18n::SUPPORTED as $code => $label): ?>
            <option value="<?= h($code) ?>"<?= $currentLang === $code ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <form method="post" action="prefs.php" class="hub-user-menu__pref user-menu-pref">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="set_theme">
      <input type="hidden" name="return" value="<?= h($returnPath) ?>">
      <label>
        <span><?= h(t('settings.appearance')) ?></span>
        <select name="theme" onchange="window.ChurchForgeTheme ? ChurchForgeTheme.commit(this) : HTMLFormElement.prototype.submit.call(this.form)">
          <?php foreach (['dark', 'light', 'system'] as $th): ?>
            <option value="<?= h($th) ?>"<?= $themePref === $th ? ' selected' : '' ?>><?= h(t('settings.theme.' . $th)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <div class="hub-user-menu__sep user-menu-sep"></div>
    <a href="templates.php" role="menuitem"><?= h(t('nav.templates')) ?></a>
    <a href="import.php" role="menuitem"><?= h(t('nav.import')) ?></a>
    <div class="hub-user-menu__sep user-menu-sep"></div>
    <a href="profile.php" role="menuitem"><?= h(t('nav.profile')) ?></a>
    <?php if (Auth::isAdmin()): ?>
      <a href="admin_settings.php" role="menuitem"><?= h(t('nav.settings')) ?></a>
    <?php endif; ?>
    <a href="logout.php" role="menuitem"><?= h(t('nav.logout')) ?></a>
  </div>
</div>
