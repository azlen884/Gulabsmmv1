<?php
/**
 * RoseSMM - Mass Order Engine
 * Parses multi-line bulk inputs, executes multi-order validation, calculates total charges, and submits batch orders.
 */

require_once __DIR__ . '/../config/database.php';

class MassOrderHelper {

    /**
     * Parse raw text lines into structured order items
     * Supports formats:
     *   service_id | quantity | link
     *   service_id | link | quantity
     */
    public static function parseLines($rawInput) {
        $lines = explode("\n", str_replace("\r", "", $rawInput));
        $parsed = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Split by pipe | or comma or tab
            $parts = array_values(array_filter(array_map('trim', preg_split('/[\|\t]/', $trimmed))));
            if (count($parts) < 3) {
                // Try splitting by space
                $parts = array_values(array_filter(array_map('trim', preg_split('/\s+/', $trimmed))));
            }

            if (count($parts) < 3) {
                $parsed[] = [
                    'line_number' => $index + 1,
                    'raw' => $trimmed,
                    'is_valid' => false,
                    'error' => 'Invalid format. Use: service_id | quantity | link'
                ];
                continue;
            }

            $serviceId = (int)$parts[0];
            $item2 = $parts[1];
            $item3 = $parts[2];

            $quantity = 0;
            $link = '';

            // Detect whether part 2 or part 3 is numeric quantity
            if (is_numeric($item2) && !is_numeric($item3)) {
                $quantity = (int)$item2;
                $link = $item3;
            } elseif (is_numeric($item3) && !is_numeric($item2)) {
                $quantity = (int)$item3;
                $link = $item2;
            } elseif (is_numeric($item2) && is_numeric($item3)) {
                // Fallback: assume second is quantity, third is link (e.g. numeric ID)
                $quantity = (int)$item2;
                $link = $item3;
            } else {
                $link = $item2;
                $quantity = (int)$item3;
            }

            $parsed[] = [
                'line_number' => $index + 1,
                'raw' => $trimmed,
                'service_id' => $serviceId,
                'quantity' => $quantity,
                'link' => $link,
                'is_valid' => true
            ];
        }

        return $parsed;
    }

    /**
     * Process and place mass orders
     */
    public static function processMassOrder($userId, $rawInput) {
        $db = getDB();

        if (get_setting('mass_order_enabled', '1') !== '1') {
            return ['success' => false, 'error' => 'Mass order service is currently disabled'];
        }

        $items = self::parseLines($rawInput);
        if (empty($items)) {
            return ['success' => false, 'error' => 'No order lines provided. Please enter at least one order.'];
        }

        if (count($items) > 100) {
            return ['success' => false, 'error' => 'Maximum 100 orders allowed per mass order batch.'];
        }

        // Cache services lookup
        $serviceIds = [];
        foreach ($items as $it) {
            if ($it['is_valid'] && $it['service_id'] > 0) {
                $serviceIds[] = $it['service_id'];
            }
        }

        $services = [];
        if (!empty($serviceIds)) {
            $inClause = implode(',', array_unique($serviceIds));
            $sRows = $db->query("SELECT * FROM services WHERE id IN ($inClause)")->fetchAll();
            foreach ($sRows as $sr) {
                $services[$sr['id']] = $sr;
            }
        }

        // Validate each item and calculate charges
        $totalBatchCost = 0.0;
        $validatedItems = [];
        $hasInvalid = false;

        foreach ($items as $idx => $it) {
            if (!$it['is_valid']) {
                $hasInvalid = true;
                $validatedItems[] = $it;
                continue;
            }

            $srvId = $it['service_id'];
            if (!isset($services[$srvId])) {
                $it['is_valid'] = false;
                $it['error'] = "Service #{$srvId} does not exist";
                $hasInvalid = true;
                $validatedItems[] = $it;
                continue;
            }

            $srv = $services[$srvId];
            if ($srv['status'] !== 'active') {
                $it['is_valid'] = false;
                $it['error'] = "Service #{$srvId} ({$srv['name']}) is currently inactive";
                $hasInvalid = true;
                $validatedItems[] = $it;
                continue;
            }

            $min = (int)($srv['min_quantity'] ?? 10);
            $max = (int)($srv['max_quantity'] ?? 100000);
            if ($it['quantity'] < $min || $it['quantity'] > $max) {
                $it['is_valid'] = false;
                $it['error'] = "Quantity {$it['quantity']} out of range ($min - $max)";
                $hasInvalid = true;
                $validatedItems[] = $it;
                continue;
            }

            if (empty($it['link'])) {
                $it['is_valid'] = false;
                $it['error'] = "Target link cannot be empty";
                $hasInvalid = true;
                $validatedItems[] = $it;
                continue;
            }

            // Calculate cost in USD
            $srvBaseCurr = $srv['currency'] ?? 'USD';
            $rateUSD = convert_price($srv['rate'], $srvBaseCurr, 'USD');
            $cost = round(($rateUSD / 1000.0) * $it['quantity'], 4);

            $it['charge'] = $cost;
            $it['service_name'] = $srv['name'];
            $it['provider_id'] = $srv['provider_id'];
            $it['provider_service_id'] = $srv['provider_service_id'];
            $totalBatchCost += $cost;

            $validatedItems[] = $it;
        }

        // Check user balance
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
            $userCurr = $user['currency'] ?: 'USD';

            // Check if user has sufficient funds for the valid orders
            $validOrdersCost = 0.0;
            foreach ($validatedItems as $it) {
                if ($it['is_valid']) {
                    $validOrdersCost += $it['charge'];
                }
            }

            if ($validOrdersCost <= 0.0001) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => 'None of the provided orders could be validated.',
                    'items' => $validatedItems
                ];
            }

            if ($currentBalance < $validOrdersCost) {
                $db->rollBack();
                $neededFmt = format_price($validOrdersCost, $userCurr, 'USD');
                $haveFmt = format_price($currentBalance, $userCurr, 'USD');
                return [
                    'success' => false,
                    'error' => "Insufficient balance. Required: {$neededFmt}, Available: {$haveFmt}.",
                    'items' => $validatedItems
                ];
            }

            // Deduct balance
            $newBalance = round($currentBalance - $validOrdersCost, 4);
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

            // Cache active providers
            $pRows = $db->query("SELECT * FROM providers WHERE status = 'active'")->fetchAll();
            $providers = [];
            foreach ($pRows as $pr) {
                $providers[$pr['id']] = $pr;
            }

            $successfulCount = 0;
            $failedCount = 0;
            $orderResults = [];

            $insOrderStmt = $db->prepare("
                INSERT INTO orders (user_id, service_id, provider_id, provider_order_id, link, quantity, charge, start_count, remains, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending', NOW())
            ");

            foreach ($validatedItems as $it) {
                if (!$it['is_valid']) {
                    $failedCount++;
                    $orderResults[] = [
                        'line' => $it['line_number'],
                        'status' => 'failed',
                        'error' => $it['error'] ?? 'Validation error'
                    ];
                    continue;
                }

                // Call provider API if connected
                $providerOrderId = null;
                if (!empty($it['provider_id']) && !empty($it['provider_service_id']) && isset($providers[$it['provider_id']])) {
                    $provider = $providers[$it['provider_id']];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $provider['api_url']);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'key' => $provider['api_key'],
                        'action' => 'add',
                        'service' => $it['provider_service_id'],
                        'link' => $it['link'],
                        'quantity' => $it['quantity']
                    ]));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    curl_close($ch);

                    $resData = json_decode($res, true);
                    if ($resData && isset($resData['order'])) {
                        $providerOrderId = (string)$resData['order'];
                    }
                }

                // Insert into orders
                $insOrderStmt->execute([
                    $userId,
                    $it['service_id'],
                    $it['provider_id'] ?: null,
                    $providerOrderId,
                    $it['link'],
                    $it['quantity'],
                    $it['charge'],
                    $it['quantity']
                ]);
                $orderId = $db->lastInsertId();

                // Insert transaction record
                $db->prepare("
                    INSERT INTO transactions (user_id, order_id, type, amount, charge, currency, payment_method, transaction_id, status, created_at)
                    VALUES (?, ?, 'order', ?, 0.0000, 'USD', 'Wallet (Mass Order)', ?, 'completed', NOW())
                ")->execute([$userId, $orderId, $it['charge'], 'MASS-ORD-' . $orderId]);

                $successfulCount++;
                $orderResults[] = [
                    'line' => $it['line_number'],
                    'order_id' => $orderId,
                    'service_name' => $it['service_name'],
                    'quantity' => $it['quantity'],
                    'link' => $it['link'],
                    'charge' => $it['charge'],
                    'status' => 'success'
                ];
            }

            // Insert mass order batch record
            $batchStatus = ($failedCount === 0) ? 'completed' : ($successfulCount > 0 ? 'partial' : 'failed');
            $db->prepare("
                INSERT INTO mass_order_batches (user_id, total_orders, successful_orders, failed_orders, total_charge, currency, raw_input, results_summary, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'USD', ?, ?, ?, NOW())
            ")->execute([
                $userId,
                count($validatedItems),
                $successfulCount,
                $failedCount,
                $validOrdersCost,
                $rawInput,
                json_encode($orderResults),
                $batchStatus
            ]);
            $batchId = $db->lastInsertId();

            // Notify user
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'order')
            ")->execute([
                $userId,
                "Mass Order Batch #{$batchId} Processed",
                "Successfully placed {$successfulCount} orders. Total charge: " . format_price($validOrdersCost, $userCurr, 'USD')
            ]);

            $db->commit();

            return [
                'success' => true,
                'batch_id' => $batchId,
                'total_orders' => count($validatedItems),
                'successful_orders' => $successfulCount,
                'failed_orders' => $failedCount,
                'total_charge' => $validOrdersCost,
                'formatted_charge' => format_price($validOrdersCost, $userCurr, 'USD'),
                'new_balance' => format_price($newBalance, $userCurr, 'USD'),
                'results' => $orderResults
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Mass order processing error: ' . $e->getMessage()];
        }
    }
}
