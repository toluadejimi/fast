<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Support – ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';

$error = $success = '';
$view  = (int)($_GET['ticket_id'] ?? 0);

$uploadDir = ROOT_PATH . '/assets/uploads/support/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

/** Validates and moves an uploaded screenshot/image. Returns filename or null (with $error set on failure). */
function upload_support_image(string $uploadDir, ?string &$error): ?string {
    if (empty($_FILES['attachment']['name'])) return null;

    $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp'];

    if (!in_array($ext, $allowed)) {
        $error = 'Attachment must be an image (PNG, JPG, or WebP).';
        return null;
    }
    if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
        $error = 'Image must be under 5MB.';
        return null;
    }

    $filename = 'sup_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
        $error = 'Attachment upload failed. Please try again.';
        return null;
    }
    return $filename;
}

/** Sends a rate-limited (2/day by default) instant auto-reply for a ticket's category, if one is configured. */
function maybe_send_support_autoreply(int $ticketId, int $userId, int $categoryId): void {
    global $pdo;

    $dailyLimit = (int) get_setting('support_auto_reply_daily_limit', '2');
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM support_replies sr
         JOIN support_tickets st ON st.id = sr.ticket_id
         WHERE st.user_id = ? AND sr.is_auto = 1 AND DATE(sr.created_at) = CURDATE()"
    );
    $countStmt->execute([$userId]);
    if ((int) $countStmt->fetchColumn() >= $dailyLimit) return;

    $cat = $pdo->prepare("SELECT auto_reply FROM support_categories WHERE id = ? AND is_active = 1");
    $cat->execute([$categoryId]);
    $autoReplyText = $cat->fetchColumn();
    if (!$autoReplyText) return;

    $pdo->prepare("INSERT INTO support_replies (ticket_id,sender,message,is_auto) VALUES (?,'admin',?,1)")
        ->execute([$ticketId, $autoReplyText]);
    $pdo->prepare("UPDATE support_tickets SET status='replied' WHERE id=?")->execute([$ticketId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['new_ticket'])) {
        $subject    = clean($_POST['subject'] ?? '');
        $message    = clean($_POST['message'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if (strlen($subject) < 5)  { $error = 'Subject is too short (min 5 characters).'; }
        elseif (strlen($message) < 10) { $error = 'Message is too short (min 10 characters).'; }
        elseif ($categoryId < 1) { $error = 'Please select what you need help with.'; }
        else {
            $attachment = upload_support_image($uploadDir, $error);
            if ($error === '') {
                $pdo->prepare("INSERT INTO support_tickets (user_id,subject,category_id) VALUES (?,?,?)")->execute([$user['id'],$subject,$categoryId]);
                $tid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO support_replies (ticket_id,sender,message,attachment) VALUES (?,'user',?,?)")->execute([$tid,$message,$attachment]);
                notify($user['id'],'Ticket Submitted','Your support ticket #'.$tid.' has been submitted.','/pages/support.php?ticket_id='.$tid);

                maybe_send_support_autoreply($tid, $user['id'], $categoryId);

                $success = 'Ticket submitted! We will reply shortly.';
                redirect(SITE_URL.'/pages/support.php?ticket_id='.$tid);
            }
        }
    } elseif (isset($_POST['reply_ticket'])) {
        $tid     = (int)($_POST['ticket_id'] ?? 0);
        $message = clean($_POST['message'] ?? '');
        $s = $pdo->prepare("SELECT id,status,category_id FROM support_tickets WHERE id=? AND user_id=?");
        $s->execute([$tid,$user['id']]); $ticket = $s->fetch();
        if (!$ticket)                    { $error = 'Ticket not found.'; }
        elseif ($ticket['status']==='closed') { $error = 'This ticket is closed.'; }
        elseif (strlen($message) < 2 && empty($_FILES['attachment']['name']))   { $error = 'Message too short.'; }
        else {
            $attachment = upload_support_image($uploadDir, $error);
            if ($error === '') {
                $pdo->prepare("INSERT INTO support_replies (ticket_id,sender,message,attachment) VALUES (?,'user',?,?)")->execute([$tid,$message,$attachment]);
                $pdo->prepare("UPDATE support_tickets SET status='open' WHERE id=?")->execute([$tid]);

                if (!empty($ticket['category_id'])) {
                    maybe_send_support_autoreply($tid, $user['id'], (int) $ticket['category_id']);
                }

                redirect(SITE_URL.'/pages/support.php?ticket_id='.$tid);
            }
        }
    }
}

// Load ticket detail
$ticketDetail = null; $replies = [];
if ($view) {
    $s = $pdo->prepare("SELECT * FROM support_tickets WHERE id=? AND user_id=?");
    $s->execute([$view,$user['id']]); $ticketDetail = $s->fetch();
    if ($ticketDetail) {
        $r = $pdo->prepare("SELECT * FROM support_replies WHERE ticket_id=? ORDER BY created_at ASC");
        $r->execute([$view]); $replies = $r->fetchAll();
    }
}
$categories = $pdo->query("SELECT * FROM support_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

$tickets = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id=? ORDER BY created_at DESC");
$tickets->execute([$user['id']]); $ticketList = $tickets->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<?php if ($ticketDetail): ?>
<!-- Ticket view -->
<div class="d-flex align-center gap-2" style="margin-bottom:14px;">
  <a href="<?= SITE_URL ?>/pages/support.php"
     style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;
            background:var(--card-bg);border:1.5px solid var(--border);text-decoration:none;color:var(--text2);">
    <i class="fi fi-rr-arrow-left" style="font-size:14px;"></i>
  </a>
  <div>
    <div style="font-size:16px;font-weight:800;"><?= clean($ticketDetail['subject']) ?></div>
    <span class="pill pill-<?= $ticketDetail['status']==='open'?'pending':($ticketDetail['status']==='replied'?'success':'gray') ?>" style="margin-top:3px;">
      <?= ucfirst($ticketDetail['status']) ?>
    </span>
  </div>
</div>
<div class="chat-wrap">
  <?php foreach ($replies as $r): ?>
  <div class="msg msg-<?= $r['sender']==='user'?'user':'admin' ?>">
    <?php if (!empty($r['message'])): ?><?= nl2br(clean($r['message'])) ?><?php endif; ?>
    <?php if (!empty($r['attachment'])): ?>
    <a href="<?= SITE_URL ?>/assets/uploads/support/<?= clean($r['attachment']) ?>" target="_blank">
      <img src="<?= SITE_URL ?>/assets/uploads/support/<?= clean($r['attachment']) ?>" style="max-width:200px;border-radius:8px;margin-top:6px;display:block;">
    </a>
    <?php endif; ?>
    <small><?= $r['sender']==='admin'?'Support Team':'You' ?><?= !empty($r['is_auto']) ? ' (Auto-Reply)' : '' ?> · <?= date('d M, H:i',strtotime($r['created_at'])) ?></small>
  </div>
  <?php endforeach; ?>
</div>
<?php if ($ticketDetail['status'] !== 'closed'): ?>
<form method="POST" enctype="multipart/form-data" style="margin-top:14px;">
  <?= csrf_field() ?>
  <input type="hidden" name="ticket_id" value="<?= $view ?>">
  <div class="form-group">
    <textarea name="message" class="form-control" rows="3"
              placeholder="Type your reply..." style="resize:none;"></textarea>
  </div>
  <div class="form-group">
    <label class="form-label" style="font-size:12px;"><i class="fi fi-rr-picture"></i> Attach a screenshot (optional)</label>
    <input type="file" name="attachment" class="form-control" accept="image/png,image/jpeg,image/webp">
  </div>
  <button type="submit" name="reply_ticket" class="btn btn-primary btn-block">
    <i class="fi fi-rr-paper-plane"></i> Send Reply
  </button>
</form>
<?php else: ?>
<div class="alert alert-info" style="margin-top:14px;">
  <i class="fi fi-rr-lock"></i><span>This ticket is closed.</span>
</div>
<?php endif; ?>

<?php else: ?>
<!-- Ticket list -->
<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">Support</h2>
<button onclick="openModal('new-ticket-modal')" class="btn btn-primary btn-block" style="margin-bottom:14px;">
  <i class="fi fi-rr-add"></i> Open New Ticket
</button>
<?php if (empty($ticketList)): ?>
<div class="empty">
  <i class="fi fi-rr-headset" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No tickets yet.<br>Need help? Open a ticket above.</p>
</div>
<?php else: ?>
<?php foreach ($ticketList as $t): ?>
<a href="<?= SITE_URL ?>/pages/support.php?ticket_id=<?= $t['id'] ?>" style="text-decoration:none;">
  <div class="ticket-item">
    <div class="d-flex justify-between align-center" style="margin-bottom:4px;">
      <div style="font-size:14px;font-weight:700;color:var(--text);"><?= clean($t['subject']) ?></div>
      <span class="pill pill-<?= $t['status']==='open'?'pending':($t['status']==='replied'?'success':'gray') ?>">
        <?= ucfirst($t['status']) ?>
      </span>
    </div>
    <div class="text-muted text-sm">Ticket #<?= $t['id'] ?> · <?= date('d M Y, H:i',strtotime($t['created_at'])) ?></div>
  </div>
</a>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<!-- New Ticket Modal -->
<div class="modal-overlay" id="new-ticket-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <h3 class="modal-title">Open Support Ticket</h3>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">What do you need? <span style="color:var(--danger);">*</span></label>
        <select name="category_id" class="form-control" required>
          <option value="">-- Select an option --</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>"><?= clean($cat['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Subject</label>
        <input type="text" name="subject" class="form-control" placeholder="Brief description of your issue" required>
      </div>
      <div class="form-group">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" rows="4"
                  placeholder="Describe your issue in detail..." required style="resize:vertical;"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" style="font-size:12px;"><i class="fi fi-rr-picture"></i> Attach a screenshot (optional)</label>
        <input type="file" name="attachment" class="form-control" accept="image/png,image/jpeg,image/webp">
      </div>
      <button type="submit" name="new_ticket" class="btn btn-primary btn-block">
        <i class="fi fi-rr-paper-plane"></i> Submit Ticket
      </button>
      <button type="button" onclick="closeModal('new-ticket-modal')" class="btn btn-ghost btn-block mt-1">Cancel</button>
    </form>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
