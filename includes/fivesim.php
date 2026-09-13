<?php
// ============================================================
// DonnieSMS OTP - includes/fivesim.php
//
// PRICE FIX (May 2026):
// Bug: getServicesForCountry() was using /guest/products Price
// field (which is in 5sim's own credit unit, NOT USD).
// e.g. Signal USA shows Price:4 credits ≈ but buy costs $0.116
//
// Fix: getServicesForCountry() now calls the authenticated
// /guest/prices?country=X endpoint which returns 'cost' in
// real USD (two decimal places) — exactly what 5sim charges.
// Markup is applied on top of the true USD cost.
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/functions.php';

class FiveSim {

    private $apiKey;
    private $base = 'https://5sim.net/v1';

    public function __construct($apiKey = '') {
        if (!empty($apiKey)) {
            $this->apiKey = $apiKey;
        } else {
            if (defined('FIVESIM_API_KEY')) {
                $this->apiKey = get_setting('fivesim_api_key', FIVESIM_API_KEY);
            } else {
                $this->apiKey = get_setting('fivesim_api_key', '');
            }
        }
    }

    // ── Safe string helper ────────────────────────────────────
    private function toString($val, $fallback = '') {
        if (is_string($val) && $val !== '') return $val;
        if (is_int($val) || is_float($val)) return (string)$val;
        if (is_array($val)) {
            foreach (array('iso','code','name','value',0) as $k) {
                if (isset($val[$k]) && is_string($val[$k]) && $val[$k] !== '') {
                    return $val[$k];
                }
            }
        }
        return $fallback;
    }

    // ── cURL request ──────────────────────────────────────────
    private function request($endpoint, $auth = true, $method = 'GET') {

        $url = $this->base . $endpoint;

        $headers = array(
            'Accept: application/json',
            'User-Agent: Mozilla/5.0'
        );

        if ($auth && !empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . trim($this->apiKey);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT,        30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if (!empty($err)) {
            return array('_error' => 'cURL Error: ' . $err, '_code' => 0, '_raw' => '');
        }

        if ($res === false || trim($res) === '') {
            return array('_error' => 'Empty response (HTTP ' . $code . ')', '_code' => $code, '_raw' => '');
        }

        $data = json_decode($res, true);

        if (!is_array($data)) {
            return array('_error' => trim($res), '_code' => $code, '_raw' => $res);
        }

        $data['_code'] = $code;
        $data['_raw']  = $res;

        return $data;
    }

    // ── Get countries ─────────────────────────────────────────
    public function getCountries() {

        $data = $this->request('/guest/countries', false);
        if (isset($data['_error'])) return array();
        unset($data['_code'], $data['_raw']);

        $out = array();
        foreach ($data as $slug => $info) {
            if (!is_array($info)) continue;
            $out[$slug] = array(
                'iso'    => strtoupper($this->toString(isset($info['iso']) ? $info['iso'] : '', $slug)),
                'prefix' => $this->toString(isset($info['prefix']) ? $info['prefix'] : '', ''),
                'text'   => $this->toString(isset($info['text']) ? $info['text'] : '', $slug)
            );
        }
        return $out;
    }

    // ── Get products (raw, returns credits-based Price) ───────
    public function getProducts($country = 'any', $operator = 'any') {

        $country  = empty($country)  ? 'any' : strtolower(trim($country));
        $operator = empty($operator) ? 'any' : strtolower(trim($operator));

        $data = $this->request('/guest/products/' . $country . '/' . $operator, false);
        if (isset($data['_error'])) return array();
        unset($data['_code'], $data['_raw']);

        return $data;
    }

    // ── Get real USD prices from /guest/prices ────────────────
    // Returns: [ 'signal' => 0.116, 'whatsapp' => 0.09, ... ]
    // This is the correct USD cost per product for the country.
    private function getRealPrices($country = 'any') {

        $country = empty($country) ? 'any' : strtolower(trim($country));

        // Use the public prices endpoint — no auth needed
        if ($country === 'any') {
            $endpoint = '/guest/prices';
        } else {
            $endpoint = '/guest/prices?country=' . urlencode($country);
        }

        $data = $this->request($endpoint, false);

        if (isset($data['_error'])) return array();

        unset($data['_code'], $data['_raw']);

        // Response structure from /guest/prices:
        // {
        //   "england": {
        //     "signal": {
        //       "virtual63": { "cost": 0.05, "count": 10, "rate": 85 },
        //       "virtual18": { "cost": 0.116, "count": 5,  "rate": 99 },
        //       "virtual4":  { "cost": 0.08,  "count": 0,  "rate": 90 }
        //     }
        //   }
        // }
        //
        // STRATEGY: pick the HIGHEST-COST virtual operator that has stock (count > 0).
        // Higher cost = premium virtual = better delivery rate.
        // Real operators (vodafone, ee, etc.) are excluded — only virtual* operators.
        // If NO virtual with stock found, fall back to highest-cost real operator with stock.

        $prices = array(); // product => ['cost' => float, 'operator' => string]

        foreach ($data as $countryKey => $countryVal) {
            if (!is_array($countryVal)) continue;

            foreach ($countryVal as $product => $operators) {
                if (!is_array($operators)) continue;

                $bestVirtualCost = -1;
                $bestRealCost    = -1;

                foreach ($operators as $opName => $opData) {
                    if (!is_array($opData)) continue;

                    $cost  = isset($opData['cost'])  ? (float)$opData['cost']  : 0;
                    $count = isset($opData['count']) ? (int)$opData['count']   : 0;

                    // Must have stock and a real price
                    if ($cost <= 0 || $count <= 0) continue;

                    $isVirtual = (strpos((string)$opName, 'virtual') === 0);

                    if ($isVirtual) {
                        // Among virtual operators, pick the HIGHEST cost (premium quality)
                        if ($cost > $bestVirtualCost) {
                            $bestVirtualCost = $cost;
                        }
                    } else {
                        // Real operator fallback — also pick highest
                        if ($cost > $bestRealCost) {
                            $bestRealCost = $cost;
                        }
                    }
                }

                // Prefer highest virtual; fall back to highest real
                $chosen = ($bestVirtualCost > 0) ? $bestVirtualCost : $bestRealCost;

                if ($chosen > 0) {
                    // If same product appears in multiple countries, keep highest price
                    if (!isset($prices[$product]) || $chosen > $prices[$product]) {
                        $prices[$product] = $chosen;
                    }
                }
            }
        }

        return $prices;
    }

    // ── Get services for country with CORRECT USD pricing ─────
    // FIXED: Uses /guest/prices 'cost' (real USD) instead of
    //        /guest/products 'Price' (5sim credits, NOT USD).
    // Markup is applied on top of the real USD cost.
    public function getServicesForCountry($country = 'any', $markupPct = 0) {

        // Step 1: get product list for availability (Qty)
        $raw = $this->getProducts($country, 'any');
        if (empty($raw)) return array();

        // Step 2: get REAL USD prices
        $realPrices = $this->getRealPrices($country);

        $services = array();

        foreach ($raw as $slug => $info) {

            if (!is_array($info)) continue;

            $qty = isset($info['Qty']) ? (int)$info['Qty'] : 0;
            if ($qty <= 0) continue;

            // Use real USD cost from /guest/prices — fallback to 0 if not found
            $costUSD = isset($realPrices[$slug]) ? (float)$realPrices[$slug] : 0;

            // Also try /guest/products Price as fallback ONLY if no real price found
            // But /guest/products Price is in 5sim credits — 1 credit ≈ varies
            // Do NOT use it as USD — skip product if no real price available
            if ($costUSD <= 0) continue;

            // Apply your markup on top of real cost
            if ($markupPct > 0) {
                $displayPrice = round($costUSD * (1 + ($markupPct / 100)), 4);
            } else {
                $displayPrice = $costUSD;
            }

            $services[] = array(
                'slug'          => $slug,
                'name'          => ucwords(str_replace(array('_','-'), ' ', $slug)),
                'qty'           => $qty,
                'price_raw'     => $costUSD,      // true 5sim cost in USD
                'price_display' => $displayPrice, // what user pays
                'country'       => $country
            );
        }

        usort($services, array($this, 'sortServices'));

        return $services;
    }

    private function sortServices($a, $b) {
        return strcmp($a['name'], $b['name']);
    }

    // ========================================================
    // BUY NUMBER
    // 5sim: GET /v1/user/buy/activation/{country}/{operator}/{product}
    //
    // SECURITY FIX (numbers.php):
    // The price the user pays is now taken from the ACTUAL buy
    // response (data['price']) — NOT from $_POST['price'].
    // This prevents users from editing the POST price to $0.
    // ========================================================
    public function buyNumber($country, $operator, $product) {

        // Use 'any' so 5sim picks from ALL available operators including virtuals.
        // 'virtual' is NOT a valid operator keyword in the 5sim API — it causes
        // 'no free phones'. Price display uses highest virtual cost separately.
        $country  = empty($country)  ? 'any' : strtolower(trim($country));
        $operator = 'any';
        $product  = strtolower(trim($product));

        $data = $this->request(
            '/user/buy/activation/' . $country . '/' . $operator . '/' . $product
        );

        if (isset($data['_error'])) {
            return array('success' => false, 'message' => $data['_error']);
        }

        if (!isset($data['_code']) || $data['_code'] != 200) {
            $msg = 'No number available';
            if (!empty($data['message'])) $msg = ucfirst(trim($data['message']));
            elseif (!empty($data['error']))   $msg = ucfirst(trim($data['error']));
            return array('success' => false, 'message' => $msg);
        }

        if (empty($data['phone'])) {
            return array('success' => false, 'message' => 'No phone number returned by 5sim.');
        }

        if (empty($data['id'])) {
            return array('success' => false, 'message' => 'No order ID returned by 5sim.');
        }

        // 'price' in the buy response IS real USD (e.g. 0.116 for Signal USA)
        $realCostUSD = isset($data['price']) ? (float)$data['price'] : 0;

        return array(
            'success'       => true,
            'id'            => (string)(int)$data['id'],
            'phone'         => $this->toString(isset($data['phone'])    ? $data['phone']    : ''),
            'operator'      => $this->toString(isset($data['operator']) ? $data['operator'] : $operator),
            'product'       => $this->toString(isset($data['product'])  ? $data['product']  : $product),
            'price'         => $realCostUSD,   // TRUE 5sim cost (USD)
            'status'        => $this->toString(isset($data['status'])   ? $data['status']   : 'PENDING'),
            'country'       => $this->toString(isset($data['country'])  ? $data['country']  : $country)
        );
    }

    // ========================================================
    // CHECK ORDER / GET SMS
    // 5sim: GET /v1/user/check/{id}
    // ========================================================
    public function checkOrder($orderId) {

        $id = (int)$orderId;
        if ($id <= 0) {
            return array('success' => false, 'otp' => null, 'status' => 'ERROR', 'message' => 'Invalid order ID: ' . $orderId);
        }

        $data = $this->request('/user/check/' . $id);

        if (isset($data['_error'])) {
            return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => $data['_error']);
        }

        $status = isset($data['status']) ? strtoupper(trim($data['status'])) : 'PENDING';
        $otp    = null;

        if (!empty($data['sms']) && is_array($data['sms'])) {
            foreach ($data['sms'] as $sms) {
                if (!empty($sms['code'])) {
                    $otp = trim($sms['code']);
                    break;
                }
                if (!empty($sms['text'])) {
                    preg_match('/\b(\d{4,8})\b/', $sms['text'], $matches);
                    if (!empty($matches[1])) {
                        $otp = trim($matches[1]);
                        break;
                    }
                }
            }
        }

        if (!empty($otp)) {
            $status = 'RECEIVED';
        }

        return array('success' => !empty($otp), 'otp' => $otp, 'status' => $status);
    }

    // ========================================================
    // FINISH ORDER — GET /v1/user/finish/{id}
    // ========================================================
    public function finishOrder($orderId) {
        $id = (int)$orderId;
        if ($id <= 0) return false;
        $data = $this->request('/user/finish/' . $id);
        return (isset($data['_code']) && $data['_code'] == 200);
    }

    // ========================================================
    // CANCEL ORDER — GET /v1/user/cancel/{id}
    // Returns array with 'success' key — MUST be checked before
    // refunding (see otp-active.php for proper usage).
    // ========================================================
    public function cancelOrder($orderId) {

        $id = (int)$orderId;
        if ($id <= 0) {
            return array('success' => false, 'message' => 'Invalid order ID: ' . $orderId);
        }

        $data = $this->request('/user/cancel/' . $id);
        $ok   = (isset($data['_code']) && $data['_code'] == 200);

        if (!$ok) {
            sleep(1);
            $data = $this->request('/user/cancel/' . $id);
            $ok   = (isset($data['_code']) && $data['_code'] == 200);
        }

        if ($ok) {
            return array('success' => true, 'message' => '');
        }

        $errMsg = 'Cancel failed (HTTP ' . ($data['_code'] ?? 0) . ')';
        if (!empty($data['_error']))     $errMsg .= ': ' . $data['_error'];
        elseif (!empty($data['message'])) $errMsg .= ': ' . $data['message'];

        return array('success' => false, 'message' => $errMsg);
    }

    // ========================================================
    // BAN ORDER — GET /v1/user/ban/{id}
    // ========================================================
    public function banOrder($orderId) {
        $id = (int)$orderId;
        if ($id <= 0) return false;
        $data = $this->request('/user/ban/' . $id);
        return (isset($data['_code']) && $data['_code'] == 200);
    }
}
