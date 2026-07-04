<?php
require __DIR__ . '/../config.php';

if (Auth::isLoggedIn()) {
    redirect('index.php');
}

$inviteToken = $_GET['invite'] ?? $_POST['invite'] ?? '';
$invite = $inviteToken !== '' ? InviteToken::find($inviteToken) : null;
$hasValidInvite = $invite !== null && !$invite['used'];
// Registrierung ist möglich, wenn generell offen ODER ein gültiger Einladungslink vorliegt.
// Der allererste User im System darf sich immer registrieren (Bootstrap).
$userCount = count(Storage::read(USERS_FILE, []));
$canRegister = $userCount === 0 || Config::registrationEnabled() || $hasValidInvite;

$error = '';
$success = '';
if (isset($_GET['pending'])) {
    $success = t('auth.registration_pending');
}
if (isset($_GET['pending_no_mail'])) {
    $success = t('auth.registration_pending_no_mail');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canRegister) {
    csrf_check();
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password !== $password2) {
        $error = t('auth.passwords_mismatch');
    } else {
        [$ok, $msg] = Auth::register($username, $email, $password, $inviteToken);
        if ($ok) {
            if ($msg === 'registration_pending') {
                redirect('register.php?pending=1');
            }
            if ($msg === 'registration_pending_no_mail') {
                redirect('register.php?pending_no_mail=1');
            }
            Auth::login($username, $password);
            redirect('index.php');
        }
        $error = $msg;
    }
}

$pageTitle = t('auth.register_title');
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="form-card">
    <h1><?= h(t('auth.create_account')) ?></h1>

    <?php if (!$canRegister): ?>
      <p class="subtitle"><?= h(t('auth.registration_disabled')) ?></p>
      <div class="switch"><?= h(t('auth.already_account')) ?> <a href="login.php"><?= h(t('auth.to_login')) ?></a></div>
    <?php else: ?>
      <p class="subtitle">
        <?= $hasValidInvite ? h(t('auth.invited_subtitle')) : h(t('auth.register_subtitle')) ?>
      </p>

      <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

      <?php if (!$success): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="invite" value="<?= h($inviteToken) ?>">
        <label for="username"><?= h(t('auth.username')) ?></label>
        <input type="text" id="username" name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">

        <label for="email"><?= h(t('auth.email')) ?></label>
        <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? ($invite['email'] ?? '')) ?>">

        <label for="password"><?= h(t('auth.password')) ?></label>
        <input type="password" id="password" name="password" required minlength="6">

        <label for="password2"><?= h(t('auth.password_repeat')) ?></label>
        <input type="password" id="password2" name="password2" required minlength="6">

        <button type="submit" class="button"><?= h(t('auth.register_btn')) ?></button>
      </form>
      <?php else: ?>
      <p class="subtitle"><a href="resend_verification.php"><?= h(t('auth.resend_verification')) ?></a></p>
      <?php endif; ?>

      <div class="switch"><?= h(t('auth.already_account')) ?> <a href="login.php"><?= h(t('auth.to_login')) ?></a></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
