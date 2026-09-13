<?php
// ============================================================
//  DonnieSMS OTP – includes/ncwallet.php  (UPGRADED)
//  - NCWallet Africa virtual account fixed with correct payload
//  - Paystack Wema Bank VA creation
//  - All NGN amounts converted to USD on wallet credit
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

// ── Fetch NCWallet gateway settings from DB ───────────────────
function ncwallet_get_config(): ?array {
    global $pdo;
    $gw = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='ncwallet'")->fetch();
    if (!$gw || !$gw['is_active']) return null;

    // ── CRITICAL: Normalise the base URL ──────────────────────
    // The admin may have saved just "https://ncwallet.africa" or
    // "https://www.ncwallet.africa" without the /api/v1 path.
    // The official NCWallet Africa endpoint is ALWAYS:
    //   https://ncwallet.africa/api/v1/bank/create
    // So we strip any saved value and force the correct base.
    $savedUrl = trim($gw['extra2'] ?? '');

    // Remove trailing slash, then check whether /api/v1 is present
    $savedUrl = rtrim($savedUrl, '/');
    if (empty($savedUrl)) {
        $baseUrl = 'https://ncwallet.africa/api/v1';
    } elseif (strpos($savedUrl, '/api/v1') !== false) {
        // Already has the path – use as-is (strip any extra trailing slash)
        $baseUrl = $savedUrl;
    } else {
        // Bare domain stored (e.g. https://ncwallet.africa or https://www.ncwallet.africa)
        // Strip www. subdomain if present, then append the correct path
        $baseUrl = preg_replace('#^(https?://)www\.#i', '$1', $savedUrl);
        $baseUrl .= '/api/v1';
    }

    return [
        'api_key'       => trim($gw['public_key']),
        'secret_key'    => trim($gw['secret_key']),
        'txn_pin'       => trim($gw['txn_pin'] ?? ''),
        'business_name' => trim($gw['business_name'] ?? ''),
        'base_url'      => $baseUrl,   // always https://ncwallet.africa/api/v1
        'is_active'     => (bool)$gw['is_active'],
    ];
}

// ── NCWallet Africa – Generate Virtual Account ────────────────
// Payload structure per NCWallet Africa official documentation.
// validation_number 22680128143 is the bypass BVN provided by NCWallet Africa.
function ncwallet_generate(array $user): array {
    $cfg = ncwallet_get_config();
    if (!$cfg) return ['success' => false, 'message' => 'NCWallet not configured or inactive.'];

    $reference = 'VA_' . $user['id'] . '_' . time();

    // account_name: use full_name if available, otherwise username uppercased.
    $accountName = strtoupper(trim($user['full_name'] ?? $user['username'] ?? 'User'));

    // phone_number: NCWallet requires a Nigerian phone number.
    // Use the user's stored phone; if missing fall back to a valid placeholder.
    $phone = trim($user['phone'] ?? '');
    if (empty($phone)) {
        $phone = '08000000000'; // generic fallback so the API does not reject
    }
    // Ensure no spaces in phone number
    $phone = preg_replace('/\s+/', '', $phone);

    // ── Official payload per NCWallet Africa docs (static + palmpay) ──
    $payload = [
        "ref_id"            => $reference,
        "bank_code"         => "paga",        // static accounts: paga, 9psb, nomba, palmpay
        "account_name"      => $accountName,
        "email"             => $user['email'],
        "phone_number"      => $phone,
        "account_type"      => "static",
        "validation_type"   => "BVN",
        "validation_number" => "22680128143",    // bypass BVN supplied by NCWallet Africa
    ];

    // ── Correct endpoint: base_url already normalised to https://ncwallet.africa/api/v1
    $baseUrl  = rtrim($cfg['base_url'], '/');
    $endpoint = $baseUrl . '/bank/create';       // → https://ncwallet.africa/api/v1/bank/create

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            // NCWallet Africa: Authorization header uses the SECRET key (ncsk_...), NOT the public key (ncpk_...)
            // as shown in their official curl examples e.g. sandbox_ncsk_90eff8a5e2250d...
            'Authorization: ' . $cfg['secret_key'],
            // trnx_pin is a required standalone header (not part of the JSON body)
            'trnx_pin: ' . $cfg['txn_pin'],
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // Follow redirects in case ncwallet.africa redirects to/from www
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    // Log for debugging
    ncwallet_log_request('create_va', $endpoint, $payload, $res, $code, $err);

    if (!$res || $err) return ['success' => false, 'message' => 'NCWallet connection error: ' . $err];

    $data = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'NCWallet returned invalid JSON. HTTP ' . $code . '. URL: ' . $endpoint];
    }

    // Official response: { status:"success", message, event_type, ref_id, data:{ account_number, account_name, bank_name, bank_code } }
    $isSuccess = ($data['status'] ?? '') === 'success' && !empty($data['data']['account_number']);

    if ($isSuccess) {
        return [
            'success'        => true,
            'provider'       => 'ncwallet',
            'account_number' => $data['data']['account_number'],
            'account_name'   => $data['data']['account_name']  ?? $accountName,
            'bank_name'      => $data['data']['bank_name']      ?? 'Paga',
            'provider_ref'   => $data['ref_id']                 ?? $reference,
        ];
    }

    $msg = $data['message'] ?? ('NCWallet error. HTTP ' . $code);
    return ['success' => false, 'message' => $msg];
}

// ── Paystack – Create Wema Bank Dedicated Virtual Account ─────
function paystack_create_va(array $user): array {
    global $pdo;
    $gw = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paystack'")->fetch();
    if (!$gw || !$gw['is_active']) return ['success' => false, 'message' => 'Paystack not active.'];

    // Step 1: Create customer on Paystack (required for dedicated VA)
    $custPayload = json_encode([
        'email'      => $user['email'],
        'first_name' => $user['username'],
        'last_name'  => '',
        'phone'      => $user['phone'] ?? '',
    ]);

    $ch = curl_init('https://api.paystack.co/customer');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $custPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $gw['secret_key'],
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $custRes  = curl_exec($ch);
    curl_close($ch);
    $custData = json_decode($custRes, true);

    $customerCode = $custData['data']['customer_code'] ?? null;

    // If customer already exists, fetch their code
    if (!$customerCode && isset($custData['message']) && str_contains($custData['message'], 'Customer already exists')) {
        $ch2 = curl_init('https://api.paystack.co/customer/' . urlencode($user['email']));
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $gw['secret_key']],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $existRes  = curl_exec($ch2);
        curl_close($ch2);
        $existData = json_decode($existRes, true);
        $customerCode = $existData['data']['customer_code'] ?? null;
    }

    if (!$customerCode) {
        return ['success' => false, 'message' => 'Could not create/fetch Paystack customer.'];
    }

    // Step 2: Create dedicated virtual account (Wema Bank)
    $vaPayload = json_encode([
        'customer'       => $customerCode,
        'preferred_bank' => 'wema-bank',
    ]);

    $ch = curl_init('https://api.paystack.co/dedicated_account');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $vaPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $gw['secret_key'],
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$res) return ['success' => false, 'message' => 'No response from Paystack.'];
    $data = json_decode($res, true);

    if ($data && $data['status'] && !empty($data['data']['account_number'])) {
        return [
            'success'        => true,
            'provider'       => 'paystack',
            'account_number' => $data['data']['account_number'],
            'account_name'   => $data['data']['account_name'],
            'bank_name'      => $data['data']['bank']['name'] ?? 'Wema Bank',
            'provider_ref'   => (string)($data['data']['id'] ?? 'PS_' . $user['id']),
        ];
    }
    return ['success' => false, 'message' => $data['message'] ?? 'Paystack VA creation failed.'];
}

// ── Virtual Account DB helpers ────────────────────────────────
function get_user_va(int $userId): ?array {
    global $pdo;
    $s = $pdo->prepare("SELECT * FROM virtual_accounts WHERE user_id=?");
    $s->execute([$userId]); return $s->fetch() ?: null;
}

function save_user_va(int $userId, array $d): bool {
    global $pdo;
    $s = $pdo->prepare("INSERT INTO virtual_accounts
        (user_id,account_number,account_name,bank_name,provider,provider_ref)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
        account_number=VALUES(account_number),account_name=VALUES(account_name),
        bank_name=VALUES(bank_name),provider=VALUES(provider),provider_ref=VALUES(provider_ref)");
    return $s->execute([
        $userId,
        $d['account_number'],
        $d['account_name'],
        $d['bank_name'],
        $d['provider'],
        $d['provider_ref'],
    ]);
}

// ── Paystack – Initialize card/USSD payment (amount in NGN) ──
// Users enter USD amount → convert to NGN → charge in NGN
function paystack_init_payment(array $user, float $amount_usd, string $ref): array {
    global $pdo;
    $gw = $pdo->query("SELECT * FROM payment_gateways WHERE gateway='paystack'")->fetch();
    if (!$gw || !$gw['is_active']) return ['success' => false, 'message' => 'Paystack not active.'];

    $rate     = get_usd_to_ngn_rate();
    $naira    = round($amount_usd * $rate, 2);
    $kobo     = (int)($naira * 100);
    $minUsd   = get_min_topup_usd();
    $maxUsd   = get_max_topup_usd();

    if ($amount_usd < $minUsd) return ['success' => false, 'message' => 'Minimum top-up is $' . $minUsd . '.'];
    if ($amount_usd > $maxUsd) return ['success' => false, 'message' => 'Maximum top-up is $' . $maxUsd . '.'];

    // Store the USD amount in metadata so callback can credit correctly
    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'email'        => $user['email'],
            'amount'       => $kobo,
            'reference'    => $ref,
            'currency'     => 'NGN',
            'callback_url' => SITE_URL . '/api/paystack-callback.php',
            'metadata'     => [
                'amount_usd'  => $amount_usd,
                'usd_ngn_rate'=> $rate,
                'user_id'     => $user['id'],
                'custom_fields' => [
                    ['display_name' => 'USD Amount', 'variable_name' => 'amount_usd', 'value' => '$' . $amount_usd],
                    ['display_name' => 'Rate',       'variable_name' => 'rate',       'value' => '₦' . $rate . '/USD'],
                ],
            ],
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $gw['secret_key'],
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $d   = json_decode($res, true);
    if ($d && $d['status']) {
        return [
            'success'   => true,
            'url'       => $d['data']['authorization_url'],
            'amount_ngn'=> $naira,
            'amount_usd'=> $amount_usd,
            'rate'      => $rate,
        ];
    }
    return ['success' => false, 'message' => $d['message'] ?? 'Paystack init failed.'];
}

// ── Paystack – Verify transaction ─────────────────────────────
function paystack_verify(string $ref): array {
    global $pdo;
    $gw = $pdo->query("SELECT secret_key FROM payment_gateways WHERE gateway='paystack'")->fetch();
    $ch = curl_init('https://api.paystack.co/transaction/verify/' . urlencode($ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . ($gw['secret_key'] ?? '')],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $d   = json_decode($res, true);

    if ($d && $d['status'] && ($d['data']['status'] ?? '') === 'success') {
        $amountNgn = $d['data']['amount'] / 100;
        $email     = $d['data']['customer']['email'];
        // Prefer the USD amount stored in metadata; fallback: convert
        $amountUsd = (float)($d['data']['metadata']['amount_usd'] ?? 0);
        $rate      = (float)($d['data']['metadata']['usd_ngn_rate'] ?? 0);
        if ($amountUsd <= 0) {
            $rate      = get_usd_to_ngn_rate();
            $amountUsd = $rate > 0 ? round($amountNgn / $rate, 6) : 0;
        }
        return [
            'success'    => true,
            'amount_usd' => $amountUsd,
            'amount_ngn' => $amountNgn,
            'rate'       => $rate,
            'email'      => $email,
        ];
    }
    return ['success' => false, 'message' => $d['message'] ?? 'Verification failed.'];
}

// ── Debug logger for NCWallet ─────────────────────────────────
function ncwallet_log_request(string $action, string $url, array $payload, $response, int $code, string $err = ''): void {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO ncwallet_logs (payload,reference,amount,status) VALUES (?,?,?,?)")
            ->execute([
                json_encode([
                    'action'   => $action,
                    'url'      => $url,
                    'payload'  => $payload,
                    'response' => $response,
                    'http_code'=> $code,
                    'curl_err' => $err,
                ]),
                $action . '_' . time(),
                0,
                'debug_' . $code,
            ]);
    } catch (Exception $e) { /* non-fatal */ }
}