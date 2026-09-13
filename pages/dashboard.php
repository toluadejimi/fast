<?php
// ============================================================
//  pages/dashboard.php  (UPGRADED)
//  – Balance shown in USD
//  – VA generation works for both NCWallet and Paystack
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Dashboard – ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';
require_once ROOT_PATH . '/includes/ncwallet.php';

$va          = get_user_va($user['id']);
$showVaPopup = !empty($_SESSION['show_va']) && !$va;
if ($showVaPopup) unset($_SESSION['show_va']);

// Recent orders
$recent = $pdo->prepare("SELECT * FROM otp_orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$recent->execute([$user['id']]);
$recentOrders = $recent->fetchAll();

// Stats
$s = $pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE user_id=?"); $s->execute([$user['id']]); $totalOrders = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM otp_orders WHERE user_id=? AND status='RECEIVED'"); $s->execute([$user['id']]); $totalSpent = (float)$s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE user_id=? AND status='CANCELED'"); $s->execute([$user['id']]); $totalCancelled = (int)$s->fetchColumn();

// Dashboard ads
try {
    $adsStmt = $pdo->query("SELECT * FROM dashboard_ads WHERE is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 5");
    $dashAds = $adsStmt ? $adsStmt->fetchAll() : [];
} catch (Exception $e) { $dashAds = []; }

$ppActive = $pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='paymentpoint'")->fetchColumn();
$ncActive = $pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='ncwallet'")->fetchColumn();
$psActive  = $pdo->query("SELECT is_active FROM payment_gateways WHERE gateway='paystack'")->fetchColumn();
$rate      = get_usd_to_ngn_rate();
?>






 




<!-- Balance card -->
<!--<div class="balance-card">-->
<!--  <div class="bal-label">Main Balance</div>-->
<!--  <div class="bal-amount"><?= fmt_money((float)$user['balance']) ?></div>-->
<!--  <div style="font-size:12px;opacity:.65;margin-top:2px;">-->
<!--    ≈ <?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>-->
<!--    &nbsp;·&nbsp; Rate: ₦<?= number_format($rate, 0) ?>/$-->
<!--  </div>-->

<!--  <?php if ($va): ?>-->
<!--  <div class="bal-va">-->
<!--    <div style="font-size:11px;opacity:.65;margin-bottom:3px;">YOUR VIRTUAL ACCOUNT · <?= clean($va['bank_name']) ?></div>-->
<!--    <div class="d-flex align-center justify-between">-->
<!--      <div>-->
<!--        <div class="bal-va-num">-->
<!--          <?= clean($va['account_number']) ?>-->
<!--          <button class="copy-btn" onclick="copyText('<?= clean($va['account_number']) ?>',this)">-->
<!--            <i class="fi fi-rr-copy"></i>-->
<!--          </button>-->
<!--        </div>-->
<!--        <div class="bal-va-sub"><?= clean($va['account_name']) ?></div>-->
<!--      </div>-->
<!--    </div>-->
<!--  </div>-->
<!--  <?php else: ?>-->
<!--  <div class="bal-va">-->
<!--    <button onclick="openModal('va-modal')"-->
<!--            style="background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);-->
<!--                   color:#fff;padding:8px 16px;border-radius:8px;cursor:pointer;-->
<!--                   font-size:13px;font-weight:700;font-family:inherit;transition:all .2s;">-->
<!--      <i class="fi fi-rr-add"></i> Generate Virtual Account-->
<!--    </button>-->
<!--  </div>-->
<!--  <?php endif; ?>-->
<!--</div>-->





<!-- Balance card -->
<!--<div class="balance-card" style="background:linear-gradient(180deg,#FFF5ED 0%,#FFFDFB 100%);padding:20px 16px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.04);margin:14px;border:1px solid #F4EBFF;position:relative;">-->
  
  <!-- Content Layout: Left Details, Right Illustration Icon -->
<!--  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;padding-right:12px;">-->
<!--    <div>-->
<!--      <div class="bal-label" style="font-size:13px;color:#475467;font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:4px;">-->
<!--        Main Balance <i class="fi fi-rr-eye-crossed" style="font-size:12px;opacity:0.6;"></i>-->
<!--      </div>-->
<!--      <div class="bal-amount" style="font-size:32px;font-weight:800;color:#1D1037;letter-spacing:-0.5px;line-height:1.1;font-family:sans-serif;"><?= fmt_money((float)$user['balance']) ?></div>-->
<!--      <div style="font-size:12px;color:#667085;margin-top:6px;font-weight:500;">-->
<!--        ≈ <?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>-->
<!--        <span style="margin:0 4px;opacity:0.5;">·</span> Rate: ₦<?= number_format($rate, 0) ?>/$-->
<!--      </div>-->
<!--    </div>-->
    
    <!-- Right Side: Large Uicons Wallet Icon styled to pop -->
<!--    <div style="width:64px;height:64px;background:#FFF4ED;border-radius:20px;display:flex;align-items:center;justify-content:center;color:#7F56D9;box-shadow:0 6px 16px rgba(127,86,217,0.1);margin-top:2px;">-->
<!--      <i class="fi fi-rr-wallet" style="font-size:32px;"></i>-->
<!--    </div>-->
<!--  </div>-->

  <!-- Dynamic Section: Integrated Floating Quick Action Grid Base -->
<!--  <div class="balance-actions-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px;">-->
    
<!--    <a href="#" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-wallet" style="font-size:15px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Add Wallet</span>-->
<!--    </a>-->

<!--    <a href="<?= SITE_URL ?>/pages/logs.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-time-past" style="font-size:15px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">View History</span>-->
<!--    </a>-->

<!--    <a href="<?= SITE_URL ?>/pages/numbers.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-plus" style="font-size:14px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Buy Number</span>-->
<!--    </a>-->

<!--    <a href="#" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-paper-plane" style="font-size:14px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Join Channel</span>-->
<!--    </a>-->

<!--  </div>-->

  <!-- Virtual Account Drawer Segment -->
<!--  <?php if ($va): ?>-->
<!--  <div class="bal-va" style="margin-top:16px;padding-top:14px;border-top:1px dashed #EAECF0;">-->
<!--    <div style="font-size:10px;letter-spacing:0.3px;color:#667085;margin-bottom:4px;font-weight:700;text-transform:uppercase;">VA: <?= clean($va['bank_name']) ?></div>-->
<!--    <div style="display:flex;align-items:center;justify-content:space-between;">-->
<!--      <div class="bal-va-num" style="font-size:15px;font-weight:700;color:#1D1037;display:flex;align-items:center;gap:6px;">-->
<!--        <?= clean($va['account_number']) ?>-->
<!--        <button class="copy-btn" onclick="copyText('<?= clean($va['account_number']) ?>',this)" style="background:#FFF4ED;border:none;color:#7F56D9;width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:11px;padding:0;">-->
<!--          <i class="fi fi-rr-copy"></i>-->
<!--        </button>-->
<!--      </div>-->
<!--      <div class="bal-va-sub" style="font-size:11px;color:#475467;font-weight:600;text-align:right;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($va['account_name']) ?></div>-->
<!--    </div>-->
<!--  </div>-->
<!--  <?php else: ?>-->
<!--  <div class="bal-va" style="margin-top:16px;padding-top:14px;border-top:1px dashed #EAECF0;">-->
<!--    <button onclick="openModal('va-modal')" style="background:transparent;border:none;color:#7F56D9;width:100%;font-size:12px;font-weight:700;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;">-->
<!--      <i class="fi fi-rr-add-document" style="font-size:13px;"></i> Tap to Link Virtual Funding Account-->
<!--    </button>-->
<!--  </div>-->
<!--  <?php endif; ?>-->

<!--</div>-->




<!-- Balance card -->
<div class="balance-card" style="background:linear-gradient(to right,#F4EBFF 0%,#E9D7FE 100%);padding:20px 16px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.04);margin:14px 0;border:1px solid #F4EBFF;position:relative;width:100%;">
  
  <!-- Content Layout: Left Details, Right Illustration Icon -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
    <div>
      <div class="bal-label" style="font-size:13px;color:#475467;font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:4px;cursor:pointer;" onclick="toggleBalanceDisplay()">
        Main Balance 
        <i id="bal-eye-icon" class="fi fi-rr-eye" style="font-size:13px;color:#7F56D9;"></i>
      </div>
      
      <div id="bal-amount-text" class="bal-amount" 
           data-current-currency="NGN"
           data-real-usd="<?= fmt_money((float)$user['balance']) ?>" 
           data-real-ngn="<?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>" 
           data-masked-amount="••••••" 
           style="font-size:32px;font-weight:800;color:#1D1037;letter-spacing:-0.5px;line-height:1.1;font-family:sans-serif;">
           <?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>
      </div>
      
      <div id="bal-sub-text" style="font-size:12px;color:#667085;margin-top:6px;font-weight:500;">
        <span id="alt-currency-preview">≈ <?= fmt_money((float)$user['balance']) ?></span>
        <span style="margin:0 4px;opacity:0.5;">·</span> Rate: ₦<?= number_format($rate, 0) ?>/$
      </div>
    </div>
    
    <!-- Right Side: Clean Vector Wallet Icon with NO background -->
    <div style="color:#7F56D9;margin-top:4px;margin-right:4px;display:flex;align-items:center;justify-content:center;">
      <i class="fi fi-rr-wallet" style="font-size:44px;line-height:1;"></i>
    </div>
  </div>

  <!-- Dynamic Section: Integrated Floating Quick Action Grid Base -->
  <div class="balance-actions-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px;">
    <a href="<?= SITE_URL ?>/pages/fund-wallet.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;"><div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-wallet" style="font-size:15px;"></i></div><span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Add Wallet</span></a>
    <a href="<?= SITE_URL ?>/pages/data.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;"><div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-time-past" style="font-size:15px;"></i></div><span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Data</span></a>
    <a href="<?= SITE_URL ?>/pages/numbers.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;"><div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-cloud-download-alt" style="font-size:14px;"></i></div><span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Buy Number</span></a>
    <a href="https://whatsapp.com/channel/0029VbDgxW52P59n2QDLU61F" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;"><div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-paper-plane" style="font-size:14px;"></i></div><span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Join Channel</span></a>
  </div>

  <!-- Virtual Account Drawer Segment -->
  <?php if ($va): ?>
  <div class="bal-va" style="margin-top:16px;padding-top:14px;border-top:1px dashed #E9D7FE;">
    <div style="font-size:10px;letter-spacing:0.3px;color:#667085;margin-bottom:4px;font-weight:700;text-transform:uppercase;">VA: <?= clean($va['bank_name']) ?></div>
    <div style="display:flex;align-items:center;justify-content:space-between;">
      <div class="bal-va-num" style="font-size:15px;font-weight:700;color:#1D1037;display:flex;align-items:center;gap:6px;">
        <?= clean($va['account_number']) ?>
        <button class="copy-btn" onclick="copyText('<?= clean($va['account_number']) ?>',this)" style="background:#F4EBFF;border:none;color:#7F56D9;width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:11px;padding:0;"><i class="fi fi-rr-copy"></i></button>
      </div>
      <div class="bal-va-sub" style="font-size:11px;color:#475467;font-weight:600;text-align:right;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($va['account_name']) ?></div>
    </div>
  </div>
  <?php else: ?>
  <div class="bal-va" style="margin-top:16px;padding-top:14px;border-top:1px dashed #E9D7FE;">
    <button onclick="openModal('va-modal')" style="background:transparent;border:none;color:#7F56D9;width:100%;font-size:12px;font-weight:700;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;"><i class="fi fi-rr-add" style="font-size:13px;"></i> Tap to Link Virtual Funding Account</button>
  </div>
  <?php endif; ?>

</div>

<!-- Consolidated JavaScript Engine -->
<script>
let isBalanceHidden = false;

setTimeout(() => {
  const tooltip = document.getElementById('currency-tooltip');
  if(tooltip) {
    tooltip.style.opacity = '0';
    tooltip.style.transform = 'translateY(5px)';
    setTimeout(() => tooltip.remove(), 400);
  }
}, 5000);

function toggleBalanceDisplay() {
  const amtText = document.getElementById('bal-amount-text');
  const subText = document.getElementById('bal-sub-text');
  const eyeIcon = document.getElementById('bal-eye-icon');
  
  isBalanceHidden = !isBalanceHidden;
  
  if (isBalanceHidden) {
    amtText.innerText = amtText.getAttribute('data-masked-amount');
    subText.style.visibility = 'hidden';
    eyeIcon.className = 'fi fi-rr-eye-crossed';
  } else {
    subText.style.visibility = 'visible';
    eyeIcon.className = 'fi fi-rr-eye';
    refreshDisplayValues();
  }
}

function toggleCurrencySystem() {
  const amtText = document.getElementById('bal-amount-text');
  const flagUi = document.getElementById('currency-flag-ui');
  const labelUi = document.getElementById('currency-label-ui');
  
  const tooltip = document.getElementById('currency-tooltip');
  if(tooltip) tooltip.remove();
  
  let targetCurrency = amtText.getAttribute('data-current-currency') === 'NGN' ? 'USD' : 'NGN';
  amtText.setAttribute('data-current-currency', targetCurrency);
  
  if (targetCurrency === 'NGN') {
    flagUi.innerText = '🇳🇬';
    labelUi.innerText = 'NGN';
  } else {
    flagUi.innerText = '🇺🇸';
    labelUi.innerText = 'USD';
  }
  
  if (!isBalanceHidden) {
    refreshDisplayValues();
  }
}

function refreshDisplayValues() {
  const amtText = document.getElementById('bal-amount-text');
  const altText = document.getElementById('alt-currency-preview');
  const activeCurrency = amtText.getAttribute('data-current-currency');
  
  if (activeCurrency === 'NGN') {
    amtText.innerText = amtText.getAttribute('data-real-ngn');
    altText.innerText = '≈ ' + amtText.getAttribute('data-real-usd');
  } else {
    amtText.innerText = amtText.getAttribute('data-real-usd');
    altText.innerText = '≈ ' + amtText.getAttribute('data-real-ngn');
  }
  
  const profBal = document.getElementById('profile-bal-display');
  if(profBal) profBal.innerText = (activeCurrency === 'NGN') ? profBal.getAttribute('data-real-ngn') : profBal.getAttribute('data-real-usd');
}
</script>




<!-- Balance card thissssss-->
<!--<div class="balance-card" style="background:linear-gradient(180deg,#FFF5ED 0%,#FFFDFB 100%);padding:20px 16px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.04);margin:14px 0;border:1px solid #F4EBFF;position:relative;width:100%;">-->
  
  <!-- Content Layout: Left Details, Right Illustration Icon -->
<!--  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">-->
<!--    <div>-->
<!--      <div class="bal-label" style="font-size:13px;color:#475467;font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:4px;cursor:pointer;" onclick="toggleBalanceDisplay()">-->
<!--        Main Balance -->
<!--        <i id="bal-eye-icon" class="fi fi-rr-eye" style="font-size:13px;color:#7F56D9;"></i>-->
<!--      </div>-->
<!--      <div id="bal-amount-text" class="bal-amount" data-real-amount="<?= fmt_money((float)$user['balance']) ?>" data-masked-amount="••••••" style="font-size:32px;font-weight:800;color:#1D1037;letter-spacing:-0.5px;line-height:1.1;font-family:sans-serif;"><?= fmt_money((float)$user['balance']) ?></div>-->
<!--      <div id="bal-sub-text" style="font-size:12px;color:#667085;margin-top:6px;font-weight:500;">-->
<!--        ≈ <?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>-->
<!--        <span style="margin:0 4px;opacity:0.5;">·</span> Rate: ₦<?= number_format($rate, 0) ?>/$-->
<!--      </div>-->
<!--    </div>-->
    
    <!-- Right Side: Clean Vector Wallet Icon with NO background -->
<!--    <div style="color:#7F56D9;margin-top:4px;margin-right:4px;display:flex;align-items:center;justify-content:center;">-->
<!--      <i class="fi fi-rr-wallet" style="font-size:44px;line-height:1;"></i>-->
<!--    </div>-->
<!--  </div>-->

  <!-- Dynamic Section: Integrated Floating Quick Action Grid Base -->
<!--  <div class="balance-actions-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px;">-->
    
<!--    <a href="<?= SITE_URL ?>/pages/fund-wallet.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-wallet" style="font-size:15px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Add Wallet</span>-->
<!--    </a>-->

<!--    <a href="<?= SITE_URL ?>/pages/logs.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-time-past" style="font-size:15px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">View History</span>-->
<!--    </a>-->

<!--    <a href="<?= SITE_URL ?>/pages/numbers.php" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-plus" style="font-size:14px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Buy Number</span>-->
<!--    </a>-->

<!--    <a href="<?= SITE_URL ?>/pages/" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:16px;padding:12px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;">-->
<!--      <div style="width:32px;height:32px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-paper-plane" style="font-size:14px;"></i></div>-->
<!--      <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;">Join Channel</span>-->
<!--    </a>-->

<!--  </div>-->

  <!-- Virtual Account Drawer Segment -->
<!--  <?php if ($va): ?>-->
<!--  <div class="bal-va" style="margin-top:16px;padding-top:14px;border-top:1px dashed #EAECF0;">-->
<!--    <div style="font-size:10px;letter-spacing:0.3px;color:#667085;margin-bottom:4px;font-weight:700;text-transform:uppercase;">VA: <?= clean($va['bank_name']) ?></div>-->
<!--    <div style="display:flex;align-items:center;justify-content:space-between;">-->
<!--      <div class="bal-va-num" style="font-size:15px;font-weight:700;color:#1D1037;display:flex;align-items:center;gap:6px;">-->
<!--        <?= clean($va['account_number']) ?>-->
<!--        <button class="copy-btn" onclick="copyText('<?= clean($va['account_number']) ?>',this)" style="background:#FFF4ED;border:none;color:#7F56D9;width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:11px;padding:0;">-->
<!--          <i class="fi fi-rr-copy"></i>-->
<!--        </button>-->
<!--      </div>-->
<!--      <div class="bal-va-sub" style="font-size:11px;color:#475467;font-weight:600;text-align:right;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($va['account_name']) ?></div>-->
<!--    </div>-->
<!--  </div>-->
<!--  <?php else: ?>-->
<!--  <div class="bal-va" style="margin-top:16px;padding-top:14px;border-top:1px dashed #EAECF0;">-->
<!--    <button onclick="openModal('va-modal')" style="background:transparent;border:none;color:#7F56D9;width:100%;font-size:12px;font-weight:700;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;">-->
<!--      <i class="fi fi-rr-add-document" style="font-size:13px;"></i> Tap to Link Virtual Funding Account-->
<!--    </button>-->
<!--  </div>-->
<!--  <?php endif; ?>-->

<!--</div>-->

<!-- JavaScript for Toggle Visibility functionality -->
<!--<script>-->
<!--function toggleBalanceDisplay() {-->
<!--  const amtText = document.getElementById('bal-amount-text');-->
<!--  const subText = document.getElementById('bal-sub-text');-->
<!--  const eyeIcon = document.getElementById('bal-eye-icon');-->
  
<!--  if (amtText.innerText !== '••••••') {-->
    <!--// Hide balance parameters-->
<!--    amtText.innerText = '••••••';-->
<!--    subText.style.visibility = 'hidden';-->
<!--    eyeIcon.className = 'fi fi-rr-eye-crossed';-->
<!--  } else {-->
    <!--// Reveal balance parameters-->
<!--    amtText.innerText = amtText.getAttribute('data-real-amount');-->
<!--    subText.style.visibility = 'visible';-->
<!--    eyeIcon.className = 'fi fi-rr-eye';-->
<!--  }-->
<!--}-->
<!--</script>-->







<!--Dashboard Ads Slider -->
<?php if (!empty($dashAds)): ?>
<div class="dash-ads-wrap" style="margin-bottom:16px;position:relative;overflow:hidden;border-radius:var(--radius);">
  <div class="dash-ads-track" id="dashAdsTrack" style="display:flex;transition:transform .4s ease;will-change:transform;">
    <?php foreach ($dashAds as $ad): ?>
    <a href="<?= clean($ad['link_url']) ?>" target="_blank" rel="noopener"
       style="flex:0 0 100%;display:block;border-radius:var(--radius);overflow:hidden;line-height:0;">
      <img src="<?= SITE_URL ?>/assets/uploads/ads/<?= clean($ad['image_file']) ?>"
           alt="<?= clean($ad['title']) ?>"
           style="width:100%;height:100px;object-fit:cover;display:block;">
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (count($dashAds) > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;padding:8px 0;background:var(--bg2);">
    <?php foreach ($dashAds as $i => $ad): ?>
    <button class="dash-ad-dot" data-idx="<?= $i ?>"
            style="width:7px;height:7px;border-radius:50%;border:none;cursor:pointer;
                   background:<?= $i===0?'var(--primary)':'var(--border)' ?>;padding:0;transition:background .3s;"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<script>
(function(){
  var track = document.getElementById('dashAdsTrack');
  if (!track) return;
  var dots = document.querySelectorAll('.dash-ad-dot');
  var total = <?= count($dashAds) ?>;
  var cur = 0;
  function go(n) {
    cur = (n + total) % total;
    track.style.transform = 'translateX(-' + (cur * 100) + '%)';
    dots.forEach(function(d,i){ d.style.background = i===cur?'var(--primary)':'var(--border)'; });
  }
  dots.forEach(function(d){ d.addEventListener('click', function(){ go(parseInt(this.dataset.idx)); }); });
  if (total > 1) setInterval(function(){ go(cur + 1); }, 4000);
})();
</script>
<?php endif; ?>









<!-- Quick Services Section Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin:24px 0 12px 0;width:100%;font-family:sans-serif;">
  <span style="font-size:16px;font-weight:700;color:#1D1037;">Quick Services</span>
  <a href="#" style="font-size:13px;font-weight:700;color:#7F56D9;text-decoration:none;">See All</a>
</div>

<!-- Quick actions Grid Wrapper -->
<div class="quick-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;width:100%;margin-bottom:20px;">

  <!-- Buy Number Card -->
  <a href="<?= SITE_URL ?>/pages/numbers.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-sr-cloud-download-alt" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Buy Number</span>
  </a>
  
  <!-- Buy Logs Card (with HOT indicator tag badge) -->
  <a href="<?= SITE_URL ?>/pages/logs.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="position:absolute;top:4px;right:4px;background:#F04438;color:#FFFFFF;font-size:8px;font-weight:800;padding:1px 5px;border-radius:6px;text-transform:uppercase;letter-spacing:0.2px;line-height:1.2;">Hot</div>
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-sr-shopping-cart-add" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Buy Logs</span>
  </a>
  
  <!-- Data Card (with NEW indicator tag badge) -->
  <a href="<?= SITE_URL ?>/pages/data.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="position:absolute;top:4px;right:4px;background:#7F56D9;color:#FFFFFF;font-size:8px;font-weight:800;padding:1px 5px;border-radius:6px;text-transform:uppercase;letter-spacing:0.2px;line-height:1.2;">New</div>
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-rss" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Data</span>
  </a>

  <!-- Airtime Card -->
  <a href="<?= SITE_URL ?>/pages/airtime.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-sr-call-incoming" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Airtime</span>
  </a>

  <!-- Cable Card -->
  <a href="<?= SITE_URL ?>/pages/cable.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-tv-retro" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Cable</span>
  </a>

  <!-- Electricity Card -->
  <a href="<?= SITE_URL ?>/pages/electricity.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-sr-radio-tower" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Electricity</span>
  </a>
  
  <!-- Fund Wallet Card -->
  <a href="<?= SITE_URL ?>/pages/fund-wallet.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-add-folder" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Fund Wallet</span>
  </a>
  
  
  <!-- Fund Wallet Card -->
  <a href="https://t.me/emzysmsverify" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-arrow-up" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">Telegram Chanel</span>
  </a>
  
  <!-- Fund Wallet Card -->
  <a href="https://whatsapp.com/channel/0029VbDgxW52P59n2QDLU61F" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-arrow-up" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">WhatsApp Chanel</span>
  </a>

  <!-- History Card -->
  <a href="<?= SITE_URL ?>/pages/history.php" class="quick-btn" style="background:#FFFFFF;border:1px solid #F2F4F7;border-radius:18px;padding:14px 4px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,0.02);transition:all 0.2s;grid-column:span 1;position:relative;">
    <div style="width:36px;height:36px;background:#F4EBFF;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-time-past" style="font-size:16px;"></i></div>
    <span style="font-size:11px;color:#1D1037;font-weight:600;text-align:center;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;">History</span>
  </a>

</div>


<!-- Unified App Trust Badges Row Matrix Container -->
<div class="app-trust-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;width:100%;background:#FFFFFF;border:1px solid #F4EBFF;border-radius:18px;padding:12px 6px;box-shadow:0 4px 16px rgba(38,4,78,0.02);margin:16px 0;font-family:sans-serif;">

  <!-- Feature Cell: Instant Delivery -->
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:2px;">
    <div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-bolt" style="font-size:14px;"></i></div>
    <span style="font-size:10px;color:#1D1037;font-weight:700;text-align:center;line-height:1.2;white-space:normal;width:100%;">Instant Delivery</span>
  </div>

  <!-- Feature Cell: High Success Rate -->
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:2px;">
    <div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-shield-check" style="font-size:14px;"></i></div>
    <span style="font-size:10px;color:#1D1037;font-weight:700;text-align:center;line-height:1.2;white-space:normal;width:100%;">High Success</span>
  </div>

  <!-- Feature Cell: 24/7 Support -->
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:2px;">
    <div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-comments" style="font-size:14px;"></i></div>
    <span style="font-size:10px;color:#1D1037;font-weight:700;text-align:center;line-height:1.2;white-space:normal;width:100%;">24/7 Support</span>
  </div>

  <!-- Feature Cell: Secure & Safe -->
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:2px;">
    <div style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-lock" style="font-size:14px;"></i></div>
    <span style="font-size:10px;color:#1D1037;font-weight:700;text-align:center;line-height:1.2;white-space:normal;width:100%;">Secure & Safe</span>
  </div>

</div>




<?php
$dashReviews = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC LIMIT 2")->fetchAll();
$dashAvatarColors = ['#7C3AED', '#2563EB', '#059669'];
?>
<?php if (!empty($dashReviews)): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
  <h3 style="font-size:15px;margin:0;">⭐ What Our Users Say</h3>
  <a href="<?= SITE_URL ?>/pages/review.php" style="font-size:12px;color:var(--primary);font-weight:600;">See all →</a>
</div>
<?php foreach ($dashReviews as $i => $r):
    $color = $dashAvatarColors[$i % count($dashAvatarColors)];
    $initials = strtoupper(substr($r['display_name'], 0, 2));
?>
<div class="glass" style="padding:14px;border-radius:var(--radius);margin-bottom:10px;">
  <div style="display:flex;gap:10px;">
    <div style="width:34px;height:34px;border-radius:50%;background:<?= $color ?>;color:#fff;
                display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">
      <?= clean($initials) ?>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:13px;"><?= clean($r['display_name']) ?></div>
      <div style="color:#f59e0b;font-size:12px;margin:2px 0 4px;"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
      <div style="font-size:12px;color:var(--text2);line-height:1.4;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= clean($r['message']) ?></div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<a href="<?= SITE_URL ?>/pages/review.php" class="btn btn-outline btn-sm btn-block" style="margin-bottom:16px;">Tap for More Reviews</a>
<?php endif; ?>





<!-- Recent orders Section Header -->
<?php if (!empty($recentOrders)): ?>
<div class="sec-hdr" style="display:flex;justify-content:space-between;align-items:center;margin:24px 0 12px 0;width:100%;font-family:sans-serif;">
  <span style="font-size:16px;font-weight:700;color:#1D1037;">Recent Orders</span>
  <a href="<?= SITE_URL ?>/pages/history.php" style="font-size:13px;font-weight:700;color:#7F56D9;text-decoration:none;">See all</a>
</div>

<?php foreach ($recentOrders as $o): ?>
<!-- Restructured Stream Layout Card -->
<div class="order-item" style="background:#FFFFFF;border:1px solid #F4EBFF;border-radius:18px;padding:14px 16px;box-shadow:0 4px 16px rgba(38,4,78,0.02);margin-bottom:12px;width:100%;font-family:sans-serif;display:flex;flex-direction:column;gap:12px;">
  
  <!-- Upper Section: Service Meta and Status Badge -->
  <div class="order-top" style="display:flex;justify-content:space-between;align-items:flex-start;width:100%;">
    <div style="display:flex;flex-direction:column;gap:3px;">
      <div class="order-title" style="font-size:14px;font-weight:700;color:#1D1037;"><?= clean($o['service_name']) ?></div>
      <div class="order-meta" style="font-family:monospace;font-size:12px;color:#667085;font-weight:600;letter-spacing:0.3px;"><?= clean($o['phone']) ?></div>
    </div>
    
    <!-- Transformed Minimal Pill Badges -->
    <span class="pill" style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:8px;text-transform:capitalize;line-height:1;background:<?= $o['status']==='RECEIVED' ? '#ECFDF3' : ($o['status']==='PENDING' ? '#FFFAEB' : '#F2F4F7') ?>;color:<?= $o['status']==='RECEIVED' ? '#12B76A' : ($o['status']==='PENDING' ? '#F79009' : '#667085') ?>;border:1px solid <?= $o['status']==='RECEIVED' ? '#D1FADF' : ($o['status']==='PENDING' ? '#FEF0C7' : '#EAECF0') ?>;">
      <?= ucfirst(strtolower($o['status'])) ?>
    </span>
  </div>
  
  <!-- Lower Section: Timestamp Metrics and Quick Action Elements -->
  <div style="display:flex;justify-content:space-between;align-items:center;width:100%;background:#FFFDFB;border-top:1px dashed #F4EBFF;padding-top:10px;">
    <!-- Timestamp field -->
    <span class="order-meta" style="font-size:12px;color:#667085;font-weight:500;"><?= date('d M, H:i', strtotime($o['created_at'])) ?></span>
    
    <div style="display:flex;align-items:center;gap:10px;">
      <!-- Total cost parameter -->
      <span class="order-amount" style="font-size:14px;font-weight:800;color:#1D1037;"><?= fmt_money((float)$o['amount_paid']) ?></span>
      
      <!-- Action Conditionals Grid Matrix -->
      <?php if ($o['status'] === 'PENDING'): ?>
      <a href="<?= SITE_URL ?>/pages/otp-active.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);color:#FFFFFF;text-decoration:none;font-size:12px;font-weight:700;padding:6px 16px;border-radius:10px;box-shadow:0 2px 6px rgba(38,4,78,0.15);">View</a>
      <?php elseif ($o['otp_code']): ?>
      <button onclick="copyText('<?= clean($o['otp_code']) ?>',this)" class="btn btn-outline btn-sm" style="font-family:monospace;background:#F4EBFF;border:1px solid #E9D7FE;color:#7F56D9;font-size:12px;font-weight:700;padding:6px 12px;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
        <?= clean($o['otp_code']) ?> <i class="fi fi-rr-copy" style="font-size:11px;"></i>
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- Empty State Section Layout -->
<?php else: ?>
<div class="empty" style="background:#FFFFFF;border:1px solid #F4EBFF;border-radius:24px;padding:32px 20px;text-align:center;box-shadow:0 8px 24px rgba(38,4,78,0.02);margin-top:14px;width:100%;font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
  <!--<div style="width:56px;height:56px;background:#F4EBFF;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#7F56D9;margin-bottom:2px;"><i class="fi fi-rr-sim" style="font-size:24px;line-height:1;"></i></div>-->
  <p class="text-muted" style="font-size:13px;color:#667085;font-weight:600;line-height:1.4;margin:0;">No orders found yet.<br><span style="color:#475467;font-weight:500;font-size:12px;">Buy your first logs and number!</span></p>
  <a href="<?= SITE_URL ?>/pages/logs.php" class="btn btn-primary mt-2" style="background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:700;padding:10px 20px;border-radius:14px;box-shadow:0 4px 12px rgba(38,4,78,0.15);display:inline-flex;align-items:center;gap:6px;margin-top:4px;">
    <i class="fi fi-rr-cart" style="font-size:13px;"></i> Browse Now
  </a>
</div>
<?php endif; ?>





<!-- Stats row -->
<!--<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">-->
<!--  <div class="glass" style="padding:12px;text-align:center;">-->
<!--    <div style="font-size:18px;font-weight:900;color:var(--primary);"><?= $totalOrders ?></div>-->
<!--    <div class="text-muted text-xs fw-700" style="margin-top:2px;">Total Orders</div>-->
<!--  </div>-->
<!--  <div class="glass" style="padding:12px;text-align:center;">-->
<!--    <div style="font-size:18px;font-weight:900;color:var(--success);"><?= fmt_money($totalSpent) ?></div>-->
<!--    <div class="text-muted text-xs fw-700" style="margin-top:2px;">Total Spent</div>-->
<!--  </div>-->
<!--  <div class="glass" style="padding:12px;text-align:center;">-->
<!--    <div style="font-size:18px;font-weight:900;color:var(--danger);"><?= $totalCancelled ?></div>-->
<!--    <div class="text-muted text-xs fw-700" style="margin-top:2px;">Cancelled</div>-->
<!--  </div>-->
</div>


<!-- Virtual Account Modal -->
<div class="modal-overlay <?= $showVaPopup ? 'open' : '' ?>" id="va-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>

    <div id="step-gen" style="text-align:center;">
      <div style="width:64px;height:64px;border-radius:50%;
                  background:var(--primary-light);
                  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="fi fi-rr-bank" style="font-size:26px;color:var(--primary);"></i>
      </div>
      <h3 class="modal-title" style="margin-bottom:8px;">Get Your Virtual Account</h3>
      <p class="text-muted text-sm" style="margin-bottom:8px;">
        Get a permanent bank account number to fund your wallet.<br>
        Money is converted to <strong>USD</strong> automatically.
      </p>
      <div style="background:var(--primary-light);border-radius:8px;padding:10px;margin-bottom:16px;font-size:12px;">
        💱 Rate: ₦<?= number_format($rate, 0) ?> = $1 USD
        &nbsp;·&nbsp; Min $<?= get_min_topup_usd() ?> &nbsp;·&nbsp; Max $<?= get_max_topup_usd() ?>
      </div>

      <?php if ($ppActive): ?>
      <button id="gen-btn-paymentpoint-palmpay" onclick="generateVA('paymentpoint','palmpay')" class="btn btn-primary btn-block" style="margin-bottom:10px;">
        <i class="fi fi-rr-bank"></i> Generate via PalmPay
      </button>
      <!--<button id="gen-btn-paymentpoint-opay" onclick="generateVA('paymentpoint','opay')" class="btn btn-primary btn-block" style="margin-bottom:10px;">-->
      <!--  <i class="fi fi-rr-bank"></i> Generate via OPay-->
      <!--</button>-->
      <?php endif; ?>

      <?php if ($ncActive): ?>
      <button id="gen-btn-ncwallet" onclick="generateVA('ncwallet')" class="btn btn-outline btn-block" style="margin-bottom:10px;">
        <i class="fi fi-rr-bank"></i> Generate via NCWallet (Palmpay)
      </button>
      <?php endif; ?>

      <?php if ($psActive): ?>
      <button id="gen-btn-paystack" onclick="generateVA('paystack')" class="btn btn-outline btn-block" style="margin-bottom:10px;">
        <i class="fi fi-rr-credit-card"></i> Generate via Paystack (Wema Bank)
      </button>
      <?php endif; ?>

      <?php if (!$ppActive && !$ncActive && !$psActive): ?>
      <div class="alert alert-warning">
        <i class="fi fi-rr-triangle-warning"></i>
        <span>Virtual accounts are not configured. Contact admin.</span>
      </div>
      <?php endif; ?>

      <button onclick="closeModal('va-modal')" class="btn btn-ghost btn-block mt-1">Skip for now</button>
    </div>

    <div id="step-done" style="display:none;text-align:center;">
      <div style="width:64px;height:64px;border-radius:50%;background:#d1fae5;
                  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="fi fi-rr-check" style="font-size:28px;color:var(--success);"></i>
      </div>
      <h3 class="modal-title">Account Created! 🎉</h3>
      <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:16px;margin:14px 0;text-align:left;">
        <div class="text-muted text-xs fw-700" style="margin-bottom:4px;">ACCOUNT NUMBER</div>
        <div style="font-size:22px;font-weight:900;letter-spacing:2px;color:var(--primary);" id="va-num">—</div>
        <div class="text-muted text-sm mt-1" id="va-bank">—</div>
        <div class="text-muted text-sm" id="va-name">—</div>
      </div>
      <p class="text-muted text-sm" style="margin-bottom:16px;">
        Transfer from any Nigerian bank. Your wallet is credited in <strong>USD</strong> automatically.
      </p>
      <button onclick="location.reload()" class="btn btn-primary btn-block">Done</button>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
