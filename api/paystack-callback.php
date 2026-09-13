<?php
// ============================================================
//  api/paystack-callback.php  (UPGRADED)
//  – Converts NGN payment to USD before crediting wallet
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/ncwallet.php';

$ref = $_GET['reference'] ?? '';
if (!$ref) {
    set_flash('error', 'Invalid payment reference.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

// Duplicate check
$s = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference=?");
$s->execute([$ref]);
if ($s->fetch()) {
    set_flash('info', 'Payment already processed.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

$result = paystack_verify($ref);
if (!$result['success']) {
    set_flash('error', 'Payment verification failed: ' . $result['message']);
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

$amountUsd = (float)$result['amount_usd'];
$amountNgn = (float)$result['amount_ngn'];
$rate      = (float)$result['rate'];
$email     = $result['email'];

// Validate limits
$minUsd = get_min_topup_usd();
$maxUsd = get_max_topup_usd();
if ($amountUsd < $minUsd || $amountUsd > $maxUsd) {
    set_flash('error', 'Payment amount out of allowed range ($' . $minUsd . ' – $' . $maxUsd . ').');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

$s = $pdo->prepare("SELECT * FROM users WHERE email=?");
$s->execute([$email]);
$userRow = $s->fetch();
if (!$userRow) {
    set_flash('error', 'User not found.');
    redirect(SITE_URL . '/pages/fund-wallet.php');
}

// Credit wallet in USD
$desc = sprintf('Card Payment (Paystack) – ₦%s @ ₦%s/$ = $%s',
    number_format($amountNgn, 2),
    number_format($rate, 2),
    number_format($amountUsd, 2)
);
wallet_credit($userRow['id'], $amountUsd, $desc, $ref);
process_referral_on_deposit((int) $userRow['id'], $amountUsd);
notify(
    $userRow['id'],
    'Wallet Funded',
    '$' . number_format($amountUsd, 2) . ' added via Paystack (₦' . number_format($amountNgn, 2) . ').',
    '/pages/fund-wallet.php'
);

if (!is_logged_in()) { $_SESSION['user_id'] = $userRow['id']; }

set_flash('success', '$' . number_format($amountUsd, 2) . ' added to your wallet! (₦' . number_format($amountNgn, 2) . ' received)');
redirect(SITE_URL . '/pages/fund-wallet.php');
