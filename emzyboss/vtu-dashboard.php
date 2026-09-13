<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'VTU Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$balanceNotice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_provider_balance'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $balanceNotice = 'Invalid request.';
    } else {
        set_setting('vtu_provider_balance', (string) (float) ($_POST['provider_balance'] ?? 0));
        set_setting('vtu_provider_balance_updated', date('Y-m-d H:i:s'));
        $balanceNotice = 'Balance updated.';
    }
}

$providerBalance = (float) get_setting('vtu_provider_balance', '0');
$providerBalanceUpdated = get_setting('vtu_provider_balance_updated', '');

$totalOrders    = (int) $pdo->query("SELECT COUNT(*) FROM vtu_orders")->fetchColumn();
$successOrders  = (int) $pdo->query("SELECT COUNT(*) FROM vtu_orders WHERE status='success'")->fetchColumn();
$refundedOrders = (int) $pdo->query("SELECT COUNT(*) FROM vtu_orders WHERE status='refunded'")->fetchColumn();
$totalRevenueNgn = (float) $pdo->query("SELECT COALESCE(SUM(amount_ngn),0) FROM vtu_orders WHERE status='success'")->fetchColumn();
$totalCostNgn    = (float) $pdo->query("SELECT COALESCE(SUM(cost_ngn),0) FROM vtu_orders WHERE status='success'")->fetchColumn();

$byService = $pdo->query(
    "SELECT service_code, COUNT(*) cnt, COALESCE(SUM(CASE WHEN status='success' THEN amount_ngn ELSE 0 END),0) revenue
     FROM vtu_orders GROUP BY service_code"
)->fetchAll();

$apiKeySet = get_setting('vtu_api_key', '') !== '';
?>

<?php if (!$apiKeySet): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span>No VTUnaija API key set yet. Go to <a href="vtu-services.php">VTU Services</a> to add it before this module can work.</span></div>
<?php endif; ?>

<?php if ($balanceNotice): ?>
<div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($balanceNotice) ?></span></div>
<?php endif; ?>

<div class="glass" style="padding:18px;border-radius:var(--radius);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
  <div>
    <div style="font-size:12px;color:var(--text3);text-transform:uppercase;letter-spacing:.03em;">VTUnaija Provider Balance</div>
    <div style="font-size:24px;font-weight:900;color:var(--primary);">₦<?= number_format($providerBalance, 2) ?></div>
    <div style="font-size:11px;color:var(--text3);">
      <?= $providerBalanceUpdated ? 'Last updated ' . date('M j, g:i A', strtotime($providerBalanceUpdated)) : 'Never updated' ?>
      — VTUnaija doesn't expose a balance API, so this is entered manually after checking your VTUnaija dashboard.
    </div>
  </div>
  <form method="POST" style="display:flex;gap:8px;align-items:center;">
    <?= csrf_field() ?>
    <input type="number" name="provider_balance" class="form-control" style="width:140px;" step="0.01" placeholder="₦ amount" value="<?= $providerBalance ?: '' ?>">
    <button type="submit" name="update_provider_balance" value="1" class="btn btn-primary btn-sm">Update</button>
  </form>
</div>

<?php if ($providerBalance > 0 && $providerBalance < 5000): ?>
<div class="alert alert-danger"><i class="fi fi-rr-triangle-warning"></i><span>Your VTUnaija balance is low (₦<?= number_format($providerBalance, 2) ?>). Fund your VTUnaija account soon to avoid failed orders.</span></div>
<?php endif; ?>

<div class="admin-stat-grid">
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-receipt"></i></div>
    <div class="s-num"><?= number_format($totalOrders) ?></div>
    <div class="s-lbl">Total Orders</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-check" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);"><?= number_format($successOrders) ?></div>
    <div class="s-lbl">Successful</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-undo"></i></div>
    <div class="s-num"><?= number_format($refundedOrders) ?></div>
    <div class="s-lbl">Refunded</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-sack" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">₦<?= number_format($totalRevenueNgn - $totalCostNgn, 2) ?></div>
    <div class="s-lbl">Profit (successful)</div>
  </div>
</div>

<h3>By Service</h3>
<div class="table-wrap">
<table class="admin-table">
  <thead><tr><th>Service</th><th>Orders</th><th>Revenue (₦)</th></tr></thead>
  <tbody>
    <?php if (empty($byService)): ?>
    <tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text3);">No orders yet</td></tr>
    <?php endif; ?>
    <?php foreach ($byService as $s): ?>
    <tr>
      <td><?= clean(ucfirst($s['service_code'])) ?></td>
      <td><?= number_format($s['cnt']) ?></td>
      <td>₦<?= number_format($s['revenue'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="glass" style="padding:16px;border-radius:var(--radius);margin-top:20px;">
  <h3 style="margin-top:0;">Quick Links</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="vtu-services.php" class="btn btn-outline btn-sm"><i class="fi fi-rr-settings"></i> Services & Markup</a>
    <a href="vtu-sync.php" class="btn btn-outline btn-sm"><i class="fi fi-rr-refresh"></i> Sync Plans</a>
    <a href="vtu-orders.php" class="btn btn-outline btn-sm"><i class="fi fi-rr-receipt"></i> All Orders</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
