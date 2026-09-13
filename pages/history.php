<?php
// ============================================================
//  pages/history.php  (UPGRADED – USD wallet display)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Order History – ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';

$tab = $_GET['tab'] ?? 'orders';

$orders = $pdo->prepare("SELECT * FROM otp_orders WHERE user_id=? ORDER BY created_at DESC LIMIT 80");
$orders->execute([$user['id']]); $orderList = $orders->fetchAll();

$txns = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 80");
$txns->execute([$user['id']]); $txnList = $txns->fetchAll();
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">History</h2>

<div class="tabs-wrap">
  <a href="?tab=orders" class="tab-btn <?= $tab==='orders'?'active':'' ?>">OTP Orders</a>
  <a href="?tab=wallet" class="tab-btn <?= $tab==='wallet'?'active':'' ?>">Wallet</a>
</div>

<?php if ($tab === 'orders'): ?>
<?php if (empty($orderList)): ?>
<div class="empty">
  <i class="fi fi-rr-mobile" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No OTP orders yet.</p>
  <a href="<?= SITE_URL ?>/pages/numbers.php" class="btn btn-primary mt-2" style="display:inline-flex;">
    <i class="fi fi-rr-mobile"></i> Buy a Number
  </a>
</div>
<?php else: ?>
<?php foreach ($orderList as $o): ?>
<div class="order-item">
  <div class="order-top">
    <div>
      <div class="order-title"><?= clean($o['service_name']) ?></div>
      <div class="order-meta" style="font-family:monospace;font-size:12px;"><?= clean($o['phone']) ?></div>
    </div>
    <span class="pill pill-<?= $o['status']==='RECEIVED'?'success':($o['status']==='PENDING'?'pending':'gray') ?>">
      <?= ucfirst(strtolower($o['status'])) ?>
    </span>
  </div>
  <div class="d-flex justify-between align-center" style="margin-top:8px;">
    <span class="order-meta"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
    <div class="d-flex gap-1 align-center">
      <span class="order-amount"><?= fmt_money((float)$o['amount_paid']) ?></span>
      <?php if ($o['status'] === 'PENDING'): ?>
      <a href="<?= SITE_URL ?>/pages/otp-active.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm">View</a>
      <?php elseif ($o['otp_code']): ?>
      <button onclick="copyText('<?= clean($o['otp_code']) ?>',this)"
              class="btn btn-outline btn-sm" style="font-family:monospace;">
        <?= clean($o['otp_code']) ?> <i class="fi fi-rr-copy"></i>
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>
<?php if (empty($txnList)): ?>
<div class="empty">
  <i class="fi fi-rr-wallet" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No transactions yet.</p>
</div>
<?php else: ?>
<?php foreach ($txnList as $t): ?>
<div class="order-item">
  <div class="order-top">
    <div>
      <div class="order-title"><?= clean($t['description']) ?></div>
      <div class="order-meta" style="font-family:monospace;font-size:11px;"><?= clean($t['reference']) ?></div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:16px;font-weight:900;color:<?= $t['type']==='credit'?'var(--success)':'var(--danger)' ?>;">
        <?= $t['type']==='credit'?'+':'-' ?><?= fmt_money((float)$t['amount']) ?>
      </div>
      <span class="pill pill-<?= $t['status']==='success'?'success':'pending' ?>"><?= ucfirst($t['status']) ?></span>
    </div>
  </div>
  <div class="order-meta" style="margin-top:6px;"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
