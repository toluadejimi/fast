<?php
// ============================================================
//  DonnieSMS OTP – pages/otp-active.php  (UPGRADED v2)
//  + Auto-refund on EXPIRED/TIMEOUT
//  + Multi-provider support (4 providers)
// ============================================================

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/fivesim.php';
require_once ROOT_PATH . '/includes/verifysms.php';
require_once ROOT_PATH . '/includes/smsman.php';
require_once ROOT_PATH . '/includes/otpsuite.php';
require_once ROOT_PATH . '/includes/logsplug.php';

$pageTitle = 'Active OTP Order';
require_once ROOT_PATH . '/includes/header.php';

// ── Load order ───────────────────────────────────────────────
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM otp_orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span>Order not found.</span></div>';
    require_once ROOT_PATH . '/includes/footer.php';
    exit;
}

// ── Which provider? ──────────────────────────────────────────
$orderProvider = $order['operator']; // 'fivesim', 'verifysms', 'provider3', 'provider4'
$providerLabel = get_setting('provider1_label', '5sim'); // default
if ($orderProvider === 'verifysms')  $providerLabel = get_setting('provider2_label', 'VerifySMS');
if ($orderProvider === 'smsman')      $providerLabel = get_setting('provider3_label', 'SMS-Man');
if ($orderProvider === 'otpsuite')   $providerLabel = get_setting('provider4_label', 'USA WhatsApp');
if ($orderProvider === 'logsplug')   $providerLabel = get_setting('provider5_label', 'Saver 3');

// ── Auto-expire + AUTO REFUND on EXPIRED/TIMEOUT ────────────
if ($order['status'] === 'PENDING' && strtotime($order['expires_at']) < time()) {
    $pdo->prepare("UPDATE otp_orders SET status='EXPIRED' WHERE id=?")->execute([$orderId]);
    $order['status'] = 'EXPIRED';

    // ✅ Auto-refund expired orders — use refunded column to prevent double-refund
    if (!(int)($order['refunded'] ?? 0)) {
        $refunded = wallet_credit(
            $user['id'],
            (float)$order['amount_paid'],
            'Auto-Refund – OTP #' . $orderId . ' expired',
            gen_ref('REF')
        );
        if ($refunded) {
            $pdo->prepare("UPDATE otp_orders SET refunded=1 WHERE id=?")->execute([$orderId]);
            track_spend($user['id'], -(float)$order['amount_paid']);
            notify(
                $user['id'],
                'Auto Refund',
                'OTP #' . $orderId . ' expired. $' . number_format((float)$order['amount_paid'], 4) . ' refunded to your wallet.',
                '/pages/history.php'
            );
        }
    }
}

// ── Handle POST actions ──────────────────────────────────────
$actionError   = '';
$actionSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $actionError = 'Invalid request. Please refresh and try again.';

    } elseif ($order['status'] !== 'PENDING') {
        $actionError = 'This order is no longer active (status: ' . $order['status'] . ').';

    } else {

        $action = $_POST['action'];

        // Get correct provider instance
        if ($orderProvider === 'verifysms') {
            $sim = new VerifySMS();
        } elseif ($orderProvider === 'smsman') {
            $sim = new SmsMan();
        } elseif ($orderProvider === 'otpsuite') {
            $sim = new OtpSuite();
        } elseif ($orderProvider === 'logsplug') {
            $sim = new LogsPlug();
        } else {
            $sim = new FiveSim();
        }

        // ── CANCEL ───────────────────────────────────────────
        if ($action === 'cancel') {

            $result = $sim->cancelOrder($order['order_id']);
            $cancelled = is_array($result) ? !empty($result['success']) : (bool)$result;

            if ($cancelled) {
                $pdo->prepare("UPDATE otp_orders SET status='CANCELED' WHERE id=?")->execute([$orderId]);

                wallet_credit(
                    $user['id'],
                    (float)$order['amount_paid'],
                    'Refund – OTP #' . $orderId . ' cancelled',
                    gen_ref('REF')
                );
                track_spend($user['id'], -(float)$order['amount_paid']);

                notify(
                    $user['id'],
                    'Order Cancelled & Refunded',
                    'OTP order #' . $orderId . ' cancelled. $' . number_format((float)$order['amount_paid'], 4) . ' refunded.',
                    '/pages/history.php'
                );

                set_flash('success', 'Order cancelled. $' . number_format((float)$order['amount_paid'], 4) . ' refunded to your wallet.');
                redirect(SITE_URL . '/pages/history.php');

            } else {
                $errMsg = is_array($result) ? ($result['message'] ?? 'Unknown error') : 'Cancel request failed.';
                notify($user['id'], 'Cancel Issue', 'OTP #' . $orderId . ' cancel had an issue. Contact support.', '/pages/history.php');
                $actionError = 'Could not cancel with provider. Your money was NOT deducted. Please try again or contact support. Ref: OTP#' . $orderId;
                if ($orderProvider === 'logsplug') {
                    $actionError .= ' [debug: ' . str_ireplace('logsplug', 'provider', $errMsg) . ']';
                }
            }

        // ── FINISH ───────────────────────────────────────────
        } elseif ($action === 'finish') {

            $sim->finishOrder($order['order_id']);
            $pdo->prepare("UPDATE otp_orders SET status='RECEIVED' WHERE id=?")->execute([$orderId]);
            set_flash('success', 'Order marked as finished.');
            redirect(SITE_URL . '/pages/history.php');

        // ── BAN ──────────────────────────────────────────────
        } elseif ($action === 'ban') {

            $sim->banOrder($order['order_id']);
            $pdo->prepare("UPDATE otp_orders SET status='BANNED' WHERE id=?")->execute([$orderId]);

            if ($orderProvider !== 'verifysms') {
                wallet_credit(
                    $user['id'],
                    (float)$order['amount_paid'],
                    'Refund – OTP #' . $orderId . ' banned/wrong number',
                    gen_ref('REF')
                );
                track_spend($user['id'], -(float)$order['amount_paid']);
                notify($user['id'], 'Number Banned & Refunded', 'OTP #' . $orderId . ' banned and refunded.', '/pages/history.php');
                set_flash('success', 'Number banned and refunded.');
            } else {
                set_flash('info', 'Number marked as banned. Contact support for a refund.');
            }

            redirect(SITE_URL . '/pages/history.php');
        }
    }

    // Reload order after action
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();
}

$isPending   = ($order['status'] === 'PENDING');
$isReceived  = ($order['status'] === 'RECEIVED');
$isCancelled = ($order['status'] === 'CANCELED');
$isExpired   = in_array($order['status'], ['EXPIRED', 'TIMEOUT']);
$isBanned    = ($order['status'] === 'BANNED');
$isDone      = !$isPending;

$timeLeft = max(0, strtotime($order['expires_at']) - time());
?>

<?php if ($actionError): ?>
<div class="alert alert-danger">
  <i class="fi fi-rr-cross-circle"></i>
  <span><?= clean($actionError) ?></span>
</div>
<?php endif; ?>

<?php if ($actionSuccess): ?>
<div class="alert alert-success">
  <i class="fi fi-rr-check"></i>
  <span><?= clean($actionSuccess) ?></span>
</div>
<?php endif; ?>

<!-- Expired banner with refund notice -->
<?php if ($isExpired): ?>
<div class="alert alert-info" style="margin-bottom:14px;">
  <i class="fi fi-rr-refresh"></i>
  <span>This number expired. <strong>$<?= number_format((float)$order['amount_paid'],4) ?> has been automatically refunded</strong> to your wallet.</span>
</div>
<?php endif; ?>

<!-- Order header -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
  <a href="<?= SITE_URL ?>/pages/numbers.php"
     style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--glass);border-radius:50%;text-decoration:none;color:var(--text);">
    <i class="fi fi-rr-arrow-left"></i>
  </a>
  <h2 style="font-size:18px;font-weight:900;margin:0;">Active Number</h2>
</div>

<!-- Service card -->
<div class="glass" style="border-radius:var(--radius);padding:20px;margin-bottom:16px;text-align:center;">

  <div style="font-size:13px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
    <?= clean($order['service_name']) ?>
  </div>

  <div style="font-size:26px;font-weight:900;font-family:monospace;color:var(--primary);letter-spacing:1px;margin-bottom:6px;">
    <?= clean($order['phone']) ?>
  </div>
  <button onclick="copyText('<?= clean($order['phone']) ?>',this)"
          class="btn btn-outline btn-sm" style="margin-bottom:14px;">
    <i class="fi fi-rr-copy"></i> Copy Number
  </button>

  <!-- Status badge -->
  <div style="margin-bottom:10px;">
    <?php if ($isPending): ?>
      <span class="pill pill-pending" style="font-size:13px;padding:6px 14px;">
        <i class="fi fi-rr-clock"></i> Waiting for OTP…
      </span>
    <?php elseif ($isReceived): ?>
      <span class="pill pill-success" style="font-size:13px;padding:6px 14px;">
        <i class="fi fi-rr-check"></i> OTP Received
      </span>
    <?php elseif ($isCancelled): ?>
      <span class="pill pill-gray" style="font-size:13px;padding:6px 14px;">
        <i class="fi fi-rr-cross"></i> Cancelled & Refunded
      </span>
    <?php elseif ($isExpired): ?>
      <span class="pill pill-gray" style="font-size:13px;padding:6px 14px;">
        <i class="fi fi-rr-time-past"></i> Expired · Auto-Refunded
      </span>
    <?php elseif ($isBanned): ?>
      <span class="pill pill-gray" style="font-size:13px;padding:6px 14px;">
        <i class="fi fi-rr-ban"></i> Banned
      </span>
    <?php endif; ?>
  </div>

  <!-- OTP code -->
  <?php if (!empty($order['otp_code'])): ?>
  <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:16px;margin-top:8px;">
    <div style="font-size:12px;color:var(--text3);margin-bottom:4px;">Your OTP Code</div>
    <div id="otp-display" style="font-size:36px;font-weight:900;font-family:monospace;color:var(--primary);letter-spacing:4px;">
      <?= clean($order['otp_code']) ?>
    </div>
    <button onclick="copyText('<?= clean($order['otp_code']) ?>',this)"
            class="btn btn-primary btn-sm" style="margin-top:8px;">
      <i class="fi fi-rr-copy"></i> Copy OTP
    </button>
  </div>
  <?php endif; ?>

  <!-- Timer (pending only) -->
  <?php if ($isPending): ?>
  <div style="margin-top:12px;font-size:12px;color:var(--text3);">
    Expires in: <strong id="countdown" style="color:var(--primary);">--:--</strong>
  </div>
  <?php endif; ?>

</div>

<!-- OTP polling area (pending only) -->
<?php if ($isPending && empty($order['otp_code'])): ?>
<div id="poll-box" style="background:var(--glass);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;">
  <div id="poll-spinner" style="margin-bottom:8px;">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round">
      <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83">
        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
      </path>
    </svg>
  </div>
  <div id="poll-msg" style="font-size:13px;color:var(--text3);">Waiting for SMS… checking every 5 seconds</div>
</div>
<?php endif; ?>

<!-- Meta info -->
<div class="glass" style="border-radius:var(--radius);padding:14px;margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
    <span style="font-size:13px;color:var(--text3);">Order Ref</span>
    <span style="font-size:13px;font-weight:700;font-family:monospace;">OTP#<?= $orderId ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
    <span style="font-size:13px;color:var(--text3);">Service</span>
    <span style="font-size:13px;font-weight:700;"><?= clean($order['service_name']) ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
    <span style="font-size:13px;color:var(--text3);">Country</span>
    <span style="font-size:13px;font-weight:700;"><?= strtoupper(clean($order['country'])) ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
    <span style="font-size:13px;color:var(--text3);">Provider</span>
    <span style="font-size:13px;font-weight:700;color:var(--primary);"><?= clean($providerLabel) ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
    <span style="font-size:13px;color:var(--text3);">Amount Paid</span>
    <span style="font-size:13px;font-weight:700;color:var(--danger);">-$<?= number_format((float)$order['amount_paid'],4) ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:6px 0;">
    <span style="font-size:13px;color:var(--text3);">Ordered At</span>
    <span style="font-size:13px;font-weight:700;"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
  </div>
</div>

<!-- Action buttons (pending only) -->
<?php if ($isPending): ?>
<div style="display:flex;gap:10px;margin-bottom:16px;">
  <button onclick="confirmAction('cancel')" class="btn btn-outline btn-block" style="flex:1;color:var(--danger);border-color:var(--danger);">
    <i class="fi fi-rr-cross-circle"></i> Cancel & Refund
  </button>
  <?php if ($orderProvider === 'fivesim'): ?>
  <button onclick="confirmAction('ban')" class="btn btn-outline btn-sm" style="color:var(--text3);" title="Number doesn't work?">
    <i class="fi fi-rr-ban"></i>
  </button>
  <?php endif; ?>
</div>
<p style="font-size:11px;color:var(--text3);text-align:center;margin-top:-8px;">
  Cancel refunds your money instantly. Numbers also auto-refund if they expire.
</p>
<?php endif; ?>

<!-- Done state -->
<?php if ($isDone): ?>
<a href="<?= SITE_URL ?>/pages/numbers.php" class="btn btn-primary btn-block">
  <i class="fi fi-rr-mobile"></i> Buy Another Number
</a>
<a href="<?= SITE_URL ?>/pages/history.php" class="btn btn-ghost btn-block mt-1">
  <i class="fi fi-rr-list"></i> View History
</a>
<?php endif; ?>

<!-- Hidden action form -->
<form method="POST" id="action-form" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" id="form-action" value="">
</form>

<!-- Confirm modal -->
<div class="modal-overlay" id="confirm-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <h3 class="modal-title" id="confirm-title">Confirm Action</h3>
    <p id="confirm-msg" class="text-muted text-sm" style="margin-bottom:16px;text-align:center;"></p>
    <button onclick="submitAction()" class="btn btn-primary btn-block" id="confirm-btn">Confirm</button>
    <button onclick="closeModal('confirm-modal')" class="btn btn-ghost btn-block mt-1">Cancel</button>
  </div>
</div>

<script>
// ── Countdown timer ──────────────────────────────────────────
(function() {
  var expiresAt = <?= strtotime($order['expires_at']) ?> * 1000;
  var el = document.getElementById('countdown');
  if (!el) return;

  function tick() {
    var left = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
    var m = Math.floor(left / 60);
    var s = left % 60;
    el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    if (left <= 60) el.style.color = 'var(--danger)';
    if (left === 0) {
      el.textContent = 'Expired';
      clearInterval(timer);
      // Auto reload — will trigger server-side refund
      setTimeout(function() { location.reload(); }, 1500);
    }
  }

  tick();
  var timer = setInterval(tick, 1000);
})();

// ── OTP Polling ──────────────────────────────────────────────
<?php if ($isPending && empty($order['otp_code'])): ?>
(function() {
  var pollInterval = null;
  var pollCount    = 0;
  var maxPolls     = <?= max(1, (int)(($timeLeft) / 5)) ?>;
  var orderId      = <?= (int)$orderId ?>;

  function poll() {
    pollCount++;
    fetch('<?= SITE_URL ?>/api/check-otp.php?id=' + orderId, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.otp) {
          clearInterval(pollInterval);
          document.getElementById('poll-box').innerHTML =
            '<div style="font-size:36px;font-weight:900;font-family:monospace;color:var(--primary);letter-spacing:4px;">' +
            data.otp + '</div>' +
            '<button onclick="copyText(\'' + data.otp + '\',this)" class="btn btn-primary btn-sm" style="margin-top:8px;">' +
            '<i class="fi fi-rr-copy"></i> Copy OTP</button>';
          var badge = document.querySelector('.pill.pill-pending');
          if (badge) { badge.className='pill pill-success'; badge.innerHTML='<i class="fi fi-rr-check"></i> OTP Received'; }
          return;
        }
        var status = (data.status || '').toUpperCase();
        if (data.debug) {
          var dbg = document.getElementById('poll-debug');
          if (!dbg) {
            dbg = document.createElement('div');
            dbg.id = 'poll-debug';
            dbg.style.cssText = 'font-size:10px;color:var(--text3);font-family:monospace;margin-top:10px;padding:8px;background:var(--glass);border-radius:8px;word-break:break-all;text-align:left;';
            document.getElementById('poll-msg').insertAdjacentElement('afterend', dbg);
          }
          dbg.textContent = 'Last raw response: ' + data.debug;
        }
        if (status === 'EXPIRED' || status === 'TIMEOUT' || status === 'CANCELED' || status === 'BANNED') {
          clearInterval(pollInterval);
          location.reload();
          return;
        }
        if (pollCount >= maxPolls) {
          clearInterval(pollInterval);
          document.getElementById('poll-msg').textContent = 'Time expired. Refreshing…';
          setTimeout(function() { location.reload(); }, 2000);
        }
      })
      .catch(function() {});
  }

  setTimeout(function() { poll(); pollInterval = setInterval(poll, 5000); }, 3000);
})();
<?php endif; ?>

function confirmAction(action) {
  var msgs = {
    cancel: { title:'Cancel & Refund?', msg:'Your money will be instantly refunded to your wallet.', btn:'Yes, Cancel & Refund' },
    ban:    { title:'Report Bad Number?', msg:'This marks the number as not working. You will be refunded.', btn:'Report & Refund' }
  };
  var m = msgs[action] || { title:'Confirm', msg:'', btn:'OK' };
  document.getElementById('confirm-title').textContent = m.title;
  document.getElementById('confirm-msg').textContent   = m.msg;
  document.getElementById('confirm-btn').textContent   = m.btn;
  document.getElementById('form-action').value         = action;
  openModal('confirm-modal');
}

function submitAction() {
  closeModal('confirm-modal');
  document.getElementById('action-form').submit();
}

function openModal(id)  { var e = document.getElementById(id); if(e) e.classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { var e = document.getElementById(id); if(e) e.classList.remove('open'); document.body.style.overflow=''; }
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
