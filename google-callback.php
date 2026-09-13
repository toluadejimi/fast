<?php
// ============================================================
//  google-callback.php — Google redirects here after consent.
//  Exchanges the code for a token, fetches the profile, then
//  either logs in an existing account or creates a new one.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (empty($_SESSION['google_oauth_state']) || $state !== $_SESSION['google_oauth_state']) {
    set_flash('error', 'Google sign-in session expired. Please try again.');
    redirect(SITE_URL . '/login.php');
}
unset($_SESSION['google_oauth_state']);

if (empty($code)) {
    set_flash('error', 'Google sign-in was cancelled.');
    redirect(SITE_URL . '/login.php');
}

// ── Exchange the authorization code for an access token ──────
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$tokenRaw = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenRaw, true);
if (empty($tokenData['access_token'])) {
    set_flash('error', 'Could not verify your Google account. Please try again.');
    redirect(SITE_URL . '/login.php');
}

// ── Fetch the user's Google profile ───────────────────────────
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$profileRaw = curl_exec($ch);
curl_close($ch);

$profile = json_decode($profileRaw, true);
if (empty($profile['sub']) || empty($profile['email'])) {
    set_flash('error', 'Could not read your Google profile. Please try again.');
    redirect(SITE_URL . '/login.php');
}

$googleId = $profile['sub'];
$email    = strtolower(trim($profile['email']));
$name     = trim($profile['name'] ?? $email);

// ── 1) Existing account already linked to this Google ID ─────
$s = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
$s->execute([$googleId]);
$user = $s->fetch();

if (!$user) {
    // ── 2) Existing account with the same email — link it ────
    $s = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $s->execute([$email]);
    $user = $s->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
    } else {
        // ── 3) Brand new account ──────────────────────────────
        $refCode = trim($_COOKIE['pending_ref_code'] ?? '');
        $referredBy = null;
        if ($refCode !== '') {
            $r = $pdo->prepare("SELECT id FROM users WHERE ref_code = ?");
            $r->execute([$refCode]);
            $referrer = $r->fetch();
            if ($referrer) $referredBy = (int) $referrer['id'];
        }

        // Derive a unique username from the email
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', strtok($email, '@'));
        $base = substr($base !== '' ? $base : 'user', 0, 15);
        $username = $base;
        $i = 0;
        while (true) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if (!$chk->fetch()) break;
            $i++;
            $username = $base . $i;
        }

        $newRefCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        // Random password — this account signs in via Google only, but the
        // column is NOT NULL, so we fill it with something unusable/unguessable.
        $randomPassword = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->prepare(
            "INSERT INTO users (username,email,phone,password,pin,google_id,ref_code,referred_by,last_login)
             VALUES (?,?,?,?,?,?,?,?,NOW())"
        )->execute([$username, $email, '', $randomPassword, '', $googleId, $newRefCode, $referredBy]);

        $userId = (int) $pdo->lastInsertId();
        notify($userId, 'Welcome!', 'Your account has been created with Google. Fund your wallet to get started.', '/pages/dashboard.php');
        if ($referredBy) {
            notify($referredBy, 'New Referral! 👋', $username . ' just signed up using your referral link.', '/pages/referrals.php');
        }

        $s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $s->execute([$userId]);
        $user = $s->fetch();
    }
}

if ($user['status'] === 'blocked') {
    set_flash('error', 'Your account has been blocked. Contact support.');
    redirect(SITE_URL . '/login.php');
}

$_SESSION['user_id']  = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
setcookie('pending_ref_code', '', time() - 3600, '/'); // clear any pending ref cookie

// Nudge incomplete profiles (phone is needed for virtual account / KYC features)
if (empty($user['phone'])) {
    redirect(SITE_URL . '/complete-profile.php');
}

redirect(SITE_URL . '/pages/dashboard.php');
