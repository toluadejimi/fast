<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'VTU Services';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['save_api_key'])) {
        set_setting('vtu_api_key', trim($_POST['vtu_api_key'] ?? ''));
        $success = 'API key saved.';
    } elseif (isset($_POST['save_markup'])) {
        foreach ($_POST['markup'] ?? [] as $serviceId => $val) {
            $pdo->prepare("UPDATE vtu_services SET markup_percent = ? WHERE id = ?")
                ->execute([(float) $val, (int) $serviceId]);
        }
        // Toggle active/inactive: any service NOT present in $_POST['active'] gets turned off
        $allServiceIds = $pdo->query("SELECT id FROM vtu_services")->fetchAll(PDO::FETCH_COLUMN);
        $activeIds = array_map('intval', array_keys($_POST['active'] ?? []));
        foreach ($allServiceIds as $sid) {
            $pdo->prepare("UPDATE vtu_services SET is_active = ? WHERE id = ?")
                ->execute([in_array((int)$sid, $activeIds, true) ? 1 : 0, $sid]);
        }
        $success = 'Services updated.';
    }
}

$apiKey = get_setting('vtu_api_key', '');
$services = $pdo->query("SELECT * FROM vtu_services ORDER BY sort_order")->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">VTUnaija API Key</h3>
  <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <?= csrf_field() ?>
    <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0;">
      <label class="form-label">API Key</label>
      <input type="text" name="vtu_api_key" class="form-control" value="<?= clean($apiKey) ?>" placeholder="Your VTUnaija API key">
    </div>
    <button type="submit" name="save_api_key" value="1" class="btn btn-primary">Save Key</button>
  </form>
</div>

<div class="glass" style="padding:20px;border-radius:var(--radius);">
  <h3 style="margin-top:0;">Services & Markup</h3>
  <p style="font-size:12px;color:var(--text3);margin-bottom:16px;">Markup % is applied automatically to every plan when you sync, and to Airtime/Electricity amounts at purchase time.</p>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Service</th><th>Active</th><th>Markup %</th></tr></thead>
      <tbody>
        <?php foreach ($services as $s): ?>
        <tr>
          <td><i class="fi <?= clean($s['icon'] ?: 'fi-rr-bolt') ?>"></i> <?= clean($s['name']) ?></td>
          <td><input type="checkbox" name="active[<?= (int)$s['id'] ?>]" value="1" <?= $s['is_active'] ? 'checked' : '' ?>></td>
          <td><input type="number" name="markup[<?= (int)$s['id'] ?>]" class="form-control" style="max-width:100px;" value="<?= clean($s['markup_percent']) ?>" step="0.1" min="0"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <button type="submit" name="save_markup" value="1" class="btn btn-primary mt-2">Save Changes</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
