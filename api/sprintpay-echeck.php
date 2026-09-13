<?php
// ============================================================
//  SprintPay Collection API – e-check (confirm customer exists)
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/sprintpay.php';

header('Content-Type: application/json');

$email = strtolower(trim((string)($_POST['email'] ?? $_GET['email'] ?? '')));
if ($email === '') {
    $raw = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim((string)($raw['email'] ?? '')));
}

sprintpay_log('e-check', ['email' => $email], $email, 0, 'e-check');

if ($email === '') {
    echo json_encode(['status' => false, 'message' => 'Email is required']);
    exit;
}

$s = $pdo->prepare("SELECT username, email FROM users WHERE email=? LIMIT 1");
$s->execute([$email]);
$user = $s->fetch();

if (!$user) {
    echo json_encode([
        'status'  => false,
        'message' => 'No user found, please check email and try again',
    ]);
    exit;
}

echo json_encode([
    'status'   => true,
    'user'     => $user['username'],
    'username' => $user['username'],
    'email'    => $user['email'],
]);
