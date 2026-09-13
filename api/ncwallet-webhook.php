<?php
// ============================================================
//  NCWallet Africa – Incoming Payment Webhook
//  Save as: /api/ncwallet-webhook.php
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method Not Allowed');
}

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

// Log raw incoming webhook
global $pdo;
try {
    $pdo->prepare("INSERT INTO ncwallet_logs (payload, reference, amount, status) VALUES (?, ?, ?, ?)")
        ->execute([$rawBody, $data['ref_id'] ?? 'webhook_' . time(), $data['amount'] ?? 0, $data['status'] ?? 'received']);
} catch (Exception $e) {}

if (!$data) { http_response_code(400); exit('Invalid JSON'); }

// ── Only process successful COLLECTION events ─────────────────
$eventType = strtoupper($data['event_type'] ?? '');
$status    = strtolower($data['status'] ?? '');

if ($status !== 'success' || $eventType !== 'COLLECTION') {
    http_response_code(200);
    exit('Event ignored: ' . $eventType . ' / ' . $status);
}

// ── Extract payment details ───────────────────────────────────
$accountNumber = $data['account_number'] ?? '';
$amountNgn     = (float)($data['settled_amount'] ?? $data['amount'] ?? 0); // settled = after fee
$providerRef   = $data['ref_id'] ?? '';

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

// ── Convert NGN → USD using live rate from site_settings ─────
$rateRow = $pdo->query("SELECT value FROM site_settings WHERE `key` = 'usd_ngn_rate'")->fetch();
$rate    = (float)($rateRow['value'] ?? 1357.17); // fallback to 1357.17 if not found

if ($rate <= 0) {
    http_response_code(200); exit('Invalid exchange rate');
}

$creditAmount = round($amountNgn / $rate, 2); // NGN → USD

if ($creditAmount <= 0) {
    http_response_code(200); exit('USD conversion resulted in zero');
}

// ── Credit wallet in a transaction ───────────────────────────
try {
    $pdo->beginTransaction();

    // 1. Add USD amount to user balance
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
        ->execute([$creditAmount, $userId]);

    // 2. Insert into wallet_transactions
    $pdo->prepare("INSERT INTO wallet_transactions
        (user_id, type, amount, description, reference, status, created_at)
        VALUES (?, 'credit', ?, ?, ?, 'success', NOW())")
        ->execute([
            $userId,
            $creditAmount,
            'NCWallet deposit via ' . ($data['bank_name'] ?? 'Paga') . ' (₦' . number_format($amountNgn, 2) . ' @ ₦' . number_format($rate, 2) . '/$)',
            $providerRef,
        ]);

    // 3. Mark as processed in ncwallet_logs
    $pdo->prepare("UPDATE ncwallet_logs SET processed = 1 WHERE reference = ?")
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
