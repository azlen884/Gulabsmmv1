<?php
/**
 * RoseSMM - Official PhonePe Payment Gateway Integration Service
 * Real server-side API (PG v1 /pay and /status), SHA256 checksums,
 * server-to-server transaction status queries, and callback verification.
 */

require_once __DIR__ . '/PaymentHelper.php';

class PhonePeService {
    private array $config;
    private string $merchantId;
    private string $saltKey;
    private string $saltIndex;
    private string $mode;
    private string $apiBase;

    public function __construct(?array $config = null) {
        $this->config = $config ?: (PaymentHelper::getGateway('phonepe', false) ?: []);
        $this->merchantId = trim($this->config['merchant_id'] ?? '');
        $this->saltKey = trim($this->config['secret_key'] ?? '');
        
        // Salt index: check parameters JSON or api_key or default to '1'
        $saltIdx = trim($this->config['api_key'] ?? '');
        if (empty($saltIdx) && !empty($this->config['parameters'])) {
            $params = json_decode($this->config['parameters'], true);
            $saltIdx = $params['salt_index'] ?? '';
        }
        $this->saltIndex = $saltIdx ?: '1';

        $this->mode = strtolower(trim($this->config['mode'] ?? 'test'));
        $this->apiBase = ($this->mode === 'live')
            ? 'https://api.phonepe.com/apis/hermes'
            : 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    }

    public function isConfigured(): bool {
        return !empty($this->merchantId) && !empty($this->saltKey);
    }

    public function getMode(): string {
        return $this->mode;
    }

    /**
     * Initiate Payment on PhonePe (Standard Pay Page)
     */
    public function initiatePayment(
        int $userId,
        float $amount,
        string $currency,
        string $internalPaymentId,
        string $redirectUrl,
        string $callbackUrl,
        string $mobileNumber = ''
    ): array {
        if (!$this->isConfigured()) {
            throw new Exception("PhonePe Merchant credentials (Merchant ID / Salt Key) are not configured.");
        }

        // PhonePe requires transaction ID up to 38 chars alphanumeric/underscore
        $txnId = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $internalPaymentId), 0, 35);
        $amountInPaise = (int)round($amount * 100);

        $payload = [
            'merchantId' => $this->merchantId,
            'merchantTransactionId' => $txnId,
            'merchantUserId' => 'USER_' . $userId,
            'amount' => $amountInPaise,
            'redirectUrl' => $redirectUrl,
            'redirectMode' => 'POST',
            'callbackUrl' => $callbackUrl,
            'paymentInstrument' => [
                'type' => 'PAY_PAGE'
            ]
        ];

        if (!empty($mobileNumber)) {
            $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
            if (strlen($cleanMobile) >= 10) {
                $payload['mobileNumber'] = substr($cleanMobile, -10);
            }
        }

        $base64Payload = base64_encode(json_encode($payload));
        $checksumStr = $base64Payload . '/pg/v1/pay' . $this->saltKey;
        $xVerify = hash('sha256', $checksumStr) . '###' . $this->saltIndex;

        $ch = curl_init($this->apiBase . '/pg/v1/pay');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['request' => $base64Payload]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-VERIFY: ' . $xVerify
            ],
            CURLOPT_TIMEOUT => 25
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("PhonePe network connection failed: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400 || empty($data['success']) || $data['success'] !== true) {
            $msg = $data['message'] ?? "PhonePe payment initiation failed (HTTP {$httpCode}).";
            throw new Exception("PhonePe Error: " . $msg);
        }

        $redirectUrlOut = $data['data']['instrumentResponse']['redirectInfo']['url'] ?? '';
        if (empty($redirectUrlOut)) {
            throw new Exception("PhonePe did not return a valid checkout redirect URL.");
        }

        return [
            'redirect_url' => $redirectUrlOut,
            'merchant_transaction_id' => $txnId,
            'raw' => $data
        ];
    }

    /**
     * Query authoritative transaction status from PhonePe servers
     */
    public function checkStatus(string $merchantTransactionId): ?array {
        if (!$this->isConfigured() || empty($merchantTransactionId)) {
            return null;
        }

        $path = '/pg/v1/status/' . $this->merchantId . '/' . $merchantTransactionId;
        $checksumStr = $path . $this->saltKey;
        $xVerify = hash('sha256', $checksumStr) . '###' . $this->saltIndex;

        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-VERIFY: ' . $xVerify,
                'X-MERCHANT-ID: ' . $this->merchantId
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        return null;
    }

    /**
     * Verify payment server-side and credit user wallet
     */
    public function verifyAndProcess(string $internalPaymentId, ?string $merchantTransactionId = null): array {
        $payment = PaymentHelper::getPaymentByInternalId($internalPaymentId);
        if (!$payment) {
            $payment = PaymentHelper::getPaymentByGatewayOrderId('phonepe', $merchantTransactionId ?: $internalPaymentId);
        }
        if (!$payment) {
            return ['success' => false, 'error' => "Payment record {$internalPaymentId} not found."];
        }

        $actualInternalId = $payment['internal_payment_id'];
        if (PaymentHelper::isPaymentCredited($actualInternalId)) {
            return [
                'success' => true,
                'already_credited' => true,
                'message' => 'Payment already verified and wallet credited.'
            ];
        }

        $targetTxnId = $merchantTransactionId ?: ($payment['gateway_order_id'] ?: $actualInternalId);

        $statusData = $this->checkStatus($targetTxnId);
        if (!$statusData) {
            return [
                'success' => false,
                'pending' => true,
                'message' => 'Unable to fetch PhonePe transaction status. Payment is still pending.'
            ];
        }

        $code = $statusData['code'] ?? '';
        $success = $statusData['success'] ?? false;

        if ($success === true && $code === 'PAYMENT_SUCCESS') {
            $dataNode = $statusData['data'] ?? [];
            $phonePePaymentId = (string)($dataNode['transactionId'] ?? $targetTxnId);
            $amountInPaise = (float)($dataNode['amount'] ?? 0);
            $verifiedAmount = round($amountInPaise / 100.0, 2);
            $verifiedCurrency = 'INR';

            return PaymentHelper::creditWalletForVerifiedPayment(
                $actualInternalId,
                $phonePePaymentId,
                $verifiedAmount,
                $verifiedCurrency,
                $statusData,
                'phonepe_server_api'
            );
        }

        if (in_array($code, ['PAYMENT_ERROR', 'PAYMENT_DECLINED', 'TIMED_OUT', 'TRANSACTION_NOT_FOUND'])) {
            PaymentHelper::markPaymentFailed($actualInternalId, "PhonePe status: {$code}", 'FAILED', $statusData);
            return ['success' => false, 'error' => "PhonePe Payment failed: {$code}"];
        }

        return [
            'success' => false,
            'pending' => true,
            'message' => "PhonePe payment status: {$code}. Wallet will be credited once confirmed."
        ];
    }

    /**
     * Verify Webhook X-VERIFY checksum from PhonePe
     */
    public function verifyWebhook(string $responseBase64, string $xVerifyHeader): bool {
        if (empty($this->saltKey) || empty($responseBase64) || empty($xVerifyHeader)) {
            return false;
        }
        $parts = explode('###', $xVerifyHeader);
        $receivedHash = $parts[0] ?? '';
        $expectedHash = hash('sha256', $responseBase64 . $this->saltKey);
        return hash_equals($expectedHash, $receivedHash);
    }
}
