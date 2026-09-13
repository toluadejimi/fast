<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Notifications – ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';

// Mark all read
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$user['id']]); $notifList = $notifs->fetchAll();
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:14px;">Notifications</h2>

<?php if (empty($notifList)): ?>
<div class="empty">
  <i class="fi fi-rr-bell" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No notifications yet.</p>
</div>
<?php else: ?>
<?php foreach ($notifList as $n): ?>
<a href="<?= clean($n['link_url']) ?>" style="text-decoration:none;">
  <div class="order-item" style="border-left:3px solid var(--primary);margin-bottom:10px;">
    <div class="d-flex gap-2 align-center">
      <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-light);
                  display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fi fi-rr-bell" style="color:var(--primary);font-size:15px;"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:14px;font-weight:700;color:var(--text);"><?= clean($n['title']) ?></div>
        <div style="font-size:13px;color:var(--text2);"><?= clean($n['message']) ?></div>
        <div class="order-meta" style="margin-top:3px;"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
      </div>
    </div>
  </div>
</a>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
