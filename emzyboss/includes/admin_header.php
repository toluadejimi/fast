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
        ['fi-rr-gauge',        'Dashboard',       '/'.ADMIN_PATH.'/index.php'],
        ['fi-rr-users',        'Users',           '/'.ADMIN_PATH.'/users.php'],
        ['fi-rr-receipt',      'OTP Orders',      '/'.ADMIN_PATH.'/orders.php'],
        ['fi-rr-shopping-bag', 'Logs & Products', '/'.ADMIN_PATH.'/logs.php'],
        // ['fi-rr-share',        'Social Boost',    '/'.ADMIN_PATH.'/social-orders.php'],
        // ['fi-rr-gift',         'Gift Products',   '/'.ADMIN_PATH.'/gifts.php'],
        // ['fi-rr-box',          'Gift Orders',     '/'.ADMIN_PATH.'/gift-orders.php'],
        // ['fi-rr-star',         'Reviews',         '/'.ADMIN_PATH.'/admin-review.php'],
        ['fi-rr-wallet',       'Wallets',         '/'.ADMIN_PATH.'/wallets.php'],
        ['fi-rr-megaphone',    'Notifications',   '/'.ADMIN_PATH.'/notifications.php'],
        ['fi-rr-headset',      'Support',         '/'.ADMIN_PATH.'/support.php'],
        ['fi-rr-list-check',   'Support Categories', '/'.ADMIN_PATH.'/support-categories.php'],
        // ['fi-rr-badge-check',  'Verified Buyers', '/'.ADMIN_PATH.'/badges.php'],
        // ['fi-rr-envelope',     'Broadcast Emails','/'.ADMIN_PATH.'/broadcast.php'],
    ],
    'VTU BILLS' => [
        ['fi-rr-gauge',        'VTU Dashboard',   '/'.ADMIN_PATH.'/vtu-dashboard.php'],
        ['fi-rr-settings',     'VTU Services',    '/'.ADMIN_PATH.'/vtu-services.php'],
        ['fi-rr-refresh',      'VTU Sync Plans',  '/'.ADMIN_PATH.'/vtu-sync.php'],
        ['fi-rr-receipt',      'VTU Orders',      '/'.ADMIN_PATH.'/vtu-orders.php'],
    ],
    'CONFIGURE' => [
        ['fi-rr-credit-card',  'Gateways',        '/'.ADMIN_PATH.'/gateways.php'],
        ['fi-rr-ad',           'Dashboard Ads',   '/'.ADMIN_PATH.'/ads.php'],
        ['fi-rr-paint-brush',  'Theme & Logo',    '/'.ADMIN_PATH.'/theme.php'],
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
    body { padding-bottom: 0 !important; }

    /* ── Mobile Admin Nav ── */
    .admin-mobile-bar {
      display: none;
      position: fixed; top: 0; left: 0; right: 0; z-index: 500;
      height: 56px;
      background: linear-gradient(180deg, #1A0A3B 0%, #2D1B69 100%);
      border-bottom: 1px solid rgba(167,139,250,.15);
      align-items: center; justify-content: space-between;
      padding: 0 16px;
    }
    .admin-mobile-bar .mob-logo { color: var(--accent); font-weight: 900; font-size: 16px; }
    .admin-hamburger {
      width: 38px; height: 38px; border-radius: 8px;
      background: rgba(124,58,237,.2); border: none;
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: 5px; cursor: pointer;
    }
    .admin-hamburger span {
      display: block; width: 20px; height: 2px;
      background: rgba(255,255,255,.8); border-radius: 2px;
      transition: all .25s;
    }
    .admin-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .admin-hamburger.open span:nth-child(2) { opacity: 0; }
    .admin-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* Drawer overlay */
    .admin-drawer-overlay {
      display: none; position: fixed; inset: 0; z-index: 998;
      background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
    }
    .admin-drawer-overlay.open { display: block; }

    /* Sidebar drawer on mobile */
    @media(max-width: 900px) {
      .admin-mobile-bar   { display: flex; }
      .admin-content      { margin-left: 0 !important; padding-top: 72px !important; }
      .admin-sidebar {
        position: fixed !important;
        top: 0 !important; left: -260px !important;
        bottom: 0 !important; z-index: 999 !important;
        width: 240px !important;
        transition: left .28s cubic-bezier(.4,0,.2,1) !important;
        display: flex !important;
      }
      .admin-sidebar.open { left: 0 !important; }
      .admin-stat-grid    { grid-template-columns: repeat(2,1fr) !important; }
    }
    @media(min-width: 901px) {
      .admin-hamburger, .admin-mobile-bar { display: none !important; }
    }
  </style>
</head>
<body class="admin-body">

<!-- Mobile top bar -->
<div class="admin-mobile-bar">
  <span class="mob-logo"><?= clean($siteName) ?> Admin</span>
  <div style="display:flex;align-items:center;gap:10px;">
    <!--<button id="theme-toggle" class="theme-toggle" onclick="toggleTheme()"-->
    <!--        style="width:34px;height:34px;">-->
    <!--  <i class="fi fi-rr-moon"></i>-->
    <!--</button>-->
    <button class="admin-hamburger" id="adminHamburger" onclick="toggleAdminNav()">
      <span></span><span></span><span></span>
    </button>
  </div>
</div>

<!-- Drawer overlay (click to close) -->
<div class="admin-drawer-overlay" id="adminDrawerOverlay" onclick="toggleAdminNav()"></div>

<div class="admin-wrap">

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="logo-area">
    <?= get_logo_html() ?>
  </div>

  <?php foreach ($menu as $section => $items): ?>
  <div class="nav-section"><?= $section ?></div>
  <?php foreach ($items as [$icon, $label, $href]):
    $active = basename($href) === $cur ? 'active' : '';
  ?>
  <a href="<?= $href ?>" class="<?= $active ?>" onclick="closeMobileNav()">
    <i class="fi <?= $icon ?>"></i> <?= $label ?>
  </a>
  <?php endforeach; ?>
  <?php endforeach; ?>

  <div style="margin-top:auto;">
    <a href="/logout.php?admin=1" class="logout-link" onclick="closeMobileNav()">
      <i class="fi fi-rr-exit"></i> Logout
    </a>
  </div>
</aside>

<!-- Main content -->
<main class="admin-content">
  <div class="admin-topbar">
    <h2><?= clean($pageTitle) ?></h2>
    <div style="display:flex;align-items:center;gap:10px;">
      <!--<button id="theme-toggle-desk" class="theme-toggle" onclick="toggleTheme()">-->
      <!--  <i class="fi fi-rr-moon"></i>-->
      <!--</button>-->
      <a href="<?= SITE_URL ?>" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fi fi-rr-globe"></i> View Site
      </a>
      <span style="font-size:13px;color:var(--text2);">
        <strong><?= clean($adminUser['username'] ?? 'Admin') ?></strong>
      </span>
      <span class="pill pill-info"><?= ucfirst($adminUser['role'] ?? 'admin') ?></span>
    </div>
  </div>

<script>
function toggleAdminNav() {
    const sidebar  = document.getElementById('adminSidebar');
    const overlay  = document.getElementById('adminDrawerOverlay');
    const hamburger= document.getElementById('adminHamburger');
    const isOpen   = sidebar.classList.contains('open');
    sidebar.classList.toggle('open', !isOpen);
    overlay.classList.toggle('open', !isOpen);
    hamburger.classList.toggle('open', !isOpen);
    document.body.style.overflow = isOpen ? '' : 'hidden';
}
function closeMobileNav() {
    document.getElementById('adminSidebar').classList.remove('open');
    document.getElementById('adminDrawerOverlay').classList.remove('open');
    document.getElementById('adminHamburger').classList.remove('open');
    document.body.style.overflow = '';
}
// Close on ESC
document.addEventListener('keydown', e => { if(e.key==='Escape') closeMobileNav(); });

// Theme toggle (works for both desktop and mobile buttons)
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    document.querySelectorAll('.theme-toggle i, #theme-toggle-desk i').forEach(i => {
        i.className = isDark ? 'fi fi-rr-moon' : 'fi fi-rr-sun';
    });
}
// Restore theme on load
(function(){
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
    document.querySelectorAll('.theme-toggle i').forEach(i => {
        i.className = t === 'dark' ? 'fi fi-rr-sun' : 'fi fi-rr-moon';
    });
})();
</script>
