<?php
// ============================================================
//  Copy this file to config/config.php and fill in live values
// ============================================================
if (!ob_get_level()) ob_start();

define('SITE_NAME',    'FastSmsHub');
if (PHP_SAPI === 'cli-server') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
    define('SITE_URL', $scheme . '://' . $host);
} else {
    define('SITE_URL', 'https://your-domain.com');
}
define('ADMIN_PATH',   'emzyboss');

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('FIVESIM_API_KEY', '');
define('FIVESIM_BASE',    'https://5sim.net/v1');

define('VERIFYSMS_API_KEY', '');
define('OTPSUITE_API_KEY', '');
define('MOMOPANEL_API_KEY', '');
define('OTPSUITE_BASE',    'https://otpsuite.com/api/v1');

define('NCWALLET_BASE',     'https://ncwallet.africa/api/v1');
define('NCWALLET_API_KEY',  '');
define('NCWALLET_SECRET',   '');
define('NCWALLET_BUSINESS', '');

define('PAYSTACK_PUBLIC', '');
define('PAYSTACK_SECRET', '');

define('GOOGLE_CLIENT_ID',     '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI',  SITE_URL . '/google-callback.php');

define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_PORT',   587);
define('SMTP_USER',   '');
define('SMTP_PASS',   '');
define('SMTP_SECURE', 'tls');
define('SITE_EMAIL',  '');

define('ENCRYPTION_KEY', 'CHANGE_THIS_TO_ANY_32_RANDOM_CHARS!');
define('SESSION_NAME',   'fms_otp_sess');
define('OTP_EXPIRY_MINUTES', 20);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name',            SESSION_NAME);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    session_start();
}
date_default_timezone_set('Africa/Lagos');
