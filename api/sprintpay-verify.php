<?php
// ============================================================
//  SprintPay Collection API – username lookup
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

$email = strtolower(trim((string)($_POST['email'] ?? $_GET['email'] ?? '')));
if ($email === '') {
    $raw = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim((string)($raw['email'] ?? '')));
}

$s = $pdo->prepare("SELECT username FROM users WHERE email=? LIMIT 1");
$s->execute([$email]);
$user = $s->fetch();

if (!$user) {
    echo json_encode(['username' => 'Not Found, Please try again']);
    exit;
}

echo json_encode(['username' => $user['username']]);
