<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Social Boost Orders';
require_once __DIR__ . '/includes/admin_header.php';
require_once ROOT_PATH . '/includes/momopanel.php';

$search = trim($_GET['q'] ?? '');

$sql = "SELECT so.*, u.username, u.email FROM social_orders so LEFT JOIN users u ON u.id = so.user_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR so.service_name LIKE ? OR so.link LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY so.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(charged_price),0) FROM social_orders WHERE status != 'Canceled'")->fetchColumn();
$totalOrders  = (int) $pdo->query("SELECT COUNT(*) FROM social_orders")->fetchColumn();
$totalCancelled = (int) $pdo->query("SELECT COUNT(*) FROM social_orders WHERE status = 'Canceled'")->fetchColumn();

$momo = new MomoPanel();
$balResp = $momo->getBalance();
?>

<div class="admin-stat-grid">
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-share"></i></div>
    <div class="s-num"><?= number_format($totalOrders) ?></div>
    <div class="s-lbl">Total Orders</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-sack" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">$<?= number_format($totalRevenue, 2) ?></div>
    <div class="s-lbl">Revenue (non-cancelled)</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-cross-circle"></i></div>
    <div class="s-num"><?= number_format($totalCancelled) ?></div>
    <div class="s-lbl">Cancelled</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-wallet"></i></div>
    <div class="s-num"><?= $balResp['success'] ? '$' . number_format((float)($balResp['data']['balance'] ?? 0), 2) : 'Error' ?></div>
    <div class="s-lbl">momopanel Balance</div>
  </div>
</div>

<?php if (!$balResp['success']): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span>Could not reach momopanel.com: <?= clean($balResp['error'] ?? 'unknown error') ?>. Check your API key in Settings.</span></div>
<?php endif; ?>

<form method="GET" style="margin-bottom:16px;">
  <input type="text" name="q" class="form-control" placeholder="Search by username, service, or link..." value="<?= clean($search) ?>">
</form>

<div class="table-wrap">
<table class="admin-table">
  <thead>
    <tr><th>User</th><th>Service</th><th>Qty</th><th>Charged</th><th>Status</th><th>Date</th></tr>
  </thead>
  <tbody>
    <?php if (empty($orders)): ?>
    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text3);">No orders yet</td></tr>
    <?php endif; ?>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= clean($o['username'] ?? 'Deleted user') ?><br><small style="color:var(--text3);"><?= clean($o['email'] ?? '') ?></small></td>
      <td><?= clean($o['service_name']) ?><br><small style="color:var(--text3);"><?= clean($o['category']) ?></small></td>
      <td><?= number_format($o['quantity']) ?></td>
      <td>$<?= number_format($o['charged_price'], 4) ?></td>
      <td><span class="pill pill-gray"><?= clean($o['status']) ?></span></td>
      <td><?= date('M j, g:i A', strtotime($o['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
