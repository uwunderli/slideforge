<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$profileMsg = '';
$profileError = '';
$passwordMsg = '';
$passwordError = '';

$allowedTabs = ['account', 'spellcheck', 'webdav', 'logos', 'password'];
$activeTab = $_GET['tab'] ?? 'account';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'account';
}

$tabFromAction = [
    'update_profile' => 'account',
    'upload_avatar' => 'account',
    'remove_avatar' => 'account',
    'save_spellcheck' => 'spellcheck',
    'save_webdav_drive' => 'webdav',
    'delete_webdav_drive' => 'webdav',
    'save_logos_importer' => 'logos',
    'change_password' => 'password',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if (isset($tabFromAction[$action])) {
        $activeTab = $tabFromAction[$action];
    }

    if ($action === 'update_profile') {
        [$ok, $msg] = Auth::updateProfile($me['id'], $_POST['username'] ?? '', $_POST['email'] ?? '');
        if ($ok) { $profileMsg = $msg; $me = Auth::currentUser(); } else { $profileError = $msg; }
    }

    if ($action === 'upload_avatar') {
        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $profileError = t('profile.no_file');
        } else {
            $file = $_FILES['avatar'];
            if ($file['size'] > 3 * 1024 * 1024) {
                $profileError = t('profile.file_too_big');
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                if (!isset($extMap[$mime])) {
                    $profileError = t('profile.invalid_format');
                } else {
                    foreach (glob(PUBLIC_UPLOADS_PATH . '/avatars/' . $me['id'] . '.*') as $old) {
                        @unlink($old);
                    }
                    $filename = $me['id'] . '.' . $extMap[$mime];
                    move_uploaded_file($file['tmp_name'], PUBLIC_UPLOADS_PATH . '/avatars/' . $filename);
                    Auth::setAvatar($me['id'], $filename);
                    $me = Auth::currentUser();
                    $profileMsg = t('profile.avatar_updated');
                }
            }
        }
    }

    if ($action === 'remove_avatar') {
        foreach (glob(PUBLIC_UPLOADS_PATH . '/avatars/' . $me['id'] . '.*') as $old) {
            @unlink($old);
        }
        Auth::setAvatar($me['id'], null);
        $me = Auth::currentUser();
        $profileMsg = t('profile.avatar_removed');
    }

    if ($action === 'change_password') {
        if (($_POST['new_password'] ?? '') !== ($_POST['new_password2'] ?? '')) {
            $passwordError = t('profile.passwords_mismatch');
        } else {
            [$ok, $msg] = Auth::changePassword($me['id'], $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
            if ($ok) { $passwordMsg = $msg; } else { $passwordError = $msg; }
        }
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

    if ($action === 'save_webdav_drive') {
        [$ok, $msgKey] = (function () use ($me) {
            $result = Auth::saveWebdavDrive($me['id'], [
                'id' => trim($_POST['drive_id'] ?? ''),
                'label' => $_POST['drive_label'] ?? '',
                'url' => $_POST['drive_url'] ?? '',
                'username' => $_POST['drive_username'] ?? '',
                'password' => $_POST['drive_password'] ?? '',
                'root_path' => $_POST['drive_root_path'] ?? '',
            ]);
            if ($result['ok']) {
                return [true, t('profile.webdav_saved')];
            }
            $map = [
                'invalid_label' => t('webdav.error_invalid_label'),
                'invalid_url' => t('webdav.error_invalid_url'),
                'https_required' => t('webdav.error_https_required'),
                'invalid_username' => t('webdav.error_invalid_username'),
                'password_required' => t('webdav.error_password_required'),
                'save_failed' => t('webdav.error_save'),
            ];
            return [false, $map[$result['error'] ?? ''] ?? t('webdav.error_generic')];
        })();
        if ($ok) { $profileMsg = $msgKey; $me = Auth::currentUser(); } else { $profileError = $msgKey; }
    }

    if ($action === 'delete_webdav_drive') {
        $driveId = trim($_POST['drive_id'] ?? '');
        $result = Auth::deleteWebdavDrive($me['id'], $driveId);
        if ($result['ok']) {
            $me = Auth::currentUser();
            $profileMsg = t('profile.webdav_deleted');
        } else {
            $profileError = t('webdav.error_drive_not_found');
        }
    }

    if ($action === 'save_logos_importer') {
        Auth::setLogosImporterEnabled($me['id'], isset($_POST['logos_importer_enabled']));
        $me = Auth::currentUser();
        $profileMsg = t('profile.logos_importer_saved');
    }
}

$webdavDrives = Auth::listWebdavDrivesPublic($me);

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
    <a href="?tab=account" class="page-tab-btn<?= $activeTab === 'account' ? ' active' : '' ?>"><?= h(t('profile.tab_account')) ?></a>
    <a href="?tab=spellcheck" class="page-tab-btn<?= $activeTab === 'spellcheck' ? ' active' : '' ?>"><?= h(t('profile.tab_spellcheck')) ?></a>
    <a href="?tab=webdav" class="page-tab-btn<?= $activeTab === 'webdav' ? ' active' : '' ?>"><?= h(t('profile.tab_webdav')) ?></a>
    <a href="?tab=logos" class="page-tab-btn<?= $activeTab === 'logos' ? ' active' : '' ?>"><?= h(t('profile.tab_logos')) ?></a>
    <a href="?tab=password" class="page-tab-btn<?= $activeTab === 'password' ? ' active' : '' ?>"><?= h(t('profile.tab_password')) ?></a>
  </div>

  <div class="profile-tab-panel"<?= $activeTab !== 'account' ? ' hidden' : '' ?> data-profile-tab="account">
    <?php if ($activeTab === 'account' && $profileError): ?><div class="alert alert-error"><?= h($profileError) ?></div><?php endif; ?>
    <?php if ($activeTab === 'account' && $profileMsg): ?><div class="alert alert-success"><?= h($profileMsg) ?></div><?php endif; ?>

    <div class="section-header"><h2><?= h(t('profile.avatar_heading')) ?></h2></div>
    <div class="profile-avatar-row">
      <?php if (!empty($me['avatar'])): ?>
        <img src="uploads/avatars/<?= h($me['avatar']) ?>" alt="" class="profile-avatar-img">
      <?php else: ?>
        <div class="profile-avatar-fallback"><?= h(mb_strtoupper(mb_substr($me['username'], 0, 1))) ?></div>
      <?php endif; ?>
      <div class="profile-avatar-actions">
        <form method="post" enctype="multipart/form-data" class="profile-avatar-upload">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="upload_avatar">
          <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" required>
          <button type="submit" class="button button-sm"><?= h(t('profile.upload')) ?></button>
        </form>
        <?php if (!empty($me['avatar'])): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="button button-ghost button-sm"><?= h(t('profile.remove_avatar')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="section-header"><h2><?= h(t('profile.account_data')) ?></h2></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="update_profile">
      <label for="username"><?= h(t('profile.username')) ?></label>
      <input type="text" id="username" name="username" value="<?= h($me['username']) ?>" required>
      <label for="email"><?= h(t('profile.email')) ?></label>
      <input type="email" id="email" name="email" value="<?= h($me['email']) ?>" required>
      <button type="submit" class="button" style="margin-top:16px;"><?= h(t('common.save')) ?></button>
    </form>
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
      <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('common.save')) ?></button>
    </form>
  </div>

  <div class="profile-tab-panel"<?= $activeTab !== 'webdav' ? ' hidden' : '' ?> data-profile-tab="webdav">
    <?php if ($activeTab === 'webdav' && $profileError): ?><div class="alert alert-error"><?= h($profileError) ?></div><?php endif; ?>
    <?php if ($activeTab === 'webdav' && $profileMsg): ?><div class="alert alert-success"><?= h($profileMsg) ?></div><?php endif; ?>
    <div class="profile-section-heading">
      <div class="section-header"><h2><?= h(t('profile.webdav_heading')) ?></h2></div>
      <button type="button" class="admin-help-btn" id="webdavHelpBtn" aria-label="<?= h(t('profile.webdav_help_btn')) ?>" title="<?= h(t('profile.webdav_help_btn')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </button>
    </div>
    <p class="profile-intro"><?= t('profile.webdav_intro') ?></p>

    <?php if ($webdavDrives): ?>
      <div class="webdav-profile-list">
        <?php foreach ($webdavDrives as $drive): ?>
          <details class="webdav-profile-item">
            <summary><strong><?= h($drive['label']) ?></strong> — <?= h($drive['url']) ?></summary>
            <form method="post" class="webdav-profile-form">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="save_webdav_drive">
              <input type="hidden" name="drive_id" value="<?= h($drive['id']) ?>">
              <label><?= h(t('profile.webdav_label')) ?></label>
              <input type="text" name="drive_label" value="<?= h($drive['label']) ?>" required maxlength="80">
              <label><?= h(t('profile.webdav_url')) ?></label>
              <p class="form-hint"><?= h(t('profile.webdav_url_hint')) ?></p>
              <input type="text" name="drive_url" value="<?= h($drive['url']) ?>" required placeholder="https://go.example.com/" inputmode="url" spellcheck="false">
              <label><?= h(t('profile.webdav_username')) ?></label>
              <input type="text" name="drive_username" value="<?= h($drive['username']) ?>" required autocomplete="username">
              <label><?= h(t('profile.webdav_password')) ?></label>
              <input type="password" name="drive_password" autocomplete="new-password" placeholder="<?= h(t('profile.webdav_password_keep')) ?>">
              <label><?= h(t('profile.webdav_root')) ?></label>
              <input type="text" name="drive_root_path" value="<?= h($drive['root_path']) ?>" placeholder="<?= h(t('profile.webdav_root_placeholder')) ?>">
              <div class="profile-form-actions">
                <button type="submit" class="button button-sm"><?= h(t('common.save')) ?></button>
                <button type="button" class="button button-ghost button-sm webdav-test-btn" data-drive-id="<?= h($drive['id']) ?>"><?= h(t('profile.webdav_test')) ?></button>
              </div>
            </form>
            <form method="post" class="profile-inline-form" onsubmit="return confirm(<?= json_encode(t('profile.webdav_delete_confirm')) ?>);">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete_webdav_drive">
              <input type="hidden" name="drive_id" value="<?= h($drive['id']) ?>">
              <button type="submit" class="button button-ghost button-sm"><?= h(t('profile.webdav_delete')) ?></button>
            </form>
            <p class="webdav-test-result" hidden></p>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (count($webdavDrives) < 10): ?>
    <form method="post" class="webdav-profile-form profile-add-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_webdav_drive">
      <div class="options-title"><?= h(t('profile.webdav_add')) ?></div>
      <label><?= h(t('profile.webdav_label')) ?></label>
      <input type="text" name="drive_label" required maxlength="80" placeholder="<?= h(t('profile.webdav_label_placeholder')) ?>">
      <label><?= h(t('profile.webdav_url')) ?></label>
      <p class="form-hint"><?= h(t('profile.webdav_url_hint')) ?></p>
      <input type="text" name="drive_url" required placeholder="https://go.example.com/" inputmode="url" spellcheck="false">
      <label><?= h(t('profile.webdav_username')) ?></label>
      <input type="text" name="drive_username" required autocomplete="username">
      <label><?= h(t('profile.webdav_password')) ?></label>
      <input type="password" name="drive_password" required autocomplete="new-password">
      <label><?= h(t('profile.webdav_root')) ?></label>
      <input type="text" name="drive_root_path" placeholder="<?= h(t('profile.webdav_root_placeholder')) ?>">
      <div class="profile-form-actions">
        <button type="submit" class="button button-sm"><?= h(t('profile.webdav_add_btn')) ?></button>
        <button type="button" class="button button-ghost button-sm" id="webdavTestNewBtn"><?= h(t('profile.webdav_test')) ?></button>
      </div>
      <p class="webdav-test-result" id="webdavTestNewResult" hidden></p>
    </form>
    <?php endif; ?>
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
      <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('common.save')) ?></button>
    </form>
    <div class="profile-logos-help">
      <?= t('profile.logos_importer_help') ?>
    </div>
  </div>

  <div class="profile-tab-panel"<?= $activeTab !== 'password' ? ' hidden' : '' ?> data-profile-tab="password">
    <?php if ($activeTab === 'password' && $passwordError): ?><div class="alert alert-error"><?= h($passwordError) ?></div><?php endif; ?>
    <?php if ($activeTab === 'password' && $passwordMsg): ?><div class="alert alert-success"><?= h($passwordMsg) ?></div><?php endif; ?>
    <div class="section-header"><h2><?= h(t('profile.change_password')) ?></h2></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="change_password">
      <label for="current_password"><?= h(t('profile.current_password')) ?></label>
      <input type="password" id="current_password" name="current_password" required>
      <label for="new_password"><?= h(t('profile.new_password')) ?></label>
      <input type="password" id="new_password" name="new_password" required minlength="6">
      <label for="new_password2"><?= h(t('profile.new_password_repeat')) ?></label>
      <input type="password" id="new_password2" name="new_password2" required minlength="6">
      <button type="submit" class="button" style="margin-top:16px;"><?= h(t('profile.change_password')) ?></button>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="webdavHelpModal" aria-hidden="true">
  <div class="modal admin-help-modal" role="dialog" aria-modal="true" aria-labelledby="webdavHelpTitle">
    <div class="admin-help-modal-header">
      <h2 id="webdavHelpTitle" style="font-size:1.15rem; text-transform:none; margin:0;"><?= h(t('profile.webdav_help_title')) ?></h2>
      <button type="button" class="button button-ghost button-sm" id="webdavHelpClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    </div>
    <div class="admin-help-modal-body">
      <?= t('profile.webdav_help_body') ?>
    </div>
    <div class="modal-actions">
      <button type="button" class="button" id="webdavHelpOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<script>
(function () {
  const csrf = <?= json_encode(csrf_token()) ?>;
  const okMsg = <?= json_encode(t('profile.webdav_test_ok')) ?>;
  const failPrefix = <?= json_encode(t('profile.webdav_test_fail')) ?>;

  async function testDrive(payload, resultEl) {
    if (!resultEl) return;
    resultEl.hidden = false;
    resultEl.textContent = '…';
    resultEl.className = 'webdav-test-result';
    try {
      const res = await fetch('user_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action: 'test_webdav_drive', csrf_token: csrf }, payload)),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || failPrefix);
      resultEl.textContent = okMsg;
      resultEl.classList.add('webdav-test-ok');
    } catch (e) {
      resultEl.textContent = failPrefix + (e.message ? ': ' + e.message : '');
      resultEl.classList.add('webdav-test-fail');
    }
  }

  document.querySelectorAll('.webdav-test-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const form = btn.closest('form');
      const resultEl = btn.closest('details')?.querySelector('.webdav-test-result');
      testDrive({
        drive_id: btn.dataset.driveId || '',
        url: form?.querySelector('[name=drive_url]')?.value || '',
        username: form?.querySelector('[name=drive_username]')?.value || '',
        password: form?.querySelector('[name=drive_password]')?.value || '',
        root_path: form?.querySelector('[name=drive_root_path]')?.value || '',
      }, resultEl);
    });
  });

  document.getElementById('webdavTestNewBtn')?.addEventListener('click', () => {
    const form = document.getElementById('webdavTestNewBtn')?.closest('form');
    testDrive({
      url: form?.querySelector('[name=drive_url]')?.value || '',
      username: form?.querySelector('[name=drive_username]')?.value || '',
      password: form?.querySelector('[name=drive_password]')?.value || '',
      root_path: form?.querySelector('[name=drive_root_path]')?.value || '',
    }, document.getElementById('webdavTestNewResult'));
  });

  const webdavHelpModal = document.getElementById('webdavHelpModal');
  const webdavHelpBtn = document.getElementById('webdavHelpBtn');
  function openWebdavHelpModal() {
    if (!webdavHelpModal) return;
    webdavHelpModal.classList.add('open');
    webdavHelpModal.setAttribute('aria-hidden', 'false');
    document.getElementById('webdavHelpClose')?.focus();
  }
  function closeWebdavHelpModal() {
    if (!webdavHelpModal) return;
    webdavHelpModal.classList.remove('open');
    webdavHelpModal.setAttribute('aria-hidden', 'true');
    webdavHelpBtn?.focus();
  }
  if (webdavHelpBtn && webdavHelpModal) {
    webdavHelpBtn.addEventListener('click', openWebdavHelpModal);
    document.getElementById('webdavHelpClose')?.addEventListener('click', closeWebdavHelpModal);
    document.getElementById('webdavHelpOk')?.addEventListener('click', closeWebdavHelpModal);
    webdavHelpModal.addEventListener('click', (e) => {
      if (e.target === webdavHelpModal) closeWebdavHelpModal();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && webdavHelpModal.classList.contains('open')) closeWebdavHelpModal();
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
