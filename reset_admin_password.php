<?php
/**
 * reset_admin_password.php — for DonnieSMS OTP (admin_users table)
 * ONE-TIME USE. Upload to your donniesms root, visit once in your
 * browser, then DELETE this file immediately.
 *
 * Usage: yourdomain.com/reset_admin_password.php?u=admin&p=YourNewPassword123
 */

if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

$username = $_GET['u'] ?? '';
$password = $_GET['p'] ?? '';

if ($username === '' || $password === '') {
    die('Usage: reset_admin_password.php?u=admin&p=YourNewPassword');
}
if (strlen($password) < 6) {
    die('Password must be at least 6 characters.');
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
$stmt->execute([$username]);

if ($stmt->fetch()) {
    $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = ?")
        ->execute([$hash, $username]);
    echo "Password updated for admin '{$username}'. <br><strong>Now delete this file immediately.</strong>";
} else {
    $pdo->prepare("INSERT INTO admin_users (username, email, password, role) VALUES (?, ?, ?, 'superadmin')")
        ->execute([$username, $username . '@' . ($_SERVER['HTTP_HOST'] ?? 'example.com'), $hash]);
    echo "Admin '{$username}' created. <br><strong>Now delete this file immediately.</strong>";
}
