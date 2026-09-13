<?php
// ============================================================
//  api/check-social-status.php
//  Polls momopanel for one order's live status and syncs it to
//  our social_orders row.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/momopanel.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$orderDbId = (int) ($_POST['order_db_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM social_orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderDbId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$momo = new MomoPanel();
$resp = $momo->getOrderStatus($order['momo_order_id']);

if (!$resp['success'] || empty($resp['data']['status'])) {
    echo json_encode(['success' => false, 'error' => $resp['error'] ?? 'Could not fetch status']);
    exit;
}

$data = $resp['data'];

$pdo->prepare("UPDATE social_orders SET status = ?, start_count = ?, remains = ? WHERE id = ?")
    ->execute([
        $data['status'],
        $data['start_count'] ?? null,
        $data['remains'] ?? null,
        $orderDbId,
    ]);

echo json_encode(['success' => true, 'status' => $data['status']]);
