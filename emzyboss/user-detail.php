<?php
// ============================================================
//  bigboss/user-detail.php — full profile view for one user
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'User Detail';
require_once __DIR__ . '/includes/admin_header.php';

$uid = (int) ($_GET['id'] ?? 0);

$s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$s->execute([$uid]);
$u = $s->fetch();

if (!$u) {
    echo '<div class="alert alert-danger">User not found.</div>';
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

$rate = get_usd_to_ngn_rate();

// Referred by
$referrerName = null;
if (!empty($u['referred_by'])) {
    $r = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $r->execute([$u['referred_by']]);
    $referrerName = $r->fetchColumn() ?: null;
}

// People this user referred
$refs = $pdo->prepare(
    "SELECT username, created_at, first_deposit_at FROM users WHERE referred_by = ? ORDER BY created_at DESC"
);
$refs->execute([$uid]);
$refs = $refs->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id=? AND type='credit' AND description LIKE 'Referral bonus%'");
$stmt->execute([$uid]);
$referralEarned = (float) $stmt->fetchColumn();

// Order counts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE user_id=?");
$stmt->execute([$uid]); $otpTotal = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE user_id=? AND status='RECEIVED'");
$stmt->execute([$uid]); $otpSuccess = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM log_orders WHERE user_id=?");
$stmt->execute([$uid]); $logsTotal = (int) $stmt->fetchColumn();

$totalOrders = $otpSuccess + $logsTotal;

// Recent wallet transactions
$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
$stmt->execute([$uid]);
$transactions = $stmt->fetchAll();
?>

<a href="users.php" style="font-size:13px;color:var(--text3);"><i class="fi fi-rr-angle-left"></i> Back to Users</a>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin:14px 0;">
  <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <div class="profile-avatar" style="width:52px;height:52px;font-size:18px;"><?= strtoupper(substr($u['username'],0,2)) ?></div>
    <div style="flex:1;min-width:200px;">
      <div style="font-size:19px;font-weight:900;"><?= clean($u['username']) ?> <?= verified_badge_html((int)$u['badge_tier']) ?></div>
      <div style="font-size:13px;color:var(--text3);"><?= clean($u['email']) ?> · <?= clean($u['phone'] ?: 'No phone on file') ?></div>
    </div>
    <span class="pill pill-<?= $u['status']==='active'?'success':'danger' ?>"><?= clean(ucfirst($u['status'])) ?></span>
  </div>
</div>

<div class="admin-stat-grid">
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-wallet"></i></div>
    <div class="s-num">$<?= number_format((float)$u['balance'], 4) ?></div>
    <div class="s-lbl">Wallet Balance</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico" style="background:#d1fae5;"><i class="fi fi-rr-sack" style="color:var(--success);"></i></div>
    <div class="s-num" style="color:var(--success);">$<?= number_format((float)$u['total_spent'], 4) ?></div>
    <div class="s-lbl">Total Spent</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-receipt"></i></div>
    <div class="s-num"><?= $totalOrders ?></div>
    <div class="s-lbl">Total Orders</div>
  </div>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi fi-rr-users"></i></div>
    <div class="s-num"><?= count($refs) ?></div>
    <div class="s-lbl">Referred Users</div>
  </div>
</div>

<div class="glass" style="padding:18px;border-radius:var(--radius);margin-bottom:16px;">
  <h3 style="margin-top:0;">Account Info</h3>
  <table class="admin-table" style="box-shadow:none;">
    <tr><td style="color:var(--text3);width:180px;">Joined</td><td><?= date('M j, Y g:i A', strtotime($u['created_at'])) ?></td></tr>
    <tr><td style="color:var(--text3);">Country</td><td><?= clean($u['country'] ?? 'Nigeria') ?></td></tr>
    <tr><td style="color:var(--text3);">Badge Tier</td><td><?= (int)$u['badge_tier'] > 0 ? verified_badge_html((int)$u['badge_tier']) . ' ' . clean(badge_tier_label((int)$u['badge_tier'])) : '— None yet' ?></td></tr>
    <tr><td style="color:var(--text3);">Referral Code</td><td><?= clean($u['ref_code'] ?: '—') ?></td></tr>
    <tr><td style="color:var(--text3);">Referred By</td><td><?= $referrerName ? clean($referrerName) : '— Organic signup' ?></td></tr>
    <tr><td style="color:var(--text3);">Earned From Referrals</td><td>$<?= number_format($referralEarned, 4) ?></td></tr>
    <tr><td style="color:var(--text3);">First Deposit</td><td><?= !empty($u['first_deposit_at']) ? date('M j, Y', strtotime($u['first_deposit_at'])) : '— Not yet' ?></td></tr>
    <tr><td style="color:var(--text3);">OTP Orders</td><td><?= $otpSuccess ?> successful / <?= $otpTotal ?> total attempted</td></tr>
    <tr><td style="color:var(--text3);">Logs Orders</td><td><?= $logsTotal ?></td></tr>
    <tr><td style="color:var(--text3);">Sign-in Method</td><td><?= !empty($u['google_id']) ? '<i class="fi fi-brands-google"></i> Google' : 'Email/Password' ?></td></tr>
  </table>
</div>

<?php if (!empty($refs)): ?>
<h3>Users They Referred (<?= count($refs) ?>)</h3>
<div class="table-wrap">
<table class="admin-table">
  <thead><tr><th>Username</th><th>Joined</th><th>Status</th></tr></thead>
  <tbody>
    <?php foreach ($refs as $r): ?>
    <tr>
      <td><?= clean($r['username']) ?></td>
      <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
      <td><?= !empty($r['first_deposit_at']) ? '<span style="color:#22c55e;">Deposited</span>' : '<span style="color:var(--text3);">No deposit yet</span>' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<h3 style="margin-top:20px;">Recent Wallet Activity</h3>
<div class="table-wrap">
<table class="admin-table">
  <thead><tr><th>Description</th><th>Amount</th><th>Date</th></tr></thead>
  <tbody>
    <?php if (empty($transactions)): ?>
    <tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text3);">No transactions yet</td></tr>
    <?php endif; ?>
    <?php foreach ($transactions as $t): ?>
    <tr>
      <td><?= clean($t['description']) ?></td>
      <td style="color:<?= $t['type']==='credit' ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600;">
        <?= $t['type']==='credit' ? '+' : '−' ?>$<?= number_format(abs((float)$t['amount']), 4) ?>
      </td>
      <td style="color:var(--text3);"><?= date('M j, g:i A', strtotime($t['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
