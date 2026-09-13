<?php
// ============================================================
//  DonnieSMS - pages/social-orders.php
//  Order history for Social Boost (SMM) orders, with live status
//  check, refill request, and cancel request.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/momopanel.php';
$pageTitle = 'Social Boost Orders';
require_once ROOT_PATH . '/includes/header.php';

$momo = new MomoPanel();
$notice = '';

// ── Handle refill / cancel requests ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $notice = 'Invalid request.';
    } else {
        $orderDbId = (int) ($_POST['order_db_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM social_orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$orderDbId, $user['id']]);
        $order = $stmt->fetch();

        if (!$order) {
            $notice = 'Order not found.';
        } elseif (isset($_POST['request_refill'])) {
            if (!$order['can_refill']) {
                $notice = 'This service does not support refills.';
            } else {
                $r = $momo->createRefill($order['momo_order_id']);
                if ($r['success'] && isset($r['data']['refill'])) {
                    $pdo->prepare("UPDATE social_orders SET refill_id = ?, refill_status = 'Requested' WHERE id = ?")
                        ->execute([(string) $r['data']['refill'], $orderDbId]);
                    $notice = 'Refill requested successfully.';
                } else {
                    $notice = 'Refill request failed: ' . ($r['error'] ?? 'Unknown error');
                }
            }
        } elseif (isset($_POST['request_cancel'])) {
            if (!$order['can_cancel']) {
                $notice = 'This service does not support cancellation.';
            } else {
                $r = $momo->cancelOrders([$order['momo_order_id']]);
                $cancelled = false;
                if ($r['success'] && is_array($r['data'])) {
                    foreach ($r['data'] as $c) {
                        if ((string) ($c['order'] ?? '') === $order['momo_order_id'] && ($c['cancel'] ?? null) == 1) {
                            $cancelled = true;
                        }
                    }
                }
                if ($cancelled) {
                    // Refund the user — order didn't complete
                    wallet_credit($user['id'], (float) $order['charged_price'], 'Refund – Social Boost order cancelled', gen_ref('SMMR'));
                    track_spend($user['id'], -(float) $order['charged_price']);
                    $pdo->prepare("UPDATE social_orders SET status = 'Canceled' WHERE id = ?")->execute([$orderDbId]);
                    $notice = 'Order cancelled and refunded.';
                } else {
                    $notice = 'Cancel request failed or was rejected by the provider.';
                }
            }
        }
    }
}

$orders = $pdo->prepare("SELECT * FROM social_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 80");
$orders->execute([$user['id']]);
$orderList = $orders->fetchAll();
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">Social Boost Orders</h2>

<?php if ($notice): ?>
<div class="alert alert-info"><i class="fi fi-rr-info"></i><span><?= clean($notice) ?></span></div>
<?php endif; ?>

<?php if (empty($orderList)): ?>
<div class="empty">
  <i class="fi fi-rr-share" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No Social Boost orders yet.</p>
  <a href="<?= SITE_URL ?>/pages/social-boost.php" class="btn btn-primary mt-2" style="display:inline-flex;">
    <i class="fi fi-rr-rocket-lunch"></i> Place an Order
  </a>
</div>
<?php else: ?>
<?php foreach ($orderList as $o):
    $statusLower = strtolower($o['status']);
    $pillClass = 'gray';
    if (strpos($statusLower, 'complet') !== false) $pillClass = 'success';
    elseif (strpos($statusLower, 'progress') !== false || strpos($statusLower, 'pending') !== false) $pillClass = 'pending';
    elseif (strpos($statusLower, 'cancel') !== false) $pillClass = 'gray';
    elseif (strpos($statusLower, 'partial') !== false) $pillClass = 'pending';
?>
<div class="order-item" id="order-<?= (int)$o['id'] ?>">
  <div class="order-top">
    <div>
      <div class="order-title"><?= clean($o['service_name']) ?></div>
      <div class="order-meta" style="font-size:12px;"><?= number_format($o['quantity']) ?>x &middot; <?= clean($o['category']) ?></div>
      <div class="order-meta" style="font-size:11px;word-break:break-all;"><?= clean($o['link']) ?></div>
    </div>
    <span class="pill pill-<?= $pillClass ?>" id="status-<?= (int)$o['id'] ?>"><?= clean($o['status']) ?></span>
  </div>

  <div class="d-flex justify-between align-center" style="margin-top:8px;flex-wrap:wrap;gap:6px;">
    <span class="order-meta"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
    <div class="d-flex gap-1 align-center" style="flex-wrap:wrap;">
      <span class="order-amount"><?= fmt_money((float)$o['charged_price']) ?></span>

      <button type="button" class="btn btn-outline btn-sm" onclick="checkStatus(<?= (int)$o['id'] ?>)">
        <i class="fi fi-rr-refresh"></i> Check Status
      </button>

      <?php if ($o['can_refill'] && strtolower($o['status']) !== 'canceled' && empty($o['refill_id'])): ?>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Request a refill for this order?')">
        <?= csrf_field() ?>
        <input type="hidden" name="order_db_id" value="<?= (int)$o['id'] ?>">
        <button type="submit" name="request_refill" value="1" class="btn btn-outline btn-sm">
          <i class="fi fi-rr-rotate-right"></i> Refill
        </button>
      </form>
      <?php elseif (!empty($o['refill_id'])): ?>
      <span class="order-meta" style="font-size:11px;">Refill: <?= clean($o['refill_status'] ?? 'Requested') ?></span>
      <?php endif; ?>

      <?php if ($o['can_cancel'] && !in_array(strtolower($o['status']), ['completed', 'canceled'])): ?>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this order? If successful you will be refunded.')">
        <?= csrf_field() ?>
        <input type="hidden" name="order_db_id" value="<?= (int)$o['id'] ?>">
        <button type="submit" name="request_cancel" value="1" class="btn btn-danger btn-sm">
          <i class="fi fi-rr-cross"></i> Cancel
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

async function checkStatus(dbId) {
    const badge = document.getElementById('status-' + dbId);
    const originalText = badge.textContent;
    badge.textContent = '...';
    try {
        const res = await fetch('<?= SITE_URL ?>/api/check-social-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ csrf_token: CSRF, order_db_id: dbId })
        });
        const json = await res.json();
        if (json.success) {
            badge.textContent = json.status;
        } else {
            badge.textContent = originalText;
            alert(json.error || 'Could not check status.');
        }
    } catch (e) {
        badge.textContent = originalText;
        alert('Network error.');
    }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
