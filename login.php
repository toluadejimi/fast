<!--
=============================================================
  COPYRIGHT & LICENSE NOTICE
=============================================================
  Script Name   : FemTech NG Web Script
  Owner         : FemTech NG
  Website       : https://femtech.top
  Contact       : +234 901 671 8588
=============================================================
  © 2025 - 2026 FemTech NG. All Rights Reserved.
=============================================================
-->

<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';
if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $s = $pdo->prepare("SELECT * FROM users WHERE email=? OR username=? LIMIT 1");
        $s->execute([$login, $login]);
        $user = $s->fetch();
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact support.';
            } else {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                redirect(SITE_URL . '/pages/dashboard.php');
            }
        } else {
            $error = 'Invalid email/username or password.';
        }
    }
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Login – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body style="padding-bottom:0;">
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <?= get_logo_html() ?>
      <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
      <i class="fi fi-rr-cross-circle"></i>
      <span><?= clean($error) ?></span>
    </div>
    <?php endif; ?>

    <div class="auth-box">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Email or Username</label>
          <div class="input-wrap">
            <i class="fi fi-rr-user"></i>
            <input type="text" name="login" class="form-control"
                   placeholder="Email or username"
                   value="<?= clean($_POST['login'] ?? '') ?>"
                   required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fi fi-rr-lock"></i>
            <input type="password" name="password" class="form-control"
                   placeholder="Your password" required>
          </div>
          <div style="text-align:right;margin-top:6px;">
            <a href="<?= SITE_URL ?>/forgot-password.php" class="text-sm text-primary">
              Forgot password?
            </a>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fi fi-rr-sign-in-alt"></i> Login
        </button>
      </form>

      <div style="display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--text3);font-size:12px;">
        <div style="flex:1;height:1px;background:var(--border);"></div>OR<div style="flex:1;height:1px;background:var(--border);"></div>
      </div>

      <!--<a href="<?= SITE_URL ?>/google-login.php" class="btn btn-block" style="background:#fff;border:1px solid var(--border);color:#333;display:flex;align-items:center;justify-content:center;gap:10px;">-->
      <!--  <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20.4H24v7.2h11.3C33.7 32 29.3 35 24 35c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.1-5.1C33.6 5.1 29 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c3.1 0 5.9 1.2 8 3.1l5.1-5.1C33.6 5.1 29 3 24 3c-7.7 0-14.3 4.4-17.7 10.7z"/><path fill="#4CAF50" d="M24 45c5.2 0 9.9-1.7 13.5-4.7l-6.2-5.2C29.4 36.6 26.8 37.5 24 37.5c-5.3 0-9.7-3.4-11.3-8.1l-6.5 5C9.6 40.5 16.2 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20.4H24v7.2h11.3c-.8 2.3-2.3 4.3-4.2 5.7l6.2 5.2C40.9 35.5 45 30.3 45 24c0-1.2-.1-2.4-.4-3.5z"/></svg>-->
      <!--  Continue with Google-->
      <!--</a>-->
    </div>

    <div class="auth-footer">
      No account?
      <a href="<?= SITE_URL ?>/register.php"><strong>Create one free</strong></a>
    </div>

    <!--<div style="text-align:center;margin-top:12px;">-->
    <!--  <button id="theme-toggle" class="theme-toggle" onclick="toggleTheme()" style="margin:auto;">-->
    <!--    <i class="fi fi-rr-moon"></i>-->
    <!--  </button>-->
    <!--</div>-->

  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
