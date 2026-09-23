<?php
/**
 * RoseSMM - Auto Refill Engine
 * Full support for provider API refills, order status monitoring, and automated delivery guarantees.
 */

require_once __DIR__ . '/../config/database.php';

class RefillHelper {

    /**
     * Determine if an order is currently eligible for refill
     */
    public static function isEligible($order) {
        if (!$order || empty($order['id'])) {
            return ['eligible' => false, 'reason' => 'Order not found'];
        }

        // Global switch
        if (get_setting('auto_refill_enabled', '1') !== '1') {
            return ['eligible' => false, 'reason' => 'Refill system is disabled'];
        }

        // Status check: must be completed or partial
        if (!in_array($order['status'], ['completed', 'partial'])) {
            return ['eligible' => false, 'reason' => 'Order must be completed or partial to request refill'];
        }

        // Refill enabled on service?
        $db = getDB();
        $stmt = $db->prepare("SELECT refill_enabled, refill_days, refill_limit FROM services WHERE id = ?");
        $stmt->execute([$order['service_id']]);
        $service = $stmt->fetch();

        if (!$service || empty($service['refill_enabled'])) {
            return ['eligible' => false, 'reason' => 'Refill is not supported for this service'];
        }

        $refillDays = (int)($service['refill_days'] ?? 30);
        $refillLimit = (int)($service['refill_limit'] ?? 5);

        // Days check
        $orderCreatedAt = strtotime($order['created_at']);
        $daysDiff = (time() - $orderCreatedAt) / 86400;
        if ($daysDiff > $refillDays) {
            return ['eligible' => false, 'reason' => "Refill warranty period of {$refillDays} days has expired"];
        }

        // Count check
        $currentCount = (int)($order['refill_count'] ?? 0);
        if ($currentCount >= $refillLimit) {
            return ['eligible' => false, 'reason' => "Maximum refill limit ({$refillLimit} refills) reached for this order"];
        }

        // Status check: not already pending/processing
        if (in_array($order['refill_status'], ['pending', 'processing'])) {
            return ['eligible' => false, 'reason' => 'A refill is already in progress for this order'];
        }

        // Cooldown check (minimum 2 hours between refill requests)
        if (!empty($order['last_refill_at'])) {
            $lastRefill = strtotime($order['last_refill_at']);
            $hoursSince = (time() - $lastRefill) / 3600;
            if ($hoursSince < 2) {
                $remMinutes = round((2 - $hoursSince) * 60);
                return ['eligible' => false, 'reason' => "Please wait {$remMinutes} more minutes before requesting another refill"];
            }
        }

        return ['eligible' => true, 'service' => $service];
    }

    /**
     * Submit a refill request for an order
     */
    public static function requestRefill($orderId, $userId, $isAuto = false) {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        // User verification (unless called by admin or auto-cron)
        if ($userId !== 0 && (int)$order['user_id'] !== (int)$userId) {
            return ['success' => false, 'error' => 'Unauthorized order access'];
        }

        $check = self::isEligible($order);
        if (!$check['eligible']) {
            return ['success' => false, 'error' => $check['reason']];
        }

        // Fetch provider information if attached
        $providerRefillId = null;
        $providerResponse = null;
        $providerStatus = 'pending';

        if (!empty($order['provider_id']) && !empty($order['provider_order_id'])) {
            $pStmt = $db->prepare("SELECT * FROM providers WHERE id = ? AND status = 'active'");
            $pStmt->execute([$order['provider_id']]);
            $provider = $pStmt->fetch();

            if ($provider && !empty($provider['api_url']) && !empty($provider['api_key'])) {
                // Call real upstream SMM provider refill endpoint
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'key' => $provider['api_key'],
                    'action' => 'refill',
                    'order' => $order['provider_order_id']
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $res = curl_exec($ch);
                curl_close($ch);

                $providerResponse = $res;
                $resData = json_decode($res, true);
                if ($resData && isset($resData['refill'])) {
                    $providerRefillId = (string)$resData['refill'];
                    $providerStatus = 'processing';
                } elseif ($resData && isset($resData['error'])) {
                    $providerStatus = 'failed';
                }
            }
        }

        // Record in refill_requests table
        $ins = $db->prepare("
            INSERT INTO refill_requests (order_id, user_id, service_id, provider_id, provider_order_id, provider_refill_id, status, refill_type, provider_response, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $refillType = $isAuto ? 'auto' : 'manual';
        $ins->execute([
            $order['id'],
            $order['user_id'],
            $order['service_id'],
            $order['provider_id'] ?: null,
            $order['provider_order_id'] ?: null,
            $providerRefillId,
            $providerStatus,
            $refillType,
            $providerResponse
        ]);
        $refillId = $db->lastInsertId();

        // Update orders table
        $newCount = (int)$order['refill_count'] + 1;
        $db->prepare("
            UPDATE orders 
            SET refill_status = ?, refill_count = ?, last_refill_at = NOW() 
            WHERE id = ?
        ")->execute([$providerStatus, $newCount, $orderId]);

        // Notify user
        $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'order')
        ")->execute([
            $order['user_id'],
            "Refill Request #{$refillId} Submitted",
            "A refill request has been initiated for Order #{$orderId}. Status: " . ucfirst($providerStatus)
        ]);

        return [
            'success' => true,
            'refill_id' => $refillId,
            'status' => $providerStatus,
            'provider_refill_id' => $providerRefillId,
            'message' => "Refill submitted successfully for Order #{$orderId}."
        ];
    }

    /**
     * Process pending refill status checks against upstream providers
     */
    public static function syncPendingRefills() {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT r.*, p.api_url, p.api_key 
            FROM refill_requests r
            LEFT JOIN providers p ON r.provider_id = p.id
            WHERE r.status IN ('pending', 'processing')
            LIMIT 50
        ");
        $stmt->execute();
        $pending = $stmt->fetchAll();

        $updatedCount = 0;

        foreach ($pending as $req) {
            if (empty($req['api_url']) || empty($req['api_key']) || empty($req['provider_refill_id'])) {
                // If local or manual provider, set to completed after 1 hour simulation
                $age = time() - strtotime($req['created_at']);
                if ($age > 3600) {
                    $db->prepare("UPDATE refill_requests SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$req['id']]);
                    $db->prepare("UPDATE orders SET refill_status = 'completed' WHERE id = ?")->execute([$req['order_id']]);
                    $updatedCount++;
                }
                continue;
            }

            // Real SMM Provider API query
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $req['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'key' => $req['api_key'],
                'action' => 'refill_status',
                'refill' => $req['provider_refill_id']
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($res, true);
            if ($data && isset($data['status'])) {
                $statusMap = [
                    'Completed' => 'completed',
                    'Rejected' => 'rejected',
                    'In progress' => 'processing',
                    'Processing' => 'processing',
                    'Pending' => 'pending'
                ];
                $normStatus = $statusMap[$data['status']] ?? strtolower($data['status']);
                $db->prepare("UPDATE refill_requests SET status = ?, provider_response = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$normStatus, $res, $req['id']]);
                $db->prepare("UPDATE orders SET refill_status = ? WHERE id = ?")
                   ->execute([$normStatus, $req['order_id']]);
                $updatedCount++;
            }
        }

        return $updatedCount;
    }
}
