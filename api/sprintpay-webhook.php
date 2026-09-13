<?php
// ============================================================
//  SprintPay Collection API – incoming webhook
//  Paste this URL in the SprintPay dashboard.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/sprintpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$raw  = file_get_contents('php://input');
$json = json_decode($raw, true);
$data = is_array($json) ? $json : $_POST;

$transId = trim((string)($data['trans_id'] ?? $data['order_id'] ?? $data['session_id'] ?? $data['reference'] ?? $data['ref'] ?? ''));
$amount  = (float)($data['amount'] ?? $data['settled_amount'] ?? 0);
$status  = strtolower(trim((string)($data['status'] ?? 'success')));
$email   = strtolower(trim((string)($data['email'] ?? '')));

sprintpay_log('webhook', $raw !== '' ? $raw : $data, $transId, $amount, $status);

if ($transId === '' || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

if ($status !== 'success') {
    http_response_code(200);
    echo json_encode(['message' => 'Transaction not successful']);
    exit;
}

$userRow = null;
if ($email !== '') {
    $s = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $s->execute([$email]);
    $userRow = $s->fetch() ?: null;
}
if (!$userRow) {
    $verified = sprintpay_verify($transId);
    if ($verified['success'] && !empty($verified['email'])) {
        $s = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $s->execute([$verified['email']]);
        $userRow = $s->fetch() ?: null;
        if ($amount <= 0 && !empty($verified['amount_ngn'])) {
            $amount = (float)$verified['amount_ngn'];
        }
    }
}

if (!$userRow) {
    http_response_code(200);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$credit = sprintpay_credit_from_ngn((int)$userRow['id'], $amount, $transId, 'SprintPay');
if (!$credit['success']) {
    http_response_code(500);
    echo json_encode(['error' => $credit['message']]);
    exit;
}

http_response_code(200);
echo json_encode([
    'status'       => 'success',
    'already'      => !empty($credit['already']),
    'credited_usd' => $credit['amount_usd'] ?? 0,
    'amount_ngn'   => $amount,
]);
exit;
