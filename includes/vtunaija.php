<?php
// ============================================================
// DonnieSMS OTP - includes/vtunaija.php
//
// Integration for vtunaija.com.ng (Airtime, Data, Cable TV,
// Electricity, and more) — powers the new VTU Bills section.
//
// Verified against the official docs at
// vtunaija.com.ng/documentationApiIntegration/ on integration day:
// endpoints, headers, and field names below match exactly.
//
// Every method returns a normalized array:
//   ['ok' => bool, 'raw' => array, 'message' => string, 'plan_amount' => float|null, 'vtunaija_id' => string|null, 'extra' => array]
// 'ok' is true only when VTUnaija's own "status" field says "success".
// ============================================================

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
require_once ROOT_PATH . '/includes/functions.php';

class VTUnaijaAPI
{
    private string $apiKey;
    private string $baseUrl = 'https://vtunaija.com.ng/api';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? get_setting('vtu_api_key', '');
    }

    // ---------------------------------------------------------
    // Core request handler
    // ---------------------------------------------------------
    private function post(string $endpoint, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Token ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            error_log("VTUnaija API network error on {$endpoint}: {$curlError}");
            return $this->normalize([], false, 'Network error contacting provider. Please try again.');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log("VTUnaija API invalid JSON on {$endpoint}: {$response}");
            return $this->normalize([], false, 'Invalid response from provider.');
        }

        // VTUnaija uses lowercase "status": "success" | "fail" as the reliable flag
        $ok = isset($decoded['status']) && strtolower($decoded['status']) === 'success';

        return $this->normalize($decoded, $ok, $decoded['api_response'] ?? $decoded['message'] ?? '');
    }

    private function normalize(array $raw, bool $ok, string $message): array
    {
        return [
            'ok'          => $ok,
            'raw'         => $raw,
            'message'     => $message,
            'plan_amount' => isset($raw['plan_amount']) ? (float) $raw['plan_amount'] : null,
            'vtunaija_id' => $raw['id'] ?? $raw['ident'] ?? null,
            'extra'       => array_diff_key($raw, array_flip([
                'Status', 'status', 'api_response', 'message', 'id', 'ident', 'plan_amount',
            ])),
        ];
    }

    private function requestId(): string
    {
        return (string) (time() . random_int(1000, 9999));
    }

    // ---------------------------------------------------------
    // Airtime — POST /topup/
    // ---------------------------------------------------------
    public function buyAirtime(string $network, string $mobileNumber, string $amount, string $airtimeType = 'VTU', ?string $requestId = null, bool $portedNumber = true): array
    {
        return $this->post('/topup/', [
            'network'       => $network,
            'mobile_number' => $mobileNumber,
            'Ported_number' => $portedNumber ? 'true' : 'false',
            'request-id'    => $requestId ?? $this->requestId(),
            'amount'        => $amount,
            'airtime_type'  => $airtimeType,
        ]);
    }

    // ---------------------------------------------------------
    // Data — POST /data/
    // ---------------------------------------------------------
    public function buyData(string $network, string $mobileNumber, string $plan, ?string $requestId = null, bool $portedNumber = true): array
    {
        return $this->post('/data/', [
            'network'       => $network,
            'mobile_number' => $mobileNumber,
            'Ported_number' => $portedNumber ? 'true' : 'false',
            'request-id'    => $requestId ?? $this->requestId(),
            'plan'          => $plan,
        ]);
    }

    // ---------------------------------------------------------
    // Cable TV — POST /cablesub/verify/ and /cablesub/
    // ---------------------------------------------------------
    public function verifyCable(string $cablename, string $smartCardNumber): array
    {
        return $this->post('/cablesub/verify/', [
            'cablename'         => $cablename,
            'smart_card_number' => $smartCardNumber,
        ]);
    }

    public function buyCable(string $cablename, string $smartCardNumber, string $cableplan): array
    {
        return $this->post('/cablesub/', [
            'cablename'         => $cablename,
            'smart_card_number' => $smartCardNumber,
            'cableplan'         => $cableplan,
        ]);
    }

    // ---------------------------------------------------------
    // Electricity — POST /billpayment/verify/ and /billpayment/
    // ---------------------------------------------------------
    public function verifyMeter(string $discoName, string $meterNumber): array
    {
        return $this->post('/billpayment/verify/', [
            'disco_name'   => $discoName,
            'meter_number' => $meterNumber,
        ]);
    }

    public function payElectricity(string $discoName, string $meterNumber, string $amount, string $meterType = 'prepaid'): array
    {
        return $this->post('/billpayment/', [
            'disco_name'   => $discoName,
            'meter_number' => $meterNumber,
            'MeterType'    => $meterType,
            'amount'       => $amount,
        ]);
    }

    // ---------------------------------------------------------
    // Plan-list fetchers — used by Admin > VTU Sync.
    // Response shape is a list, not a single transaction, so this
    // doesn't go through post()'s normalize().
    // ---------------------------------------------------------
    private function postRaw(string $endpoint): array
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Token ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([]),
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'message' => 'Network error: ' . $error];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'message' => 'Invalid JSON response from provider.'];
        }
        $ok = isset($decoded['status']) && strtolower($decoded['status']) === 'success';
        return ['ok' => $ok, 'message' => $decoded['message'] ?? '', 'data' => $decoded];
    }

    public function listDataPlans(): array
    {
        return $this->postRaw('/listdataplans/');
    }

    public function listCableTvPlans(): array
    {
        return $this->postRaw('/listcabletvplans/');
    }

    public function listElectricityPlanIds(): array
    {
        return $this->postRaw('/listelectricity/');
    }
}
