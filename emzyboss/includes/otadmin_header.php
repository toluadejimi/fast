<?php
// ============================================================
//  DonnieSMS OTP – admin/includes/admin_header.php
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$adminUser = $pdo->prepare("SELECT * FROM admin_users WHERE id=?");
$adminUser->execute([$_SESSION['admin_id']]);
$adminUser = $adminUser->fetch();
$pageTitle = $pageTitle ?? 'Admin';
$siteName  = get_setting('site_name', SITE_NAME);

$menu = [
    'MAIN' => [
        ['fi-rr-gauge',        'Dashboard',    '/'.ADMIN_PATH.'/index.php'],
        ['fi-rr-users',        'Users',         '/'.ADMIN_PATH.'/users.php'],
        ['fi-rr-receipt',      'All Orders',    '/'.ADMIN_PATH.'/orders.php'],
        ['fi-rr-wallet',       'Wallets',        '/'.ADMIN_PATH.'/wallets.php'],
        ['fi-rr-megaphone',    'Notifications',  '/' . ADMIN_PATH . '/notifications.php'],
        ['fi-rr-headset',      'Support',        '/' . ADMIN_PATH . '/support.php'],
    ],
    'CONFIGURE' => [
        ['fi-rr-credit-card',  'Gateways',       '/'.ADMIN_PATH.'/gateways.php'],
        ['fi-rr-ad',           'Dashboard Ads',  '/'.ADMIN_PATH.'/ads.php'],
        ['fi-rr-paint-brush',  'Theme & Logo',   '/'.ADMIN_PATH.'/theme.php'],
        ['fi-rr-settings',     'Settings',        '/'.ADMIN_PATH.'/settings.php'],
    ],
];
$cur = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= clean($pageTitle) ?> – <?= clean($siteName) ?> Admin</title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <style>
    body { padding-bottom:0 !important; }
    @media(max-width:900px){.admin-sidebar{display:none!important;}.admin-content{margin-left:0!important;}}
  </style>
</head>
<body class="admin-body">
<div class="admin-wrap">

<!-- Sidebar -->
<aside class="admin-sidebar">
  <div class="logo-area">
    <?= get_logo_html() ?>
  </div>

  <?php foreach ($menu as $section => $items): ?>
  <div class="nav-section"><?= $section ?></div>
  <?php foreach ($items as [$icon, $label, $href]):
    $active = basename($href) === $cur ? 'active' : '';
  ?>
  <a href="<?= $href ?>" class="<?= $active ?>">
    <i class="fi <?= $icon ?>"></i> <?= $label ?>
  </a>
  <?php endforeach; ?>
  <?php endforeach; ?>

  <div style="margin-top:auto;">
    <a href="/logout.php?admin=1" class="logout-link">
      <i class="fi fi-rr-exit"></i> Logout
    </a>
  </div>
</aside>

<!-- Main content -->
<main class="admin-content">
  <div class="admin-topbar">
    <h2><?= clean($pageTitle) ?></h2>
    <div style="display:flex;align-items:center;gap:10px;">
      <button id="theme-toggle" class="theme-toggle" onclick="toggleTheme()">
        <i class="fi fi-rr-moon"></i>
      </button>
      <a href="<?= SITE_URL ?>" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fi fi-rr-globe"></i> View Site
      </a>
      <span style="font-size:13px;color:var(--text2);">
        <strong><?= clean($adminUser['username'] ?? 'Admin') ?></strong>
      </span>
      <span class="pill pill-info"><?= ucfirst($adminUser['role'] ?? 'admin') ?></span>
    </div>
  </div>
