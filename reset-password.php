<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';
if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');

$token   = $_GET['token'] ?? '';
$uid     = (int)($_GET['uid'] ?? 0);
$error   = $success = '';
$valid   = false;

if ($token && $uid) {
    // Read reset token directly from users table
    $row = $pdo->prepare("SELECT reset_token, reset_expires FROM users WHERE id=? LIMIT 1");
    $row->execute([$uid]); $row = $row->fetch();
    if ($row && $row['reset_token'] && $row['reset_expires']) {
        if (hash_equals($row['reset_token'], $token) && strtotime($row['reset_expires']) > time())
            $valid = true;
    }
}
if (!$valid && $_SERVER['REQUEST_METHOD'] !== 'POST') $error = 'This link is invalid or has expired.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $pw  = $_POST['password'] ?? '';
        $con = $_POST['confirm']   ?? '';
        if (strlen($pw) < 6)    { $error = 'Password must be at least 6 characters.'; }
        elseif ($pw !== $con)   { $error = 'Passwords do not match.'; }
        else {
            // Update password and clear the reset token
            $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
                ->execute([password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $uid]);
            $success = 'Password reset! You can now login.';
        }
    }
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reset Password – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body style="padding-bottom:0;">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><?= get_logo_html() ?><p>Set a new password</p></div>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div>
      <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary btn-block">Go to Login</a>
    <?php elseif ($valid): ?>
    <div class="auth-box">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-wrap"><i class="fi fi-rr-lock"></i>
            <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="input-wrap"><i class="fi fi-rr-lock"></i>
            <input type="password" name="confirm" class="form-control" placeholder="Repeat password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fi fi-rr-key"></i> Reset Password</button>
      </form>
    </div>
    <?php endif; ?>
    <div class="auth-footer"><a href="<?= SITE_URL ?>/login.php">← Back to Login</a></div>
  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
