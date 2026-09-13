<?php
// ============================================================
//  ErifyLogs – pages/log-order.php  (Order Detail)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_login();
$user = current_user();

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { header('Location: /pages/logs.php'); exit; }

$ord = $pdo->prepare("SELECT o.*, u.email AS user_email, u.username
    FROM log_orders o JOIN users u ON u.id=o.user_id
    WHERE o.id=? AND o.user_id=?");
$ord->execute([$orderId, $user['id']]);
$order = $ord->fetch();
if (!$order) { header('Location: /pages/logs.php'); exit; }

// Get delivered credentials
$items = $pdo->prepare("SELECT ls.credentials FROM log_order_items loi
    JOIN log_stock ls ON ls.id=loi.stock_id WHERE loi.order_id=?");
$items->execute([$orderId]);
$credentials = $items->fetchAll(PDO::FETCH_COLUMN);

$cur  = $user['currency'] ?? 'USD';
$rate = get_usd_to_ngn_rate();
$pageTitle = 'Order #' . $orderId;
require_once ROOT_PATH . '/includes/header.php';
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
  <a href="/pages/logs.php" style="width:36px;height:36px;border-radius:50%;
     background:var(--card-bg);border:1.5px solid var(--card-border);
     display:flex;align-items:center;justify-content:center;color:var(--text);text-decoration:none;">
    <i class="fi fi-rr-arrow-left" style="font-size:15px;"></i>
  </a>
  <h2 style="font-size:18px;font-weight:900;color:var(--text);">Order #<?= $orderId ?></h2>
</div>

<!-- Status Banner -->
<div style="background:<?= $order['status']==='completed' ? 'rgba(5,150,105,.12)' : 'rgba(220,38,38,.12)' ?>;
     border:1.5px solid <?= $order['status']==='completed' ? 'rgba(5,150,105,.3)' : 'rgba(220,38,38,.3)' ?>;
     border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:14px;
     display:flex;align-items:center;gap:10px;">
  <i class="fi fi-rr-<?= $order['status']==='completed' ? 'check-circle' : 'cross-circle' ?>"
     style="font-size:20px;color:<?= $order['status']==='completed' ? 'var(--success)' : 'var(--danger)' ?>;"></i>
  <div>
    <div style="font-size:14px;font-weight:800;
         color:<?= $order['status']==='completed' ? 'var(--success)' : 'var(--danger)' ?>;">
      <?= ucfirst($order['status']) ?>
    </div>
    <div style="font-size:12px;color:var(--text2);">
      <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
    </div>
  </div>
</div>

<!-- Order Summary -->
<div style="background:var(--card-bg);border:1.5px solid var(--card-border);
     border-radius:var(--radius);padding:16px;margin-bottom:14px;backdrop-filter:var(--glass-blur);">
  <div style="font-size:13px;font-weight:700;color:var(--text3);margin-bottom:12px;">ORDER DETAILS</div>
  <?php
    $rows = [
      ['Product',   $order['product_name']],
      ['Quantity',  $order['quantity'] . ' item(s)'],
      ['Reference', $order['reference']],
      ['Amount',    '$' . number_format((float)$order['total_usd'],4)
                    . ($cur==='NGN' ? ' (₦'.number_format((float)$order['total_usd']*$rate,2).')' : '')],
      ['Date',      date('d M Y, H:i', strtotime($order['created_at']))],
    ];
    foreach ($rows as [$k,$v]):
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;
       padding:9px 0;border-bottom:1px solid var(--border);">
    <span style="font-size:13px;color:var(--text2);"><?= $k ?></span>
    <span style="font-size:13px;font-weight:700;color:var(--text);"><?= htmlspecialchars($v) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<!-- Credentials -->
<div style="margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
    <div style="font-size:15px;font-weight:800;color:var(--text);">
      Delivered Credentials
    </div>
    <button onclick="copyAll()" class="btn btn-ghost btn-sm">
      <i class="fi fi-rr-copy"></i> Copy All
    </button>
  </div>

  <?php if (empty($credentials)): ?>
  <div class="alert alert-info">
    <i class="fi fi-rr-info"></i> Credentials were sent to your email: <?= clean($user['email']) ?>
  </div>
  <?php else: ?>
  <?php foreach ($credentials as $i => $cred): ?>
  <div style="background:var(--card-bg);border:1.5px solid var(--card-border);
       border-radius:var(--radius-sm);padding:14px;margin-bottom:10px;
       backdrop-filter:var(--glass-blur);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <span style="font-size:11px;font-weight:700;color:var(--text3);">ITEM <?= $i+1 ?></span>
      <button onclick="copySingle(this)"
        data-text="<?= htmlspecialchars($cred) ?>"
        style="font-size:11px;padding:4px 10px;border-radius:99px;
               border:1px solid var(--card-border);background:transparent;
               color:var(--primary);cursor:pointer;font-weight:600;">
        Copy
      </button>
    </div>
    <pre style="font-size:12px;color:var(--text);white-space:pre-wrap;word-break:break-all;
         margin:0;font-family:'Courier New',monospace;line-height:1.6;"><?= htmlspecialchars($cred) ?></pre>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<div style="padding:14px;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.25);
     border-radius:var(--radius-sm);font-size:12px;color:var(--warning);margin-bottom:16px;">
  <i class="fi fi-rr-info" style="margin-right:6px;"></i>
  Credentials also sent to <strong><?= clean($user['email']) ?></strong>. Keep them safe — do not share.
</div>

<a href="/pages/logs.php" class="btn btn-primary btn-block">
  <i class="fi fi-rr-shopping-bag"></i> Continue Shopping
</a>

<script>
const allCreds = <?= json_encode(implode("\n\n---\n\n", $credentials)) ?>;
function copyAll() {
    navigator.clipboard.writeText(allCreds).then(()=>showToast('All credentials copied!','success'));
}
function copySingle(btn) {
    navigator.clipboard.writeText(btn.dataset.text).then(()=>{
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        btn.style.color = 'var(--success)';
        setTimeout(()=>{ btn.textContent=orig; btn.style.color='var(--primary)'; },1500);
    });
}
function showToast(msg,type){
    const t=document.createElement('div');
    t.style.cssText=`position:fixed;bottom:90px;left:50%;transform:translateX(-50%);z-index:9999;
      background:${type==='success'?'var(--success)':'var(--danger)'};color:#fff;
      padding:12px 20px;border-radius:99px;font-size:13px;font-weight:700;
      box-shadow:0 4px 20px rgba(0,0,0,.3);white-space:nowrap;`;
    t.textContent=msg; document.body.appendChild(t); setTimeout(()=>t.remove(),3000);
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
