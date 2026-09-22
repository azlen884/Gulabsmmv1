<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$gatewayCode = trim($_GET['gateway'] ?? '');
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$db = getDB();

if (empty($gatewayCode)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing gateway identifier.']);
    exit;
}

$gwStmt = $db->prepare("SELECT * FROM payment_gateways WHERE code = ? LIMIT 1");
$gwStmt->execute([$gatewayCode]);
$gateway = $gwStmt->fetch();

if (!$gateway) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Gateway not found in system.']);
    exit;
}

try {
    // -------------------------------------------------------------
    // STRIPE WEBHOOK
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
                // Find transaction
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

                    // Credit user balance
                    $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalCredit, $userId]);

                    // Update transaction
                    $db->prepare("
                        UPDATE transactions 
                        SET status = 'completed', transaction_id = ?, gateway_response = ?, updated_at = NOW() 
                        WHERE id = ?
                    ")->execute([$sessionId, json_encode($session), $txn['id']]);

                    // Bonus
                    if ($bonusAmount > 0) {
                        $db->prepare("
                            INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id, created_at)
                            VALUES (?, ?, 'bonus', 'Deposit Bonus (10%)', 'completed', ?, NOW())
                        ")->execute([$userId, $bonusAmount, 'BONUS-' . $sessionId]);
                    }

                    // Notification
                    $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, 'Payment Confirmed via Webhook', ?, 'wallet')
                    ")->execute([
                        $userId,
                        "Your deposit of \${$amount} was successfully verified via Stripe webhook."
                    ]);

                    $db->commit();
                }
            }
        }

        echo json_encode(['received' => true]);
        exit;
    }

    // -------------------------------------------------------------
    // PAYPAL WEBHOOK
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
