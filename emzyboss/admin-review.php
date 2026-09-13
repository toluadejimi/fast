<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Reviews';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['add_review'])) {
        $name    = trim($_POST['display_name'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $rating  = max(1, min(5, (int) ($_POST['rating'] ?? 5)));

        if ($name === '' || $message === '') {
            $error = 'Name and message are both required.';
        } else {
            $pdo->prepare("INSERT INTO reviews (display_name, message, rating) VALUES (?,?,?)")
                ->execute([$name, $message, $rating]);
            $success = 'Review added.';
        }
    } elseif (isset($_POST['delete_review'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
        $success = 'Review deleted.';
    }
}

$reviews = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Add Review</h3>
  <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Display Name</label>
      <input type="text" name="display_name" class="form-control" placeholder="e.g. Chidinma O." required>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Rating</label>
      <select name="rating" class="form-control">
        <option value="5" selected>★★★★★ (5)</option>
        <option value="4">★★★★☆ (4)</option>
        <option value="3">★★★☆☆ (3)</option>
        <option value="2">★★☆☆☆ (2)</option>
        <option value="1">★☆☆☆☆ (1)</option>
      </select>
    </div>
    <div class="form-group" style="grid-column:1/-1;margin-bottom:0;">
      <label class="form-label">Message</label>
      <textarea name="message" class="form-control" rows="3" placeholder="What did they say?" required></textarea>
    </div>
    <div style="grid-column:1/-1;">
      <button type="submit" name="add_review" value="1" class="btn btn-primary"><i class="fi fi-rr-plus"></i> Add Review</button>
    </div>
  </form>
</div>

<h3>All Reviews (<?= count($reviews) ?>)</h3>
<?php if (empty($reviews)): ?>
<div class="empty"><p>No reviews yet — add one above.</p></div>
<?php endif; ?>

<?php foreach ($reviews as $r): ?>
<div class="glass" style="padding:16px;border-radius:var(--radius);margin-bottom:12px;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
    <div>
      <strong><?= clean($r['display_name']) ?></strong>
      <div style="color:#f59e0b;font-size:14px;margin:2px 0 6px;"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
      <div style="font-size:13px;color:var(--text2);"><?= nl2br(clean($r['message'])) ?></div>
      <div style="font-size:11px;color:var(--text3);margin-top:8px;"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
    </div>
    <form method="POST" onsubmit="return confirm('Delete this review?')">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button type="submit" name="delete_review" value="1" class="btn btn-danger btn-sm"><i class="fi fi-rr-trash"></i></button>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
