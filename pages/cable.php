<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/vtu_helper.php';
$pageTitle = 'Cable TV';
require_once ROOT_PATH . '/includes/header.php';

$service = $pdo->query("SELECT * FROM vtu_services WHERE code='cable'")->fetch();

if (!$service || !$service['is_active']) {
    echo '<div class="empty"><i class="fi fi-rr-tv-music" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i><p>Cable TV subscriptions are temporarily unavailable.</p><a href="' . SITE_URL . '/pages/vtu-bills.php" class="btn btn-primary mt-2" style="display:inline-flex;">Back to VTU Bills</a></div>';
    require_once ROOT_PATH . '/includes/footer.php';
    exit;
}
$providers = $pdo->prepare("SELECT * FROM vtu_providers WHERE service_id = ? AND is_active = 1");
$providers->execute([$service['id']]);
$providers = $providers->fetchAll();

$error = ''; $verifiedName = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Please refresh and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $api = new VTUnaijaAPI();
    $res = $api->verifyCable(clean($_POST['cablename'] ?? ''), trim($_POST['smart_card_number'] ?? ''));
    if ($res['ok']) {
        $verifiedName = $res['raw']['Customer_Name'] ?? $res['raw']['name'] ?? 'Verified';
    } else {
        $error = $res['message'] ?: 'Could not verify smart card number.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purchase') {
    $cablenameId = clean($_POST['cablename'] ?? '');
    $smartCard   = trim($_POST['smart_card_number'] ?? '');
    $planId      = (int) ($_POST['plan_id'] ?? 0);
    $pin         = $_POST['pin'] ?? '';

    $planStmt = $pdo->prepare("SELECT * FROM vtu_plans WHERE id = ? AND is_active = 1");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch();

    if (empty($user['pin'])) {
        $error = 'You must set a PIN first. Go to Profile → Set PIN.';
    } elseif (!password_verify($pin, $user['pin'])) {
        $error = 'Incorrect PIN.';
    } elseif (!$plan) {
        $error = 'Select a valid cable plan.';
    } else {
        $sellingUsd = ngn_to_usd((float) $plan['selling_price']);

        if ($sellingUsd > (float) $user['balance']) {
            $error = 'Insufficient wallet balance.';
        } else {
            $providerName = array_column($providers, 'name', 'provider_code')[$cablenameId] ?? $cablenameId;

            $result = vtu_process_order(
                $user['id'], 'cable', 'CAB', $smartCard, (float) $plan['selling_price'], (float) $plan['cost_price'],
                $providerName, $plan['plan_name'],
                function () use ($cablenameId, $smartCard, $plan) {
                    $api = new VTUnaijaAPI();
                    return $api->buyCable($cablenameId, $smartCard, $plan['plan_code']);
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

$anyPlans = (int) $pdo->query("SELECT COUNT(*) FROM vtu_plans p JOIN vtu_providers pr ON p.provider_id=pr.id WHERE pr.service_id={$service['id']}")->fetchColumn();
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;"><i class="fi fi-rr-tv-music"></i> Cable TV Subscription</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);max-width:480px;">
  <?php if (!$anyPlans): ?>
  <div class="alert alert-info"><i class="fi fi-rr-info"></i><span>No cable plans available yet. Please check back shortly.</span></div>
  <?php else: ?>

  <form method="POST" style="<?= $verifiedName ? 'margin-bottom:16px;' : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="verify">
    <div class="form-group">
      <label class="form-label">Provider</label>
      <select name="cablename" class="form-control" required <?= $verifiedName ? 'disabled' : '' ?>>
        <option value="">Select provider</option>
        <?php foreach ($providers as $p): ?>
        <option value="<?= clean($p['provider_code']) ?>" <?= ($_POST['cablename'] ?? '') === $p['provider_code'] ? 'selected' : '' ?>><?= clean($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Smart Card / IUC Number</label>
      <input type="text" name="smart_card_number" class="form-control" required value="<?= clean($_POST['smart_card_number'] ?? '') ?>" <?= $verifiedName ? 'readonly' : '' ?>>
    </div>
    <?php if (!$verifiedName): ?>
    <button type="submit" class="btn btn-outline btn-block"><i class="fi fi-rr-search"></i> Verify Smart Card</button>
    <?php endif; ?>
  </form>

  <?php if ($verifiedName): ?>
  <div class="alert alert-success" style="margin-bottom:12px;"><i class="fi fi-rr-check"></i><span>Verified: <?= clean($verifiedName) ?></span></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="purchase">
    <input type="hidden" name="cablename" value="<?= clean($_POST['cablename']) ?>">
    <input type="hidden" name="smart_card_number" value="<?= clean($_POST['smart_card_number']) ?>">
    <div class="form-group">
      <label class="form-label">Package</label>
      <input type="hidden" name="plan_id" id="planIdInput" required>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <?php
        $planStmt = $pdo->prepare("SELECT p.* FROM vtu_plans p JOIN vtu_providers pr ON p.provider_id = pr.id WHERE pr.provider_code = ? AND pr.service_id = ? AND p.is_active = 1 ORDER BY p.selling_price ASC");
        $planStmt->execute([$_POST['cablename'], $service['id']]);
        foreach ($planStmt->fetchAll() as $pl): ?>
        <div class="plan-card" onclick="selectPlan(this, <?= (int)$pl['id'] ?>)">
          <div class="size"><?= clean($pl['plan_name']) ?></div>
          <div class="price">₦<?= number_format($pl['selling_price']) ?></div>
          <?php if ($pl['validity']): ?><div class="validity"><?= clean($pl['validity']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Enter 4-digit PIN to confirm</label>
      <input type="password" name="pin" class="form-control" maxlength="4" inputmode="numeric" placeholder="●●●●" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><i class="fi fi-rr-bolt"></i> Subscribe Now</button>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</div>

<style>
.plan-card{border:1.5px solid var(--border);border-radius:12px;padding:14px 12px;background:var(--card-bg);cursor:pointer;}
.plan-card.active{border-color:var(--primary);background:rgba(124,58,237,0.06);}
.plan-card .size{font-size:14px;font-weight:800;color:var(--text);}
.plan-card .price{font-size:14px;font-weight:800;color:var(--primary);margin:4px 0;}
.plan-card .validity{font-size:11px;color:var(--text3);}
</style>
<script>
function selectPlan(card, id) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    document.getElementById('planIdInput').value = id;
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
