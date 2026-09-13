<?php
// ============================================================
//  DonnieSMS - pages/social-boost.php
//  SMM services (followers, likes, views, etc.) via momopanel.com
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/momopanel.php';
$pageTitle = 'Social Boost';
require_once ROOT_PATH . '/includes/header.php';

$error  = '';
$markup = (float) get_setting('momopanel_markup_percent', '30');

$momo = new MomoPanel();
$servicesResp = $momo->getServices();
$services = [];
$apiError = '';

if (!$servicesResp['success']) {
    $apiError = 'Could not load services right now: ' . ($servicesResp['error'] ?? 'Unknown error') . '. Check the API key in Admin → Settings.';
} else {
    foreach ($servicesResp['data'] as $s) {
        $rate = (float) ($s['rate'] ?? 0);
        if ($rate <= 0) continue; // never show a free/broken service
        $services[] = [
            'service'  => (string) $s['service'],
            'name'     => $s['name'] ?? 'Service',
            'type'     => $s['type'] ?? '',
            'category' => $s['category'] ?? 'Other',
            'rate'     => $rate, // per 1000, real cost
            'min'      => (int) ($s['min'] ?? 1),
            'max'      => (int) ($s['max'] ?? 100000),
            'refill'   => !empty($s['refill']),
            'cancel'   => !empty($s['cancel']),
        ];
    }
}

// ── Handle order placement ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy'])) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {

        $serviceId = clean($_POST['service_id'] ?? '');
        $link      = trim($_POST['link'] ?? '');
        $quantity  = (int) ($_POST['quantity'] ?? 0);
        $pin       = $_POST['pin'] ?? '';

        if (empty($user['pin'])) {
            $error = 'You must set a PIN first. Go to Profile → Set PIN.';
        } elseif (!password_verify($pin, $user['pin'])) {
            $error = 'Incorrect PIN. Please try again.';
        } elseif ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid link.';
        } elseif ($quantity < 1) {
            $error = 'Please enter a valid quantity.';
        } else {

            // Re-fetch fresh services and find this one server-side —
            // never trust a price/rate submitted from the browser.
            $svc = null;
            foreach ($services as $s) {
                if ($s['service'] === $serviceId) { $svc = $s; break; }
            }

            if (!$svc) {
                $error = 'Service not found. Please refresh and try again.';
            } elseif ($quantity < $svc['min'] || $quantity > $svc['max']) {
                $error = "Quantity must be between {$svc['min']} and {$svc['max']} for this service.";
            } else {

                $realCost    = round(($svc['rate'] / 1000) * $quantity, 4);
                $chargePrice = round($realCost * (1 + $markup / 100), 4);

                if ($chargePrice <= 0) {
                    $error = 'Could not determine price. Please try again.';
                } elseif ((float) $user['balance'] < $chargePrice) {
                    $error = 'Insufficient balance. Please fund your wallet.';
                } else {

                    $result = $momo->addOrder($svc['service'], $link, $quantity);

                    if (!$result['success'] || empty($result['data']['order'])) {
                        $error = 'Order failed: ' . ($result['error'] ?? 'Unknown error from provider.');
                    } else {

                        $momoOrderId = (string) $result['data']['order'];
                        $ref = gen_ref('SMM');

                        wallet_debit($user['id'], $chargePrice, 'Social Boost – ' . $svc['name'], $ref);
                        track_spend($user['id'], $chargePrice);

                        $pdo->prepare(
                            "INSERT INTO social_orders
                                (user_id, momo_order_id, service_id, service_name, category, link, quantity, rate, charged_price, status, can_refill, can_cancel)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)"
                        )->execute([
                            $user['id'], $momoOrderId, $svc['service'], $svc['name'], $svc['category'],
                            $link, $quantity, $svc['rate'], $chargePrice,
                            $svc['refill'] ? 1 : 0, $svc['cancel'] ? 1 : 0,
                        ]);

                        notify(
                            $user['id'],
                            'Order Placed',
                            $quantity . 'x ' . $svc['name'] . ' — $' . number_format($chargePrice, 4) . ' charged.',
                            '/pages/social-orders.php'
                        );

                        redirect(SITE_URL . '/pages/social-orders.php');
                    }
                }
            }
        }
    }
}

// Group services by category for the dropdown
$byCategory = [];
foreach ($services as $s) {
    $byCategory[$s['category']][] = $s;
}
ksort($byCategory);
?>

<h2 style="margin-bottom:4px;">📈 Social Boost</h2>
<p class="text-muted text-sm" style="margin-bottom:16px;">Followers, likes, views &amp; more for your social media — powered by our SMM network.</p>

<?php if ($apiError): ?>
<div class="alert alert-warning"><i class="fi fi-rr-triangle-warning"></i><span><?= clean($apiError) ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>

<div class="glass" style="padding:20px;margin-bottom:16px;">
  <form method="POST" id="boostForm">
    <?= csrf_field() ?>
    <input type="hidden" name="buy" value="1">
    <input type="hidden" name="service_id" id="serviceIdInput">

    <div class="form-group">
      <label class="form-label">Category</label>
      <select id="categorySelect" class="form-control">
        <option value="">-- Select category --</option>
        <?php foreach ($byCategory as $cat => $list): ?>
        <option value="<?= clean($cat) ?>"><?= clean($cat) ?> (<?= count($list) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Service</label>
      <select id="serviceSelect" class="form-control" disabled>
        <option value="">-- Select category first --</option>
      </select>
    </div>

    <div id="serviceInfo" style="display:none;font-size:12px;color:var(--text3);margin:-8px 0 14px;"></div>

    <div class="form-group">
      <label class="form-label">Link</label>
      <input type="url" name="link" id="linkInput" class="form-control" placeholder="https://instagram.com/yourpage" required>
    </div>

    <div class="form-group">
      <label class="form-label">Quantity</label>
      <input type="number" name="quantity" id="quantityInput" class="form-control" placeholder="Select a service first" disabled required>
    </div>

    <div class="glass" style="padding:12px 16px;margin-bottom:16px;background:rgba(124,58,237,0.06);">
      <i class="fi fi-rr-dollar"></i> Estimated Cost: <strong id="costDisplay">$0.00</strong>
    </div>

    <div class="form-group">
      <label class="form-label">Enter 4-digit PIN to confirm</label>
      <input type="password" name="pin" class="form-control" maxlength="4" inputmode="numeric" placeholder="●●●●" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>
      <i class="fi fi-rr-rocket-lunch"></i> Place Order
    </button>
  </form>
</div>

<p style="text-align:center;"><a href="<?= SITE_URL ?>/pages/social-orders.php">View My Orders →</a></p>

<script>
const SERVICES_BY_CATEGORY = <?= json_encode($byCategory) ?>;
const MARKUP = <?= (float) $markup ?>;

const categorySelect = document.getElementById('categorySelect');
const serviceSelect  = document.getElementById('serviceSelect');
const serviceIdInput = document.getElementById('serviceIdInput');
const serviceInfo    = document.getElementById('serviceInfo');
const quantityInput  = document.getElementById('quantityInput');
const costDisplay    = document.getElementById('costDisplay');
const submitBtn      = document.getElementById('submitBtn');

let currentService = null;

categorySelect.addEventListener('change', () => {
    const cat = categorySelect.value;
    serviceSelect.innerHTML = '<option value="">-- Select service --</option>';
    serviceSelect.disabled = !cat;
    resetServiceState();

    if (cat && SERVICES_BY_CATEGORY[cat]) {
        SERVICES_BY_CATEGORY[cat].forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.service;
            opt.textContent = s.name + ' (' + s.type + ')';
            serviceSelect.appendChild(opt);
        });
    }
});

serviceSelect.addEventListener('change', () => {
    const cat = categorySelect.value;
    const list = SERVICES_BY_CATEGORY[cat] || [];
    currentService = list.find(s => s.service == serviceSelect.value) || null;

    if (!currentService) { resetServiceState(); return; }

    serviceIdInput.value = currentService.service;
    const chargeRate = currentService.rate * (1 + MARKUP / 100);
    serviceInfo.style.display = 'block';
    serviceInfo.innerHTML = `Rate: $${chargeRate.toFixed(4)} per 1000` +
        ` &middot; Min: ${currentService.min} &middot; Max: ${currentService.max}` +
        (currentService.refill ? ' &middot; Refill available' : '') +
        (currentService.cancel ? ' &middot; Cancel available' : '');

    quantityInput.disabled = false;
    quantityInput.min = currentService.min;
    quantityInput.max = currentService.max;
    quantityInput.placeholder = `${currentService.min} - ${currentService.max}`;
    quantityInput.value = '';
    updateCost();
});

quantityInput.addEventListener('input', updateCost);

function updateCost() {
    if (!currentService) { costDisplay.textContent = '$0.00'; submitBtn.disabled = true; return; }
    const qty = parseInt(quantityInput.value) || 0;
    const chargeRate = currentService.rate * (1 + MARKUP / 100);
    const cost = (chargeRate / 1000) * qty;
    costDisplay.textContent = '$' + cost.toFixed(4);
    submitBtn.disabled = !(qty >= currentService.min && qty <= currentService.max);
}

function resetServiceState() {
    currentService = null;
    serviceIdInput.value = '';
    serviceInfo.style.display = 'none';
    quantityInput.disabled = true;
    quantityInput.value = '';
    quantityInput.placeholder = 'Select a service first';
    costDisplay.textContent = '$0.00';
    submitBtn.disabled = true;
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
