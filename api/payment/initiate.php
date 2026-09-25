<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/payments/PaymentHelper.php';
require_once __DIR__ . '/../../includes/payments/RazorpayService.php';
require_once __DIR__ . '/../../includes/payments/CashfreeService.php';
require_once __DIR__ . '/../../includes/payments/PhonePeService.php';
require_once __DIR__ . '/../../includes/payments/PayUService.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'You must be signed in to add funds.']);
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];

// Get user info
$uStmt = $db->prepare("SELECT id, username, email, balance FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User account not found.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$gatewayCode = trim($input['gateway_code'] ?? '');
$amount = (float)($input['amount'] ?? 0);
$manualRef = trim($input['manual_reference'] ?? '');

if (empty($gatewayCode)) {
    echo json_encode(['success' => false, 'error' => 'Please select a payment method.']);
    exit;
}

// Fetch active gateway from MySQL
$stmt = $db->prepare("SELECT * FROM payment_gateways WHERE code = ? AND status = 'active' LIMIT 1");
$stmt->execute([$gatewayCode]);
$gateway = $stmt->fetch();

if (!$gateway) {
    echo json_encode([
        'success' => false, 
        'error' => 'The selected payment method is currently disabled or unavailable. Please choose another method.'
    ]);
    exit;
}

// Validate amount
$minAmount = (float)$gateway['min_amount'];
$maxAmount = (float)$gateway['max_amount'];

if ($amount < $minAmount) {
    echo json_encode([
        'success' => false, 
        'error' => "Minimum deposit amount for {$gateway['name']} is \${$minAmount}."
    ]);
    exit;
}

if ($amount > $maxAmount) {
    echo json_encode([
        'success' => false, 
        'error' => "Maximum deposit amount for {$gateway['name']} is \${$maxAmount}."
    ]);
    exit;
}

// Calculate fee if any
$feePercent = (float)$gateway['fee_percent'];
$feeAmount = round(($feePercent / 100.0) * $amount, 2);
$totalToCharge = $amount + $feeAmount;

// Protocol & host for redirect URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:3000';
$baseUrl = $protocol . $host;

// Generate temporary transaction reference
$tempTxnRef = 'TXN-' . strtoupper($gateway['code']) . '-' . strtoupper(bin2hex(random_bytes(4)));

try {
    // -------------------------------------------------------------
    // GATEWAY 1: STRIPE
    // -------------------------------------------------------------
    if ($gateway['code'] === 'stripe') {
        $secretKey = trim($gateway['secret_key'] ?? '');
        if (empty($secretKey) || $secretKey === 'sk_test_sample') {
            echo json_encode([
                'success' => false, 
                'error' => 'Stripe is currently in setup mode. The administrator has not configured a valid live or test Secret Key yet.'
            ]);
            exit;
        }

        // Insert pending transaction record
        $ins = $db->prepare("
            INSERT INTO transactions (user_id, amount, type, payment_method, gateway_code, currency, status, transaction_id, created_at)
            VALUES (?, ?, 'deposit', ?, 'stripe', ?, 'pending', ?, NOW())
        ");
        $ins->execute([$userId, $amount, $gateway['name'], $gateway['currency'], $tempTxnRef]);
        $recordId = $db->lastInsertId();

        // Call Stripe Checkout Sessions API
        $stripeUrl = 'https://api.stripe.com/v1/checkout/sessions';
        $postData = [
            'mode' => 'payment',
            'success_url' => $baseUrl . '/payment/verify?gateway=stripe&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/payment/cancel?gateway=stripe&txn_id=' . $recordId,
            'client_reference_id' => (string)$recordId,
            'customer_email' => $user['email'],
            'line_items[0][price_data][currency]' => strtolower($gateway['currency']),
            'line_items[0][price_data][product_data][name]' => 'Deposit to ' . get_site_name() . ' Wallet (' . $user['username'] . ')',
            'line_items[0][price_data][unit_amount]' => (int)round($totalToCharge * 100),
            'line_items[0][quantity]' => 1,
            'metadata[user_id]' => (string)$userId,
            'metadata[txn_record_id]' => (string)$recordId,
        ];

        $ch = curl_init($stripeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Unable to connect to Stripe server: " . $curlError);
        }

        $sessionData = json_decode($response, true);

        if ($httpCode !== 200 || empty($sessionData['id']) || empty($sessionData['url'])) {
            $errMessage = $sessionData['error']['message'] ?? 'Failed to initialize Stripe Checkout session.';
            // Record failure in transaction
            $db->prepare("UPDATE transactions SET status = 'failed', gateway_response = ? WHERE id = ?")
               ->execute([json_encode($sessionData), $recordId]);
            
            echo json_encode([
                'success' => false, 
                'error' => "Stripe Gateway Error: " . $errMessage
            ]);
            exit;
        }

        // Update transaction with real Stripe session ID
        $db->prepare("UPDATE transactions SET transaction_id = ? WHERE id = ?")
           ->execute([$sessionData['id'], $recordId]);

        echo json_encode([
            'success' => true,
            'redirect_url' => $sessionData['url']
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 2: PAYPAL
    // -------------------------------------------------------------
    if ($gateway['code'] === 'paypal') {
        $clientId = trim($gateway['api_key'] ?? '');
        $clientSecret = trim($gateway['secret_key'] ?? '');

        if (empty($clientId) || empty($clientSecret) || $clientId === 'client_id_sample') {
            echo json_encode([
                'success' => false, 
                'error' => 'PayPal is currently in setup mode. Administrator credentials have not been configured yet.'
            ]);
            exit;
        }

        $isLive = $gateway['mode'] === 'live';
        $paypalBase = $isLive ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        // 1. Get OAuth Access Token
        $ch = curl_init($paypalBase . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $authRes = curl_exec($ch);
        $authHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $authData = json_decode($authRes, true);
        $accessToken = $authData['access_token'] ?? null;

        if ($authHttp !== 200 || !$accessToken) {
            $paypalErr = $authData['error_description'] ?? 'Authentication failed with PayPal API.';
            echo json_encode(['success' => false, 'error' => "PayPal API Error: " . $paypalErr]);
            exit;
        }

        // 2. Insert pending record
        $ins = $db->prepare("
            INSERT INTO transactions (user_id, amount, type, payment_method, gateway_code, currency, status, transaction_id, created_at)
            VALUES (?, ?, 'deposit', ?, 'paypal', ?, 'pending', ?, NOW())
        ");
        $ins->execute([$userId, $amount, $gateway['name'], $gateway['currency'], $tempTxnRef]);
        $recordId = $db->lastInsertId();

        // 3. Create PayPal Order
        $orderPayload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string)$recordId,
                'description' => 'Deposit to ' . get_site_name() . ' Wallet (' . $user['username'] . ')',
                'amount' => [
                    'currency_code' => $gateway['currency'],
                    'value' => number_format($totalToCharge, 2, '.', '')
                ]
            ]],
            'application_context' => [
                'brand_name' => get_site_name(),
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => $baseUrl . '/payment/verify?gateway=paypal&txn_id=' . $recordId,
                'cancel_url' => $baseUrl . '/payment/cancel?gateway=paypal&txn_id=' . $recordId
            ]
        ];

        $ch = curl_init($paypalBase . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $orderRes = curl_exec($ch);
        $orderHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $orderData = json_decode($orderRes, true);

        if ($orderHttp !== 201 || empty($orderData['id'])) {
            $db->prepare("UPDATE transactions SET status = 'failed', gateway_response = ? WHERE id = ?")
               ->execute([$orderRes, $recordId]);
            $errDetail = $orderData['details'][0]['description'] ?? $orderData['message'] ?? 'Could not create PayPal order.';
            echo json_encode(['success' => false, 'error' => "PayPal Order Error: " . $errDetail]);
            exit;
        }

        // Find approve link
        $approveLink = null;
        if (!empty($orderData['links'])) {
            foreach ($orderData['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveLink = $link['href'];
                    break;
                }
            }
        }

        if (!$approveLink) {
            echo json_encode(['success' => false, 'error' => 'PayPal approval link not found.']);
            exit;
        }

        // Update transaction ID with PayPal Order ID
        $db->prepare("UPDATE transactions SET transaction_id = ? WHERE id = ?")->execute([$orderData['id'], $recordId]);

        echo json_encode([
            'success' => true,
            'redirect_url' => $approveLink
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 3: BANK WIRE / MANUAL TRANSFER
    // -------------------------------------------------------------
    if ($gateway['code'] === 'bank_transfer') {
        if (empty($manualRef)) {
            echo json_encode([
                'success' => false, 
                'error' => 'Please provide your Payment Reference ID, Transaction UTR, or Sender Name.'
            ]);
            exit;
        }

        $ins = $db->prepare("
            INSERT INTO transactions (user_id, amount, type, payment_method, gateway_code, currency, status, transaction_id, gateway_response, created_at)
            VALUES (?, ?, 'deposit', ?, 'bank_transfer', ?, 'pending', ?, ?, NOW())
        ");
        $notes = "Customer Reference: " . $manualRef;
        $ins->execute([$userId, $amount, $gateway['name'], $gateway['currency'], $tempTxnRef, $notes]);
        $recordId = $db->lastInsertId();

        // Send user notification
        $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, 'Manual Deposit Submitted', ?, 'wallet')
        ")->execute([
            $userId,
            "Your deposit request for \${$amount} via Bank Transfer (Ref: {$manualRef}) has been submitted and is pending Admin verification."
        ]);

        echo json_encode([
            'success' => true,
            'manual' => true,
            'message' => "Your payment of \${$amount} (Reference: {$manualRef}) was submitted successfully! It will be credited once verified by our administration.",
            'redirect_url' => '/wallet'
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 4: RAZORPAY
    // -------------------------------------------------------------
    if ($gateway['code'] === 'razorpay') {
        $rzp = new RazorpayService($gateway);
        if (!$rzp->isConfigured()) {
            echo json_encode([
                'success' => false,
                'error' => 'Razorpay merchant credentials (Key ID and Key Secret) are not configured by the administrator.'
            ]);
            exit;
        }

        $internalId = PaymentHelper::generateInternalId('razorpay');
        $rzpOrder = $rzp->createOrder($userId, $totalToCharge, $gateway['currency'], $internalId);
        $orderId = $rzpOrder['id'];

        // Save in payments table
        $paymentRecord = PaymentHelper::createPayment(
            $userId,
            'razorpay',
            $amount,
            $gateway['currency'],
            $internalId,
            $orderId,
            $rzpOrder
        );

        echo json_encode([
            'success' => true,
            'gateway' => 'razorpay',
            'internal_payment_id' => $internalId,
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $gateway['currency'],
            'redirect_url' => '/payment/checkout?internal_id=' . urlencode($internalId)
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 5: CASHFREE PAYMENTS
    // -------------------------------------------------------------
    if ($gateway['code'] === 'cashfree') {
        $cf = new CashfreeService($gateway);
        if (!$cf->isConfigured()) {
            echo json_encode([
                'success' => false,
                'error' => 'Cashfree merchant credentials (App ID and Secret Key) are not configured by the administrator.'
            ]);
            exit;
        }

        $internalId = PaymentHelper::generateInternalId('cashfree');
        $returnUrl = $baseUrl . '/payment/verify?gateway=cashfree&internal_id=' . urlencode($internalId);
        
        $cfOrder = $cf->createOrder(
            $userId,
            $totalToCharge,
            $gateway['currency'],
            $internalId,
            $user['username'],
            $user['email'],
            '9876543210',
            $returnUrl
        );

        $orderId = $cfOrder['order_id'];
        $sessionId = $cfOrder['payment_session_id'] ?? '';

        PaymentHelper::createPayment(
            $userId,
            'cashfree',
            $amount,
            $gateway['currency'],
            $internalId,
            $orderId,
            [
                'payment_session_id' => $sessionId,
                'cf_order' => $cfOrder
            ]
        );

        echo json_encode([
            'success' => true,
            'gateway' => 'cashfree',
            'internal_payment_id' => $internalId,
            'payment_session_id' => $sessionId,
            'order_id' => $orderId,
            'redirect_url' => '/payment/checkout?internal_id=' . urlencode($internalId)
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 6: PHONEPE PAYMENT GATEWAY
    // -------------------------------------------------------------
    if ($gateway['code'] === 'phonepe') {
        $phonepe = new PhonePeService($gateway);
        if (!$phonepe->isConfigured()) {
            echo json_encode([
                'success' => false,
                'error' => 'PhonePe merchant credentials (Merchant ID and Salt Key) are not configured by the administrator.'
            ]);
            exit;
        }

        $internalId = PaymentHelper::generateInternalId('phonepe');
        $redirectUrl = $baseUrl . '/payment/verify?gateway=phonepe&internal_id=' . urlencode($internalId);
        $callbackUrl = $baseUrl . '/payment/webhook?gateway=phonepe';

        $initData = $phonepe->initiatePayment(
            $userId,
            $totalToCharge,
            $gateway['currency'],
            $internalId,
            $redirectUrl,
            $callbackUrl
        );

        $merchantTxnId = $initData['merchant_transaction_id'];
        $targetRedirect = $initData['redirect_url'];

        PaymentHelper::createPayment(
            $userId,
            'phonepe',
            $amount,
            $gateway['currency'],
            $internalId,
            $merchantTxnId,
            [
                'redirect_url' => $targetRedirect,
                'phonepe_init' => $initData
            ]
        );

        echo json_encode([
            'success' => true,
            'gateway' => 'phonepe',
            'internal_payment_id' => $internalId,
            'redirect_url' => $targetRedirect
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 7: PAYU
    // -------------------------------------------------------------
    if ($gateway['code'] === 'payu') {
        $payu = new PayUService($gateway);
        if (!$payu->isConfigured()) {
            echo json_encode([
                'success' => false,
                'error' => 'PayU merchant credentials (Merchant Key and Merchant Salt) are not configured by the administrator.'
            ]);
            exit;
        }

        $internalId = PaymentHelper::generateInternalId('payu');

        PaymentHelper::createPayment(
            $userId,
            'payu',
            $amount,
            $gateway['currency'],
            $internalId,
            $internalId,
            ['checkout' => 'form_post']
        );

        echo json_encode([
            'success' => true,
            'gateway' => 'payu',
            'internal_payment_id' => $internalId,
            'redirect_url' => '/payment/checkout?internal_id=' . urlencode($internalId)
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // FALLBACK: OTHER CONFIGURED GATEWAY
    // -------------------------------------------------------------
    $apiKey = trim($gateway['api_key'] ?? '');
    $secretKey = trim($gateway['secret_key'] ?? '');

    if (empty($apiKey) || empty($secretKey)) {
        echo json_encode([
            'success' => false,
            'error' => "Gateway {$gateway['name']} is not fully configured with merchant credentials. Please choose an active method or contact support."
        ]);
        exit;
    }

    $ins = $db->prepare("
        INSERT INTO transactions (user_id, amount, type, payment_method, gateway_code, currency, status, transaction_id, created_at)
        VALUES (?, ?, 'deposit', ?, ?, ?, 'pending', ?, NOW())
    ");
    $ins->execute([$userId, $amount, $gateway['name'], $gateway['code'], $gateway['currency'], $tempTxnRef]);
    $recordId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => "Deposit initiated with {$gateway['name']}.",
        'redirect_url' => $baseUrl . '/payment/verify?gateway=' . urlencode($gateway['code']) . '&txn_id=' . $recordId
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Payment initialization failed: ' . $e->getMessage()
    ]);
    exit;
}
