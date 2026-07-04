<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$profileMsg = '';
$profileError = '';
$passwordMsg = '';
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

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
                    // Alte Avatar-Datei(en) dieses Users entfernen
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
}

$pageTitle = t('profile.title');
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 560px;">
  <a href="index.php" class="back-link">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('profile.title')) ?></h1>
  <p style="color:var(--text-muted); font-size:0.9rem;">
    <?= h(t('profile.role')) ?>: <span class="perm-tag <?= $me['role'] === 'admin' ? 'edit' : 'view' ?>"><?= $me['role'] === 'admin' ? h(t('profile.role_admin')) : h(t('profile.role_editor')) ?></span>
  </p>

  <div class="section-header" style="margin-top:28px;"><h2><?= h(t('profile.avatar_heading')) ?></h2></div>
  <div style="display:flex; align-items:center; gap:18px;">
    <?php if (!empty($me['avatar'])): ?>
      <img src="uploads/avatars/<?= h($me['avatar']) ?>" alt="" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:1px solid var(--border);">
    <?php else: ?>
      <div style="width:64px; height:64px; border-radius:50%; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700;"><?= h(mb_strtoupper(mb_substr($me['username'], 0, 1))) ?></div>
    <?php endif; ?>
    <div style="display:flex; flex-direction:column; gap:8px;">
      <form method="post" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" required>
        <button type="submit" class="button button-sm"><?= h(t('profile.upload')) ?></button>
      </form>
      <?php if (!empty($me['avatar'])): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="remove_avatar">
          <button type="submit" class="button button-ghost button-sm"><?= h(t('profile.remove_avatar')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="section-header"><h2><?= h(t('profile.account_data')) ?></h2></div>
  <?php if ($profileError): ?><div class="alert alert-error"><?= h($profileError) ?></div><?php endif; ?>
  <?php if ($profileMsg): ?><div class="alert alert-success"><?= h($profileMsg) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="update_profile">
    <label for="username"><?= h(t('profile.username')) ?></label>
    <input type="text" id="username" name="username" value="<?= h($me['username']) ?>" required>
    <label for="email"><?= h(t('profile.email')) ?></label>
    <input type="email" id="email" name="email" value="<?= h($me['email']) ?>" required>
    <button type="submit" class="button" style="margin-top:16px;"><?= h(t('common.save')) ?></button>
  </form>

  <div class="section-header"><h2><?= h(t('profile.spellcheck_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('profile.spellcheck_intro') ?></p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save_spellcheck">
    <label style="display:flex; align-items:center; gap:8px; margin-top:12px;">
      <input type="checkbox" name="spellcheck_browser" style="width:auto;" <?= Auth::spellcheckBrowserEnabled($me) ? 'checked' : '' ?>>
      <?= h(t('profile.spellcheck_browser')) ?>
    </label>
    <label style="display:flex; align-items:center; gap:8px; margin-top:12px;">
      <input type="checkbox" name="spellcheck_before_present" style="width:auto;" <?= Auth::spellcheckBeforePresent($me) ? 'checked' : '' ?>>
      <?= h(t('profile.spellcheck_before_present')) ?>
    </label>
    <label for="spellcheck_lang" style="margin-top:14px;"><?= h(t('profile.spellcheck_language')) ?></label>
    <select id="spellcheck_lang" name="spellcheck_lang">
      <option value="" <?= ($me['spellcheck_lang'] ?? '') === '' ? 'selected' : '' ?>><?= h(t('profile.spellcheck_lang_auto')) ?></option>
      <?php foreach (Auth::SPELLCHECK_LANGUAGES as $code => $label): ?>
        <option value="<?= h($code) ?>" <?= ($me['spellcheck_lang'] ?? '') === $code ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('common.save')) ?></button>
  </form>

  <div class="section-header"><h2><?= h(t('profile.change_password')) ?></h2></div>
  <?php if ($passwordError): ?><div class="alert alert-error"><?= h($passwordError) ?></div><?php endif; ?>
  <?php if ($passwordMsg): ?><div class="alert alert-success"><?= h($passwordMsg) ?></div><?php endif; ?>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
