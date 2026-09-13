<?php
// ============================================================
// DonnieSMS OTP - includes/vtu_helper.php
//
// Shared order pattern for every VTU service (Airtime, Data,
// Cable, Electricity):
//   1. Create pending order row (amount stored in both NGN and
//      USD, since VTUnaija prices in NGN but your wallet is USD)
//   2. Debit wallet at the USD-equivalent selling price
//   3. Call the VTUnaija API
//   4. Success -> mark success, track_spend() for badges
//   5. Failure -> refund wallet, mark refunded
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/vtunaija.php';

/** Applies a service's markup % to a NGN cost price. */
function vtu_selling_price(float $costNgn, float $markupPercent): float
{
    return round($costNgn * (1 + $markupPercent / 100), 2);
}

/**
 * $apiCall is a closure that takes no args and returns the normalized
 * VTUnaijaAPI response array (['ok', 'raw', 'message', 'vtunaija_id', 'extra']).
 */
function vtu_process_order(
    int $userId,
    string $serviceCode,
    string $refPrefix,
    string $recipient,
    float $sellingNgn,
    float $costNgn,
    ?string $providerName,
    ?string $planName,
    callable $apiCall
): array {
    global $pdo;

    $sellingUsd = ngn_to_usd($sellingNgn);
    $reference  = gen_ref($refPrefix);

    $stmt = $pdo->prepare(
        "INSERT INTO vtu_orders (user_id, service_code, provider_name, plan_name, reference, recipient, amount_ngn, cost_ngn, amount_usd, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$userId, $serviceCode, $providerName, $planName, $reference, $recipient, $sellingNgn, $costNgn, $sellingUsd]);
    $orderId = (int) $pdo->lastInsertId();

    $debited = wallet_debit($userId, $sellingUsd, ucfirst($serviceCode) . " purchase for {$recipient}", $reference);

    if (!$debited) {
        $pdo->prepare("UPDATE vtu_orders SET status='failed', api_response=? WHERE id=?")
            ->execute(['Wallet debit failed — insufficient balance.', $orderId]);
        return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
    }

    $result = $apiCall();

    if ($result['ok']) {
        $pdo->prepare("UPDATE vtu_orders SET status='success', api_response=?, extra_data=?, vtunaija_id=? WHERE id=?")
            ->execute([$result['message'], json_encode($result['extra']), $result['vtunaija_id'], $orderId]);

        track_spend($userId, $sellingUsd);

        notify($userId, 'VTU Purchase Successful', ucfirst($serviceCode) . " purchase for {$recipient} was successful.", '/pages/vtu-history.php');

        return ['ok' => true, 'message' => $result['message'], 'extra' => $result['extra']];
    }

    // Failed — attempt refund, and verify it actually landed before telling the user it did.
    // Log the RAW provider message (this is where "insufficient balance" from VTUnaija's own
    // account gets confused with the user's wallet) — the user only ever sees a generic message.
    error_log("[VTU] {$serviceCode} order #{$orderId} failed. Provider message: " . $result['message']);

    $refunded = wallet_credit($userId, $sellingUsd, ucfirst($serviceCode) . " refund for {$recipient}", $reference . '-RF');
    if (!$refunded) {
        // Transient DB contention — try once more before giving up.
        $refunded = wallet_credit($userId, $sellingUsd, ucfirst($serviceCode) . " refund for {$recipient}", $reference . '-RF2');
    }

    if ($refunded) {
        $pdo->prepare("UPDATE vtu_orders SET status='refunded', api_response=? WHERE id=?")
            ->execute([$result['message'], $orderId]);
        notify($userId, 'VTU Purchase Failed & Refunded', 'This service is temporarily unavailable. Your wallet has been refunded — please try again shortly.', '/pages/vtu-history.php');
        return ['ok' => false, 'message' => 'This service is temporarily unavailable. Your wallet has been refunded — please try again shortly.'];
    }

    // Refund itself failed after retrying — do NOT tell the user they were refunded.
    // Flag it clearly so admin can see and manually refund from VTU Orders.
    error_log("[VTU] URGENT: refund FAILED for order #{$orderId}, user #{$userId}, amount \${$sellingUsd}. Needs manual refund.");
    $pdo->prepare("UPDATE vtu_orders SET status='failed', api_response=? WHERE id=?")
        ->execute(['REFUND FAILED — needs manual admin refund. Provider message: ' . $result['message'], $orderId]);
    notify($userId, 'VTU Purchase Failed', 'Your purchase failed and there was an issue processing your refund automatically. Please contact support with reference ' . $reference . ' — our team will resolve this promptly.', '/pages/support.php');

    return ['ok' => false, 'message' => 'Purchase failed. There was an issue with your automatic refund — please contact support with reference ' . $reference . ' so our team can resolve it.'];
}

/** Simple Nigerian phone number check (11 digits starting with 0). */
function vtu_is_valid_phone(string $phone): bool
{
    return (bool) preg_match('/^0\d{10}$/', $phone);
}
