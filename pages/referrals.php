<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Refer & Earn – ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';

$pct = (float) get_setting('referral_bonus_percent', '2');

// Make sure this user has a ref_code (covers accounts created before this feature)
if (empty($user['ref_code'])) {
    $newCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $pdo->prepare("UPDATE users SET ref_code = ? WHERE id = ?")->execute([$newCode, $user['id']]);
    $user['ref_code'] = $newCode;
}

$refLink = SITE_URL . '/register.php?ref=' . $user['ref_code'];

$referred = $pdo->prepare(
    "SELECT username, created_at, first_deposit_at FROM users WHERE referred_by = ? ORDER BY created_at DESC"
);
$referred->execute([$user['id']]);
$referred = $referred->fetchAll();

$earnedStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions
     WHERE user_id = ? AND type = 'credit' AND description LIKE 'Referral bonus%'"
);
$earnedStmt->execute([$user['id']]);
$totalEarned = (float) $earnedStmt->fetchColumn();
?>

<div class="glass" style="padding:22px;border-radius:var(--radius);text-align:center;margin-bottom:16px;">
  <i class="fi fi-rr-gift" style="font-size:32px;color:var(--primary);"></i>
  <h2 style="margin:10px 0 4px;">Refer & Earn</h2>
  <p style="color:var(--text3);font-size:14px;margin:0;">
    Share your link. When a friend signs up and makes their <strong>first deposit</strong>,
    you instantly earn <strong><?= clean(rtrim(rtrim(number_format($pct, 2), '0'), '.')) ?>%</strong> of that deposit.
  </p>
</div>

<div class="glass" style="padding:18px;border-radius:var(--radius);margin-bottom:16px;">
  <label class="form-label">Your Referral Link</label>
  <div style="display:flex;gap:8px;">
    <input type="text" id="refLinkInput" class="form-control" value="<?= clean($refLink) ?>" readonly>
    <button class="btn btn-primary" onclick="copyRefLink()"><i class="fi fi-rr-copy"></i></button>
  </div>
  <div style="font-size:12px;color:var(--text3);margin-top:6px;">Your code: <strong><?= clean($user['ref_code']) ?></strong></div>
</div>

<div class="admin-stat-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px;">
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-users"></i></div>
    <div class="s-num"><?= count($referred) ?></div>
    <div class="s-lbl">Friends Referred</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-sack" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">$<?= number_format($totalEarned, 4) ?></div>
    <div class="s-lbl">Total Earned</div>
  </div>
</div>

<h3>Your Referrals</h3>
<?php if (empty($referred)): ?>
  <p style="color:var(--text3);">No referrals yet — share your link above to start earning.</p>
<?php else: ?>
<div class="table-wrap">
<table class="admin-table">
  <thead><tr><th>User</th><th>Joined</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($referred as $r): ?>
    <tr>
      <td><?= clean($r['username']) ?></td>
      <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
      <td>
        <?php if (!empty($r['first_deposit_at'])): ?>
          <span style="color:#22c55e;font-weight:600;">Bonus earned</span>
        <?php else: ?>
          <span style="color:var(--text3);">Waiting for first deposit</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<script>
function copyRefLink() {
    const input = document.getElementById('refLinkInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Referral link copied!');
    }).catch(() => {
        document.execCommand('copy');
        alert('Referral link copied!');
    });
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
