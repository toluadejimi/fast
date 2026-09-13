<?php
// ============================================================
//  DonnieSMS OTP – includes/footer.php  (UPDATED – ErifyLogs)
// ============================================================
$cur = basename($_SERVER['PHP_SELF']);
?>

</div><!-- /.page-wrap -->

<!-- Floating Support Buttons Container -->
<div style="position: fixed; bottom: 120px; right: 40px; display: flex; flex-direction: column; gap: 15px; z-index: 999;">

  <!-- Telegram Button -->
  <a href="https://t.me/emzysmsverify" 
     title="Telegram Support" 
     target="_blank" 
     rel="noopener noreferrer"
     style="width: 60px; height: 60px; background-color: #8a2be2; color: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); text-decoration: none; transition: transform 0.2s;"
     onmouseover="this.style.transform='scale(1.1)'" 
     onmouseout="this.style.transform='scale(1)'">
    <!-- Inline SVG Telegram Logo -->
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="m22 2-7 20-4-9-9-4Z"/>
      <path d="M22 2 11 13"/>
    </svg>
  </a>

  <!-- WhatsApp Button -->
  <a href="https://whatsapp.com/channel/0029VbDgxW52P59n2QDLU61F" 
     title="WhatsApp Support" 
     target="_blank" 
     rel="noopener noreferrer"
     style="width: 60px; height: 60px; background-color: #6f42c1; color: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); text-decoration: none; transition: transform 0.2s;"
     onmouseover="this.style.transform='scale(1.1)'" 
     onmouseout="this.style.transform='scale(1)'">
    <!-- Inline SVG WhatsApp Style Chat Logo -->
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
    </svg>
  </a>

</div>






<!-- Bottom Navigation: Home | Numbers | Buy Logs | Messages -->
<nav class="bottom-nav" style="position:fixed;bottom:15px;left:15px;right:15px;background:#FFF;display:flex;justify-content:space-around;align-items:center;padding:10px 5px;border-radius:25px;box-shadow:0 8px 30px rgba(38,4,78,0.12);border-top:2px solid #7F56D9;z-index:999;">

  <a href="<?= SITE_URL ?>/pages/dashboard.php" class="<?= $cur === 'dashboard.php' ? 'active' : '' ?>" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;font-size:11px;font-family:sans-serif;gap:4px;color:<?= $cur === 'dashboard.php' ? '#26044E' : '#475467' ?>;font-weight:<?= $cur === 'dashboard.php' ? '600' : '400' ?>;">
    <i class="fi fi-<?= $cur === 'dashboard.php' ? 'sr' : 'rr' ?>-house-blank" style="font-size:20px;"></i>
    <span>Home</span>
  </a>

  <a href="<?= SITE_URL ?>/pages/numbers.php" class="<?= $cur === 'numbers.php' ? 'active' : '' ?>" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;font-size:11px;font-family:sans-serif;gap:4px;color:<?= $cur === 'numbers.php' ? '#26044E' : '#475467' ?>;font-weight:<?= $cur === 'numbers.php' ? '600' : '400' ?>;">
    <i class="fi fi-<?= $cur === 'numbers.php' ? 'sr' : 'rr' ?>-sim-card" style="font-size:20px;"></i>
    <span>Numbers</span>
  </a>

  <a href="<?= SITE_URL ?>/pages/logs.php" class="<?= $cur === 'logs.php' ? 'active' : '' ?>" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;font-size:11px;font-family:sans-serif;gap:4px;color:<?= $cur === 'logs.php' ? '#26044E' : '#475467' ?>;font-weight:<?= $cur === 'logs.php' ? '600' : '400' ?>;">
    <i class="fi fi-<?= $cur === 'logs.php' ? 'sr' : 'rr' ?>-cart-arrow-down" style="font-size:20px;"></i>
    <span>Buy Logs</span>
  </a>
  
   <a href="<?= SITE_URL ?>/pages/data.php" class="<?= $cur === 'data.php' ? 'active' : '' ?>" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;font-size:11px;font-family:sans-serif;gap:4px;color:<?= $cur === 'data.php' ? '#26044E' : '#475467' ?>;font-weight:<?= $cur === 'data.php' ? '600' : '400' ?>;">
    <i class="fi fi-<?= $cur === 'data.php' ? 'sr' : 'rr' ?>-smart-home" style="font-size:20px;"></i>
    <span>Buy Data</span>
  </a>

  <!--<a href="<?= SITE_URL ?>/pages/data.php" class="<?= in_array($cur, ['vtu-bills.php','airtime.php','data.php','cable.php','electricity.php','vtu-history.php']) ? 'active' : '' ?>" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;font-size:11px;font-family:sans-serif;gap:4px;color:<?= in_array($cur, ['vtu-bills.php','airtime.php','data.php','cable.php','electricity.php','vtu-history.php']) ? '#26044E' : '#475467' ?>;font-weight:<?= in_array($cur, ['vtu-bills.php','airtime.php','data.php','cable.php','electricity.php','vtu-history.php']) ? '600' : '400' ?>;">-->
  <!--  <i class="fi fi-<?= in_array($cur, ['vtu-bills.php','airtime.php','data.php','cable.php','electricity.php','vtu-history.php']) ? 'sr' : 'rr' ?>-smart-home" style="font-size:20px;"></i>-->
  <!--  <span>VTU</span>-->
  <!--</a>-->

  <a href="<?= SITE_URL ?>/pages/profile.php" class="<?= $cur === 'profile.php' ? 'active' : '' ?>" style="display:flex;flex-direction:column;align-items:center;text-decoration:none;font-size:11px;font-family:sans-serif;gap:4px;color:<?= $cur === 'profile.php' ? '#26044E' : '#475467' ?>;font-weight:<?= $cur === 'profile.php' ? '600' : '400' ?>;">
    <i class="fi fi-<?= $cur === 'profile.php' ? 'sr' : 'rr' ?>-id-card-clip-alt" style="font-size:20px;"></i>
    <span>Profile</span>
  </a>

</nav>


<!-- Bottom Navigation: Home | Numbers | Buy Logs | Messages -->
<!--<nav class="bottom-nav">-->

<!--  <a href="<?= SITE_URL ?>/pages/dashboard.php"-->
<!--     class="<?= $cur === 'dashboard.php' ? 'active' : '' ?>">-->
<!--    <i class="fi fi-<?= $cur === 'dashboard.php' ? 'sr' : 'rr' ?>-home"></i>-->
<!--    <span>Home</span>-->
<!--  </a>-->

<!--  <a href="<?= SITE_URL ?>/pages/numbers.php"-->
<!--     class="<?= $cur === 'numbers.php' ? 'active' : '' ?>">-->
<!--    <i class="fi fi-<?= $cur === 'numbers.php' ? 'sr' : 'rr' ?>-mobile"></i>-->
<!--    <span>Numbers</span>-->
<!--  </a>-->

<!--  <a href="<?= SITE_URL ?>/pages/logs.php"-->
<!--     class="<?= $cur === 'logs.php' ? 'active' : '' ?>">-->
<!--    <i class="fi fi-<?= $cur === 'logs.php' ? 'sr' : 'rr' ?>-shopping-bag"></i>-->
<!--    <span>Buy Logs</span>-->
<!--  </a>-->

<!--  <a href="<?= SITE_URL ?>/pages/vtu-bills.php"-->
<!--     class="<?= in_array($cur, ['vtu-bills.php','airtime.php','data.php','cable.php','electricity.php','vtu-history.php']) ? 'active' : '' ?>">-->
<!--    <i class="fi fi-<?= in_array($cur, ['vtu-bills.php','airtime.php','data.php','cable.php','electricity.php','vtu-history.php']) ? 'sr' : 'rr' ?>-bolt"></i>-->
<!--    <span>VTU</span>-->
<!--  </a>-->

  <!--<a href="<?= SITE_URL ?>/pages/social-boost.php"-->
  <!--   class="<?= $cur === 'social-boost.php' || $cur === 'social-orders.php' ? 'active' : '' ?>">-->
  <!--  <i class="fi fi-<?= ($cur === 'social-boost.php' || $cur === 'social-orders.php') ? 'sr' : 'rr' ?>-share"></i>-->
  <!--  <span>Boost</span>-->
  <!--</a>-->

  <!--<a href="<?= SITE_URL ?>/pages/notifications.php"-->
  <!--   class="<?= $cur === 'notifications.php' ? 'active' : '' ?>" style="position:relative;">-->
  <!--  <i class="fi fi-<?= $cur === 'notifications.php' ? 'sr' : 'rr' ?>-bell"></i>-->
  <!--  <?php if (!empty($unread) && $unread > 0): ?>-->
  <!--  <span class="notif-dot"></span>-->
  <!--  <?php endif; ?>-->
  <!--  <span>Messages</span>-->
  <!--</a>-->
  
  
<!--  <a href="<?= SITE_URL ?>/pages/profile.php"-->
<!--     class="<?= $cur === 'profile.php' ? 'active' : '' ?>">-->
<!--    <i class="fi fi-<?= $cur === 'profile.php' ? 'sr' : 'rr' ?>-user"></i>-->
<!--    <span>Profile</span>-->
<!--  </a>-->

<!--</nav>-->

<!-- Global PIN Modal -->
<div class="modal-overlay" id="pin-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <h3 class="modal-title">Enter PIN</h3>
    <p class="text-muted text-sm mb-2">Enter your 4-digit PIN to continue</p>
    <div class="form-group">
      <div class="input-wrap">
        <i class="fi fi-rr-lock"></i>
        <input type="password" id="global-pin" class="form-control" data-pin
               placeholder="● ● ● ●" maxlength="4" inputmode="numeric" autocomplete="off">
      </div>
    </div>
    <button onclick="closeModal('pin-modal')" class="btn btn-ghost btn-block">Cancel</button>
  </div>
</div>

<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
