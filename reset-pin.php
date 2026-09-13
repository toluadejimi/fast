<?php
// reset-pin.php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

$token   = $_GET['token'] ?? '';
$uid     = (int)($_GET['uid'] ?? 0);
$error   = $success = '';
$valid   = false;

if ($token && $uid) {
    $row = $pdo->prepare("SELECT pin_reset_token, pin_reset_expires FROM users WHERE id=? LIMIT 1");
    $row->execute([$uid]); $row = $row->fetch();
    if ($row && $row['pin_reset_token'] && $row['pin_reset_expires']) {
        if (hash_equals($row['pin_reset_token'], $token) && strtotime($row['pin_reset_expires']) > time())
            $valid = true;
    }
}
if (!$valid && $_SERVER['REQUEST_METHOD'] !== 'POST') $error = 'This link is invalid or has expired.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $pin = $_POST['pin']     ?? '';
        $con = $_POST['confirm'] ?? '';
        if (!preg_match('/^\d{4}$/', $pin)) { $error = 'PIN must be exactly 4 digits.'; }
        elseif ($pin !== $con)              { $error = 'PINs do not match.'; }
        else {
            $pdo->prepare("UPDATE users SET pin=?, pin_reset_token=NULL, pin_reset_expires=NULL WHERE id=?")
                ->execute([password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]), $uid]);
            $success = 'PIN reset! You can now use your new PIN to confirm purchases.';
        }
    }
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reset PIN – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body style="padding-bottom:0;">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><?= get_logo_html() ?><p>Set a new 4-digit PIN</p></div>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div>
      <a href="<?= SITE_URL ?>/pages/dashboard.php" class="btn btn-primary btn-block">Go to Dashboard</a>
    <?php elseif ($valid): ?>
    <div class="auth-box">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">New 4-digit PIN</label>
          <div class="input-wrap"><i class="fi fi-rr-lock"></i>
            <input type="password" name="pin" class="form-control" placeholder="● ● ● ●"
                   maxlength="4" inputmode="numeric" pattern="\d{4}" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New PIN</label>
          <div class="input-wrap"><i class="fi fi-rr-lock"></i>
            <input type="password" name="confirm" class="form-control" placeholder="● ● ● ●"
                   maxlength="4" inputmode="numeric" pattern="\d{4}" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fi fi-rr-key"></i> Reset PIN</button>
      </form>
    </div>
    <?php endif; ?>
    <div class="auth-footer"><a href="<?= SITE_URL ?>/login.php">← Back to Login</a></div>
  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
