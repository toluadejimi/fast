<?php
// ============================================================
//  includes/paymentpoint.php
//  PaymentPoint – Main payment gateway
//  – Generates a dedicated virtual account for a user on either
//    PalmPay or OPay, backed by PaymentPoint's Reserved Account API
//  – Docs: https://docs.paymentpoint.co
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

// Partner bank codes per PaymentPoint docs
const PAYMENTPOINT_BANK_CODES = [
    'palmpay' => '20946',
    'opay'    => '20897',
];

// ── Fetch PaymentPoint gateway settings from DB ────────────────
// public_key  -> PaymentPoint "api-key" header
// secret_key  -> PaymentPoint Bearer token (Authorization header)
// extra       -> PaymentPoint Business ID
function paymentpoint_get_config(): ?array {
    global $pdo;
    $gw = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paymentpoint'")->fetch();
    if (!$gw || !$gw['is_active']) return null;

    return [
        'api_key'     => trim($gw['public_key'] ?? ''),
        'secret_key'  => trim($gw['secret_key'] ?? ''),
        'business_id' => trim($gw['extra'] ?? ''),
        'is_active'   => (bool)$gw['is_active'],
    ];
}

// ── PaymentPoint – Generate Virtual Account ─────────────────────
// $bank must be 'palmpay' or 'opay'
function paymentpoint_generate(array $user, string $bank = 'palmpay'): array {
    $cfg = paymentpoint_get_config();
    if (!$cfg) return ['success' => false, 'message' => 'PaymentPoint not configured or inactive.'];
    if (empty($cfg['api_key']) || empty($cfg['secret_key']) || empty($cfg['business_id'])) {
        return ['success' => false, 'message' => 'PaymentPoint API keys / Business ID missing. Contact admin.'];
    }

    $bank = strtolower($bank) === 'opay' ? 'opay' : 'palmpay';
    $bankCode = PAYMENTPOINT_BANK_CODES[$bank];

    $accountName = trim($user['full_name'] ?? $user['username'] ?? 'User');
    if ($accountName === '') $accountName = $user['username'] ?? 'User';

    $phone = preg_replace('/\s+/', '', trim($user['phone'] ?? ''));
    if (empty($phone)) $phone = '08000000000';

    $payload = [
        'email'       => $user['email'],
        'name'        => $accountName,
        'phoneNumber' => $phone,
        'bankCode'    => [$bankCode],
        'businessId'  => $cfg['business_id'],
    ];

    $endpoint = 'https://api.paymentpoint.co/api/v1/createVirtualAccount';

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $cfg['secret_key'],
            'api-key: ' . $cfg['api_key'],
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    paymentpoint_log_request('create_va', $endpoint, $payload, $res, $code, $err);

    if (!$res || $err) return ['success' => false, 'message' => 'PaymentPoint connection error: ' . $err];

    $data = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'PaymentPoint returned invalid response. HTTP ' . $code];
    }

    $account = $data['bankAccounts'][0] ?? null;
    $isSuccess = (($data['status'] ?? '') === 'success') && !empty($account['accountNumber']);

    if ($isSuccess) {
        return [
            'success'        => true,
            'provider'       => 'paymentpoint',
            'account_number' => $account['accountNumber'],
            'account_name'   => $account['accountName'] ?? $accountName,
            'bank_name'      => $account['bankName'] ?? ucfirst($bank),
            'provider_ref'   => $account['Reserved_Account_Id'] ?? ($data['customer']['customer_id'] ?? ('PP_' . $user['id'])),
        ];
    }

    $msg = $data['message'] ?? (!empty($data['errors']) ? implode(', ', (array)$data['errors']) : 'PaymentPoint error. HTTP ' . $code);
    return ['success' => false, 'message' => $msg];
}

// ── Debug logger for PaymentPoint ───────────────────────────────
function paymentpoint_log_request(string $action, string $url, array $payload, $response, int $code, string $err = ''): void {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO paymentpoint_logs (payload,reference,amount,status) VALUES (?,?,?,?)")
            ->execute([
                json_encode([
                    'action'    => $action,
                    'url'       => $url,
                    'payload'   => $payload,
                    'response'  => $response,
                    'http_code' => $code,
                    'curl_err'  => $err,
                ]),
                $action . '_' . time(),
                0,
                'debug_' . $code,
            ]);
    } catch (Exception $e) { /* non-fatal */ }
}
