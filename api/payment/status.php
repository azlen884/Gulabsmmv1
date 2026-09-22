<?php
/**
 * RoseSMM - Real-Time Payment Status Polling & Authoritative Query API
 * Enables safe background check for payment completion (e.g. on checkout & verify screens).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/payments/PaymentHelper.php';
require_once __DIR__ . '/../../includes/payments/RazorpayService.php';
require_once __DIR__ . '/../../includes/payments/CashfreeService.php';
require_once __DIR__ . '/../../includes/payments/PhonePeService.php';
require_once __DIR__ . '/../../includes/payments/PayUService.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$internalId = trim($_GET['internal_id'] ?? $_POST['internal_id'] ?? '');
$orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');

if (empty($internalId) && empty($orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payment reference is required.']);
    exit;
}

$payment = null;
if (!empty($internalId)) {
    $payment = PaymentHelper::getPaymentByInternalId($internalId);
}
if (!$payment && !empty($orderId)) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM payments WHERE gateway_order_id = ? OR internal_payment_id = ? LIMIT 1");
    $stmt->execute([$orderId, $orderId]);
    $payment = $stmt->fetch();
}

if (!$payment || (int)$payment['user_id'] !== $userId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Payment record not found.']);
    exit;
}

$gateway = $payment['gateway'] ?? ($payment['gateway_code'] ?? '');
$status = strtoupper($payment['status']);
$credited = ((int)($payment['is_credited'] ?? 0) === 1 || $status === 'SUCCESS');

// If still pending, query gateway authoritative API if configured
if (!$credited && in_array($status, ['CREATED', 'PENDING'])) {
    try {
        if ($gateway === 'razorpay') {
            $rzp = new RazorpayService();
            if ($rzp->isConfigured()) {
                $res = $rzp->verifyAndProcess($payment['internal_payment_id']);
                if (!empty($res['success'])) {
                    $credited = true;
                    $status = 'COMPLETED';
                }
            }
        } elseif ($gateway === 'cashfree') {
            $cf = new CashfreeService();
            if ($cf->isConfigured()) {
                $res = $cf->verifyAndProcess($payment['internal_payment_id']);
                if (!empty($res['success'])) {
                    $credited = true;
                    $status = 'COMPLETED';
                }
            }
        } elseif ($gateway === 'phonepe') {
            $phonepe = new PhonePeService();
            if ($phonepe->isConfigured()) {
                $res = $phonepe->verifyAndProcess($payment['internal_payment_id']);
                if (!empty($res['success'])) {
                    $credited = true;
                    $status = 'COMPLETED';
                }
            }
        } elseif ($gateway === 'payu') {
            $payu = new PayUService();
            if ($payu->isConfigured()) {
                $res = $payu->verifyAndProcess($payment['internal_payment_id']);
                if (!empty($res['success'])) {
                    $credited = true;
                    $status = 'COMPLETED';
                }
            }
        }
    } catch (Throwable $e) {
        // Log silently, status remains pending
    }
}

// Re-fetch user balance
$db = getDB();
$balance = (float)$db->query("SELECT balance FROM users WHERE id = {$userId}")->fetchColumn();

echo json_encode([
    'success' => true,
    'internal_payment_id' => $payment['internal_payment_id'],
    'gateway' => $gateway,
    'status' => $status,
    'credited' => $credited,
    'amount' => (float)$payment['amount'],
    'currency' => $payment['currency'],
    'wallet_balance' => $balance,
    'redirect_url' => '/payment/verify?gateway=' . urlencode($gateway) . '&internal_id=' . urlencode($payment['internal_payment_id'])
]);
