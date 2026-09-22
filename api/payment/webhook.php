<?php
/**
 * RoseSMM - Central Asynchronous Payment Webhook Listener
 * Authoritative server-to-server confirmation for Razorpay, Cashfree, PhonePe, PayU, Stripe, and PayPal.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/payments/PaymentHelper.php';
require_once __DIR__ . '/../../includes/payments/RazorpayService.php';
require_once __DIR__ . '/../../includes/payments/CashfreeService.php';
require_once __DIR__ . '/../../includes/payments/PhonePeService.php';
require_once __DIR__ . '/../../includes/payments/PayUService.php';

header('Content-Type: application/json');

$gatewayCode = strtolower(trim($_GET['gateway'] ?? ''));
$payload = file_get_contents('php://input');

$db = getDB();

if (empty($gatewayCode)) {
    // Try to auto-detect gateway from headers
    if (!empty($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'])) {
        $gatewayCode = 'razorpay';
    } elseif (!empty($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'])) {
        $gatewayCode = 'cashfree';
    } elseif (!empty($_SERVER['HTTP_X_VERIFY'])) {
        $gatewayCode = 'phonepe';
    } elseif (!empty($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
        $gatewayCode = 'stripe';
    } elseif (!empty($_POST['hash']) && !empty($_POST['mihpayid'])) {
        $gatewayCode = 'payu';
    }
}

if (empty($gatewayCode)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing gateway identifier.']);
    exit;
}

$gateway = PaymentHelper::getGateway($gatewayCode, false);
if (!$gateway) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => "Gateway {$gatewayCode} not found in system."]);
    exit;
}

try {
    // -------------------------------------------------------------
    // GATEWAY 1: RAZORPAY WEBHOOK
    // -------------------------------------------------------------
    if ($gatewayCode === 'razorpay') {
        $rzp = new RazorpayService($gateway);
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

        if (empty($signature)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing X-Razorpay-Signature header.']);
            exit;
        }

        $webhookSecret = trim($gateway['webhook_secret'] ?? '');
        if (!empty($webhookSecret)) {
            if (!$rzp->verifyWebhookSignature($payload, $signature, $webhookSecret)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid Razorpay webhook signature.']);
                exit;
            }
        }

        $event = json_decode($payload, true);
        $eventType = $event['event'] ?? '';

        if (in_array($eventType, ['payment.captured', 'order.paid'])) {
            $paymentEntity = $event['payload']['payment']['entity'] ?? [];
            $orderId = $paymentEntity['order_id'] ?? ($event['payload']['order']['entity']['id'] ?? '');
            $paymentId = $paymentEntity['id'] ?? '';
            $amount = (float)($paymentEntity['amount'] ?? 0) / 100.0;
            $currency = $paymentEntity['currency'] ?? 'INR';

            $payment = PaymentHelper::getPaymentByGatewayOrderId('razorpay', $orderId);
            if ($payment) {
                PaymentHelper::creditWalletForVerifiedPayment(
                    $payment['internal_payment_id'],
                    $paymentId,
                    $amount,
                    $currency,
                    $event,
                    'razorpay_webhook'
                );
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true, 'event' => $eventType]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 2: CASHFREE WEBHOOK
    // -------------------------------------------------------------
    if ($gatewayCode === 'cashfree') {
        $cf = new CashfreeService($gateway);
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';

        if (!empty($signature) && !empty($timestamp)) {
            if (!$cf->verifyWebhookSignature($payload, $signature, $timestamp)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid Cashfree webhook signature.']);
                exit;
            }
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';

        if ($type === 'PAYMENT_SUCCESS_WEBHOOK') {
            $data = $event['data'] ?? [];
            $orderId = $data['order']['order_id'] ?? '';
            $paymentId = (string)($data['payment']['cf_payment_id'] ?? '');
            $amount = (float)($data['payment']['payment_amount'] ?? ($data['order']['order_amount'] ?? 0));
            $currency = $data['payment']['payment_currency'] ?? 'INR';

            $payment = PaymentHelper::getPaymentByGatewayOrderId('cashfree', $orderId);
            if ($payment) {
                PaymentHelper::creditWalletForVerifiedPayment(
                    $payment['internal_payment_id'],
                    $paymentId,
                    $amount,
                    $currency,
                    $event,
                    'cashfree_webhook'
                );
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true, 'type' => $type]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 3: PHONEPE WEBHOOK
    // -------------------------------------------------------------
    if ($gatewayCode === 'phonepe') {
        $phonepe = new PhonePeService($gateway);
        $xVerify = $_SERVER['HTTP_X_VERIFY'] ?? '';
        $data = json_decode($payload, true) ?: $_POST;
        $responseBase64 = $data['response'] ?? '';

        if (!empty($xVerify) && !empty($responseBase64)) {
            if (!$phonepe->verifyWebhook($responseBase64, $xVerify)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid PhonePe webhook checksum.']);
                exit;
            }
        }

        $decoded = json_decode(base64_decode($responseBase64), true);
        if ($decoded && ($decoded['code'] ?? '') === 'PAYMENT_SUCCESS') {
            $paymentData = $decoded['data'] ?? [];
            $merchantTxnId = $paymentData['merchantTransactionId'] ?? '';
            $phonePeTxnId = (string)($paymentData['transactionId'] ?? $merchantTxnId);
            $amountInPaise = (float)($paymentData['amount'] ?? 0);
            $amount = round($amountInPaise / 100.0, 2);

            $payment = PaymentHelper::getPaymentByGatewayOrderId('phonepe', $merchantTxnId);
            if (!$payment) {
                $payment = PaymentHelper::getPaymentByInternalId($merchantTxnId);
            }

            if ($payment) {
                PaymentHelper::creditWalletForVerifiedPayment(
                    $payment['internal_payment_id'],
                    $phonePeTxnId,
                    $amount,
                    'INR',
                    $decoded,
                    'phonepe_webhook'
                );
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 4: PAYU WEBHOOK / S2S NOTIFICATION
    // -------------------------------------------------------------
    if ($gatewayCode === 'payu') {
        $payu = new PayUService($gateway);
        $postData = !empty($_POST) ? $_POST : json_decode($payload, true);

        if (!empty($postData) && $payu->verifyCallbackHash($postData)) {
            $status = strtolower($postData['status'] ?? '');
            if ($status === 'success') {
                $txnid = $postData['txnid'] ?? '';
                $mihpayid = (string)($postData['mihpayid'] ?? $txnid);
                $amount = (float)($postData['amount'] ?? 0);

                $payment = PaymentHelper::getPaymentByGatewayOrderId('payu', $txnid);
                if (!$payment) {
                    $payment = PaymentHelper::getPaymentByInternalId($txnid);
                }

                if ($payment) {
                    PaymentHelper::creditWalletForVerifiedPayment(
                        $payment['internal_payment_id'],
                        $mihpayid,
                        $amount,
                        'INR',
                        $postData,
                        'payu_webhook'
                    );
                }
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 5: STRIPE WEBHOOK
    // -------------------------------------------------------------
    if ($gatewayCode === 'stripe') {
        $event = json_decode($payload, true);
        if (!$event || empty($event['type'])) {
            throw new Exception("Invalid Stripe event payload.");
        }

        if ($event['type'] === 'checkout.session.completed') {
            $session = $event['data']['object'];
            $sessionId = $session['id'] ?? '';
            $paymentStatus = $session['payment_status'] ?? '';
            $recordId = (int)($session['client_reference_id'] ?? 0);

            if ($paymentStatus === 'paid' && !empty($sessionId)) {
                $tStmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id = ? OR id = ? LIMIT 1");
                $tStmt->execute([$sessionId, $recordId]);
                $txn = $tStmt->fetch();

                if ($txn && $txn['status'] !== 'completed') {
                    $userId = (int)$txn['user_id'];
                    $amount = (float)$txn['amount'];

                    $db->beginTransaction();
                    $bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
                    $bonusAmount = round(($bonusPercent / 100.0) * $amount, 2);
                    $totalCredit = $amount + $bonusAmount;

                    $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalCredit, $userId]);
                    $db->prepare("UPDATE transactions SET status = 'completed', transaction_id = ?, gateway_response = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$sessionId, json_encode($session), $txn['id']]);

                    if ($bonusAmount > 0) {
                        $db->prepare("INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id, created_at) VALUES (?, ?, 'bonus', 'Deposit Bonus (10%)', 'completed', ?, NOW())")
                           ->execute([$userId, $bonusAmount, 'BONUS-' . $sessionId]);
                    }

                    $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Payment Confirmed via Webhook', ?, 'wallet')")
                       ->execute([$userId, "Your deposit of \${$amount} was successfully verified via Stripe webhook."]);

                    $db->commit();
                }
            }
        }

        echo json_encode(['received' => true]);
        exit;
    }

    // -------------------------------------------------------------
    // GATEWAY 6: PAYPAL WEBHOOK
    // -------------------------------------------------------------
    if ($gatewayCode === 'paypal') {
        $event = json_decode($payload, true);
        if (!empty($event['event_type']) && $event['event_type'] === 'CHECKOUT.ORDER.APPROVED') {
            $resource = $event['resource'] ?? [];
            $orderId = $resource['id'] ?? '';

            if (!empty($orderId)) {
                $tStmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND status = 'pending' LIMIT 1");
                $tStmt->execute([$orderId]);
                $txn = $tStmt->fetch();

                if ($txn) {
                    $userId = (int)$txn['user_id'];
                    $amount = (float)$txn['amount'];

                    $db->beginTransaction();
                    $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);
                    $db->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$txn['id']]);
                    $db->commit();
                }
            }
        }
        echo json_encode(['received' => true]);
        exit;
    }

    // Default response
    echo json_encode(['received' => true, 'gateway' => $gatewayCode]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
