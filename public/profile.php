<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$profileMsg = '';
$profileError = '';

$allowedTabs = ['spellcheck', 'logos'];
$activeTab = $_GET['tab'] ?? 'spellcheck';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'spellcheck';
}

$tabFromAction = [
    'save_spellcheck' => 'spellcheck',
    'save_logos_importer' => 'logos',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if (isset($tabFromAction[$action])) {
        $activeTab = $tabFromAction[$action];
    }

    if ($action === 'save_spellcheck') {
        Auth::setSpellcheckPrefs(
            $me['id'],
            isset($_POST['spellcheck_browser']),
            trim($_POST['spellcheck_lang'] ?? ''),
            isset($_POST['spellcheck_before_present'])
        );
        $me = Auth::currentUser();
        $profileMsg = t('profile.spellcheck_saved');
    }

    if ($action === 'save_logos_importer') {
        Auth::setLogosImporterEnabled($me['id'], isset($_POST['logos_importer_enabled']));
        $me = Auth::currentUser();
        $profileMsg = t('profile.logos_importer_saved');
    }
}

$pageTitle = t('profile.title');
require __DIR__ . '/includes/header.php';
?>
<div class="container profile-page">
  <a href="index.php" class="back-link">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('profile.title')) ?></h1>
  <p class="profile-role-line">
    <?= h(t('profile.role')) ?>: <span class="perm-tag <?= $me['role'] === 'admin' ? 'edit' : 'view' ?>"><?= $me['role'] === 'admin' ? h(t('profile.role_admin')) : h(t('profile.role_editor')) ?></span>
  </p>

  <div class="page-tabs profile-tabs">
    <a href="?tab=spellcheck" class="page-tab-btn<?= $activeTab === 'spellcheck' ? ' active' : '' ?>"><?= h(t('profile.tab_spellcheck')) ?></a>
    <a href="?tab=logos" class="page-tab-btn<?= $activeTab === 'logos' ? ' active' : '' ?>"><?= h(t('profile.tab_logos')) ?></a>
  </div>

  <div class="profile-tab-panel"<?= $activeTab !== 'spellcheck' ? ' hidden' : '' ?> data-profile-tab="spellcheck">
    <?php if ($activeTab === 'spellcheck' && $profileMsg): ?><div class="alert alert-success"><?= h($profileMsg) ?></div><?php endif; ?>
    <div class="section-header"><h2><?= h(t('profile.spellcheck_heading')) ?></h2></div>
    <p class="profile-intro"><?= t('profile.spellcheck_intro') ?></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_spellcheck">
      <label class="profile-check-row">
        <input type="checkbox" name="spellcheck_browser" <?= Auth::spellcheckBrowserEnabled($me) ? 'checked' : '' ?>>
        <?= h(t('profile.spellcheck_browser')) ?>
      </label>
      <label class="profile-check-row">
        <input type="checkbox" name="spellcheck_before_present" <?= Auth::spellcheckBeforePresent($me) ? 'checked' : '' ?>>
        <?= h(t('profile.spellcheck_before_present')) ?>
      </label>
      <label for="spellcheck_lang"><?= h(t('profile.spellcheck_language')) ?></label>
      <select id="spellcheck_lang" name="spellcheck_lang">
        <option value="" <?= ($me['spellcheck_lang'] ?? '') === '' ? 'selected' : '' ?>><?= h(t('profile.spellcheck_lang_auto')) ?></option>
        <?php foreach (Auth::SPELLCHECK_LANGUAGES as $code => $label): ?>
          <option value="<?= h($code) ?>" <?= ($me['spellcheck_lang'] ?? '') === $code ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="button button-sm hub-btn hub-btn--primary hub-btn--sm" style="margin-top:14px;"><?= h(t('common.save')) ?></button>
    </form>
  </div>

  <div class="profile-tab-panel"<?= $activeTab !== 'logos' ? ' hidden' : '' ?> data-profile-tab="logos">
    <?php if ($activeTab === 'logos' && $profileMsg): ?><div class="alert alert-success"><?= h($profileMsg) ?></div><?php endif; ?>
    <div class="section-header"><h2><?= h(t('profile.logos_importer_heading')) ?></h2></div>
    <form method="post" style="margin-bottom:24px;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_logos_importer">
      <label class="profile-check-row">
        <input type="checkbox" name="logos_importer_enabled" <?= Auth::logosImporterEnabled($me) ? 'checked' : '' ?>>
        <?= h(t('profile.logos_importer_enable')) ?>
      </label>
      <p class="profile-intro" style="margin-top:10px;"><?= h(t('profile.logos_importer_enable_desc')) ?></p>
      <button type="submit" class="button button-sm hub-btn hub-btn--primary hub-btn--sm" style="margin-top:14px;"><?= h(t('common.save')) ?></button>
    </form>
    <div class="profile-logos-help">
      <?= t('profile.logos_importer_help') ?>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
