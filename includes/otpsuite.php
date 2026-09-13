<?php
// ============================================================
// DonnieSMS OTP - includes/otpsuite.php
//
// Integration for otpsuite.com (USA WhatsApp numbers), wired in
// as "Provider 4" alongside 5sim / VerifySMS / SMS-Man.
//
// otpsuite.com API contract (from developer docs):
//   GET  /balance                         -> { balance, currency }
//   GET  /countries                       -> { countries: [{country_code, country_name}] }
//   GET  /services?country=US             -> { services: [{service_id, service_name, base_price, your_price}] }
//   POST /buy-number  {country, service}  -> { order_id, number, service, country, price, status }
//   GET  /get-sms?order_id=X              -> { order_id, status, sms }
//   POST /cancel-number {order_id}        -> { order_id, ... }
//
// Every response is wrapped: { "success": true, "data": {...} }
//                         or  { "success": false, "error": "message" }
//
// NOTE: otpsuite prices are in NGN, not USD (unlike 5sim/SMS-Man).
// This class converts NGN -> USD internally using the site's
// existing usd_to_ngn()/ngn_to_usd() rate helpers, so the rest of
// numbers.php / otp-active.php can treat it exactly like the other
// USD-based providers and no other file needs special-casing.
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/functions.php';

class OtpSuite {

    private $apiKey;
    private $base;

    public function __construct($apiKey = '') {
        if (!empty($apiKey)) {
            $this->apiKey = $apiKey;
        } else {
            $this->apiKey = get_setting('otpsuite_api_key', defined('OTPSUITE_API_KEY') ? OTPSUITE_API_KEY : '');
        }
        $this->base = get_setting('otpsuite_base_url', defined('OTPSUITE_BASE') ? OTPSUITE_BASE : 'https://otpsuite.com/api/v1');
    }

    // ── cURL request ──────────────────────────────────────────
    private function request($endpoint, $method = 'GET', $params = array()) {

        $url = rtrim($this->base, '/') . $endpoint;

        $ch = curl_init();

        if (strtoupper($method) === 'GET') {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-API-KEY: ' . $this->apiKey,
            'Accept: application/json',
        ));

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            error_log('OtpSuite cURL error: ' . $err);
            return array('_error' => 'Connection error, please try again');
        }
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('OtpSuite bad response: ' . $raw);
            return array('_error' => 'Unexpected response from provider');
        }

        if (empty($decoded['success'])) {
            return array('_error' => $decoded['error'] ?? 'Unknown provider error');
        }

        return isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : array();
    }

    // ── Countries ──────────────────────────────────────────────
    // Returns [ 'US' => ['iso' => 'US', 'name' => 'United States'], ... ]
    public function getCountries() {
        $data = $this->request('/countries');
        if (isset($data['_error'])) return array();

        $out = array();
        foreach (($data['countries'] ?? array()) as $c) {
            $code = strtoupper($c['country_code'] ?? '');
            if ($code === '') continue;
            $out[$code] = array(
                'iso'  => $code,
                'name' => $c['country_name'] ?? $code,
            );
        }
        return $out;
    }

    // ── Services for a country, with markup applied ────────────
    // Returns the same shape as FiveSim/SmsMan::getServicesForCountry()
    public function getServicesForCountry($country = 'US', $markupPct = 0) {

        $country = empty($country) || $country === 'any' ? 'US' : strtoupper($country);

        $data = $this->request('/services', 'GET', array('country' => $country));
        if (isset($data['_error'])) return array();

        $services = array();

        foreach (($data['services'] ?? array()) as $s) {
            $ngnCost = isset($s['your_price']) ? (float)$s['your_price'] : 0;
            if ($ngnCost <= 0) continue;

            // Convert provider's NGN cost to USD so the rest of the app
            // (which is USD-denominated) treats this like any other provider.
            $usdCost = ngn_to_usd($ngnCost);
            if ($usdCost <= 0) continue;

            $displayPrice = $markupPct > 0
                ? round($usdCost * (1 + ($markupPct / 100)), 4)
                : round($usdCost, 4);

            $services[] = array(
                'slug'          => $s['service_id'],
                'name'          => $s['service_name'],
                'qty'           => 999, // otpsuite doesn't expose stock counts
                'price_raw'     => round($usdCost, 4),
                'price_display' => $displayPrice,
                'country'       => $country,
            );
        }

        usort($services, function ($a, $b) { return strcmp($a['name'], $b['name']); });

        return $services;
    }

    // ── Buy a number ─────────────────────────────────────────────
    public function buyNumber($country, $operator, $product) {

        $country = empty($country) || $country === 'any' ? 'US' : strtoupper($country);

        $data = $this->request('/buy-number', 'POST', array(
            'country' => $country,
            'service' => $product,
        ));

        if (isset($data['_error'])) {
            return array('success' => false, 'message' => $data['_error']);
        }

        if (empty($data['order_id']) || empty($data['number'])) {
            return array('success' => false, 'message' => 'No number available for this service right now.');
        }

        $ngnCost = isset($data['price']) ? (float)$data['price'] : 0;
        $usdCost = $ngnCost > 0 ? ngn_to_usd($ngnCost) : 0;

        return array(
            'success'  => true,
            'id'       => (string)$data['order_id'],
            'phone'    => $data['number'],
            'operator' => 'otpsuite',
            'product'  => $product,
            'price'    => round($usdCost, 4), // TRUE cost in USD (markup applied by caller)
            'status'   => 'PENDING',
            'country'  => $country,
        );
    }

    // ── Poll for the OTP ───────────────────────────────────────
    public function checkOrder($orderId) {

        if (empty($orderId)) {
            return array('success' => false, 'otp' => null, 'status' => 'ERROR', 'message' => 'Invalid order ID');
        }

        $data = $this->request('/get-sms', 'GET', array('order_id' => $orderId));

        if (isset($data['_error'])) {
            return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => $data['_error']);
        }

        $status = strtoupper($data['status'] ?? 'WAITING');
        $otp    = null;

        if ($status === 'RECEIVED' && !empty($data['sms'])) {
            // Try to isolate just the numeric code if the API returns full SMS text
            if (preg_match('/\b(\d{4,8})\b/', $data['sms'], $m)) {
                $otp = $m[1];
            } else {
                $otp = trim($data['sms']);
            }
        }

        return array(
            'success' => !empty($otp),
            'otp'     => $otp,
            'status'  => $status === 'WAITING' ? 'PENDING' : $status,
        );
    }

    // ── Cancel + refund ──────────────────────────────────────────
    public function cancelOrder($orderId) {

        if (empty($orderId)) {
            return array('success' => false, 'message' => 'Invalid order ID');
        }

        $data = $this->request('/cancel-number', 'POST', array('order_id' => $orderId));

        if (isset($data['_error'])) {
            return array('success' => false, 'message' => $data['_error']);
        }

        return array('success' => true, 'message' => '');
    }

    // ── No native "finish" endpoint — treat as a no-op success ───
    public function finishOrder($orderId) {
        return true;
    }

    // ── No native "ban" endpoint — cancel with the provider so it
    //    isn't left dangling, refund is handled by the caller ─────
    public function banOrder($orderId) {
        $this->cancelOrder($orderId);
        return true;
    }

    // ── Wallet balance (NGN converted to USD for consistency) ────
    public function getBalance() {
        $data = $this->request('/balance');
        if (isset($data['_error'])) return 0.0;
        $ngn = isset($data['balance']) ? (float)$data['balance'] : 0.0;
        return round(ngn_to_usd($ngn), 2);
    }
}
