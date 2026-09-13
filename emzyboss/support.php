<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Support Tickets';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';
$view  = (int)($_GET['ticket_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['admin_reply'])) {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $msg = clean($_POST['message'] ?? '');
        if (strlen($msg) >= 2) {
            $pdo->prepare("INSERT INTO support_replies (ticket_id,sender,message) VALUES (?,'admin',?)")->execute([$tid,$msg]);
            $pdo->prepare("UPDATE support_tickets SET status='replied' WHERE id=?")->execute([$tid]);
            $t = $pdo->prepare("SELECT user_id FROM support_tickets WHERE id=?"); $t->execute([$tid]); $t=$t->fetch();
            if ($t) notify($t['user_id'],'Support Reply','Admin replied to your ticket #'.$tid.'.', '/pages/support.php?ticket_id='.$tid);
            redirect(SITE_URL.'/'.ADMIN_PATH.'/support.php?ticket_id='.$tid);
        } else { $error = 'Reply message too short.'; }
    } elseif (isset($_POST['close_ticket'])) {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $pdo->prepare("UPDATE support_tickets SET status='closed' WHERE id=?")->execute([$tid]);
        redirect(SITE_URL.'/'.ADMIN_PATH.'/support.php');
    }
}

$ticketDetail = null; $replies = [];
if ($view) {
    $s = $pdo->prepare("SELECT st.*,u.username,u.email,sc.label AS category_label FROM support_tickets st LEFT JOIN users u ON u.id=st.user_id LEFT JOIN support_categories sc ON sc.id=st.category_id WHERE st.id=?");
    $s->execute([$view]); $ticketDetail = $s->fetch();
    if ($ticketDetail) {
        $r = $pdo->prepare("SELECT * FROM support_replies WHERE ticket_id=? ORDER BY created_at ASC");
        $r->execute([$view]); $replies = $r->fetchAll();
    }
}

$tickets   = $pdo->query("SELECT st.*,u.username,sc.label AS category_label FROM support_tickets st LEFT JOIN users u ON u.id=st.user_id LEFT JOIN support_categories sc ON sc.id=st.category_id ORDER BY FIELD(st.status,'open','replied','closed'), st.created_at DESC LIMIT 100")->fetchAll();
$openCount = count(array_filter($tickets, fn($t) => $t['status']==='open'));
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;min-height:600px;">

<!-- Ticket List -->
<div style="background:var(--bg2);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;display:flex;flex-direction:column;">
  <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:800;display:flex;align-items:center;justify-content:space-between;">
    <span>Tickets (<?= count($tickets) ?>)</span>
    <?php if ($openCount > 0): ?>
    <span class="pill pill-pending"><?= $openCount ?> open</span>
    <?php endif; ?>
  </div>
  <div style="overflow-y:auto;flex:1;">
    <?php if (empty($tickets)): ?>
    <div style="padding:30px;text-align:center;color:var(--text3);">No tickets yet</div>
    <?php endif; ?>
    <?php foreach ($tickets as $t): ?>
    <a href="/<?= ADMIN_PATH ?>/support.php?ticket_id=<?= $t['id'] ?>"
       style="display:block;padding:12px 16px;border-bottom:1px solid var(--border);text-decoration:none;
              background:<?= $view==$t['id']?'rgba(124,58,237,.08)':'transparent' ?>;transition:background .15s;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
        <div style="font-size:13px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">
          #<?= $t['id'] ?> <?= clean($t['subject']) ?>
        </div>
        <span class="pill pill-<?= $t['status']==='open'?'pending':($t['status']==='replied'?'success':'gray') ?>" style="font-size:10px;padding:2px 7px;flex-shrink:0;">
          <?= ucfirst($t['status']) ?>
        </span>
      </div>
      <div style="font-size:12px;color:var(--text3);"><?= clean($t['username']??'—') ?> · <?= date('d M, H:i',strtotime($t['created_at'])) ?><?= !empty($t['category_label']) ? ' · ' . clean($t['category_label']) : '' ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Ticket Detail -->
<div style="background:var(--bg2);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;display:flex;flex-direction:column;">
<?php if ($ticketDetail): ?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
    <div>
      <div style="font-size:16px;font-weight:800;">#<?= $view ?>: <?= clean($ticketDetail['subject']) ?></div>
      <div style="font-size:13px;color:var(--text2);margin-top:3px;">
        From: <strong><?= clean($ticketDetail['username']) ?></strong> (<?= clean($ticketDetail['email']) ?>)
        <?php if (!empty($ticketDetail['category_label'])): ?>
        <br><span class="pill pill-gray" style="margin-top:4px;"><?= clean($ticketDetail['category_label']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($ticketDetail['status'] !== 'closed'): ?>
    <form method="POST">
    <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= $view ?>">
      <button type="submit" name="close_ticket" class="btn btn-danger btn-sm"
              onclick="return confirm('Close this ticket?')">
        <i class="fi fi-rr-lock"></i> Close
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div class="chat-wrap" style="flex:1;overflow-y:auto;max-height:400px;">
    <?php if (empty($replies)): ?>
    <p style="text-align:center;color:var(--text3);padding:20px;">No messages yet.</p>
    <?php endif; ?>
    <?php foreach ($replies as $r): ?>
    <div class="msg msg-<?= $r['sender']==='user'?'user':'admin' ?>">
      <?php if (!empty($r['message'])): ?><?= nl2br(clean($r['message'])) ?><?php endif; ?>
      <?php if (!empty($r['attachment'])): ?>
      <a href="<?= SITE_URL ?>/assets/uploads/support/<?= clean($r['attachment']) ?>" target="_blank">
        <img src="<?= SITE_URL ?>/assets/uploads/support/<?= clean($r['attachment']) ?>" style="max-width:220px;border-radius:8px;margin-top:6px;display:block;">
      </a>
      <?php endif; ?>
      <small><?= $r['sender']==='admin'?'Admin':clean($ticketDetail['username']) ?><?= !empty($r['is_auto']) ? ' (Auto-Reply)' : '' ?> · <?= date('d M, H:i',strtotime($r['created_at'])) ?></small>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($ticketDetail['status'] !== 'closed'): ?>
  <form method="POST" style="margin-top:14px;">
    <input type="hidden" name="ticket_id" value="<?= $view ?>">
    <div style="display:flex;gap:8px;">
      <textarea name="message" class="form-control" rows="2" placeholder="Type reply..." required style="resize:none;flex:1;"></textarea>
      <button type="submit" name="admin_reply" class="btn btn-primary">
        <i class="fi fi-rr-paper-plane"></i>
      </button>
    </div>
  </form>
  <?php else: ?>
  <div class="alert alert-info" style="margin-top:14px;margin-bottom:0;">
    <i class="fi fi-rr-lock"></i><span>Ticket is closed.</span>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:var(--text3);">
    <i class="fi fi-rr-headset" style="font-size:48px;margin-bottom:14px;opacity:.3;"></i>
    <p>Select a ticket to view and reply</p>
  </div>
<?php endif; ?>
</div>

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
