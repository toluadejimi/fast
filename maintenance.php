<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';
if (get_setting('maintenance_mode','0') !== '1') {
    header('Location: /'); exit;
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Maintenance – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <style>body{padding-bottom:0;}</style>
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
            background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);">
  <div style="text-align:center;color:#fff;max-width:400px;">
    <i class="fi fi-rr-settings" style="font-size:64px;margin-bottom:20px;display:block;opacity:.9;"></i>
    <h1 style="font-size:26px;font-weight:900;margin-bottom:12px;">We'll be right back!</h1>
    <p style="font-size:15px;opacity:.85;margin-bottom:24px;line-height:1.6;">
      <?= clean($siteName) ?> is undergoing maintenance. We're working to improve your experience. Check back shortly.
    </p>
    <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:16px;font-size:14px;border:1px solid rgba(255,255,255,.2);">
      <i class="fi fi-rr-clock"></i> Expected downtime: A few minutes
    </div>
  </div>
</div>
</body>
</html>
