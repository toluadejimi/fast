<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_login();
$user    = current_user();
// Guard: if session exists but user row is missing (deleted account etc.)
if (!$user) { session_destroy(); header('Location: ' . SITE_URL . '/login.php'); exit; }
$unread  = unread_count((int)$user['id']);
$flash   = get_flash();
$pgTitle = $pageTitle ?? get_setting('site_name', SITE_NAME);
$siteName = get_setting('site_name', SITE_NAME);
$logoFile = get_setting('logo_path', '');
$hasLogo  = $logoFile !== '';
$logoMark = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $siteName) ?: 'F', 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <title><?= clean($pgTitle) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="manifest" href="/pwa/manifest.json">
  <meta name="theme-color" content="#CC0000">
  <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body>

<!-- Top Navigation Header -->
<nav class="top-nav" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg2);margin:12px 14px 0 14px;border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);">
  
  <!-- Left Side: Site Profile & Greeting Layout -->
  <div class="top-nav-brand">
    <a href="<?= SITE_URL ?>/pages/dashboard.php" class="top-nav-logo <?= $hasLogo ? 'has-image' : 'has-text' ?>" aria-label="<?= clean($siteName) ?>">
      <?php if ($hasLogo): ?>
        <?= get_logo_html('top-nav-logo-img') ?>
      <?php else: ?>
        <span class="logo-mark"><?= clean($logoMark) ?></span>
      <?php endif; ?>
    </a>
    <div class="top-nav-greet">
      <span class="top-nav-greet-label">Good Morning,</span>
      <strong class="top-nav-greet-name"><?= htmlspecialchars($user['username'] ?? 'User') ?> 👋</strong>
    </div>
  </div>
  
  <!-- Right Side: Action Badges Layout -->
  <div style="display:flex;align-items:center;gap:8px;">
    
    <!-- Interactive NGN / USD Currency Pill Toggle Switcher -->
    <div id="currency-pill-switch" onclick="toggleCurrencySystem()" style="background:var(--bg2);padding:6px 10px;border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;align-items:center;gap:5px;font-weight:600;font-size:12px;color:var(--text);box-shadow:var(--shadow);cursor:pointer;user-select:none;">
      <span id="currency-flag-ui" style="font-size:13px;">🇳🇬</span>
      <span id="currency-label-ui">NGN</span>
      <i class="fa-solid fa-chevron-down" style="font-size:9px;color:var(--text3);margin-left:1px;"></i>
    </div>

    <!-- Standalone Support Headset Connection Container -->
    <a href="<?= SITE_URL ?>/pages/support.php" style="width:36px;height:36px;background:var(--bg2);border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--text2);text-decoration:none;box-shadow:var(--shadow);">
      <i class="fi fi-rr-comments" style="color:var(--text2);"></i>
    </a>

    <!-- Standalone Notification Alert Container -->
    <a href="<?= SITE_URL ?>/pages/notifications.php" style="width:36px;height:36px;background:var(--bg2);border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:16px;position:relative;color:var(--text2);text-decoration:none;box-shadow:var(--shadow);">
      <i class="fi fi-rr-envelope-dot" style="color:var(--text2);"></i>
      <?php if ($unread > 0): ?>
      <span style="position:absolute;top:-4px;right:-4px;background:var(--accent);color:#FFF;font-size:9px;font-weight:700;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg2);box-shadow:0 2px 8px rgba(127,86,217,0.4);"><?= $unread > 9 ? '9+' : $unread ?></span>
      <?php endif; ?>
    </a>
  </div>

</nav>




<!-- Page Content Wrapper -->
<div class="page-wrap">

<?php if ($flash): ?>
<?php 
  $bg = $flash['type'] === 'error' ? 'var(--primary-light)' : ($flash['type'] === 'success' ? '#E6F7F0' : 'var(--bg3)');
  $border = $flash['type'] === 'error' ? 'rgba(255,0,0,0.15)' : ($flash['type'] === 'success' ? 'rgba(5,150,105,0.15)' : 'var(--border)');
  $color = $flash['type'] === 'error' ? 'var(--primary)' : ($flash['type'] === 'success' ? 'var(--success)' : 'var(--info)');
?>
<!-- Modern Standalone Flash Block Alert -->
<div class="alert flash-msg" style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--radius-sm); margin: 12px 16px; font-size: 14px; font-weight: 500; background: <?= $bg ?>; border: 1px solid <?= $border ?>; color: <?= $color ?>; animation: fadeIn .3s ease;">
  <i class="fi fi-rr-<?= $flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'cross-circle' : 'info') ?>"></i>
  <span><?= clean($flash['msg']) ?></span>
</div>
<style>@keyframes fadeIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}</style>
<?php endif; ?>

