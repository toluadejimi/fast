<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Support Categories';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    }

    elseif (isset($_POST['save_limit'])) {
        $limit = max(0, (int) ($_POST['daily_limit'] ?? 2));
        set_setting('support_auto_reply_daily_limit', (string) $limit);
        $success = 'Daily auto-reply limit updated.';
    }

    elseif (isset($_POST['save_category'])) {
        $id        = (int) ($_POST['id'] ?? 0);
        $label     = trim($_POST['label'] ?? '');
        $autoReply = trim($_POST['auto_reply'] ?? '');
        $isActive  = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($label === '' || $autoReply === '') {
            $error = 'Label and auto-reply message are both required.';
        } elseif ($id > 0) {
            $pdo->prepare("UPDATE support_categories SET label=?, auto_reply=?, is_active=?, sort_order=? WHERE id=?")
                ->execute([$label, $autoReply, $isActive, $sortOrder, $id]);
            $success = 'Category updated.';
        } else {
            $pdo->prepare("INSERT INTO support_categories (label, auto_reply, is_active, sort_order) VALUES (?,?,?,?)")
                ->execute([$label, $autoReply, $isActive, $sortOrder]);
            $success = 'Category added.';
        }
    }

    elseif (isset($_POST['delete_category'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE support_tickets SET category_id = NULL WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM support_categories WHERE id=?")->execute([$id]);
        $success = 'Category deleted.';
    }
}

$dailyLimit  = get_setting('support_auto_reply_daily_limit', '2');
$categories  = $pdo->query("SELECT * FROM support_categories ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div class="glass" style="padding:18px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Auto-Reply Daily Limit</h3>
  <p style="font-size:13px;color:var(--text3);">The maximum number of instant auto-replies a single user can receive per day. Once they hit this, new tickets still go to admin normally — they just won't get another canned reply until tomorrow.</p>
  <form method="POST" style="display:flex;gap:10px;align-items:center;">
    <?= csrf_field() ?>
    <input type="number" name="daily_limit" class="form-control" style="max-width:120px;" value="<?= (int)$dailyLimit ?>" min="0">
    <button type="submit" name="save_limit" value="1" class="btn btn-primary btn-sm">Save</button>
  </form>
</div>

<div class="glass" style="padding:18px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Add New Category</h3>
  <form method="POST" style="display:flex;flex-direction:column;gap:10px;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="0">
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Category Label</label>
      <input type="text" name="label" class="form-control" placeholder="e.g. Wallet Funding Issues" required>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Auto-Reply Message</label>
      <textarea name="auto_reply" class="form-control" rows="3" placeholder="Sent instantly when a user picks this option..." required></textarea>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" style="width:100px;" value="<?= count($categories) ?>">
      </div>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-top:20px;">
        <input type="checkbox" name="is_active" value="1" checked> Active
      </label>
    </div>
    <button type="submit" name="save_category" value="1" class="btn btn-primary"><i class="fi fi-rr-plus"></i> Add Category</button>
  </form>
</div>

<h3>All Categories</h3>
<?php if (empty($categories)): ?>
<div class="empty"><p>No categories yet — add one above.</p></div>
<?php endif; ?>

<?php foreach ($categories as $c): ?>
<div class="glass" style="padding:16px;border-radius:var(--radius);margin-bottom:12px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
    <strong><?= clean($c['label']) ?></strong>
    <span class="pill pill-<?= $c['is_active'] ? 'success' : 'gray' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span>
  </div>
  <details>
    <summary style="cursor:pointer;font-size:13px;color:var(--primary);">Edit</summary>
    <form method="POST" style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <input type="text" name="label" class="form-control" value="<?= clean($c['label']) ?>" required>
      <textarea name="auto_reply" class="form-control" rows="3" required><?= clean($c['auto_reply']) ?></textarea>
      <div style="display:flex;gap:10px;align-items:center;">
        <input type="number" name="sort_order" class="form-control" style="width:100px;" value="<?= (int)$c['sort_order'] ?>">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
          <input type="checkbox" name="is_active" value="1" <?= $c['is_active']?'checked':'' ?>> Active
        </label>
      </div>
      <button type="submit" name="save_category" value="1" class="btn btn-primary btn-sm">Save Changes</button>
    </form>
    <form method="POST" style="margin-top:6px;" onsubmit="return confirm('Delete this category? Existing tickets will keep their history but lose the category tag.')">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button type="submit" name="delete_category" value="1" class="btn btn-danger btn-sm">Delete</button>
    </form>
  </details>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
