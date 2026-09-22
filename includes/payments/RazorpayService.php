<?php
/**
 * RoseSMM - Official Razorpay Integration Service
 * Real server-side API order creation, signature verification, webhook processing,
 * and resilient mobile/WebView fallback verification.
 */

require_once __DIR__ . '/PaymentHelper.php';

class RazorpayService {
    private array $config;
    private string $keyId;
    private string $keySecret;
    private string $webhookSecret;
    private string $apiBase = 'https://api.razorpay.com/v1';

    public function __construct(?array $config = null) {
        $this->config = $config ?: (PaymentHelper::getGateway('razorpay', false) ?: []);
        $this->keyId = trim($this->config['api_key'] ?? '');
        $this->keySecret = trim($this->config['secret_key'] ?? '');
        $this->webhookSecret = trim($this->config['webhook_secret'] ?? '');
    }

    public function isConfigured(): bool {
        return !empty($this->keyId) && !empty($this->keySecret);
    }

    /**
     * Create Real Order on Razorpay via API
     */
    public function createOrder(int $userId, float $amount, string $currency, string $internalPaymentId): array {
        if (!$this->isConfigured()) {
            throw new Exception("Razorpay API credentials (Key ID / Key Secret) are not configured.");
        }

        $amountInPaise = (int)round($amount * 100);

        $payload = [
            'amount' => $amountInPaise,
            'currency' => strtoupper($currency),
            'receipt' => substr($internalPaymentId, 0, 40),
            'notes' => [
                'internal_payment_id' => $internalPaymentId,
                'user_id' => (string)$userId,
                'source' => 'RoseSMM'
            ]
        ];

        $ch = curl_init($this->apiBase . '/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("Connection error with Razorpay: " . $curlErr);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400 || empty($data['id'])) {
            $msg = $data['error']['description'] ?? ($data['error']['message'] ?? "HTTP error {$httpCode} creating Razorpay order.");
            throw new Exception("Razorpay Order Creation Failed: " . $msg);
        }

        return $data;
    }

    /**
     * Verify payment signature from frontend checkout
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool {
        if (empty($this->keySecret) || empty($orderId) || empty($paymentId) || empty($signature)) {
            return false;
        }
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Query Razorpay API for official payment details
     */
    public function fetchPayment(string $paymentId): ?array {
        if (!$this->isConfigured() || empty($paymentId)) {
            return null;
        }

        $ch = curl_init($this->apiBase . '/payments/' . urlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
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
     * Query Razorpay API for all payments belonging to an order
     * Essential for mobile/WebView fallback when redirect fails!
     */
    public function fetchOrderPayments(string $orderId): ?array {
        if (!$this->isConfigured() || empty($orderId)) {
            return null;
        }

        $ch = curl_init($this->apiBase . '/orders/' . urlencode($orderId) . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['items'] ?? null;
        }
        return null;
    }

    /**
     * Verify and process payment server-side using Razorpay API
     * Can verify either by payment ID + signature OR by order ID query
     */
    public function verifyAndProcess(
        string $internalPaymentId, 
        ?string $paymentId = null, 
        ?string $signature = null, 
        ?string $orderId = null
    ): array {
        $payment = PaymentHelper::getPaymentByInternalId($internalPaymentId);
        if (!$payment) {
            return ['success' => false, 'error' => "Internal payment {$internalPaymentId} not found."];
        }

        if (PaymentHelper::isPaymentCredited($internalPaymentId)) {
            return [
                'success' => true,
                'already_credited' => true,
                'message' => 'Payment already verified and wallet credited.'
            ];
        }

        $targetOrderId = $orderId ?: ($payment['gateway_order_id'] ?? '');

        // 1. If payment ID & signature provided, verify signature
        if (!empty($paymentId) && !empty($signature) && !empty($targetOrderId)) {
            if (!$this->verifyPaymentSignature($targetOrderId, $paymentId, $signature)) {
                PaymentHelper::markPaymentFailed($internalPaymentId, 'Invalid signature received from client.', 'FAILED');
                return ['success' => false, 'error' => 'Razorpay payment signature mismatch.'];
            }
        }

        // 2. Fetch authoritative payment state from Razorpay API
        $verifiedPaymentData = null;

        if (!empty($paymentId)) {
            $paymentData = $this->fetchPayment($paymentId);
            if ($paymentData && in_array($paymentData['status'] ?? '', ['captured', 'authorized'])) {
                $verifiedPaymentData = $paymentData;
            }
        }

        // 3. Fallback for mobile/WebView: Query order payments if paymentId wasn't returned
        if (!$verifiedPaymentData && !empty($targetOrderId)) {
            $orderPayments = $this->fetchOrderPayments($targetOrderId);
            if (!empty($orderPayments)) {
                foreach ($orderPayments as $op) {
                    if (in_array($op['status'] ?? '', ['captured', 'authorized'])) {
                        $verifiedPaymentData = $op;
                        $paymentId = $op['id'];
                        break;
                    }
                }
            }
        }

        if (!$verifiedPaymentData) {
            return [
                'success' => false,
                'pending' => true,
                'message' => 'Payment has not been confirmed by Razorpay yet. If completed, it will credit automatically once the gateway webhook or status check settles.'
            ];
        }

        // 4. Verify exact amount and currency
        $paidAmount = (float)($verifiedPaymentData['amount'] / 100.0);
        $paidCurrency = strtoupper($verifiedPaymentData['currency'] ?? 'INR');

        return PaymentHelper::creditWalletForVerifiedPayment(
            $internalPaymentId,
            $verifiedPaymentData['id'],
            $paidAmount,
            $paidCurrency,
            $verifiedPaymentData,
            'razorpay_server_api'
        );
    }

    /**
     * Webhook Signature Verification
     */
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool {
        if (empty($this->webhookSecret) || empty($signature)) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawPayload, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }
}
