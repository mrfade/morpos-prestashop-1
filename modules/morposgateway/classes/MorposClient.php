<?php
/**
 * MorPOS Payment Plugin Client
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MorposClient
{
    // Base URLs
    const SANDBOX_BASE_URL = 'https://finagopay-pf-sale-api-gateway.prp.morpara.com';
    const PRODUCTION_BASE_URL = 'https://sale-gateway.morpara.com';

    // Endpoints
    const EP_HOSTED_PAYMENT = '/v1/HostedPayment/HostedPaymentRedirect';
    const EP_EMBEDDED_PAYMENT = '/v1/EmbeddedPayment/CreatePaymentForm';
    const EP_CHECK_PAYMENT = '/v1/Payment/CheckPayment';
    const EP_BIN_CHECK = '/v1/BinList/CheckBin';

    // Scopes
    const SCOPE_PAYMENT = 'payment';
    const SCOPE_PF_RW = 'pf_write pf_read';

    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $merchantId;
    private $submerchantId;
    private $apiKey;
    private $environment;

    public function __construct($clientId, $clientSecret, $merchantId, $submerchantId, $apiKey, $environment = 'production')
    {
        $baseUrl = $environment === 'production'
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;

        $this->environment = $environment;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->merchantId = $merchantId;
        $this->submerchantId = $submerchantId;
        $this->apiKey = $apiKey;
    }

    /**
     * Create Hosted Payment and receive redirect URL.
     */
    public function createPayment($args)
    {
        $conversationId = isset($args['conversationId']) ? (string) $args['conversationId'] : $this->generateConversationId();
        $paymentMethod = isset($args['paymentMethod']) ? (string) $args['paymentMethod'] : 'HOSTEDPAYMENT';
        $paymentInstrumentType = isset($args['paymentInstrumentType']) ? (string) $args['paymentInstrumentType'] : 'CARD';
        $language = isset($args['language']) ? (string) $args['language'] : 'tr';
        $transactionType = isset($args['transactionType']) ? (string) $args['transactionType'] : 'SALE';
        $installmentCount = isset($args['installmentCount']) ? (int) $args['installmentCount'] : 0;
        $amount = (string) $args['amount'];
        $currencyCode = (string) $args['currencyCode'];
        $returnUrl = (string) $args['returnUrl'];
        $failUrl = (string) $args['failUrl'];

        $path = $paymentMethod === 'HOSTEDPAYMENT'
            ? self::EP_HOSTED_PAYMENT
            : self::EP_EMBEDDED_PAYMENT;

        $vftFlag = false;
        $sign = $this->sign(array(
            $conversationId,
            $this->merchantId,
            $returnUrl,
            $failUrl,
            $paymentMethod,
            $language,
            $paymentInstrumentType,
            $transactionType,
            $vftFlag ? 'True' : 'False',
            $installmentCount,
            $amount,
            $currencyCode,
            $this->submerchantId,
            $this->apiKey,
        ));

        $payload = array(
            'merchantId' => $this->merchantId,
            'returnUrl' => $returnUrl,
            'failUrl' => $failUrl,
            'paymentMethod' => $paymentMethod,
            'paymentInstrumentType' => $paymentInstrumentType,
            'language' => $language,
            'conversationId' => $conversationId,
            'sign' => $sign,
            'transactionDetails' => array(
                'transactionType' => $transactionType,
                'installmentCount' => $installmentCount,
                'amount' => $amount,
                'currencyCode' => $currencyCode,
                'vftFlag' => $vftFlag,
            ),
            'extraParameter' => array(
                'pFSubMerchantId' => $this->submerchantId,
            ),
        );

        return $this->postJson($path, $payload, self::SCOPE_PAYMENT, 'CreatePayment');
    }

    /**
     * Check payment status with MorPOS API.
     */
    public function checkPayment($args = array())
    {
        if ($this->apiKey === '') {
            return array(
                'ok' => false,
                'error' => 'API Key is required',
                'http' => null,
                'body' => null,
            );
        }

        $conversationId = $args['conversationId'];
        if (!$conversationId) {
            return array(
                'ok' => false,
                'error' => 'Conversation ID is required.',
                'http' => null,
                'body' => null,
            );
        }

        $sign = $this->sign(array(
            $conversationId,
            $this->merchantId,
            $this->apiKey,
        ));

        $payload = array(
            'conversationId' => $conversationId,
            'merchantId' => $this->merchantId,
            'sign' => $sign,
        );

        return $this->postJson(self::EP_CHECK_PAYMENT, $payload, self::SCOPE_PF_RW, 'CheckPayment');
    }

    /**
     * Small connectivity check to validate credentials.
     */
    public function makeTestConnection($args = array())
    {
        if ($this->apiKey === '') {
            return array(
                'ok' => false,
                'error' => 'API Key is required',
                'http' => null,
                'body' => null,
            );
        }

        $conversationId = isset($args['conversationId']) ? (string) $args['conversationId'] : $this->generateConversationId();
        $bin = isset($args['bin']) ? (string) $args['bin'] : '402940';
        $language = isset($args['language']) ? (string) $args['language'] : 'tr';

        $sign = $this->sign(array(
            $conversationId,
            $this->merchantId,
            $language,
            $bin,
            $this->apiKey,
        ));

        $payload = array(
            'bin' => $bin,
            'language' => $language,
            'conversationId' => $conversationId,
            'merchantId' => $this->merchantId,
            'sign' => $sign,
        );

        $response = $this->postJson(self::EP_BIN_CHECK, $payload, self::SCOPE_PF_RW, 'TestConnection');

        if (!$response['ok']) {
            return array('ok' => false);
        }

        $data = isset($response['data']) ? $response['data'] : array();

        if (!isset($data['responseCode']) || !isset($data['responseDescription'])) {
            return array('ok' => false);
        }

        if ($data['responseCode'] !== 'B0000' || $data['responseDescription'] !== 'Approved') {
            return array('ok' => false);
        }

        return array('ok' => true);
    }

    /**
     * Sends a POST request with JSON payload to the specified path.
     */
    private function postJson($path, $payload, $scope, $logPrefix)
    {
        $endpoint = $this->baseUrl . $path;
        $timestamp = $this->currentTimestampStr();
        $headers = $this->buildHeaders($timestamp, $scope);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
            function ($k, $v) {
                return $k . ': ' . $v;
            },
            array_keys($headers),
            $headers
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        // Always verify the TLS certificate (production and sandbox).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $raw = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        $err = $errno ? curl_error($ch) : null;
        curl_close($ch);

        if ($err !== null) {
            return array(
                'ok' => false,
                'error' => $err ?: 'cURL error',
                'http' => null,
                'body' => null,
            );
        }

        $body = $raw ?: '';
        $data = $this->jsonDecodeAssoc($body);

        if ($this->isHttpSuccess($code) && is_array($data)) {
            return array('ok' => true, 'http' => $code, 'data' => $data);
        }

        return array(
            'ok' => false,
            'http' => $code,
            'body' => $body,
            'message' => isset($data['Message']) ? $data['Message'] : null,
        );
    }

    /**
     * Builds the headers for the API request.
     */
    private function buildHeaders($timestamp, $scope)
    {
        return array(
            'Content-Type' => 'application/json',
            'X-ClientSecret' => $this->encodeHash($this->clientSecret, $timestamp),
            'X-ClientId' => $this->clientId,
            'X-Timestamp' => $timestamp,
            'X-GrantType' => 'client_credentials',
            'X-Scope' => $scope,
        );
    }

    /**
     * Checks if the HTTP status code indicates success (2xx).
     */
    private function isHttpSuccess($code)
    {
        return $code >= 200 && $code < 300;
    }

    /**
     * Decodes JSON string into associative array.
     */
    private function jsonDecodeAssoc($json)
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Returns the current timestamp in "YmdHis" format.
     */
    protected function currentTimestampStr()
    {
        return gmdate('YmdHis');
    }

    /**
     * Encodes the client secret and timestamp using SHA-256 and Base64 encoding.
     */
    protected function encodeHash($clientSecret, $timestamp)
    {
        $decoded = base64_decode($clientSecret, true);
        if ($decoded === false) {
            $decoded = '';
        }

        $shaHex = hash('sha256', $decoded . $timestamp);
        return base64_encode($shaHex);
    }

    /**
     * Signs the given fields using SHA-256 and Base64 encoding.
     */
    protected function sign($fields)
    {
        $concatenated = implode(';', array_values($fields));
        $shaBin = hash('sha256', $concatenated, true);
        $b64 = base64_encode($shaBin);
        return strtoupper($b64);
    }

    /**
     * Generates a unique conversation ID.
     */
    protected function generateConversationId()
    {
        return 'MSD' . rand(10000000000000000, 99999999999999999);
    }
}
