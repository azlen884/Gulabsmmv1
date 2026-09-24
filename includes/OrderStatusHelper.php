<?php
/**
 * RoseSMM - Real-Time Provider Order Status Synchronization Engine
 * Automatically queries upstream SMM API providers for order progress, start count, remains, and status changes.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/RefundHelper.php';

class OrderStatusHelper {

    /**
     * Map provider status strings to panel order status enum:
     * enum('pending','processing','in_progress','completed','partial','canceled')
     */
    public static function mapProviderStatus($rawStatus) {
        if ($rawStatus === null || $rawStatus === '') {
            return null;
        }

        $cleaned = strtolower(trim((string)$rawStatus));
        // Normalize separators
        $cleaned = str_replace(['_', '-'], ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        // Standard mapping according to SMM v2 protocol & real-world upstream providers
        switch ($cleaned) {
            case 'completed':
            case 'complete':
            case 'finish':
            case 'finished':
            case 'success':
            case 'done':
                return 'completed';

            case 'in progress':
            case 'inprogress':
            case 'active':
            case 'running':
                return 'in_progress';

            case 'processing':
            case 'process':
                return 'processing';

            case 'pending':
            case 'waiting':
            case 'queued':
            case 'submitted':
                return 'pending';

            case 'partial':
            case 'partially completed':
            case 'partially complete':
                return 'partial';

            case 'canceled':
            case 'cancelled':
            case 'refunded':
            case 'refund':
            case 'aborted':
            case 'rejected':
            case 'fail':
            case 'failed':
                return 'canceled';

            default:
                if (strpos($cleaned, 'complete') !== false) return 'completed';
                if (strpos($cleaned, 'progress') !== false) return 'in_progress';
                if (strpos($cleaned, 'process') !== false) return 'processing';
                if (strpos($cleaned, 'pend') !== false) return 'pending';
                if (strpos($cleaned, 'part') !== false) return 'partial';
                if (strpos($cleaned, 'cancel') !== false || strpos($cleaned, 'refund') !== false) return 'canceled';
                return null;
        }
    }

    /**
     * Synchronize all eligible orders with their configured upstream providers.
     * Safe for repeated execution: only modifies orders when changes occur.
     *
     * @param int|null $specificOrderId If specified, syncs only this single order.
     * @return array Execution summary report.
     */
    public static function syncProviderOrders($specificOrderId = null) {
        $db = getDB();
        $startTime = microtime(true);

        // 1. Fetch eligible orders that need status synchronization
        // Must have non-empty provider_order_id and be in an active non-terminal status
        $sql = "
            SELECT o.id, o.user_id, o.service_id, o.provider_id, o.provider_order_id, 
                   o.status, o.start_count, o.remains, o.quantity, o.charge,
                   s.provider_id AS service_provider_id
            FROM orders o
            LEFT JOIN services s ON o.service_id = s.id
            WHERE o.provider_order_id IS NOT NULL 
              AND TRIM(o.provider_order_id) != ''
        ";

        if ($specificOrderId !== null && (int)$specificOrderId > 0) {
            $sql .= " AND o.id = " . (int)$specificOrderId;
        } else {
            $sql .= " AND o.status IN ('pending', 'processing', 'in_progress')";
        }

        $sql .= " ORDER BY o.id ASC LIMIT 250";

        $orders = $db->query($sql)->fetchAll();

        if (empty($orders)) {
            return [
                'success' => true,
                'total_eligible' => 0,
                'updated_count' => 0,
                'unchanged_count' => 0,
                'error_count' => 0,
                'message' => 'No active provider orders require synchronization.',
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
                'logs' => []
            ];
        }

        // 2. Fetch all active providers indexed by ID
        $provStmt = $db->query("SELECT id, name, api_url, api_key, status FROM providers WHERE status = 'active'");
        $activeProviders = [];
        while ($row = $provStmt->fetch()) {
            $activeProviders[(int)$row['id']] = $row;
        }

        // Group eligible orders by effective provider ID
        $ordersByProvider = [];
        $unmatchedOrders = [];

        foreach ($orders as $ord) {
            $provId = !empty($ord['provider_id']) ? (int)$ord['provider_id'] : (int)($ord['service_provider_id'] ?? 0);

            if ($provId > 0 && isset($activeProviders[$provId])) {
                $ordersByProvider[$provId][] = $ord;
            } else {
                $unmatchedOrders[] = $ord;
            }
        }

        $updatedCount = 0;
        $unchangedCount = 0;
        $errorCount = 0;
        $logs = [];

        // 3. Process each provider's orders
        foreach ($ordersByProvider as $provId => $pOrders) {
            $provider = $activeProviders[$provId];
            $apiUrl = trim($provider['api_url']);
            $apiKey = trim($provider['api_key']);
            $provName = $provider['name'];

            if (empty($apiUrl) || empty($apiKey)) {
                $errorCount += count($pOrders);
                $logs[] = "Provider #{$provId} ({$provName}): Incomplete configuration (missing API URL or API key).";
                continue;
            }

            // Chunk orders into batches of 50 to respect upstream API limits
            $chunks = array_chunk($pOrders, 50);

            foreach ($chunks as $chunk) {
                $providerOrderMap = [];
                $cleanIds = [];
                foreach ($chunk as $ord) {
                    $pOrdId = trim($ord['provider_order_id']);
                    $providerOrderMap[$pOrdId] = $ord;
                    $cleanIds[] = $pOrdId;
                }

                // Attempt multi-order batch status query first (Standard SMM API v2)
                $multiResponse = self::queryProviderStatusMulti($apiUrl, $apiKey, $cleanIds);
                $batchHandled = false;

                if ($multiResponse['success'] && is_array($multiResponse['data'])) {
                    $resData = $multiResponse['data'];

                    // Check if response is keyed by order IDs (dictionary style)
                    $hasKeyedOrders = false;
                    foreach ($cleanIds as $cid) {
                        if (isset($resData[$cid]) && is_array($resData[$cid])) {
                            $hasKeyedOrders = true;
                            break;
                        }
                    }

                    if ($hasKeyedOrders) {
                        $batchHandled = true;
                        foreach ($cleanIds as $cid) {
                            $ord = $providerOrderMap[$cid];
                            $orderInfo = $resData[$cid] ?? null;

                            if (!$orderInfo || isset($orderInfo['error'])) {
                                $errDetail = is_array($orderInfo) ? ($orderInfo['error'] ?? 'No status returned') : 'No data in batch response';
                                $logs[] = "Order #{$ord['id']} (Provider ID {$cid}): " . self::sanitizeErrorMessage($errDetail, $apiKey);
                                $errorCount++;
                                continue;
                            }

                            $res = self::applyOrderUpdate($ord, $orderInfo, $provId);
                            if ($res['updated']) {
                                $updatedCount++;
                                $logs[] = "Order #{$ord['id']} updated: {$res['old_status']} -> {$res['new_status']}";
                            } elseif ($res['error']) {
                                $errorCount++;
                                $logs[] = "Order #{$ord['id']} sync error: " . self::sanitizeErrorMessage($res['error'], $apiKey);
                            } else {
                                $unchangedCount++;
                            }
                        }
                    }
                }

                // If multi-order batch query was not supported by provider, query individually
                if (!$batchHandled) {
                    foreach ($chunk as $ord) {
                        $cid = trim($ord['provider_order_id']);
                        $singleRes = self::queryProviderStatusSingle($apiUrl, $apiKey, $cid);

                        if (!$singleRes['success'] || !is_array($singleRes['data'])) {
                            $errDetail = $singleRes['error'] ?: 'Invalid provider API response';
                            $logs[] = "Order #{$ord['id']} (Provider ID {$cid}): " . self::sanitizeErrorMessage($errDetail, $apiKey);
                            $errorCount++;
                            continue;
                        }

                        $orderInfo = $singleRes['data'];
                        if (isset($orderInfo['error'])) {
                            $errMsg = is_string($orderInfo['error']) ? $orderInfo['error'] : json_encode($orderInfo['error']);
                            $logs[] = "Order #{$ord['id']} (Provider ID {$cid}): " . self::sanitizeErrorMessage($errMsg, $apiKey);
                            $errorCount++;
                            continue;
                        }

                        $res = self::applyOrderUpdate($ord, $orderInfo, $provId);
                        if ($res['updated']) {
                            $updatedCount++;
                            $logs[] = "Order #{$ord['id']} updated: {$res['old_status']} -> {$res['new_status']}";
                        } elseif ($res['error']) {
                            $errorCount++;
                            $logs[] = "Order #{$ord['id']} sync error: " . self::sanitizeErrorMessage($res['error'], $apiKey);
                        } else {
                            $unchangedCount++;
                        }
                    }
                }
            }
        }

        // Record any orders lacking an active provider
        if (!empty($unmatchedOrders)) {
            foreach ($unmatchedOrders as $uOrd) {
                $errorCount++;
                $pId = !empty($uOrd['provider_id']) ? $uOrd['provider_id'] : ($uOrd['service_provider_id'] ?? 'None');
                $logs[] = "Order #{$uOrd['id']}: Configured Provider #{$pId} is inactive or does not exist.";
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000);
        $summaryText = "Processed " . count($orders) . " orders. Updated: {$updatedCount}, Unchanged: {$unchangedCount}, Skipped/Errors: {$errorCount}.";

        return [
            'success' => true,
            'total_eligible' => count($orders),
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'error_count' => $errorCount,
            'duration_ms' => $durationMs,
            'message' => $summaryText,
            'logs' => $logs
        ];
    }

    /**
     * Apply status, start_count, and remains update to a single order row.
     * Preserves all other existing columns without overwriting unrelated data.
     */
    private static function applyOrderUpdate($ord, array $orderInfo, $providerId) {
        $db = getDB();
        $orderId = (int)$ord['id'];
        $oldStatus = $ord['status'];

        $rawStatus = $orderInfo['status'] ?? null;
        if (!$rawStatus) {
            return ['updated' => false, 'old_status' => $oldStatus, 'new_status' => $oldStatus, 'error' => 'No status field present in provider API response'];
        }

        $newStatus = self::mapProviderStatus($rawStatus);
        if (!$newStatus) {
            return ['updated' => false, 'old_status' => $oldStatus, 'new_status' => $oldStatus, 'error' => "Unmapped provider status value: '{$rawStatus}'"];
        }

        // Extract start_count and remains safely
        $newStartCount = null;
        if (isset($orderInfo['start_count']) && is_numeric($orderInfo['start_count'])) {
            $newStartCount = (int)$orderInfo['start_count'];
        }

        $newRemains = null;
        if (isset($orderInfo['remains']) && is_numeric($orderInfo['remains'])) {
            $newRemains = max(0, (int)$orderInfo['remains']);
        } elseif ($newStatus === 'completed') {
            $newRemains = 0;
        }

        // Determine if any database columns actually need updating
        $statusChanged = ($oldStatus !== $newStatus);
        $startCountChanged = ($newStartCount !== null && (int)$ord['start_count'] !== $newStartCount);
        $remainsChanged = ($newRemains !== null && (int)$ord['remains'] !== $newRemains);
        $providerIdNeedsSet = (empty($ord['provider_id']) && $providerId > 0);

        if (!$statusChanged && !$startCountChanged && !$remainsChanged && !$providerIdNeedsSet) {
            return ['updated' => false, 'old_status' => $oldStatus, 'new_status' => $oldStatus, 'error' => null];
        }

        // Build atomic UPDATE statement
        $fields = [];
        $params = [];

        if ($statusChanged) {
            $fields[] = "status = ?";
            $params[] = $newStatus;
        }

        if ($startCountChanged) {
            $fields[] = "start_count = ?";
            $params[] = $newStartCount;
        }

        if ($remainsChanged) {
            $fields[] = "remains = ?";
            $params[] = $newRemains;
        }

        if ($providerIdNeedsSet) {
            $fields[] = "provider_id = ?";
            $params[] = $providerId;
        }

        $fields[] = "updated_at = NOW()";

        $sql = "UPDATE orders SET " . implode(", ", $fields) . " WHERE id = ?";
        $params[] = $orderId;

        $db->prepare($sql)->execute($params);

        // If status changed to canceled or partial, trigger auto-refund logic if configured
        if ($statusChanged && in_array($newStatus, ['canceled', 'partial'])) {
            try {
                RefundHelper::handleOrderStateChange($orderId, $oldStatus, $newStatus);
            } catch (Exception $e) {
                // Keep order status update intact even if auto-refund hook logs a note
            }
        }

        return [
            'updated' => true,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'error' => null
        ];
    }

    /**
     * Check if provider URL is an internal local endpoint
     */
    private static function isLocalEndpoint($apiUrl) {
        $host = parse_url($apiUrl, PHP_URL_HOST);
        $port = parse_url($apiUrl, PHP_URL_PORT);
        $serverPort = (int)($_SERVER['SERVER_PORT'] ?? 3000);
        $isLocalHost = in_array($host, ['127.0.0.1', 'localhost', '::1']);
        return ($isLocalHost && ($port === $serverPort || empty($port)));
    }

    /**
     * Query provider via POST for a single order status
     */
    private static function queryProviderStatusSingle($apiUrl, $apiKey, $providerOrderId) {
        if (self::isLocalEndpoint($apiUrl)) {
            return self::queryLocalProviderSingle($apiKey, $providerOrderId);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'key' => $apiKey,
                'action' => 'status',
                'order' => $providerOrderId
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'RoseSMM-SyncEngine/1.0'
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($err)) {
            return ['success' => false, 'data' => null, 'error' => "cURL Network Error: {$err}"];
        }

        if ($httpCode >= 400) {
            return ['success' => false, 'data' => null, 'error' => "Provider server returned HTTP {$httpCode} status."];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null, 'error' => "Malformed non-JSON provider response: " . substr(strip_tags((string)$response), 0, 150)];
        }

        return ['success' => true, 'data' => $data, 'error' => null];
    }

    /**
     * Query provider via POST for multiple orders in batch
     */
    private static function queryProviderStatusMulti($apiUrl, $apiKey, array $providerOrderIds) {
        if (self::isLocalEndpoint($apiUrl)) {
            return self::queryLocalProviderMulti($apiKey, $providerOrderIds);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'key' => $apiKey,
                'action' => 'status',
                'orders' => implode(',', $providerOrderIds)
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'RoseSMM-SyncEngine/1.0'
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($err)) {
            return ['success' => false, 'data' => null, 'error' => "cURL Network Error: {$err}"];
        }

        if ($httpCode >= 400) {
            return ['success' => false, 'data' => null, 'error' => "Provider server returned HTTP {$httpCode} status."];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null, 'error' => "Malformed non-JSON provider response: " . substr(strip_tags((string)$response), 0, 150)];
        }

        return ['success' => true, 'data' => $data, 'error' => null];
    }

    /**
     * Internal direct query handler for localhost provider instances
     */
    private static function queryLocalProviderSingle($apiKey, $providerOrderId) {
        $db = getDB();
        $uStmt = $db->prepare("SELECT id, role FROM users WHERE api_key = ?");
        $uStmt->execute([$apiKey]);
        $user = $uStmt->fetch();

        if (!$user) {
            return ['success' => true, 'data' => ['error' => 'Invalid API key'], 'error' => null];
        }

        $pId = (int)$providerOrderId;
        $stmt = ($user['role'] === 'admin')
            ? $db->prepare("SELECT * FROM orders WHERE id = ?")
            : $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $params = ($user['role'] === 'admin') ? [$pId] : [$pId, $user['id']];
        $stmt->execute($params);
        $ord = $stmt->fetch();

        if (!$ord) {
            return ['success' => true, 'data' => ['error' => 'Incorrect order ID'], 'error' => null];
        }

        return [
            'success' => true,
            'data' => [
                'charge' => number_format($ord['charge'], 4, '.', ''),
                'start_count' => (string)($ord['start_count'] ?? 0),
                'status' => ucwords(str_replace('_', ' ', $ord['status'])),
                'remains' => (string)($ord['remains'] ?? 0),
                'currency' => 'USD'
            ],
            'error' => null
        ];
    }

    /**
     * Internal direct query batch handler for localhost provider instances
     */
    private static function queryLocalProviderMulti($apiKey, array $providerOrderIds) {
        $db = getDB();
        $uStmt = $db->prepare("SELECT id, role FROM users WHERE api_key = ?");
        $uStmt->execute([$apiKey]);
        $user = $uStmt->fetch();

        if (!$user) {
            return ['success' => true, 'data' => ['error' => 'Invalid API key'], 'error' => null];
        }

        $result = [];
        foreach ($providerOrderIds as $pIdStr) {
            $pId = (int)$pIdStr;
            $stmt = ($user['role'] === 'admin')
                ? $db->prepare("SELECT * FROM orders WHERE id = ?")
                : $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
            $params = ($user['role'] === 'admin') ? [$pId] : [$pId, $user['id']];
            $stmt->execute($params);
            $ord = $stmt->fetch();

            if (!$ord) {
                $result[(string)$pIdStr] = ['error' => 'Incorrect order ID'];
            } else {
                $result[(string)$pIdStr] = [
                    'charge' => number_format($ord['charge'], 4, '.', ''),
                    'start_count' => (string)($ord['start_count'] ?? 0),
                    'status' => ucwords(str_replace('_', ' ', $ord['status'])),
                    'remains' => (string)($ord['remains'] ?? 0),
                    'currency' => 'USD'
                ];
            }
        }

        return ['success' => true, 'data' => $result, 'error' => null];
    }

    /**
     * Sanitize sensitive API keys from log strings to protect credentials
     */
    public static function sanitizeErrorMessage($msg, $apiKey = null) {
        if (!is_string($msg)) {
            return (string)$msg;
        }
        if (!empty($apiKey)) {
            $masked = substr($apiKey, 0, 4) . '***' . substr($apiKey, -2);
            $msg = str_replace($apiKey, $masked, $msg);
        }
        // Also scrub any raw keys in URL query patterns
        $msg = preg_replace('/(key=)[a-zA-Z0-9_\-]+/i', '$1***', $msg);
        return $msg;
    }
}
