<?php
// ============================================================
//  admin/gateways.php  (UPGRADED)
//  – NCWallet: API Key, Secret Key, Transaction PIN,
//              Business Name, Base URL
//  – Paystack: Public Key, Secret Key
//  – Both: Active toggle + webhook/callback URL display
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Payment Gateways';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {

        // ── Save NCWallet ─────────────────────────────────────
        if (isset($_POST['save_ncwallet'])) {
            $apiKey   = trim($_POST['nc_api_key']   ?? '');
            $secret   = trim($_POST['nc_secret']    ?? '');
            $txnPin   = trim($_POST['nc_txn_pin']   ?? '');
            $bizName  = trim($_POST['nc_biz_name']  ?? '');
            $baseUrl  = rtrim(trim($_POST['nc_base_url'] ?? 'https://ncwallet.africa/api/v1'), '/');
            $active   = isset($_POST['nc_active']) ? 1 : 0;

            // Normalise: strip www. and ensure /api/v1 path is present
            $baseUrl = preg_replace('#^(https?://)www\.#i', '$1', $baseUrl);
            if (strpos($baseUrl, '/api/v1') === false) {
                $baseUrl .= '/api/v1';
            }

            // Validate URL
            if ($baseUrl && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $error = 'NCWallet Base URL is not a valid URL.';
            } else {
                // Store: public_key=api_key, secret_key=secret, txn_pin=txn_pin,
                //        business_name=business_name, extra2=base_url
                $pdo->prepare("UPDATE payment_gateways SET
                    public_key=?, secret_key=?, txn_pin=?,
                    business_name=?, extra2=?, is_active=?
                    WHERE gateway='ncwallet'")
                    ->execute([$apiKey, $secret, $txnPin, $bizName, $baseUrl, $active]);
                $success = '✅ NCWallet settings saved successfully.';
            }
        }

        // ── Save PaymentPoint (Main Gateway) ──────────────────
        if (isset($_POST['save_paymentpoint'])) {
            $apiKey  = trim($_POST['pp_api_key'] ?? '');
            $secret  = trim($_POST['pp_secret']  ?? '');
            $bizId   = trim($_POST['pp_business_id'] ?? '');
            $active  = isset($_POST['pp_active']) ? 1 : 0;

            $pdo->prepare("UPDATE payment_gateways SET
                public_key=?, secret_key=?, extra=?, is_active=?
                WHERE gateway='paymentpoint'")
                ->execute([$apiKey, $secret, $bizId, $active]);
            $success = '✅ PaymentPoint settings saved successfully.';
        }

        // ── Save Paystack ─────────────────────────────────────
        elseif (isset($_POST['save_paystack'])) {
            $pub    = trim($_POST['ps_public'] ?? '');
            $sec    = trim($_POST['ps_secret'] ?? '');
            $active = isset($_POST['ps_active']) ? 1 : 0;
            $pdo->prepare("UPDATE payment_gateways SET public_key=?,secret_key=?,is_active=? WHERE gateway='paystack'")
                ->execute([$pub, $sec, $active]);
            $success = '✅ Paystack settings saved successfully.';
        }

        // ── Save SprintPay ────────────────────────────────────
        elseif (isset($_POST['save_sprintpay'])) {
            $apiKey = trim($_POST['sp_api_key'] ?? '');
            $secret = trim($_POST['sp_secret'] ?? '');
            $active = isset($_POST['sp_active']) ? 1 : 0;
            $pdo->prepare("UPDATE payment_gateways SET public_key=?,secret_key=?,is_active=? WHERE gateway='sprintpay'")
                ->execute([$apiKey, $secret, $active]);
            $success = '✅ SprintPay settings saved successfully.';
        }

        // ── Save USD/NGN fallback rate ─────────────────────────
        elseif (isset($_POST['save_rate'])) {
            $rate = (float)($_POST['usd_ngn_rate'] ?? 0);
            if ($rate < 100) {
                $error = 'Rate must be at least 100 NGN/USD.';
            } else {
                set_setting('usd_ngn_fallback_rate', (string)$rate);
                // Clear cached live rate so next fetch picks up fallback if needed
                $pdo->prepare("DELETE FROM site_settings WHERE `key`='usd_ngn_rate'")->execute();
                $success = '✅ Fallback USD/NGN rate updated.';
            }
        }
    }
}

// ── Fetch current config ──────────────────────────────────────
$pp      = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paymentpoint'")->fetch();
$nc      = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='ncwallet'")->fetch();
$ps      = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paystack'")->fetch();
$sp      = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='sprintpay'")->fetch();
$fallback= get_setting('usd_ngn_fallback_rate', '1600');
$liveRate= get_setting('usd_ngn_rate', '');

// Ensure rows exist
if (!$pp) { $pdo->query("INSERT IGNORE INTO payment_gateways (gateway,is_active) VALUES ('paymentpoint',0)"); $pp = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paymentpoint'")->fetch(); }
if (!$nc) { $pdo->query("INSERT IGNORE INTO payment_gateways (gateway,is_active) VALUES ('ncwallet',0)"); $nc = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='ncwallet'")->fetch(); }
if (!$ps) { $pdo->query("INSERT IGNORE INTO payment_gateways (gateway,is_active) VALUES ('paystack',0)"); $ps = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paystack'")->fetch(); }
if (!$sp) { $pdo->query("INSERT IGNORE INTO payment_gateways (gateway,is_active) VALUES ('sprintpay',0)"); $sp = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='sprintpay'")->fetch(); }
?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div>
<?php endif; ?>

<!-- Live Rate Info Banner -->
<div class="alert alert-info" style="margin-bottom:20px;">
  <i class="fi fi-rr-dollar"></i>
  <div>
    <strong>USD/NGN Rate:</strong>
    <?php if ($liveRate): ?>
      Live rate: <strong>₦<?= number_format((float)$liveRate, 2) ?>/USD</strong>
      &nbsp;·&nbsp; Cached, refreshes every 10 minutes
    <?php else: ?>
      No live rate cached yet. Will use fallback: <strong>₦<?= number_format((float)$fallback, 2) ?>/USD</strong>
    <?php endif; ?>
    &nbsp;·&nbsp; Users see <strong>USD</strong> balance; topups accepted in <strong>NGN</strong>.
  </div>
</div>

<!-- ══ PaymentPoint — MAIN GATEWAY ══════════════════════════ -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);
            margin-bottom:20px;border:2px solid var(--primary);max-width:640px;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <div style="width:46px;height:46px;border-radius:12px;background:var(--primary-light);
                display:flex;align-items:center;justify-content:center;">
      <i class="fi fi-rr-bank" style="font-size:20px;color:var(--primary);"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:800;font-size:15px;">PaymentPoint <span class="pill pill-info" style="margin-left:6px;">MAIN GATEWAY</span></div>
      <div style="font-size:12px;color:var(--text2);">Virtual accounts · PalmPay & OPay · Auto-deposit</div>
    </div>
    <span class="pill pill-<?= $pp['is_active'] ? 'success' : 'gray' ?>"><?= $pp['is_active'] ? 'Active' : 'Off' ?></span>
  </div>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label">API Key <span style="color:var(--danger);">*</span></label>
      <input type="text" name="pp_api_key" class="form-control"
             value="<?= clean($pp['public_key'] ?? '') ?>"
             placeholder="PaymentPoint api-key">
    </div>

    <div class="form-group">
      <label class="form-label">Secret Key (Bearer Token) <span style="color:var(--danger);">*</span></label>
      <input type="password" name="pp_secret" class="form-control"
             value="<?= clean($pp['secret_key'] ?? '') ?>"
             placeholder="PaymentPoint secret / Authorization token">
    </div>

    <div class="form-group">
      <label class="form-label">Business ID <span style="color:var(--danger);">*</span></label>
      <input type="text" name="pp_business_id" class="form-control"
             value="<?= clean($pp['extra'] ?? '') ?>"
             placeholder="Your PaymentPoint Business ID">
      <div style="font-size:11px;color:var(--text3);margin-top:4px;">Found on your PaymentPoint dashboard. Sent as <code>businessId</code> when creating accounts.</div>
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
      <input type="checkbox" name="pp_active" id="pp-on"
             <?= $pp['is_active'] ? 'checked' : '' ?>
             style="width:18px;height:18px;accent-color:var(--primary);">
      <label for="pp-on" style="font-size:14px;font-weight:600;cursor:pointer;">Enable PaymentPoint (recommended as main gateway)</label>
    </div>

    <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
      <i class="fi fi-rr-link"></i>
      <div>
        Webhook URL (paste in PaymentPoint dashboard):<br>
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/paymentpoint-webhook.php
        </code>
      </div>
    </div>

    <button type="submit" name="save_paymentpoint" class="btn btn-primary btn-block">
      <i class="fi fi-rr-disk"></i> Save PaymentPoint Settings
    </button>
  </form>
</div>

<div style="font-size:12px;color:var(--text3);font-weight:700;letter-spacing:.5px;margin:0 0 12px;">SECONDARY / BACKUP GATEWAYS</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

<!-- ══ NCWallet Africa ══════════════════════════════════════ -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <div style="width:46px;height:46px;border-radius:12px;background:var(--primary-light);
                display:flex;align-items:center;justify-content:center;">
      <i class="fi fi-rr-bank" style="font-size:20px;color:var(--primary);"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:800;font-size:15px;">NCWallet Africa</div>
      <div style="font-size:12px;color:var(--text2);">Virtual accounts · Auto-deposit · Palmpay</div>
    </div>
    <span class="pill pill-<?= $nc['is_active'] ? 'success' : 'gray' ?>"><?= $nc['is_active'] ? 'Active' : 'Off' ?></span>
  </div>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label">API Key <span style="color:var(--danger);">*</span></label>
      <input type="text" name="nc_api_key" class="form-control"
             value="<?= clean($nc['public_key'] ?? '') ?>"
             placeholder="NCWallet API Key (Bearer token)">
    </div>

    <div class="form-group">
      <label class="form-label">Secret Key <span style="color:var(--danger);">*</span></label>
      <input type="password" name="nc_secret" class="form-control"
             value="<?= clean($nc['secret_key'] ?? '') ?>"
             placeholder="NCWallet Secret Key">
    </div>

    <div class="form-group">
      <label class="form-label">Transaction PIN <span style="color:var(--danger);">*</span></label>
      <input type="password" name="nc_txn_pin" class="form-control"
             value="<?= clean($nc['txn_pin'] ?? '') ?>"
             placeholder="4-digit transaction PIN" maxlength="6" inputmode="numeric">
      <div style="font-size:11px;color:var(--text3);margin-top:4px;">Required for payouts and transfers via NCWallet.</div>
    </div>

    <div class="form-group">
      <label class="form-label">Business Name</label>
      <input type="text" name="nc_biz_name" class="form-control"
             value="<?= clean($nc['business_name'] ?? '') ?>"
             placeholder="e.g. DonnieSMS">
    </div>

    <div class="form-group">
      <label class="form-label">NCWallet API Base URL</label>
      <input type="url" name="nc_base_url" class="form-control"
             value="<?= clean($nc['extra2'] ?? 'https://ncwallet.africa/api/v1') ?>"
             placeholder="https://ncwallet.africa/api/v1">
      <div style="font-size:11px;color:var(--text3);margin-top:4px;">The correct URL is <strong>https://ncwallet.africa/api/v1</strong> — do not add www. or change this unless NCWallet specifically tells you to.</div>
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
      <input type="checkbox" name="nc_active" id="nc-on"
             <?= $nc['is_active'] ? 'checked' : '' ?>
             style="width:18px;height:18px;accent-color:var(--primary);">
      <label for="nc-on" style="font-size:14px;font-weight:600;cursor:pointer;">Enable NCWallet</label>
    </div>

    <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
      <i class="fi fi-rr-link"></i>
      <div>
        Webhook URL (paste in NCWallet dashboard):<br>
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/ncwallet-webhook.php
        </code>
      </div>
    </div>

    <button type="submit" name="save_ncwallet" class="btn btn-primary btn-block">
      <i class="fi fi-rr-disk"></i> Save NCWallet Settings
    </button>
  </form>
</div>

<!-- ══ Paystack ═════════════════════════════════════════════ -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <div style="width:46px;height:46px;border-radius:12px;background:#e8f5e9;
                display:flex;align-items:center;justify-content:center;">
      <i class="fi fi-rr-credit-card" style="font-size:20px;color:var(--success);"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:800;font-size:15px;">Paystack</div>
      <div style="font-size:12px;color:var(--text2);">Card · USSD · Wema Bank Virtual Account</div>
    </div>
    <span class="pill pill-<?= $ps['is_active'] ? 'success' : 'gray' ?>"><?= $ps['is_active'] ? 'Active' : 'Off' ?></span>
  </div>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label">Public Key</label>
      <input type="text" name="ps_public" class="form-control"
             value="<?= clean($ps['public_key'] ?? '') ?>"
             placeholder="pk_live_...">
    </div>

    <div class="form-group">
      <label class="form-label">Secret Key</label>
      <input type="password" name="ps_secret" class="form-control"
             value="<?= clean($ps['secret_key'] ?? '') ?>"
             placeholder="sk_live_...">
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
      <input type="checkbox" name="ps_active" id="ps-on"
             <?= $ps['is_active'] ? 'checked' : '' ?>
             style="width:18px;height:18px;accent-color:var(--primary);">
      <label for="ps-on" style="font-size:14px;font-weight:600;cursor:pointer;">Enable Paystack</label>
    </div>

    <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
      <i class="fi fi-rr-link"></i>
      <div>
        Callback URL (set in Paystack dashboard):<br>
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/paystack-callback.php
        </code>
      </div>
    </div>

    <div class="alert alert-warning" style="margin-bottom:14px;font-size:12px;">
      <i class="fi fi-rr-info"></i>
      <div>
        <strong>Wema Bank VA note:</strong> Paystack requires your account to be KYC-verified
        and have dedicated virtual accounts enabled. Contact Paystack support if VA generation fails.
      </div>
    </div>

    <button type="submit" name="save_paystack" class="btn btn-primary btn-block">
      <i class="fi fi-rr-disk"></i> Save Paystack Settings
    </button>
  </form>
</div>

</div><!-- end grid -->

<!-- ══ SprintPay Collection API ══════════════════════════════ -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);
            margin-bottom:20px;max-width:640px;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <div style="width:46px;height:46px;border-radius:12px;background:#e0f2fe;
                display:flex;align-items:center;justify-content:center;">
      <i class="fi fi-rr-credit-card" style="font-size:20px;color:#0284c7;"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:800;font-size:15px;">SprintPay</div>
      <div style="font-size:12px;color:var(--text2);">Collection API · Bank · Card · Crypto · Hosted checkout</div>
    </div>
    <span class="pill pill-<?= $sp['is_active'] ? 'success' : 'gray' ?>"><?= $sp['is_active'] ? 'Active' : 'Off' ?></span>
  </div>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label">API Key <span style="color:var(--danger);">*</span></label>
      <input type="text" name="sp_api_key" class="form-control"
             value="<?= clean($sp['public_key'] ?? '') ?>"
             placeholder="Your SprintPay API key">
      <div style="font-size:11px;color:var(--text3);margin-top:4px;">From SprintPay → Api Keys. Sent as <code>key</code> on the /pay checkout URL.</div>
    </div>

    <div class="form-group">
      <label class="form-label">Secret / Webhook Key</label>
      <input type="password" name="sp_secret" class="form-control"
             value="<?= clean($sp['secret_key'] ?? '') ?>"
             placeholder="Optional secret from SprintPay dashboard">
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
      <input type="checkbox" name="sp_active" id="sp-on"
             <?= $sp['is_active'] ? 'checked' : '' ?>
             style="width:18px;height:18px;accent-color:var(--primary);">
      <label for="sp-on" style="font-size:14px;font-weight:600;cursor:pointer;">Enable SprintPay</label>
    </div>

    <div class="alert alert-info" style="margin-bottom:14px;font-size:12px;">
      <i class="fi fi-rr-link"></i>
      <div>
        Paste these URLs in your SprintPay Collection dashboard:<br>
        Callback:
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/sprintpay-callback.php
        </code><br>
        Webhook:
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/sprintpay-webhook.php
        </code><br>
        e-Check:
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/sprintpay-echeck.php
        </code><br>
        e-Fund:
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/sprintpay-efund.php
        </code><br>
        Verify username:
        <code style="background:rgba(0,0,0,.08);padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;">
          <?= SITE_URL ?>/api/sprintpay-verify.php
        </code>
      </div>
    </div>

    <button type="submit" name="save_sprintpay" class="btn btn-primary btn-block">
      <i class="fi fi-rr-disk"></i> Save SprintPay Settings
    </button>
  </form>
</div>

<!-- ══ USD/NGN Rate Settings ════════════════════════════════ -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);max-width:520px;">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <div style="width:46px;height:46px;border-radius:12px;background:#fef3c7;
                display:flex;align-items:center;justify-content:center;">
      <i class="fi fi-rr-dollar" style="font-size:20px;color:#d97706;"></i>
    </div>
    <div>
      <div style="font-weight:800;font-size:15px;">USD / NGN Rate Settings</div>
      <div style="font-size:12px;color:var(--text2);">Fallback rate if live fetch fails · Min $<?= get_min_topup_usd() ?> / Max $<?= get_max_topup_usd() ?></div>
    </div>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Fallback NGN Rate per $1 USD</label>
      <div class="input-wrap">
        <i class="fi fi-rr-coin"></i>
        <input type="number" name="usd_ngn_rate" class="form-control"
               value="<?= clean($fallback) ?>"
               min="100" step="10" placeholder="e.g. 1600">
      </div>
      <div style="font-size:11px;color:var(--text3);margin-top:4px;">
        Live rate fetched automatically every 10 min. This is used only if live fetch fails.
      </div>
    </div>
    <button type="submit" name="save_rate" class="btn btn-primary">
      <i class="fi fi-rr-disk"></i> Save Rate
    </button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
