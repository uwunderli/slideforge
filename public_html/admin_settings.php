<?php
require __DIR__ . '/../config.php';
Auth::requireAdmin();
$me = Auth::currentUser();

$msg = '';
$error = '';
$postAction = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $postAction = $action;

    // ---- Tab "Allgemeine Einstellungen" ----
    if ($action === 'save_general') {
        Config::update([
            'site_title' => trim($_POST['site_title'] ?? '') !== '' ? trim($_POST['site_title']) : APP_NAME,
            'registration_enabled' => isset($_POST['registration_enabled']),
        ]);
        $msg = t('admin.general_saved');
    }

    if ($action === 'upload_logo' && !empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= MAX_LOGO_SIZE) {
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? @finfo_file($finfo, $file['tmp_name']) : false;
            if ($mime && isset($allowed[$mime])) {
                $filename = 'logo-' . Storage::generateId(6) . '.' . $allowed[$mime];
                if (@move_uploaded_file($file['tmp_name'], PUBLIC_UPLOADS_PATH . '/' . $filename)) {
                    $oldLogo = Config::get('logo', '');
                    Config::update(['logo' => $filename]);
                    if ($oldLogo && file_exists(PUBLIC_UPLOADS_PATH . '/' . $oldLogo)) {
                        @unlink(PUBLIC_UPLOADS_PATH . '/' . $oldLogo);
                    }
                    $msg = t('admin.logo_updated');
                } else {
                    $error = t('admin.logo_save_failed');
                }
            } else {
                $error = t('admin.logo_unsupported_type');
            }
        } else {
            $error = t('admin.logo_upload_failed', ['mb' => round(MAX_LOGO_SIZE / 1024 / 1024)]);
        }
    }

    if ($action === 'remove_logo') {
        $oldLogo = Config::get('logo', '');
        if ($oldLogo && file_exists(PUBLIC_UPLOADS_PATH . '/' . $oldLogo)) {
            @unlink(PUBLIC_UPLOADS_PATH . '/' . $oldLogo);
        }
        Config::update(['logo' => '']);
        $msg = t('admin.logo_removed');
    }

    if ($action === 'save_smtp') {
        if (Demo::smtpLocked()) {
            $error = t('demo.smtp_locked');
        } else {
        Config::update(['smtp' => [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => (int)($_POST['smtp_port'] ?? 587),
            'encryption' => in_array($_POST['smtp_encryption'] ?? 'tls', ['none', 'tls', 'ssl'], true) ? $_POST['smtp_encryption'] : 'tls',
            'username' => trim($_POST['smtp_username'] ?? ''),
            'password' => $_POST['smtp_password'] !== '' ? $_POST['smtp_password'] : (Config::get('smtp')['password'] ?? ''),
            'from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'from_name' => trim($_POST['smtp_from_name'] ?? APP_NAME),
        ]]);
        $msg = t('admin.smtp_saved');
        }
    }

    if ($action === 'save_languagetool') {
        $existing = Config::languageTool();
        Config::update(['languagetool' => [
            'enabled' => isset($_POST['lt_enabled']),
            'api_url' => trim($_POST['lt_api_url'] ?? ''),
            'api_username' => trim($_POST['lt_api_username'] ?? ''),
            'api_key' => ($_POST['lt_api_key'] ?? '') !== '' ? $_POST['lt_api_key'] : ($existing['api_key'] ?? ''),
        ]]);
        $msg = t('admin.languagetool_saved');
    }

    if ($action === 'save_pixabay') {
        $existing = Config::pixabay();
        Config::update(['pixabay' => [
            'enabled' => isset($_POST['px_enabled']),
            'api_key' => ($_POST['px_api_key'] ?? '') !== '' ? trim($_POST['px_api_key']) : ($existing['api_key'] ?? ''),
        ]]);
        $msg = t('admin.pixabay_saved');
    }

    if ($action === 'save_iconify') {
        Config::update(['iconify' => [
            'enabled' => isset($_POST['ic_enabled']),
        ]]);
        $msg = t('admin.iconify_saved');
    }

    if ($action === 'save_openclipart') {
        Config::update(['openclipart' => [
            'enabled' => isset($_POST['oc_enabled']),
        ]]);
        $msg = t('admin.openclipart_saved');
    }

    // ---- Tab "Benutzerverwaltung" ----
    if ($action === 'create_invite') {
        $inviteEmail = trim($_POST['invite_email'] ?? '');
        $invite = InviteToken::create($me['id'], $inviteEmail);
        $msg = t('admin.invite_created');

        if ($inviteEmail !== '') {
            if (Demo::smtpLocked()) {
                $error = t('demo.smtp_locked');
            } elseif (!filter_var($inviteEmail, FILTER_VALIDATE_EMAIL)) {
                $error = t('admin.invite_invalid_email');
            } else {
                $smtp = Config::smtp();
                if (empty($smtp['host'])) {
                    $error = t('admin.invite_no_smtp');
                } else {
                    $scheme = current_scheme();
                    $host = $_SERVER['HTTP_HOST'];
                    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                    $inviteUrl = "$scheme://$host$base/register.php?invite=" . $invite['token'];
                    $siteTitle = Config::siteTitle();
                    $result = SmtpMailer::send(
                        $smtp,
                        $inviteEmail,
                        t('admin.invite_mail_subject', ['site' => $siteTitle]),
                        t('admin.invite_mail_body', ['site' => $siteTitle, 'url' => $inviteUrl])
                    );
                    if ($result['ok']) {
                        $msg = t('admin.invite_created_and_sent', ['email' => $inviteEmail]);
                    } else {
                        $error = t('admin.invite_send_failed', ['error' => $result['error'] ?? '?']);
                    }
                }
            }
        }
    }

    if ($action === 'delete_invite') {
        InviteToken::delete($_POST['token'] ?? '');
        $msg = t('admin.invite_deleted');
    }

    if ($action === 'set_role') {
        $targetId = $_POST['user_id'] ?? '';
        if ($targetId === $me['id'] && ($_POST['role'] ?? '') !== 'admin') {
            $error = t('admin.cannot_revoke_self_admin');
        } elseif (($_POST['role'] ?? '') !== 'admin' && Auth::countAdmins() <= 1 && (Auth::findById($targetId)['role'] ?? '') === 'admin') {
            $error = t('admin.min_one_admin');
        } else {
            Auth::setRole($targetId, $_POST['role'] ?? 'editor');
            $msg = t('admin.role_updated');
        }
    }

    if ($action === 'delete_user') {
        $targetId = $_POST['user_id'] ?? '';
        if ($targetId === $me['id']) {
            $error = t('admin.cannot_delete_self');
        } elseif ((Auth::findById($targetId)['role'] ?? '') === 'admin' && Auth::countAdmins() <= 1) {
            $error = t('admin.min_one_admin');
        } else {
            Auth::deleteUser($targetId, isset($_POST['also_delete_presentations']));
            $msg = t('admin.user_deleted');
        }
    }
}

$userTabActions = ['create_invite', 'delete_invite', 'set_role', 'delete_user'];
$activeTab = in_array($postAction, $userTabActions, true) || (($_GET['tab'] ?? '') === 'users')
    ? 'users'
    : 'general';

$cfg = Config::all();
$smtp = $cfg['smtp'] ?? [];
$languagetool = Config::languageTool();
$pixabay = Config::pixabay();
$iconify = Config::iconify();
$openclipart = Config::openclipart();
$invites = InviteToken::listAll();
$users = Auth::listAll();
$smtpDemoLocked = Demo::smtpLocked();
$scheme = current_scheme();
$host = $_SERVER['HTTP_HOST'];
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$pageTitle = t('admin.heading');
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 780px;">
  <a href="index.php" class="back-link">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('admin.heading')) ?></h1>

  <div class="page-tabs">
    <a href="?tab=general" class="page-tab-btn<?= $activeTab === 'general' ? ' active' : '' ?>"><?= h(t('admin.tab_general')) ?></a>
    <a href="?tab=users" class="page-tab-btn<?= $activeTab === 'users' ? ' active' : '' ?>"><?= h(t('admin.tab_users')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>

  <?php if ($activeTab === 'general'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('admin.general_heading')) ?></h2></div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_general">
      <label for="site_title"><?= h(t('admin.site_title')) ?></label>
      <input type="text" id="site_title" name="site_title" value="<?= h($cfg['site_title'] ?? APP_NAME) ?>">
      <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
        <input type="checkbox" name="registration_enabled" style="width:auto;" <?= !empty($cfg['registration_enabled']) ? 'checked' : '' ?>>
        <?= h(t('admin.open_registration')) ?>
      </label>
      <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('common.save')) ?></button>
    </form>

    <div class="section-header"><h2><?= h(t('admin.logo_heading')) ?></h2></div>
    <?php if (Config::logoUrl()): ?>
      <img src="<?= h(Config::logoUrl()) ?>" alt="Logo" style="max-height:50px; max-width:220px; display:block; margin-bottom:12px; background:var(--surface-raised); padding:8px; border-radius:var(--radius-sm);">
    <?php else: ?>
      <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('admin.no_logo') ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="upload_logo">
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
      <button type="submit" class="button button-sm"><?= h(t('profile.upload')) ?></button>
    </form>
    <?php if (Config::logoUrl()): ?>
      <form method="post" style="margin-top:8px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="remove_logo">
        <button type="submit" class="button button-ghost button-sm"><?= h(t('bg.remove')) ?> Logo</button>
      </form>
    <?php endif; ?>

    <div class="section-header"><h2><?= h(t('admin.smtp_heading')) ?></h2></div>
    <?php if ($smtpDemoLocked): ?>
      <p class="demo-smtp-notice"><?= h(t('demo.smtp_locked')) ?></p>
    <?php endif; ?>
    <fieldset class="demo-fieldset-lock<?= $smtpDemoLocked ? ' is-locked' : '' ?>"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_smtp">
      <div class="row">
        <div style="flex:2;">
          <label for="smtp_host"><?= h(t('admin.smtp_host')) ?></label>
          <input type="text" id="smtp_host" name="smtp_host" value="<?= h($smtp['host'] ?? '') ?>" placeholder="smtp.example.com"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        </div>
        <div>
          <label for="smtp_port"><?= h(t('admin.smtp_port')) ?></label>
          <input type="number" id="smtp_port" name="smtp_port" value="<?= (int)($smtp['port'] ?? 587) ?>"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        </div>
      </div>
      <label for="smtp_encryption"><?= h(t('admin.smtp_encryption')) ?></label>
      <select id="smtp_encryption" name="smtp_encryption"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        <option value="tls" <?= ($smtp['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>><?= h(t('admin.smtp_enc_tls')) ?></option>
        <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>><?= h(t('admin.smtp_enc_ssl')) ?></option>
        <option value="none" <?= ($smtp['encryption'] ?? '') === 'none' ? 'selected' : '' ?>><?= h(t('admin.smtp_enc_none')) ?></option>
      </select>
      <div class="row">
        <div>
          <label for="smtp_username"><?= h(t('admin.smtp_username')) ?></label>
          <input type="text" id="smtp_username" name="smtp_username" value="<?= h($smtp['username'] ?? '') ?>"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        </div>
        <div>
          <label for="smtp_password"><?= h(t('admin.smtp_password')) ?></label>
          <input type="password" id="smtp_password" name="smtp_password" placeholder="<?= !empty($smtp['password']) ? h(t('admin.smtp_password_unchanged')) : '' ?>"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        </div>
      </div>
      <div class="row">
        <div>
          <label for="smtp_from_email"><?= h(t('admin.smtp_from_email')) ?></label>
          <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= h($smtp['from_email'] ?? '') ?>"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        </div>
        <div>
          <label for="smtp_from_name"><?= h(t('admin.smtp_from_name')) ?></label>
          <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= h($smtp['from_name'] ?? APP_NAME) ?>"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
        </div>
      </div>
      <button type="submit" class="button button-sm" style="margin-top:14px;"<?= $smtpDemoLocked ? ' disabled' : '' ?>><?= h(t('admin.smtp_save_btn')) ?></button>
    </form>

    <div class="options-subtitle" style="margin-top:20px;"><?= h(t('admin.send_test_mail')) ?></div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <input type="email" id="testMailTo" placeholder="empfänger@example.com" style="flex:1; min-width:220px;"<?= $smtpDemoLocked ? ' disabled' : '' ?>>
      <button type="button" id="testMailBtn" class="button button-ghost button-sm"<?= $smtpDemoLocked ? ' disabled' : '' ?>><?= h(t('admin.send_test_mail')) ?></button>
    </div>
    <div id="testMailResult" style="margin-top:10px; font-size:0.85rem;"></div>
    </fieldset>

    <div class="section-header"><h2><?= h(t('admin.languagetool_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('admin.languagetool_intro') ?></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_languagetool">
      <label style="display:flex; align-items:center; gap:8px; margin-top:12px;">
        <input type="checkbox" name="lt_enabled" style="width:auto;" <?= !empty($languagetool['enabled']) ? 'checked' : '' ?>>
        <?= h(t('admin.languagetool_enabled')) ?>
      </label>
      <label for="lt_api_url" style="margin-top:14px;"><?= h(t('admin.languagetool_url')) ?></label>
      <input type="url" id="lt_api_url" name="lt_api_url" value="<?= h($languagetool['api_url'] ?? '') ?>" placeholder="https://api.languagetool.org/v2/check">
      <div class="row" style="margin-top:10px;">
        <div>
          <label for="lt_api_username"><?= h(t('admin.languagetool_username')) ?></label>
          <input type="text" id="lt_api_username" name="lt_api_username" value="<?= h($languagetool['api_username'] ?? '') ?>" placeholder="<?= h(t('admin.languagetool_optional')) ?>">
        </div>
        <div>
          <label for="lt_api_key"><?= h(t('admin.languagetool_api_key')) ?></label>
          <input type="password" id="lt_api_key" name="lt_api_key" placeholder="<?= !empty($languagetool['api_key']) ? h(t('admin.smtp_password_unchanged')) : h(t('admin.languagetool_optional')) ?>">
        </div>
      </div>
      <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('admin.languagetool_save_btn')) ?></button>
    </form>

    <div class="section-header"><h2><?= h(t('admin.pixabay_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('admin.pixabay_intro') ?></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_pixabay">
      <label style="display:flex; align-items:center; gap:8px; margin-top:12px;">
        <input type="checkbox" name="px_enabled" style="width:auto;" <?= !empty($pixabay['enabled']) ? 'checked' : '' ?>>
        <?= h(t('admin.pixabay_enabled')) ?>
      </label>
      <div class="label-with-help" style="margin-top:14px;">
        <label for="px_api_key"><?= h(t('admin.pixabay_api_key')) ?></label>
        <button type="button" class="admin-help-btn" id="pixabayHelpBtn" aria-label="<?= h(t('admin.pixabay_help_btn')) ?>" title="<?= h(t('admin.pixabay_help_btn')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </button>
      </div>
      <input type="password" id="px_api_key" name="px_api_key" placeholder="<?= !empty($pixabay['api_key']) ? h(t('admin.smtp_password_unchanged')) : 'Pixabay API Key' ?>">
      <p style="color:var(--text-muted); font-size:0.85rem; margin-top:8px;">
        <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener"><?= h(t('admin.pixabay_docs_link')) ?></a>
      </p>
      <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('admin.pixabay_save_btn')) ?></button>
    </form>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('admin.iconify_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('admin.iconify_intro') ?></p>
    <form method="post" style="margin-top:12px;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_iconify">
      <label style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
        <input type="checkbox" name="ic_enabled" style="width:auto;" <?= !empty($iconify['enabled']) ? 'checked' : '' ?>>
        <?= h(t('admin.iconify_enabled')) ?>
      </label>
      <button type="submit" class="button button-sm"><?= h(t('admin.iconify_save_btn')) ?></button>
    </form>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('admin.openclipart_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('admin.openclipart_intro') ?></p>
    <form method="post" style="margin-top:12px;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_openclipart">
      <label style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
        <input type="checkbox" name="oc_enabled" style="width:auto;" <?= !empty($openclipart['enabled']) ? 'checked' : '' ?>>
        <?= h(t('admin.openclipart_enabled')) ?>
      </label>
      <button type="submit" class="button button-sm"><?= h(t('admin.openclipart_save_btn')) ?></button>
    </form>

  <?php else: /* Tab: Benutzerverwaltung */ ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('admin.invites_heading')) ?></h2></div>
    <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create_invite">
      <div style="flex:1; min-width:200px;">
        <label for="invite_email" style="margin-top:0;"><?= h(t('admin.invite_email_label')) ?></label>
        <input type="email" id="invite_email" name="invite_email" placeholder="max.muster@example.com">
      </div>
      <button type="submit" class="button button-sm"><?= h(t('admin.create_invite_btn')) ?></button>
    </form>

    <ul class="share-list" style="margin-top:16px;">
      <?php if (empty($invites)): ?>
        <li style="color:var(--text-muted); background:none; border:none;"><?= h(t('admin.no_invites_yet')) ?></li>
      <?php endif; ?>
      <?php foreach ($invites as $inv): ?>
        <li style="align-items:flex-start; flex-direction:column; gap:6px;">
          <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
            <span>
              <?= $inv['email'] ? h($inv['email']) : '<span style="color:var(--text-muted)">' . h(t('admin.no_email_note')) . '</span>' ?>
              <span class="perm-tag <?= $inv['used'] ? 'view' : 'edit' ?>"><?= $inv['used'] ? h(t('admin.invite_used')) : h(t('admin.invite_open')) ?></span>
            </span>
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete_invite">
              <input type="hidden" name="token" value="<?= h($inv['token']) ?>">
              <button type="submit" class="button button-ghost button-sm"><?= h(t('common.delete')) ?></button>
            </form>
          </div>
          <?php if (!$inv['used']): ?>
            <input type="text" readonly value="<?= h("$scheme://$host$base/register.php?invite=" . $inv['token']) ?>" onclick="this.select()" style="font-family:var(--font-mono); font-size:0.78rem;">
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="section-header"><h2><?= h(t('admin.users_heading')) ?></h2></div>
    <ul class="share-list">
      <?php foreach ($users as $u): ?>
        <li style="align-items:center;">
          <span>
            <strong><?= h($u['username']) ?></strong>
            <span style="color:var(--text-muted); font-size:0.85rem;"> &middot; <?= h($u['email']) ?></span>
            <?php if ($u['id'] === $me['id']): ?><span class="perm-tag view"><?= h(t('admin.you_badge')) ?></span><?php endif; ?>
          </span>
          <div style="display:flex; gap:8px; align-items:center;">
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="set_role">
              <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
              <select name="role" onchange="this.form.submit()" <?= $u['id'] === $me['id'] ? 'disabled title="' . h(t('admin.own_role_locked')) . '"' : '' ?>>
                <option value="editor" <?= ($u['role'] ?? 'editor') === 'editor' ? 'selected' : '' ?>><?= h(t('admin.role_editor')) ?></option>
                <option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= h(t('admin.role_admin')) ?></option>
              </select>
            </form>
            <?php if ($u['id'] !== $me['id']): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('<?= h(t('admin.delete_user_confirm', ['name' => $u['username']])) ?>');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                <label style="display:flex; align-items:center; gap:4px; font-size:0.78rem; color:var(--text-muted); margin:0;">
                  <input type="checkbox" name="also_delete_presentations" style="width:auto;"> <?= h(t('admin.also_delete_presentations')) ?>
                </label>
                <button type="submit" class="button button-ghost button-sm"><?= h(t('common.delete')) ?></button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

  <?php endif; ?>
</div>

<div class="modal-backdrop" id="pixabayHelpModal" aria-hidden="true">
  <div class="modal admin-help-modal" role="dialog" aria-modal="true" aria-labelledby="pixabayHelpTitle">
    <div class="admin-help-modal-header">
      <h2 id="pixabayHelpTitle" style="font-size:1.15rem; text-transform:none; margin:0;"><?= h(t('admin.pixabay_help_title')) ?></h2>
      <button type="button" class="button button-ghost button-sm" id="pixabayHelpClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    </div>
    <div class="admin-help-modal-body">
      <?= str_replace('assets/images/pixabay-api-key-help.png', 'assets/images/pixabay-api-key-help.png?v=' . ASSET_VERSION, t('admin.pixabay_help_body')) ?>
    </div>
    <div class="modal-actions">
      <button type="button" class="button" id="pixabayHelpOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<script>
const pixabayHelpModal = document.getElementById('pixabayHelpModal');
const pixabayHelpBtn = document.getElementById('pixabayHelpBtn');
function openPixabayHelpModal() {
  if (!pixabayHelpModal) return;
  pixabayHelpModal.classList.add('open');
  pixabayHelpModal.setAttribute('aria-hidden', 'false');
  document.getElementById('pixabayHelpClose')?.focus();
}
function closePixabayHelpModal() {
  if (!pixabayHelpModal) return;
  pixabayHelpModal.classList.remove('open');
  pixabayHelpModal.setAttribute('aria-hidden', 'true');
  pixabayHelpBtn?.focus();
}
if (pixabayHelpBtn && pixabayHelpModal) {
  pixabayHelpBtn.addEventListener('click', openPixabayHelpModal);
  document.getElementById('pixabayHelpClose')?.addEventListener('click', closePixabayHelpModal);
  document.getElementById('pixabayHelpOk')?.addEventListener('click', closePixabayHelpModal);
  pixabayHelpModal.addEventListener('click', (e) => {
    if (e.target === pixabayHelpModal) closePixabayHelpModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && pixabayHelpModal.classList.contains('open')) closePixabayHelpModal();
  });
}

const testMailBtn = document.getElementById('testMailBtn');
if (testMailBtn) {
  testMailBtn.addEventListener('click', async () => {
    const to = document.getElementById('testMailTo').value.trim();
    const resultEl = document.getElementById('testMailResult');
    if (!to) { resultEl.innerHTML = '<span style="color:var(--danger)"><?= h(t('admin.please_enter_recipient')) ?></span>'; return; }
    resultEl.textContent = '<?= h(t('admin.sending')) ?>';
    try {
      const res = await fetch('admin_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'test_mail', to: to, csrf_token: '<?= csrf_token() ?>' }),
      });
      const json = await res.json();
      if (json.ok) {
        resultEl.innerHTML = '<span style="color:var(--accent-view)"><?= h(t('admin.test_mail_sent')) ?></span>';
      } else {
        resultEl.innerHTML = '<span style="color:var(--danger)"><?= h(t('admin.test_mail_error', ['error' => ''])) ?>' + (json.error || '<?= h(t('admin.test_mail_unknown_error')) ?>') + '</span>';
      }
    } catch (e) {
      resultEl.innerHTML = '<span style="color:var(--danger)"><?= h(t('admin.network_error')) ?></span>';
    }
  });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
