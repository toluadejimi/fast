<?php
// ============================================================
//  ErifyLogs – pages/logs.php  (FIXED: PIN + API path)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_login();
$user = current_user();
$pageTitle = 'Buy Logs';

// Currency toggle – saved to DB
if (isset($_GET['currency']) && in_array($_GET['currency'], ['USD','NGN'])) {
    $pdo->prepare("UPDATE users SET currency=? WHERE id=?")->execute([$_GET['currency'], $user['id']]);
    header('Location: /pages/logs.php'); exit;
}
$cur  = $user['currency'] ?? 'USD';
$rate = get_usd_to_ngn_rate();

// Load categories + products with stock count
$cats = $pdo->query(
    "SELECT * FROM log_categories WHERE is_active=1 ORDER BY sort_order,id"
)->fetchAll();

$allProds = [];
foreach ($cats as $cat) {
    $s = $pdo->prepare(
        "SELECT p.*,
         (SELECT COUNT(*) FROM log_stock ls WHERE ls.product_id=p.id AND ls.is_sold=0) AS stock_count
         FROM log_products p
         WHERE p.category_id=? AND p.is_active=1
         ORDER BY p.sort_order, p.id"
    );
    $s->execute([$cat['id']]);
    $allProds[$cat['id']] = $s->fetchAll();
}

function disp(float $usd, string $cur, float $rate): string {
    return $cur === 'NGN'
        ? '₦' . number_format($usd * $rate, 2)
        : '$' . number_format($usd, 2);
}

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Wallet + Currency Bar -->
<!--<div style="display:flex;align-items:center;justify-content:space-between;-->
<!--     background:var(--card-bg);border:1.5px solid var(--card-border);-->
<!--     border-radius:var(--radius-sm);padding:11px 14px;margin-bottom:14px;-->
<!--     backdrop-filter:var(--glass-blur);box-shadow:var(--shadow);">-->
<!--  <div>-->
<!--    <div style="font-size:11px;color:var(--text3);">Wallet Balance</div>-->
<!--    <div style="font-size:19px;font-weight:900;color:var(--primary);">-->
<!--      <?= disp((float)$user['balance'], $cur, $rate) ?>-->
<!--    </div>-->
<!--  </div>-->
<!--  <div style="display:flex;align-items:center;gap:8px;">-->
    <!-- USD / NGN toggle -->
<!--    <div style="display:flex;background:var(--bg3);border-radius:99px;padding:3px;gap:2px;">-->
<!--      <a href="?currency=USD"-->
<!--         style="padding:5px 13px;border-radius:99px;font-size:12px;font-weight:800;-->
<!--                text-decoration:none;transition:all .2s;-->
<!--                background:<?= $cur==='USD'?'var(--primary)':'transparent' ?>;-->
<!--                color:<?= $cur==='USD'?'#fff':'var(--text2)' ?>;">-->
<!--        $ USD-->
<!--      </a>-->
<!--      <a href="?currency=NGN"-->
<!--         style="padding:5px 13px;border-radius:99px;font-size:12px;font-weight:800;-->
<!--                text-decoration:none;transition:all .2s;-->
<!--                background:<?= $cur==='NGN'?'var(--primary)':'transparent' ?>;-->
<!--                color:<?= $cur==='NGN'?'#fff':'var(--text2)' ?>;">-->
<!--        ₦ NGN-->
<!--      </a>-->
<!--    </div>-->
<!--    <a href="/pages/fund-wallet.php" class="btn btn-primary btn-sm">-->
<!--      <i class="fi fi-rr-plus"></i> Fund-->
<!--    </a>-->
<!--  </div>-->
<!--</div>-->

<!--<?php if ($cur === 'NGN'): ?>-->
<!--<div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);-->
<!--     border-radius:var(--radius-sm);padding:9px 14px;margin-bottom:12px;-->
<!--     font-size:12px;color:var(--text2);display:flex;align-items:center;gap:8px;">-->
<!--  <i class="fi fi-rr-info" style="color:var(--primary);flex-shrink:0;"></i>-->
<!--  Live Rate: <strong>$1 = ₦<?= number_format($rate, 2) ?></strong>-->
<!--  &nbsp;·&nbsp; Auto-updated every 10 minutes-->
<!--</div>-->
<!--<?php endif; ?>-->
<br>
<!-- Search -->
<div style="position:relative;margin-bottom:14px;">
  <i class="fi fi-rr-search" style="position:absolute;left:14px;top:50%;
     transform:translateY(-50%);color:var(--text3);font-size:13px;pointer-events:none;"></i>
  <input type="text" id="searchInput" placeholder="Search logs by name..."
    oninput="filterProducts()"
    style="width:100%;padding:11px 14px 11px 38px;border-radius:99px;
           border:1.5px solid var(--input-border);background:var(--input-bg);
           color:var(--text);font-size:13px;outline:none;
           box-sizing:border-box;font-family:inherit;">
</div>

<!-- Category Tabs -->
<div style="overflow-x:auto;margin-bottom:18px;padding-bottom:4px;-webkit-overflow-scrolling:touch;">
  <div style="display:flex;gap:8px;min-width:max-content;">
    <button onclick="filterByTab('all')" id="tab-all"
      style="display:inline-flex;align-items:center;gap:5px;padding:7px 16px;
             border-radius:99px;font-size:13px;font-weight:700;white-space:nowrap;
             cursor:pointer;border:none;background:var(--primary);color:#fff;transition:all .2s;">
      <i class="fi fi-rr-border-all" style="font-size:12px;"></i> All
    </button>
    <?php foreach ($cats as $cat): ?>
    <button onclick="filterByTab(<?= $cat['id'] ?>)" id="tab-<?= $cat['id'] ?>"
      style="display:inline-flex;align-items:center;gap:5px;padding:7px 16px;
             border-radius:99px;font-size:13px;font-weight:700;white-space:nowrap;
             cursor:pointer;border:1.5px solid var(--card-border);
             background:var(--card-bg);color:var(--text2);transition:all .2s;">
      <i class="<?= htmlspecialchars($cat['icon']) ?>" style="font-size:12px;"></i>
      <?= htmlspecialchars($cat['name']) ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- Products Container -->
<div id="logsContainer">
<?php if (empty($cats)): ?>
  <div style="text-align:center;padding:60px 20px;">
    <i class="fi fi-sr-dolly-flatbed-alt" style="font-size:48px;color:var(--text3);opacity:.3;display:block;margin-bottom:12px;"></i>
    <p style="color:var(--text3);">No products available yet. Check back soon!</p>
  </div>
<?php else: ?>
<?php foreach ($cats as $cat):
  $catProds = $allProds[$cat['id']] ?? [];
?>
<div class="cat-section" data-cat-id="<?= $cat['id'] ?>"
     data-cat-name="<?= strtolower(htmlspecialchars($cat['name'])) ?>">

  <!-- Category Header -->
  <div style="display:flex;align-items:center;gap:12px;padding:13px 15px;
       border-radius:var(--radius-sm);margin-bottom:10px;
       background:linear-gradient(135deg,var(--primary),var(--primary-dark,#5b21b6));">
    <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.18);
         display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="<?= htmlspecialchars($cat['icon']) ?>" style="font-size:20px;color:#fff;"></i>
    </div>
    <div>
      <div style="font-size:14px;font-weight:900;color:#fff;line-height:1.2;">
        <?= strtoupper(htmlspecialchars($cat['name'])) ?>
        <?php if ($cat['description']): ?>
        <span style="font-size:11px;font-weight:500;opacity:.8;"> · <?= htmlspecialchars($cat['description']) ?></span>
        <?php endif; ?>
      </div>
      <div style="font-size:11px;color:rgba(255,255,255,.8);margin-top:2px;">
        <?= count($catProds) ?> product<?= count($catProds)!=1?'s':'' ?> available
      </div>
    </div>
  </div>

  <!-- Products -->
  <?php if ($catProds): ?>
  <?php foreach ($catProds as $product):
    $inStock  = (int)$product['stock_count'] > 0;
    $priceUsd = (float)$product['price_usd'];
    $dispPrice = disp($priceUsd, $cur, $rate);
  ?>
  <div class="product-row"
       data-name="<?= strtolower(htmlspecialchars($product['name'].' '.$product['description'])) ?>"
       style="display:flex;align-items:center;gap:12px;
              background:var(--card-bg);border:1.5px solid var(--card-border);
              border-radius:var(--radius-sm);padding:13px;margin-bottom:8px;
              box-shadow:var(--shadow);transition:border-color .15s,transform .15s;">

    <!-- Icon -->
    <div style="width:46px;height:46px;border-radius:12px;
         background:rgba(124,58,237,.1);display:flex;align-items:center;
         justify-content:center;flex-shrink:0;">
      <i class="<?= htmlspecialchars($cat['icon']) ?>" style="font-size:22px;color:var(--primary);"></i>
    </div>

    <!-- Info -->
    <div style="flex:1;min-width:0;">
      <div style="font-size:13px;font-weight:800;color:var(--text);
           line-height:1.3;margin-bottom:2px;">
        <?= htmlspecialchars($product['name']) ?>
      </div>
      <?php if ($product['description']): ?>
      <div style="font-size:11px;color:var(--text3);margin-bottom:6px;
           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        <?= htmlspecialchars($product['description']) ?>
      </div>
      <?php else: ?><div style="margin-bottom:6px;"></div><?php endif; ?>
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;
               border:1px solid <?= $inStock?'rgba(5,150,105,.3)':'rgba(220,38,38,.3)' ?>;
               color:<?= $inStock?'var(--success,#059669)':'var(--danger,#dc2626)' ?>;
               background:<?= $inStock?'rgba(5,150,105,.08)':'rgba(220,38,38,.08)' ?>;">
          <?= $product['stock_count'] ?> in stock
        </span>
        <span style="font-size:13px;font-weight:900;color:var(--primary);">
          <?= $dispPrice ?>
        </span>
      </div>
    </div>

    <!-- Buy / Out button -->
    <?php if ($inStock): ?>
    <button onclick="openBuyModal(
        <?= $product['id'] ?>,
        '<?= htmlspecialchars(addslashes($product['name'])) ?>',
        <?= $priceUsd ?>,
        <?= (int)$product['stock_count'] ?>,
        '<?= addslashes($dispPrice) ?>')"
      style="flex-shrink:0;display:inline-flex;align-items:center;gap:5px;
             background:linear-gradient(135deg,var(--primary),var(--primary-dark,#5b21b6));
             color:#fff;font-size:12px;font-weight:800;padding:10px 15px;
             border-radius:10px;border:none;cursor:pointer;white-space:nowrap;
             box-shadow:0 3px 10px rgba(124,58,237,.35);">
        <i class="fi fi-rr-cart-arrow-down" style="font-size:10px;"> </i>
      Buy 
    </button>
    <?php else: ?>
    <button disabled
      style="flex-shrink:0;background:var(--bg3);color:var(--text3);font-size:12px;
             font-weight:700;padding:10px 15px;border-radius:10px;border:none;cursor:not-allowed;">
      Out
    </button>
    <?php endif; ?>

  </div><!-- /.product-row -->
  <?php endforeach; ?>
  <?php else: ?>
  <div style="text-align:center;padding:18px;color:var(--text3);font-size:13px;">
    No products in this category yet.
  </div>
  <?php endif; ?>

  <div style="margin-bottom:20px;"></div>
</div><!-- /.cat-section -->
<?php endforeach; ?>
<?php endif; ?>

<!-- No results -->
<div id="noResults" style="display:none;text-align:center;padding:50px 20px;">
  <i class="fi fi-rr-search" style="display:block;font-size:40px;
     color:var(--text3);opacity:.3;margin-bottom:12px;"></i>
  <p style="color:var(--text3);font-size:14px;">No products found.</p>
</div>
</div><!-- /#logsContainer -->


<!-- ══════════════════════════════════════════════════════════ -->
<!--  STEP 1 MODAL – Product + Quantity                        -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="buyModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <h3 class="modal-title" style="margin-bottom:16px;">
      <i class="fi fi-rr-shopping-cart" style="color:var(--primary);margin-right:6px;"></i>
      Confirm Purchase
    </h3>

    <!-- Product info card -->
    <div style="background:var(--bg3);border-radius:var(--radius-sm);
         padding:14px;margin-bottom:14px;">
      <div style="font-size:11px;color:var(--text3);margin-bottom:3px;font-weight:600;">PRODUCT</div>
      <div style="font-size:15px;font-weight:800;color:var(--text);" id="buyProductName">—</div>
    </div>

    <!-- Price + Balance -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
      <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:14px;text-align:center;">
        <div style="font-size:11px;color:var(--text3);margin-bottom:3px;">Price each</div>
        <div style="font-size:18px;font-weight:900;color:var(--primary);" id="buyPriceDisplay">—</div>
      </div>
      <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:14px;text-align:center;">
        <div style="font-size:11px;color:var(--text3);margin-bottom:3px;">Your Balance</div>
        <div style="font-size:16px;font-weight:900;color:var(--success,#059669);">
          <?= disp((float)$user['balance'], $cur, $rate) ?>
        </div>
      </div>
    </div>

    <!-- Quantity -->
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label">Quantity <span style="color:var(--text3);font-weight:500;">(max 10)</span></label>
      <div style="display:flex;align-items:center;gap:10px;">
        <button type="button" onclick="changeQty(-1)"
          style="width:42px;height:42px;border-radius:50%;flex-shrink:0;
                 border:1.5px solid var(--card-border);background:var(--card-bg);
                 color:var(--text);font-size:22px;cursor:pointer;
                 display:flex;align-items:center;justify-content:center;font-weight:300;">−</button>
        <input type="number" id="buyQty" value="1" min="1" max="10"
          oninput="updateTotal()"
          style="flex:1;text-align:center;padding:12px;border:1.5px solid var(--input-border);
                 border-radius:var(--radius-sm);background:var(--input-bg);color:var(--text);
                 font-size:18px;font-weight:900;font-family:inherit;outline:none;">
        <button type="button" onclick="changeQty(1)"
          style="width:42px;height:42px;border-radius:50%;flex-shrink:0;
                 border:1.5px solid var(--card-border);background:var(--card-bg);
                 color:var(--text);font-size:22px;cursor:pointer;
                 display:flex;align-items:center;justify-content:center;font-weight:300;">+</button>
      </div>
    </div>

    <!-- Total -->
    <div style="display:flex;justify-content:space-between;align-items:center;
         padding:12px 14px;background:var(--bg3);border-radius:var(--radius-sm);margin-bottom:16px;">
      <span style="font-size:13px;color:var(--text2);font-weight:600;">Total:</span>
      <span style="font-size:20px;font-weight:900;color:var(--primary);" id="buyTotal">—</span>
    </div>

    <div style="display:flex;gap:10px;">
      <button onclick="closeModal('buyModal')"
        style="flex:1;padding:13px;border-radius:var(--radius-sm);border:1.5px solid var(--card-border);
               background:transparent;color:var(--text);font-size:14px;font-weight:700;
               cursor:pointer;font-family:inherit;">
        Cancel
      </button>
      <button onclick="goToPin()"
        style="flex:1;padding:13px;border-radius:var(--radius-sm);border:none;
               background:linear-gradient(135deg,var(--primary),var(--primary-dark,#5b21b6));
               color:#fff;font-size:14px;font-weight:800;cursor:pointer;
               font-family:inherit;box-shadow:0 3px 12px rgba(124,58,237,.35);
               display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fi fi-rr-lock"></i> Enter PIN
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════ -->
<!--  STEP 2 MODAL – PIN Entry                                 -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="pinModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>

    <!-- Icon -->
    <div style="text-align:center;margin-bottom:14px;">
      <div style="width:60px;height:60px;border-radius:50%;
           background:rgba(124,58,237,.12);display:flex;align-items:center;
           justify-content:center;margin:0 auto 10px;">
        <i class="fi fi-rr-lock" style="font-size:26px;color:var(--primary);"></i>
      </div>
      <h3 style="font-size:18px;font-weight:900;color:var(--text);margin:0 0 4px;">Enter Your PIN</h3>
      <p style="font-size:13px;color:var(--text2);margin:0;">
        Confirm your 4-digit PIN to complete purchase
      </p>
    </div>

    <!-- Summary of what they're buying -->
    <div style="background:var(--bg3);border-radius:var(--radius-sm);
         padding:12px 14px;margin-bottom:16px;
         display:flex;justify-content:space-between;align-items:center;">
      <div style="font-size:13px;font-weight:700;color:var(--text);" id="pinSummaryName">—</div>
      <div style="font-size:15px;font-weight:900;color:var(--primary);" id="pinSummaryTotal">—</div>
    </div>

    <!-- PIN input -->
    <div class="form-group" style="margin-bottom:16px;">
      <label class="form-label" style="text-align:center;display:block;margin-bottom:8px;">
        4-Digit PIN
      </label>
      <div style="position:relative;">
        <i class="fi fi-rr-lock" style="position:absolute;left:14px;top:50%;
           transform:translateY(-50%);color:var(--text3);font-size:14px;pointer-events:none;"></i>
        <input type="password" id="pinInput"
          data-pin
          placeholder="● ● ● ●"
          maxlength="4"
          inputmode="numeric"
          autocomplete="off"
          onkeydown="if(event.key==='Enter')confirmBuy()"
          style="width:100%;box-sizing:border-box;
                 padding:14px 14px 14px 42px;
                 border:1.5px solid var(--input-border);
                 border-radius:var(--radius-sm);
                 background:var(--input-bg);color:var(--text);
                 font-size:20px;font-weight:900;letter-spacing:8px;
                 text-align:center;outline:none;font-family:inherit;">
      </div>
      <!-- Error message -->
      <div id="pinError" style="display:none;margin-top:8px;padding:8px 12px;
           background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);
           border-radius:8px;font-size:13px;color:var(--danger,#dc2626);text-align:center;">
      </div>
    </div>

    <div style="display:flex;gap:10px;">
      <button onclick="backToBuy()"
        style="flex:1;padding:13px;border-radius:var(--radius-sm);
               border:1.5px solid var(--card-border);background:transparent;
               color:var(--text);font-size:14px;font-weight:700;
               cursor:pointer;font-family:inherit;
               display:flex;align-items:center;justify-content:center;gap:6px;">
        <i class="fi fi-rr-arrow-left" style="font-size:12px;"></i> Back
      </button>
      <button onclick="confirmBuy()" id="confirmBuyBtn"
        style="flex:1;padding:13px;border-radius:var(--radius-sm);border:none;
               background:linear-gradient(135deg,var(--primary),var(--primary-dark,#5b21b6));
               color:#fff;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;
               box-shadow:0 3px 12px rgba(124,58,237,.35);
               display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fi fi-rr-check"></i> Confirm Buy
      </button>
    </div>

    <div style="text-align:center;margin-top:12px;">
      <a href="/pages/profile.php" style="font-size:12px;color:var(--text3);">
        Forgot PIN? → Go to Profile to reset
      </a>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════ -->
<!--  SUCCESS MODAL – Credentials                              -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="successModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="text-align:center;margin-bottom:16px;">
      <div style="width:64px;height:64px;border-radius:50%;
           background:rgba(5,150,105,.12);display:flex;align-items:center;
           justify-content:center;margin:0 auto 12px;">
        <i class="fi fi-rr-check" style="font-size:28px;color:var(--success,#059669);"></i>
      </div>
      <h3 style="font-size:20px;font-weight:900;color:var(--text);margin:0 0 6px;">Purchase Successful!</h3>
      <p style="font-size:13px;color:var(--text2);margin:0;" id="successMsg">Your credentials are ready.</p>
    </div>

    <!-- Credentials box -->
    <div id="credentialsBox"
      style="background:var(--bg3);border-radius:var(--radius-sm);
             padding:14px;margin-bottom:14px;
             max-height:280px;overflow-y:auto;">
    </div>

    <div style="display:flex;gap:10px;margin-bottom:10px;">
      <button onclick="copyAllCreds()"
        style="flex:1;padding:12px;border-radius:var(--radius-sm);
               border:1.5px solid var(--card-border);background:transparent;
               color:var(--text);font-size:13px;font-weight:700;cursor:pointer;
               font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;">
        <i class="fi fi-rr-copy"></i> Copy All
      </button>
      <a id="viewOrderBtn" href="#"
        style="flex:1;padding:12px;border-radius:var(--radius-sm);border:none;
               background:linear-gradient(135deg,var(--primary),var(--primary-dark,#5b21b6));
               color:#fff;font-size:13px;font-weight:800;text-decoration:none;
               font-family:inherit;display:flex;align-items:center;
               justify-content:center;gap:6px;">
        <i class="fi fi-rr-receipt"></i> View Order
      </a>
    </div>

    <button onclick="closeModal('successModal')"
      style="width:100%;padding:11px;border-radius:var(--radius-sm);
             border:1.5px solid var(--card-border);background:transparent;
             color:var(--text3);font-size:13px;cursor:pointer;font-family:inherit;">
      Close
    </button>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════ -->
<!--  JAVASCRIPT                                               -->
<!-- ══════════════════════════════════════════════════════════ -->
<script>
var curProductId  = 0;
var curPriceUsd   = 0;
var curStock      = 0;
var curDispPrice  = '';
var curQty        = 1;
var activeTab     = 'all';
var savedCreds    = '';

var CURRENCY = '<?= $cur ?>';
var RATE     = <?= (float)$rate ?>;

// ── Tab filter ───────────────────────────────────────────────
function filterByTab(catId) {
    activeTab = catId;
    document.getElementById('tab-all').style.cssText +=
        ';background:' + (catId==='all' ? 'var(--primary)' : 'var(--card-bg)') +
        ';color:' + (catId==='all' ? '#fff' : 'var(--text2)') +
        ';border-color:' + (catId==='all' ? 'transparent' : 'var(--card-border)');

    document.querySelectorAll('[id^="tab-"]:not(#tab-all)').forEach(function(btn) {
        var id = parseInt(btn.id.replace('tab-',''));
        var on = id === catId;
        btn.style.background   = on ? 'var(--primary)' : 'var(--card-bg)';
        btn.style.color        = on ? '#fff' : 'var(--text2)';
        btn.style.borderColor  = on ? 'transparent' : 'var(--card-border)';
    });

    document.querySelectorAll('.cat-section').forEach(function(sec) {
        sec.style.display = (catId === 'all' || parseInt(sec.dataset.catId) === catId) ? '' : 'none';
    });

    document.getElementById('searchInput').value = '';
    document.getElementById('noResults').style.display = 'none';
}

// ── Search filter ────────────────────────────────────────────
function filterProducts() {
    var q = document.getElementById('searchInput').value.toLowerCase().trim();
    if (!q) { filterByTab(activeTab); return; }
    var any = false;
    document.querySelectorAll('.cat-section').forEach(function(sec) {
        var vis = false;
        sec.querySelectorAll('.product-row').forEach(function(row) {
            var m = row.dataset.name.includes(q);
            row.style.display = m ? '' : 'none';
            if (m) { vis = true; any = true; }
        });
        sec.style.display = vis ? '' : 'none';
    });
    document.getElementById('noResults').style.display = any ? 'none' : 'block';
}

// ── Open Buy Modal (Step 1) ──────────────────────────────────
function openBuyModal(id, name, priceUsd, stock, dispPrice) {
    curProductId = id;
    curPriceUsd  = priceUsd;
    curStock     = stock;
    curDispPrice = dispPrice;
    document.getElementById('buyProductName').textContent = name;
    document.getElementById('buyPriceDisplay').textContent = dispPrice;
    document.getElementById('buyQty').max   = Math.min(stock, 10);
    document.getElementById('buyQty').value = 1;
    curQty = 1;
    updateTotal();
    openModal('buyModal');
}

function changeQty(d) {
    var inp = document.getElementById('buyQty');
    var v   = Math.max(1, Math.min((parseInt(inp.value) || 1) + d, parseInt(inp.max) || 10));
    inp.value = v;
    updateTotal();
}

function updateTotal() {
    curQty = parseInt(document.getElementById('buyQty').value) || 1;
    var total = curQty * curPriceUsd;
    var disp;
    if (CURRENCY === 'NGN') {
        disp = '₦' + (total * RATE).toLocaleString('en-NG', {minimumFractionDigits:2});
    } else {
        disp = '$' + total.toLocaleString('en-US', {minimumFractionDigits:2});
    }
    document.getElementById('buyTotal').textContent = disp;
}

// ── Go to PIN step ───────────────────────────────────────────
function goToPin() {
    curQty = parseInt(document.getElementById('buyQty').value) || 1;
    if (curQty < 1 || curQty > Math.min(curStock, 10)) {
        showToast('Invalid quantity.', 'error'); return;
    }
    // Fill summary in PIN modal
    var name = document.getElementById('buyProductName').textContent;
    var totalDisp = document.getElementById('buyTotal').textContent;
    document.getElementById('pinSummaryName').textContent  = curQty + '× ' + name;
    document.getElementById('pinSummaryTotal').textContent = totalDisp;

    // Clear PIN + error
    document.getElementById('pinInput').value = '';
    document.getElementById('pinError').style.display = 'none';

    closeModal('buyModal');
    openModal('pinModal');
    // Auto-focus PIN input
    setTimeout(function(){ document.getElementById('pinInput').focus(); }, 320);
}

// ── Back to buy ──────────────────────────────────────────────
function backToBuy() {
    closeModal('pinModal');
    openModal('buyModal');
}

// ── Confirm Buy (with PIN) ────────────────────────────────────
async function confirmBuy() {
    var pin = document.getElementById('pinInput').value.trim();
    var errEl = document.getElementById('pinError');

    errEl.style.display = 'none';

    if (!pin || pin.length !== 4 || !/^\d{4}$/.test(pin)) {
        errEl.textContent = 'Please enter your 4-digit numeric PIN.';
        errEl.style.display = 'block';
        document.getElementById('pinInput').focus();
        return;
    }

    var btn = document.getElementById('confirmBuyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fi fi-rr-spinner" style="animation:spin .8s linear infinite;display:inline-block;"></i> Processing...';

    try {
        var res = await fetch('/api/logs-purchase.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id : curProductId,
                quantity   : curQty,
                currency   : CURRENCY,
                pin        : pin
            })
        });

        // Check content-type before parsing JSON
        var contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            var raw = await res.text();
            console.error('Non-JSON response:', raw);
            throw new Error('Server returned invalid response. Check server logs.');
        }

        var data = await res.json();

        if (data.success) {
            closeModal('pinModal');
            // Build credentials display
            savedCreds = data.credentials.join('\n\n---\n\n');
            var box = document.getElementById('credentialsBox');
            box.innerHTML = data.credentials.map(function(c, i) {
                return '<div style="' + (i > 0 ? 'margin-top:10px;padding-top:10px;border-top:1px solid var(--border);' : '') + '">'
                    + '<div style="font-size:10px;font-weight:700;color:var(--text3);margin-bottom:5px;">ITEM ' + (i+1) + '</div>'
                    + '<pre style="font-size:12px;color:var(--text);white-space:pre-wrap;word-break:break-all;'
                    + 'margin:0;font-family:monospace;line-height:1.6;">' + escHtml(c) + '</pre>'
                    + '</div>';
            }).join('');

            var qty = data.credentials.length;
            document.getElementById('successMsg').textContent =
                qty + ' item' + (qty>1?'s':'') + ' delivered. Also sent to your email.';
            document.getElementById('viewOrderBtn').href = '/pages/log-order.php?id=' + data.order_id;
            openModal('successModal');

            // Reload page after close to refresh stock counts
            document.getElementById('successModal').addEventListener('click', function handler(e) {
                if (e.target === document.getElementById('successModal')) {
                    window.location.reload();
                    document.getElementById('successModal').removeEventListener('click', handler);
                }
            });

        } else {
            // Show error in PIN modal if it's a PIN error, else toast
            var msg = data.message || 'Purchase failed. Please try again.';
            if (msg.toLowerCase().includes('pin') || msg.toLowerCase().includes('incorrect')) {
                errEl.textContent = msg;
                errEl.style.display = 'block';
                document.getElementById('pinInput').select();
            } else {
                closeModal('pinModal');
                showToast(msg, 'error');
            }
        }

    } catch(e) {
        console.error('Purchase error:', e);
        errEl.textContent = e.message || 'Network error. Please check your connection and try again.';
        errEl.style.display = 'block';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fi fi-rr-check"></i> Confirm Buy';
}

// ── Helpers ──────────────────────────────────────────────────
function copyAllCreds() {
    navigator.clipboard.writeText(savedCreds).then(function() {
        showToast('All credentials copied!', 'success');
    });
}
function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showToast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%);'
        + 'z-index:9999;background:' + (type==='success'?'var(--success,#059669)':'var(--danger,#dc2626)') + ';'
        + 'color:#fff;padding:12px 22px;border-radius:99px;font-size:13px;font-weight:700;'
        + 'box-shadow:0 4px 20px rgba(0,0,0,.3);white-space:nowrap;pointer-events:none;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .3s'; }, 2500);
    setTimeout(function(){ t.remove(); }, 2900);
}

// PIN – digits only (reinforced)
document.getElementById('pinInput').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'').slice(0,4);
    document.getElementById('pinError').style.display = 'none';
});
</script>


<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
