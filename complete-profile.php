<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

if (!is_logged_in()) redirect(SITE_URL . '/login.php');

$user = current_user();

// Nothing to do if phone is already set
if (!empty($user['phone'])) redirect(SITE_URL . '/pages/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh.';
    } else {
        $phone   = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? 'Nigeria');

        if (!get_country_info($country)) {
            $errors[] = 'Please select a valid country.';
        } elseif (!validate_phone_for_country($phone, $country)) {
            $errors[] = 'Enter a valid phone number for ' . $country . ' (include the leading 0, no country code).';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $chk->execute([$phone, $user['id']]);
            if ($chk->fetch()) {
                $errors[] = 'This phone number is already registered to another account.';
            } else {
                $pdo->prepare("UPDATE users SET phone = ?, country = ? WHERE id = ?")->execute([$phone, $country, $user['id']]);
                redirect(SITE_URL . '/pages/dashboard.php');
            }
        }
    }
}

$siteName = get_setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Complete Your Profile – <?= clean($siteName) ?></title>
  <style><?= get_theme_css() ?></style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body style="padding-bottom:0;">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><?= get_logo_html() ?><p>One last step</p></div>
    <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($err) ?></span></div>
    <?php endforeach; ?>
    <div class="auth-box">
      <p style="font-size:14px;color:var(--text2);margin-top:0;">We need your phone number to finish setting up your account (used for wallet funding and support).</p>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Country</label>
          <div class="input-wrap">
            <i class="fi fi-rr-marker"></i>
            <select name="country" id="countrySelect" class="form-control" required>
              <?php foreach (get_country_list() as $cName => $cInfo): ?>
                <option value="<?= clean($cName) ?>" data-min="<?= (int)$cInfo[1] ?>" data-max="<?= (int)$cInfo[2] ?>"><?= clean($cName) ?> (<?= clean($cInfo[0]) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" id="phoneLabel">Phone Number</label>
          <div class="input-wrap">
            <i class="fi fi-rr-phone-call"></i>
            <input type="tel" name="phone" id="phoneInput" class="form-control" placeholder="08012345678" maxlength="11" inputmode="numeric" required autofocus>
          </div>
        </div>
        <script>
        (function() {
            var sel = document.getElementById('countrySelect');
            var input = document.getElementById('phoneInput');
            var label = document.getElementById('phoneLabel');
            function updateHint() {
                var opt = sel.options[sel.selectedIndex];
                label.textContent = opt.dataset.min === opt.dataset.max ? ('Phone Number (' + opt.dataset.min + ' digits)') : ('Phone Number (' + opt.dataset.min + '–' + opt.dataset.max + ' digits)');
                input.setAttribute('maxlength', opt.dataset.max);
            }
            sel.addEventListener('change', updateHint);
            updateHint();
        })();
        </script>
        <button type="submit" class="btn btn-primary btn-block">Continue</button>
      </form>
    </div>
  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>
