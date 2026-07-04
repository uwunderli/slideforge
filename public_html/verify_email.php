<?php
require __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';
[$ok, $status, $user] = EmailVerification::verify($token);

$pageTitle = t('auth.verify_title');
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="form-card">
    <h1><?= h(t('auth.verify_title')) ?></h1>

    <?php if ($status === 'ok'): ?>
      <div class="alert alert-success"><?= h(t('auth.verify_success')) ?></div>
      <p class="subtitle"><?= h(t('auth.verify_success_hint')) ?></p>
      <a href="login.php" class="button"><?= h(t('auth.to_login')) ?></a>
    <?php elseif ($status === 'already'): ?>
      <div class="alert alert-success"><?= h(t('auth.verify_already')) ?></div>
      <a href="login.php" class="button"><?= h(t('auth.to_login')) ?></a>
    <?php elseif ($status === 'expired'): ?>
      <div class="alert alert-error"><?= h(t('auth.verify_expired')) ?></div>
      <p class="subtitle"><?= h(t('auth.verify_expired_hint')) ?></p>
      <a href="resend_verification.php" class="button button-ghost"><?= h(t('auth.resend_verification')) ?></a>
    <?php else: ?>
      <div class="alert alert-error"><?= h(t('auth.verify_invalid')) ?></div>
      <a href="login.php" class="button button-ghost"><?= h(t('auth.to_login')) ?></a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
