<!--
=============================================================
  COPYRIGHT & LICENSE NOTICE
=============================================================
  Script Name   : FemTech NG Web Script
  Owner         : FemTech NG
  Website       : https://femtech.top
  Contact       : +234 901 671 8588
=============================================================
  © 2025 FemTech NG. All Rights Reserved.

  This script and its contents are the exclusive intellectual
  property of FemTech NG (femtech.top). Unauthorized copying,
  redistribution, modification, reselling, or use of this
  script — in whole or in part — without the express written
  permission of FemTech NG is strictly prohibited.

  Violators will be subject to applicable copyright laws and
  may face legal action.

  For licensing inquiries, permissions, or support:
  📞 +234 901 671 8588
  🌐 https://femtech.top
=============================================================
-->



<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$totalUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders")->fetchColumn();
$totalRev    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='credit' AND status='success'")->fetchColumn();
$totalBal    = (float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM users")->fetchColumn();
$openTickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn();
$pendingOtp  = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders WHERE status='PENDING'")->fetchColumn();
$todayOrders = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$todayRev       = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='credit' AND status='success' AND DATE(created_at)=CURDATE()")->fetchColumn();
$totalCancelled = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders WHERE status='CANCELED'")->fetchColumn();
$totalSuccess   = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders WHERE status='RECEIVED'")->fetchColumn();

$recentUsers  = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();
$recentOrders = $pdo->query("SELECT o.*,u.username FROM otp_orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();
?>

<!-- Stats -->
<div class="admin-stat-grid">
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-users"></i></div>
    <div class="s-num"><?= number_format($totalUsers) ?></div>
    <div class="s-lbl">Total Users</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-sack" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">$<?= number_format($totalRev, 2) ?></div>
    <div class="s-lbl">Total Revenue (USD)</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-mobile"></i></div>
    <div class="s-num"><?= number_format($totalOrders) ?></div>
    <div class="s-lbl">Total OTP Orders</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-wallet"></i></div>
    <div class="s-num">$<?= number_format($totalBal, 2) ?></div>
    <div class="s-lbl">User Balances (USD)</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#fef3c7;"><i class="fi fi-rr-headset" style="color:var(--warning);"></i></div>
    <div class="s-num" style="color:var(--warning);"><?= $openTickets ?></div>
    <div class="s-lbl">Open Tickets</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#fef3c7;"><i class="fi fi-rr-clock" style="color:var(--warning);"></i></div>
    <div class="s-num" style="color:var(--warning);"><?= $pendingOtp ?></div>
    <div class="s-lbl">Pending OTPs</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-check" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);"><?= $totalSuccess ?></div>
    <div class="s-lbl">Successful OTPs</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#fee2e2;"><i class="fi fi-rr-cross-circle" style="color:var(--danger);"></i></div>
    <div class="s-num" style="color:var(--danger);"><?= $totalCancelled ?></div>
    <div class="s-lbl">Cancelled OTPs</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-calendar"></i></div>
    <div class="s-num"><?= $todayOrders ?></div>
    <div class="s-lbl">Orders Today</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-coins" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">$<?= number_format($todayRev, 2) ?></div>
    <div class="s-lbl">Revenue Today (USD)</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<!-- Recent Users -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <div class="d-flex justify-between align-center" style="margin-bottom:16px;">
    <h3 style="font-weight:800;font-size:15px;">Recent Users</h3>
    <a href="/<?= ADMIN_PATH ?>/users.php" class="text-primary text-sm fw-700">View All</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>Username</th><th>Balance</th><th>Joined</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (empty($recentUsers)): ?>
    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text3);">No users yet</td></tr>
    <?php endif; ?>
    <?php foreach ($recentUsers as $u): ?>
    <tr>
      <td><strong><?= clean($u['username']) ?></strong><br><small style="color:var(--text3);"><?= clean($u['email']) ?></small></td>
      <td style="font-weight:700;">$<?= number_format((float)$u['balance'],2) ?></td>
      <td style="font-size:12px;color:var(--text2);"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
      <td><span class="pill pill-<?= $u['status']==='active'?'success':'danger' ?>"><?= ucfirst($u['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Recent Orders -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <div class="d-flex justify-between align-center" style="margin-bottom:16px;">
    <h3 style="font-weight:800;font-size:15px;">Recent Orders</h3>
    <a href="/<?= ADMIN_PATH ?>/orders.php" class="text-primary text-sm fw-700">View All</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>User</th><th>Service</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (empty($recentOrders)): ?>
    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text3);">No orders yet</td></tr>
    <?php endif; ?>
    <?php foreach ($recentOrders as $o): ?>
    <tr>
      <td><strong><?= clean($o['username']??'—') ?></strong></td>
      <td><?= clean($o['service_name']) ?></td>
      <td style="font-weight:700;">$<?= number_format((float)$o['amount_paid'],2) ?></td>
      <td><span class="pill pill-<?= $o['status']==='RECEIVED'?'success':($o['status']==='PENDING'?'pending':'gray') ?>"><?= ucfirst(strtolower($o['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
