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
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';
if (is_logged_in()) redirect(SITE_URL . '/pages/dashboard.php');

// Capture ?ref=CODE and carry it through the form (works even after a validation error)
$refCode = trim($_POST['ref_code'] ?? $_GET['ref'] ?? '');
if ($refCode !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    setcookie('pending_ref_code', $refCode, time() + 3600, '/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone'] ?? '');
        $country  = trim($_POST['country'] ?? 'Nigeria');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $pin      = $_POST['pin'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username))
            $errors[] = 'Username must be 3–20 characters (letters, numbers, underscore).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Enter a valid email address.';
        if (!get_country_info($country))
            $errors[] = 'Please select a valid country.';
        elseif (!validate_phone_for_country($phone, $country))
            $errors[] = 'Enter a valid phone number for ' . $country . ' (include the leading 0, no country code).';
        if (strlen($password) < 6)
            $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm)
            $errors[] = 'Passwords do not match.';
        if (!preg_match('/^\d{4}$/', $pin))
            $errors[] = 'PIN must be exactly 4 digits.';

        if (empty($errors)) {
            $s = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=? OR phone=?");
            $s->execute([$username, $email, $phone]);
            if ($s->fetch()) $errors[] = 'Username, email or phone number already registered.';
        }

        if (empty($errors)) {
            $referredBy = null;
            if ($refCode !== '') {
                $r = $pdo->prepare("SELECT id FROM users WHERE ref_code = ?");
                $r->execute([$refCode]);
                $referrer = $r->fetch();
                if ($referrer) $referredBy = (int) $referrer['id'];
            }

            $newRefCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $pdo->prepare("INSERT INTO users (username,email,phone,country,password,pin,ref_code,referred_by,last_login) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([
                    $username, $email, $phone, $country,
                    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                    password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]),
                    $newRefCode, $referredBy,
                ]);
            $userId = (int)$pdo->lastInsertId();
            $_SESSION['user_id']   = $userId;
            $_SESSION['username']  = $username;
            $_SESSION['show_va']   = true;
            notify($userId, 'Welcome!', 'Your account has been created successfully. Fund your wallet to get started.', '/pages/dashboard.php');

            if ($referredBy) {
                notify($referredBy, 'New Referral! 👋', $username . ' just signed up using your referral link.', '/pages/referrals.php');
            }

            // ── Send welcome email ───────────────────────────────────────
            $siteName_    = get_setting('site_name', SITE_NAME);
            $dashUrl_     = SITE_URL . '/pages/dashboard.php';
            $primaryClr_  = get_setting('primary_color', '#7C3AED');
            $safeUser_    = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $welcomeHtml_ = email_template("
                <h2 style=\"margin-top:0;color:#333;\">Welcome to {$siteName_}, {$safeUser_}! &#127881;</h2>
                <p>Your account has been created successfully. You're all set to start using <strong>{$siteName_}</strong>.</p>
                <p>Here's what you can do next:</p>
                <ul style=\"padding-left:20px;\">
                  <li>Fund your wallet to purchase OTP numbers</li>
                  <li>Browse available virtual numbers</li>
                  <li>Track all your transactions from your dashboard</li>
                </ul>
                <p style=\"text-align:center;margin:30px 0;\">
                  <a href=\"{$dashUrl_}\" style=\"background:{$primaryClr_};color:#ffffff;padding:13px 32px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;\">Go to Dashboard</a>
                </p>
                <p style=\"color:#999;font-size:13px;\">If you did not create this account, please contact support immediately.</p>
            ");
            send_email($email, 'Welcome to ' . $siteName_ . '!', $welcomeHtml_);
            // ────────────────────────────────────────────────────────────

            redirect(SITE_URL . '/pages/dashboard.php');
        }
    }
}
$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Register – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body style="padding-bottom:0;">
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <?= get_logo_html() ?>
      <p>Create your free account</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <i class="fi fi-rr-cross-circle"></i>
      <div><?= implode('<br>', array_map('clean', $errors)) ?></div>
    </div>
    <?php endif; ?>

    <div class="auth-box">
      <form method="POST" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="ref_code" value="<?= clean($refCode) ?>">

        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-wrap">
            <i class="fi fi-rr-user"></i>
            <input type="text" name="username" class="form-control"
                   placeholder="e.g. johndoe" maxlength="20"
                   value="<?= clean($_POST['username'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-wrap">
            <i class="fi fi-rr-envelope"></i>
            <input type="email" name="email" class="form-control"
                   placeholder="you@email.com"
                   value="<?= clean($_POST['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Country</label>
          <div class="input-wrap">
            <i class="fi fi-rr-marker"></i>
            <select name="country" id="countrySelect" class="form-control" required>
              <?php $selectedCountry = $_POST['country'] ?? 'Nigeria'; ?>
              <?php foreach (get_country_list() as $cName => $cInfo): ?>
                <option value="<?= clean($cName) ?>" data-dial="<?= clean($cInfo[0]) ?>" data-min="<?= (int)$cInfo[1] ?>" data-max="<?= (int)$cInfo[2] ?>"
                  <?= $selectedCountry === $cName ? 'selected' : '' ?>><?= clean($cName) ?> (<?= clean($cInfo[0]) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" id="phoneLabel">Phone Number</label>
          <div class="input-wrap">
            <i class="fi fi-rr-phone-call"></i>
            <input type="tel" name="phone" id="phoneInput" class="form-control"
                   placeholder="08012345678" maxlength="11" inputmode="numeric"
                   value="<?= clean($_POST['phone'] ?? '') ?>" required>
          </div>
          <div style="font-size:11px;color:var(--text3);margin-top:3px;">Enter with the leading 0, no country code.</div>
        </div>

        <script>
        (function() {
            var sel = document.getElementById('countrySelect');
            var input = document.getElementById('phoneInput');
            var label = document.getElementById('phoneLabel');
            function updateHint() {
                var opt = sel.options[sel.selectedIndex];
                var min = opt.dataset.min, max = opt.dataset.max;
                label.textContent = min === max ? ('Phone Number (' + min + ' digits)') : ('Phone Number (' + min + '–' + max + ' digits)');
                input.setAttribute('maxlength', max);
            }
            sel.addEventListener('change', updateHint);
            updateHint();
        })();
        </script>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <i class="fi fi-rr-lock"></i>
            <input type="password" name="password" class="form-control"
                   placeholder="At least 6 characters" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="input-wrap">
            <i class="fi fi-rr-lock"></i>
            <input type="password" name="confirm" class="form-control"
                   placeholder="Repeat password" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">4-Digit Transaction PIN</label>
          <div class="input-wrap">
            <i class="fi fi-rr-hashtag"></i>
            <input type="password" name="pin" class="form-control" data-pin
                   placeholder="● ● ● ●" maxlength="4"
                   inputmode="numeric" required autocomplete="off">
          </div>
          <div class="text-xs text-muted" style="margin-top:4px;">
            Used to confirm purchases. Keep it private.
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:4px;">
          <i class="fi fi-rr-user-add"></i> Create Account
        </button>
      </form>

      <div style="display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--text3);font-size:12px;">
        <div style="flex:1;height:1px;background:var(--border);"></div>OR<div style="flex:1;height:1px;background:var(--border);"></div>
      </div>

      <!--<a href="<?= SITE_URL ?>/google-login.php" class="btn btn-block" style="background:#fff;border:1px solid var(--border);color:#333;display:flex;align-items:center;justify-content:center;gap:10px;">-->
      <!--  <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20.4H24v7.2h11.3C33.7 32 29.3 35 24 35c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.1-5.1C33.6 5.1 29 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c3.1 0 5.9 1.2 8 3.1l5.1-5.1C33.6 5.1 29 3 24 3c-7.7 0-14.3 4.4-17.7 10.7z"/><path fill="#4CAF50" d="M24 45c5.2 0 9.9-1.7 13.5-4.7l-6.2-5.2C29.4 36.6 26.8 37.5 24 37.5c-5.3 0-9.7-3.4-11.3-8.1l-6.5 5C9.6 40.5 16.2 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20.4H24v7.2h11.3c-.8 2.3-2.3 4.3-4.2 5.7l6.2 5.2C40.9 35.5 45 30.3 45 24c0-1.2-.1-2.4-.4-3.5z"/></svg>-->
      <!--  Continue with Google-->
      <!--</a>-->
    </div>

    <div class="auth-footer">
      Already have an account?
      <a href="<?= SITE_URL ?>/login.php"><strong>Login</strong></a>
    </div>

    <!-- Theme toggle -->
    <!--<div style="text-align:center;margin-top:12px;">-->
    <!--  <button id="theme-toggle" class="theme-toggle" onclick="toggleTheme()" style="margin:auto;">-->
    <!--    <i class="fi fi-rr-moon"></i>-->
    <!--  </button>-->
    <!--</div>-->

  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
