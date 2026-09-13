<?php
// ============================================================
// DonnieSMS OTP - includes/momopanel.php
//
// Integration for momopanel.com (SMM services: followers, likes,
// views, comments, etc. for social media) — powers the new
// "Social Boost" section.
//
// momopanel.com API contract (single endpoint, action-based):
//   POST /api/v2  key=X&action=services                     -> [{service,name,type,category,rate,min,max,refill,cancel}, ...]
//   POST /api/v2  key=X&action=add&service&link&quantity     -> {order}
//   POST /api/v2  key=X&action=status&order                  -> {charge,start_count,status,remains,currency}
//   POST /api/v2  key=X&action=status&orders (csv, up to 100) -> {orderId: {...}, ...}
//   POST /api/v2  key=X&action=refill&order                  -> {refill}
//   POST /api/v2  key=X&action=refill&orders (csv)            -> [{order,refill}, ...]
//   POST /api/v2  key=X&action=refill_status&refill           -> {status}
//   POST /api/v2  key=X&action=cancel&orders (csv)             -> [{order,cancel}, ...]
//   POST /api/v2  key=X&action=balance                        -> {balance,currency}
//
// NOTE: rate is USD per 1000 units (industry-standard SMM panel
// convention). Real cost for an order = (rate / 1000) * quantity.
// Unlike otpsuite, everything here is already USD — no currency
// conversion needed.
//
// This API has no wrapped {success,data} envelope like otpsuite —
// it returns the raw array/object directly, or {"error": "..."}
// on failure. This class normalizes both into a consistent
// ['success' => bool, 'data' => ..., 'error' => ...] shape so the
// rest of the app doesn't need to know the difference.
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/functions.php';

class MomoPanel {

    private $apiKey;
    private $base = 'https://momopanel.com/api/v2';

    public function __construct($apiKey = '') {
        $this->apiKey = !empty($apiKey)
            ? $apiKey
            : get_setting('momopanel_api_key', defined('MOMOPANEL_API_KEY') ? MOMOPANEL_API_KEY : '');
    }

    // ── POST request (this API only uses POST, form-urlencoded) ──
    private function request(array $params) {
        $params['key'] = $this->apiKey;

        $ch = curl_init($this->base);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            error_log('MomoPanel cURL error: ' . $err);
            return ['success' => false, 'data' => null, 'error' => 'Connection error, please try again'];
        }
        curl_close($ch);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            error_log('MomoPanel bad response: ' . $raw);
            return ['success' => false, 'data' => null, 'error' => 'Unexpected response from provider'];
        }

        // Single-object error response: {"error": "..."}
        if (isset($decoded['error']) && count($decoded) === 1) {
            return ['success' => false, 'data' => null, 'error' => $decoded['error']];
        }

        return ['success' => true, 'data' => $decoded, 'error' => null];
    }

    // ── Full services list (all categories) ───────────────────
    public function getServices() {
        return $this->request(['action' => 'services']);
    }

    // ── Place an order ─────────────────────────────────────────
    public function addOrder(string $service, string $link, int $quantity, ?int $runs = null, ?int $interval = null) {
        $params = ['action' => 'add', 'service' => $service, 'link' => $link, 'quantity' => $quantity];
        if ($runs !== null)     $params['runs'] = $runs;
        if ($interval !== null) $params['interval'] = $interval;
        return $this->request($params);
    }

    // ── Single order status ────────────────────────────────────
    public function getOrderStatus(string $orderId) {
        return $this->request(['action' => 'status', 'order' => $orderId]);
    }

    // ── Bulk order status (up to 100 comma-separated IDs) ────────
    public function getMultipleOrderStatus(array $orderIds) {
        return $this->request(['action' => 'status', 'orders' => implode(',', array_slice($orderIds, 0, 100))]);
    }

    // ── Request a refill ────────────────────────────────────────
    public function createRefill(string $orderId) {
        return $this->request(['action' => 'refill', 'order' => $orderId]);
    }

    // ── Refill status ───────────────────────────────────────────
    public function getRefillStatus(string $refillId) {
        return $this->request(['action' => 'refill_status', 'refill' => $refillId]);
    }

    // ── Cancel one or more orders (up to 100 comma-separated) ────
    public function cancelOrders(array $orderIds) {
        return $this->request(['action' => 'cancel', 'orders' => implode(',', array_slice($orderIds, 0, 100))]);
    }

    // ── Provider account balance ──────────────────────────────────
    public function getBalance() {
        return $this->request(['action' => 'balance']);
    }
}
