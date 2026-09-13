<?php
// forgot-password.php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';
if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email.';
        } else {
            $s = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
            $s->execute([$email]);
            $u = $s->fetch();
            if ($u) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $pdo->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?")
                    ->execute([$token, $expires, $u['id']]);
                $url         = SITE_URL . '/reset-password.php?token=' . urlencode($token) . '&uid=' . $u['id'];
                $siteName_   = get_setting('site_name', SITE_NAME);
                $primaryClr_ = get_setting('primary_color', '#7C3AED');
                $safeUser_   = htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8');
                $resetHtml_  = email_template("
                    <h2 style=\"margin-top:0;color:#333;\">Password Reset Request</h2>
                    <p>Hi <strong>{$safeUser_}</strong>,</p>
                    <p>We received a request to reset the password for your <strong>{$siteName_}</strong> account.</p>
                    <p>Click the button below to choose a new password. This link expires in <strong>1 hour</strong>.</p>
                    <p style=\"text-align:center;margin:30px 0;\">
                      <a href=\"{$url}\" style=\"background:{$primaryClr_};color:#ffffff;padding:13px 32px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;\">Reset My Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style=\"word-break:break-all;font-size:13px;color:#555;\">{$url}</p>
                    <p style=\"color:#999;font-size:13px;\">If you did not request a password reset, you can safely ignore this email.</p>
                ");
                $sent = send_email($email, 'Reset Your Password - ' . $siteName_, $resetHtml_);
                if ($sent) {
                    $success = 'Reset link sent! Check your email (including spam folder).';
                } else {
                    $pdo->prepare("UPDATE users SET reset_token=NULL, reset_expires=NULL WHERE id=?")
                        ->execute([$u['id']]);
                    $error = 'Could not send email. Please try again later or contact support.';
                }
            } else {
                // Don't reveal if email exists or not — security best practice
                $success = 'If that email is registered, a reset link has been sent.';
            }
        }
    }
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Forgot Password – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body style="padding-bottom:0;">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><?= get_logo_html() ?><p>Reset your password</p></div>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div>
    <?php endif; ?>
    <div class="auth-box">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-wrap">
            <i class="fi fi-rr-envelope"></i>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" required autofocus>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fi fi-rr-paper-plane"></i> Send Reset Link
        </button>
      </form>
    </div>
    <div class="auth-footer"><a href="<?= SITE_URL ?>/login.php">← Back to Login</a></div>
  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
