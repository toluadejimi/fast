<?php
// ============================================================
//  PaymentPoint – Incoming Payment Webhook
//  Save as: /api/paymentpoint-webhook.php
//  Set this URL in the PaymentPoint dashboard as your webhook.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method Not Allowed');
}

global $pdo;

$rawBody   = file_get_contents('php://input');
$data      = json_decode($rawBody, true);
$signature = $_SERVER['HTTP_PAYMENTPOINT_SIGNATURE'] ?? '';

// ── Log raw incoming webhook ────────────────────────────────────
try {
    $pdo->prepare("INSERT INTO paymentpoint_logs (payload, reference, amount, status) VALUES (?, ?, ?, ?)")
        ->execute([$rawBody, $data['transaction_id'] ?? 'webhook_' . time(), $data['amount_paid'] ?? 0, $data['notification_status'] ?? 'received']);
} catch (Exception $e) {}

if (!$data) { http_response_code(400); exit('Invalid JSON'); }

// ── Verify signature (HMAC-SHA256 of the raw body, keyed with the secret key) ──
$gw = $pdo->query("SELECT secret_key FROM payment_gateways WHERE gateway='paymentpoint'")->fetch();
$secretKey = trim($gw['secret_key'] ?? '');

if ($secretKey !== '' && $signature !== '') {
    $expected = hash_hmac('sha256', $rawBody, $secretKey);
    if (!hash_equals($expected, $signature)) {
        http_response_code(400); exit('Invalid signature.');
    }
}

// ── Only process successful payments ────────────────────────────
$notifStatus = strtolower($data['notification_status'] ?? '');
$txnStatus   = strtolower($data['transaction_status'] ?? '');

if ($notifStatus !== 'payment_successful' || $txnStatus !== 'success') {
    http_response_code(200);
    exit('Event ignored: ' . $notifStatus . ' / ' . $txnStatus);
}

// ── Extract payment details ───────────────────────────────────
$accountNumber = $data['receiver']['account_number'] ?? '';
$amountNgn     = (float)($data['settlement_amount'] ?? $data['amount_paid'] ?? 0); // settled = after fee
$providerRef   = $data['transaction_id'] ?? '';

if (empty($accountNumber) || $amountNgn <= 0) {
    http_response_code(200); exit('Missing data');
}

// ── Find user by virtual account number ──────────────────────
$stmt = $pdo->prepare("SELECT user_id FROM virtual_accounts WHERE account_number = ?");
$stmt->execute([$accountNumber]);
$va = $stmt->fetch();

if (!$va) {
    http_response_code(200); exit('Account not found: ' . $accountNumber);
}
$userId = (int)$va['user_id'];

// ── Prevent duplicate crediting ───────────────────────────────
if (!empty($providerRef)) {
    $dup = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference = ? AND type = 'credit'");
    $dup->execute([$providerRef]);
    if ($dup->fetch()) {
        http_response_code(200); exit('Already processed: ' . $providerRef);
    }
}

// ── Convert NGN → USD using live rate ─────────────────────────
$rate = get_usd_to_ngn_rate();
if ($rate <= 0) {
    http_response_code(200); exit('Invalid exchange rate');
}

$creditAmount = round($amountNgn / $rate, 2);
if ($creditAmount <= 0) {
    http_response_code(200); exit('USD conversion resulted in zero');
}

// ── Credit wallet in a transaction ─────────────────────────────
try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
        ->execute([$creditAmount, $userId]);

    $pdo->prepare("INSERT INTO wallet_transactions
        (user_id, type, amount, description, reference, status, created_at)
        VALUES (?, 'credit', ?, ?, ?, 'success', NOW())")
        ->execute([
            $userId,
            $creditAmount,
            'PaymentPoint deposit via ' . ($data['receiver']['bank'] ?? 'Bank Transfer') . ' (₦' . number_format($amountNgn, 2) . ' @ ₦' . number_format($rate, 2) . '/$)',
            $providerRef,
        ]);

    $pdo->prepare("UPDATE paymentpoint_logs SET processed = 1 WHERE reference = ?")
        ->execute([$providerRef]);

    $pdo->commit();

    process_referral_on_deposit($userId, $creditAmount);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit('DB error: ' . $e->getMessage());
}

http_response_code(200);
echo json_encode(['status' => 'success', 'credited_usd' => $creditAmount, 'amount_ngn' => $amountNgn, 'rate' => $rate]);
exit;
