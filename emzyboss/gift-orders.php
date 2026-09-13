<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Gift Orders';
require_once __DIR__ . '/includes/admin_header.php';

$notice = '';
$statuses = ['Pending', 'Confirmed', 'Shipped', 'Delivered', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $notice = 'Invalid request.';
    } else {
        $orderId   = (int) ($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $adminNote = trim($_POST['admin_note'] ?? '');

        if (!in_array($newStatus, $statuses, true)) {
            $notice = 'Invalid status.';
        } else {
            $stmt = $pdo->prepare("SELECT go.*, u.email, u.username FROM gift_orders go JOIN users u ON u.id = go.user_id WHERE go.id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                $notice = 'Order not found.';
            } else {
                $wasCancelled = $order['status'] === 'Cancelled';

                $pdo->prepare("UPDATE gift_orders SET status = ?, admin_note = ? WHERE id = ?")
                    ->execute([$newStatus, $adminNote !== '' ? $adminNote : null, $orderId]);

                // Refund if newly cancelled (and wasn't already cancelled before)
                if ($newStatus === 'Cancelled' && !$wasCancelled) {
                    wallet_credit($order['user_id'], (float) $order['total_price'], 'Refund – Gift order cancelled: ' . $order['product_name'], gen_ref('GIFTR'));
                    track_spend($order['user_id'], -(float) $order['total_price']);
                    // Restock
                    $pdo->prepare("UPDATE gift_products SET stock = stock + ? WHERE id = ?")->execute([$order['quantity'], $order['product_id']]);
                }

                $order['status']     = $newStatus;
                $order['admin_note'] = $adminNote;
                notify_gift_status($order, $newStatus, $order['email'], $order['username']);

                $notice = "Order #{$orderId} updated to {$newStatus} and the customer has been notified.";
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "SELECT go.*, u.username, u.email FROM gift_orders go LEFT JOIN users u ON u.id = go.user_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR go.receiver_name LIKE ? OR go.product_name LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
if ($filterStatus !== '' && in_array($filterStatus, $statuses, true)) {
    $sql .= " AND go.status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY go.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<?php if ($notice): ?><div class="alert alert-info"><i class="fi fi-rr-info"></i><span><?= clean($notice) ?></span></div><?php endif; ?>

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
  <input type="text" name="q" class="form-control" style="flex:1;min-width:200px;" placeholder="Search by username, receiver, product..." value="<?= clean($search) ?>">
  <select name="status" class="form-control" style="max-width:180px;">
    <option value="">All Statuses</option>
    <?php foreach ($statuses as $st): ?>
    <option value="<?= $st ?>" <?= $filterStatus===$st?'selected':'' ?>><?= $st ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
</form>

<?php foreach ($orders as $o): ?>
<div class="glass" style="padding:16px;border-radius:var(--radius);margin-bottom:14px;">
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
    <div>
      <strong>Order #<?= (int)$o['id'] ?></strong> — <?= clean($o['product_name']) ?> x<?= (int)$o['quantity'] ?>
      <div style="font-size:12px;color:var(--text3);"><?= clean($o['username'] ?? 'Deleted user') ?> (<?= clean($o['email'] ?? '') ?>) &middot; <?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></div>
    </div>
    <span class="pill pill-<?= $o['status']==='Completed'?'success':($o['status']==='Cancelled'?'gray':'pending') ?>"><?= clean($o['status']) ?></span>
  </div>

  <div style="font-size:13px;line-height:1.7;background:var(--bg2);padding:12px;border-radius:8px;margin-bottom:10px;">
    <strong>Sender:</strong> <?= clean($o['sender_name']) ?><br>
    <strong>Receiver:</strong> <?= clean($o['receiver_name']) ?><br>
    <strong>Address:</strong> <?= clean($o['address']) ?><?= $o['apartment_number'] ? ', Apt ' . clean($o['apartment_number']) : '' ?><br>
    <strong>City/State/Zip:</strong> <?= clean($o['city']) ?>, <?= clean($o['state']) ?> <?= clean($o['zip_code']) ?><br>
    <strong>Phone:</strong> <?= clean($o['phone_number']) ?><br>
    <strong>Total Charged:</strong> $<?= number_format($o['total_price'], 2) ?>
    <?php if ($o['admin_note']): ?><br><strong>Note:</strong> <?= clean($o['admin_note']) ?><?php endif; ?>
  </div>

  <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <?= csrf_field() ?>
    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
    <select name="status" class="form-control" style="max-width:160px;">
      <?php foreach ($statuses as $st): ?>
      <option value="<?= $st ?>" <?= $o['status']===$st?'selected':'' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="admin_note" class="form-control" style="flex:1;min-width:180px;" placeholder="Optional note (e.g. tracking number)" value="<?= clean($o['admin_note'] ?? '') ?>">
    <button type="submit" name="update_status" value="1" class="btn btn-primary btn-sm">Update &amp; Notify</button>
  </form>
</div>
<?php endforeach; ?>

<?php if (empty($orders)): ?>
<div class="empty"><p>No gift orders yet.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
