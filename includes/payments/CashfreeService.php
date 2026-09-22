<?php
/**
 * RoseSMM - Official Cashfree Payments Integration Service
 * Real server-side API (2023-08-01 / PG API), secure order creation,
 * server-to-server payment verification, and webhook signature validation.
 */

require_once __DIR__ . '/PaymentHelper.php';

class CashfreeService {
    private array $config;
    private string $appId;
    private string $secretKey;
    private string $mode;
    private string $apiBase;

    public function __construct(?array $config = null) {
        $this->config = $config ?: (PaymentHelper::getGateway('cashfree', false) ?: []);
        $this->appId = trim($this->config['api_key'] ?? '');
        $this->secretKey = trim($this->config['secret_key'] ?? '');
        $this->mode = strtolower(trim($this->config['mode'] ?? 'test'));
        $this->apiBase = ($this->mode === 'live') 
            ? 'https://api.cashfree.com/pg' 
            : 'https://sandbox.cashfree.com/pg';
    }

    public function isConfigured(): bool {
        return !empty($this->appId) && !empty($this->secretKey);
    }

    public function getMode(): string {
        return $this->mode;
    }

    /**
     * Create Real Order on Cashfree PG
     */
    public function createOrder(
        int $userId, 
        float $amount, 
        string $currency, 
        string $internalPaymentId,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $returnUrl,
        string $notifyUrl
    ): array {
        if (!$this->isConfigured()) {
            throw new Exception("Cashfree API credentials (App ID / Secret Key) are not configured.");
        }

        // Clean internal order ID: Cashfree requires alphanumeric, underscores, hyphens (max 45 chars)
        $cfOrderId = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $internalPaymentId), 0, 45);

        // Sanitize phone (default 10-digit number if user hasn't provided phone)
        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (strlen($cleanPhone) < 10) {
            $cleanPhone = '9876543210';
        } else {
            $cleanPhone = substr($cleanPhone, -10);
        }

        $payload = [
            'order_id' => $cfOrderId,
            'order_amount' => round($amount, 2),
            'order_currency' => strtoupper($currency),
            'customer_details' => [
                'customer_id' => 'USER_' . $userId,
                'customer_name' => substr($customerName ?: 'Customer', 0, 50),
                'customer_email' => filter_var($customerEmail, FILTER_VALIDATE_EMAIL) ? $customerEmail : 'support@rosesmm.com',
                'customer_phone' => $cleanPhone
            ],
            'order_meta' => [
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl
            ],
            'order_note' => 'RoseSMM Wallet Deposit #' . $internalPaymentId
        ];

        $ch = curl_init($this->apiBase . '/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-client-id: ' . $this->appId,
                'x-client-secret: ' . $this->secretKey,
                'x-api-version: 2023-08-01'
            ],
            CURLOPT_TIMEOUT => 25
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("Cashfree network connection failed: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400 || empty($data['payment_session_id'])) {
            $msg = $data['message'] ?? "HTTP error {$httpCode} creating Cashfree order.";
            throw new Exception("Cashfree Order Failed: " . $msg);
        }

        return $data;
    }

    /**
     * Query Cashfree API for order details
     */
    public function fetchOrder(string $orderId): ?array {
        if (!$this->isConfigured() || empty($orderId)) {
            return null;
        }

        $ch = curl_init($this->apiBase . '/orders/' . urlencode($orderId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-client-id: ' . $this->appId,
                'x-client-secret: ' . $this->secretKey,
                'x-api-version: 2023-08-01'
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
     * Query Cashfree API for order payments
     */
    public function fetchOrderPayments(string $orderId): ?array {
        if (!$this->isConfigured() || empty($orderId)) {
            return null;
        }

        $ch = curl_init($this->apiBase . '/orders/' . urlencode($orderId) . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-client-id: ' . $this->appId,
                'x-client-secret: ' . $this->secretKey,
                'x-api-version: 2023-08-01'
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
     * Verify payment status directly with Cashfree servers
     */
    public function verifyAndProcess(string $internalPaymentId, ?string $orderId = null): array {
        $payment = PaymentHelper::getPaymentByInternalId($internalPaymentId);
        if (!$payment) {
            // Also check by gateway_order_id
            $payment = PaymentHelper::getPaymentByGatewayOrderId('cashfree', $orderId ?: $internalPaymentId);
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

        $targetOrderId = $orderId ?: ($payment['gateway_order_id'] ?: $actualInternalId);

        // Fetch official order and payments from Cashfree API
        $orderData = $this->fetchOrder($targetOrderId);
        $paymentsList = $this->fetchOrderPayments($targetOrderId);

        $successfulPayment = null;
        if (!empty($paymentsList)) {
            foreach ($paymentsList as $p) {
                if (strtoupper($p['payment_status'] ?? '') === 'SUCCESS') {
                    $successfulPayment = $p;
                    break;
                }
            }
        }

        if (!$successfulPayment && ($orderData['order_status'] ?? '') !== 'PAID') {
            $currentStatus = $orderData['order_status'] ?? 'PENDING';
            if (in_array($currentStatus, ['EXPIRED', 'TERMINATED', 'CANCELLED'])) {
                PaymentHelper::markPaymentFailed($actualInternalId, "Cashfree order status: {$currentStatus}", 'CANCELLED');
            }
            return [
                'success' => false,
                'pending' => true,
                'message' => 'Payment is still being processed by Cashfree or has not yet been completed.'
            ];
        }

        $cfPaymentId = (string)($successfulPayment['cf_payment_id'] ?? ($orderData['order_id'] ?? $targetOrderId));
        $verifiedAmount = (float)($successfulPayment['payment_amount'] ?? ($orderData['order_amount'] ?? $payment['amount']));
        $verifiedCurrency = strtoupper($successfulPayment['payment_currency'] ?? ($orderData['order_currency'] ?? $payment['currency']));

        return PaymentHelper::creditWalletForVerifiedPayment(
            $actualInternalId,
            $cfPaymentId,
            $verifiedAmount,
            $verifiedCurrency,
            [
                'order' => $orderData,
                'payment' => $successfulPayment
            ],
            'cashfree_server_api'
        );
    }

    /**
     * Webhook Signature Verification for Cashfree
     */
    public function verifyWebhookSignature(string $rawBody, string $timestamp, string $signature): bool {
        if (empty($this->secretKey) || empty($signature) || empty($timestamp)) {
            return false;
        }
        $signedData = $timestamp . $rawBody;
        $expected = base64_encode(hash_hmac('sha256', $signedData, $this->secretKey, true));
        return hash_equals($expected, $signature);
    }
}
