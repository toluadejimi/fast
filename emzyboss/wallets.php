<?php
// ============================================================
//  admin/wallets.php  (UPGRADED – shows USD amounts)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Wallets';
require_once __DIR__ . '/includes/admin_header.php';

$search = trim($_GET['q'] ?? '');
$type   = trim($_GET['type'] ?? '');

$sql    = "SELECT wt.*, u.username FROM wallet_transactions wt LEFT JOIN users u ON u.id=wt.user_id WHERE 1=1";
$params = [];

if ($search !== '') {
    $like    = '%' . $search . '%';
    $sql    .= " AND (u.username LIKE ? OR wt.reference LIKE ? OR wt.description LIKE ?)";
    $params  = array_merge($params, [$like, $like, $like]);
}
$allowedTypes = ['credit', 'debit'];
if ($type !== '' && in_array($type, $allowedTypes)) {
    $sql    .= " AND wt.type = ?";
    $params[] = $type;
}
$sql .= " ORDER BY wt.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$txns = $stmt->fetchAll();

$totalCredit = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='credit' AND status='success'")->fetchColumn();
$totalDebit  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='debit'  AND status='success'")->fetchColumn();
$rate        = get_usd_to_ngn_rate();
?>

<div class="admin-stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-arrow-down" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">$<?= number_format($totalCredit, 2) ?></div>
    <div class="s-lbl">Total Credited (USD)</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#fee2e2;"><i class="fi fi-rr-arrow-up" style="color:var(--danger);"></i></div>
    <div class="s-num" style="color:var(--danger);">$<?= number_format($totalDebit, 2) ?></div>
    <div class="s-lbl">Total Debited (USD)</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-balance-scale"></i></div>
    <div class="s-num">$<?= number_format($totalCredit - $totalDebit, 2) ?></div>
    <div class="s-lbl">Net Balance (USD)</div>
  </div>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
  <input type="text" name="q" class="form-control" placeholder="Search username, reference or description..."
         value="<?= clean($search) ?>" style="width:280px;">
  <select name="type" class="form-control" style="width:140px;">
    <option value="">All Types</option>
    <option value="credit" <?= $type==='credit'?'selected':'' ?>>Credit</option>
    <option value="debit"  <?= $type==='debit'?'selected':'' ?>>Debit</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fi fi-rr-search"></i></button>
  <a href="?" class="btn btn-ghost btn-sm">Reset</a>
</form>

<div class="table-wrap">
<table class="admin-table">
  <thead>
    <tr>
      <th>#</th><th>User</th><th>Type</th>
      <th>USD Amount</th><th>≈ NGN</th>
      <th>Description</th><th>Reference</th><th>Status</th><th>Date</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($txns)): ?>
  <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3);">No transactions found</td></tr>
  <?php endif; ?>
  <?php foreach ($txns as $t): ?>
  <tr>
    <td style="color:var(--text3);font-size:12px;"><?= $t['id'] ?></td>
    <td><strong><?= clean($t['username'] ?? '—') ?></strong></td>
    <td style="font-weight:700;color:<?= $t['type']==='credit'?'var(--success)':'var(--danger)' ?>;"><?= strtoupper($t['type']) ?></td>
    <td style="font-weight:800;color:<?= $t['type']==='credit'?'var(--success)':'var(--danger)' ?>;">
      <?= $t['type']==='credit'?'+':'-' ?>$<?= number_format((float)$t['amount'], 4) ?>
    </td>
    <td style="font-size:12px;color:var(--text2);">
      ₦<?= number_format((float)$t['amount'] * $rate, 0) ?>
    </td>
    <td style="font-size:12px;max-width:200px;" title="<?= clean($t['description']) ?>">
      <?= clean(mb_substr($t['description'], 0, 50)) ?><?= strlen($t['description']) > 50 ? '…' : '' ?>
    </td>
    <td style="font-family:monospace;font-size:11px;color:var(--text3);"><?= clean($t['reference']) ?></td>
    <td><span class="pill pill-<?= $t['status']==='success'?'success':'pending' ?>"><?= ucfirst($t['status']) ?></span></td>
    <td style="font-size:12px;color:var(--text2);"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
