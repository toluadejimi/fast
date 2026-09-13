<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'VTU Orders';
require_once __DIR__ . '/includes/admin_header.php';

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_refund'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $notice = 'Invalid request.';
    } else {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM vtu_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $notice = 'Order not found.';
        } elseif ($order['status'] === 'refunded') {
            $notice = "Order #{$orderId} was already marked refunded — not refunding again.";
        } elseif ($order['status'] === 'success') {
            $notice = "Order #{$orderId} was successful — refunding it would be incorrect, skipped.";
        } else {
            $ok = wallet_credit(
                (int) $order['user_id'], (float) $order['amount_usd'],
                ucfirst($order['service_code']) . ' manual refund (order #' . $orderId . ')',
                $order['reference'] . '-MANUAL'
            );
            if ($ok) {
                $pdo->prepare("UPDATE vtu_orders SET status='refunded' WHERE id=?")->execute([$orderId]);
                notify((int) $order['user_id'], 'Refund Processed', 'Your VTU purchase (reference ' . $order['reference'] . ') has been refunded to your wallet.', '/pages/vtu-history.php');
                $notice = "Order #{$orderId} refunded successfully.";
            } else {
                $notice = "Refund FAILED for order #{$orderId} — please check the user's wallet manually.";
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT vo.*, u.username, u.email FROM vtu_orders vo LEFT JOIN users u ON u.id = vo.user_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR vo.recipient LIKE ? OR vo.reference LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
if ($statusFilter !== '') {
    $sql .= " AND vo.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY vo.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<?php if ($notice): ?>
<div class="alert alert-info"><i class="fi fi-rr-info"></i><span><?= clean($notice) ?></span></div>
<?php endif; ?>

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
  <input type="text" name="q" class="form-control" style="flex:1;min-width:200px;" placeholder="Search by username, recipient, or reference..." value="<?= clean($search) ?>">
  <select name="status" class="form-control" style="max-width:160px;">
    <option value="">All Statuses</option>
    <?php foreach (['pending','success','failed','refunded'] as $st): ?>
    <option value="<?= $st ?>" <?= $statusFilter===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
</form>

<div class="table-wrap">
<table class="admin-table">
  <thead>
    <tr><th>User</th><th>Service</th><th>Recipient</th><th>Amount (₦)</th><th>Status</th><th>Reference</th><th>Date</th><th></th></tr>
  </thead>
  <tbody>
    <?php if (empty($orders)): ?>
    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3);">No VTU orders yet</td></tr>
    <?php endif; ?>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= clean($o['username'] ?? 'Deleted user') ?><br><small style="color:var(--text3);"><?= clean($o['email'] ?? '') ?></small></td>
      <td><?= clean(ucfirst($o['service_code'])) ?><?= $o['plan_name'] ? '<br><small style="color:var(--text3);">' . clean($o['plan_name']) . '</small>' : '' ?></td>
      <td><?= clean($o['recipient'] ?? '') ?></td>
      <td>₦<?= number_format($o['amount_ngn'], 2) ?></td>
      <td><span class="pill pill-<?= $o['status']==='success'?'success':'gray' ?>"><?= clean(ucfirst($o['status'])) ?></span>
        <?php if (strpos($o['api_response'] ?? '', 'REFUND FAILED') !== false): ?>
        <br><small style="color:var(--danger);font-weight:700;">⚠ needs manual refund</small>
        <?php endif; ?>
      </td>
      <td style="font-size:11px;"><?= clean($o['reference']) ?></td>
      <td><?= date('M j, g:i A', strtotime($o['created_at'])) ?></td>
      <td>
        <?php if (!in_array($o['status'], ['success', 'refunded'], true)): ?>
        <form method="POST" onsubmit="return confirm('Refund ₦<?= number_format($o['amount_ngn'],2) ?> ($<?= number_format($o['amount_usd'],4) ?>) to this user\'s wallet?')">
          <?= csrf_field() ?>
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button type="submit" name="manual_refund" value="1" class="btn btn-sm btn-outline">Refund</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
