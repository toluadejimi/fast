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
require_once ROOT_PATH . '/includes/fivesim.php';
require_once ROOT_PATH . '/includes/verifysms.php';
require_once ROOT_PATH . '/includes/smsman.php';
require_once ROOT_PATH . '/includes/otpsuite.php';
require_once ROOT_PATH . '/includes/logsplug.php';
$pageTitle = 'Buy OTP Number';
require_once ROOT_PATH . '/includes/header.php';

$error  = '';
$markup = (float)get_setting('sms_markup_pct', '20');
$expiry = (int)get_setting('otp_expiry_minutes', '20');
$rate   = get_usd_to_ngn_rate(); // live rate (cached 10 min), used for the NGN/USD price toggle below

// ── Warning popup (admin-set) ─────────────────────────────────
$warningMsg = get_setting('numbers_warning_message', '');

// ── Provider config ───────────────────────────────────────────
$providers = array(
    '1' => array(
        'key'     => 'fivesim',
        'label'   => get_setting('provider1_label', '5sim'),
        'enabled' => get_setting('provider1_enabled', '1') === '1',
        'icon'    => 'fi-sr-cloud-download-alt',
    ),
    '2' => array(
        'key'     => 'verifysms',
        'label'   => get_setting('provider2_label', 'VerifySMS'),
        'enabled' => get_setting('provider2_enabled', '1') === '1',
        'icon'    => 'fi-rr-cloud',
    ),
    // '3' => array(
    //     'key'     => 'smsman',
    //     'label'   => get_setting('provider3_label', 'SMS-Man'),
    //     'enabled' => get_setting('provider3_enabled', '0') === '1',
    //     'icon'    => 'fi-rr-cloud',
    // ),
    // '4' => array(
    //     'key'     => 'otpsuite',
    //     'label'   => get_setting('provider4_label', 'USA WhatsApp'),
    //     'enabled' => get_setting('provider4_enabled', '1') === '1',
    //     'icon'    => 'fi-rr-cloud',
    // ),
    '5' => array(
        'key'     => 'logsplug',
        'label'   => get_setting('provider5_label', 'Saver 3'),
        'enabled' => get_setting('provider5_enabled', '1') === '1',
        'icon'    => 'fi-rr-cloud',
    ),
);

// ── Active provider ───────────────────────────────────────────
// LogsPlug (5) is the main/default provider; others are optional fallbacks.
$chosenProvider = clean($_GET['provider'] ?? $_POST['provider_key'] ?? '');
if (!isset($providers[$chosenProvider]) || !$providers[$chosenProvider]['enabled']) {
    $priorityOrder = array('5', '1', '2', '3', '4');
    $chosenProvider = '';
    foreach ($priorityOrder as $num) {
        if (isset($providers[$num]) && $providers[$num]['enabled']) { $chosenProvider = $num; break; }
    }
}
$activeProvider = $providers[$chosenProvider] ?? array('key' => '', 'label' => 'No provider available', 'enabled' => false);
$providerKey    = $activeProvider['key'];

// ── Helper ────────────────────────────────────────────────────
function safe_upper($val, $fallback = '') {
    if (is_array($val)) {
        foreach (array('iso','name','code',0) as $k) {
            if (isset($val[$k]) && is_string($val[$k]) && $val[$k] !== '') {
                return strtoupper($val[$k]);
            }
        }
        return strtoupper($fallback);
    }
    return strtoupper((string)$val);
}

// ── Country selection ─────────────────────────────────────────
$selCountry = trim(clean($_GET['country'] ?? 'any'));
if ($selCountry === '') $selCountry = 'any';

// ── LogsPlug sub-server selection (server1 / server2 / server3) ─
$lpServersEnabled = array(
    'server1' => get_setting('logsplug_server1_enabled', '1') === '1',
    'server2' => get_setting('logsplug_server2_enabled', '1') === '1',
    'server3' => get_setting('logsplug_server3_enabled', '1') === '1',
);
$selLpServer = clean($_GET['lp_server'] ?? '');
if (!isset($lpServersEnabled[$selLpServer]) || !$lpServersEnabled[$selLpServer]) {
    $selLpServer = '';
    foreach (array('server1', 'server2', 'server3') as $s) {
        if ($lpServersEnabled[$s]) { $selLpServer = $s; break; }
    }
}

// ── Load services from chosen provider ───────────────────────
$countries = array();
$services  = array();
$apiError  = '';

try {

    if ($providerKey === 'fivesim') {

        $sim       = new FiveSim();
        $countries = $sim->getCountries();
        $raw       = $sim->getServicesForCountry($selCountry, $markup);

        // Filter out any that slipped through with price=0
        $services = array_values(array_filter($raw, function($s) {
            return isset($s['price_display']) && (float)$s['price_display'] > 0;
        }));

    } elseif ($providerKey === 'verifysms') {

        $sim       = new VerifySMS();
        $countries = $sim->getCountries();
        $raw       = $sim->getServicesForCountry('us', $markup);

        // Filter zero-price entries — VerifySMS sometimes returns
        // cost=0 for services that are technically listed but inactive
        $services = array_values(array_filter($raw, function($s) {
            return isset($s['price_display']) && (float)$s['price_display'] > 0;
        }));

    } elseif ($providerKey === 'smsman') {

        $sim          = new SmsMan();
        $rawCountries = $sim->getCountries();
        foreach ($rawCountries as $ct) {
            $countries[(string)$ct['id']] = array(
                'iso'  => $ct['code'] ? $ct['code'] : strtoupper(substr($ct['name'], 0, 2)),
                'name' => $ct['name'],
            );
        }

        // Russia=0, default to 0 (most stock)
        $smsCid = ($selCountry !== 'any' && $selCountry !== '' && is_numeric($selCountry))
            ? (int)$selCountry
            : 0;

        // Test API connection first — expose error clearly
        $testBal = $sim->getBalance();
        if ($testBal === 0.0) {
            // Balance=0 could be real zero OR bad key — check applications
        }
        $raw = $sim->getServicesForCountry($smsCid, $markup);
        if (empty($raw)) {
            // Try to give a useful error — check API key
            $apps = $sim->getApplications();
            if (empty($apps)) {
                $apiError = 'SMS-Man: Cannot load services. Check API key in Admin → Settings.';
            } else {
                $apiError = 'SMS-Man: No services found for this country. Try selecting a different country.';
            }
        }

        $services = array_values(array_filter($raw, function($s) {
            return isset($s['price_display']) && (float)$s['price_display'] > 0;
        }));

    } elseif ($providerKey === 'otpsuite') {

        $sim       = new OtpSuite();
        $countries = $sim->getCountries();

        $otpCountry = ($selCountry !== 'any' && $selCountry !== '') ? strtoupper($selCountry) : 'US';
        $raw        = $sim->getServicesForCountry($otpCountry, $markup);

        if (empty($raw)) {
            $apiError = 'USA WhatsApp: Cannot load services. Check API key in Admin → Settings.';
        }

        $services = array_values(array_filter($raw, function($s) {
            return isset($s['price_display']) && (float)$s['price_display'] > 0;
        }));

    } elseif ($providerKey === 'logsplug') {

        $sim = new LogsPlug();

        if ($selLpServer === '') {
            $raw = array();
            $apiError = $activeProvider['label'] . ': All servers are currently disabled. Enable at least one in Admin → Settings.';

        } elseif ($selLpServer === 'server2') {
            // TWO_STEP: countries first, then services for the chosen country
            $countries = $sim->getCountries('server2');

            $lpCountry = ($selCountry !== 'any' && $selCountry !== '') ? $selCountry : '';
            if ($lpCountry === '' && !empty($countries)) {
                // Prefer US (most likely to have stock) over an arbitrary first entry
                $lpCountry = '';
                foreach ($countries as $cid => $cinfo) {
                    if (strtoupper($cinfo['iso'] ?? '') === 'US' || stripos($cinfo['name'] ?? '', 'united states') !== false) {
                        $lpCountry = $cid;
                        break;
                    }
                }
                if ($lpCountry === '') $lpCountry = array_key_first($countries);
            }
            $selCountry = $lpCountry; // keep the dropdown's "selected" option in sync with what actually loaded

            $raw = ($lpCountry !== '') ? $sim->getServicesForServer('server2', $lpCountry, $markup) : array();

            if (empty($raw)) {
                $detail = $sim->getLastError();
                if (stripos($detail, 'no services available') !== false) {
                    // Not a real fault — this specific country just has zero stock right now
                    $apiError = $activeProvider['label'] . ' (Server 2): No services currently in stock for this country. Try a different country above.';
                } else {
                    $detail = $detail ? ' — ' . str_ireplace('logsplug', 'provider', $detail) : '';
                    $apiError = $activeProvider['label'] . ' (Server 2): Cannot load services.' . $detail;
                }
            }

        } else {
            // ONE_STEP: server1 or server3, straight to services
            $countries = array();
            $raw       = $sim->getServicesForServer($selLpServer, null, $markup);

            if (empty($raw)) {
                $detail = $sim->getLastError();
                $detail = $detail ? ' — ' . str_ireplace('logsplug', 'provider', $detail) : '';
                $apiError = $activeProvider['label'] . ' (' . ($selLpServer === 'server3' ? 'Server 3' : 'Server 1') . '): Cannot load services.' . $detail;
            }
        }

        $services = array_values(array_filter($raw, function($s) {
            return isset($s['price_display']) && (float)$s['price_display'] > 0;
        }));

    } else {
        $apiError = $activeProvider['label'] . ' is coming soon.';
    }

} catch (Throwable $e) {
    $apiError = $e->getMessage();
}

// ── Handle purchase ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy'])) {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {

        $pKey     = clean($_POST['provider_key'] ?? '');
        $slug     = clean($_POST['slug']    ?? '');
        $country  = clean($_POST['country'] ?? 'any');
        $price    = (float)($_POST['price'] ?? 0);
        $pin      = $_POST['pin'] ?? '';
        $lpServer = clean($_POST['lp_server'] ?? 'server1');
        if (!in_array($lpServer, array('server1', 'server2', 'server3'))) $lpServer = 'server1';

        if (empty($user['pin'])) {
            $error = 'You must set a PIN first. Go to Profile → Set PIN.';
        } elseif (!password_verify($pin, $user['pin'])) {
            $error = 'Incorrect PIN. Please try again.';
        } elseif ((float)$user['balance'] < $price) {
            $error = 'Insufficient balance. Please fund your wallet.';
        } elseif ($slug === '') {
            $error = 'Invalid service selected.';
        } elseif (!isset($providers[$pKey]) || !$providers[$pKey]['enabled']) {
            $error = 'Selected provider is unavailable.';
        } elseif ($providers[$pKey]['key'] === 'logsplug' &&
                  get_setting('logsplug_' . $lpServer . '_enabled', '1') !== '1') {
            $error = 'Selected server is currently disabled.';
        } else {

            $buyKey = $providers[$pKey]['key'];

            try {
                if ($buyKey === 'fivesim')      $buyerSim = new FiveSim();
                elseif ($buyKey === 'verifysms') $buyerSim = new VerifySMS();
                elseif ($buyKey === 'smsman')    $buyerSim = new SmsMan();
                elseif ($buyKey === 'otpsuite')  $buyerSim = new OtpSuite();
                elseif ($buyKey === 'logsplug')  $buyerSim = new LogsPlug();
                else throw new Exception($providers[$pKey]['label'] . ' not yet available.');

                if ($buyKey === 'logsplug') {
                    $result = $buyerSim->buyNumber($lpServer, $country, $slug);
                } else {
                    $result = $buyerSim->buyNumber($country, 'any', $slug);
                }

            } catch (Throwable $e) {
                $result = array('success' => false, 'message' => 'Error: ' . $e->getMessage());
            }

            if ($result['success']) {

                $ref     = gen_ref('OTP');
                $expires = date('Y-m-d H:i:s', time() + $expiry * 60);
                $svcName = ucwords(str_replace(array('_','-'), ' ', $slug));

                if ($buyKey === 'verifysms' && !empty($result['service'])) {
                    $svcName = $result['service'];
                }
                if ($buyKey === 'smsman' || $buyKey === 'otpsuite' || $buyKey === 'logsplug') {
                    foreach ($services as $sv) {
                        if ($sv['slug'] === $slug) { $svcName = $sv['name']; break; }
                    }
                }

                // ── PRICE SECURITY ───────────────────────────────────
                // RULE: always use the API buy-response price as the TRUE cost,
                // then apply markup on top. Never trust raw $_POST['price'].
                //
                // For SMS-Man, buyNumber() rarely echoes a price back.
                // In that case we use $_POST['price'] which equals the
                // price_display the user saw — it already has markup baked in,
                // so we use it directly WITHOUT re-applying markup.
                $realCostUSD = (float)($result['price'] ?? 0);

                if ($buyKey === 'smsman') {
                    if ($realCostUSD > 0) {
                        // Response included a price — treat as raw cost, apply markup
                        $chargePrice = round($realCostUSD * (1 + $markup / 100), 4);
                    } else {
                        // No price in response — use the displayed price as-is
                        // (markup was already applied in getServicesForCountry())
                        $chargePrice = round($price, 4);
                    }
                } else {
                    // 5sim + VerifySMS + OtpSuite + LogsPlug: API always returns real raw cost — apply markup
                    $chargePrice = ($realCostUSD > 0)
                        ? round($realCostUSD * (1 + $markup / 100), 4)
                        : round($price, 4); // last-resort fallback
                }

                // Final sanity: never charge $0
                if ($chargePrice <= 0) {
                    $error = 'Could not determine price. Please try again.';
                } else {

                    wallet_debit($user['id'], $chargePrice, 'OTP – ' . $svcName, $ref);

                    track_spend($user['id'], $chargePrice);

                    $pdo->prepare("INSERT INTO otp_orders
                        (user_id, order_id, service, service_name, country, operator, phone, amount_paid, status, expires_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)")
                        ->execute(array(
                            $user['id'],
                            $result['id'],
                            $slug,
                            $svcName,
                            $result['country'],
                            $buyKey,
                            $result['phone'],
                            $chargePrice,
                            $expires,
                        ));

                    $orderId = $pdo->lastInsertId();

                    notify($user['id'], 'OTP Number Ordered',
                        "Number {$result['phone']} ordered for {$svcName}",
                        '/pages/otp-active.php?id=' . $orderId);

                    $redirectUrl = SITE_URL . '/pages/otp-active.php?id=' . $orderId;
                    if (!headers_sent()) {
                        redirect($redirectUrl);
                    } else {
                        // Page HTML already flushed (large service list, etc.) — fall back to a client redirect
                        echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
                        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl) . '"></noscript>';
                        exit;
                    }
                }

            } else {
                $error = $result['message'] ?? 'Failed to get number. Please try again.';
            }
        }
    }
}

// ── Balance for USD/NGN toggle ────────────────────────────────
$usdBal = (float)$user['balance'];
$ngnBal = usd_to_ngn($usdBal);
?>

<?php if ($error): ?>
<div class="alert alert-danger">
  <i class="fi fi-rr-cross-circle"></i>
  <span><?= clean($error) ?></span>
</div>
<?php endif; ?>

<?php if ($apiError): ?>
<div class="alert alert-warning">
  <i class="fi fi-rr-triangle-warning"></i>
  <span>API error: <?= clean($apiError) ?></span>
</div>
<?php endif; ?>

<!-- ── WARNING POPUP ─────────────────────────────────────────── -->
<?php if (!empty($warningMsg)): ?>
<div id="warning-overlay" style="
  position:fixed;inset:0;z-index:9999;
  background:rgba(15,10,30,.75);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  display:flex;align-items:center;justify-content:center;padding:20px;">
  <div style="
    background:var(--bg2);border-radius:20px;
    border:2px solid #f59e0b;padding:28px 24px 24px;
    width:100%;max-width:380px;text-align:center;
    box-shadow:0 20px 60px rgba(0,0,0,.4);">
    <div style="width:56px;height:56px;border-radius:50%;background:#fef3c7;
                display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
      <i class="fi fi-rr-triangle-warning" style="font-size:26px;color:#f59e0b;"></i>
    </div>
    <h3 style="font-size:17px;font-weight:900;margin:0 0 12px;">Important Notice</h3>
    <div style="background:var(--glass);border-radius:12px;padding:14px 16px;
                margin-bottom:20px;font-size:13px;line-height:1.65;text-align:left;
                max-height:200px;overflow-y:auto;">
      <?= nl2br(clean($warningMsg)) ?>
    </div>
    <button onclick="document.getElementById('warning-overlay').style.display='none';document.body.style.overflow='';"
            class="btn btn-primary btn-block" style="padding:13px;">
      <i class="fi fi-rr-check"></i> I Understand, Continue
    </button>
  </div>
</div>
<script>document.body.style.overflow='hidden';</script>
<?php endif; ?>

<!-- ── BALANCE with USD/NGN toggle ──────────────────────────── -->
<!--<div class="glass d-flex justify-between align-center" style="padding:12px 16px;margin-bottom:14px;">-->
<!--  <div>-->
<!--    <div style="font-size:11px;color:var(--text3);font-weight:600;">WALLET BALANCE</div>-->
<!--    <div id="bal-display" style="font-size:20px;font-weight:900;color:var(--primary);"-->
<!--         data-usd="<?= number_format($usdBal,2) ?>"-->
<!--         data-ngn="<?= number_format($ngnBal,2) ?>">-->
<!--      $<?= number_format($usdBal,2) ?>-->
<!--    </div>-->
<!--  </div>-->
<!--  <div style="display:flex;gap:8px;align-items:center;">-->
<!--    <div style="display:flex;background:var(--glass);border-radius:20px;padding:3px;gap:2px;">-->
<!--      <button id="btn-usd" onclick="switchCurrency('usd')"-->
<!--        style="border:none;background:var(--primary);color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:16px;cursor:pointer;">USD</button>-->
<!--      <button id="btn-ngn" onclick="switchCurrency('ngn')"-->
<!--        style="border:none;background:transparent;color:var(--text3);font-size:11px;font-weight:700;padding:4px 10px;border-radius:16px;cursor:pointer;">NGN</button>-->
<!--    </div>-->
<!--    <a href="<?= SITE_URL ?>/pages/fund-wallet.php" class="btn btn-primary btn-sm">-->
<!--      <i class="fi fi-rr-add"></i> Fund-->
<!--    </a>-->
<!--  </div>-->
<!--</div>-->
<br>
<!-- ── PROVIDER SELECTOR ─────────────────────────────────────── -->
<div style="margin-bottom:14px;">
  <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Choose Provider</div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
    <?php foreach ($providers as $num => $p): if (!$p['enabled']) continue; ?>
    <a href="?provider=<?= $num ?>&country=<?= urlencode($selCountry) ?>"
       style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 6px;
              background:var(--glass);border:2px solid <?= $chosenProvider == $num ? 'var(--primary)' : 'transparent' ?>;
              border-radius:var(--radius-sm);text-decoration:none;color:<?= $chosenProvider == $num ? 'var(--primary)' : 'var(--text)' ?>;">
      <i class="fi <?= $p['icon'] ?>" style="font-size:20px;"></i>
      <div style="font-size:10px;font-weight:700;text-align:center;"><?= clean($p['label']) ?></div>
      <div style="font-size:9px;color:var(--text3);">P<?= $num ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── LOGSPLUG SERVER SELECTOR ─────────────────────────────── -->
<?php if ($providerKey === 'logsplug' && array_filter($lpServersEnabled)): ?>
<div style="margin-bottom:14px;">
  <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Server</div>
  <div style="display:grid;grid-template-columns:repeat(<?= count(array_filter($lpServersEnabled)) ?>,1fr);gap:8px;">
    <?php
      $lpServerLabels = array(
          'server1' => 'Server 1',
          'server2' => 'Server 2 (Global)',
          'server3' => 'Server 3 (USA)',
      );
      foreach ($lpServerLabels as $lpKey => $lpLabel): if (empty($lpServersEnabled[$lpKey])) continue;
    ?>
    <a href="?provider=<?= $chosenProvider ?>&lp_server=<?= $lpKey ?>&country=any"
       style="display:flex;align-items:center;justify-content:center;padding:9px 6px;
              background:var(--glass);border:2px solid <?= $selLpServer === $lpKey ? 'var(--primary)' : 'transparent' ?>;
              border-radius:var(--radius-sm);text-decoration:none;font-size:11px;font-weight:700;text-align:center;
              color:<?= $selLpServer === $lpKey ? 'var(--primary)' : 'var(--text)' ?>;"><?= $lpLabel ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── COUNTRY FILTER (5sim + smsman + otpsuite + logsplug server2) ─ -->
<?php if ((in_array($providerKey, array('fivesim','smsman','otpsuite')) || ($providerKey === 'logsplug' && $selLpServer === 'server2')) && !empty($countries)): ?>
<div style="margin-bottom:14px;">
  <form method="GET" style="display:flex;gap:8px;">
    <input type="hidden" name="provider" value="<?= $chosenProvider ?>">
    <?php if ($providerKey === 'logsplug'): ?>
    <input type="hidden" name="lp_server" value="<?= $selLpServer ?>">
    <?php endif; ?>
    <select name="country" class="form-control" onchange="this.form.submit()" style="flex:1;">
      <?php if ($providerKey === 'fivesim'): ?>
      <option value="any" <?= $selCountry==='any'?'selected':'' ?>>🌍 All Countries</option>
      <?php endif; ?>
      <?php foreach ($countries as $cSlug => $info): ?>
        <?php
          $cName = is_array($info) ? ($info['name'] ?? $info['iso'] ?? $cSlug) : $cSlug;
          $cIso  = is_array($info) ? ($info['iso']  ?? $info['code'] ?? '') : strtoupper($cSlug);
        ?>
        <option value="<?= clean($cSlug) ?>" <?= $selCountry==(string)$cSlug?'selected':'' ?>>
          <?= clean($cName) ?> (<?= clean($cIso) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>
<?php endif; ?>

<!-- Info bar -->
<?php if (!empty($services)): ?>
<div class="alert alert-info" style="padding:9px 14px;margin-bottom:12px;">
  <i class="fi fi-rr-info"></i>
  <span>
    <strong><?= number_format(count($services)) ?></strong> services ·
    Provider: <strong style="color:var(--primary);"><?= clean($activeProvider['label']) ?></strong> ·
    <?= $expiry ?> min expiry
    · Rate: ₦<?= number_format($rate, 0) ?>/$ <span style="color:var(--text3);">(tap NGN/USD above to switch)</span>
  </span>
</div>
<?php endif; ?>

<!-- Search -->
<div class="form-group">
  <div class="input-wrap">
    <i class="fi fi-rr-search"></i>
    <input type="text" id="svc-search" class="form-control"
           placeholder="Search service (e.g. WhatsApp, Gmail, TikTok...)">
  </div>
</div>

<!-- Services list -->
<div id="svc-list">
<?php if (!empty($apiError)): ?>
  <div class="empty">
    <i class="fi fi-rr-cloud-disabled" style="display:block;font-size:48px;text-align:center;margin-bottom:12px;opacity:.3;"></i>
    <p class="text-muted text-center">Services unavailable.<br><?= clean($apiError) ?></p>
  </div>

<?php elseif (empty($services)): ?>
  <div class="empty">
    <i class="fi fi-rr-mobile" style="display:block;font-size:48px;text-align:center;margin-bottom:12px;opacity:.3;"></i>
    <p class="text-muted text-center">No services found.<br>Check API key in Admin → Settings.</p>
  </div>

<?php else: ?>
  <p id="no-results" style="display:none;text-align:center;color:var(--text3);padding:20px;">No services match your search.</p>

  <?php foreach ($services as $svc): ?>
  <?php
    // ── PRICE GUARD ───────────────────────────────────────────
    // Ensure price_display is always a valid positive float.
    // If somehow it's still 0 here, skip the row entirely.
    $displayPrice = isset($svc['price_display']) ? (float)$svc['price_display'] : 0;
    if ($displayPrice <= 0) continue;

    $ngnPrice = round($displayPrice * $rate, 2);

    $svcCountryDisplay = safe_upper($svc['country'] ?? '', 'US');
    $svcCountryClean   = clean(is_array($svc['country'])
        ? ($svc['country'][0] ?? '')
        : (string)($svc['country'] ?? ($providerKey === 'verifysms' ? 'us' : 'any')));
  ?>
  <div class="svc-item" data-name="<?= strtolower(clean($svc['name'])) ?>">
    <div class="svc-icon"><i class="fi fi-rr-mobile"></i></div>
    <div class="svc-info">
      <div class="svc-name"><?= clean($svc['name']) ?></div>
      <div class="svc-meta">
        <?= clean($svcCountryDisplay) ?>
        <?php if ($providerKey === 'fivesim'): ?>
          &nbsp;·&nbsp; <?= number_format($svc['qty']) ?> available
        <?php endif; ?>
      </div>
    </div>
    <div class="svc-price price-toggle"
         data-usd="$<?= number_format($displayPrice, 4) ?>"
         data-ngn="₦<?= number_format($ngnPrice, 2) ?>">₦<?= number_format($ngnPrice, 2) ?></div>
    <button type="button"
            class="btn btn-primary btn-sm buy-btn"
            data-slug="<?= htmlspecialchars($svc['slug'], ENT_QUOTES) ?>"
            data-name="<?= htmlspecialchars($svc['name'], ENT_QUOTES) ?>"
            data-country="<?= htmlspecialchars($svcCountryClean, ENT_QUOTES) ?>"
            data-price="<?= $displayPrice ?>"
            data-prov="<?= $chosenProvider ?>"
            data-server="<?= $providerKey === 'logsplug' ? clean($selLpServer) : '' ?>">Buy</button>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Hidden buy form -->
<form method="POST" id="buy-form" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="buy"          value="1">
  <input type="hidden" name="provider_key" id="f-pkey">
  <input type="hidden" name="lp_server"    id="f-lpserver">
  <input type="hidden" name="slug"         id="f-slug">
  <input type="hidden" name="country"      id="f-country">
  <input type="hidden" name="price"        id="f-price">
  <input type="hidden" name="pin"          id="f-pin">
</form>

<!-- Buy modal -->
<div class="modal-overlay" id="buy-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <h3 class="modal-title">Confirm Purchase</h3>
    <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
      <div style="font-size:15px;font-weight:700;" id="modal-svc-name">—</div>
      <div style="font-size:20px;font-weight:900;color:var(--primary);margin-top:4px;" id="modal-svc-price">—</div>
      <div class="text-muted text-sm" style="margin-top:3px;">
        Provider: <span id="modal-provider" style="color:var(--primary);font-weight:700;">—</span>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Enter 4-digit PIN to confirm</label>
      <div class="input-wrap">
        <i class="fi fi-rr-lock"></i>
        <input type="password" id="buy-pin" class="form-control"
               placeholder="● ● ● ●" maxlength="4" inputmode="numeric" autocomplete="off">
      </div>
      <div style="text-align:right;margin-top:6px;">
        <a href="<?= SITE_URL ?>/forgot-pin.php" style="font-size:11px;color:var(--text3);">Forgot PIN?</a>
      </div>
    </div>
    <button onclick="submitBuy()" class="btn btn-primary btn-block">
      <i class="fi fi-rr-mobile"></i> Get Number Now
    </button>
    <button onclick="closeModal('buy-modal')" class="btn btn-ghost btn-block mt-1">Cancel</button>
  </div>
</div>

<script>
// ── NGN / USD price toggle (live rate baked in server-side) ────
var usdToNgnRate = <?= (float)$rate ?>;
var currentCurrency = localStorage.getItem('numbers_currency') || 'NGN';

function applyCurrency(cur) {
  document.querySelectorAll('.price-toggle').forEach(function(el) {
    el.textContent = (cur === 'NGN') ? el.dataset.ngn : el.dataset.usd;
  });
  var flag  = document.getElementById('currency-flag-ui');
  var label = document.getElementById('currency-label-ui');
  if (flag && label) {
    flag.innerText  = (cur === 'NGN') ? '🇳🇬' : '🇺🇸';
    label.innerText = cur;
  }
}

// This overrides/defines the header pill's onclick handler for this page
function toggleCurrencySystem() {
  currentCurrency = (currentCurrency === 'NGN') ? 'USD' : 'NGN';
  localStorage.setItem('numbers_currency', currentCurrency);
  applyCurrency(currentCurrency);
}

applyCurrency(currentCurrency);

// Provider labels
var provLabels = {
  <?php foreach ($providers as $num => $p): ?>
  '<?= $num ?>': '<?= clean($p['label']) ?>',
  <?php endforeach; ?>
};

// Service search
var srch = document.getElementById('svc-search');
if (srch) {
  srch.addEventListener('input', function() {
    var q = this.value.toLowerCase();
    var items = document.querySelectorAll('.svc-item');
    var found = 0;
    items.forEach(function(el) {
      var show = !q || el.dataset.name.indexOf(q) !== -1;
      el.style.display = show ? '' : 'none';
      if (show) found++;
    });
    var nr = document.getElementById('no-results');
    if (nr) nr.style.display = (!found && q) ? '' : 'none';
  });
}

// Buy button — use event delegation to avoid inline JS escaping issues
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('svc-list').addEventListener('click', function(e) {
    var btn = e.target.closest('.buy-btn');
    if (!btn) return;
    var slug    = btn.dataset.slug;
    var name    = btn.dataset.name;
    var country = btn.dataset.country;
    var price   = btn.dataset.price; // always USD — this is what actually gets submitted
    var provNum = btn.dataset.prov;
    var lpServer = btn.dataset.server || '';
    document.getElementById('f-slug').value    = slug;
    document.getElementById('f-country').value = country;
    document.getElementById('f-price').value   = price;
    document.getElementById('f-pkey').value    = provNum;
    document.getElementById('f-lpserver').value = lpServer;
    document.getElementById('buy-pin').value   = '';
    document.getElementById('modal-svc-name').textContent  = name;

    var priceUsd = parseFloat(price);
    document.getElementById('modal-svc-price').textContent = (currentCurrency === 'NGN')
        ? ('₦' + (priceUsd * usdToNgnRate).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}))
        : ('$' + priceUsd.toFixed(4));

    document.getElementById('modal-provider').textContent  = provLabels[provNum] || 'P' + provNum;
    openModal('buy-modal');
  });
});

function submitBuy() {
  var pin = document.getElementById('buy-pin').value.trim();
  if (!pin || pin.length !== 4) { alert('Please enter your 4-digit PIN.'); return; }
  document.getElementById('f-pin').value = pin;
  document.getElementById('buy-form').submit();
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>