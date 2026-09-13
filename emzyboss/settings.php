<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/admin_header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // General fields
        $general = ['site_name','site_tagline','min_fund_amount','sms_markup_pct','support_email','otp_expiry_minutes','maintenance_mode'];
        foreach ($general as $f) {
            set_setting($f, clean($_POST[$f] ?? ''));
        }

        // Warning popup message
        set_setting('numbers_warning_message', trim($_POST['numbers_warning_message'] ?? ''));

        // Provider 1 (5sim)
        set_setting('provider1_label',   trim($_POST['provider1_label']   ?? '5sim'));
        set_setting('provider1_enabled', isset($_POST['provider1_enabled']) ? '1' : '0');
        if (!empty($_POST['fivesim_api_key'])) {
            set_setting('fivesim_api_key', trim($_POST['fivesim_api_key']));
        }

        // Provider 2 (VerifySMS)
        set_setting('provider2_label',   trim($_POST['provider2_label']   ?? 'VerifySMS'));
        set_setting('provider2_enabled', isset($_POST['provider2_enabled']) ? '1' : '0');
        if (!empty($_POST['verifysms_api_key'])) {
            set_setting('verifysms_api_key', trim($_POST['verifysms_api_key']));
        }
        if (!empty($_POST['smsman_api_key'])) {
            set_setting('smsman_api_key', trim($_POST['smsman_api_key']));
        }

        // Provider 3
        set_setting('provider3_label',   trim($_POST['provider3_label']   ?? 'SMS-Man'));
        set_setting('provider3_enabled', isset($_POST['provider3_enabled']) ? '1' : '0');

        // Provider 4 (otpsuite — USA WhatsApp)
        set_setting('provider4_label',   trim($_POST['provider4_label']   ?? 'USA WhatsApp'));
        set_setting('provider4_desc',    trim($_POST['provider4_desc']    ?? 'USA WhatsApp numbers via otpsuite.com'));
        set_setting('provider4_enabled', isset($_POST['provider4_enabled']) ? '1' : '0');
        if (!empty($_POST['otpsuite_api_key'])) {
            set_setting('otpsuite_api_key', trim($_POST['otpsuite_api_key']));
        }

        // Provider 5 (logsplug)
        set_setting('provider5_label',   trim($_POST['provider5_label']   ?? 'Saver 3'));
        set_setting('provider5_enabled', isset($_POST['provider5_enabled']) ? '1' : '0');
        if (!empty($_POST['logsplug_api_key'])) {
            set_setting('logsplug_api_key', trim($_POST['logsplug_api_key']));
        }
        if (!empty($_POST['logsplug_base_url'])) {
            set_setting('logsplug_base_url', trim($_POST['logsplug_base_url']));
        }
        set_setting('logsplug_server1_enabled', isset($_POST['logsplug_server1_enabled']) ? '1' : '0');
        set_setting('logsplug_server2_enabled', isset($_POST['logsplug_server2_enabled']) ? '1' : '0');
        set_setting('logsplug_server3_enabled', isset($_POST['logsplug_server3_enabled']) ? '1' : '0');

        // USD/NGN fallback rate
        if (!empty($_POST['usd_ngn_fallback_rate'])) {
            set_setting('usd_ngn_fallback_rate', (string)(float)$_POST['usd_ngn_fallback_rate']);
        }

        // Referral bonus %
        if (isset($_POST['referral_bonus_percent'])) {
            set_setting('referral_bonus_percent', (string)(float)$_POST['referral_bonus_percent']);
        }

        // Social Boost (momopanel)
        if (!empty($_POST['momopanel_api_key'])) {
            set_setting('momopanel_api_key', trim($_POST['momopanel_api_key']));
        }
        if (isset($_POST['momopanel_markup_percent'])) {
            set_setting('momopanel_markup_percent', (string)(float)$_POST['momopanel_markup_percent']);
        }

        $success = 'Settings saved successfully.';

    } elseif (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $s   = $pdo->prepare("SELECT password FROM admin_users WHERE id=?");
        $s->execute([$_SESSION['admin_id']]); $admin = $s->fetch();
        if (!$admin || !password_verify($old, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]), $_SESSION['admin_id']]);
            $success = 'Admin password changed.';
        }
    }
}

$s = []; foreach ($pdo->query("SELECT * FROM site_settings")->fetchAll() as $r) $s[$r['key']] = $r['value'];
?>

<?php if ($error): ?><div class="alert alert-danger"><i class="fi fi-rr-cross-circle"></i><span><?= clean($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fi fi-rr-check"></i><span><?= clean($success) ?></span></div><?php endif; ?>

<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="save_settings" value="1">

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

    <!-- General -->
    <div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
      <h3 style="font-weight:800;font-size:15px;margin-bottom:16px;">⚙️ General</h3>
      <div class="form-group">
        <label class="form-label">Site Name</label>
        <input type="text" name="site_name" class="form-control" value="<?= clean($s['site_name']??'DonnieSMS') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Tagline</label>
        <input type="text" name="site_tagline" class="form-control" value="<?= clean($s['site_tagline']??'') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Support Email</label>
        <div class="input-wrap">
          <i class="fi fi-rr-envelope"></i>
          <input type="email" name="support_email" class="form-control" value="<?= clean($s['support_email']??'') ?>" placeholder="support@yoursite.com">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Maintenance Mode</label>
        <select name="maintenance_mode" class="form-control">
          <option value="0" <?= ($s['maintenance_mode']??'0')==='0'?'selected':'' ?>>OFF – Site is live</option>
          <option value="1" <?= ($s['maintenance_mode']??'0')==='1'?'selected':'' ?>>ON – Maintenance page</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">OTP Expiry (minutes)</label>
        <input type="number" name="otp_expiry_minutes" class="form-control" value="<?= clean($s['otp_expiry_minutes']??'20') ?>" min="5" max="60">
      </div>
      <div class="form-group">
        <label class="form-label">SMS Markup (%)</label>
        <input type="number" name="sms_markup_pct" class="form-control" value="<?= clean($s['sms_markup_pct']??'20') ?>" min="0" max="500">
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Added on top of provider prices. e.g. 20% on $0.10 = $0.12</div>
      </div>
      <div class="form-group">
        <label class="form-label">Minimum Fund Amount (USD $)</label>
        <input type="number" name="min_fund_amount" class="form-control" value="<?= clean($s['min_fund_amount']??'1') ?>" min="0.5" step="0.5">
      </div>
      <div class="form-group">
        <label class="form-label">USD → NGN Fallback Rate</label>
        <div class="input-wrap">
          <i class="fi fi-rr-exchange"></i>
          <input type="number" name="usd_ngn_fallback_rate" class="form-control"
                 value="<?= clean($s['usd_ngn_fallback_rate']??'1600') ?>" min="100" step="1"
                 placeholder="e.g. 1650">
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Used when live exchange rate API is unavailable. Current cached: <?= clean($s['usd_ngn_rate']??'Not set') ?></div>
      </div>
      <div class="form-group">
        <label class="form-label">Referral Bonus %</label>
        <input type="number" name="referral_bonus_percent" class="form-control"
               value="<?= clean($s['referral_bonus_percent']??'2') ?>" min="0" max="100" step="0.1">
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Paid to the referrer when their invited friend makes their first deposit.</div>
      </div>
    <!--  <div class="form-group">-->
    <!--    <label class="form-label">Social Boost — momopanel.com API Key</label>-->
    <!--    <input type="text" name="momopanel_api_key" class="form-control"-->
    <!--           value="<?= clean($s['momopanel_api_key']??'') ?>" placeholder="Your momopanel.com API key">-->
    <!--  </div>-->
    <!--  <div class="form-group">-->
    <!--    <label class="form-label">Social Boost Markup %</label>-->
    <!--    <input type="number" name="momopanel_markup_percent" class="form-control"-->
    <!--           value="<?= clean($s['momopanel_markup_percent']??'30') ?>" min="0" step="0.1">-->
    <!--    <div style="font-size:11px;color:var(--text3);margin-top:4px;">Added on top of momopanel's rate (already in USD, no conversion needed).</div>-->
    <!--  </div>-->
    </div>

    <!-- Providers -->
    <div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
      <h3 style="font-weight:800;font-size:15px;margin-bottom:16px;">📡 OTP Providers</h3>

      <!-- Provider 1 - 5sim -->
      <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <span style="font-weight:700;font-size:13px;">🔵 Provider 1</span>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
            <input type="checkbox" name="provider1_enabled" value="1" <?= ($s['provider1_enabled']??'1')==='1'?'checked':'' ?>>
            Enabled
          </label>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Display Name</label>
          <input type="text" name="provider1_label" class="form-control" value="<?= clean($s['provider1_label']??'5sim') ?>" placeholder="5sim">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="font-size:11px;">5sim API Key</label>
          <input type="text" name="fivesim_api_key" class="form-control" value="<?= clean($s['fivesim_api_key']??'') ?>" placeholder="JWT API key from 5sim.net">
        </div>
      </div>

      <!-- Provider 2 - VerifySMS -->
      <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <span style="font-weight:700;font-size:13px;">🟢 Provider 2</span>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
            <input type="checkbox" name="provider2_enabled" value="1" <?= ($s['provider2_enabled']??'1')==='1'?'checked':'' ?>>
            Enabled
          </label>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Display Name</label>
          <input type="text" name="provider2_label" class="form-control" value="<?= clean($s['provider2_label']??'VerifySMS') ?>" placeholder="VerifySMS">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">VerifySMS API Key</label>
          <input type="text" name="verifysms_api_key" class="form-control" value="<?= clean($s['verifysms_api_key']??'') ?>" placeholder="From verifysms.io → Profile">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="font-size:11px;">VerifySMS Webhook URL</label>
          <?php $webhookUrl = SITE_URL . '/api/verifysms-webhook.php'; ?>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" class="form-control" id="verifysms-webhook-url" value="<?= clean($webhookUrl) ?>" readonly style="font-size:11px;" onclick="this.select()">
            <button type="button" onclick="copyWebhookUrl()" class="btn btn-primary btn-sm" style="white-space:nowrap;" id="copy-webhook-btn">
              <i class="fi fi-rr-copy"></i> Copy
            </button>
          </div>
        </div>
      </div>

      <!-- Provider 3 - SMS-Man -->
      <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <span style="font-weight:700;font-size:13px;">🟡 Provider 3 · SMS-Man</span>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
            <input type="checkbox" name="provider3_enabled" value="1" <?= ($s['provider3_enabled']??'0')==='1'?'checked':'' ?>>
            Enabled
          </label>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Display Name</label>
          <input type="text" name="provider3_label" class="form-control" value="<?= clean($s['provider3_label']??'SMS-Man') ?>" placeholder="SMS-Man">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="font-size:11px;">SMS-Man API Key (token)</label>
          <input type="text" name="smsman_api_key" class="form-control"
                 value="<?= clean($s['smsman_api_key']??'') ?>"
                 placeholder="Get from sms-man.com → Profile → API">
          <div style="font-size:10px;color:var(--text3);margin-top:3px;">Base URL: api.sms-man.com/control · Auth via ?token=</div>
        </div>
      </div>

      <!-- Provider 4 -->
      <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <span style="font-weight:700;font-size:13px;">🟢 Provider 4 <span style="font-size:10px;color:var(--text3);">(otpsuite.com)</span></span>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
            <input type="checkbox" name="provider4_enabled" value="1" <?= ($s['provider4_enabled']??'0')==='1'?'checked':'' ?>>
            Enabled
          </label>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Display Name</label>
          <input type="text" name="provider4_label" class="form-control" value="<?= clean($s['provider4_label']??'USA WhatsApp') ?>" placeholder="USA WhatsApp">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Description</label>
          <input type="text" name="provider4_desc" class="form-control" value="<?= clean($s['provider4_desc']??'USA WhatsApp numbers via otpsuite.com') ?>" placeholder="USA WhatsApp numbers via otpsuite.com">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="font-size:11px;">otpsuite.com API Key</label>
          <input type="text" name="otpsuite_api_key" class="form-control"
                 value="<?= clean($s['otpsuite_api_key']??'') ?>"
                 placeholder="From otpsuite.com/developer-api → X-API-KEY">
          <div style="font-size:10px;color:var(--text3);margin-top:3px;">Base URL: otpsuite.com/api/v1 · Prices are in NGN, converted to USD automatically.</div>
        </div>
      </div>

      <!-- Provider 5 -->
      <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-top:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <span style="font-weight:700;font-size:13px;">🟢 Provider 5</span>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;">
            <input type="checkbox" name="provider5_enabled" value="1" <?= ($s['provider5_enabled']??'1')==='1'?'checked':'' ?>>
            Enabled
          </label>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Display Name</label>
          <input type="text" name="provider5_label" class="form-control" value="<?= clean($s['provider5_label']??'Saver 3') ?>" placeholder="Saver 3">
          <div style="font-size:10px;color:var(--text3);margin-top:3px;">Users only ever see this name — never the underlying API provider's brand.</div>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">API Key</label>
          <input type="text" name="logsplug_api_key" class="form-control"
                 value="<?= clean($s['logsplug_api_key']??'') ?>"
                 placeholder="x-api-key from your LogsPlug dashboard">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label" style="font-size:11px;">Base URL (leave blank for default)</label>
          <input type="text" name="logsplug_base_url" class="form-control"
                 value="<?= clean($s['logsplug_base_url']??'') ?>"
                 placeholder="https://v2.api.logsplug.com/api/v2">
          <div style="font-size:10px;color:var(--text3);margin-top:3px;">Has 3 sub-servers (chosen on the Numbers page). Prices assumed NGN, converted to USD automatically.</div>
        </div>
        <div class="form-group" style="margin-bottom:0;margin-top:12px;">
          <label class="form-label" style="font-size:11px;">Sub-Servers</label>
          <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;">
              <input type="checkbox" name="logsplug_server1_enabled" value="1" <?= ($s['logsplug_server1_enabled']??'1')==='1'?'checked':'' ?>>
              Server 1
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;">
              <input type="checkbox" name="logsplug_server2_enabled" value="1" <?= ($s['logsplug_server2_enabled']??'1')==='1'?'checked':'' ?>>
              Server 2 (Global)
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;">
              <input type="checkbox" name="logsplug_server3_enabled" value="1" <?= ($s['logsplug_server3_enabled']??'1')==='1'?'checked':'' ?>>
              Server 3 (USA)
            </label>
          </div>
          <div style="font-size:10px;color:var(--text3);margin-top:5px;">A disabled server disappears from the Numbers page entirely — same behavior as turning off a whole provider.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Warning popup message (full width) -->
  <div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
    <h3 style="font-weight:800;font-size:15px;margin-bottom:6px;">
      ⚠️ Numbers Page Warning Popup
    </h3>
    <p style="font-size:12px;color:var(--text3);margin-bottom:12px;">
      This message will appear as a popup when users open the Buy Numbers page. Leave blank to disable. Users will only see it once per session.
    </p>
    <textarea name="numbers_warning_message" class="form-control" rows="4"
              placeholder="e.g. Please note: All purchases are final and non-refundable unless the number expires. OTP numbers are for personal use only."
              style="resize:vertical;"><?= htmlspecialchars($s['numbers_warning_message']??'', ENT_QUOTES) ?></textarea>
    <div style="font-size:11px;color:var(--text3);margin-top:6px;">
      Supports line breaks. Max 500 characters recommended.
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="padding:14px 40px;font-size:16px;">
    <i class="fi fi-rr-disk"></i> Save All Settings
  </button>
</form>

<!-- Change Admin Password -->
<div style="background:var(--bg2);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-top:20px;max-width:480px;">
  <h3 style="font-weight:800;font-size:15px;margin-bottom:16px;">🔒 Change Admin Password</h3>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Current Password</label>
      <div class="input-wrap">
        <i class="fi fi-rr-lock"></i>
        <input type="password" name="old_password" class="form-control" placeholder="Current password">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">New Password</label>
      <div class="input-wrap">
        <i class="fi fi-rr-lock"></i>
        <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters">
      </div>
    </div>
    <button type="submit" name="change_password" class="btn btn-primary btn-block">
      Update Password
    </button>
  </form>
</div>

<script>
function copyWebhookUrl() {
  var el = document.getElementById('verifysms-webhook-url');
  el.select(); el.setSelectionRange(0, 99999);
  navigator.clipboard ? navigator.clipboard.writeText(el.value).then(function(){
    var btn = document.getElementById('copy-webhook-btn');
    btn.innerHTML = '<i class="fi fi-rr-check"></i> Copied!';
    setTimeout(function(){ btn.innerHTML = '<i class="fi fi-rr-copy"></i> Copy'; }, 2000);
  }) : document.execCommand('copy');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
