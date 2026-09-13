<?php
// ============================================================
//  google-login.php — redirects the user to Google's OAuth
//  consent screen. Linked from the "Continue with Google"
//  button on login.php / register.php.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

if (GOOGLE_CLIENT_ID === '') {
    set_flash('error', 'Google Sign-In is not configured yet. Contact the site admin.');
    redirect(SITE_URL . '/login.php');
}

// CSRF-style state param, verified in google-callback.php
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
