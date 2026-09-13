<?php
// ============================================================
//  DonnieSMS OTP - admin/login.php
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
if (is_admin()) redirect(SITE_URL . '/' . ADMIN_PATH . '/index.php');

$error    = '';
$_siteUrl = rtrim(SITE_URL, '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $s = $pdo->prepare("SELECT * FROM admin_users WHERE username=? OR email=? LIMIT 1");
        $s->execute([$username, $username]);
        $admin = $s->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            $pdo->prepare("UPDATE admin_users SET last_login=NOW() WHERE id=?")->execute([$admin['id']]);
            redirect($_siteUrl . '/' . ADMIN_PATH . '/index.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Login – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= $_siteUrl ?>/assets/css/style.css?v=<?= filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <style>
    body{padding-bottom:0;}
    body::before{
      content:'';position:fixed;inset:0;z-index:-1;
      background:
        radial-gradient(ellipse 80% 50% at 20% 10%,rgba(124,58,237,.25) 0%,transparent 60%),
        radial-gradient(ellipse 60% 40% at 80% 80%,rgba(124,58,237,.15) 0%,transparent 60%),
        var(--bg);
    }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <?= get_logo_html() ?>
      <p style="color:var(--text2);margin-top:6px;">Administration Panel</p>
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
          <label class="form-label">Username or Email</label>
          <div class="input-wrap">
            <i class="fi fi-rr-user"></i>
            <input type="text" name="username" class="form-control"
                   placeholder="admin" required autofocus autocomplete="username">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fi fi-rr-lock"></i>
            <input type="password" name="password" class="form-control"
                   placeholder="Password" required autocomplete="current-password">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fi fi-rr-sign-in-alt"></i> Login to Admin
        </button>
      </form>
    </div>

    <div style="text-align:center;margin-top:20px;">
      <a href="<?= $_siteUrl ?>" style="color:var(--text3);font-size:13px;">
        ← Back to Site
      </a>
    </div>

  </div>
</div>
<script src="<?= $_siteUrl ?>/assets/js/app.js?v=<?= filemtime(ROOT_PATH . '/assets/js/app.js') ?>"></script>
</body>
</html>
