<?php
// ============================================================
//  DonnieSMS OTP – includes/footer.php
// ============================================================
$cur = basename($_SERVER['PHP_SELF']);
?>

</div><!-- /.page-wrap -->

<!-- Floating Support Button -->
<a href="<?= SITE_URL ?>/pages/support.php" class="fab" title="Support">
  <i class="fi fi-rr-headset"></i>
</a>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
  <a href="<?= SITE_URL ?>/pages/dashboard.php"
     class="<?= $cur === 'dashboard.php' ? 'active' : '' ?>">
    <i class="fi fi-<?= $cur === 'dashboard.php' ? 'sr' : 'rr' ?>-home"></i>
    <span>Home</span>
  </a>

  <a href="<?= SITE_URL ?>/pages/numbers.php"
     class="<?= $cur === 'numbers.php' ? 'active' : '' ?>">
    <i class="fi fi-<?= $cur === 'numbers.php' ? 'sr' : 'rr' ?>-mobile"></i>
    <span>Numbers</span>
  </a>

  <a href="<?= SITE_URL ?>/pages/notifications.php"
     class="<?= $cur === 'notifications.php' ? 'active' : '' ?>" style="position:relative;">
    <i class="fi fi-<?= $cur === 'notifications.php' ? 'sr' : 'rr' ?>-bell"></i>
    <?php if (!empty($unread) && $unread > 0): ?>
    <span class="notif-dot"></span>
    <?php endif; ?>
    <span>Messages</span>
  </a>

  <a href="<?= SITE_URL ?>/pages/profile.php"
     class="<?= $cur === 'profile.php' ? 'active' : '' ?>">
    <i class="fi fi-<?= $cur === 'profile.php' ? 'sr' : 'rr' ?>-user"></i>
    <span>Profile</span>
  </a>
</nav>

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
