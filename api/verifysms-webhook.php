<?php
// ============================================================
//  DonnieSMS OTP - api/verifysms-webhook.php
//  VerifySMS pushes OTP codes here automatically when received.
//  Set this URL in your VerifySMS profile → Webhook URL:
//    https://donniesms.com/api/verifysms-webhook.php
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/functions.php';

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ── Read raw body ─────────────────────────────────────────────
$rawBody = file_get_contents('php://input');

// ── Verify our API key is configured ─────────────────────────
$ourApiKey = get_setting('verifysms_api_key', defined('VERIFYSMS_API_KEY') ? VERIFYSMS_API_KEY : '');

// ── Parse JSON payload ────────────────────────────────────────
// VerifySMS webhook payload (inferred from API structure):
// {
//   "transaction_id": "b3f0cdaa-d50f-409d-b032-08bb61671379",
//   "code": "422316",
//   "number": "4345122324",
//   "service": "Signal"
// }
// Some providers also send:  "api_key": "...", "event": "code_received"
$data = json_decode($rawBody, true);

// Log raw payload for debugging (30-day auto-cleanup via DB)
try {
    $pdo->prepare(
        "INSERT INTO site_settings (`key`, `value`)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
    )->execute([
        'verifysms_last_webhook_' . date('YmdHis'),
        substr($rawBody, 0, 2000)
    ]);
} catch (Exception $e) {
    // Non-fatal — log silently
}

// ── Validate payload ──────────────────────────────────────────
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid JSON');
}

$transactionId = trim($data['transaction_id'] ?? '');
$code          = trim($data['code']           ?? '');

// VerifySMS may also send the code in these alternate keys
if ($code === '') {
    $code = trim($data['otp']  ?? '');
}
if ($code === '') {
    $code = trim($data['sms']  ?? '');
}
if ($code === '') {
    $code = trim($data['text'] ?? '');
}

// If still no code, try to extract digits from any text field
if ($code === '') {
    $searchIn = $rawBody;
    if (preg_match('/\b(\d{4,8})\b/', $searchIn, $m)) {
        $code = $m[1];
    }
}

// Must have at minimum a transaction_id
if ($transactionId === '') {
    http_response_code(400);
    exit('Missing transaction_id');
}

// ── Validate API key if VerifySMS sends it ────────────────────
// Some webhook senders include the API key for verification.
// If present, check it matches ours. If absent, skip (not required).
if (isset($data['api_key']) && $ourApiKey !== '') {
    if (trim($data['api_key']) !== $ourApiKey) {
        http_response_code(401);
        exit('Unauthorized');
    }
}

// ── Find the matching order ───────────────────────────────────
// order_id column stores the VerifySMS transaction_id
$s = $pdo->prepare("
    SELECT *
    FROM otp_orders
    WHERE order_id  = ?
    AND   operator  = 'verifysms'
    AND   status    = 'PENDING'
    LIMIT 1
");
$s->execute([$transactionId]);
$order = $s->fetch();

if (!$order) {
    // Could be already received, expired, or not found — respond 200 to stop retries
    http_response_code(200);
    exit('OK');
}

// ── Save OTP code ─────────────────────────────────────────────
if ($code !== '') {

    $pdo->prepare("
        UPDATE otp_orders
        SET otp_code = ?,
            status   = 'RECEIVED'
        WHERE id = ?
    ")->execute([$code, $order['id']]);

    // Notify the user
    notify(
        $order['user_id'],
        'OTP Received',
        'OTP for ' . $order['service_name'] . ': ' . $code,
        '/pages/otp-active.php?id=' . $order['id']
    );

} else {

    // Webhook fired but no code in payload yet — just acknowledge.
    // The polling fallback in check-otp.php will catch it.
    http_response_code(200);
    exit('OK — no code yet');
}

// ── Respond 200 to VerifySMS ──────────────────────────────────
http_response_code(200);
echo 'OK';
