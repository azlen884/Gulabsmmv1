<?php
/**
 * RoseSMM - Payment Helper
 * Centralized, production-grade payment management, atomic wallet credit,
 * idempotency checks, and audit logging.
 */

require_once __DIR__ . '/../../config/database.php';

class PaymentHelper {

    /**
     * Fetch gateway configuration from MySQL
     */
    public static function getGateway(string $code, bool $onlyActive = true): ?array {
        $db = getDB();
        $sql = "SELECT * FROM payment_gateways WHERE code = ?";
        if ($onlyActive) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$code]);
        $gw = $stmt->fetch();
        return $gw ?: null;
    }

    /**
     * Generate unique, non-colliding internal payment reference
     */
    public static function generateInternalId(string $gatewayCode): string {
        $prefix = 'RSMM_' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $gatewayCode), 0, 4));
        $timestamp = date('ymdHis');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return "{$prefix}_{$timestamp}_{$random}";
    }

    /**
     * Create initial payment record in MySQL
     */
    public static function createPayment(
        int $userId,
        string $gatewayCode,
        float $amount,
        string $currency,
        string $internalPaymentId,
        ?string $gatewayOrderId = null,
        ?array $initialResponse = null
    ): array {
        $db = getDB();

        $respJson = !empty($initialResponse) ? json_encode($initialResponse, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $db->prepare("
            INSERT INTO payments (
                user_id, gateway, internal_payment_id, gateway_order_id, 
                amount, currency, status, verification_status, gateway_response, created_at
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, 'CREATED', 'unverified', ?, NOW()
            )
        ");
        $stmt->execute([
            $userId,
            $gatewayCode,
            $internalPaymentId,
            $gatewayOrderId,
            $amount,
            strtoupper($currency),
            $respJson
        ]);

        $recordId = (int)$db->lastInsertId();

        // Also create a tracking pending row in transactions
        $gw = self::getGateway($gatewayCode, false);
        $gwName = $gw['name'] ?? ucfirst($gatewayCode);
        $tStmt = $db->prepare("
            INSERT INTO transactions (
                user_id, amount, type, payment_method, gateway_code, 
                currency, status, transaction_id, gateway_response, created_at
            ) VALUES (
                ?, ?, 'deposit', ?, ?, 
                ?, 'pending', ?, ?, NOW()
            )
        ");
        $tStmt->execute([
            $userId,
            $amount,
            $gwName,
            $gatewayCode,
            strtoupper($currency),
            $internalPaymentId,
            $respJson
        ]);

        return self::getPaymentByInternalId($internalPaymentId);
    }

    /**
     * Fetch payment by internal payment ID
     */
    public static function getPaymentByInternalId(string $internalId): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM payments WHERE internal_payment_id = ? LIMIT 1");
        $stmt->execute([$internalId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch payment by gateway order ID
     */
    public static function getPaymentByGatewayOrderId(string $gateway, string $orderId): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM payments WHERE gateway = ? AND gateway_order_id = ? LIMIT 1");
        $stmt->execute([$gateway, $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Update gateway order ID for an internal payment
     */
    public static function setGatewayOrderId(string $internalId, string $orderId, ?array $response = null): void {
        $db = getDB();
        $respJson = !empty($response) ? json_encode($response, JSON_UNESCAPED_SLASHES) : null;
        if ($respJson) {
            $stmt = $db->prepare("
                UPDATE payments 
                SET gateway_order_id = ?, status = 'PENDING', gateway_response = ?, updated_at = NOW() 
                WHERE internal_payment_id = ?
            ");
            $stmt->execute([$orderId, $respJson, $internalId]);
        } else {
            $stmt = $db->prepare("
                UPDATE payments 
                SET gateway_order_id = ?, status = 'PENDING', updated_at = NOW() 
                WHERE internal_payment_id = ?
            ");
            $stmt->execute([$orderId, $internalId]);
        }
    }

    /**
     * ATOMIC, IDEMPOTENT WALLET CREDIT
     * Uses MySQL row locking (FOR UPDATE) to prevent race conditions,
     * duplicate webhook triggers, or double crediting.
     *
     * @return array [success => bool, already_credited => bool, message => string, new_balance => float]
     */
    public static function creditWalletForVerifiedPayment(
        string $internalPaymentId,
        string $gatewayPaymentId,
        float $verifiedAmount,
        string $verifiedCurrency,
        array $gatewayPayload,
        string $verificationMethod = 'server_api'
    ): array {
        $db = getDB();

        try {
            $db->beginTransaction();

            // Lock payment row with FOR UPDATE
            $stmt = $db->prepare("SELECT * FROM payments WHERE internal_payment_id = ? FOR UPDATE");
            $stmt->execute([$internalPaymentId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $db->rollBack();
                return ['success' => false, 'error' => "Payment record {$internalPaymentId} not found in database."];
            }

            $userId = (int)$payment['user_id'];

            // IDEMPOTENCY CHECK: If already credited, do NOT credit again!
            if ((int)$payment['is_credited'] === 1 || $payment['status'] === 'SUCCESS') {
                $db->commit();
                $curBal = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
                return [
                    'success' => true,
                    'already_credited' => true,
                    'message' => 'Payment was already verified and credited previously.',
                    'credited_amount' => (float)$payment['amount'],
                    'new_balance' => $curBal,
                    'payment' => $payment
                ];
            }

            // AMOUNT INTEGRITY VERIFICATION
            $expectedAmount = (float)$payment['amount'];
            if (abs($verifiedAmount - $expectedAmount) > 0.05) {
                // If gateway charged significantly different amount, flag as mismatch
                $stmtFail = $db->prepare("
                    UPDATE payments 
                    SET status = 'REJECTED', verification_status = 'amount_mismatch', 
                        failure_reason = ?, gateway_response = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $failMsg = "Amount mismatch: expected {$expectedAmount}, verified {$verifiedAmount}";
                $stmtFail->execute([$failMsg, json_encode($gatewayPayload), $payment['id']]);
                $db->commit();
                return ['success' => false, 'error' => $failMsg];
            }

            // CURRENCY INTEGRITY VERIFICATION
            $expectedCurrency = strtoupper($payment['currency']);
            $verifiedCurrency = strtoupper($verifiedCurrency);
            if ($expectedCurrency !== $verifiedCurrency) {
                $stmtFail = $db->prepare("
                    UPDATE payments 
                    SET status = 'REJECTED', verification_status = 'currency_mismatch', 
                        failure_reason = ?, gateway_response = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $failMsg = "Currency mismatch: expected {$expectedCurrency}, verified {$verifiedCurrency}";
                $stmtFail->execute([$failMsg, json_encode($gatewayPayload), $payment['id']]);
                $db->commit();
                return ['success' => false, 'error' => $failMsg];
            }

            // Calculate Deposit Bonus
            $bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
            $bonusAmount = round(($bonusPercent / 100.0) * $verifiedAmount, 2);
            $totalCredit = $verifiedAmount + $bonusAmount;

            // 1. Credit User Balance atomically
            $updUser = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $updUser->execute([$totalCredit, $userId]);

            // 2. Fetch or update transaction row in `transactions`
            $gw = self::getGateway($payment['gateway'], false);
            $gwName = $gw['name'] ?? ucfirst($payment['gateway']);

            $tFind = $db->prepare("
                SELECT id FROM transactions 
                WHERE (transaction_id = ? OR transaction_id = ?) AND user_id = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $tFind->execute([$internalPaymentId, $gatewayPaymentId, $userId]);
            $existingTxnId = $tFind->fetchColumn();

            $fullResponseStr = json_encode([
                'verification_method' => $verificationMethod,
                'verified_at' => date('Y-m-d H:i:s'),
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_data' => $gatewayPayload
            ], JSON_UNESCAPED_SLASHES);

            if ($existingTxnId) {
                $db->prepare("
                    UPDATE transactions 
                    SET status = 'completed', transaction_id = ?, payment_method = ?, 
                        gateway_response = ?, updated_at = NOW() 
                    WHERE id = ?
                ")->execute([$gatewayPaymentId, $gwName, $fullResponseStr, $existingTxnId]);
                $walletTxnId = (int)$existingTxnId;
            } else {
                $insTxn = $db->prepare("
                    INSERT INTO transactions (
                        user_id, amount, type, payment_method, gateway_code, 
                        currency, status, transaction_id, gateway_response, created_at
                    ) VALUES (
                        ?, ?, 'deposit', ?, ?, 
                        ?, 'completed', ?, ?, NOW()
                    )
                ");
                $insTxn->execute([
                    $userId,
                    $verifiedAmount,
                    $gwName,
                    $payment['gateway'],
                    $verifiedCurrency,
                    $gatewayPaymentId,
                    $fullResponseStr
                ]);
                $walletTxnId = (int)$db->lastInsertId();
            }

            // 3. Record Bonus Transaction if applicable
            if ($bonusAmount > 0) {
                $bonusTxnId = 'BONUS-' . $gatewayPaymentId;
                $db->prepare("
                    INSERT INTO transactions (
                        user_id, amount, type, payment_method, gateway_code, 
                        currency, status, transaction_id, created_at
                    ) VALUES (
                        ?, ?, 'bonus', 'Deposit Bonus (10%)', ?, 
                        ?, 'completed', ?, NOW()
                    )
                ")->execute([
                    $userId,
                    $bonusAmount,
                    $payment['gateway'],
                    $verifiedCurrency,
                    $bonusTxnId
                ]);
            }

            // 4. Mark Payment as SUCCESS and CREDITED in `payments` table
            $updPay = $db->prepare("
                UPDATE payments 
                SET status = 'SUCCESS', verification_status = 'verified', 
                    is_credited = 1, gateway_payment_id = ?, 
                    wallet_transaction_id = ?, gateway_response = ?, 
                    failure_reason = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $updPay->execute([
                $gatewayPaymentId,
                $walletTxnId,
                $fullResponseStr,
                $payment['id']
            ]);

            // 5. User Notification
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Funds Added to Wallet', ?, 'wallet')
            ")->execute([
                $userId,
                "Your deposit of {$verifiedCurrency} " . number_format($verifiedAmount, 2) . 
                " via {$gwName} has been verified and added to your balance." . 
                ($bonusAmount > 0 ? " (Bonus: +{$verifiedCurrency} " . number_format($bonusAmount, 2) . ")" : "")
            ]);

            $db->commit();

            // Process real referral commission if eligible
            require_once __DIR__ . '/../ReferralHelper.php';
            ReferralHelper::processCommission('deposit', (string)$payment['id'], (int)$userId, (float)$verifiedAmount, $verifiedCurrency);

            $newBal = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

            return [
                'success' => true,
                'already_credited' => false,
                'message' => 'Payment successfully verified and wallet credited.',
                'credited_amount' => $verifiedAmount,
                'bonus_amount' => $bonusAmount,
                'total_credited' => $totalCredit,
                'currency' => $verifiedCurrency,
                'new_balance' => $newBal,
                'gateway_payment_id' => $gatewayPaymentId,
                'wallet_transaction_id' => $walletTxnId
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Database error during wallet credit: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mark payment as failed or cancelled
     */
    public static function markPaymentFailed(
        string $internalPaymentId,
        string $reason,
        string $status = 'FAILED',
        ?array $response = null
    ): void {
        $db = getDB();
        $validStatuses = ['FAILED', 'CANCELLED', 'EXPIRED', 'REJECTED'];
        if (!in_array($status, $validStatuses)) {
            $status = 'FAILED';
        }

        $respJson = !empty($response) ? json_encode($response, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $db->prepare("
            UPDATE payments 
            SET status = ?, verification_status = 'failed', 
                failure_reason = ?, gateway_response = COALESCE(?, gateway_response), 
                updated_at = NOW() 
            WHERE internal_payment_id = ? AND is_credited = 0
        ");
        $stmt->execute([$status, $reason, $respJson, $internalPaymentId]);

        // Also update transactions table
        $db->prepare("
            UPDATE transactions 
            SET status = 'failed', gateway_response = ?, updated_at = NOW() 
            WHERE transaction_id = ? AND status = 'pending'
        ")->execute([$reason, $internalPaymentId]);
    }

    /**
     * Check if payment is already credited
     */
    public static function isPaymentCredited(string $internalPaymentId): bool {
        $p = self::getPaymentByInternalId($internalPaymentId);
        return $p && ((int)$p['is_credited'] === 1 || $p['status'] === 'SUCCESS');
    }
}
