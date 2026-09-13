<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'VTU History';
require_once ROOT_PATH . '/includes/header.php';

$flash = get_flash();

$orders = $pdo->prepare("SELECT * FROM vtu_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 80");
$orders->execute([$user['id']]);
$orderList = $orders->fetchAll();

$serviceIcons = ['airtime' => 'fi-rr-mobile-notch', 'data' => 'fi-rr-signal-alt', 'cable' => 'fi-rr-tv-music', 'electricity' => 'fi-rr-bolt'];
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">VTU History</h2>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><i class="fi fi-rr-check"></i><span><?= clean($flash['msg']) ?></span></div>
<?php endif; ?>

<?php if (empty($orderList)): ?>
<div class="empty">
  <i class="fi fi-rr-receipt" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No VTU purchases yet.</p>
  <a href="<?= SITE_URL ?>/pages/vtu-bills.php" class="btn btn-primary mt-2" style="display:inline-flex;">
    <i class="fi fi-rr-bolt"></i> Buy Now
  </a>
</div>
<?php else: ?>
<?php foreach ($orderList as $o):
    $icon = $serviceIcons[$o['service_code']] ?? 'fi-rr-receipt';
    $pillClass = $o['status'] === 'success' ? 'success' : ($o['status'] === 'refunded' ? 'gray' : ($o['status'] === 'failed' ? 'gray' : 'pending'));
    $extra = json_decode($o['extra_data'] ?? '', true);
?>
<div class="order-item">
  <div class="order-top">
    <div style="display:flex;gap:10px;align-items:flex-start;">
      <i class="fi <?= $icon ?>" style="font-size:18px;color:var(--primary);margin-top:2px;"></i>
      <div>
        <div class="order-title"><?= clean(ucfirst($o['service_code'])) ?><?= $o['plan_name'] ? ' — ' . clean($o['plan_name']) : '' ?></div>
        <div class="order-meta" style="font-size:12px;"><?= clean($o['provider_name'] ?? '') ?> · <?= clean($o['recipient'] ?? '') ?></div>
      </div>
    </div>
    <span class="pill pill-<?= $pillClass ?>"><?= clean(ucfirst($o['status'])) ?></span>
  </div>

  <?php if (!empty($extra['token'])): ?>
  <div style="font-size:12px;color:var(--text2);background:var(--bg2);padding:8px 10px;border-radius:6px;margin:8px 0;word-break:break-all;">
    <i class="fi fi-rr-key"></i> Token: <?= clean($extra['token']) ?>
  </div>
  <?php endif; ?>

  <div class="d-flex justify-between align-center" style="margin-top:8px;">
    <span class="order-meta"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
    <span class="order-amount">₦<?= number_format($o['amount_ngn'], 2) ?></span>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
