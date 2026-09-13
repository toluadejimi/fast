<?php
// ============================================================
// api/check-otp.php
// Ajax OTP polling — supports 5sim, VerifySMS, SMS-Man, OtpSuite, LogsPlug
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/fivesim.php';
require_once ROOT_PATH . '/includes/verifysms.php';
require_once ROOT_PATH . '/includes/smsman.php';
require_once ROOT_PATH . '/includes/otpsuite.php';
require_once ROOT_PATH . '/includes/logsplug.php';

header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['success'=>false]); exit; }

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$s = $pdo->prepare("SELECT * FROM otp_orders WHERE id=? AND user_id=?");
$s->execute([$orderId, $_SESSION['user_id']]);
$order = $s->fetch();

if (!$order) { echo json_encode(['success'=>false,'status'=>'NOT_FOUND']); exit; }

// Already received
if (!empty($order['otp_code'])) {
    echo json_encode(['success'=>true,'otp'=>$order['otp_code'],'status'=>'RECEIVED']); exit;
}

// Expired
if (strtotime($order['expires_at']) < time()) {
    $pdo->prepare("UPDATE otp_orders SET status='EXPIRED' WHERE id=?")->execute([$orderId]);
    echo json_encode(['success'=>false,'status'=>'EXPIRED']); exit;
}

// Pick provider
$prov = $order['operator'] ?? 'fivesim';
if ($prov === 'verifysms')  $sim = new VerifySMS();
elseif ($prov === 'smsman') $sim = new SmsMan();
elseif ($prov === 'otpsuite') $sim = new OtpSuite();
elseif ($prov === 'logsplug') $sim = new LogsPlug();
else                        $sim = new FiveSim();

$result = $sim->checkOrder($order['order_id']);

if (!empty($result['success']) && !empty($result['otp'])) {
    $otp = trim($result['otp']);
    $pdo->prepare("UPDATE otp_orders SET otp_code=?, status='RECEIVED' WHERE id=?")
        ->execute([$otp, $orderId]);
    notify($order['user_id'], 'OTP Received',
        'OTP for ' . $order['service_name'] . ': ' . $otp,
        '/pages/otp-active.php?id=' . $orderId);
    echo json_encode(['success'=>true,'otp'=>$otp,'status'=>'RECEIVED']);
} else {
    $status = $result['status'] ?? $order['status'];
    if ($status !== $order['status'] &&
        in_array($status, ['CANCELED','TIMEOUT','BANNED','EXPIRED'])) {
        $pdo->prepare("UPDATE otp_orders SET status=? WHERE id=?")->execute([$status, $orderId]);
    }
    $resp = ['success'=>false,'otp'=>null,'status'=>$status];
    if ($prov === 'logsplug' && !empty($result['debug'])) {
        $resp['debug'] = $result['debug'];
    }
    echo json_encode($resp);
}
