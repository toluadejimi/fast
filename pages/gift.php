<?php
// ============================================================
//  DonnieSMS - pages/gift.php
//  Gift Shop e-commerce — order physical gifts using wallet balance.
//  No PIN required for checkout (per site owner's instruction).
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Gift Shop';
require_once ROOT_PATH . '/includes/header.php';

$error = '';

// ── Handle order placement ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_gift'])) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {

        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity  = max(1, (int) ($_POST['quantity'] ?? 1));

        $senderName  = trim($_POST['sender_name'] ?? '');
        $receiverName = trim($_POST['receiver_name'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $state       = trim($_POST['state'] ?? '');
        $zip         = trim($_POST['zip_code'] ?? '');
        $phone       = trim($_POST['phone_number'] ?? '');
        $apartment   = trim($_POST['apartment_number'] ?? '');

        if ($senderName === '' || $receiverName === '' || $address === '' || $city === '' || $state === '' || $zip === '' || $phone === '') {
            $error = 'Please fill in all required receiver details (apartment number is optional).';
        } else {

            // Re-fetch the product server-side — never trust a submitted price
            $stmt = $pdo->prepare("SELECT * FROM gift_products WHERE id = ? AND status = 'active'");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                $error = 'This product is no longer available.';
            } elseif ($product['stock'] < $quantity) {
                $error = 'Only ' . (int) $product['stock'] . ' left in stock.';
            } else {

                $totalPrice = round((float) $product['price'] * $quantity, 4);

                if ((float) $user['balance'] < $totalPrice) {
                    $error = 'Insufficient balance. Please fund your wallet.';
                } else {

                    $debited = wallet_debit($user['id'], $totalPrice, 'Gift Order – ' . $product['name'], gen_ref('GIFT'));

                    if (!$debited) {
                        $error = 'Payment failed, please try again.';
                    } else {
                        track_spend($user['id'], $totalPrice);

                        $pdo->prepare("UPDATE gift_products SET stock = stock - ? WHERE id = ? AND stock >= ?")
                            ->execute([$quantity, $productId, $quantity]);

                        $pdo->prepare(
                            "INSERT INTO gift_orders
                                (user_id, product_id, product_name, unit_price, quantity, total_price,
                                 sender_name, receiver_name, address, city, state, zip_code, phone_number, apartment_number, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
                        )->execute([
                            $user['id'], $productId, $product['name'], $product['price'], $quantity, $totalPrice,
                            $senderName, $receiverName, $address, $city, $state, $zip, $phone, $apartment ?: null,
                        ]);

                        $newOrderId = (int) $pdo->lastInsertId();

                        $orderForNotify = [
                            'id' => $newOrderId, 'user_id' => $user['id'],
                            'product_name' => $product['name'], 'receiver_name' => $receiverName,
                            'admin_note' => null,
                        ];
                        notify_gift_status($orderForNotify, 'Pending', $user['email'], $user['username']);

                        redirect(SITE_URL . '/pages/gift-orders.php');
                    }
                }
            }
        }
    }
}

$products = $pdo->query("SELECT * FROM gift_products WHERE status = 'active' AND stock > 0 ORDER BY created_at DESC")->fetchAll();
$categories = [];
foreach ($products as $p) {
    $categories[$p['category']] = true;
}
$categories = array_keys($categories);
sort($categories);
?>

<!-- Hero banner -->
<div style="background:linear-gradient(160deg,var(--primary) 0%,var(--primary-dark) 100%);
            border-radius:var(--radius);padding:32px 20px;text-align:center;color:#fff;
            margin-bottom:20px;position:relative;overflow:hidden;">
  <div style="font-size:40px;margin-bottom:8px;">🎁</div>
  <h2 style="margin:0 0 6px;color:#fff;font-size:22px;">Send a Gift, Make a Day</h2>
  <p style="margin:0;opacity:.9;font-size:13px;">Order real gifts for friends &amp; family — paid straight from your wallet balance.</p>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>

<?php if (empty($products)): ?>
<div class="empty">
  <i class="fi fi-rr-gift" style="display:block;font-size:48px;margin-bottom:14px;opacity:.3;"></i>
  <p>No gifts available right now. Check back soon!</p>
</div>
<?php else: ?>

<!-- Live search -->
<div class="form-group">
  <div class="input-wrap">
    <i class="fi fi-rr-search"></i>
    <input type="text" id="giftSearch" class="form-control" placeholder="Search for a gift..." oninput="filterGifts()">
  </div>
</div>

<!-- Category pills -->
<div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:8px;margin-bottom:12px;-webkit-overflow-scrolling:touch;">
  <button type="button" class="cat-pill active" data-cat="" onclick="selectCategory(this)">All</button>
  <?php foreach ($categories as $cat): ?>
  <button type="button" class="cat-pill" data-cat="<?= clean($cat) ?>" onclick="selectCategory(this)"><?= clean($cat) ?></button>
  <?php endforeach; ?>
</div>

<div id="noResults" class="empty" style="display:none;">
  <i class="fi fi-rr-search" style="display:block;font-size:40px;margin-bottom:10px;opacity:.3;"></i>
  <p>No gifts match your search.</p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" id="giftGrid">
  <?php foreach ($products as $p): ?>
  <div class="glass gift-card" style="padding:12px;border-radius:var(--radius);" data-name="<?= clean(strtolower($p['name'])) ?>" data-category="<?= clean($p['category']) ?>">
    <?php if ($p['image']): ?>
    <img src="<?= SITE_URL ?>/assets/uploads/gifts/<?= clean($p['image']) ?>" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px;">
    <?php else: ?>
    <div style="width:100%;height:110px;background:var(--bg2);border-radius:8px;margin-bottom:8px;display:flex;align-items:center;justify-content:center;color:var(--text3);">
      <i class="fi fi-rr-gift" style="font-size:28px;"></i>
    </div>
    <?php endif; ?>
    <div style="font-size:10px;color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px;"><?= clean($p['category']) ?></div>
    <div style="font-size:13px;font-weight:700;line-height:1.3;min-height:34px;"><?= clean($p['name']) ?></div>
    <div style="font-size:15px;font-weight:900;color:var(--primary);margin:4px 0 8px;">$<?= number_format($p['price'], 2) ?></div>
    <button type="button" class="btn btn-primary btn-sm btn-block"
            onclick="openGiftModal(<?= (int)$p['id'] ?>, '<?= clean(addslashes($p['name'])) ?>', <?= (float)$p['price'] ?>, <?= (int)$p['stock'] ?>)">
      Order Gift
    </button>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.cat-pill{
  flex-shrink:0;padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);
  background:var(--card-bg);color:var(--text2);font-size:12px;font-weight:600;
  white-space:nowrap;cursor:pointer;transition:all .15s;
}
.cat-pill.active{background:var(--primary);border-color:var(--primary);color:#fff;}
</style>

<script>
let activeCategory = '';

function selectCategory(btn) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    activeCategory = btn.dataset.cat;
    filterGifts();
}

function filterGifts() {
    const query = document.getElementById('giftSearch').value.trim().toLowerCase();
    const cards = document.querySelectorAll('.gift-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const matchesSearch = card.dataset.name.includes(query);
        const matchesCategory = !activeCategory || card.dataset.category === activeCategory;
        const show = matchesSearch && matchesCategory;
        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
    document.getElementById('giftGrid').style.display = visibleCount === 0 ? 'none' : 'grid';
}
</script>

<p style="text-align:center;margin-top:18px;"><a href="<?= SITE_URL ?>/pages/gift-orders.php">View My Gift Orders →</a></p>

<!-- Order form modal -->
<div class="modal-overlay" id="gift-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <h3 class="modal-title">Order Gift</h3>

    <div style="font-size:15px;font-weight:700;" id="gift-modal-name">—</div>
    <div style="font-size:20px;font-weight:900;color:var(--primary);margin-bottom:14px;" id="gift-modal-total">—</div>

    <form method="POST" id="giftForm">
      <?= csrf_field() ?>
      <input type="hidden" name="order_gift" value="1">
      <input type="hidden" name="product_id" id="gift-product-id">

      <div class="form-group">
        <label class="form-label">Quantity</label>
        <input type="number" name="quantity" id="gift-quantity" class="form-control" value="1" min="1" required>
      </div>

      <div style="background:var(--bg2);padding:12px;border-radius:8px;font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Please make sure your address is correct and provided in this form — address not provided (or incorrect) will be delayed till corrected.
      </div>

      <div class="form-group"><label class="form-label">🪪 Sender's Name</label><input type="text" name="sender_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">🪪 Receiver's Name</label><input type="text" name="receiver_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">📍 Address</label><input type="text" name="address" class="form-control" required></div>
      <div class="form-group"><label class="form-label">🌇 City</label><input type="text" name="city" class="form-control" required></div>
      <div class="form-group"><label class="form-label">🗺️ State</label><input type="text" name="state" class="form-control" required></div>
      <div class="form-group"><label class="form-label">📫 Zip Code</label><input type="text" name="zip_code" class="form-control" required></div>
      <div class="form-group"><label class="form-label">📞 Phone Number</label><input type="tel" name="phone_number" class="form-control" required></div>
      <div class="form-group"><label class="form-label">🏡 Apartment Number <span style="color:var(--text3);font-weight:400;">(optional)</span></label><input type="text" name="apartment_number" class="form-control"></div>

      <button type="submit" class="btn btn-primary btn-block mt-1">Place Order</button>
      <button type="button" onclick="closeModal('gift-modal')" class="btn btn-ghost btn-block mt-1">Cancel</button>
    </form>
  </div>
</div>

<script>
let giftUnitPrice = 0;

function openGiftModal(id, name, price, stock) {
    giftUnitPrice = price;
    document.getElementById('gift-product-id').value = id;
    document.getElementById('gift-modal-name').textContent = name;
    document.getElementById('gift-quantity').max = stock;
    document.getElementById('gift-quantity').value = 1;
    updateGiftTotal();
    openModal('gift-modal');
}

function updateGiftTotal() {
    const qty = parseInt(document.getElementById('gift-quantity').value) || 1;
    document.getElementById('gift-modal-total').textContent = '$' + (giftUnitPrice * qty).toFixed(2);
}
document.getElementById('gift-quantity').addEventListener('input', updateGiftTotal);
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
