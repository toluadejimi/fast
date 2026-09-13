<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/vtu_helper.php';
$pageTitle = 'Buy Airtime';
require_once ROOT_PATH . '/includes/header.php';

$service = $pdo->query("SELECT * FROM vtu_services WHERE code='airtime'")->fetch();

if (!$service || !$service['is_active']) {
    echo '<div class="empty"><i class="fi fi-rr-mobile-notch" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i><p>Airtime purchases are temporarily unavailable.</p><a href="' . SITE_URL . '/pages/vtu-bills.php" class="btn btn-primary mt-2" style="display:inline-flex;">Back to VTU Bills</a></div>';
    require_once ROOT_PATH . '/includes/footer.php';
    exit;
}
$providers = $pdo->prepare("SELECT * FROM vtu_providers WHERE service_id = ? AND is_active = 1");
$providers->execute([$service['id']]);
$providers = $providers->fetchAll();

$rate = get_usd_to_ngn_rate();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $networkId = clean($_POST['network'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $amount    = (float) ($_POST['amount'] ?? 0);
        $pin       = $_POST['pin'] ?? '';

        if (empty($user['pin'])) {
            $error = 'You must set a PIN first. Go to Profile → Set PIN.';
        } elseif (!password_verify($pin, $user['pin'])) {
            $error = 'Incorrect PIN.';
        } elseif (!vtu_is_valid_phone($phone)) {
            $error = 'Enter a valid 11-digit phone number.';
        } elseif ($amount < 50) {
            $error = 'Minimum airtime purchase is ₦50.';
        } else {
            $sellingNgn = vtu_selling_price($amount, (float) $service['markup_percent']);
            $sellingUsd = ngn_to_usd($sellingNgn);

            if ($sellingUsd > (float) $user['balance']) {
                $error = 'Insufficient wallet balance. Please fund your wallet.';
            } else {
                $providerName = array_column($providers, 'name', 'provider_code')[$networkId] ?? $networkId;

                $result = vtu_process_order(
                    $user['id'], 'airtime', 'AIR', $phone, $sellingNgn, $amount, $providerName, null,
                    function () use ($networkId, $phone, $amount) {
                        $api = new VTUnaijaAPI();
                        return $api->buyAirtime($networkId, $phone, (string) $amount);
                    }
                );

                if ($result['ok']) {
                    redirect(SITE_URL . '/pages/vtu-history.php');
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;"><i class="fi fi-rr-mobile-notch"></i> Buy Airtime</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);max-width:480px;">
  <form method="POST" id="airtimeForm">
    <?= csrf_field() ?>

    <label class="form-label">Network</label>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px;">
      <?php foreach ($providers as $p): ?>
      <label class="network-pill">
        <input type="radio" name="network" value="<?= clean($p['provider_code']) ?>" required style="display:none;">
        <span><?= clean($p['name']) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="form-group">
      <label class="form-label">Phone Number</label>
      <input type="text" name="phone" class="form-control" placeholder="08012345678" maxlength="11" inputmode="numeric" required value="<?= clean($_POST['phone'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Amount (₦)</label>
      <input type="number" name="amount" id="amountInput" class="form-control" min="50" placeholder="Enter amount" required value="<?= clean($_POST['amount'] ?? '') ?>">
      <div id="usdHint" style="font-size:11px;color:var(--text3);margin-top:4px;"></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;">
      <?php foreach ([100, 200, 300, 500, 1000, 2000] as $preset): ?>
      <button type="button" class="amount-pill" data-amt="<?= $preset ?>" onclick="pickAmount(<?= $preset ?>, this)">₦<?= number_format($preset) ?></button>
      <?php endforeach; ?>
    </div>

    <p style="font-size:12px;color:var(--text3);margin-bottom:14px;">A <?= clean($service['markup_percent']) ?>% service charge applies. Rate: $1 ≈ ₦<?= number_format($rate, 0) ?></p>

    <div class="form-group">
      <label class="form-label">Enter 4-digit PIN to confirm</label>
      <input type="password" name="pin" class="form-control" maxlength="4" inputmode="numeric" placeholder="●●●●" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block"><i class="fi fi-rr-bolt"></i> Buy Airtime</button>
  </form>
</div>

<style>
.network-pill{display:block;text-align:center;padding:10px 4px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:12px;font-weight:600;background:var(--card-bg);color:var(--text2);}
.network-pill:has(input:checked){border-color:var(--primary);background:var(--primary);color:#fff;}
.amount-pill{padding:12px 4px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:14px;font-weight:700;background:var(--card-bg);color:var(--text);}
.amount-pill.active{border-color:var(--primary);background:var(--primary);color:#fff;}
</style>

<script>
const rate = <?= (float) $rate ?>;
const amountInput = document.getElementById('amountInput');
const usdHint = document.getElementById('usdHint');
const markup = <?= (float) $service['markup_percent'] ?>;

function updateHint() {
    const amt = parseFloat(amountInput.value) || 0;
    const selling = amt * (1 + markup / 100);
    usdHint.textContent = amt > 0 ? ('≈ $' + (selling / rate).toFixed(4) + ' will be deducted from your wallet') : '';
    document.querySelectorAll('.amount-pill').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.amt) === amt);
    });
}
function pickAmount(amt, btn) {
    amountInput.value = amt;
    updateHint();
}
amountInput.addEventListener('input', updateHint);
updateHint();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
