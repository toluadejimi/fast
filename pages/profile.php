<!--
=============================================================
  COPYRIGHT & LICENSE NOTICE
=============================================================
  Script Name   : FemTech NG Web Script
  Owner         : FemTech NG
  Website       : https://femtech.top
  Contact       : +234 901 671 8588
=============================================================
  © 2025 - 2026 FemTech NG. All Rights Reserved.
=============================================================
-->


<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = 'Profile â€“ ' . get_setting('site_name', SITE_NAME);

require_once ROOT_PATH . '/includes/header.php';
require_once ROOT_PATH . '/includes/ncwallet.php';

$va    = get_user_va($user['id']);
$tab   = $_GET['tab'] ?? 'account';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $con = $_POST['confirm']       ?? '';
        if (!password_verify($old, $user['password']))   { $error = 'Current password is incorrect.'; }
        elseif (strlen($new) < 6)                         { $error = 'New password must be at least 6 characters.'; }
        elseif ($new !== $con)                            { $error = 'Passwords do not match.'; }
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]), $user['id']]);
            $success = 'Password updated successfully.';
            $user    = current_user();
        }
    } elseif (isset($_POST['change_pin'])) {
        $old = $_POST['old_pin'] ?? '';
        $new = $_POST['new_pin'] ?? '';
        if (!password_verify($old, $user['pin']))        { $error = 'Current PIN is incorrect.'; }
        elseif (!preg_match('/^\d{4}$/', $new))          { $error = 'PIN must be exactly 4 digits.'; }
        else {
            $pdo->prepare("UPDATE users SET pin=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost'=>10]), $user['id']]);
            $success = 'PIN updated successfully.';
        }
    }
}

// Order stats
$totalOrders = (int)$pdo->prepare("SELECT COUNT(*) FROM otp_orders WHERE user_id=?")->execute([$user['id']]) ? $pdo->query("SELECT COUNT(*) FROM otp_orders WHERE user_id={$user['id']}")->fetchColumn() : 0;
?>

<!-- Master Profile Card Container -->
<div class="profile-hdr-card" style="background:#FFFFFF;border:1px solid #F4EBFF;border-radius:24px;box-shadow:0 8px 30px rgba(38,4,78,0.04);margin:14px 0;width:100%;font-family:sans-serif;position:relative;overflow:hidden;display:flex;flex-direction:column;">
  
  <!-- 1. UPPER BANNER: Solid corporate brand banner fluid background -->
  <div style="height:110px;background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);width:100%;position:relative;"></div>

  <!-- 2. MIDDLE MANAGEMENT ROW: Overlapping circular layout profile framework -->
  <div style="padding:0 16px;margin-top:-34px;position:relative;z-index:5;width:100%;display:flex;justify-content:flex-start;">
    <!-- Overlapping Circular Profile Avatar with thick contrast boundary rings -->
    <div class="profile-avatar" style="width:68px;height:68px;background:#7F56D9;color:#FFFFFF;font-size:22px;font-weight:700;border-radius:50%;display:flex;align-items:center;justify-content:center;border:4px solid #FFFFFF;box-shadow:0 4px 12px rgba(0,0,0,0.06);">
      <?= strtoupper(substr($user['username'],0,1)) ?>
    </div>
  </div>

  <!-- 3. LOWER CONTENT AREA: Left aligned database field metrics -->
  <div style="padding:16px;display:flex;flex-direction:column;align-items:flex-start;gap:12px;width:100%;margin-top:4px;">
    
    
    <div style="display:flex;flex-direction:column;gap:2px;align-items:flex-start;">
      <div style="display:flex;align-items:center;gap:6px;">
        <span style="font-size:17px;font-weight:800;color:#1D1037;letter-spacing:-0.2px;"><?= clean($user['username']) ?></span>
        <?= verified_badge_html((int)($user['badge_tier'] ?? 0)) ?>
        
        <!-- Inline Membership Pill Tag Badge directly next to name -->
        <div style="background:#F4EBFF;border:1px solid #E9D7FE;border-radius:10px;padding:1px 6px;display:inline-flex;align-items:center;gap:3px;">
          <i class="fi fi-rr-user" style="font-size:8px;color:#7F56D9;"></i>
          <span style="font-size:9px;font-weight:700;color:#7F56D9;text-transform:capitalize;line-height:1;">Member</span>
        </div>
      </div>
      <!-- User profile reference username string line layer underneath -->
      <span style="font-size:12px;color:#667085;font-weight:600;">@<?= clean($user['username']) ?></span>
    </div>

    <!-- Dynamic Metrics Stack Fields Display System Panel Group -->
    <div style="background:#FFFDFB;border:1px solid #F4EBFF;border-radius:16px;padding:6px 14px;width:100%;box-shadow:inset 0 2px 8px rgba(38,4,78,0.01);">
      
      <!-- Core balance row tracking global switcher triggers cleanly -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F2F4F7;">
        <span style="font-size:12px;color:#667085;font-weight:600;display:flex;align-items:center;gap:5px;"><i class="fi fi-rr-wallet" style="color:#7F56D9;"></i> Balance</span>
        <span id="profile-bal-display" data-real-usd="<?= fmt_money((float)$user['balance']) ?>" data-real-ngn="<?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>" style="font-size:14px;font-weight:700;color:#1D1037;">
          <?= fmt_naira(usd_to_ngn((float)$user['balance'])) ?>
        </span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F2F4F7;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Email Address</span>
        <span style="font-size:13px;font-weight:600;color:#1D1037;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($user['email']) ?></span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F2F4F7;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Phone Number</span>
        <span style="font-size:13px;font-weight:600;color:#1D1037;"><?= clean($user['phone']) ?></span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F2F4F7;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Member Since</span>
        <span style="font-size:13px;font-weight:600;color:#475467;"><?= date('d M Y',strtotime($user['created_at'])) ?></span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:<?= $va ? '1px solid #F2F4F7' : 'none' ?>;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Total Orders</span>
        <span style="font-size:12px;font-weight:700;color:#7F56D9;background:#F4EBFF;padding:2px 8px;border-radius:8px;"><?= $totalOrders ?></span>
      </div>

      <!-- Conditional Virtual Bank Subsegment Display Module -->
      <?php if ($va): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F2F4F7;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Account Number</span>
        <span style="font-size:13px;font-weight:800;font-family:monospace;color:#1D1037;display:flex;align-items:center;gap:4px;">
          <?= clean($va['account_number']) ?>
          <button type="button" style="background:#FFF4ED;border:none;color:#7F56D9;width:22px;height:22px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:10px;padding:0;margin-left:2px;" onclick="copyText('<?= clean($va['account_number']) ?>',this)">
            <i class="fi fi-rr-copy"></i>
          </button>
        </span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F2F4F7;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Bank</span>
        <span style="font-size:13px;font-weight:600;color:#1D1037;text-transform:uppercase;"><?= clean($va['bank_name']) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0 0 0;">
        <span style="font-size:12px;color:#667085;font-weight:600;">Account Name</span>
        <span style="font-size:13px;font-weight:600;color:#475467;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($va['account_name']) ?></span>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>







<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<!-- Profile Tabs Segment Wrapper -->
<!--<div class="tabs-wrap" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:6px;border-radius:16px;display:flex;gap:6px;margin:14px 0;box-shadow:0 4px 16px rgba(38,4,78,0.02);width:100%;font-family:sans-serif;">-->
  
<!--  <a href="?tab=account" class="tab-btn <?= $tab==='account' ? 'active' : '' ?>" style="flex:1;text-align:center;padding:10px 12px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:700;transition:all 0.2s;color:<?= $tab==='account' ? '#FFFFFF' : '#475467' ?>;background:<?= $tab==='account' ? 'linear-gradient(135deg, #26044E 0%, #1D1037 100%)' : 'transparent' ?>;box-shadow:<?= $tab==='account' ? '0 4px 12px rgba(38,4,78,0.15)' : 'none' ?>;">-->
<!--    Account-->
<!--  </a>-->
  
<!--  <a href="?tab=security" class="tab-btn <?= $tab==='security' ? 'active' : '' ?>" style="flex:1;text-align:center;padding:10px 12px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:700;transition:all 0.2s;color:<?= $tab==='security' ? '#FFFFFF' : '#475467' ?>;background:<?= $tab==='security' ? 'linear-gradient(135deg, #26044E 0%, #1D1037 100%)' : 'transparent' ?>;box-shadow:<?= $tab==='security' ? '0 4px 12px rgba(38,4,78,0.15)' : 'none' ?>;">-->
<!--    Security-->
<!--  </a>-->

<!--</div>-->



<!-- Menu list Card Wrapper -->
<div class="glass settings-list" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:6px 16px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);margin-bottom:20px;width:100%;font-family:sans-serif;">
  
  <a href="<?= SITE_URL ?>/pages/fund-wallet.php" class="s-row" style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #F2F4F7;text-decoration:none;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="s-icon" style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-wallet" style="font-size:14px;"></i></div>
      <span class="s-label" style="font-size:14px;color:#1D1037;font-weight:600;">Fund Wallet</span>
    </div>
    <i class="fi fi-rr-angle-right s-right" style="color:#667085;font-size:12px;"></i>
  </a>

  <a href="<?= SITE_URL ?>/pages/history.php" class="s-row" style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #F2F4F7;text-decoration:none;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="s-icon" style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-time-past" style="font-size:14px;"></i></div>
      <span class="s-label" style="font-size:14px;color:#1D1037;font-weight:600;">Order History</span>
    </div>
    <i class="fi fi-rr-angle-right s-right" style="color:#667085;font-size:12px;"></i>
  </a>

  <a href="<?= SITE_URL ?>/pages/support.php" class="s-row" style="display:flex;align-items:center;justify-content:space-between;padding:14px 0 6px 0;text-decoration:none;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="s-icon" style="width:32px;height:32px;background:#F4EBFF;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#7F56D9;"><i class="fi fi-rr-headset" style="font-size:14px;"></i></div>
      <span class="s-label" style="font-size:14px;color:#1D1037;font-weight:600;">Support Connection</span>
    </div>
    <i class="fi fi-rr-angle-right s-right" style="color:#667085;font-size:12px;"></i>
  </a>

</div>




<!-- Profile Tabs Segment Wrapper -->
<div class="tabs-wrap" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:6px;border-radius:16px;display:flex;margin:14px 0;box-shadow:0 4px 16px rgba(38,4,78,0.02);width:100%;font-family:sans-serif;">
  
  <a href="?tab=security" class="tab-btn active" style="flex:1;text-align:center;padding:10px 12px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:700;transition:all 0.2s;color:#FFFFFF;background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);box-shadow:0 4px 12px rgba(38,4,78,0.15);display:flex;align-items:center;justify-content:center;gap:6px;">
    <i class="fi fi-rr-lock" style="font-size:13px;"></i>
    Profile Security Settings
  </a>

</div>



<!--<?php if ($tab === 'account'): ?>-->

<!-- Account info -->
<!--<div class="glass" style="padding:18px;margin-bottom:12px;">-->
<!--  <div style="font-weight:800;font-size:15px;margin-bottom:14px;">Account Info</div>-->
<!--  <?php foreach (['Username'=>$user['username'],'Email'=>$user['email'],'Phone'=>$user['phone'],'Member Since'=>date('d M Y',strtotime($user['created_at']))] as $lbl=>$val): ?>-->
<!--  <div class="d-flex justify-between align-center" style="padding:10px 0;border-bottom:1px solid var(--border);">-->
<!--    <span class="text-muted text-sm"><?= $lbl ?></span>-->
<!--    <span style="font-weight:600;"><?= clean($val) ?></span>-->
<!--  </div>-->
<!--  <?php endforeach; ?>-->
<!--  <div class="d-flex justify-between align-center" style="padding:10px 0;">-->
<!--    <span class="text-muted text-sm">Total Orders</span>-->
<!--    <span style="font-weight:600;"><?= $totalOrders ?></span>-->
<!--  </div>-->
<!--</div>-->

<!-- Virtual account -->
<!--<?php if ($va): ?>-->
<!--<div class="glass" style="padding:18px;margin-bottom:12px;">-->
<!--  <div style="font-weight:800;font-size:15px;margin-bottom:14px;">Virtual Account</div>-->
<!--  <div class="d-flex justify-between align-center" style="padding:8px 0;border-bottom:1px solid var(--border);">-->
<!--    <span class="text-muted text-sm">Account Number</span>-->
<!--    <span style="font-weight:800;font-family:monospace;letter-spacing:1px;">-->
<!--      <?= clean($va['account_number']) ?>-->
<!--      <button class="copy-btn" style="color:var(--primary);" onclick="copyText('<?= clean($va['account_number']) ?>',this)">-->
<!--        <i class="fi fi-rr-copy"></i>-->
<!--      </button>-->
<!--    </span>-->
<!--  </div>-->
<!--  <div class="d-flex justify-between" style="padding:8px 0;border-bottom:1px solid var(--border);">-->
<!--    <span class="text-muted text-sm">Bank</span>-->
<!--    <span style="font-weight:600;"><?= clean($va['bank_name']) ?></span>-->
<!--  </div>-->
<!--  <div class="d-flex justify-between" style="padding:8px 0;">-->
<!--    <span class="text-muted text-sm">Account Name</span>-->
<!--    <span style="font-weight:600;"><?= clean($va['account_name']) ?></span>-->
<!--  </div>-->
<!--</div>-->
<!--<?php endif; ?>-->





<!-- Menu list -->
<div class="glass settings-list" style="padding:0 18px;margin-bottom:12px;">
    
<!--       <a href="-->
<!--https://wa.me/2348127093593?text=Hello%20ðŸ‘‹%20I%20need%20logs%0AMessage%20from%20DonnieSMS" class="s-row" style="text-decoration:none;">-->
<!--    <div class="s-icon"><i class="fi fi-sr-shopping-cart-add"></i></div>-->
<!--    <span class="s-label">Buy All Social Media Logs</span>-->
<!--    <i class="fi fi-rr-angle-right s-right"></i>-->
<!--  </a>-->
<!--    <a href="-->
<!--https://wa.me/2348127093593?text=Hello%20👋%20I%20need%20ESIM%0AMessage%20from%20DonnieSMS" class="s-row" style="text-decoration:none;">-->
<!--    <div class="s-icon"><i class="fi fi-rr-sim-card"></i></div>-->
<!--    <span class="s-label">Order ESim & Physical Sim</span>-->
<!--    <i class="fi fi-rr-angle-right s-right"></i>-->
<!--  </a>-->
    
<!--    <a href="-->
<!--https://whatsapp.com/channel/0029VbBojMD0bIdu5igVM42Z" class="s-row" style="text-decoration:none;">-->
<!--    <div class="s-icon"><i class="fi fi-rr-link-slash-alt"></i></div>-->
<!--    <span class="s-label">Join Channel</span>-->
<!--    <i class="fi fi-rr-angle-right s-right"></i>-->
<!--  </a>-->
  
  
  <!--<a href="<?= SITE_URL ?>/pages/fund-wallet.php" class="s-row" style="text-decoration:none;">-->
  <!--  <div class="s-icon"><i class="fi fi-rr-wallet"></i></div>-->
  <!--  <span class="s-label">Fund Wallet</span>-->
  <!--  <i class="fi fi-rr-angle-right s-right"></i>-->
  <!--</a>-->
  <!--<a href="<?= SITE_URL ?>/pages/history.php" class="s-row" style="text-decoration:none;">-->
  <!--  <div class="s-icon"><i class="fi fi-rr-time-past"></i></div>-->
  <!--  <span class="s-label">Order History</span>-->
  <!--  <i class="fi fi-rr-angle-right s-right"></i>-->
  <!--</a>-->
  <!--<a href="<?= SITE_URL ?>/pages/referrals.php" class="s-row" style="text-decoration:none;">-->
  <!--  <div class="s-icon"><i class="fi fi-rr-users-alt"></i></div>-->
  <!--  <span class="s-label">Refer &amp; Earn</span>-->
  <!--  <i class="fi fi-rr-angle-right s-right"></i>-->
  <!--</a>-->
  <!--<a href="<?= SITE_URL ?>/pages/support.php" class="s-row" style="text-decoration:none;">-->
  <!--  <div class="s-icon"><i class="fi fi-rr-headset"></i></div>-->
  <!--  <span class="s-label">Support</span>-->
  <!--  <i class="fi fi-rr-angle-right s-right"></i>-->
  <!--</a>-->
  <!--<div class="s-row" style="cursor:pointer;" onclick="toggleTheme()">-->
  <!--  <div class="s-icon"><i class="fi fi-rr-moon"></i></div>-->
  <!--  <span class="s-label">Toggle Dark Mode</span>-->
  <!--  <i class="fi fi-rr-angle-right s-right"></i>-->
  <!--</div>-->
  <a href="/logout.php" class="s-row" style="text-decoration:none;color:var(--danger)!important;">
    <div class="s-icon" style="background:rgba(220,38,38,.1);">
      <i class="fi fi-rr-exit" style="color:var(--danger);"></i>
    </div>
    <span class="s-label" style="color:var(--danger);">Logout</span>
    <i class="fi fi-rr-angle-right s-right" style="color:var(--danger);"></i>
  </a>
</div>

<?php elseif ($tab === 'security'): ?>

<!-- Change Password -->
<div class="glass" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:20px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);margin-bottom:16px;width:100%;font-family:sans-serif;">
  <div style="font-weight:800;font-size:16px;color:#1D1037;margin-bottom:18px;">Change Password</div>
  <form method="POST">
    <?= csrf_field() ?>
    
    <div class="form-group" style="margin-bottom:14px;display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Current Password</label>
      <div class="input-wrap" style="position:relative;display:flex;align-items:center;width:100%;">
        <i class="fi fi-rr-lock" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="password" name="old_password" class="form-control" placeholder="Current password" required style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;outline:none;font-weight:500;">
      </div>
    </div>
    
    <div class="form-group" style="margin-bottom:14px;display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">New Password</label>
      <div class="input-wrap" style="position:relative;display:flex;align-items:center;width:100%;">
        <i class="fi fi-rr-lock" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;outline:none;font-weight:500;">
      </div>
    </div>
    
    <div class="form-group" style="margin-bottom:20px;display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Confirm New Password</label>
      <div class="input-wrap" style="position:relative;display:flex;align-items:center;width:100%;">
        <i class="fi fi-rr-lock" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="password" name="confirm" class="form-control" placeholder="Repeat password" required style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;outline:none;font-weight:500;">
      </div>
    </div>
    
    <button type="submit" name="change_password" class="btn btn-primary btn-block" style="width:100%;background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);color:#FFFFFF;border:none;padding:12px;border-radius:14px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(38,4,78,0.15);transition:all 0.2s;">Update Password</button>
  </form>
</div>

<!-- Change PIN -->
<div class="glass" style="background:#FFFFFF;border:1px solid #F4EBFF;padding:20px;border-radius:24px;box-shadow:0 8px 24px rgba(38,4,78,0.03);width:100%;font-family:sans-serif;">
  <div style="font-weight:800;font-size:16px;color:#1D1037;margin-bottom:18px;">Change PIN</div>
  <form method="POST">
    <?= csrf_field() ?>
    
    <div class="form-group" style="margin-bottom:14px;display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">Current PIN</label>
      <div class="input-wrap" style="position:relative;display:flex;align-items:center;width:100%;">
        <i class="fi fi-rr-hashtag" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="password" name="old_pin" class="form-control" data-pin placeholder="Current 4-digit PIN" maxlength="4" inputmode="numeric" required autocomplete="off" style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;outline:none;font-weight:500;">
      </div>
    </div>
    
    <div class="form-group" style="margin-bottom:20px;display:flex;flex-direction:column;gap:6px;">
      <label class="form-label" style="font-size:12px;font-weight:700;color:#475467;">New PIN</label>
      <div class="input-wrap" style="position:relative;display:flex;align-items:center;width:100%;">
        <i class="fi fi-rr-hashtag" style="position:absolute;left:14px;color:#7F56D9;font-size:14px;"></i>
        <input type="password" name="new_pin" class="form-control" data-pin placeholder="New 4-digit PIN" maxlength="4" inputmode="numeric" required autocomplete="off" style="width:100%;padding:12px 14px 12px 38px;background:#FFF9F5;border:1px solid #E9D7FE;border-radius:12px;font-size:14px;color:#1D1037;font-family:inherit;outline:none;font-weight:500;">
      </div>
    </div>
    
    <button type="submit" name="change_pin" class="btn btn-primary btn-block" style="width:100%;background:linear-gradient(135deg, #26044E 0%, #1D1037 100%);color:#FFFFFF;border:none;padding:12px;border-radius:14px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(38,4,78,0.15);transition:all 0.2s;">Update PIN</button>
  </form>
  
  <div style="text-align:center;margin-top:14px;">
    <a href="<?= SITE_URL ?>/forgot-pin.php" style="font-size:12px;color:#667085;font-weight:600;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='#7F56D9'" onmouseout="this.style.color='#667085'">Forgot your PIN? Reset via email</a>
  </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
