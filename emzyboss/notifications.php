<?php
// ============================================================
//  DonnieSMS OTP – bigboss/notifications.php  (FIXED)
//  Admin: Send broadcast notifications to all users / specific user
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

// ── Handle send ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } elseif (isset($_POST['send_broadcast'])) {
        $title   = trim($_POST['notif_title']   ?? '');
        $msg     = trim($_POST['notif_message']  ?? '');
        $link    = trim($_POST['notif_link']     ?? '#');
        $target  = $_POST['target'] ?? 'all';

        if (!$title || !$msg) {
            $error = 'Title and message are required.';
        } else {
            if ($target === 'all') {
                // use status column (no is_banned on this schema)
                $users = $pdo->query("SELECT id FROM users WHERE status='active'")->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                $users = [(int)$target];
            }

            $sent = 0;
            foreach ($users as $uid) {
                notify((int)$uid, $title, $msg, $link ?: '#');
                $sent++;
            }
            $success = "✅ Notification sent to {$sent} user(s) successfully.";
        }
    }
}

// ── Load recent notifications history ────────────────────────
$history = $pdo->query(
    "SELECT n.*, u.username
     FROM notifications n
     LEFT JOIN users u ON u.id = n.user_id
     ORDER BY n.created_at DESC
     LIMIT 100"
)->fetchAll();

// ── Load users list for dropdown ─────────────────────────────
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY username ASC")->fetchAll();
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= $success ?></span></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:20px;align-items:start;">

  <!-- ── Send form ── -->
  <div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <h3 style="font-weight:800;font-size:15px;margin-bottom:16px;">
      <i class="fi fi-rr-megaphone" style="color:var(--primary);"></i>
      Send Notification
    </h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="send_broadcast" value="1">

      <div class="form-group">
        <label class="form-label">Target</label>
        <select name="target" class="form-control">
          <option value="all">📢 All Active Users (<?= $totalActive ?>)</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= (int)$u['id'] ?>">
            👤 <?= clean($u['username']) ?> · <?= clean($u['email']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Title</label>
        <div class="input-wrap">
          <i class="fi fi-rr-bell"></i>
          <input type="text" name="notif_title" id="notif_title" class="form-control"
                 placeholder="e.g. System Update" maxlength="100" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Message</label>
        <textarea name="notif_message" id="notif_message" class="form-control" rows="4"
                  placeholder="Write your notification message here…"
                  maxlength="255" required style="resize:vertical;"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">
          Link URL
          <span style="color:var(--text3);font-weight:400;">(optional)</span>
        </label>
        <div class="input-wrap">
          <i class="fi fi-rr-link"></i>
          <input type="text" name="notif_link" id="notif_link" class="form-control"
                 placeholder="/pages/dashboard.php">
        </div>
      </div>

      <!-- Quick templates -->
      <div style="margin-bottom:14px;">
        <div style="font-size:11px;color:var(--text3);font-weight:600;margin-bottom:6px;text-transform:uppercase;">Quick Templates</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          <button type="button" class="btn btn-ghost btn-sm" style="font-size:11px;"
            onclick="tpl('Maintenance Notice','The site will undergo maintenance shortly. Please save your work.','#')">
            🔧 Maintenance
          </button>
          <button type="button" class="btn btn-ghost btn-sm" style="font-size:11px;"
            onclick="tpl('New Feature!','We have added exciting new features. Check them out now!','/pages/dashboard.php')">
            ✨ New Feature
          </button>
          <button type="button" class="btn btn-ghost btn-sm" style="font-size:11px;"
            onclick="tpl('Fund Your Wallet','Top up your wallet today and enjoy uninterrupted OTP services.','/pages/fund-wallet.php')">
            💰 Fund Wallet
          </button>
          <button type="button" class="btn btn-ghost btn-sm" style="font-size:11px;"
            onclick="tpl('Important Update','We have updated our service terms. Please review them.','/pages/dashboard.php')">
            📋 Policy
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="padding:13px;">
        <i class="fi fi-rr-paper-plane"></i> Send Notification
      </button>
    </form>
  </div>

  <!-- ── History panel ── -->
  <div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-weight:800;font-size:15px;margin:0;">
        <i class="fi fi-rr-list" style="color:var(--primary);"></i>
        Recent Notifications
      </h3>
      <span class="pill pill-info" style="font-size:11px;"><?= count($history) ?> shown</span>
    </div>

    <?php if (empty($history)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text3);">
      <i class="fi fi-rr-bell" style="font-size:36px;display:block;margin-bottom:10px;opacity:.3;"></i>
      <p>No notifications yet. Send one above!</p>
    </div>
    <?php else: ?>
    <div style="max-height:560px;overflow-y:auto;padding-right:4px;">
      <?php foreach ($history as $n): ?>
      <div style="border-left:3px solid var(--primary);padding:10px 12px;margin-bottom:8px;
                  background:var(--glass);border-radius:0 var(--radius-sm) var(--radius-sm) 0;">
        <div style="font-size:13px;font-weight:700;color:var(--text);"><?= clean($n['title']) ?></div>
        <div style="font-size:12px;color:var(--text2);margin-top:2px;"><?= clean($n['message']) ?></div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;display:flex;gap:10px;flex-wrap:wrap;">
          <span>👤 <strong><?= clean($n['username'] ?? 'Unknown') ?></strong></span>
          <span>🕒 <?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
          <?php if ($n['is_read']): ?>
          <span style="color:var(--success);">✓ Read</span>
          <?php else: ?>
          <span style="color:var(--warning);">● Unread</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function tpl(title, msg, link) {
  document.getElementById('notif_title').value   = title;
  document.getElementById('notif_message').value = msg;
  document.getElementById('notif_link').value    = link;
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
