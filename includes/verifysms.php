<?php
// ============================================================
// DonnieSMS OTP - includes/verifysms.php
// VerifySMS.io API Integration
//
// PRICE FIX (May 2026):
// getServices() now checks both 'cost' AND 'price' keys since
// the VerifySMS /services endpoint has returned both field names
// at different times. Whichever is non-zero is used.
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/functions.php';

class VerifySMS {

    private $apiKey;
    private $base = 'https://verifysms.io/api';

    // ========================================================
    // CONSTRUCTOR
    // ========================================================
    public function __construct($apiKey = '') {

        if (!empty($apiKey)) {

            $this->apiKey = $apiKey;

        } else {

            if (defined('VERIFYSMS_API_KEY') && VERIFYSMS_API_KEY !== '') {

                $this->apiKey = get_setting(
                    'verifysms_api_key',
                    VERIFYSMS_API_KEY
                );

            } else {

                $this->apiKey = get_setting(
                    'verifysms_api_key',
                    ''
                );
            }
        }
    }

    // ========================================================
    // REQUEST
    // ========================================================
    private function request(
        $endpoint,
        $method = 'GET',
        $body = null
    ) {

        $url = $this->base . $endpoint;

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json'
        );

        if (!empty($this->apiKey)) {
            $headers[] = 'API-KEY: ' . trim($this->apiKey);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $body !== null ? json_encode($body) : '{}'
            );
        }

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if (!empty($err)) {
            return array(
                '_error' => 'cURL Error: ' . $err,
                '_code'  => 0,
                '_raw'   => ''
            );
        }

        if ($res === false || trim($res) === '') {
            return array(
                '_error' => 'Empty response from VerifySMS',
                '_code'  => $code,
                '_raw'   => ''
            );
        }

        $trimmed = trim($res);
        $data    = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (!is_array($data)) {
                $data = array('_value' => $data);
            }
            $data['_code'] = $code;
            $data['_raw']  = $trimmed;
            return $data;
        }

        return array(
            '_value' => $trimmed,
            '_code'  => $code,
            '_raw'   => $trimmed
        );
    }

    // ========================================================
    // EXTRACT PRICE FROM SERVICE INFO
    // VerifySMS has used both 'cost' and 'price' as field names.
    // This helper checks both and returns whichever is non-zero.
    // ========================================================
    private function extractPrice($info) {

        // Try 'cost' first (original field name)
        if (isset($info['cost']) && (float)$info['cost'] > 0) {
            return (float)$info['cost'];
        }

        // Try 'price' (alternate field name seen in some responses)
        if (isset($info['price']) && (float)$info['price'] > 0) {
            return (float)$info['price'];
        }

        // Try 'amount' (another possible field)
        if (isset($info['amount']) && (float)$info['amount'] > 0) {
            return (float)$info['amount'];
        }

        return 0.0;
    }

    // ========================================================
    // GET SERVICES
    // Returns array of services with slug/name/cost/in_stock
    // ========================================================
    public function getServices() {

        $data = $this->request('/services');

        if (isset($data['_error'])) {
            return array();
        }

        $out = array();

        foreach ($data as $code => $info) {

            if ($code === '_code' || $code === '_raw' || $code === '_value') {
                continue;
            }

            if (!is_array($info)) {
                continue;
            }

            // ── in_stock check ────────────────────────────────
            // Some responses use 'in_stock' (bool), others use
            // 'available' or simply omit the field (assume true).
            $inStock = true;
            if (isset($info['in_stock'])) {
                $inStock = (bool)$info['in_stock'];
            } elseif (isset($info['available'])) {
                $inStock = (bool)$info['available'];
            }

            if (!$inStock) {
                continue;
            }

            // ── price extraction ──────────────────────────────
            $cost = $this->extractPrice($info);

            // Skip services with no price — they are not purchasable
            if ($cost <= 0) {
                continue;
            }

            $out[] = array(
                'slug'          => $code,
                'name'          => isset($info['name'])
                    ? $info['name']
                    : ucwords(str_replace(array('_', '-'), ' ', $code)),
                'qty'           => $inStock ? 999 : 0,
                'price_raw'     => $cost,
                'price_display' => $cost,   // markup applied in getServicesForCountry()
                'country'       => 'US',
                'in_stock'      => $inStock
            );
        }

        usort($out, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $out;
    }

    // ========================================================
    // GET SERVICES WITH MARKUP
    // ========================================================
    public function getServicesForCountry(
        $country = 'any',
        $markupPct = 0
    ) {
        // VerifySMS only has US numbers — country filter ignored
        $services = $this->getServices();

        if ($markupPct > 0) {
            foreach ($services as &$svc) {
                $svc['price_display'] = round(
                    $svc['price_raw'] * (1 + ($markupPct / 100)),
                    4
                );
            }
            unset($svc);
        }

        return $services;
    }

    // ========================================================
    // GET COUNTRIES (stub — VerifySMS is US only)
    // ========================================================
    public function getCountries() {
        return array(
            'us' => array(
                'iso'    => 'US',
                'prefix' => '+1',
                'text'   => 'United States'
            )
        );
    }

    // ========================================================
    // BUY NUMBER (rent)
    // ========================================================
    public function buyNumber(
        $country,
        $operator,
        $product
    ) {
        $body = array('code' => $product);

        $data = $this->request('/rent', 'POST', $body);

        if (isset($data['_error'])) {
            return array(
                'success' => false,
                'message' => $data['_error']
            );
        }

        $code = isset($data['_code']) ? (int)$data['_code'] : 0;

        if ($code !== 200) {

            $msg = 'No number available';

            if ($code === 401) {
                $msg = 'Invalid VerifySMS API key. Check Admin → Settings.';
            } elseif ($code === 402) {
                $msg = 'Insufficient VerifySMS account balance.';
            } elseif ($code === 404) {
                $msg = 'Service not found on VerifySMS.';
            } elseif ($code === 409) {
                $msg = 'No numbers available for this service right now. Try again later.';
            } elseif (!empty($data['message'])) {
                $msg = ucfirst(trim($data['message']));
            } elseif (!empty($data['error'])) {
                $msg = ucfirst(trim($data['error']));
            }

            return array(
                'success' => false,
                'message' => $msg
            );
        }

        if (empty($data['number'])) {
            return array(
                'success' => false,
                'message' => 'No phone number returned by VerifySMS.'
            );
        }

        if (empty($data['transaction_id'])) {
            return array(
                'success' => false,
                'message' => 'No transaction ID returned by VerifySMS.'
            );
        }

        $svcName = isset($data['service'])
            ? $data['service']
            : ucwords(str_replace(array('_', '-'), ' ', $product));

        // Extract actual charged price from buy response
        $realPrice = $this->extractPrice($data);

        return array(
            'success'  => true,
            'id'       => $data['transaction_id'],
            'phone'    => $data['number'],
            'operator' => 'verifysms',
            'product'  => $product,
            'price'    => $realPrice,   // real USD cost from API response
            'status'   => 'PENDING',
            'country'  => 'us',
            'service'  => $svcName
        );
    }

    // ========================================================
    // CHECK ORDER (poll for OTP)
    // ========================================================
    public function checkOrder($transactionId) {

        $data = $this->request(
            '/code?transaction_id=' . urlencode($transactionId)
        );

        if (isset($data['_error'])) {
            return array(
                'success' => false,
                'otp'     => null,
                'status'  => 'ERROR',
                'message' => $data['_error']
            );
        }

        $code = isset($data['_code']) ? (int)$data['_code'] : 0;

        if ($code === 409) {
            return array(
                'success' => false,
                'otp'     => null,
                'status'  => 'PENDING'
            );
        }

        if ($code === 400) {
            $msg = isset($data['_raw']) ? $data['_raw'] : 'Transaction cancelled or invalid.';
            return array(
                'success' => false,
                'otp'     => null,
                'status'  => 'CANCELED',
                'message' => $msg
            );
        }

        if ($code === 401) {
            return array(
                'success' => false,
                'otp'     => null,
                'status'  => 'ERROR',
                'message' => 'Invalid API key.'
            );
        }

        if ($code === 200) {

            $otp = '';

            if (isset($data['_value']) && $data['_value'] !== '') {
                $otp = trim($data['_value']);
            } elseif (isset($data['_raw']) && $data['_raw'] !== '') {
                $otp = trim($data['_raw']);
            }

            $otp = trim($otp, '"\'');

            if ($otp !== '') {
                return array(
                    'success' => true,
                    'otp'     => $otp,
                    'status'  => 'RECEIVED'
                );
            }
        }

        return array(
            'success' => false,
            'otp'     => null,
            'status'  => 'PENDING'
        );
    }

    // ========================================================
    // CANCEL ORDER
    // ========================================================
    public function cancelOrder($transactionId) {

        $data = $this->request(
            '/cancel',
            'POST',
            array('transaction_id' => $transactionId)
        );

        $code = isset($data['_code']) ? (int)$data['_code'] : 0;
        return ($code === 200);
    }

    // ========================================================
    // FINISH / BAN (not supported by VerifySMS)
    // ========================================================
    public function finishOrder($transactionId) {
        return true;
    }

    public function banOrder($transactionId) {
        return true;
    }

    // ========================================================
    // GET BALANCE
    // ========================================================
    public function getBalance() {

        $data = $this->request('/balance');

        if (isset($data['_error'])) {
            return null;
        }

        $val = isset($data['_value']) ? $data['_value'] : null;

        if ($val !== null && is_numeric($val)) {
            return (float)$val;
        }

        return null;
    }
}