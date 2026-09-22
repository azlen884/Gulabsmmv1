<?php
/**
 * RoseSMM - Official PayU India Integration Service
 * Real server-side API, SHA512 hash calculation, reverse callback verification,
 * and server-to-server verify_payment API queries.
 */

require_once __DIR__ . '/PaymentHelper.php';

class PayUService {
    private array $config;
    private string $merchantKey;
    private string $merchantSalt;
    private string $mode;
    private string $paymentUrl;
    private string $verifyUrl;

    public function __construct(?array $config = null) {
        $this->config = $config ?: (PaymentHelper::getGateway('payu', false) ?: []);
        $this->merchantKey = trim($this->config['api_key'] ?? '');
        $this->merchantSalt = trim($this->config['secret_key'] ?? '');
        $this->mode = strtolower(trim($this->config['mode'] ?? 'test'));

        if ($this->mode === 'live') {
            $this->paymentUrl = 'https://secure.payu.in/_payment';
            $this->verifyUrl = 'https://info.payu.in/merchant/postservice?form=2';
        } else {
            $this->paymentUrl = 'https://test.payu.in/_payment';
            $this->verifyUrl = 'https://test.payu.in/merchant/postservice?form=2';
        }
    }

    public function isConfigured(): bool {
        return !empty($this->merchantKey) && !empty($this->merchantSalt);
    }

    public function getMode(): string {
        return $this->mode;
    }

    public function getPaymentUrl(): string {
        return $this->paymentUrl;
    }

    /**
     * Generate SHA512 hash for PayU hosted checkout
     * Formula: sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
     */
    public function generatePaymentHash(
        string $txnid,
        float $amount,
        string $productinfo,
        string $firstname,
        string $email,
        string $udf1 = '',
        string $udf2 = '',
        string $udf3 = '',
        string $udf4 = '',
        string $udf5 = ''
    ): string {
        $formattedAmount = number_format($amount, 2, '.', '');
        $hashString = $this->merchantKey . '|' . $txnid . '|' . $formattedAmount . '|' . 
            $productinfo . '|' . $firstname . '|' . $email . '|' . 
            $udf1 . '|' . $udf2 . '|' . $udf3 . '|' . $udf4 . '|' . $udf5 . '||||||' . 
            $this->merchantSalt;

        return hash('sha512', $hashString);
    }

    /**
     * Prepare complete parameters for PayU form redirection
     */
    public function prepareCheckoutData(
        int $userId,
        float $amount,
        string $currency,
        string $internalPaymentId,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $surl,
        string $furl
    ): array {
        if (!$this->isConfigured()) {
            throw new Exception("PayU credentials (Merchant Key / Merchant Salt) are not configured.");
        }

        $txnid = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $internalPaymentId), 0, 35);
        $productinfo = 'RoseSMM Wallet Deposit #' . $internalPaymentId;
        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (strlen($cleanPhone) < 10) $cleanPhone = '9876543210';
        $cleanName = preg_replace('/[^a-zA-Z0-9 ]/', '', $customerName) ?: 'Customer';

        $hash = $this->generatePaymentHash(
            $txnid,
            $amount,
            $productinfo,
            $cleanName,
            $customerEmail,
            (string)$userId, // udf1 = user_id
            $internalPaymentId // udf2 = internalPaymentId
        );

        return [
            'action_url' => $this->paymentUrl,
            'fields' => [
                'key' => $this->merchantKey,
                'txnid' => $txnid,
                'amount' => number_format($amount, 2, '.', ''),
                'productinfo' => $productinfo,
                'firstname' => $cleanName,
                'email' => $customerEmail,
                'phone' => $cleanPhone,
                'surl' => $surl,
                'furl' => $furl,
                'hash' => $hash,
                'service_provider' => 'payu_paisa',
                'udf1' => (string)$userId,
                'udf2' => $internalPaymentId
            ]
        ];
    }

    /**
     * Validate reverse hash from PayU return callback or webhook
     * Formula: sha512(SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
     * If additionalCharges: sha512(additionalCharges|SALT|status...)
     */
    public function verifyCallbackHash(array $postData): bool {
        if (!$this->isConfigured()) {
            return false;
        }

        $receivedHash = $postData['hash'] ?? '';
        $status = $postData['status'] ?? '';
        $txnid = $postData['txnid'] ?? '';
        $amount = $postData['amount'] ?? '';
        $productinfo = $postData['productinfo'] ?? '';
        $firstname = $postData['firstname'] ?? '';
        $email = $postData['email'] ?? '';
        $udf1 = $postData['udf1'] ?? '';
        $udf2 = $postData['udf2'] ?? '';
        $udf3 = $postData['udf3'] ?? '';
        $udf4 = $postData['udf4'] ?? '';
        $udf5 = $postData['udf5'] ?? '';
        $additionalCharges = $postData['additionalCharges'] ?? '';

        if (!empty($additionalCharges)) {
            $hashSequence = $additionalCharges . '|' . $this->merchantSalt . '|' . $status . '||||||' . 
                $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . 
                $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . 
                $txnid . '|' . $this->merchantKey;
        } else {
            $hashSequence = $this->merchantSalt . '|' . $status . '||||||' . 
                $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . 
                $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . 
                $txnid . '|' . $this->merchantKey;
        }

        $calculatedHash = strtolower(hash('sha512', $hashSequence));
        return hash_equals($calculatedHash, strtolower($receivedHash));
    }

    /**
     * Query authoritative transaction status from PayU verify_payment API
     */
    public function verifyPaymentApi(string $txnid): ?array {
        if (!$this->isConfigured() || empty($txnid)) {
            return null;
        }

        // Hash for verify_payment: sha512(key|command|var1|salt)
        $hashString = $this->merchantKey . '|verify_payment|' . $txnid . '|' . $this->merchantSalt;
        $hash = hash('sha512', $hashString);

        $postFields = http_build_query([
            'key' => $this->merchantKey,
            'command' => 'verify_payment',
            'var1' => $txnid,
            'hash' => $hash
        ]);

        $ch = curl_init($this->verifyUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if (isset($data['transaction_details'][$txnid])) {
                return $data['transaction_details'][$txnid];
            }
            return $data;
        }
        return null;
    }

    /**
     * Verify payment server-side and credit user wallet
     */
    public function verifyAndProcess(string $internalPaymentId, ?array $callbackPostData = null): array {
        $payment = PaymentHelper::getPaymentByInternalId($internalPaymentId);
        if (!$payment && !empty($callbackPostData['txnid'])) {
            $payment = PaymentHelper::getPaymentByGatewayOrderId('payu', $callbackPostData['txnid']);
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

        $txnid = $payment['gateway_order_id'] ?: $actualInternalId;

        // 1. If callback POST data was provided, verify hash
        if (!empty($callbackPostData)) {
            if (!$this->verifyCallbackHash($callbackPostData)) {
                PaymentHelper::markPaymentFailed($actualInternalId, 'PayU return hash verification failed.', 'FAILED', $callbackPostData);
                return ['success' => false, 'error' => 'PayU signature / reverse hash mismatch.'];
            }
        }

        // 2. Query official server-to-server verify_payment API for ultimate authority
        $apiDetails = $this->verifyPaymentApi($txnid);
        $finalStatus = '';
        $mihpayid = '';
        $verifiedAmount = 0.0;

        if ($apiDetails && isset($apiDetails['status'])) {
            $finalStatus = strtolower($apiDetails['status']);
            $mihpayid = (string)($apiDetails['mihpayid'] ?? '');
            $verifiedAmount = (float)($apiDetails['amt'] ?? $payment['amount']);
        } elseif (!empty($callbackPostData)) {
            $finalStatus = strtolower($callbackPostData['status'] ?? '');
            $mihpayid = (string)($callbackPostData['mihpayid'] ?? '');
            $verifiedAmount = (float)($callbackPostData['amount'] ?? $payment['amount']);
        }

        if ($finalStatus === 'success') {
            return PaymentHelper::creditWalletForVerifiedPayment(
                $actualInternalId,
                $mihpayid ?: $txnid,
                $verifiedAmount,
                'INR',
                [
                    'api' => $apiDetails,
                    'callback' => $callbackPostData
                ],
                'payu_server_api'
            );
        }

        if (in_array($finalStatus, ['failure', 'failed', 'cancelled', 'bounced'])) {
            PaymentHelper::markPaymentFailed($actualInternalId, "PayU reported payment {$finalStatus}", 'FAILED');
            return ['success' => false, 'error' => "PayU payment {$finalStatus}."];
        }

        return [
            'success' => false,
            'pending' => true,
            'message' => 'Payment status is currently pending or awaiting confirmation from PayU.'
        ];
    }
}
