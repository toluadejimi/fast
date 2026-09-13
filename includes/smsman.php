<?php
// ============================================================
//  DonnieSMS OTP – includes/smsman.php
//  SMS-Man API  (FIXED v2)
//  Base: https://api.sms-man.com/control
//  Auth: ?token=API_KEY (GET param on every request)
//
//  FIXES:
//  - getCountries() now accepts country_id=0 (Russia, highest stock)
//  - getApplications() uses correct 'applications' endpoint
//  - getPrices() default country_id changed to 0 (Russia)
//  - buyNumber() passes correct country_id including 0
//  - checkOrder() handles both sms_code and msg fields
// ============================================================

if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';

class SmsMan {

    private $apiKey;
    private $base;

    public function __construct($apiKey = '') {
        $this->base   = 'https://api.sms-man.com/control';
        $this->apiKey = $apiKey ? $apiKey : get_setting('smsman_api_key', '');
    }

    // ── Internal GET ─────────────────────────────────────────
    private function get($endpoint, $params = array()) {

        $params['token'] = $this->apiKey;

        $url = $this->base . '/' . ltrim($endpoint, '/') . '?' . http_build_query($params);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'User-Agent: Mozilla/5.0'
        ));

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);

        curl_close($ch);

        if ($err) {
            return array('_error' => 'cURL: ' . $err, '_code' => 0);
        }

        if (!$res) {
            return array('_error' => 'Empty response', '_code' => $code);
        }

        $data = json_decode($res, true);

        if (!is_array($data)) {
            // SMS-Man sometimes returns plain error strings
            return array('_error' => 'Bad JSON: ' . substr($res, 0, 200), '_code' => $code);
        }

        $data['_code'] = $code;

        return $data;
    }

    // ── Balance ──────────────────────────────────────────────
    public function getBalance() {

        $d = $this->get('get-balance');

        return isset($d['balance']) ? (float)$d['balance'] : 0.0;
    }

    // ── Countries ─────────────────────────────────────────────
    // FIX: Russia is country_id=0 — old code skipped it with $id <= 0
    public function getCountries() {

        $d = $this->get('countries');

        if (isset($d['_error'])) {
            return array();
        }

        $out = array();

        foreach ($d as $item) {

            if (!is_array($item)) {
                continue;
            }

            // id can be 0 (Russia) — use isset, not > 0 check
            if (!isset($item['id'])) {
                continue;
            }

            $id    = (int)$item['id'];
            $title = isset($item['title']) ? trim($item['title']) : '';
            $code  = isset($item['code'])  ? strtoupper(trim($item['code'])) : '';

            if ($title === '') {
                continue;
            }

            $out[] = array(
                'id'   => $id,
                'name' => $title,
                'code' => $code
            );
        }

        usort($out, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $out;
    }

    // ── Applications (services list) ─────────────────────────
    // Endpoint: /control/applications?token=...
    // Returns: [{"id":"1","title":"Telegram"}, ...]
    public function getApplications() {

        $d = $this->get('applications');

        if (isset($d['_error'])) {
            return array();
        }

        $out = array();

        foreach ($d as $item) {

            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['id'])) {
                continue;
            }

            $id   = (int)$item['id'];
            $name = isset($item['title']) ? trim($item['title']) : '';

            if ($name === '') {
                continue;
            }

            $out[$id] = $name;
        }

        return $out;
    }

    // ── Prices + stock ───────────────────────────────────────
    // SMS-Man API returns prices in RUBLES (not USD).
    // cost=15 means ₽15. We convert to USD using live rate.
    // Response shape: {"0": {"1": {"cost":"15","count":6455}, "2": {...}}}
    // The outer key is the country_id, inner key is application_id.

    private function rubToUsd($rubles) {
        // Try to get live USD/RUB rate from open API, cache 1 hour in DB
        global $pdo;
        static $rate = null;
        if ($rate !== null) return $rubles / $rate;
        try {
            if ($pdo) {
                $row = $pdo->query("SELECT value, updated_at FROM site_settings WHERE `key`='usd_rub_rate'")->fetch();
                if ($row && (time() - strtotime($row['updated_at'])) < 3600) {
                    $rate = (float)$row['value'];
                    if ($rate > 0) return $rubles / $rate;
                }
            }
        } catch (Exception $e) {}
        // Fetch live rate
        $ch = curl_init('https://open.er-api.com/v6/latest/USD');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res) {
            $data = json_decode($res, true);
            $r = (float)($data['rates']['RUB'] ?? 0);
            if ($r > 0) {
                $rate = $r;
                try {
                    if ($pdo) {
                        $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES ('usd_rub_rate',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$rate]);
                    }
                } catch (Exception $e) {}
                return $rubles / $rate;
            }
        }
        // Fallback: ~90 rubles per USD
        $rate = 90.0;
        return $rubles / $rate;
    }

    private function getPrices($countryId) {

        // FIX: allow 0 (Russia) — old code rejected it
        $countryId = (int)$countryId;

        $d = $this->get('get-prices', array(
            'country_id' => $countryId
        ));

        if (isset($d['_error']) || empty($d)) {
            return array();
        }

        $out = array();

        foreach ($d as $countryKey => $services) {

            if ($countryKey === '_code') {
                continue;
            }

            if (!is_array($services)) {
                continue;
            }

            foreach ($services as $appId => $serviceData) {

                if (!is_array($serviceData)) {
                    continue;
                }

                // cost is in RUBLES — convert to USD
                $price = isset($serviceData['cost']) ? $this->rubToUsd((float)$serviceData['cost']) : 0;
                $qty   = isset($serviceData['count']) ? (int)$serviceData['count']   : 0;

                if ($price <= 0 || $qty <= 0) {
                    continue;
                }

                $appId = (int)$appId;

                if (!isset($out[$appId]) || $price < $out[$appId]['price']) {
                    $out[$appId] = array(
                        'price' => $price,
                        'qty'   => $qty
                    );
                }
            }
        }

        return $out;
    }

    // ── Services list for a country ───────────────────────────
    // FIX: default country changed to 0 (Russia = most stock on SMS-Man)
    public function getServicesForCountry($countryId, $markupPct = 50) {

        // FIX: cast to int; 0 is valid
        $countryId = (int)$countryId;

        $apps   = $this->getApplications(); // may be empty — still show prices
        $prices = $this->getPrices($countryId);

        if (empty($prices)) {
            return array(); // no prices = nothing to show
        }

        $services = array();

        foreach ($prices as $appId => $priceData) {

            $rawPrice = isset($priceData['price']) ? (float)$priceData['price'] : 0;
            $qty      = isset($priceData['qty'])   ? (int)$priceData['qty']     : 0;

            if ($rawPrice <= 0 || $qty <= 0) {
                continue;
            }

            $name = isset($apps[$appId]) ? $apps[$appId] : 'Service #' . $appId;

            // Apply markup
            $displayPrice = round($rawPrice * (1 + ($markupPct / 100)), 4);

            $services[] = array(
                'slug'          => (string)$appId,
                'name'          => $name,
                'qty'           => $qty,
                'price_raw'     => $rawPrice,
                'price_display' => $displayPrice,
                'country'       => $countryId
            );
        }

        usort($services, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $services;
    }

    // ── Buy number ───────────────────────────────────────────
    // FIX: country_id=0 is valid — removed old > 0 guard
    public function buyNumber($country, $operator, $slug) {

        $appId     = (int)$slug;
        $countryId = (int)$country;

        // FIX: only reject if slug (appId) is invalid, not country=0
        if (!$appId) {
            return array('success' => false, 'message' => 'Invalid service selected.');
        }

        $d = $this->get('get-number', array(
            'country_id'     => $countryId,
            'application_id' => $appId
        ));

        if (isset($d['_error'])) {
            return array('success' => false, 'message' => $d['_error']);
        }

        // Handle SMS-Man error responses
        if (!empty($d['error_msg'])) {
            $msg = is_array($d['error_msg'])
                ? implode(', ', array_values($d['error_msg']))
                : (string)$d['error_msg'];
            return array('success' => false, 'message' => $msg);
        }

        if (empty($d['request_id']) || empty($d['number'])) {
            return array('success' => false, 'message' => 'No number returned by SMS-Man.');
        }

        return array(
            'success' => true,
            'id'      => (string)$d['request_id'],
            'phone'   => (string)$d['number'],
            'price'   => isset($d['price']) ? (float)$d['price'] : 0,
            'status'  => 'PENDING',
            'country' => (string)$countryId
        );
    }

    // ── Check order / get SMS ─────────────────────────────────
    // FIX: also reads 'msg' field (full SMS text) when sms_code not present
    public function checkOrder($orderId) {

        $d = $this->get('get-sms', array('request_id' => $orderId));

        if (isset($d['_error'])) {
            return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => $d['_error']);
        }

        // Waiting for SMS
        if (!empty($d['error_code']) && $d['error_code'] === 'wait_sms') {
            return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => 'Waiting for SMS');
        }

        // No number / cancelled
        if (!empty($d['error_code'])) {
            return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => (string)$d['error_code']);
        }

        $otp = null;

        // Primary: sms_code field
        if (!empty($d['sms_code'])) {
            $otp = trim((string)$d['sms_code']);
        }

        // Fallback: extract digits from full SMS text
        if (!$otp && !empty($d['msg'])) {
            // Try to extract 4-8 digit OTP code
            if (preg_match('/\b(\d{4,8})\b/', $d['msg'], $m)) {
                $otp = $m[1];
            }
        }

        if ($otp) {
            return array('success' => true, 'otp' => $otp, 'status' => 'RECEIVED');
        }

        return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => 'No code yet');
    }

    // ── Set status ───────────────────────────────────────────
    // Status IDs: 1=received, 6=finished, 8=cancel, 9=ban
    private function setStatus($orderId, $statusId) {

        $d = $this->get('set-status', array(
            'request_id' => $orderId,
            'status'     => $statusId
        ));

        return !isset($d['_error']) && empty($d['error_code']);
    }

    public function cancelOrder($orderId) {
        $ok = $this->setStatus($orderId, 8);
        if (!$ok) { sleep(1); $ok = $this->setStatus($orderId, 8); }
        return $ok
            ? array('success' => true,  'message' => '')
            : array('success' => false, 'message' => 'Cancel failed.');
    }

    public function finishOrder($orderId) {
        return $this->setStatus($orderId, 6);
    }

    public function banOrder($orderId) {
        return $this->setStatus($orderId, 9);
    }
}
