<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/vtu_helper.php';
$pageTitle = 'Electricity';
require_once ROOT_PATH . '/includes/header.php';

$service = $pdo->query("SELECT * FROM vtu_services WHERE code='electricity'")->fetch();

if (!$service || !$service['is_active']) {
    echo '<div class="empty"><i class="fi fi-rr-bolt" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i><p>Electricity payments are temporarily unavailable.</p><a href="' . SITE_URL . '/pages/vtu-bills.php" class="btn btn-primary mt-2" style="display:inline-flex;">Back to VTU Bills</a></div>';
    require_once ROOT_PATH . '/includes/footer.php';
    exit;
}
$providers = $pdo->prepare("SELECT * FROM vtu_providers WHERE service_id = ? AND is_active = 1");
$providers->execute([$service['id']]);
$providers = $providers->fetchAll();

$rate = get_usd_to_ngn_rate();
$error = ''; $verifiedName = null; $verifiedAddress = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Please refresh and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $api = new VTUnaijaAPI();
    $res = $api->verifyMeter(clean($_POST['disco_name'] ?? ''), trim($_POST['meter_number'] ?? ''));
    if ($res['ok']) {
        $verifiedName = $res['raw']['Customer_Name'] ?? $res['raw']['name'] ?? 'Verified';
        $verifiedAddress = $res['raw']['Customer_Address'] ?? '';
    } else {
        $error = $res['message'] ?: 'Could not verify meter number.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purchase') {
    $discoId   = clean($_POST['disco_name'] ?? '');
    $meter     = trim($_POST['meter_number'] ?? '');
    $meterType = ($_POST['meter_type'] ?? 'prepaid') === 'postpaid' ? 'postpaid' : 'prepaid';
    $amount    = (float) ($_POST['amount'] ?? 0);
    $pin       = $_POST['pin'] ?? '';

    if (empty($user['pin'])) {
        $error = 'You must set a PIN first. Go to Profile → Set PIN.';
    } elseif (!password_verify($pin, $user['pin'])) {
        $error = 'Incorrect PIN.';
    } elseif ($amount < 500) {
        $error = 'Minimum electricity payment is ₦500.';
    } else {
        $sellingNgn = vtu_selling_price($amount, (float) $service['markup_percent']);
        $sellingUsd = ngn_to_usd($sellingNgn);

        if ($sellingUsd > (float) $user['balance']) {
            $error = 'Insufficient wallet balance.';
        } else {
            $providerName = array_column($providers, 'name', 'provider_code')[$discoId] ?? $discoId;

            $result = vtu_process_order(
                $user['id'], 'electricity', 'ELE', $meter, $sellingNgn, $amount, $providerName, $meterType,
                function () use ($discoId, $meter, $amount, $meterType) {
                    $api = new VTUnaijaAPI();
                    return $api->payElectricity($discoId, $meter, (string) $amount, $meterType);
                }
            );

            if ($result['ok']) {
                $successExtra = !empty($result['extra']['token']) ? ' Token: ' . $result['extra']['token'] : '';
                set_flash('success', 'Payment successful.' . $successExtra);
                redirect(SITE_URL . '/pages/vtu-history.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;"><i class="fi fi-rr-bolt"></i> Pay Electricity Bill</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);max-width:480px;">
  <?php if (empty($providers)): ?>
  <div class="alert alert-info"><i class="fi fi-rr-info"></i><span>No electricity discos available yet. Please check back shortly.</span></div>
  <?php else: ?>

  <form method="POST" style="<?= $verifiedName ? 'margin-bottom:16px;' : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="verify">
    <div class="form-group">
      <label class="form-label">Disco (Provider)</label>
      <select name="disco_name" class="form-control" required <?= $verifiedName ? 'disabled' : '' ?>>
        <option value="">Select disco</option>
        <?php foreach ($providers as $p): ?>
        <option value="<?= clean($p['provider_code']) ?>" <?= ($_POST['disco_name'] ?? '') === $p['provider_code'] ? 'selected' : '' ?>><?= clean($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Meter Number</label>
      <input type="text" name="meter_number" class="form-control" required value="<?= clean($_POST['meter_number'] ?? '') ?>" <?= $verifiedName ? 'readonly' : '' ?>>
    </div>
    <?php if (!$verifiedName): ?>
    <button type="submit" class="btn btn-outline btn-block"><i class="fi fi-rr-search"></i> Verify Meter</button>
    <?php endif; ?>
  </form>

  <?php if ($verifiedName): ?>
  <div class="alert alert-success" style="margin-bottom:12px;"><i class="fi fi-rr-check"></i><span>Verified: <?= clean($verifiedName) ?><?= $verifiedAddress ? ' — ' . clean($verifiedAddress) : '' ?></span></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <input type="hidden" name="disco_name" value="<?= clean($_POST['disco_name']) ?>">
    <input type="hidden" name="meter_number" value="<?= clean($_POST['meter_number']) ?>">
    <div class="form-group">
      <label class="form-label">Meter Type</label>
      <select name="meter_type" class="form-control">
        <option value="prepaid">Prepaid</option>
        <option value="postpaid">Postpaid</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Amount (₦)</label>
      <input type="number" name="amount" id="amountInput" class="form-control" min="500" required>
      <div id="usdHint" style="font-size:11px;color:var(--text3);margin-top:4px;"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;">
      <?php foreach ([500, 1000, 2000, 5000, 10000, 20000] as $preset): ?>
      <button type="button" class="amount-pill" data-amt="<?= $preset ?>" onclick="pickAmount(<?= $preset ?>)">₦<?= number_format($preset) ?></button>
      <?php endforeach; ?>
    </div>
    <p style="font-size:12px;color:var(--text3);margin-bottom:14px;">A <?= clean($service['markup_percent']) ?>% service charge applies.</p>
    <div class="form-group">
      <label class="form-label">Enter 4-digit PIN to confirm</label>
      <input type="password" name="pin" class="form-control" maxlength="4" inputmode="numeric" placeholder="●●●●" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><i class="fi fi-rr-bolt"></i> Pay Now</button>
  </form>
  <style>
  .amount-pill{padding:12px 4px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:14px;font-weight:700;background:var(--card-bg);color:var(--text);}
  .amount-pill.active{border-color:var(--primary);background:var(--primary);color:#fff;}
  </style>
  <script>
  const rate = <?= (float) $rate ?>;
  const markup = <?= (float) $service['markup_percent'] ?>;
  const amountInput = document.getElementById('amountInput');
  const usdHint = document.getElementById('usdHint');
  function pickAmount(amt) { amountInput.value = amt; updateHint(); }
  function updateHint() {
      const amt = parseFloat(amountInput.value) || 0;
      const selling = amt * (1 + markup / 100);
      usdHint.textContent = amt > 0 ? ('≈ $' + (selling / rate).toFixed(4) + ' will be deducted from your wallet') : '';
      document.querySelectorAll('.amount-pill').forEach(btn => btn.classList.toggle('active', parseInt(btn.dataset.amt) === amt));
  }
  amountInput.addEventListener('input', updateHint);
  </script>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
