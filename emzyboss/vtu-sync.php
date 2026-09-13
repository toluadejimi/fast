<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/vtunaija.php';
require_once ROOT_PATH . '/includes/vtu_helper.php';
$pageTitle = 'Sync VTU Plans';
require_once __DIR__ . '/includes/admin_header.php';

// Maps the plan-list "name" text VTUnaija returns to the numeric code their
// buy endpoints expect. Matching is by substring so small text variations
// (e.g. "MTN DATA" vs "MTN") don't break the sync.
const VTU_NETWORK_CODE_MAP = ['MTN' => '1', 'GLO' => '2', '9MOBILE' => '3', 'AIRTEL' => '4'];
const VTU_CABLE_CODE_MAP = ['GOTV' => '1', 'DSTV' => '2', 'STARTIMES' => '3', 'SHOWMAX' => '4'];

function vtu_match_code(string $name, array $map): ?string
{
    $name = strtoupper($name);
    foreach ($map as $key => $code) {
        if (str_contains($name, $key)) return $code;
    }
    return null;
}

function vtu_upsert_provider(PDO $pdo, int $serviceId, string $code, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM vtu_providers WHERE service_id = ? AND provider_code = ?');
    $stmt->execute([$serviceId, $code]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];

    $pdo->prepare('INSERT INTO vtu_providers (service_id, provider_code, name) VALUES (?, ?, ?)')
        ->execute([$serviceId, $code, $name]);
    return (int) $pdo->lastInsertId();
}

function vtu_upsert_plan(PDO $pdo, int $providerId, string $planCode, string $planName, float $costPrice, float $markup, ?string $validity): void
{
    $sellingPrice = vtu_selling_price($costPrice, $markup);
    $stmt = $pdo->prepare('SELECT id FROM vtu_plans WHERE provider_id = ? AND plan_code = ?');
    $stmt->execute([$providerId, $planCode]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare('UPDATE vtu_plans SET plan_name = ?, cost_price = ?, selling_price = ?, validity = ?, is_active = 1 WHERE id = ?')
            ->execute([$planName, $costPrice, $sellingPrice, $validity, $row['id']]);
    } else {
        $pdo->prepare('INSERT INTO vtu_plans (provider_id, plan_code, plan_name, cost_price, selling_price, validity) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$providerId, $planCode, $planName, $costPrice, $sellingPrice, $validity]);
    }
}

function vtu_sync_data_plans(PDO $pdo, VTUnaijaAPI $api): string
{
    $service = $pdo->query("SELECT * FROM vtu_services WHERE code='data'")->fetch();
    $res = $api->listDataPlans();
    if (!$res['ok']) return 'Data Plans sync FAILED: ' . ($res['message'] ?: 'Unknown error. Check your API key.');
    $added = 0; $skipped = 0;
    foreach ($res['data']['dataplans'] ?? [] as $p) {
        $code = vtu_match_code($p['the_network_name'] ?? '', VTU_NETWORK_CODE_MAP);
        if (!$code || ($p['status'] ?? 'On') !== 'On') { $skipped++; continue; }
        $providerId = vtu_upsert_provider($pdo, (int) $service['id'], $code, strtoupper($p['the_network_name']));
        vtu_upsert_plan(
            $pdo, $providerId, (string) $p['data_plan_id'],
            ($p['size'] ?? '') . ' (' . ($p['the_datatype_name'] ?? '') . ')',
            (float) ($p['price_for_basicuser'] ?? 0), (float) $service['markup_percent'],
            ($p['duration'] ?? '') . ' days'
        );
        $added++;
    }
    return "Data Plans: synced {$added} plans" . ($skipped ? ", skipped {$skipped} (unmatched network or inactive)" : '') . '.';
}

function vtu_sync_cable_plans(PDO $pdo, VTUnaijaAPI $api): string
{
    $service = $pdo->query("SELECT * FROM vtu_services WHERE code='cable'")->fetch();
    $res = $api->listCableTvPlans();
    if (!$res['ok']) return 'Cable TV sync FAILED: ' . ($res['message'] ?: 'Unknown error. Check your API key.');
    $added = 0; $skipped = 0;
    foreach ($res['data']['cabletvplans'] ?? [] as $p) {
        $code = vtu_match_code($p['the_cabletv_name'] ?? '', VTU_CABLE_CODE_MAP);
        if (!$code || ($p['status'] ?? 'On') !== 'On') { $skipped++; continue; }
        $providerId = vtu_upsert_provider($pdo, (int) $service['id'], $code, strtoupper($p['the_cabletv_name']));
        vtu_upsert_plan(
            $pdo, $providerId, (string) $p['cabletv_plan_id'], $p['size'] ?? '',
            (float) ($p['price_for_basicuser'] ?? 0), (float) $service['markup_percent'],
            ($p['duration'] ?? '') . ' days'
        );
        $added++;
    }
    return "Cable TV Plans: synced {$added} plans" . ($skipped ? ", skipped {$skipped}" : '') . '.';
}

function vtu_sync_electricity(PDO $pdo, VTUnaijaAPI $api): string
{
    $service = $pdo->query("SELECT * FROM vtu_services WHERE code='electricity'")->fetch();
    $res = $api->listElectricityPlanIds();
    if (!$res['ok']) return 'Electricity sync FAILED: ' . ($res['message'] ?: 'Unknown error. Check your API key.');
    $added = 0;
    foreach ($res['data']['electricityplanids'] ?? [] as $p) {
        // Electricity has no fixed plan price (user enters an amount), so we
        // only sync the disco list as providers, not plans.
        vtu_upsert_provider($pdo, (int) $service['id'], (string) $p['electricity_plan_id'], $p['the_electricty_name'] ?? 'Disco');
        $added++;
    }
    return "Electricity Discos: synced {$added} providers.";
}

$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sync'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $log[] = 'Invalid request.';
    } else {
        $api = new VTUnaijaAPI();
        $log[] = vtu_sync_data_plans($pdo, $api);
        $log[] = vtu_sync_cable_plans($pdo, $api);
        $log[] = vtu_sync_electricity($pdo, $api);
    }
}

$planCounts = $pdo->query(
    "SELECT s.name, COUNT(p.id) cnt FROM vtu_services s
     LEFT JOIN vtu_providers pr ON pr.service_id = s.id
     LEFT JOIN vtu_plans p ON p.provider_id = pr.id
     GROUP BY s.id, s.name ORDER BY s.sort_order"
)->fetchAll();
?>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Sync Plans from VTUnaija</h3>
  <p style="font-size:12px;color:var(--text3);margin-bottom:16px;">
    Pulls the latest Data, Cable TV, and Electricity disco lists directly from VTUnaija and updates your local prices
    (markup is applied automatically per service). Airtime doesn't need syncing — users enter any amount directly.
  </p>
  <form method="POST">
    <?= csrf_field() ?>
    <button type="submit" name="run_sync" value="1" class="btn btn-primary"><i class="fi fi-rr-refresh"></i> Run Sync Now</button>
  </form>
</div>

<?php if (!empty($log)): ?>
<div class="glass" style="padding:16px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;font-size:14px;">Sync Results</h3>
  <?php foreach ($log as $line): ?>
  <div style="font-size:13px;padding:6px 0;border-bottom:1px solid var(--border);"><?= clean($line) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h3>Current Plan Counts</h3>
<div class="table-wrap">
<table class="admin-table">
  <thead><tr><th>Service</th><th>Plans Synced</th></tr></thead>
  <tbody>
    <?php foreach ($planCounts as $p): ?>
    <tr><td><?= clean($p['name']) ?></td><td><?= number_format($p['cnt']) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
