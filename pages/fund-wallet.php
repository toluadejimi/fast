<?php
// ============================================================
//  pages/fund-wallet.php  (UPGRADED)
//  – Balance displayed in USD
//  – Paystack charged in NGN, live rate shown
//  – Min $1 / Max $50 enforced
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Fund Wallet – ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';
require_once ROOT_PATH . '/includes/ncwallet.php';
require_once ROOT_PATH . '/includes/paymentpoint.php';
require_once ROOT_PATH . '/includes/sprintpay.php';

$va        = get_user_va($user['id']);
$ppActive  = (bool)$pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='paymentpoint'")->fetchColumn();
$ncActive  = (bool)$pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='ncwallet'")->fetchColumn();
$psActive  = (bool)$pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='paystack'")->fetchColumn();
$spActive  = (bool)$pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='sprintpay'")->fetchColumn();

// ── AFRICA-WIDE REGISTRATION: Bank Transfer (PaymentPoint/NCWallet/Paystack VA)
// is Nigeria-only. Card payment (further below) works for every country.
// $showBankTransfer is the single flag to build your redesign around —
// wrap whichever block(s) you want hidden for non-Nigerian users in it.
$showBankTransfer = ($ppActive || $ncActive || $psActive) && country_supports_ncwallet($user['country'] ?? 'Nigeria');

$minUsd    = get_min_topup_usd();   // $1
$maxUsd    = get_max_topup_usd();   // $50
$rate      = get_usd_to_ngn_rate(); // live rate
$error     = '';

// Minimum NGN equivalents for quick buttons
$quickAmounts = [1, 2, 5, 10, 20, 50]; // USD amounts

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['pay_card']) || isset($_POST['pay_sprintpay']))) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $amountUsd = (float)($_POST['amount_usd'] ?? 0);
        if ($amountUsd < $minUsd) {
            $error = 'Minimum top-up is $' . $minUsd . '.';
        } elseif ($amountUsd > $maxUsd) {
            $error = 'Maximum top-up is $' . $maxUsd . '.';
        } elseif (isset($_POST['pay_sprintpay'])) {
            $ref    = gen_ref('SP');
            $result = sprintpay_init_payment($user, $amountUsd, $ref);
            if ($result['success']) {
                redirect($result['url']);
            } else {
                $error = $result['message'];
            }
        } else {
            $ref    = gen_ref('PAY');
            $result = paystack_init_payment($user, $amountUsd, $ref);
            if ($result['success']) {
                redirect($result['url']);
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">Fund Wallet</h2>

<!-- USD Balance Card -->
<div class="balance-card" style="margin-bottom:16px;">
  <div class="bal-label">Current Balance</div>
  <div class="bal-amount"><?= fmt_money((float)$user['balance']) ?></div>
  <div style="font-size:12px;opacity:.7;margin-top:4px;">
    ≈ <?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>
    &nbsp;·&nbsp; Rate: ₦<?= number_format($rate, 0) ?>/$
  </div>
</div>

<!-- Rate Info Banner -->
<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="fi fi-rr-dollar"></i>
  <div>
    <strong>Live Exchange Rate:</strong> $1 USD = ₦<?= number_format($rate, 2) ?>
    &nbsp;·&nbsp; Min: $<?= $minUsd ?> &nbsp;·&nbsp; Max: $<?= $maxUsd ?>
  </div>
</div>

<!-- Bank Transfer via Virtual Account — Nigeria only, see $showBankTransfer above -->
<?php if ($showBankTransfer): ?>
<div class="glass" style="padding:20px;margin-bottom:12px;">
  <div class="d-flex align-center gap-2" style="margin-bottom:14px;">
    <div style="width:44px;height:44px;border-radius:12px;background:var(--primary-light);
                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fi fi-rr-bank" style="font-size:20px;color:var(--primary);"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:800;font-size:15px;">Bank Transfer</div>
      <div class="text-muted text-sm">Instant · Any Nigerian bank · Auto-converts to USD</div>
    </div>
    <span class="pill pill-success">Recommended</span>
  </div>

  <?php if ($va): ?>
  <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:16px;
              border:1.5px solid rgba(124,58,237,.15);">
    <div class="text-xs text-muted fw-700" style="margin-bottom:4px;letter-spacing:1px;">
      YOUR ACCOUNT · <?= clean($va['bank_name']) ?>
    </div>
    <div style="font-size:26px;font-weight:900;letter-spacing:3px;color:var(--primary);">
      <?= clean($va['account_number']) ?>
      <button class="copy-btn" style="color:var(--primary);font-size:15px;margin-left:6px;"
              onclick="copyText('<?= clean($va['account_number']) ?>',this)">
        <i class="fi fi-rr-copy"></i>
      </button>
    </div>
    <div class="text-muted text-sm" style="margin-top:5px;">
      <?= clean($va['account_name']) ?>
    </div>
  </div>
  <div class="alert alert-success" style="margin-top:12px;margin-bottom:0;">
    <i class="fi fi-rr-check"></i>
    <span>
      Transfer any amount (min ₦<?= number_format(usd_to_ngn($minUsd), 0) ?> ≈ $<?= $minUsd ?>).
      Your wallet is credited <strong>in USD</strong> automatically within minutes.
    </span>
  </div>
  <?php else: ?>
  <div class="alert alert-warning" style="margin-bottom:0;">
    <i class="fi fi-rr-triangle-warning"></i>
    <span>No virtual account yet.
      <a href="<?= SITE_URL ?>/pages/dashboard.php" style="font-weight:700;">Generate from dashboard →</a>
    </span>
  </div>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- Non-Nigerian user: bank transfer isn't available, point them to card below.
     Feel free to redesign/remove this — it's just a placeholder. -->
<div class="glass" style="padding:16px;margin-bottom:12px;">
  <div class="text-muted text-sm"><i class="fi fi-rr-info"></i> Bank transfer funding is available for Nigerian accounts only. Use the card option below to fund your wallet.</div>
</div>
<?php endif; ?>

<!-- Pay with Paystack (NGN → USD) -->
<?php if ($psActive): ?>
<div class="glass" style="padding:20px;margin-bottom:12px;">
  <div class="d-flex align-center gap-2" style="margin-bottom:14px;">
    <div style="width:44px;height:44px;border-radius:12px;background:#e8f5e9;
                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fi fi-rr-credit-card" style="font-size:20px;color:var(--success);"></i>
    </div>
    <div>
      <div style="font-weight:800;font-size:15px;">Pay with Card / USSD</div>
      <div class="text-muted text-sm">Paystack · Pay in Naira · Credited in USD</div>
    </div>
  </div>

  <!-- Live rate callout -->
  <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);
              border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;">
    <div style="font-size:13px;font-weight:700;color:var(--success);margin-bottom:2px;">
      💱 Current Rate: $1 = ₦<?= number_format($rate, 2) ?>
    </div>
    <div style="font-size:12px;color:var(--text2);">
      Min: $<?= $minUsd ?> = ₦<?= number_format(usd_to_ngn($minUsd), 0) ?> &nbsp;|&nbsp;
      Max: $<?= $maxUsd ?> = ₦<?= number_format(usd_to_ngn($maxUsd), 0) ?>
    </div>
  </div>

  <form method="POST" id="paystack-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Amount (USD) <span style="color:var(--text3);font-weight:400;">— you pay in Naira</span></label>
      <div class="input-wrap">
        <i class="fi fi-rr-dollar"></i>
        <input type="number" name="amount_usd" id="pay-amount-usd"
               class="form-control"
               placeholder="Enter USD amount (min $<?= $minUsd ?>, max $<?= $maxUsd ?>)"
               min="<?= $minUsd ?>" max="<?= $maxUsd ?>" step="0.5" required
               oninput="updateNgnPreview(this.value)">
      </div>
    </div>

    <!-- NGN equivalent live preview -->
    <div id="ngn-preview" style="display:none;background:var(--bg3);border-radius:8px;
         padding:10px 14px;margin-bottom:14px;font-size:13px;">
      You will be charged: <strong id="ngn-amount" style="color:var(--primary);">—</strong>
      &nbsp;via Paystack
    </div>

    <!-- Quick USD amounts -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
      <?php foreach ($quickAmounts as $amt): ?>
      <button type="button"
              onclick="setAmount(<?= $amt ?>)"
              class="btn btn-ghost btn-sm">$<?= $amt ?></button>
      <?php endforeach; ?>
    </div>

    <button type="submit" name="pay_card" class="btn btn-success btn-block">
      <i class="fi fi-rr-credit-card"></i> Pay with Paystack
    </button>
  </form>
</div>
<?php endif; ?>

<!-- Pay with SprintPay (NGN → USD) -->
<?php if ($spActive): ?>
<div class="glass" style="padding:20px;margin-bottom:12px;">
  <div class="d-flex align-center gap-2" style="margin-bottom:14px;">
    <div style="width:44px;height:44px;border-radius:12px;background:#e0f2fe;
                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fi fi-rr-credit-card" style="font-size:20px;color:#0284c7;"></i>
    </div>
    <div>
      <div style="font-weight:800;font-size:15px;">Pay with SprintPay</div>
      <div class="text-muted text-sm">Bank · Card · Crypto · Pay in Naira · Credited in USD</div>
    </div>
  </div>

  <div style="background:rgba(2,132,199,.08);border:1px solid rgba(2,132,199,.25);
              border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;">
    <div style="font-size:13px;font-weight:700;color:#0284c7;margin-bottom:2px;">
      Current Rate: $1 = ₦<?= number_format($rate, 2) ?>
    </div>
    <div style="font-size:12px;color:var(--text2);">
      Min: $<?= $minUsd ?> = ₦<?= number_format(usd_to_ngn($minUsd), 0) ?> &nbsp;|&nbsp;
      Max: $<?= $maxUsd ?> = ₦<?= number_format(usd_to_ngn($maxUsd), 0) ?>
    </div>
  </div>

  <form method="POST" id="sprintpay-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Amount (USD) <span style="color:var(--text3);font-weight:400;">— you pay in Naira</span></label>
      <div class="input-wrap">
        <i class="fi fi-rr-dollar"></i>
        <input type="number" name="amount_usd" id="sp-amount-usd"
               class="form-control"
               placeholder="Enter USD amount (min $<?= $minUsd ?>, max $<?= $maxUsd ?>)"
               min="<?= $minUsd ?>" max="<?= $maxUsd ?>" step="0.5" required
               oninput="updateSpNgnPreview(this.value)">
      </div>
    </div>

    <div id="sp-ngn-preview" style="display:none;background:var(--bg3);border-radius:8px;
         padding:10px 14px;margin-bottom:14px;font-size:13px;">
      You will be charged: <strong id="sp-ngn-amount" style="color:var(--primary);">—</strong>
      &nbsp;via SprintPay
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
      <?php foreach ($quickAmounts as $amt): ?>
      <button type="button"
              onclick="setSpAmount(<?= $amt ?>)"
              class="btn btn-ghost btn-sm">$<?= $amt ?></button>
      <?php endforeach; ?>
    </div>

    <button type="submit" name="pay_sprintpay" class="btn btn-primary btn-block">
      <i class="fi fi-rr-credit-card"></i> Continue to SprintPay
    </button>
  </form>
</div>
<?php endif; ?>

<?php if (!$ppActive && !$ncActive && !$psActive && !$spActive): ?>
<div class="alert alert-warning">
  <i class="fi fi-rr-triangle-warning"></i>
  <span>No payment method is currently active. Please contact admin.</span>
</div>
<?php endif; ?>

<!-- Transaction history link -->
<a href="<?= SITE_URL ?>/pages/history.php?tab=wallet"
   class="glass d-flex align-center gap-2" style="padding:14px 16px;margin-top:4px;text-decoration:none;color:var(--text);">
  <i class="fi fi-rr-time-past" style="color:var(--primary);font-size:18px;"></i>
  <span style="flex:1;font-weight:600;">Transaction History</span>
  <i class="fi fi-rr-angle-right" style="color:var(--text3);font-size:13px;"></i>
</a>

<script>
const USD_RATE = <?= json_encode($rate) ?>;
function updateNgnPreview(usd) {
  const preview = document.getElementById('ngn-preview');
  const amountEl = document.getElementById('ngn-amount');
  const val = parseFloat(usd);
  if (isNaN(val) || val <= 0) { preview.style.display = 'none'; return; }
  const ngn = (val * USD_RATE).toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2});
  amountEl.textContent = '₦' + ngn;
  preview.style.display = 'block';
}
function setAmount(usd) {
  const input = document.getElementById('pay-amount-usd');
  input.value = usd;
  updateNgnPreview(usd);
}
function updateSpNgnPreview(usd) {
  const preview = document.getElementById('sp-ngn-preview');
  const amountEl = document.getElementById('sp-ngn-amount');
  if (!preview || !amountEl) return;
  const val = parseFloat(usd);
  if (isNaN(val) || val <= 0) { preview.style.display = 'none'; return; }
  const ngn = (val * USD_RATE).toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2});
  amountEl.textContent = '₦' + ngn;
  preview.style.display = 'block';
}
function setSpAmount(usd) {
  const input = document.getElementById('sp-amount-usd');
  input.value = usd;
  updateSpNgnPreview(usd);
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
