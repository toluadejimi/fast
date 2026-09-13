<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'All Orders';
require_once __DIR__ . '/includes/admin_header.php';

// ── Safe search with prepared statements ─────────────────────
$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql    = "SELECT o.*, u.username FROM otp_orders o LEFT JOIN users u ON u.id=o.user_id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql    .= " AND (u.username LIKE ? OR o.phone LIKE ? OR o.service_name LIKE ?)";
    $like    = '%' . $search . '%';
    $params  = array_merge($params, [$like, $like, $like]);
}
$allowedStatus = ['PENDING','RECEIVED','CANCELED','EXPIRED','TIMEOUT'];
if ($status !== '' && in_array(strtoupper($status), $allowedStatus)) {
    $sql    .= " AND o.status = ?";
    $params[] = strtoupper($status);
}
$sql .= " ORDER BY o.created_at DESC LIMIT 300";

$stmt   = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalRev        = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM otp_orders WHERE status='RECEIVED'")->fetchColumn();
$totalCancelled  = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders WHERE status='CANCELED'")->fetchColumn();
$totalSuccessful = (int)$pdo->query("SELECT COUNT(*) FROM otp_orders WHERE status='RECEIVED'")->fetchColumn();

$stats = [
    ['PENDING',  'Pending',   'pending'],
    ['RECEIVED', 'Received',  'success'],
    ['CANCELED', 'Cancelled', 'gray'],
    ['EXPIRED',  'Expired',   'danger'],
];
?>

<div class="admin-stat-grid" style="grid-template-columns:repeat(3,1fr) repeat(3,1fr);margin-bottom:20px;">
  <?php foreach ($stats as [$st, $lbl, $cls]):
    $cnt = (int)$pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE status=?")->execute([$st]) ? 0 : 0;
    $s2  = $pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE status=?");
    $s2->execute([$st]);
    $cnt = (int)$s2->fetchColumn();
  ?>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-mobile"></i></div>
    <div class="s-num"><?= $cnt ?></div>
    <div class="s-lbl"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-check" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);"><?= $totalSuccessful ?></div>
    <div class="s-lbl">Successful</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#fee2e2;"><i class="fi fi-rr-cross-circle" style="color:var(--danger);"></i></div>
    <div class="s-num" style="color:var(--danger);"><?= $totalCancelled ?></div>
    <div class="s-lbl">Cancelled</div>
  </div>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
  <input type="text" name="q" class="form-control" placeholder="Search user, number, service..."
         value="<?= clean($search) ?>" style="width:240px;">
  <select name="status" class="form-control" style="width:150px;">
    <option value="">All Status</option>
    <?php foreach (['PENDING'=>'Pending','RECEIVED'=>'Received','CANCELED'=>'Cancelled','EXPIRED'=>'Expired','TIMEOUT'=>'Timeout'] as $v=>$l): ?>
    <option value="<?= $v ?>" <?= strtoupper($status)===$v?'selected':'' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fi fi-rr-search"></i></button>
  <a href="?" class="btn btn-ghost btn-sm">Reset</a>
  <span style="margin-left:auto;font-size:13px;color:var(--text2);align-self:center;">
    <?= count($orders) ?> orders shown &nbsp;·&nbsp; <span style="color:var(--success);font-weight:700;"><?= $totalSuccessful ?> Successful</span> &nbsp;·&nbsp; <span style="color:var(--danger);font-weight:700;"><?= $totalCancelled ?> Cancelled</span> &nbsp;·&nbsp; Revenue: $<?= number_format($totalRev,2) ?> USD
  </span>
</form>

<div class="table-wrap">
<table class="admin-table">
  <thead><tr>
    <th>#</th><th>User</th><th>Service</th><th>Number</th><th>OTP</th>
    <th>Country</th><th>Amount</th><th>Status</th><th>Date</th>
  </tr></thead>
  <tbody>
  <?php if (empty($orders)): ?>
  <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3);">No orders found</td></tr>
  <?php endif; ?>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td style="color:var(--text3);font-size:12px;"><?= $o['id'] ?></td>
    <td><strong><?= clean($o['username'] ?? '—') ?></strong></td>
    <td><?= clean($o['service_name']) ?></td>
    <td style="font-family:monospace;font-size:12px;"><?= clean($o['phone']) ?></td>
    <td style="font-weight:800;color:var(--success);font-family:monospace;letter-spacing:1px;">
      <?= $o['otp_code'] ? clean($o['otp_code']) : '—' ?>
    </td>
    <td style="font-size:12px;"><?= clean(ucwords($o['country'])) ?></td>
    <td style="font-weight:700;">$<?= number_format((float)$o['amount_paid'],2) ?></td>
    <td>
      <span class="pill pill-<?= $o['status']==='RECEIVED'?'success':($o['status']==='PENDING'?'pending':'gray') ?>">
        <?= ucfirst(strtolower($o['status'])) ?>
      </span>
    </td>
    <td style="font-size:12px;color:var(--text2);"><?= date('d M Y, H:i',strtotime($o['created_at'])) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
