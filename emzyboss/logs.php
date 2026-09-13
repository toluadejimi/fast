<?php
// ============================================================
//  ErifyLogs – bigboss/logs.php  (Admin: Logs Management)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Logs Management';
require_once __DIR__ . '/includes/admin_header.php';

$msg = $err = '';

// ── POST HANDLER ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add/Edit Category
    if ($action === 'save_category') {
        $cid   = (int)($_POST['cat_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $slug  = trim($_POST['slug'] ?? preg_replace('/[^a-z0-9_-]/', '', strtolower($name)));
        $icon  = trim($_POST['icon'] ?? 'fi fi-sr-dolly-flatbed-alt');
        $desc  = trim($_POST['description'] ?? '');
        $sort  = (int)($_POST['sort_order'] ?? 0);
        $active= (int)($_POST['is_active'] ?? 1);
        if (!$name) { $err = 'Category name is required.'; }
        else {
            if ($cid) {
                $pdo->prepare("UPDATE log_categories SET name=?,slug=?,icon=?,description=?,sort_order=?,is_active=? WHERE id=?")
                    ->execute([$name,$slug,$icon,$desc,$sort,$active,$cid]);
                $msg = 'Category updated.';
            } else {
                $slug = $slug ?: preg_replace('/[^a-z0-9_-]/', '-', strtolower($name));
                $pdo->prepare("INSERT INTO log_categories (name,slug,icon,description,sort_order,is_active) VALUES (?,?,?,?,?,?)")
                    ->execute([$name,$slug,$icon,$desc,$sort,$active]);
                $msg = 'Category added.';
            }
        }
    }

    // ── Delete Category
    if ($action === 'del_category') {
        $cid = (int)($_POST['cat_id'] ?? 0);
        $pdo->prepare("DELETE FROM log_categories WHERE id=?")->execute([$cid]);
        $msg = 'Category deleted.';
    }

    // ── Add/Edit Product
    if ($action === 'save_product') {
        $pid    = (int)($_POST['prod_id'] ?? 0);
        $catId  = (int)($_POST['category_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $price  = (float)($_POST['price_usd'] ?? 0);
        $sort   = (int)($_POST['sort_order'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 1);
        if (!$name || !$catId || $price <= 0) {
            $err = 'Name, category and price (>0) are required.';
        } else {
            if ($pid) {
                $pdo->prepare("UPDATE log_products SET category_id=?,name=?,description=?,price_usd=?,sort_order=?,is_active=? WHERE id=?")
                    ->execute([$catId,$name,$desc,$price,$sort,$active,$pid]);
                $msg = 'Product updated.';
            } else {
                $pdo->prepare("INSERT INTO log_products (category_id,name,description,price_usd,sort_order,is_active) VALUES (?,?,?,?,?,?)")
                    ->execute([$catId,$name,$desc,$price,$sort,$active]);
                $msg = 'Product added.';
            }
        }
    }

    // ── Delete Product
    if ($action === 'del_product') {
        $pid = (int)($_POST['prod_id'] ?? 0);
        $pdo->prepare("DELETE FROM log_products WHERE id=?")->execute([$pid]);
        $msg = 'Product deleted.';
    }

    // ── Bulk Add Stock
    if ($action === 'bulk_stock') {
        $pid   = (int)($_POST['product_id'] ?? 0);
        $lines = array_filter(array_map('trim', explode("\n", $_POST['stock_text'] ?? '')));
        if (!$pid || empty($lines)) {
            $err = 'Select a product and enter at least one credential line.';
        } else {
            $count = 0;
            foreach ($lines as $line) {
                if ($line === '') continue;
                $pdo->prepare("INSERT INTO log_stock (product_id, credentials) VALUES (?,?)")
                    ->execute([$pid, $line]);
                $count++;
            }
            $msg = "{$count} stock item(s) added successfully.";
        }
    }

    // ── Delete a stock item
    if ($action === 'del_stock') {
        $sid = (int)($_POST['stock_id'] ?? 0);
        $pdo->prepare("DELETE FROM log_stock WHERE id=? AND is_sold=0")->execute([$sid]);
        $msg = 'Stock item deleted.';
    }

    // ── Clear all unsold stock for a product
    if ($action === 'clear_stock') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $pdo->prepare("DELETE FROM log_stock WHERE product_id=? AND is_sold=0")->execute([$pid]);
        $msg = 'Unsold stock cleared.';
    }
}

// ── Load data ─────────────────────────────────────────────────
$cats  = $pdo->query("SELECT * FROM log_categories ORDER BY sort_order,id")->fetchAll();
$prods = $pdo->query("SELECT p.*, c.name AS cat_name,
    (SELECT COUNT(*) FROM log_stock ls WHERE ls.product_id=p.id AND ls.is_sold=0) AS stock_unsold,
    (SELECT COUNT(*) FROM log_stock ls WHERE ls.product_id=p.id AND ls.is_sold=1) AS stock_sold
    FROM log_products p LEFT JOIN log_categories c ON c.id=p.category_id
    ORDER BY p.category_id, p.sort_order, p.id")->fetchAll();

// Stock view (filter)
$viewProd = (int)($_GET['stock_prod'] ?? 0);
$stockItems = [];
if ($viewProd) {
    $st = $pdo->prepare("SELECT ls.*, lo.reference AS order_ref
        FROM log_stock ls LEFT JOIN log_orders lo ON lo.id=ls.order_id
        WHERE ls.product_id=? ORDER BY ls.created_at DESC LIMIT 200");
    $st->execute([$viewProd]);
    $stockItems = $st->fetchAll();
}

// Log orders stats
$totalLogOrders   = (int)$pdo->query("SELECT COUNT(*) FROM log_orders")->fetchColumn();
$totalLogRevUsd   = (float)$pdo->query("SELECT COALESCE(SUM(total_usd),0) FROM log_orders WHERE status='completed'")->fetchColumn();
$totalLogProducts = (int)$pdo->query("SELECT COUNT(*) FROM log_products")->fetchColumn();
$totalLogStock    = (int)$pdo->query("SELECT COUNT(*) FROM log_stock WHERE is_sold=0")->fetchColumn();
?>

<?php if ($msg): ?>
<div class="alert alert-success" style="margin-bottom:16px;">
  <i class="fi fi-rr-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger" style="margin-bottom:16px;">
  <i class="fi fi-rr-cross-circle"></i> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
  <?php foreach ([
    ['fi-rr-shopping-bag','Total Orders',    $totalLogOrders,  ''],
    ['fi-rr-sack',        'Revenue (USD)',   '$'.number_format($totalLogRevUsd,2), 'success'],
    ['fi-rr-apps',        'Products',        $totalLogProducts,''],
    ['fi-rr-boxes',       'Stock Available', $totalLogStock,   'info'],
  ] as [$ico,$lbl,$val,$color]): ?>
  <div class="admin-stat">
    <div class="s-ico"><i class="fi <?= $ico ?>"></i></div>
    <div class="s-num" style="<?= $color ? 'color:var(--'.$color.')' : '' ?>"><?= $val ?></div>
    <div class="s-lbl"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- TABS -->
<div class="tabs-wrap" style="margin-bottom:20px;">
  <button class="tab-btn active" onclick="showTab('categories',this)">Categories</button>
  <button class="tab-btn" onclick="showTab('products',this)">Products</button>
  <button class="tab-btn" onclick="showTab('stock',this)">Add Stock</button>
  <button class="tab-btn" onclick="showTab('orders',this)">Orders</button>
</div>

<!-- ══ CATEGORIES TAB ══ -->
<div id="tab-categories">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<!-- Category Form -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;" id="catFormTitle">Add Category</h3>
  <form method="POST">
    <input type="hidden" name="action" value="save_category">
    <input type="hidden" name="cat_id" id="editCatId" value="0">
    <div class="form-group">
      <label class="form-label">Name *</label>
      <input type="text" name="name" id="catName" class="form-control" placeholder="e.g. Facebook" required>
    </div>
    <div class="form-group">
      <label class="form-label">Slug (auto)</label>
      <input type="text" name="slug" id="catSlug" class="form-control" placeholder="facebook">
    </div>
    <div class="form-group">
      <label class="form-label">Icon class (flaticon)</label>
      <input type="text" name="icon" id="catIcon" class="form-control" value="fi fi-rr-shopping-bag"
             placeholder="fi fi-brands-facebook">
      <small style="color:var(--text3);font-size:11px;">From cdn-uicons.flaticon.com e.g. fi fi-brands-facebook</small>
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <input type="text" name="description" id="catDesc" class="form-control" placeholder="Short note">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" id="catSort" class="form-control" value="0">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="is_active" id="catActive" class="form-control">
          <option value="1">Active</option><option value="0">Hidden</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px;">
      <button type="submit" class="btn btn-primary" style="flex:1;">Save Category</button>
      <button type="button" class="btn btn-ghost" onclick="resetCatForm()">Reset</button>
    </div>
  </form>
</div>

<!-- Category List -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;">
    All Categories (<?= count($cats) ?>)
  </h3>
  <?php if (empty($cats)): ?>
  <p style="color:var(--text3);font-size:13px;">No categories yet.</p>
  <?php else: ?>
  <?php foreach ($cats as $cat): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:10px 0;
       border-bottom:1px solid var(--border);">
    <div style="width:36px;height:36px;border-radius:8px;background:var(--primary-light);
         display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="<?= htmlspecialchars($cat['icon']) ?>" style="color:var(--primary);font-size:16px;"></i>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-size:13px;font-weight:700;"><?= clean($cat['name']) ?></div>
      <div style="font-size:11px;color:var(--text3);"><?= clean($cat['slug']) ?> ·
        <span class="pill pill-<?= $cat['is_active'] ? 'success' : 'gray' ?>"
              style="font-size:10px;padding:1px 7px;"><?= $cat['is_active'] ? 'Active' : 'Hidden' ?></span>
      </div>
    </div>
    <button onclick="editCat(<?= htmlspecialchars(json_encode($cat)) ?>)"
      class="btn btn-ghost btn-sm">Edit</button>
    <form method="POST" onsubmit="return confirm('Delete category?');" style="margin:0;">
      <input type="hidden" name="action" value="del_category">
      <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
      <button type="submit" class="btn btn-danger btn-sm">Del</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

</div>
</div><!-- /#tab-categories -->

<!-- ══ PRODUCTS TAB ══ -->
<div id="tab-products" style="display:none;">
<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;">

<!-- Product Form -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;" id="prodFormTitle">Add Product</h3>
  <form method="POST">
    <input type="hidden" name="action" value="save_product">
    <input type="hidden" name="prod_id" id="editProdId" value="0">
    <div class="form-group">
      <label class="form-label">Category *</label>
      <select name="category_id" id="prodCat" class="form-control" required>
        <option value="">— Choose —</option>
        <?php foreach ($cats as $cat): ?>
        <option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Product Name *</label>
      <input type="text" name="name" id="prodName" class="form-control"
             placeholder="e.g. Facebook Aged 2020 US" required>
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <input type="text" name="description" id="prodDesc" class="form-control"
             placeholder="e.g. Aged, US profile, with friends">
    </div>
    <div class="form-group">
      <label class="form-label">Price (USD) *</label>
      <input type="number" name="price_usd" id="prodPrice" class="form-control"
             step="0.0001" min="0.0001" placeholder="e.g. 2.50" required>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" id="prodSort" class="form-control" value="0">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="is_active" id="prodActive" class="form-control">
          <option value="1">Active</option><option value="0">Hidden</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px;">
      <button type="submit" class="btn btn-primary" style="flex:1;">Save Product</button>
      <button type="button" class="btn btn-ghost" onclick="resetProdForm()">Reset</button>
    </div>
  </form>
</div>

<!-- Product List -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);overflow-x:auto;">
  <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;">All Products (<?= count($prods) ?>)</h3>
  <table class="admin-table">
    <thead>
      <tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (empty($prods)): ?>
    <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text3);">No products yet.</td></tr>
    <?php else: ?>
    <?php foreach ($prods as $p): ?>
    <tr>
      <td>
        <strong><?= clean($p['name']) ?></strong>
        <?php if ($p['description']): ?>
        <br><small style="color:var(--text3);"><?= clean($p['description']) ?></small>
        <?php endif; ?>
      </td>
      <td><?= clean($p['cat_name'] ?? '—') ?></td>
      <td style="font-weight:800;color:var(--primary);">$<?= number_format((float)$p['price_usd'],4) ?></td>
      <td>
        <span style="color:<?= $p['stock_unsold']>0?'var(--success)':'var(--danger)' ?>;font-weight:700;">
          <?= $p['stock_unsold'] ?>
        </span>
        <small style="color:var(--text3);">/ <?= $p['stock_sold'] ?> sold</small>
      </td>
      <td><span class="pill pill-<?= $p['is_active']?'success':'gray' ?>"><?= $p['is_active']?'Active':'Hidden' ?></span></td>
      <td style="white-space:nowrap;">
        <button onclick="editProd(<?= htmlspecialchars(json_encode($p)) ?>)"
          class="btn btn-ghost btn-sm" style="margin-right:4px;">Edit</button>
        <a href="?stock_prod=<?= $p['id'] ?>#stockSection"
           class="btn btn-ghost btn-sm" style="margin-right:4px;">Stock</a>
        <form method="POST" onsubmit="return confirm('Delete product and all its stock?');" style="display:inline;">
          <input type="hidden" name="action" value="del_product">
          <input type="hidden" name="prod_id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div>
</div><!-- /#tab-products -->

<!-- ══ STOCK TAB ══ -->
<div id="tab-stock" style="display:none;">
<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:20px;" id="stockSection">

<!-- Bulk Add Stock -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <h3 style="font-size:15px;font-weight:800;margin-bottom:6px;">Bulk Add Stock</h3>
  <p style="font-size:12px;color:var(--text3);margin-bottom:16px;">
    Each line = 1 credential item. You can paste multi-line credentials per item using a separator,
    or one item per line.
  </p>
  <form method="POST">
    <input type="hidden" name="action" value="bulk_stock">
    <div class="form-group">
      <label class="form-label">Select Product *</label>
      <select name="product_id" class="form-control" required>
        <option value="">— Choose Product —</option>
        <?php foreach ($prods as $p): ?>
        <option value="<?= $p['id'] ?>" <?= ($viewProd===$p['id'])?'selected':'' ?>>
          <?= clean($p['name']) ?> (<?= $p['stock_unsold'] ?> in stock)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Credentials (one item per line) *</label>
      <textarea name="stock_text" class="form-control" rows="12"
        placeholder="user@example.com:Password123&#10;user2@example.com:Pass456&#10;..."
        style="resize:vertical;font-family:monospace;font-size:12px;" required></textarea>
      <small style="color:var(--text3);font-size:11px;">
        Each line is stored as one account. Lines are counted: blank lines ignored.
      </small>
    </div>
    <button type="submit" class="btn btn-primary btn-block">
      <i class="fi fi-rr-plus"></i> Add Stock
    </button>
  </form>
</div>

<!-- Stock Viewer -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:15px;font-weight:800;">
      <?= $viewProd ? 'Stock Items' : 'Select a product above to view stock' ?>
    </h3>
    <?php if ($viewProd && $stockItems): ?>
    <form method="POST" onsubmit="return confirm('Clear all unsold stock for this product?');">
      <input type="hidden" name="action" value="clear_stock">
      <input type="hidden" name="product_id" value="<?= $viewProd ?>">
      <button type="submit" class="btn btn-danger btn-sm">Clear Unsold</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($viewProd): ?>
    <?php if (empty($stockItems)): ?>
    <div class="empty" style="padding:30px 0;">
      <i class="fi fi-rr-boxes" style="display:block;font-size:36px;margin-bottom:10px;opacity:.3;"></i>
      <p>No stock for this product.</p>
    </div>
    <?php else: ?>
    <div style="max-height:500px;overflow-y:auto;">
    <?php foreach ($stockItems as $si): ?>
    <div style="background:var(--bg3);border-radius:8px;padding:10px 12px;margin-bottom:8px;
         display:flex;align-items:flex-start;gap:10px;">
      <div style="flex:1;min-width:0;">
        <pre style="font-size:11px;color:var(--text);white-space:pre-wrap;word-break:break-all;
             margin:0;font-family:monospace;line-height:1.5;"><?= htmlspecialchars(substr($si['credentials'],0,120)) . (strlen($si['credentials'])>120?'…':'') ?></pre>
        <div style="font-size:10px;color:var(--text3);margin-top:4px;">
          <?php if ($si['is_sold']): ?>
          <span style="color:var(--success);">✓ Sold</span>
          · ref: <?= clean($si['order_ref'] ?? '—') ?>
          · <?= date('d M H:i', strtotime($si['sold_at'])) ?>
          <?php else: ?>
          <span style="color:var(--warning);">● Available</span>
          · added <?= date('d M H:i', strtotime($si['created_at'])) ?>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!$si['is_sold']): ?>
      <form method="POST" onsubmit="return confirm('Delete this stock item?');" style="flex-shrink:0;">
        <input type="hidden" name="action" value="del_stock">
        <input type="hidden" name="stock_id" value="<?= $si['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm"
          style="padding:4px 10px;font-size:11px;">Del</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php else: ?>
  <p style="color:var(--text3);font-size:13px;">
    Click "Stock" on any product to view its items here,
    or select a product in the form to add stock.
  </p>
  <?php endif; ?>
</div>

</div>
</div><!-- /#tab-stock -->

<!-- ══ ORDERS TAB ══ -->
<div id="tab-orders" style="display:none;">
<?php
$logOrders = $pdo->query("SELECT lo.*, u.username FROM log_orders lo
    LEFT JOIN users u ON u.id=lo.user_id
    ORDER BY lo.created_at DESC LIMIT 100")->fetchAll();
?>
<div style="background:var(--bg2);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);overflow-x:auto;">
  <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;">Recent Log Orders (<?= $totalLogOrders ?>)</h3>
  <table class="admin-table">
    <thead>
      <tr><th>Order</th><th>User</th><th>Product</th><th>Qty</th><th>Amount</th><th>Currency</th><th>Date</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php if (empty($logOrders)): ?>
    <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--text3);">No orders yet.</td></tr>
    <?php else: ?>
    <?php foreach ($logOrders as $lo): ?>
    <tr>
      <td style="font-weight:700;">#<?= $lo['id'] ?><br>
        <small style="color:var(--text3);font-size:10px;"><?= clean($lo['reference']) ?></small></td>
      <td><?= clean($lo['username'] ?? '—') ?></td>
      <td><?= clean($lo['product_name']) ?></td>
      <td style="text-align:center;font-weight:700;"><?= $lo['quantity'] ?></td>
      <td style="font-weight:800;color:var(--primary);">$<?= number_format((float)$lo['total_usd'],4) ?></td>
      <td><span class="pill pill-info"><?= $lo['currency_paid'] ?></span></td>
      <td style="font-size:12px;color:var(--text2);"><?= date('d M Y H:i', strtotime($lo['created_at'])) ?></td>
      <td><span class="pill pill-<?= $lo['status']==='completed'?'success':'danger' ?>"><?= ucfirst($lo['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</div><!-- /#tab-orders -->

<script>
function showTab(name, btn) {
    ['categories','products','stock','orders'].forEach(t => {
        document.getElementById('tab-'+t).style.display = t===name?'block':'none';
    });
    document.querySelectorAll('.tabs-wrap .tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
}

// Auto-activate stock tab if stock_prod is in URL
<?php if ($viewProd): ?>
document.addEventListener('DOMContentLoaded', ()=>{
    showTab('stock', document.querySelectorAll('.tabs-wrap .tab-btn')[2]);
});
<?php endif; ?>

function editCat(cat) {
    document.getElementById('catFormTitle').textContent = 'Edit Category';
    document.getElementById('editCatId').value   = cat.id;
    document.getElementById('catName').value     = cat.name;
    document.getElementById('catSlug').value     = cat.slug;
    document.getElementById('catIcon').value     = cat.icon;
    document.getElementById('catDesc').value     = cat.description;
    document.getElementById('catSort').value     = cat.sort_order;
    document.getElementById('catActive').value   = cat.is_active;
    document.getElementById('catName').scrollIntoView({behavior:'smooth',block:'center'});
}
function resetCatForm() {
    document.getElementById('catFormTitle').textContent = 'Add Category';
    ['editCatId','catName','catSlug','catDesc'].forEach(id => document.getElementById(id).value='');
    document.getElementById('catIcon').value   = 'fi fi-rr-shopping-bag';
    document.getElementById('catSort').value   = '0';
    document.getElementById('catActive').value = '1';
}

function editProd(prod) {
    showTab('products', document.querySelectorAll('.tabs-wrap .tab-btn')[1]);
    document.getElementById('prodFormTitle').textContent = 'Edit Product';
    document.getElementById('editProdId').value   = prod.id;
    document.getElementById('prodCat').value      = prod.category_id;
    document.getElementById('prodName').value     = prod.name;
    document.getElementById('prodDesc').value     = prod.description;
    document.getElementById('prodPrice').value    = prod.price_usd;
    document.getElementById('prodSort').value     = prod.sort_order;
    document.getElementById('prodActive').value   = prod.is_active;
    document.getElementById('prodName').scrollIntoView({behavior:'smooth',block:'center'});
}
function resetProdForm() {
    document.getElementById('prodFormTitle').textContent = 'Add Product';
    ['editProdId','prodName','prodDesc','prodPrice'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('prodCat').value    = '';
    document.getElementById('prodSort').value   = '0';
    document.getElementById('prodActive').value = '1';
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
