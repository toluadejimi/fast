<?php
// ============================================================
//  DonnieSMS OTP – includes/functions.php  (UPGRADED)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/countries.php';

// ── Encryption ───────────────────────────────────────────────
function encrypt_data(string $d): string {
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($d, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $enc);
}
function decrypt_data(string $d): string {
    $raw = base64_decode($d);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
}

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(string $t): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// ── Helpers ───────────────────────────────────────────────────
function clean(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void { header("Location: $url"); exit; }

// ── Money formatting — users see USD balance ──────────────────
function fmt_money(float $a): string {
    // User balances are stored and displayed in USD
    return '$' . number_format($a, 2);
}
function fmt_naira(float $a): string {
    return '₦' . number_format($a, 2);
}
function gen_ref(string $prefix = 'TXN'): string {
    return $prefix . strtoupper(bin2hex(random_bytes(8)));
}

// ── USD ↔ NGN Conversion ──────────────────────────────────────
// Fetches live rate from a free API, caches in DB for 10 minutes
function get_usd_to_ngn_rate(): float {
    global $pdo;
    // Check cache
    $cached = $pdo->query("SELECT value, updated_at FROM site_settings WHERE `key`='usd_ngn_rate'")->fetch();
    if ($cached && (time() - strtotime($cached['updated_at'])) < 600) {
        $rate = (float)$cached['value'];
        if ($rate > 0) return $rate;
    }
    // Try ExchangeRate-API (free, no key needed for this endpoint)
    $rate = 0;
    $apis = [
        'https://open.er-api.com/v6/latest/USD',
        'https://api.exchangerate-api.com/v4/latest/USD',
    ];
    foreach ($apis as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res) {
            $data = json_decode($res, true);
            $r = (float)($data['rates']['NGN'] ?? $data['conversion_rates']['NGN'] ?? 0);
            if ($r > 100) { $rate = $r; break; }
        }
    }
    // Fallback to admin-configured rate
    if ($rate <= 0) {
        $fallback = $pdo->query("SELECT value FROM site_settings WHERE `key`='usd_ngn_fallback_rate'")->fetchColumn();
        $rate = (float)($fallback ?: 1600);
    }
    // Cache it
    $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES ('usd_ngn_rate',?)
        ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$rate]);
    return $rate;
}

function usd_to_ngn(float $usd): float {
    return round($usd * get_usd_to_ngn_rate(), 2);
}
function ngn_to_usd(float $ngn): float {
    $rate = get_usd_to_ngn_rate();
    if ($rate <= 0) return 0;
    return round($ngn / $rate, 6);
}

// ── Min / Max topup limits (in USD) ──────────────────────────
function get_min_topup_usd(): float { return (float)get_setting('min_topup_usd', '1'); }
function get_max_topup_usd(): float { return (float)get_setting('max_topup_usd', '50'); }

// ── Flash messages ───────────────────────────────────────────
function set_flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
    }
    return null;
}

// ── Site settings ────────────────────────────────────────────
function get_setting(string $key, string $default = ''): string {
    global $pdo;
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $s = $pdo->prepare("SELECT value FROM site_settings WHERE `key`=?");
    $s->execute([$key]);
    $row = $s->fetch();
    $cache[$key] = $row ? $row['value'] : $default;
    return $cache[$key];
}
function set_setting(string $key, string $value): void {
    global $pdo;
    static $cache = [];
    unset($cache[$key]); // clear static cache if used
    $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (?,?)
        ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$key, $value]);
}

// ── Wallet (balances stored in USD) ──────────────────────────
function wallet_credit(int $uid, float $amount_usd, string $desc, string $ref): bool {
    global $pdo;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$amount_usd, $uid]);
        $pdo->prepare("INSERT INTO wallet_transactions
            (user_id,type,amount,description,reference,status)
            VALUES (?,'credit',?,?,?,'success')")->execute([$uid, $amount_usd, $desc, $ref]);
        $pdo->commit(); return true;
    } catch (Exception $e) { $pdo->rollBack(); return false; }
}
function wallet_debit(int $uid, float $amount_usd, string $desc, string $ref): bool {
    global $pdo;
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
        $s->execute([$uid]); $row = $s->fetch();
        if (!$row || $row['balance'] < $amount_usd) { $pdo->rollBack(); return false; }
        $pdo->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$amount_usd, $uid]);
        $pdo->prepare("INSERT INTO wallet_transactions
            (user_id,type,amount,description,reference,status)
            VALUES (?,'debit',?,?,?,'success')")->execute([$uid, $amount_usd, $desc, $ref]);
        $pdo->commit(); return true;
    } catch (Exception $e) { $pdo->rollBack(); return false; }
}

// ── Notifications ─────────────────────────────────────────────
function notify(int $uid, string $title, string $msg, string $link = '#'): void {
    global $pdo;
    $pdo->prepare("INSERT INTO notifications (user_id,title,message,link_url) VALUES (?,?,?,?)")
        ->execute([$uid, $title, $msg, $link]);
}
function unread_count(int $uid): int {
    global $pdo;
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->execute([$uid]); return (int)$s->fetchColumn();
}

// ── Verification badges (based on lifetime USD spend) ─────────
/**
 * Adjusts a user's running lifetime spend counter (in USD).
 * Call with a positive amount on purchase, negative on refund.
 * Automatically checks for badge upgrades when spend increases.
 */
function track_spend(int $uid, float $deltaUsd): void {
    global $pdo;
    $pdo->prepare("UPDATE users SET total_spent = GREATEST(total_spent + ?, 0) WHERE id=?")
        ->execute([$deltaUsd, $uid]);
    if ($deltaUsd > 0) {
        check_badge_upgrade($uid);
    }
}

/** Current badge thresholds, admin-configurable in NGN, returned as USD. */
function badge_thresholds_usd(): array {
    return [
        1 => ngn_to_usd((float) get_setting('badge_tier1_ngn', '20000')),
        2 => ngn_to_usd((float) get_setting('badge_tier2_ngn', '50000')),
        3 => ngn_to_usd((float) get_setting('badge_tier3_ngn', '100000')),
    ];
}

/** Badge tier names shown in tooltips/admin — no admin config needed for these. */
function badge_tier_label(int $tier): string {
    $labels = [1 => 'Rising Buyer', 2 => 'Trusted Buyer', 3 => 'VIP Verified'];
    return $labels[$tier] ?? '';
}

/**
 * Upgrades (never downgrades) a user's badge tier based on total_spent.
 * Notifies the user automatically the moment they cross a new threshold.
 */
function check_badge_upgrade(int $uid): void {
    global $pdo;
    $s = $pdo->prepare("SELECT total_spent, badge_tier, username FROM users WHERE id=?");
    $s->execute([$uid]);
    $u = $s->fetch();
    if (!$u) return;

    $spent      = (float) $u['total_spent'];
    $thresholds = badge_thresholds_usd();
    $newTier    = (int) $u['badge_tier'];

    foreach ([3, 2, 1] as $tier) {
        if ($spent >= $thresholds[$tier]) { $newTier = max($newTier, $tier); break; }
    }

    if ($newTier > (int) $u['badge_tier']) {
        $pdo->prepare("UPDATE users SET badge_tier=? WHERE id=?")->execute([$newTier, $uid]);
        $label = badge_tier_label($newTier);
        notify(
            $uid,
            "You're now {$label}! 🎉",
            "Congratulations! Your purchases have earned you the {$label} verification badge.",
            '/pages/profile.php'
        );
    }
}

/**
 * Returns the small inline SVG badge icon for a tier (1=triangle, 2=circle,
 * 3=seal), purple-filled so it automatically matches light/dark theme via
 * the --primary CSS variable. Returns '' for tier 0 (no badge).
 */
function verified_badge_html(int $tier): string {
    if ($tier < 1 || $tier > 3) return '';
    $label = badge_tier_label($tier);
    $check = '<path d="M8 12.5L11 15.5L16.3 8.7" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" fill="none"/>';

    // Each shape is drawn twice: a slightly larger white "ring" copy underneath
    // for contrast/pop against any background, then the purple shape on top.
    $shapes = [
        1 => '<path d="M12 1L23 21H1L12 1Z" fill="#fff"/><path d="M12 2.5L22 20.5H2L12 2.5Z" fill="var(--primary)"/>',
        2 => '<circle cx="12" cy="12" r="10.8" fill="#fff"/><circle cx="12" cy="12" r="10" fill="var(--primary)"/>',
        3 => '<polygon points="23,12 20.06,15.36 19.71,19.71 15.36,20.06 12,23 8.64,20.06 4.29,19.71 3.94,15.36 1,12 3.94,8.64 4.29,4.29 8.64,3.94 12,1 15.36,3.94 19.71,4.29 20.06,8.64" fill="#fff"/><polygon points="22,12 19.39,15.06 19.07,19.07 15.06,19.39 12,22 8.94,19.39 4.93,19.07 4.61,15.06 2,12 4.61,8.94 4.93,4.93 8.94,4.61 12,2 15.06,4.61 19.07,4.93 19.39,8.94" fill="var(--primary)"/>',
    ];

    return '<span class="verify-badge" title="' . clean($label) . '">'
         . '<svg viewBox="0 0 24 24" width="16" height="16" xmlns="http://www.w3.org/2000/svg">'
         . $shapes[$tier] . $check
         . '</svg></span>';
}

// ── Gift Shop order notifications (in-app + email) ────────────
function notify_gift_status(array $order, string $status, string $userEmail, string $userName): void {
    $siteName = get_setting('site_name', SITE_NAME);

    $messages = [
        'Pending'   => ["Order Received 🎁", "Your gift order for {$order['product_name']} has been received and is pending confirmation."],
        'Confirmed' => ["Order Confirmed ✅", "Your gift order for {$order['product_name']} has been confirmed and is being prepared."],
        'Shipped'   => ["Order Shipped 🚚", "Your gift order for {$order['product_name']} has been shipped and is on its way to {$order['receiver_name']}."],
        'Delivered' => ["Order Delivered 📦", "Your gift order for {$order['product_name']} has been delivered."],
        'Completed' => ["Order Completed 🎉", "Your gift order for {$order['product_name']} is complete. Thank you for your order!"],
        'Cancelled' => ["Order Cancelled", "Your gift order for {$order['product_name']} was cancelled and your wallet has been refunded."],
    ];

    if (!isset($messages[$status])) return;
    [$title, $body] = $messages[$status];

    if (!empty($order['admin_note'])) {
        $body .= ' Note: ' . $order['admin_note'];
    }

    notify((int) $order['user_id'], $title, $body, '/pages/gift-orders.php');

    $html = email_template(
        '<h2 style="margin-top:0;color:#333;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>' .
        '<p>Hi <strong>' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>' .
        '<p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>' .
        '<p style="color:#999;font-size:13px;">Order #' . (int) $order['id'] . ' &middot; ' . htmlspecialchars($order['product_name'], ENT_QUOTES, 'UTF-8') . '</p>'
    );

    send_email($userEmail, $title . ' - ' . $siteName, $html);
}

// ── Admin broadcast segments ──────────────────────────────────
/**
 * Returns [id, username, email] rows for a named user segment.
 * Used by both the live count preview and the actual send action
 * on bigboss/broadcast.php, so both always agree on who matches.
 */
function get_segment_users(string $segment, int $days = 3): array {
    global $pdo;

    switch ($segment) {
        case 'no_balance':
            $sql = "SELECT id, username, email FROM users WHERE balance <= 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            break;

        case 'inactive':
            $sql = "SELECT id, username, email FROM users
                    WHERE (last_login IS NULL OR last_login < (NOW() - INTERVAL ? DAY))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$days]);
            break;

        case 'no_va':
            $sql = "SELECT id, username, email FROM users
                    WHERE id NOT IN (SELECT user_id FROM virtual_accounts)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            break;

        case 'no_purchase':
            $sql = "SELECT id, username, email FROM users
                    WHERE id NOT IN (SELECT user_id FROM otp_orders)
                      AND id NOT IN (SELECT user_id FROM log_orders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            break;

        case 'all':
        default:
            $sql = "SELECT id, username, email FROM users";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            break;
    }

    return $stmt->fetchAll();
}

function segment_label(string $segment, int $days = 3): string {
    $labels = [
        'all'         => 'All Users',
        'no_balance'  => 'Users With No Wallet Balance',
        'inactive'    => "Inactive for {$days}+ Days",
        'no_va'       => 'Never Generated an Account Number',
        'no_purchase' => 'Never Made a Purchase',
    ];
    return $labels[$segment] ?? 'Unknown Segment';
}

// ── Auth ─────────────────────────────────────────────────────

// ── Referral program ───────────────────────────────────────────
/**
 * Call this right after ANY successful deposit (Paystack or NCWallet).
 * If it's the depositor's first-ever deposit AND they were referred,
 * credits the referrer a % bonus (admin-configurable) and notifies them.
 */
function process_referral_on_deposit(int $userId, float $depositUsd): void {
    global $pdo;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT referred_by, first_deposit_at, username FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) { $pdo->rollBack(); return; }

        $isFirstDeposit = empty($u['first_deposit_at']);
        if ($isFirstDeposit) {
            $pdo->prepare("UPDATE users SET first_deposit_at = NOW() WHERE id = ?")->execute([$userId]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        return;
    }

    if ($isFirstDeposit && !empty($u['referred_by'])) {
        $pct   = (float) get_setting('referral_bonus_percent', '2');
        $bonus = round($depositUsd * ($pct / 100), 4);
        if ($bonus > 0) {
            wallet_credit(
                (int) $u['referred_by'],
                $bonus,
                "Referral bonus – {$pct}% of {$u['username']}'s first deposit",
                gen_ref('REFB')
            );
            notify(
                (int) $u['referred_by'],
                'Referral Bonus! 🎉',
                'You earned $' . number_format($bonus, 4) . ' because ' . $u['username'] . ' made their first deposit.',
                '/pages/referrals.php'
            );
        }
    }
}
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_login(): void {
    if (!is_logged_in()) { header('Location: ' . SITE_URL . '/login.php'); exit; }
}
function current_user(): ?array {
    global $pdo;
    if (!is_logged_in()) return null;
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([$_SESSION['user_id']]); return $s->fetch() ?: null;
}
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void {
    if (!is_admin()) { header('Location: ' . SITE_URL . '/' . ADMIN_PATH . '/login.php'); exit; }
}

// ── Theme CSS variables from DB settings ─────────────────────
function get_theme_css(): string {
    $primary  = get_setting('primary_color',  '#7C3AED');
    $dark     = get_setting('primary_dark',   '#5B21B6');
    $light    = get_setting('primary_light',  '#EDE9FE');
    $accent   = get_setting('accent_color',   '#A78BFA');
    $bgDark   = get_setting('bg_dark_color',  '#0F0A1E');
    return ":root {
        --primary:      {$primary};
        --primary-dark: {$dark};
        --primary-light:{$light};
        --accent:       {$accent};
        --bg-dark:      {$bgDark};
    }";
}

// ── Logo helper ───────────────────────────────────────────────
function get_logo_html(string $class = ''): string {
    $logo = get_setting('logo_path', '');
    $name = get_setting('site_name', SITE_NAME);
    if ($logo) {
        return '<img src="' . SITE_URL . '/assets/uploads/logo/' . clean($logo) . '"
                     alt="' . clean($name) . '" class="site-logo ' . $class . '">';
    }
    return '<span class="logo-text ' . $class . '">' . clean($name) . '</span>';
}

// ── Email (SMTP via native socket — no PHPMailer dependency) ─────────────────
/**
 * Send an HTML email over SMTP using credentials from config.php.
 *
 * @param string $to        Recipient email address
 * @param string $subject   Email subject
 * @param string $html_body Full HTML body of the email
 * @return bool             true on success, false on failure
 */
function send_email(string $to, string $subject, string $html_body): bool {
    $host    = defined('SMTP_HOST')   ? SMTP_HOST   : '';
    $port    = defined('SMTP_PORT')   ? SMTP_PORT   : 465;
    $user    = defined('SMTP_USER')   ? SMTP_USER   : '';
    $pass    = defined('SMTP_PASS')   ? SMTP_PASS   : '';
    $secure  = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'ssl';
    $from    = defined('SITE_EMAIL')  ? SITE_EMAIL  : $user;
    $siteName = get_setting('site_name', defined('SITE_NAME') ? SITE_NAME : 'No-Reply');

    if (!$host || !$user || !$pass) {
        error_log("[send_email] SMTP not configured. HOST={$host} USER={$user}");
        return false;
    }

    // Build the raw message
    $headers  = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: {$siteName} <{$from}>",
        "To: <{$to}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "X-Mailer: DonnieSMS/1.0",
    ]);

    $full_message = $headers . "\r\n\r\n" . $html_body;

    try {
        // Connect (SSL wraps the socket; TLS/STARTTLS upgrades after connect)
        $ctx    = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ]]);
        $scheme = ($secure === 'ssl') ? 'ssl' : 'tcp';
        $socket = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            error_log("[send_email] Cannot connect to {$scheme}://{$host}:{$port} — errno={$errno} {$errstr}");
            return false;
        }
        stream_set_timeout($socket, 15);

        $read = function() use ($socket) {
            $data = '';
            while ($line = fgets($socket, 512)) {
                $data .= $line;
                if (substr($line, 3, 1) === ' ') break; // last line of response
            }
            return $data;
        };
        $write = function(string $cmd) use ($socket) { fwrite($socket, $cmd . "\r\n"); };

        $read(); // 220 greeting

        $write("EHLO " . gethostname());
        $read();

        // STARTTLS upgrade for port 587 / tls
        if ($secure === 'tls') {
            $write("STARTTLS");
            $read();
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("[send_email] STARTTLS crypto upgrade failed for {$host}:{$port}");
                fclose($socket); return false;
            }
            $write("EHLO " . gethostname());
            $read();
        }

        // AUTH LOGIN
        $write("AUTH LOGIN");
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $auth = $read();
        if (strpos($auth, '235') === false) {
            error_log("[send_email] AUTH failed for {$user} on {$host} — response: " . trim($auth));
            fclose($socket); return false;
        }

        // Envelope
        $write("MAIL FROM:<{$from}>");
        $read();
        $write("RCPT TO:<{$to}>");
        $rcpt = $read();
        if (strpos($rcpt, '250') === false && strpos($rcpt, '251') === false) {
            error_log("[send_email] RCPT TO rejected for {$to} — response: " . trim($rcpt));
            fclose($socket); return false;
        }

        // Data
        $write("DATA");
        $read();
        fwrite($socket, $full_message . "\r\n.\r\n");
        $data_resp = $read();
        $write("QUIT");
        fclose($socket);

        $sent = strpos($data_resp, '250') !== false;
        if (!$sent) {
            error_log("[send_email] DATA response did not return 250 for {$to} — response: " . trim($data_resp));
        }
        return $sent;
    } catch (\Throwable $e) {
        error_log("[send_email] Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Build a standard branded HTML email wrapper.
 *
 * @param string $content  Inner HTML (headings, paragraphs, buttons, etc.)
 * @return string          Full HTML email document
 */
function email_template(string $content): string {
    $siteName = get_setting('site_name', defined('SITE_NAME') ? SITE_NAME : 'DonnieSMS');
    $siteUrl  = defined('SITE_URL') ? SITE_URL : '#';
    $primary  = get_setting('primary_color', '#7C3AED');
    $year     = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;max-width:600px;width:100%;">
        <!-- Header -->
        <tr>
          <td style="background:{$primary};padding:28px 40px;text-align:center;">
            <a href="{$siteUrl}" style="font-size:24px;font-weight:bold;color:#ffffff;text-decoration:none;">{$siteName}</a>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;color:#333333;font-size:15px;line-height:1.7;">
            {$content}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f4f4f7;padding:20px 40px;text-align:center;font-size:12px;color:#888888;">
            &copy; {$year} {$siteName}. All rights reserved.<br>
            <a href="{$siteUrl}" style="color:{$primary};text-decoration:none;">{$siteUrl}</a>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
