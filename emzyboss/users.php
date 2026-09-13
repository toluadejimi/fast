<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Users';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $uid    = (int)($_POST['user_id'] ?? 0);

        if ($action === 'toggle_status' && $uid) {
            $s   = $pdo->prepare("SELECT status FROM users WHERE id=?");
            $s->execute([$uid]);
            $cur = $s->fetchColumn();
            $new = $cur === 'active' ? 'suspended' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new, $uid]);
            $success = "User #$uid status set to $new.";

        } elseif ($action === 'credit' && $uid) {
            $amount = (float)($_POST['amount'] ?? 0);
            $note   = clean($_POST['note'] ?? 'Admin credit');
            if ($amount > 0) {
                wallet_credit($uid, $amount, $note, gen_ref('ADM'));
                notify($uid, 'Wallet Credited', '$'.number_format($amount,2).' added by admin.', '/pages/fund-wallet.php');
                $success = '$'.number_format($amount,2)." credited to user #$uid.";
            } else { $error = 'Enter a valid USD amount ($).'; }

        } elseif ($action === 'debit' && $uid) {
            $amount = (float)($_POST['amount'] ?? 0);
            $note   = clean($_POST['note'] ?? 'Admin debit');
            if ($amount > 0) {
                $ok      = wallet_debit($uid, $amount, $note, gen_ref('ADM'));
                $success = $ok ? '$'.number_format($amount,2)." debited from user #$uid." : 'Insufficient balance.';
            } else { $error = 'Enter a valid USD amount ($).'; }

        } elseif ($action === 'reset_password' && $uid) {
            $pw = $_POST['new_password'] ?? '';
            if (strlen($pw) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                    ->execute([password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]), $uid]);
                $success = "Password reset for user #$uid.";
            }

        } elseif ($action === 'delete' && $uid) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            $success = "User #$uid deleted.";
        }
    }
}

// ── Safe search with prepared statements ─────────────────────
$search = trim($_GET['q'] ?? '');
$sql    = "SELECT u.*, va.account_number FROM users u
           LEFT JOIN virtual_accounts va ON va.user_id=u.id";
$params = [];
if ($search !== '') {
    $like    = '%' . $search . '%';
    $sql    .= " WHERE u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?";
    $params  = [$like, $like, $like];
}
$sql .= " ORDER BY u.created_at DESC LIMIT 200";
$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div class="d-flex justify-between align-center" style="margin-bottom:16px;flex-wrap:wrap;gap:8px;">
  <form method="GET" style="display:flex;gap:8px;">
    <input type="text" name="q" class="form-control" placeholder="Search username, email, phone..."
           value="<?= clean($search) ?>" style="width:280px;">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fi fi-rr-search"></i></button>
    <?php if ($search): ?><a href="?" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
  </form>
  <span style="font-size:13px;color:var(--text2);"><?= count($users) ?> user(s)</span>
</div>

<div class="table-wrap">
<table class="admin-table">
  <thead><tr>
    <th>#</th><th>User</th><th>Phone</th><th>Country</th><th>Balance</th><th>Virtual Account</th><th>Status</th><th>Joined</th><th>Actions</th>
  </tr></thead>
  <tbody>
  <?php if (empty($users)): ?>
  <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3);">No users found</td></tr>
  <?php endif; ?>
  <?php foreach ($users as $u): ?>
  <tr>
    <td style="color:var(--text3);font-size:12px;"><?= $u['id'] ?></td>
    <td>
      <strong><?= clean($u['username']) ?></strong> <?= verified_badge_html((int)($u['badge_tier'] ?? 0)) ?><br>
      <small style="color:var(--text3);"><?= clean($u['email']) ?></small>
    </td>
    <td><?= clean($u['phone']) ?></td>
    <td><?= clean($u['country'] ?? 'Nigeria') ?></td>
    <td style="font-weight:800;color:var(--primary);">$<?= number_format((float)$u['balance'],2) ?></td>
    <td style="font-family:monospace;font-size:12px;">
      <?= $u['account_number'] ? clean($u['account_number']) : '<span style="color:var(--text3);">—</span>' ?>
    </td>
    <td><span class="pill pill-<?= $u['status']==='active'?'success':'danger' ?>"><?= ucfirst($u['status']) ?></span></td>
    <td style="font-size:12px;color:var(--text2);"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
    <td>
      <div style="display:flex;gap:4px;">
        <a href="user-detail.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm" title="View full profile"><i class="fi fi-rr-eye"></i></a>
        <button onclick="openUserModal(<?= $u['id'] ?>,'<?= clean($u['username']) ?>')"
                class="btn btn-primary btn-sm"><i class="fi fi-rr-pencil"></i></button>
        <form method="POST" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="toggle_status">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button type="submit" class="btn btn-sm <?= $u['status']==='active'?'btn-danger':'btn-success' ?>">
            <i class="fi fi-rr-<?= $u['status']==='active'?'ban':'check' ?>"></i>
          </button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- User modal -->
<div class="modal-overlay" id="user-modal" style="align-items:center;">
  <div class="modal-sheet" style="border-radius:var(--radius);max-width:460px;padding:24px;max-height:90vh;overflow-y:auto;">
    <h3 class="modal-title">Manage: <span id="modal-uname" style="color:var(--primary);"></span></h3>

    <div style="background:rgba(5,150,105,.08);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
      <div style="font-weight:700;color:var(--success);margin-bottom:8px;"><i class="fi fi-rr-add"></i> Credit Wallet</div>
      <form method="POST" style="display:flex;gap:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action"  value="credit">
        <input type="hidden" name="user_id" class="uid-f">
        <input type="number" name="amount"  class="form-control" placeholder="Amount (USD $)" min="0.01" step="0.01" required style="flex:1;">
        <input type="text"   name="note"    class="form-control" value="Admin credit" style="flex:1;">
        <button type="submit" class="btn btn-success btn-sm">Add</button>
      </form>
    </div>

    <div style="background:rgba(220,38,38,.08);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
      <div style="font-weight:700;color:var(--danger);margin-bottom:8px;"><i class="fi fi-rr-minus-small"></i> Debit Wallet</div>
      <form method="POST" style="display:flex;gap:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action"  value="debit">
        <input type="hidden" name="user_id" class="uid-f">
        <input type="number" name="amount"  class="form-control" placeholder="Amount (USD $)" min="0.01" step="0.01" required style="flex:1;">
        <input type="text"   name="note"    class="form-control" value="Admin debit" style="flex:1;">
        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
      </form>
    </div>

    <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
      <div style="font-weight:700;margin-bottom:8px;"><i class="fi fi-rr-key"></i> Reset Password</div>
      <form method="POST" style="display:flex;gap:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action"       value="reset_password">
        <input type="hidden" name="user_id"      class="uid-f">
        <input type="password" name="new_password" class="form-control" placeholder="New password (min 6)" required style="flex:1;">
        <button type="submit" class="btn btn-primary btn-sm">Reset</button>
      </form>
    </div>

    <form method="POST" onsubmit="return confirm('Delete this user permanently? This cannot be undone.')">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="delete">
      <input type="hidden" name="user_id" class="uid-f">
      <button type="submit" class="btn btn-danger btn-block"><i class="fi fi-rr-trash"></i> Delete User</button>
    </form>
    <button onclick="closeModal('user-modal')" class="btn btn-ghost btn-block mt-1">Close</button>
  </div>
</div>

<script>
function openUserModal(id, name) {
  document.getElementById('modal-uname').textContent = name + ' (#' + id + ')';
  document.querySelectorAll('.uid-f').forEach(el => el.value = id);
  openModal('user-modal');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
