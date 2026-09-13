<?php
// DonnieSMS OTP - includes/logsplug.php
//
// Integration for LogsPlug (v2.api.logsplug.com) Virtual Numbers
// API, wired in as an extra provider alongside 5sim / VerifySMS /
// SMS-Man / OtpSuite.
//
// LogsPlug has THREE sub-servers, selected with a "server" param:
//   server1  = ONE_STEP  (direct: GET /services?server=server1)
//   server2  = TWO_STEP  (GET /countries?server=server2 first,
//                         then GET /services?server=server2&country=ID)
//   server3  = ONE_STEP  (USA numbers, direct like server1)
//
// NOTE: endpoint paths below were partly inferred from limited docs.
// The /services path and its field names (serviceId, apiPrice,
// listPrice, countryCode, currency) are now CONFIRMED from a real
// server1 response. /countries, /rent and /cancel are still best
// guesses - if a call to those fails, fix the path constants below,
// nothing else in the app needs to change.

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/functions.php';

class LogsPlug {

    // -- Endpoint paths (relative to base URL) - EDIT HERE IF WRONG --
    const EP_SERVERS   = '/third-party/numbers/servers';
    const EP_COUNTRIES = '/third-party/numbers/countries';
    const EP_SERVICES  = '/third-party/numbers/services';
    const EP_RENT      = '/third-party/numbers/rent';
    const EP_OTP       = '/third-party/numbers/rent/%s/otp';    // %s = rental id  (CONFIRMED path)
    const EP_CANCEL    = '/third-party/numbers/rent/%s/cancel'; // %s = rental id  (inferred)

    private $apiKey;
    private $base;
    private $lastError = '';

    public function getLastError() {
        return $this->lastError;
    }

    public function __construct($apiKey = '') {
        if (!empty($apiKey)) {
            $this->apiKey = $apiKey;
        } else {
            $this->apiKey = get_setting('logsplug_api_key', defined('LOGSPLUG_API_KEY') ? LOGSPLUG_API_KEY : '');
        }
        $this->base = get_setting('logsplug_base_url', defined('LOGSPLUG_BASE') ? LOGSPLUG_BASE : 'https://v2.api.logsplug.com/api/v2');
    }

    // -- cURL request ------------------------------------------
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ));

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            error_log('Saver3 cURL error: ' . $err);
            $this->lastError = 'Connection failed calling ' . $endpoint . ' - ' . $err;
            return array('_error' => 'Connection error, please try again');
        }
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('Saver3 bad response [' . $code . ']: ' . $raw);
            $this->lastError = 'Non-JSON response (HTTP ' . $code . ') from ' . $endpoint . ' - ' . substr((string)$raw, 0, 200);
            return array('_error' => 'Unexpected response, please try again');
        }

        if (isset($decoded['status']) && $decoded['status'] !== 'success') {
            $this->lastError = 'HTTP ' . $code . ' from ' . $endpoint . ' - ' . ($decoded['message'] ?? json_encode($decoded));
            return array('_error' => $decoded['message'] ?? 'Something went wrong, please try again');
        }
        if ($code >= 400) {
            $this->lastError = 'HTTP ' . $code . ' from ' . $endpoint . ' - ' . ($decoded['message'] ?? json_encode($decoded));
            return array('_error' => $decoded['message'] ?? ('Provider error (HTTP ' . $code . ')'));
        }

        return isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
    }

    // -- Pull the first matching key out of a row, several
    //    possible names deep (response field names aren't
    //    100% confirmed by the docs, so we hedge) -------------
    private static function pick($row, array $keys, $fallback = null) {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
        }
        return $fallback;
    }

    // -- List servers (server1 / server2 / server3) -------------
    public function getServers() {
        $data = $this->request(self::EP_SERVERS);
        if (isset($data['_error'])) return array();

        $list = isset($data[0]) ? $data : ($data['servers'] ?? array());
        $out  = array();
        foreach ($list as $s) {
            $id = self::pick($s, array('id'));
            if (!$id) continue;
            $out[$id] = array(
                'id'          => $id,
                'name'        => self::pick($s, array('name'), $id),
                'description' => self::pick($s, array('description'), ''),
                'flowType'    => self::pick($s, array('flowType'), 'ONE_STEP'),
            );
        }
        return $out;
    }

    // -- Countries for server2 (TWO_STEP) ------------------------
    // Returns [ 'ID' => ['iso'=>..,'name'=>..], ... ]
    public function getCountries($server = 'server2') {
        $data = $this->request(self::EP_COUNTRIES, 'GET', array('server' => $server));
        if (isset($data['_error'])) return array();

        $list = isset($data[0]) ? $data : ($data['countries'] ?? array());
        $out  = array();
        foreach ($list as $c) {
            $id = self::pick($c, array('countryCode', 'id', 'countryId', 'country_id'));
            if (!$id) continue;
            $out[(string)$id] = array(
                'iso'  => self::pick($c, array('countryCode', 'iso', 'code'), (string)$id),
                'name' => self::pick($c, array('name', 'country', 'countryName'), (string)$id),
            );
        }
        return $out;
    }

    // -- Services for a server (+ country for server2), markup applied -
    // Returns the same shape numbers.php expects from every provider.
    public function getServicesForServer($server = 'server1', $country = null, $markupPct = 0) {

        $params = array('server' => $server);
        if ($server === 'server2' && !empty($country) && $country !== 'any') {
            $params['country'] = $country;
        }

        $data = $this->request(self::EP_SERVICES, 'GET', $params);
        if (isset($data['_error'])) return array();

        $list = isset($data[0]) ? $data : ($data['services'] ?? array());

        if (empty($list)) {
            $this->lastError = 'Call to ' . self::EP_SERVICES . ' succeeded but returned no rows - raw shape: ' . substr(json_encode($data), 0, 200);
            return array();
        }

        $services = array();
        $skippedNoPrice = 0;

        foreach ($list as $s) {
            $rawCost = (float)self::pick($s, array('apiPrice', 'listPrice', 'price', 'cost', 'amount', 'your_price'), 0);
            if ($rawCost <= 0) { $skippedNoPrice++; continue; }

            $currency = strtoupper((string)self::pick($s, array('currency'), 'NGN'));
            $usdCost  = ($currency === 'USD') ? $rawCost : ngn_to_usd($rawCost);
            if ($usdCost <= 0) { $skippedNoPrice++; continue; }

            $displayPrice = $markupPct > 0
                ? round($usdCost * (1 + ($markupPct / 100)), 4)
                : round($usdCost, 4);

            $services[] = array(
                'slug'          => (string)self::pick($s, array('serviceId', 'id', 'service_id', 'slug')),
                'name'          => self::pick($s, array('name', 'serviceName', 'service_name'), 'Service'),
                'qty'           => (int)self::pick($s, array('qty', 'stock', 'available'), 999),
                'price_raw'     => round($usdCost, 4),
                'price_display' => $displayPrice,
                'country'       => $server === 'server2' ? (string)$country : (string)self::pick($s, array('countryCode', 'country'), 'any'),
            );
        }

        usort($services, function ($a, $b) { return strcmp($a['name'], $b['name']); });

        if (empty($services) && $skippedNoPrice > 0) {
            $sample = isset($list[0]) ? json_encode($list[0]) : '';
            $this->lastError = 'Got ' . count($list) . ' rows from ' . self::EP_SERVICES . ' but none had a usable price field - sample row: ' . substr($sample, 0, 200);
        }

        return $services;
    }

    // -- Rent (buy) a number -----------------------------------
    public function buyNumber($server, $country, $serviceSlug) {

        $params = array('server' => $server, 'service' => $serviceSlug);
        if ($server === 'server2' && !empty($country) && $country !== 'any') {
            $params['country'] = $country;
        }

        $data = $this->request(self::EP_RENT, 'POST', $params);
        if (isset($data['_error'])) {
            return array('success' => false, 'message' => $data['_error']);
        }

        // Sample shapes seen in the docs nest things under 'rental' -
        // hedge for both a flat response and a {rental:{...}} wrapper.
        $rental = isset($data['rental']) && is_array($data['rental']) ? $data['rental'] : $data;

        $id    = self::pick($rental, array('numberId', 'id', 'rentalId'));
        $phone = self::pick($rental, array('number', 'phone', 'phoneNumber'));

        if (empty($id) || empty($phone)) {
            return array('success' => false, 'message' => 'No number available for this service right now.');
        }

        $rawCost  = (float)self::pick($rental, array('apiPrice', 'listPrice', 'price', 'amount', 'cost'), 0);
        $currency = strtoupper((string)self::pick($rental, array('currency'), 'NGN'));
        $usdCost  = $rawCost > 0 ? (($currency === 'USD') ? $rawCost : ngn_to_usd($rawCost)) : 0;

        return array(
            'success'  => true,
            'id'       => (string)$id,
            'phone'    => $phone,
            'operator' => 'logsplug',
            'product'  => $serviceSlug,
            'price'    => round($usdCost, 4), // TRUE cost in USD (markup applied by caller)
            'status'   => 'PENDING',
            'country'  => $server === 'server2' ? (string)$country : 'any',
        );
    }

    // -- Poll for the OTP - CONFIRMED path from the docs ---------
    public function checkOrder($rentalId) {

        if (empty($rentalId)) {
            return array('success' => false, 'otp' => null, 'status' => 'ERROR', 'message' => 'Invalid rental ID');
        }

        $data = $this->request(sprintf(self::EP_OTP, $rentalId));

        if (isset($data['_error'])) {
            return array('success' => false, 'otp' => null, 'status' => 'PENDING', 'message' => $data['_error']);
        }

        // CONFIRMED real shape from a live poll:
        // {"id":"...","code":[],"status":"PROCESSING","expiresAt":"...","serverId":"..."}
        // 'code' is an ARRAY - empty [] until the SMS lands, then holds the code(s).
        $rawCode = $data['code'] ?? null;

        $code = null;
        if (is_array($rawCode) && !empty($rawCode)) {
            $code = end($rawCode); // most recent code
        } elseif (is_string($rawCode) && $rawCode !== '') {
            $code = $rawCode; // hedge in case a future response ever sends it as a bare string
        }

        $rawSnippet = substr(json_encode($data), 0, 350);
        error_log('LogsPlug OTP poll [' . $rentalId . ']: ' . $rawSnippet);

        // We trust the 'code' array as the source of truth for whether the OTP has
        // arrived - the 'status' text is just carried through for display/debugging.
        $rawStatus = strtoupper((string)($data['status'] ?? 'PENDING'));
        $status    = !empty($code) ? 'RECEIVED' : ($rawStatus === 'PENDING' ? 'PENDING' : $rawStatus);

        return array(
            'success' => !empty($code),
            'otp'     => $code,
            'status'  => $status,
            'debug'   => $rawSnippet, // raw response, so it can be shown on-screen if no code was found
        );
    }

    // -- Cancel a rental (refunded only if no SMS within 2 min) --
    public function cancelOrder($rentalId) {

        if (empty($rentalId)) {
            return array('success' => false, 'message' => 'Invalid rental ID');
        }

        $data = $this->request(sprintf(self::EP_CANCEL, $rentalId), 'POST');

        if (isset($data['_error'])) {
            return array('success' => false, 'message' => $this->lastError ?: $data['_error']);
        }

        // Docs sample nests a second 'data'/'rental' layer under cancel -
        // hedge for both single- and double-nested shapes.
        $inner    = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
        $refunded = (bool)self::pick($inner, array('refunded'), true);

        return array('success' => true, 'refunded' => $refunded, 'message' => '');
    }

    // -- No documented "finish" endpoint - no-op success ---------
    public function finishOrder($rentalId) {
        return true;
    }

    // -- No documented "ban" endpoint - cancel with the provider
    //    so it isn't left dangling; refund handled by caller -----
    public function banOrder($rentalId) {
        $this->cancelOrder($rentalId);
        return true;
    }

    // -- Wallet balance - NOT in the pasted docs (Logs & Wallet
    //    section was cut off). Returns 0 until confirmed; this is
    //    only used for an optional pre-flight check in numbers.php,
    //    never for anything money-critical. ---------------------
    public function getBalance() {
        return 0.0;
    }
}
