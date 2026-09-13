<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/vtu_helper.php';
$pageTitle = 'Buy Data';
require_once ROOT_PATH . '/includes/header.php';

$service = $pdo->query("SELECT * FROM vtu_services WHERE code='data'")->fetch();

if (!$service || !$service['is_active']) {
    echo '<div class="empty"><i class="fi fi-rr-signal-alt" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i><p>Data purchases are temporarily unavailable.</p><a href="' . SITE_URL . '/pages/vtu-bills.php" class="btn btn-primary mt-2" style="display:inline-flex;">Back to VTU Bills</a></div>';
    require_once ROOT_PATH . '/includes/footer.php';
    exit;
}
$providers = $pdo->prepare("SELECT * FROM vtu_providers WHERE service_id = ? AND is_active = 1");
$providers->execute([$service['id']]);
$providers = $providers->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $networkId = clean($_POST['network'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $planId    = (int) ($_POST['plan_id'] ?? 0);
        $pin       = $_POST['pin'] ?? '';

        $planStmt = $pdo->prepare("SELECT * FROM vtu_plans WHERE id = ? AND is_active = 1");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();

        if (empty($user['pin'])) {
            $error = 'You must set a PIN first. Go to Profile → Set PIN.';
        } elseif (!password_verify($pin, $user['pin'])) {
            $error = 'Incorrect PIN.';
        } elseif (!vtu_is_valid_phone($phone)) {
            $error = 'Enter a valid 11-digit phone number.';
        } elseif (!$plan) {
            $error = 'Select a valid data plan.';
        } else {
            $sellingUsd = ngn_to_usd((float) $plan['selling_price']);

            if ($sellingUsd > (float) $user['balance']) {
                $error = 'Insufficient wallet balance. Please fund your wallet.';
            } else {
                $providerName = array_column($providers, 'name', 'provider_code')[$networkId] ?? $networkId;

                $result = vtu_process_order(
                    $user['id'], 'data', 'DAT', $phone, (float) $plan['selling_price'], (float) $plan['cost_price'],
                    $providerName, $plan['plan_name'],
                    function () use ($networkId, $phone, $plan) {
                        $api = new VTUnaijaAPI();
                        return $api->buyData($networkId, $phone, $plan['plan_code']);
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

/** Buckets a plan into Daily/Weekly/Monthly/Other based on its validity text. */
function vtu_data_category(?string $validity): string {
    if (!$validity) return 'Other';
    preg_match('/(\d+)/', $validity, $m);
    $days = isset($m[1]) ? (int) $m[1] : 0;
    if ($days <= 1) return 'Daily';
    if ($days <= 9) return 'Weekly';
    if ($days >= 28) return 'Monthly';
    return 'Other';
}

$allPlans = $pdo->query(
    "SELECT p.id, p.provider_id, p.plan_name, p.selling_price, p.validity
     FROM vtu_plans p JOIN vtu_providers pr ON p.provider_id=pr.id
     WHERE pr.service_id={$service['id']} AND p.is_active=1
     ORDER BY p.selling_price ASC"
)->fetchAll();

foreach ($allPlans as &$p) {
    $p['category'] = vtu_data_category($p['validity']);
}
unset($p);

$categories = array_values(array_unique(array_column($allPlans, 'category')));
$categoryOrder = ['Daily', 'Weekly', 'Monthly', 'Other'];
usort($categories, fn($a, $b) => array_search($a, $categoryOrder) <=> array_search($b, $categoryOrder));

$anyPlans = count($allPlans) > 0;
?>
<br>
<!--<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;"><i class="fi fi-rr-smart-home"></i> Buy Data or Gift Friend</h2>-->

<!--<?php if ($error): ?>-->
<!--<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>-->
<!--<?php endif; ?>-->

<!--<?php if (!$anyPlans): ?>-->
<!--<div class="glass" style="padding:20px;border-radius:var(--radius);">-->
<!--  <div class="alert alert-info"><i class="fi fi-rr-info"></i><span>No data plans available yet. Please check back shortly.</span></div>-->
<!--</div>-->
<!--<?php else: ?>-->

<!--<form method="POST" id="dataForm">-->
<!--  <?= csrf_field() ?>-->
<!--  <input type="hidden" name="plan_id" id="planIdInput" required>-->

<!--  <div class="glass" style="padding:16px;border-radius:var(--radius);margin-bottom:14px;">-->
<!--    <label class="form-label">Network</label>-->
<!--    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px;">-->
<!--      <?php foreach ($providers as $p): ?>-->
<!--      <label class="network-pill">-->
<!--        <input type="radio" name="network" value="<?= clean($p['provider_code']) ?>" data-provider-id="<?= (int)$p['id'] ?>" required style="display:none;" onchange="onNetworkChange(this)">-->
<!--        <span><?= clean($p['name']) ?></span>-->
<!--      </label>-->
<!--      <?php endforeach; ?>-->
<!--    </div>-->

<!--    <label class="form-label">Phone Number</label>-->
<!--    <input type="text" name="phone" class="form-control" placeholder="08012345678" maxlength="11" inputmode="numeric" required value="<?= clean($_POST['phone'] ?? '') ?>">-->
<!--  </div>-->

<!--  <div id="planPicker" style="display:none;">-->
<!--    <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:8px;margin-bottom:12px;-webkit-overflow-scrolling:touch;" id="categoryTabs">-->
<!--      <?php foreach ($categories as $i => $cat): ?>-->
<!--      <button type="button" class="cat-pill <?= $i===0?'active':'' ?>" data-cat="<?= clean($cat) ?>" onclick="selectCategory(this)"><?= clean($cat) ?></button>-->
<!--      <?php endforeach; ?>-->
<!--    </div>-->

<!--    <div id="plansGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;"></div>-->
<!--  </div>-->

<!--  <div class="glass" style="padding:16px;border-radius:var(--radius);">-->
<!--    <label class="form-label">Enter 4-digit PIN to confirm</label>-->
<!--    <input type="password" name="pin" class="form-control" maxlength="4" inputmode="numeric" placeholder="●●●●" required>-->
<!--    <button type="submit" class="btn btn-primary btn-block mt-2"><i class="fi fi-rr-bolt"></i> Buy Data</button>-->
<!--  </div>-->
<!--</form>-->

<!--<style>-->
<!--.network-pill{display:block;text-align:center;padding:10px 4px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:12px;font-weight:600;background:var(--card-bg);color:var(--text2);}-->
<!--.network-pill:has(input:checked){border-color:var(--primary);background:var(--primary);color:#fff;}-->
<!--.cat-pill{flex-shrink:0;padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);background:var(--card-bg);color:var(--text2);font-size:12px;font-weight:600;white-space:nowrap;cursor:pointer;}-->
<!--.cat-pill.active{background:var(--primary);border-color:var(--primary);color:#fff;}-->
<!--.plan-card{border:1.5px solid var(--border);border-radius:12px;padding:14px 12px;background:var(--card-bg);cursor:pointer;}-->
<!--.plan-card.active{border-color:var(--primary);background:rgba(124,58,237,0.06);}-->
<!--.plan-card .size{font-size:16px;font-weight:900;color:var(--text);}-->
<!--.plan-card .price{font-size:14px;font-weight:800;color:var(--primary);margin:4px 0;}-->
<!--.plan-card .validity{font-size:11px;color:var(--text3);}-->
<!--</style>-->

<!--<script>-->
<!--const allPlans = <?= json_encode($allPlans) ?>;-->
<!--const planIdInput = document.getElementById('planIdInput');-->
<!--const planPicker = document.getElementById('planPicker');-->
<!--const plansGrid = document.getElementById('plansGrid');-->
<!--let currentProviderId = null;-->
<!--let currentCategory = <?= json_encode($categories[0] ?? 'Other') ?>;-->

<!--function onNetworkChange(radio) {-->
<!--    currentProviderId = radio.dataset.providerId;-->
<!--    planPicker.style.display = 'block';-->
<!--    planIdInput.value = '';-->
<!--    renderPlans();-->
<!--}-->

<!--function selectCategory(btn) {-->
<!--    document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));-->
<!--    btn.classList.add('active');-->
<!--    currentCategory = btn.dataset.cat;-->
<!--    planIdInput.value = '';-->
<!--    renderPlans();-->
<!--}-->

<!--function renderPlans() {-->
<!--    plansGrid.innerHTML = '';-->
<!--    const matches = allPlans.filter(p => String(p.provider_id) === String(currentProviderId) && p.category === currentCategory);-->

<!--    if (matches.length === 0) {-->
<!--        plansGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--text3);font-size:13px;">No plans in this category for this network.</div>';-->
<!--        return;-->
<!--    }-->

<!--    matches.forEach(p => {-->
<!--        const card = document.createElement('div');-->
<!--        card.className = 'plan-card';-->
<!--        card.innerHTML = `<div class="size">${p.plan_name.split(' (')[0]}</div>-->
<!--                           <div class="price">₦${Number(p.selling_price).toLocaleString()}</div>-->
<!--                           <div class="validity">${p.validity || ''}</div>`;-->
<!--        card.addEventListener('click', () => {-->
<!--            document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('active'));-->
<!--            card.classList.add('active');-->
<!--            planIdInput.value = p.id;-->
<!--        });-->
<!--        plansGrid.appendChild(card);-->
<!--    });-->
<!--}-->
<!--</script>-->
<!--<?php endif; ?>-->







<h2 style="font-size:18px;font-weight:800;color:#1D1037;margin-bottom:14px;display:flex;align-items:center;gap:8px;font-family:sans-serif;">
  <i class="fi fi-rr-smart-home" style="color:#7F56D9;"></i> Buy Data 
</h2>

<?php if ($error): ?>
<div class="alert alert-danger" style="background:#FEF3F2;border:1px solid #FECDCA;color:#B42318;padding:12px 14px;border-radius:12px;font-size:13px;font-family:sans-serif;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
  <i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span>
</div>
<?php endif; ?>

<?php if (!$anyPlans): ?>
<div class="glass" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:20px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);font-family:sans-serif;">
  <div class="alert alert-info" style="background:#F4EBFF;border:1px solid #E9D7FE;color:#7F56D9;padding:12px 14px;border-radius:12px;font-size:13px;display:flex;align-items:center;gap:8px;margin:0;">
    <i class="fi fi-rr-info"></i><span>No data plans available yet. Please check back shortly.</span>
  </div>
</div>
<?php else: ?>

<form method="POST" id="dataForm" style="font-family:sans-serif;">
  <?= csrf_field() ?>
  <input type="hidden" name="plan_id" id="planIdInput" required>

  <!-- Section: Connection Parameters Selector Frame -->
  <div class="glass" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:18px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);margin-bottom:14px;display:flex;flex-direction:column;gap:14px;">
    
    <!-- Network Provider Dropdown Selection Layer -->
    <div style="display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Select Network Provider</label>
      <div style="position:relative;display:flex;align-items:center;">
        <i class="fi fi-rr-mobile" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <select name="network" required style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;font-weight:600;outline:none;appearance:none;cursor:pointer;" onchange="onNetworkChange(this)">
          <option value="" disabled selected hidden>Choose your operator...</option>
          <?php foreach ($providers as $p): ?>
          <option value="<?= clean($p['provider_code']) ?>" data-provider-id="<?= (int)$p['id'] ?>"><?= clean($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down" style="position:absolute;right:14px;color:#667085;font-size:11px;pointer-events:none;"></i>
      </div>
    </div>

    <!-- Phone Number Numeric Entry Input Field Layer -->
    <div style="display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Phone Number</label>
      <div style="position:relative;display:flex;align-items:center;">
        <i class="fi fi-rr-call-incoming" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="text" name="phone" class="form-control" placeholder="08012345678" maxlength="11" inputmode="numeric" required value="<?= clean($_POST['phone'] ?? '') ?>" style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;font-weight:500;outline:none;">
      </div>
    </div>
  </div>

  <!-- Section: Dynamic Data Category & Bundle Selections Frame -->
  <div id="planPicker" style="display:none;background:#FFFFFF;border:1px solid #F4EBFF;padding:18px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);margin-bottom:14px;display:none;flex-direction:column;gap:14px;">
    
    <!-- Data Bundle Category Types Dropdown Selector Layer -->
    <div style="display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Bundle Type Category</label>
      <div style="position:relative;display:flex;align-items:center;">
        <i class="fi fi-rr-rss" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <select id="categorySelect" style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;font-weight:600;outline:none;appearance:none;cursor:pointer;" onchange="selectCategoryInline(this)">
          <?php foreach ($categories as $cat): ?>
          <option value="<?= clean($cat) ?>"><?= clean($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down" style="position:absolute;right:14px;color:#667085;font-size:11px;pointer-events:none;"></i>
      </div>
    </div>

    <!-- Live Available Data Bundle Plans Dropdown Selector Layer -->
    <div style="display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Choose Available Data Plan</label>
      <div style="position:relative;display:flex;align-items:center;">
        <i class="fi fi-rr-apps-sort" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <select id="plansSelect" style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;font-weight:600;outline:none;appearance:none;cursor:pointer;" onchange="selectPlanInline(this)">
          <option value="" disabled selected hidden>Select a data package...</option>
        </select>
        <i class="fa-solid fa-chevron-down" style="position:absolute;right:14px;color:#667085;font-size:11px;pointer-events:none;"></i>
      </div>
    </div>
  </div>

  <!-- Section: Security Check Authorization & Submission Frame -->
  <div class="glass" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:18px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);display:flex;flex-direction:column;gap:12px;">
    <div style="display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Enter 4-digit PIN to confirm purchase</label>
      <div style="position:relative;display:flex;align-items:center;">
        <i class="fi fi-rr-hashtag" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="password" name="pin" class="form-control" maxlength="4" inputmode="numeric" placeholder="●●●●" required style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;font-weight:500;outline:none;letter-spacing:2px;">
      </div>
    </div>
    
    <button type="submit" class="btn btn-primary btn-block" style="width:100%;background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);color:#FFFFFF;border:none;padding:13px;border-radius:14px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(38,4,78,0.15);display:inline-flex;align-items:center;justify-content:center;gap:6px;margin-top:4px;"><i class="fi fi-rr-bolt"></i> Buy Data</button>
  </div>
</form>

<script>
const allPlans = <?= json_encode($allPlans) ?>;
const planIdInput = document.getElementById('planIdInput');
const planPicker = document.getElementById('planPicker');
const plansSelect = document.getElementById('plansSelect');
const categorySelect = document.getElementById('categorySelect');
let currentProviderId = null;
let currentCategory = <?= json_encode($categories[0] ?? 'Other') ?>;

function onNetworkChange(select) {
    const selectedOption = select.options[select.selectedIndex];
    currentProviderId = selectedOption.dataset.providerId;
    planPicker.style.display = 'flex';
    planIdInput.value = '';
    renderPlansDropdown();
}

function selectCategoryInline(select) {
    currentCategory = select.value;
    planIdInput.value = '';
    renderPlansDropdown();
}

function selectPlanInline(select) {
    planIdInput.value = select.value;
}

function renderPlansDropdown() {
    plansSelect.innerHTML = '';
    const matches = allPlans.filter(p => String(p.provider_id) === String(currentProviderId) && p.category === currentCategory);

    if (matches.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.disabled = true;
        opt.selected = true;
        opt.innerText = 'No plans available in this category.';
        plansSelect.appendChild(opt);
        return;
    }

    // Append standard empty fallback suggestion placeholder row
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.disabled = true;
    defaultOpt.selected = true;
    defaultOpt.hidden = true;
    defaultOpt.innerText = 'Choose an options bundle package...';
    plansSelect.appendChild(defaultOpt);

    matches.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        
        const cleanName = p.plan_name.split(' (')[0];
        const formattedPrice = '₦' + Number(p.selling_price).toLocaleString();
        const validityText = p.validity ? ' | ' + p.validity : '';
        
        opt.innerText = `${cleanName} (${formattedPrice}${validityText})`;
        plansSelect.appendChild(opt);
    });
}
</script>
<?php endif; ?>






<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
