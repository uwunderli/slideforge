<?php
require __DIR__ . '/../config.php';

if (Auth::isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$email = trim($_POST['email'] ?? $_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$ok, $status] = EmailVerification::resendForEmail($_POST['email'] ?? '');
    if ($ok) {
        if ($status === 'already_verified') {
            $success = t('auth.resend_already_verified');
        } else {
            $success = t('auth.resend_sent');
        }
        $email = trim($_POST['email'] ?? '');
    } else {
        if ($status === 'no_smtp') {
            $error = t('auth.resend_no_smtp');
        } elseif ($status === 'invalid_email') {
            $error = t('auth.resend_invalid_email');
        } else {
            $error = t('auth.resend_failed', ['error' => $status]);
        }
    }
}

$pageTitle = t('auth.resend_verification');
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="form-card">
    <h1><?= h(t('auth.resend_verification')) ?></h1>
    <p class="subtitle"><?= h(t('auth.resend_subtitle')) ?></p>

    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <label for="email"><?= h(t('auth.email')) ?></label>
      <input type="email" id="email" name="email" required value="<?= h($email) ?>">
      <button type="submit" class="button" style="margin-top:16px;"><?= h(t('auth.resend_btn')) ?></button>
    </form>

    <div class="switch"><?= h(t('auth.already_account')) ?> <a href="login.php"><?= h(t('auth.to_login')) ?></a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
