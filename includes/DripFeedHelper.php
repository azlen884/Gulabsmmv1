<?php
/**
 * RoseSMM - Drip-Feed Order Engine
 * Manages incremental service delivery over scheduled intervals, batch tracking, and provider dispatches.
 */

require_once __DIR__ . '/../config/database.php';

class DripFeedHelper {

    /**
     * Create a multi-run Drip-Feed order
     */
    public static function createDripFeed($userId, $serviceId, $link, $quantityPerRun, $runs, $intervalMinutes) {
        $db = getDB();

        if (get_setting('dripfeed_enabled', '1') !== '1') {
            return ['success' => false, 'error' => 'Drip-Feed service is currently disabled'];
        }

        $runs = (int)$runs;
        $intervalMinutes = (int)$intervalMinutes;
        $quantityPerRun = (int)$quantityPerRun;

        if ($runs < 2) {
            return ['success' => false, 'error' => 'Drip-feed requires at least 2 runs'];
        }
        if ($runs > 100) {
            return ['success' => false, 'error' => 'Maximum allowed runs is 100'];
        }
        if ($intervalMinutes < 1) {
            return ['success' => false, 'error' => 'Interval between runs must be at least 1 minute'];
        }

        // Validate service
        $sStmt = $db->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
        $sStmt->execute([$serviceId]);
        $service = $sStmt->fetch();

        if (!$service) {
            return ['success' => false, 'error' => 'Selected service is unavailable'];
        }

        if (empty($service['dripfeed'])) {
            return ['success' => false, 'error' => 'Selected service does not support Drip-Feed'];
        }

        $minQty = (int)($service['min_quantity'] ?? 10);
        $maxQty = (int)($service['max_quantity'] ?? 100000);

        if ($quantityPerRun < $minQty || $quantityPerRun > $maxQty) {
            return ['success' => false, 'error' => "Quantity per run must be between {$minQty} and {$maxQty}"];
        }

        $totalQuantity = $quantityPerRun * $runs;

        // Calculate total cost in USD
        $serviceBaseCurr = $service['currency'] ?? 'USD';
        $serviceRateInUSD = convert_price($service['rate'], $serviceBaseCurr, 'USD');
        $totalCharge = round(($serviceRateInUSD / 1000.0) * $totalQuantity, 4);

        $uStmt = $db->prepare("SELECT balance, currency FROM users WHERE id = ? FOR UPDATE");

        try {
            $db->beginTransaction();
            $uStmt->execute([$userId]);
            $user = $uStmt->fetch();

            if (!$user) {
                $db->rollBack();
                return ['success' => false, 'error' => 'User not found'];
            }

            $currentBalance = (float)$user['balance'];
            if ($currentBalance < $totalCharge) {
                $db->rollBack();
                $userCurr = $user['currency'] ?: 'USD';
                $neededFormatted = format_price($totalCharge, $userCurr, 'USD');
                $haveFormatted = format_price($currentBalance, $userCurr, 'USD');
                return [
                    'success' => false, 
                    'error' => "Insufficient wallet balance. Total drip-feed cost is {$neededFormatted}, your balance is {$haveFormatted}."
                ];
            }

            // Deduct balance
            $newBalance = round($currentBalance - $totalCharge, 4);
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

            // Create drip_feed_orders record
            $insDf = $db->prepare("
                INSERT INTO drip_feed_orders (user_id, service_id, link, total_quantity, runs, interval_minutes, quantity_per_run, current_run, status, total_charge, currency, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', ?, 'USD', NOW())
            ");
            $insDf->execute([
                $userId,
                $serviceId,
                $link,
                $totalQuantity,
                $runs,
                $intervalMinutes,
                $quantityPerRun,
                $totalCharge
            ]);
            $dripFeedId = $db->lastInsertId();

            // Record main wallet transaction
            $db->prepare("
                INSERT INTO transactions (user_id, order_id, type, amount, charge, currency, payment_method, transaction_id, status, created_at)
                VALUES (?, NULL, 'order', ?, 0.0000, 'USD', 'Wallet (Drip-Feed)', ?, 'completed', NOW())
            ")->execute([$userId, $totalCharge, 'DRIP-' . $dripFeedId]);

            // Create batch records
            $nowTime = time();
            $insBatch = $db->prepare("
                INSERT INTO drip_feed_batches (drip_feed_id, run_number, quantity, status, scheduled_at, created_at)
                VALUES (?, ?, ?, 'pending', ?, NOW())
            ");

            $firstBatchId = null;
            for ($i = 1; $i <= $runs; $i++) {
                $scheduleTs = $nowTime + (($i - 1) * $intervalMinutes * 60);
                $scheduledAt = date('Y-m-d H:i:s', $scheduleTs);
                $insBatch->execute([$dripFeedId, $i, $quantityPerRun, $scheduledAt]);
                if ($i === 1) {
                    $firstBatchId = $db->lastInsertId();
                }
            }

            $db->commit();

            // Execute the first batch immediately!
            if ($firstBatchId) {
                self::executeBatch($firstBatchId);
            }

            return [
                'success' => true,
                'drip_feed_id' => $dripFeedId,
                'total_charge' => $totalCharge,
                'new_balance' => $newBalance,
                'runs' => $runs,
                'total_quantity' => $totalQuantity,
                'message' => "Drip-feed order #{$dripFeedId} created successfully! Run 1 has started."
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Drip-feed execution error: ' . $e->getMessage()];
        }
    }

    /**
     * Dispatch an individual batch of a drip feed
     */
    public static function executeBatch($batchId) {
        $db = getDB();

        $bStmt = $db->prepare("
            SELECT b.*, d.user_id, d.service_id, d.link, d.runs, d.current_run, d.status AS drip_status
            FROM drip_feed_batches b
            JOIN drip_feed_orders d ON b.drip_feed_id = d.id
            WHERE b.id = ?
        ");
        $bStmt->execute([$batchId]);
        $batch = $bStmt->fetch();

        if (!$batch || $batch['status'] === 'completed' || $batch['drip_status'] === 'canceled') {
            return false;
        }

        // Fetch service & provider details
        $sStmt = $db->prepare("SELECT * FROM services WHERE id = ?");
        $sStmt->execute([$batch['service_id']]);
        $service = $sStmt->fetch();

        if (!$service) {
            $db->prepare("UPDATE drip_feed_batches SET status = 'failed', error_message = 'Service missing' WHERE id = ?")->execute([$batchId]);
            return false;
        }

        $serviceBaseCurr = $service['currency'] ?? 'USD';
        $rateUSD = convert_price($service['rate'], $serviceBaseCurr, 'USD');
        $batchCharge = round(($rateUSD / 1000.0) * $batch['quantity'], 4);

        $providerOrderId = null;
        $providerResponse = null;

        // Upstream provider order dispatch
        if (!empty($service['provider_id']) && !empty($service['provider_service_id'])) {
            $pStmt = $db->prepare("SELECT * FROM providers WHERE id = ? AND status = 'active'");
            $pStmt->execute([$service['provider_id']]);
            $provider = $pStmt->fetch();

            if ($provider) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'key' => $provider['api_key'],
                    'action' => 'add',
                    'service' => $service['provider_service_id'],
                    'link' => $batch['link'],
                    'quantity' => $batch['quantity']
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $res = curl_exec($ch);
                curl_close($ch);

                $providerResponse = $res;
                $resData = json_decode($res, true);
                if ($resData && isset($resData['order'])) {
                    $providerOrderId = (string)$resData['order'];
                }
            }
        }

        // Create child order in orders table
        $insOrder = $db->prepare("
            INSERT INTO orders (user_id, service_id, provider_id, provider_order_id, link, quantity, charge, start_count, remains, status, is_dripfeed, dripfeed_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending', 1, ?, NOW())
        ");
        $insOrder->execute([
            $batch['user_id'],
            $batch['service_id'],
            $service['provider_id'] ?: null,
            $providerOrderId,
            $batch['link'],
            $batch['quantity'],
            $batchCharge,
            $batch['quantity'],
            $batch['drip_feed_id']
        ]);
        $orderId = $db->lastInsertId();

        // Update batch status
        $db->prepare("
            UPDATE drip_feed_batches 
            SET status = 'completed', order_id = ?, provider_order_id = ?, response = ?, executed_at = NOW() 
            WHERE id = ?
        ")->execute([$orderId, $providerOrderId, $providerResponse, $batchId]);

        // Update drip feed main record
        $newRun = (int)$batch['current_run'] + 1;
        $isCompleted = ($newRun >= (int)$batch['runs']) ? 'completed' : 'active';

        $db->prepare("
            UPDATE drip_feed_orders 
            SET current_run = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ")->execute([$newRun, $isCompleted, $batch['drip_feed_id']]);

        // User notification
        $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'order')
        ")->execute([
            $batch['user_id'],
            "Drip-Feed #{$batch['drip_feed_id']} Run {$batch['run_number']}/{$batch['runs']} Dispatched",
            "Batch order #{$orderId} ({$batch['quantity']} units) has been processed."
        ]);

        return $orderId;
    }

    /**
     * Sweep and run all pending batches that have reached their scheduled time
     */
    public static function processDueBatches() {
        $db = getDB();

        if (get_setting('dripfeed_enabled', '1') !== '1') {
            return 0;
        }

        $stmt = $db->query("
            SELECT b.id 
            FROM drip_feed_batches b
            JOIN drip_feed_orders d ON b.drip_feed_id = d.id
            WHERE b.status = 'pending' 
              AND d.status = 'active'
              AND b.scheduled_at <= NOW()
            ORDER BY b.scheduled_at ASC
            LIMIT 30
        ");
        $dueBatches = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $executed = 0;
        foreach ($dueBatches as $batchId) {
            if (self::executeBatch($batchId)) {
                $executed++;
            }
        }

        return $executed;
    }
}
