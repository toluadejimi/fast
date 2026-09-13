<?php
// ============================================================
//  DonnieSMS - pages/gift-orders.php
//  User's own gift order history with status tracking.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'My Gift Orders';
require_once ROOT_PATH . '/includes/header.php';

$orders = $pdo->prepare("SELECT * FROM gift_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 80");
$orders->execute([$user['id']]);
$orderList = $orders->fetchAll();

$stages = ['Pending', 'Confirmed', 'Shipped', 'Delivered', 'Completed'];
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">My Gift Orders</h2>

<?php if (empty($orderList)): ?>
<div class="empty">
  <i class="fi fi-rr-gift" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No gift orders yet.</p>
  <a href="<?= SITE_URL ?>/pages/gift.php" class="btn btn-primary mt-2" style="display:inline-flex;">
    <i class="fi fi-rr-gift"></i> Browse Gifts
  </a>
</div>
<?php else: ?>
<?php foreach ($orderList as $o):
    $stepIndex = array_search($o['status'], $stages, true);
    $isCancelled = $o['status'] === 'Cancelled';
?>
<div class="order-item">
  <div class="order-top">
    <div>
      <div class="order-title"><?= clean($o['product_name']) ?> x<?= (int)$o['quantity'] ?></div>
      <div class="order-meta" style="font-size:12px;">To: <?= clean($o['receiver_name']) ?>, <?= clean($o['city']) ?>, <?= clean($o['state']) ?></div>
    </div>
    <span class="pill pill-<?= $isCancelled ? 'gray' : ($o['status']==='Completed' ? 'success' : 'pending') ?>"><?= clean($o['status']) ?></span>
  </div>

  <?php if (!$isCancelled): ?>
  <div style="display:flex;gap:4px;margin:12px 0;">
    <?php foreach ($stages as $i => $stage): ?>
    <div style="flex:1;height:5px;border-radius:3px;background:<?= $i <= $stepIndex ? 'var(--primary)' : 'var(--bg2)' ?>;"></div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:8px;">
    <?php foreach ($stages as $stage): ?>
    <span><?= $stage ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($o['admin_note']): ?>
  <div style="font-size:12px;color:var(--text2);background:var(--bg2);padding:8px 10px;border-radius:6px;margin-bottom:8px;">
    <i class="fi fi-rr-info"></i> <?= clean($o['admin_note']) ?>
  </div>
  <?php endif; ?>

  <div class="d-flex justify-between align-center">
    <span class="order-meta"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
    <span class="order-amount"><?= fmt_money((float)$o['total_price']) ?></span>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
