<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (Presentation::checkPermission($id, $me['id']) !== 'owner') {
    http_response_code(403);
    die(t('share.owner_only'));
}
if (Presentation::isTemplate($id)) {
    redirect('templates.php?tab=slides');
}

$meta = Presentation::getMeta($id);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_share') {
        $username = trim($_POST['username'] ?? '');
        $permission = $_POST['permission'] ?? 'view';
        $target = Auth::findByUsername($username);
        if (!$target) {
            $error = t('share.user_not_found');
        } elseif ($target['id'] === $me['id']) {
            $error = t('share.cannot_share_self');
        } else {
            Presentation::addShare($id, $target['id'], $target['username'], $permission);
            $mail = ShareNotification::send($target, $me, $meta, $permission);
            if ($mail['ok']) {
                $success = t('share.saved_and_mailed', ['user' => $target['username'], 'email' => $target['email']]);
            } elseif (($mail['error'] ?? '') === 'no_smtp') {
                $success = t('share.saved_for', ['user' => $target['username']]);
                $error = t('share.mail_no_smtp');
            } elseif (($mail['error'] ?? '') === 'invalid_email') {
                $success = t('share.saved_for', ['user' => $target['username']]);
                $error = t('share.mail_invalid_email', ['user' => $target['username']]);
            } else {
                $success = t('share.saved_for', ['user' => $target['username']]);
                $error = t('share.mail_send_failed', ['error' => $mail['error'] ?? t('admin.test_mail_unknown_error')]);
            }
        }
    }

    if ($action === 'remove_share') {
        Presentation::removeShare($id, $_POST['user_id'] ?? '');
        $success = t('share.removed');
    }

    if ($action === 'set_public') {
        $enabled = isset($_POST['enabled']);
        $permission = $_POST['public_permission'] ?? 'view';
        Presentation::setPublic($id, $enabled, $permission);
        $success = $enabled ? t('share.public_enabled') : t('share.public_disabled');
    }

    if ($action === 'regenerate_token') {
        Presentation::regeneratePublicToken($id);
        $success = t('share.token_regenerated');
    }
}

$acl = Presentation::getAcl($id);
$sharedIds = array_column($acl['shares'], 'user_id');
$availableUsers = array_values(array_filter(Auth::listAll(), fn($u) => $u['id'] !== $me['id']));
usort($availableUsers, fn($a, $b) => strcasecmp($a['username'], $b['username']));

$publicUrl = '';
if (!empty($acl['public']['token'])) {
    $scheme = current_scheme();
    $host = $_SERVER['HTTP_HOST'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $publicUrl = "$scheme://$host$base/view.php?token=" . $acl['public']['token'];
}

$pageTitle = t('share.heading') . ' · ' . $meta['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 640px;">
  <a href="editor.php?id=<?= urlencode($id) ?>" class="back-link">&larr; <?= h(t('present.back_to_editor')) ?></a>
  <h1 style="margin-top:14px;"><?= h($meta['title']) ?> <?= h(t('share.heading')) ?></h1>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

  <div class="section-header" style="margin-top:32px;"><h2><?= h(t('share.with_people')) ?></h2></div>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="add_share">
    <input type="hidden" name="id" value="<?= h($id) ?>">
    <div class="row">
      <div style="flex:2;">
        <label for="username"><?= h(t('share.user_label')) ?></label>
        <?php if (empty($availableUsers)): ?>
          <select id="username" name="username" disabled>
            <option><?= h(t('share.no_other_users')) ?></option>
          </select>
        <?php else: ?>
          <select id="username" name="username" required>
            <option value="" disabled selected><?= h(t('share.please_choose')) ?></option>
            <?php foreach ($availableUsers as $u): ?>
              <option value="<?= h($u['username']) ?>">
                <?= h($u['username']) ?> (<?= h($u['email']) ?>)<?= in_array($u['id'], $sharedIds, true) ? h(t('share.already_shared')) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
      <div>
        <label for="permission"><?= h(t('share.permission')) ?></label>
        <select id="permission" name="permission">
          <option value="view"><?= h(t('share.view_only_opt')) ?></option>
          <option value="edit"><?= h(t('share.edit_opt')) ?></option>
        </select>
      </div>
    </div>
    <button type="submit" class="button" style="margin-top:16px;" <?= empty($availableUsers) ? 'disabled' : '' ?>><?= h(t('share.share_btn')) ?></button>
  </form>

  <ul class="share-list">
    <?php if (empty($acl['shares'])): ?>
      <li style="color:var(--text-muted); background:none; border:none;"><?= h(t('share.none_yet')) ?></li>
    <?php endif; ?>
    <?php foreach ($acl['shares'] as $s): ?>
      <li>
        <span><?= h($s['username']) ?> <span class="perm-tag <?= h($s['permission']) ?>"><?= $s['permission'] === 'edit' ? h(t('dashboard.permission_edit')) : h(t('dashboard.permission_view')) ?></span></span>
        <form method="post" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="remove_share">
          <input type="hidden" name="id" value="<?= h($id) ?>">
          <input type="hidden" name="user_id" value="<?= h($s['user_id']) ?>">
          <button type="submit" class="button button-ghost button-sm"><?= h(t('common.remove')) ?></button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="section-header"><h2><?= h(t('share.public_link')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.9rem;"><?= t('share.public_link_desc') ?></p>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="set_public">
    <input type="hidden" name="id" value="<?= h($id) ?>">
    <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
      <input type="checkbox" name="enabled" style="width:auto;" <?= !empty($acl['public']['enabled']) ? 'checked' : '' ?>>
      <?= h(t('share.enable_public_link')) ?>
    </label>
    <button type="submit" class="button button-sm" style="margin-top:12px;"><?= h(t('common.save')) ?></button>
  </form>

  <?php if (!empty($acl['public']['enabled']) && $publicUrl): ?>
    <div class="public-link-box">
      <input type="text" readonly value="<?= h($publicUrl) ?>" onclick="this.select()">
      <button class="button button-ghost button-sm" onclick="navigator.clipboard.writeText('<?= h($publicUrl) ?>')" type="button"><?= h(t('present.copy')) ?></button>
    </div>
    <form method="post" style="margin-top:10px;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="regenerate_token">
      <input type="hidden" name="id" value="<?= h($id) ?>">
      <button type="submit" class="button button-ghost button-sm" onclick="return confirm('<?= h(t('share.reset_confirm')) ?>')"><?= h(t('share.reset_link')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
