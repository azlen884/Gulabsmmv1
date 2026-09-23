<?php
/**
 * RoseSMM - Auto Refund Engine
 * Handles wallet credits, partial refunds, provider cancellation syncs, and transaction audit trails.
 */

require_once __DIR__ . '/../config/database.php';

class RefundHelper {

    /**
     * Process a real refund for an order into the user's wallet
     */
    public static function processOrderRefund($orderId, $reason = 'Order Canceled/Partial', $refundType = 'auto') {
        $db = getDB();

        // Check global setting
        if ($refundType === 'auto' && get_setting('auto_refund_enabled', '1') !== '1') {
            return ['success' => false, 'error' => 'Auto-refund system is disabled'];
        }

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        if ($order['refund_status'] === 'refunded') {
            return ['success' => false, 'error' => 'Order has already been fully refunded'];
        }

        $charge = (float)$order['charge'];
        $alreadyRefunded = (float)($order['refunded_amount'] ?? 0);
        $status = strtolower($order['status']);

        $refundAmount = 0.0;
        $targetRefundStatus = 'refunded';

        if ($status === 'canceled') {
            // Full refund
            $refundAmount = max(0, round($charge - $alreadyRefunded, 4));
            $targetRefundStatus = 'refunded';
        } elseif ($status === 'partial') {
            // Partial refund based on remains vs quantity
            $qty = max(1, (int)$order['quantity']);
            $remains = max(0, (int)$order['remains']);
            $calculatedRefund = round(($remains / (float)$qty) * $charge, 4);
            $refundAmount = max(0, round($calculatedRefund - $alreadyRefunded, 4));
            $targetRefundStatus = 'partial_refunded';
        } else {
            // If manual refund requested for other statuses
            if ($refundType === 'manual') {
                $refundAmount = max(0, round($charge - $alreadyRefunded, 4));
                $targetRefundStatus = 'refunded';
            } else {
                return ['success' => false, 'error' => "Order status '{$status}' is not eligible for auto-refund"];
            }
        }

        if ($refundAmount <= 0.0001) {
            return ['success' => false, 'error' => 'Calculated refund amount is zero or already satisfied'];
        }

        $userId = (int)$order['user_id'];

        try {
            $db->beginTransaction();

            // Lock user wallet
            $uStmt = $db->prepare("SELECT balance, currency FROM users WHERE id = ? FOR UPDATE");
            $uStmt->execute([$userId]);
            $user = $uStmt->fetch();

            if (!$user) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Associated user account not found'];
            }

            $currentBalance = (float)$user['balance'];
            $newBalance = round($currentBalance + $refundAmount, 4);

            // Update user wallet
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

            // Create wallet transaction record
            $txnId = 'REF-' . $orderId . '-' . time();
            $tIns = $db->prepare("
                INSERT INTO transactions (user_id, order_id, type, amount, charge, currency, payment_method, transaction_id, status, created_at)
                VALUES (?, ?, 'refund', ?, 0.0000, 'USD', 'System Refund', ?, 'completed', NOW())
            ");
            $tIns->execute([$userId, $orderId, $refundAmount, $txnId]);
            $walletTxnId = $db->lastInsertId();

            // Record in refund_records table
            $db->prepare("
                INSERT INTO refund_records (order_id, user_id, amount, currency, reason, refund_type, status, wallet_transaction_id, created_at)
                VALUES (?, ?, ?, 'USD', ?, ?, 'completed', ?, NOW())
            ")->execute([$orderId, $userId, $refundAmount, $reason, $refundType, $walletTxnId]);

            // Update order status & refund columns
            $newTotalRefunded = round($alreadyRefunded + $refundAmount, 4);
            $db->prepare("
                UPDATE orders 
                SET refund_status = ?, refunded_amount = ? 
                WHERE id = ?
            ")->execute([$targetRefundStatus, $newTotalRefunded, $orderId]);

            // Notify user
            $userCurr = $user['currency'] ?: 'USD';
            $formattedAmt = format_price($refundAmount, $userCurr, 'USD');
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'wallet')
            ")->execute([
                $userId,
                "Refund Issued: Order #{$orderId}",
                "Your account balance has been credited with {$formattedAmt} for Order #{$orderId} ({$reason})."
            ]);

            $db->commit();

            return [
                'success' => true,
                'refund_amount' => $refundAmount,
                'formatted_amount' => $formattedAmt,
                'new_balance' => $newBalance,
                'message' => "Successfully refunded {$formattedAmt} to user wallet."
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Database error during refund: ' . $e->getMessage()];
        }
    }

    /**
     * Sweep orders and automatically refund any canceled or partial orders
     */
    public static function sweepAutoRefunds() {
        $db = getDB();

        if (get_setting('auto_refund_enabled', '1') !== '1') {
            return ['processed' => 0, 'total_amount' => 0];
        }

        $statusesConfig = get_setting('auto_refund_statuses', 'canceled,partial');
        $allowedStatuses = array_map('trim', explode(',', $statusesConfig));
        if (empty($allowedStatuses)) {
            $allowedStatuses = ['canceled', 'partial'];
        }

        $inClause = implode("','", array_map('addslashes', $allowedStatuses));
        $stmt = $db->query("
            SELECT id FROM orders 
            WHERE status IN ('$inClause') 
              AND (refund_status IS NULL OR refund_status IN ('none', 'failed'))
            ORDER BY id ASC 
            LIMIT 50
        ");
        $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $processed = 0;
        $totalAmount = 0.0;

        foreach ($orders as $oid) {
            $res = self::processOrderRefund($oid, 'Automated Refund Engine Sweep', 'auto');
            if ($res['success']) {
                $processed++;
                $totalAmount += $res['refund_amount'];
            }
        }

        return ['processed' => $processed, 'total_amount' => $totalAmount];
    }

    /**
     * Process manual or specific refund amount
     */
    public static function processRefund($orderId, $amount = null, $reason = 'Manual refund', $refundType = 'manual', $cancelOrder = false) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $userId = (int)$order['user_id'];
        $charge = (float)$order['charge'];
        $alreadyRefunded = (float)($order['refunded_amount'] ?? 0);

        if ($amount === null || (float)$amount <= 0) {
            $amount = max(0, $charge - $alreadyRefunded);
        } else {
            $amount = (float)$amount;
        }

        if ($amount <= 0.0001) {
            return ['success' => false, 'error' => 'Refund amount must be greater than zero'];
        }

        try {
            $db->beginTransaction();

            $uStmt = $db->prepare("SELECT balance, currency FROM users WHERE id = ? FOR UPDATE");
            $uStmt->execute([$userId]);
            $user = $uStmt->fetch();

            if (!$user) {
                $db->rollBack();
                return ['success' => false, 'error' => 'User not found'];
            }

            $newBalance = round((float)$user['balance'] + $amount, 4);
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

            $txnId = 'REF-' . $orderId . '-' . time();
            $tIns = $db->prepare("
                INSERT INTO transactions (user_id, order_id, type, amount, charge, currency, payment_method, transaction_id, status, created_at)
                VALUES (?, ?, 'refund', ?, 0.0000, 'USD', 'System Refund', ?, 'completed', NOW())
            ");
            $tIns->execute([$userId, $orderId, $amount, $txnId]);
            $walletTxnId = $db->lastInsertId();

            $db->prepare("
                INSERT INTO refund_records (order_id, user_id, amount, currency, reason, refund_type, status, wallet_transaction_id, created_at)
                VALUES (?, ?, ?, 'USD', ?, ?, 'completed', ?, NOW())
            ")->execute([$orderId, $userId, $amount, $reason, $refundType, $walletTxnId]);

            $newTotalRefunded = round($alreadyRefunded + $amount, 4);
            $targetRefundStatus = ($newTotalRefunded >= $charge) ? 'refunded' : 'partial_refunded';

            $orderUpdateSql = "UPDATE orders SET refund_status = ?, refunded_amount = ?";
            $orderParams = [$targetRefundStatus, $newTotalRefunded];

            if ($cancelOrder) {
                $orderUpdateSql .= ", status = 'canceled'";
            }
            $orderUpdateSql .= " WHERE id = ?";
            $orderParams[] = $orderId;

            $db->prepare($orderUpdateSql)->execute($orderParams);

            $userCurr = $user['currency'] ?: 'USD';
            $formattedAmt = format_price($amount, $userCurr, 'USD');
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'wallet')
            ")->execute([
                $userId,
                "Refund Issued: Order #{$orderId}",
                "Your account balance has been credited with {$formattedAmt} for Order #{$orderId} ({$reason})."
            ]);

            $db->commit();

            return [
                'success' => true,
                'refund_amount' => $amount,
                'formatted_amount' => $formattedAmt,
                'new_balance' => $newBalance,
                'message' => "Successfully refunded {$formattedAmt} to user wallet."
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle state changes on orders (e.g. from admin panel status select)
     */
    public static function handleOrderStateChange($orderId, $oldStatus, $newStatus) {
        if ($oldStatus === $newStatus) return;
        if (in_array(strtolower($newStatus), ['canceled', 'partial'])) {
            return self::processOrderRefund($orderId, "Status updated to " . ucfirst($newStatus), 'auto');
        }
    }
}
