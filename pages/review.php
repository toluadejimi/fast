<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Reviews';
require_once ROOT_PATH . '/includes/header.php';

$reviews = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();

$avatarColors = ['#7C3AED', '#2563EB', '#059669', '#DC2626', '#D97706', '#DB2777'];
?>

<h2 style="font-size:20px;font-weight:900;margin-bottom:4px;">⭐ What Our Users Say</h2>
<p class="text-muted text-sm" style="margin-bottom:16px;">Real feedback from real users.</p>

<?php if (empty($reviews)): ?>
<div class="empty">
  <i class="fi fi-rr-comment-heart" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No reviews yet.</p>
</div>
<?php else: ?>
<?php foreach ($reviews as $i => $r):
    $color = $avatarColors[$i % count($avatarColors)];
    $initials = strtoupper(substr($r['display_name'], 0, 2));
?>
<div class="glass" style="padding:16px;border-radius:var(--radius);margin-bottom:12px;">
  <div style="display:flex;gap:12px;">
    <div style="width:40px;height:40px;border-radius:50%;background:<?= $color ?>;color:#fff;
                display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0;">
      <?= clean($initials) ?>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:14px;"><?= clean($r['display_name']) ?></div>
      <div style="color:#f59e0b;font-size:13px;margin:2px 0 6px;"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
      <div style="font-size:13px;color:var(--text2);line-height:1.5;"><?= nl2br(clean($r['message'])) ?></div>
      <div style="font-size:11px;color:var(--text3);margin-top:8px;"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
