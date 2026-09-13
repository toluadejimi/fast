<?php
// ============================================================
//  api/generate-va.php  (UPGRADED — PaymentPoint added as main)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/ncwallet.php';
require_once ROOT_PATH . '/includes/paymentpoint.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user     = current_user();
$provider = clean($_POST['provider'] ?? 'paymentpoint');
$bank     = clean($_POST['bank'] ?? 'palmpay'); // used only when provider=paymentpoint: 'palmpay' or 'opay'

if (!country_supports_ncwallet($user['country'] ?? 'Nigeria')) {
    echo json_encode(['success' => false, 'message' => 'Bank transfer funding is only available for Nigerian accounts. Please use card funding instead.']);
    exit;
}

// Already has VA?
$existing = get_user_va($user['id']);
if ($existing) {
    echo json_encode([
        'success'        => true,
        'account_number' => $existing['account_number'],
        'bank_name'      => $existing['bank_name'],
        'account_name'   => $existing['account_name'],
        'provider'       => $existing['provider'],
    ]);
    exit;
}

// Add full_name fallback
if (empty($user['full_name'])) {
    $user['full_name'] = $user['username'];
}

if ($provider === 'paymentpoint') {
    $result = paymentpoint_generate($user, $bank);
} elseif ($provider === 'paystack') {
    $result = paystack_create_va($user);
} else {
    $result = ncwallet_generate($user);
}

if ($result['success']) {
    save_user_va($user['id'], $result);
    echo json_encode([
        'success'        => true,
        'account_number' => $result['account_number'],
        'bank_name'      => $result['bank_name'],
        'account_name'   => $result['account_name'],
        'provider'       => $result['provider'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
