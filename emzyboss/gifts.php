<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Gift Products';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';
$uploadDir = ROOT_PATH . '/assets/uploads/gifts/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    }

    // Add or update a product
    elseif (isset($_POST['save_product'])) {
        $id     = (int) ($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '') ?: 'General';
        $desc   = trim($_POST['description'] ?? '');
        $price  = (float) ($_POST['price'] ?? 0);
        $stock  = (int) ($_POST['stock'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($name === '' || $price <= 0) {
            $error = 'Name and a valid price are required.';
        } else {
            $imageFilename = null;

            if (!empty($_FILES['image']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'webp'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Only PNG, JPG, WebP allowed for product images.';
                } elseif ($_FILES['image']['size'] > 3 * 1024 * 1024) {
                    $error = 'Image must be under 3MB.';
                } else {
                    $imageFilename = 'gift_' . time() . '_' . random_int(100, 999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageFilename)) {
                        $error = 'Image upload failed. Check folder permissions on assets/uploads/gifts/';
                        $imageFilename = null;
                    }
                }
            }

            if ($error === '') {
                if ($id > 0) {
                    if ($imageFilename) {
                        $old = $pdo->prepare("SELECT image FROM gift_products WHERE id=?");
                        $old->execute([$id]);
                        $oldImg = $old->fetchColumn();
                        if ($oldImg && file_exists($uploadDir . $oldImg)) @unlink($uploadDir . $oldImg);

                        $pdo->prepare("UPDATE gift_products SET name=?,category=?,description=?,price=?,stock=?,status=?,image=? WHERE id=?")
                            ->execute([$name, $category, $desc, $price, $stock, $status, $imageFilename, $id]);
                    } else {
                        $pdo->prepare("UPDATE gift_products SET name=?,category=?,description=?,price=?,stock=?,status=? WHERE id=?")
                            ->execute([$name, $category, $desc, $price, $stock, $status, $id]);
                    }
                    $success = 'Product updated.';
                } else {
                    $pdo->prepare("INSERT INTO gift_products (name,category,description,price,stock,status,image) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$name, $category, $desc, $price, $stock, $status, $imageFilename]);
                    $success = 'Product added.';
                }
            }
        }
    }

    // Delete a product
    elseif (isset($_POST['delete_product'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $img = $pdo->prepare("SELECT image FROM gift_products WHERE id=?");
        $img->execute([$id]);
        $imgFile = $img->fetchColumn();
        if ($imgFile && file_exists($uploadDir . $imgFile)) @unlink($uploadDir . $imgFile);
        $pdo->prepare("DELETE FROM gift_products WHERE id=?")->execute([$id]);
        $success = 'Product deleted.';
    }
}

$products = $pdo->query("SELECT * FROM gift_products ORDER BY created_at DESC")->fetchAll();
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<div class="glass" style="padding:20px;border-radius:var(--radius);margin-bottom:20px;">
  <h3 style="margin-top:0;">Add New Gift Product</h3>
  <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="0">

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Product Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Category</label>
      <input type="text" name="category" class="form-control" placeholder="e.g. Flowers, Electronics, Hampers" value="General">
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Price (USD)</label>
      <input type="number" name="price" class="form-control" step="0.01" min="0.01" required>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Stock</label>
      <input type="number" name="stock" class="form-control" min="0" value="100" required>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>
    <div class="form-group" style="grid-column:1/-1;margin-bottom:0;">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3"></textarea>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Product Image</label>
      <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp">
    </div>
    <div style="grid-column:1/-1;">
      <button type="submit" name="save_product" value="1" class="btn btn-primary"><i class="fi fi-rr-plus"></i> Add Product</button>
    </div>
  </form>
</div>

<h3>All Products</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
  <?php foreach ($products as $p): ?>
  <div class="glass" style="padding:14px;border-radius:var(--radius);">
    <?php if ($p['image']): ?>
    <img src="<?= SITE_URL ?>/assets/uploads/gifts/<?= clean($p['image']) ?>" style="width:100%;height:140px;object-fit:cover;border-radius:8px;margin-bottom:10px;">
    <?php else: ?>
    <div style="width:100%;height:140px;background:var(--bg2);border-radius:8px;margin-bottom:10px;display:flex;align-items:center;justify-content:center;color:var(--text3);">
      <i class="fi fi-rr-gift" style="font-size:32px;"></i>
    </div>
    <?php endif; ?>
    <div style="font-weight:800;"><?= clean($p['name']) ?></div>
    <div style="font-size:13px;color:var(--text3);margin:4px 0;">$<?= number_format($p['price'], 2) ?> &middot; Stock: <?= (int)$p['stock'] ?> &middot; <?= clean($p['category']) ?></div>
    <span class="pill pill-<?= $p['status']==='active'?'success':'gray' ?>"><?= ucfirst($p['status']) ?></span>

    <details style="margin-top:10px;">
      <summary style="cursor:pointer;font-size:13px;color:var(--primary);">Edit</summary>
      <form method="POST" enctype="multipart/form-data" style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <input type="text" name="name" class="form-control" value="<?= clean($p['name']) ?>" required>
        <input type="text" name="category" class="form-control" placeholder="Category" value="<?= clean($p['category']) ?>">
        <textarea name="description" class="form-control" rows="2"><?= clean($p['description']) ?></textarea>
        <input type="number" name="price" class="form-control" step="0.01" value="<?= clean($p['price']) ?>" required>
        <input type="number" name="stock" class="form-control" value="<?= (int)$p['stock'] ?>" required>
        <select name="status" class="form-control">
          <option value="active" <?= $p['status']==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $p['status']==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp">
        <button type="submit" name="save_product" value="1" class="btn btn-primary btn-sm">Save Changes</button>
      </form>
      <form method="POST" style="margin-top:6px;" onsubmit="return confirm('Delete this product permanently?')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button type="submit" name="delete_product" value="1" class="btn btn-danger btn-sm">Delete</button>
      </form>
    </details>
  </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
