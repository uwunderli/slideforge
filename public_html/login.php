<?php
require __DIR__ . '/../config.php';

if (Auth::isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$resendEmail = '';
if (isset($_GET['expired'])) {
    $error = t('auth.session_expired');
}
if (isset($_GET['verified'])) {
    $success = t('auth.verify_success');
} else {
    $success = '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$ok, $msg] = Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($ok) {
        if (!empty($_POST['remember'])) {
            extend_session_cookie(30);
        }
        redirect('index.php');
    }
    if ($msg === 'email_not_verified') {
        $error = t('auth.email_not_verified');
        $loginId = trim($_POST['username'] ?? '');
        if (str_contains($loginId, '@')) {
            $resendEmail = $loginId;
        } else {
            $u = Auth::findByUsername($loginId);
            $resendEmail = $u['email'] ?? '';
        }
    } else {
        $error = $msg;
    }
}

$pageTitle = t('auth.login_title');
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="form-card">
    <h1><?= h(t('auth.welcome_back')) ?></h1>
    <p class="subtitle"><?= h(t('auth.login_subtitle')) ?></p>

    <?php if (Config::demoMode()): ?>
    <div class="demo-login-accounts">
      <h2 class="demo-login-accounts-title"><?= h(t('demo.login_accounts')) ?></h2>
      <table class="demo-login-table">
        <thead>
          <tr>
            <th><?= h(t('demo.col_username')) ?></th>
            <th><?= h(t('demo.col_email')) ?></th>
            <th><?= h(t('demo.col_password')) ?></th>
            <th><?= h(t('demo.col_role')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (Demo::accountRows() as $acc): ?>
          <tr>
            <td><code><?= h($acc['username']) ?></code></td>
            <td><code><?= h($acc['email']) ?></code></td>
            <td><code><?= h($acc['password']) ?></code></td>
            <td><?= h($acc['role_label']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($resendEmail !== ''): ?>
      <p class="subtitle"><a href="resend_verification.php?email=<?= urlencode($resendEmail) ?>"><?= h(t('auth.resend_verification')) ?></a></p>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <label for="username"><?= h(t('auth.username_or_email')) ?></label>
      <input type="text" id="username" name="username" required autofocus>

      <label for="password"><?= h(t('auth.password')) ?></label>
      <input type="password" id="password" name="password" required>

      <label style="display:flex; align-items:center; gap:8px; margin-top:14px;">
        <input type="checkbox" name="remember" value="1" style="width:auto;">
        <?= h(t('auth.remember_me')) ?>
      </label>

      <button type="submit" class="button" style="margin-top:16px;"><?= h(t('auth.login_btn')) ?></button>
    </form>

    <div class="switch"><?= h(t('auth.no_account')) ?> <a href="register.php"><?= h(t('auth.to_register')) ?></a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
