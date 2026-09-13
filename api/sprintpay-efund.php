<?php
// ============================================================
//  SprintPay Collection API – e-fund (credit wallet)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/sprintpay.php';

header('Content-Type: application/json');

$raw  = json_decode(file_get_contents('php://input'), true);
$data = is_array($raw) ? $raw : $_REQUEST;

$email   = strtolower(trim((string)($data['email'] ?? '')));
$amount  = (float)($data['amount'] ?? 0);
$orderId = trim((string)($data['order_id'] ?? $data['trans_id'] ?? $data['reference'] ?? $data['ref'] ?? ''));

sprintpay_log('e-fund', $data, $orderId, $amount, 'e-fund');

if ($email === '' || $amount <= 0 || $orderId === '') {
    echo json_encode(['status' => false, 'message' => 'Missing email, amount or order_id']);
    exit;
}

$s = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
$s->execute([$email]);
$user = $s->fetch();

if (!$user) {
    echo json_encode(['status' => false, 'message' => 'No user found']);
    exit;
}

$credit = sprintpay_credit_from_ngn((int)$user['id'], $amount, $orderId, 'SprintPay');
if (!$credit['success']) {
    echo json_encode(['status' => false, 'message' => $credit['message']]);
    exit;
}

$usd = number_format((float)($credit['amount_usd'] ?? 0), 2);
echo json_encode([
    'status'  => true,
    'message' => !empty($credit['already'])
        ? 'Transaction already processed'
        : ('$' . $usd . ' has been successfully added to your wallet'),
]);
