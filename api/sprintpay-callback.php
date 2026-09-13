<?php
// ============================================================
//  SprintPay Collection API – customer return / verify URL
//  SprintPay redirects here with trans_id, amount, status
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/sprintpay.php';

$transId = trim((string)($_GET['trans_id'] ?? $_GET['reference'] ?? $_GET['ref'] ?? ''));
$amount  = (float)($_GET['amount'] ?? 0);
$status  = strtolower(trim((string)($_GET['status'] ?? '')));

sprintpay_log('callback', $_GET, $transId, $amount, $status !== '' ? $status : 'callback');

if ($transId === '') {
    set_flash('error', 'Invalid SprintPay payment reference.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

if ($status !== '' && $status !== 'success') {
    set_flash('error', 'SprintPay transaction was not successful.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

if (sprintpay_already_credited($transId)) {
    set_flash('info', 'Payment already processed.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

$result = sprintpay_verify($transId);
if (!$result['success']) {
    set_flash('error', 'Payment verification failed: ' . $result['message']);
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

$amountNgn = (float)$result['amount_ngn'];
if ($amountNgn <= 0 && $amount > 0) $amountNgn = $amount;

$email = trim((string)$result['email']);
$ref   = $result['reference'] ?: $transId;

$userRow = null;
if ($email !== '') {
    $s = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $s->execute([$email]);
    $userRow = $s->fetch() ?: null;
}
if (!$userRow && is_logged_in()) {
    $userRow = current_user();
}
if (!$userRow) {
    set_flash('error', 'User not found for this SprintPay payment.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

$credit = sprintpay_credit_from_ngn((int)$userRow['id'], $amountNgn, $ref, 'SprintPay');
if (!$credit['success']) {
    set_flash('error', $credit['message']);
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

if (!is_logged_in()) {
    $_SESSION['user_id'] = $userRow['id'];
}

if (!empty($credit['already'])) {
    set_flash('info', 'Payment already processed.');
} else {
    set_flash('success', '$' . number_format($credit['amount_usd'], 2) . ' added to your wallet! (₦' . number_format($credit['amount_ngn'], 2) . ' received)');
}
redirect(SITE_URL . '/pages/fund-wallet.php');
