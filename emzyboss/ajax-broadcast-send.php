<?php
// ============================================================
//  bigboss/ajax-broadcast-send.php
//  Sends ONE batch (default 5) of broadcast emails per call.
//  broadcast.php's JS calls this repeatedly, advancing "offset"
//  each time, until "done" comes back true. This is what keeps
//  a large recipient list from ever timing out a single request.
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh and try again.']);
    exit;
}

$segment = clean($_POST['segment'] ?? 'all');
$days    = max(1, (int) ($_POST['days'] ?? 3));
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$offset  = max(0, (int) ($_POST['offset'] ?? 0));
$batch   = 5; // small on purpose — keeps each request fast and safe

if ($subject === '' || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Subject and message are required']);
    exit;
}

$allRecipients = get_segment_users($segment, $days);
$total   = count($allRecipients);
$slice   = array_slice($allRecipients, $offset, $batch);
$siteName = get_setting('site_name', SITE_NAME);

$sent = $failed = 0;

foreach ($slice as $r) {
    $personalized = str_replace('{name}', $r['username'], $message);
    $html = email_template(
        '<p>Hi <strong>' . htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>' .
        '<div style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($personalized, ENT_QUOTES, 'UTF-8')) . '</div>'
    );
    try {
        $ok = send_email($r['email'], $subject . ' - ' . $siteName, $html);
        if ($ok) $sent++; else $failed++;
    } catch (Throwable $e) {
        error_log('[broadcast] send failed for ' . $r['email'] . ': ' . $e->getMessage());
        $failed++;
    }
}

$nextOffset = $offset + $batch;
$done = $nextOffset >= $total;

echo json_encode([
    'success'     => true,
    'sent'        => $sent,
    'failed'      => $failed,
    'next_offset' => $nextOffset,
    'total'       => $total,
    'done'        => $done,
]);
