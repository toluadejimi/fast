<?php
// ============================================================
//  includes/sprintpay.php
//  SprintPay Collection API
//  Guide: https://web.sprintpay.online/collection-api
//
//  First command — redirect the customer to hosted checkout:
//    https://web.sprintpay.online/pay?amount={NGN}&key={API_KEY}&ref={REF}&email={EMAIL}
//  Verify:
//    GET https://web.sprintpay.online/api/verify-transaction?trans_id={TRANS_ID}
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

function sprintpay_get_config(): ?array {
    global $pdo;
    $gw = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='sprintpay'")->fetch();
    if (!$gw || !$gw['is_active']) return null;

    return [
        'api_key'   => trim($gw['public_key'] ?? ''),
        'secret'    => trim($gw['secret_key'] ?? ''),
        'is_active' => (bool)$gw['is_active'],
    ];
}

function sprintpay_ensure_logs_table(): void {
    global $pdo;
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sprintpay_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payload LONGTEXT,
            reference VARCHAR(191) DEFAULT NULL,
            amount DECIMAL(18,2) DEFAULT 0,
            status VARCHAR(64) DEFAULT NULL,
            processed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { /* non-fatal */ }
    $done = true;
}

function sprintpay_log(string $action, $payload, string $reference = '', float $amount = 0, string $status = ''): void {
    global $pdo;
    sprintpay_ensure_logs_table();
    try {
        $pdo->prepare("INSERT INTO sprintpay_logs (payload,reference,amount,status) VALUES (?,?,?,?)")
            ->execute([
                is_string($payload) ? $payload : json_encode($payload),
                $reference !== '' ? $reference : ($action . '_' . time()),
                $amount,
                $status !== '' ? $status : $action,
            ]);
    } catch (Exception $e) { /* non-fatal */ }
}

function sprintpay_init_payment(array $user, float $amountUsd, string $ref): array {
    $cfg = sprintpay_get_config();
    if (!$cfg) return ['success' => false, 'message' => 'SprintPay is not active.'];
    if ($cfg['api_key'] === '') {
        return ['success' => false, 'message' => 'SprintPay API key is missing. Contact admin.'];
    }

    $minUsd = get_min_topup_usd();
    $maxUsd = get_max_topup_usd();
    if ($amountUsd < $minUsd) return ['success' => false, 'message' => 'Minimum top-up is $' . $minUsd . '.'];
    if ($amountUsd > $maxUsd) return ['success' => false, 'message' => 'Maximum top-up is $' . $maxUsd . '.'];

    $rate    = get_usd_to_ngn_rate();
    $naira   = round($amountUsd * $rate, 2);
    if ($naira < 100) {
        return ['success' => false, 'message' => 'Amount is too small after conversion. Try a higher USD amount.'];
    }

    $url = 'https://web.sprintpay.online/pay?' . http_build_query([
        'amount' => $naira,
        'key'    => $cfg['api_key'],
        'ref'    => $ref,
        'email'  => $user['email'] ?? '',
    ]);

    sprintpay_log('init_pay', [
        'user_id'    => $user['id'] ?? null,
        'email'      => $user['email'] ?? '',
        'amount_usd' => $amountUsd,
        'amount_ngn' => $naira,
        'rate'       => $rate,
        'reference'  => $ref,
    ], $ref, $naira, 'initiated');

    return [
        'success'    => true,
        'url'        => $url,
        'amount_ngn' => $naira,
        'amount_usd' => $amountUsd,
        'rate'       => $rate,
    ];
}

function sprintpay_verify(string $transId): array {
    $transId = trim($transId);
    if ($transId === '') return ['success' => false, 'message' => 'Missing transaction id.'];

    $endpoint = 'https://web.sprintpay.online/api/verify-transaction?trans_id=' . urlencode($transId);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    sprintpay_log('verify', [
        'trans_id'  => $transId,
        'http_code' => $code,
        'curl_err'  => $err,
        'response'  => $res,
    ], $transId, 0, 'verify_' . $code);

    if (!$res || $err) {
        return ['success' => false, 'message' => 'SprintPay verification error: ' . ($err ?: 'empty response')];
    }

    $data = json_decode($res, true);
    if (!is_array($data)) {
        return ['success' => false, 'message' => 'SprintPay returned an invalid verification response.'];
    }

    $ok = !empty($data['status']) && (($data['data']['status'] ?? '') === 'success');
    if (!$ok) {
        return ['success' => false, 'message' => $data['message'] ?? 'SprintPay transaction is not successful.'];
    }

    $txn = $data['data'];
    $amountNgn = (float)($txn['amount'] ?? $txn['settled_amount'] ?? 0);
    $email     = trim((string)($txn['email'] ?? ''));
    $ref       = (string)($txn['trans_id'] ?? $txn['reference'] ?? $txn['ref'] ?? $transId);

    $rate      = get_usd_to_ngn_rate();
    $amountUsd = $amountNgn > 0 && $rate > 0 ? round($amountNgn / $rate, 2) : 0;

    return [
        'success'    => true,
        'amount_usd' => $amountUsd,
        'amount_ngn' => $amountNgn,
        'rate'       => $rate,
        'email'      => $email,
        'reference'  => $ref,
        'raw'        => $txn,
    ];
}

function sprintpay_already_credited(string $reference): bool {
    global $pdo;
    if ($reference === '') return false;
    $s = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference=? AND type='credit' LIMIT 1");
    $s->execute([$reference]);
    return (bool)$s->fetch();
}

function sprintpay_credit_from_ngn(int $userId, float $amountNgn, string $reference, string $source = 'SprintPay'): array {
    if ($amountNgn <= 0) return ['success' => false, 'message' => 'Invalid amount.'];
    if ($reference === '') return ['success' => false, 'message' => 'Missing reference.'];
    if (sprintpay_already_credited($reference)) {
        return ['success' => true, 'already' => true, 'message' => 'Already processed.'];
    }

    $rate = get_usd_to_ngn_rate();
    if ($rate <= 0) return ['success' => false, 'message' => 'Invalid exchange rate.'];

    $amountUsd = round($amountNgn / $rate, 2);
    if ($amountUsd <= 0) return ['success' => false, 'message' => 'USD conversion resulted in zero.'];

    $desc = sprintf(
        '%s deposit (₦%s @ ₦%s/$ = $%s)',
        $source,
        number_format($amountNgn, 2),
        number_format($rate, 2),
        number_format($amountUsd, 2)
    );

    if (!wallet_credit($userId, $amountUsd, $desc, $reference)) {
        return ['success' => false, 'message' => 'Failed to credit wallet.'];
    }

    process_referral_on_deposit($userId, $amountUsd);
    notify(
        $userId,
        'Wallet Funded',
        '$' . number_format($amountUsd, 2) . ' added via ' . $source . ' (₦' . number_format($amountNgn, 2) . ').',
        '/pages/fund-wallet.php'
    );

    return [
        'success'    => true,
        'already'    => false,
        'amount_usd' => $amountUsd,
        'amount_ngn' => $amountNgn,
        'rate'       => $rate,
    ];
}
