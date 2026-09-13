<?php
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

header('Content-Type: application/json');

$segment = clean($_GET['segment'] ?? 'all');
$days    = max(1, (int) ($_GET['days'] ?? 3));

$users = get_segment_users($segment, $days);
echo json_encode(['success' => true, 'count' => count($users), 'label' => segment_label($segment, $days)]);
