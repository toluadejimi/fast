<?php
// ============================================================
//  bigboss/badges.php — Verification badge thresholds + list
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Verified Buyers';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $t1 = (float) ($_POST['badge_tier1_ngn'] ?? 20000);
        $t2 = (float) ($_POST['badge_tier2_ngn'] ?? 50000);
        $t3 = (float) ($_POST['badge_tier3_ngn'] ?? 100000);

        if ($t1 <= 0 || $t2 <= $t1 || $t3 <= $t2) {
            $error = 'Thresholds must be positive and increasing: Tier 1 < Tier 2 < Tier 3.';
        } else {
            set_setting('badge_tier1_ngn', (string) $t1);
            set_setting('badge_tier2_ngn', (string) $t2);
            set_setting('badge_tier3_ngn', (string) $t3);
            $success = 'Thresholds updated.';
        }
    }
}

$rate = get_usd_to_ngn_rate();
$tier1Ngn = (float) get_setting('badge_tier1_ngn', '20000');
$tier2Ngn = (float) get_setting('badge_tier2_ngn', '50000');
$tier3Ngn = (float) get_setting('badge_tier3_ngn', '100000');

$verifiedUsers = $pdo->query(
    "SELECT id, username, email, total_spent, badge_tier
     FROM users WHERE badge_tier > 0
     ORDER BY badge_tier DESC, total_spent DESC"
)->fetchAll();

$topLimit = (int) ($_GET['top'] ?? 50);
$topLimit = in_array($topLimit, [50, 200], true) ? $topLimit : 50;

$allUsers = $pdo->query(
    "SELECT id, username, email, total_spent, badge_tier
     FROM users ORDER BY total_spent DESC LIMIT {$topLimit}"
)->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Badge Thresholds</h3>
  <p style="color:var(--text3);font-size:13px;">
    Set how much a user must spend (lifetime, net of refunds) to earn each badge.
    Enter amounts in NGN — the system converts and compares against wallets automatically
    (wallets are USD-based). Current rate: <strong>$1 ≈ ₦<?= number_format($rate, 0) ?></strong>.
    Badges are never taken away once earned, even if a later refund lowers the user's total.
  </p>
  <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
    <?= csrf_field() ?>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label"><span class="verify-badge-inline"><?= verified_badge_html(1) ?></span> Tier 1 — Rising Buyer (₦)</label>
      <input type="number" name="badge_tier1_ngn" class="form-control" value="<?= (int)$tier1Ngn ?>" min="1" step="1" required>
      <div style="font-size:11px;color:var(--text3);margin-top:3px;">≈ $<?= number_format(ngn_to_usd($tier1Ngn), 2) ?></div>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label"><span class="verify-badge-inline"><?= verified_badge_html(2) ?></span> Tier 2 — Trusted Buyer (₦)</label>
      <input type="number" name="badge_tier2_ngn" class="form-control" value="<?= (int)$tier2Ngn ?>" min="1" step="1" required>
      <div style="font-size:11px;color:var(--text3);margin-top:3px;">≈ $<?= number_format(ngn_to_usd($tier2Ngn), 2) ?></div>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label"><span class="verify-badge-inline"><?= verified_badge_html(3) ?></span> Tier 3 — VIP Verified (₦)</label>
      <input type="number" name="badge_tier3_ngn" class="form-control" value="<?= (int)$tier3Ngn ?>" min="1" step="1" required>
      <div style="font-size:11px;color:var(--text3);margin-top:3px;">≈ $<?= number_format(ngn_to_usd($tier3Ngn), 2) ?></div>
    </div>

    <div style="grid-column:1/-1;">
      <button type="submit" class="btn btn-primary"><i class="fi fi-rr-check"></i> Save Thresholds</button>
    </div>
  </form>
</div>

<h3>Verified Users (<?= count($verifiedUsers) ?>)</h3>
<div class="table-wrap">
<table class="admin-table">
  <thead>
    <tr><th>User</th><th>Badge</th><th>Total Spent (USD)</th><th>≈ NGN</th></tr>
  </thead>
  <tbody>
    <?php if (empty($verifiedUsers)): ?>
    <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text3);">No verified users yet</td></tr>
    <?php endif; ?>
    <?php foreach ($verifiedUsers as $u): ?>
    <tr onclick="location.href='user-detail.php?id=<?= (int)$u['id'] ?>'" style="cursor:pointer;">
      <td><strong><?= clean($u['username']) ?></strong><br><small style="color:var(--text3);"><?= clean($u['email']) ?></small></td>
      <td><?= verified_badge_html((int)$u['badge_tier']) ?> <?= clean(badge_tier_label((int)$u['badge_tier'])) ?></td>
      <td style="font-weight:700;color:var(--primary);">$<?= number_format((float)$u['total_spent'], 2) ?></td>
      <td style="color:var(--text2);">₦<?= number_format((float)$u['total_spent'] * $rate, 0) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<h3 style="margin-top:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
  <span>All Users — Top Spenders</span>
  <span style="font-size:13px;font-weight:500;">
    <a href="?top=50" class="btn btn-sm <?= $topLimit===50 ? 'btn-primary' : '' ?>">Top 50</a>
    <a href="?top=200" class="btn btn-sm <?= $topLimit===200 ? 'btn-primary' : '' ?>">Top 200</a>
  </span>
</h3>
<div class="table-wrap">
<table class="admin-table">
  <thead>
    <tr><th>User</th><th>Badge</th><th>Total Spent (USD)</th></tr>
  </thead>
  <tbody>
    <?php foreach ($allUsers as $u): ?>
    <tr onclick="location.href='user-detail.php?id=<?= (int)$u['id'] ?>'" style="cursor:pointer;">
      <td><?= clean($u['username']) ?></td>
      <td><?= verified_badge_html((int)$u['badge_tier']) ?: '<span style="color:var(--text3);">—</span>' ?></td>
      <td>$<?= number_format((float)$u['total_spent'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
