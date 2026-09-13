<?php
// ============================================================
//  ErifyLogs – api/logs-purchase.php  (FIXED)
//  Fix 1: correct ROOT_PATH for /api/ location
//  Fix 2: PIN verification added
// ============================================================
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

// Must be logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$productId = (int)($input['product_id'] ?? 0);
$quantity  = max(1, min(10, (int)($input['quantity'] ?? 1)));
$currency  = in_array($input['currency'] ?? '', ['USD','NGN']) ? $input['currency'] : 'USD';
$pin       = trim($input['pin'] ?? '');

// ── PIN check ────────────────────────────────────────────────
if (empty($user['pin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You have no PIN set. Please go to Profile → Set PIN first.'
    ]);
    exit;
}
if (!$pin || strlen($pin) !== 4) {
    echo json_encode(['success' => false, 'message' => 'Please enter your 4-digit PIN.']);
    exit;
}
if (!password_verify($pin, $user['pin'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect PIN. Please try again.']);
    exit;
}

// ── Validate product ─────────────────────────────────────────
if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS cat_name
     FROM log_products p
     LEFT JOIN log_categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.is_active = 1"
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found or unavailable.']);
    exit;
}

// ── Check stock ──────────────────────────────────────────────
$stockStmt = $pdo->prepare(
    "SELECT id, credentials FROM log_stock
     WHERE product_id = ? AND is_sold = 0
     ORDER BY id ASC LIMIT ?"
);
$stockStmt->execute([$productId, $quantity]);
$items = $stockStmt->fetchAll();

if (count($items) < $quantity) {
    $avail = count($items);
    echo json_encode([
        'success' => false,
        'message' => $avail === 0
            ? 'This product is out of stock.'
            : "Only {$avail} item(s) left in stock. Please reduce quantity."
    ]);
    exit;
}

// ── Price & balance check ────────────────────────────────────
$priceUsd = (float)$product['price_usd'];
$totalUsd = round($priceUsd * $quantity, 4);
$rate     = get_usd_to_ngn_rate();
$balance  = (float)$user['balance'];

if ($balance < $totalUsd) {
    $have = $currency === 'NGN'
        ? '₦' . number_format($balance  * $rate, 2)
        : '$' . number_format($balance, 4);
    $need = $currency === 'NGN'
        ? '₦' . number_format($totalUsd * $rate, 2)
        : '$' . number_format($totalUsd, 4);
    echo json_encode([
        'success' => false,
        'message' => "Insufficient balance. You have {$have} but need {$need}. Please fund your wallet."
    ]);
    exit;
}

// ── Transaction ──────────────────────────────────────────────
$pdo->beginTransaction();
try {
    $ref = 'LOG' . strtoupper(bin2hex(random_bytes(6)));

    // Debit wallet with race-condition guard
    $debit = $pdo->prepare(
        "UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?"
    );
    $debit->execute([$totalUsd, $user['id'], $totalUsd]);

    if ($debit->rowCount() === 0) {
        throw new Exception('Insufficient balance.');
    }

    // Wallet transaction log
    $amtPaid = $currency === 'NGN' ? round($totalUsd * $rate, 2) : $totalUsd;
    $pdo->prepare(
        "INSERT INTO wallet_transactions
         (user_id, type, amount, description, reference, status)
         VALUES (?, 'debit', ?, ?, ?, 'success')"
    )->execute([
        $user['id'],
        $totalUsd,
        "Logs purchase: {$quantity}× {$product['name']}",
        $ref
    ]);

    // Create log order
    $pdo->prepare(
        "INSERT INTO log_orders
         (user_id, product_id, product_name, quantity,
          unit_price_usd, total_usd, currency_paid, amount_paid, reference)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([
        $user['id'], $productId, $product['name'], $quantity,
        $priceUsd, $totalUsd, $currency, $amtPaid, $ref
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // Mark stock sold + collect credentials
    $credentials = [];
    foreach ($items as $item) {
        $pdo->prepare(
            "UPDATE log_stock SET is_sold = 1, sold_at = NOW(), order_id = ? WHERE id = ?"
        )->execute([$orderId, $item['id']]);

        $pdo->prepare(
            "INSERT INTO log_order_items (order_id, stock_id) VALUES (?, ?)"
        )->execute([$orderId, $item['id']]);

        $credentials[] = $item['credentials'];
    }

    $pdo->commit();

    track_spend((int)$user['id'], $totalUsd);

    // ── Notification ─────────────────────────────────────────
    notify(
        (int)$user['id'],
        '✅ Purchase Successful',
        "{$quantity}× {$product['name']} delivered. Tap to view.",
        '/pages/log-order.php?id=' . $orderId
    );

    // ── Email ────────────────────────────────────────────────
    $credHtml = '';
    foreach ($credentials as $i => $cred) {
        $credHtml .= '<div style="margin-bottom:12px;padding:12px;background:#f4f4f7;
                      border-radius:8px;border-left:3px solid #7c3aed;">'
            . '<strong style="font-size:12px;color:#666;">ITEM ' . ($i + 1) . '</strong><br>'
            . '<pre style="font-size:13px;margin:6px 0 0;white-space:pre-wrap;word-break:break-all;">'
            . htmlspecialchars($cred) . '</pre></div>';
    }

    $totalDisplay = $currency === 'NGN'
        ? '₦' . number_format($amtPaid, 2) . ' ($' . number_format($totalUsd, 4) . ')'
        : '$' . number_format($totalUsd, 4);

    $emailBody = '
    <div style="font-family:sans-serif;max-width:540px;margin:0 auto;">
      <h2 style="color:#7c3aed;">Your Purchase is Ready! 🎉</h2>
      <p>Hi <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
      <p>Your order for <strong>' . htmlspecialchars($quantity . '× ' . $product['name']) . '</strong>
         has been processed successfully.</p>
      <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
        <tr style="background:#f9f9f9;">
          <td style="padding:8px 12px;border:1px solid #eee;font-weight:700;">Order #</td>
          <td style="padding:8px 12px;border:1px solid #eee;">' . $orderId . '</td>
        </tr>
        <tr>
          <td style="padding:8px 12px;border:1px solid #eee;font-weight:700;">Reference</td>
          <td style="padding:8px 12px;border:1px solid #eee;">' . $ref . '</td>
        </tr>
        <tr style="background:#f9f9f9;">
          <td style="padding:8px 12px;border:1px solid #eee;font-weight:700;">Amount Paid</td>
          <td style="padding:8px 12px;border:1px solid #eee;">' . $totalDisplay . '</td>
        </tr>
      </table>
      <h3 style="color:#333;margin-top:20px;">Your Credentials:</h3>
      ' . $credHtml . '
      <p style="margin-top:20px;padding:12px;background:#fff8e1;border-radius:8px;
         font-size:12px;color:#856404;border:1px solid #ffd966;">
        ⚠️ Keep these credentials safe. Do not share with anyone.
        All sales are final.
      </p>
    </div>';

    send_email(
        $user['email'],
        "✅ Your {$product['name']} credentials – Order #{$orderId}",
        $emailBody
    );

    echo json_encode([
        'success'     => true,
        'order_id'    => $orderId,
        'credentials' => $credentials,
        'message'     => 'Purchase successful!'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[logs-purchase] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() === 'Insufficient balance.'
            ? 'Insufficient balance. Please fund your wallet.'
            : 'Purchase failed. Please try again.'
    ]);
}
