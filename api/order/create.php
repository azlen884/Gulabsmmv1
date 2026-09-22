<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$serviceId = isset($input['service_id']) ? (int)$input['service_id'] : 0;
$link = isset($input['link']) ? trim($input['link']) : '';
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
$runs = isset($input['runs']) ? (int)$input['runs'] : 1;
$interval = isset($input['interval']) ? (int)$input['interval'] : 0;

if ($serviceId <= 0 || empty($link) || $quantity <= 0) {
    echo json_encode(['success' => false, 'error' => 'Please provide valid service, link and quantity.']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Get Service
$sStmt = $db->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
$sStmt->execute([$serviceId]);
$service = $sStmt->fetch();

if (!$service) {
    echo json_encode(['success' => false, 'error' => 'Selected service is currently unavailable.']);
    exit;
}

$minQty = isset($service['min_quantity']) ? (int)$service['min_quantity'] : (int)($service['min'] ?? 10);
$maxQty = isset($service['max_quantity']) ? (int)$service['max_quantity'] : (int)($service['max'] ?? 100000);

if ($quantity < $minQty || $quantity > $maxQty) {
    echo json_encode(['success' => false, 'error' => "Quantity must be between $minQty and $maxQty."]);
    exit;
}

// Calculate total charge in base currency (USD)
$serviceBaseCurr = $service['currency'] ?? 'USD';
$serviceRateInUSD = convert_price($service['rate'], $serviceBaseCurr, 'USD');
$totalQuantity = $quantity * max(1, $runs);
$charge = round(($serviceRateInUSD / 1000.0) * $totalQuantity, 4);

$userCurrency = get_user_currency();
$formattedCharge = format_price($charge, $userCurrency, 'USD');

// Check user balance
$uStmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
$db->beginTransaction();
$uStmt->execute([$userId]);
$currentBalance = (float)$uStmt->fetchColumn();

if ($currentBalance < $charge) {
    $db->rollBack();
    $currentBalanceFormatted = format_price($currentBalance, $userCurrency, 'USD');
    echo json_encode([
        'success' => false, 
        'error' => "Insufficient wallet balance. Total cost is $formattedCharge, but your current balance is $currentBalanceFormatted. Please add funds to place this order."
    ]);
    exit;
}

// Deduct user balance
$newBalance = $currentBalance - $charge;
$db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);

// Provider automation handling (if linked to upstream API)
$providerOrderId = null;
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
            'link' => $link,
            'quantity' => $quantity,
            'runs' => $runs,
            'interval' => $interval
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
}

// Insert into orders
$insOrder = $db->prepare("
    INSERT INTO orders (user_id, service_id, provider_id, provider_order_id, link, quantity, charge, start_count, remains, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending')
");
$insOrder->execute([
    $userId,
    $serviceId,
    $service['provider_id'] ?: null,
    $providerOrderId,
    $link,
    $totalQuantity,
    $charge,
    $totalQuantity
]);
$orderId = $db->lastInsertId();

// Record order transaction
$db->prepare("
    INSERT INTO transactions (user_id, order_id, amount, type, payment_method, status, transaction_id)
    VALUES (?, ?, ?, 'order', 'Wallet Balance', 'completed', ?)
")->execute([$userId, $orderId, $charge, 'ORD-' . $orderId]);

// Insert customer notification
$db->prepare("
    INSERT INTO notifications (user_id, title, message, type)
    VALUES (?, ?, ?, 'order')
")->execute([
    $userId,
    "Order #$orderId Received",
    "Your order for {$service['name']} (Quantity: " . number_format($totalQuantity) . ") is being processed."
]);

$db->commit();

// Process real referral commission if eligible
require_once __DIR__ . '/../../includes/ReferralHelper.php';
ReferralHelper::processCommission('order', (string)$orderId, (int)$userId, (float)$charge, 'USD');

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'charge' => $charge,
    'converted_charge' => convert_price($charge, 'USD', $userCurrency),
    'formatted_charge' => $formattedCharge,
    'balance' => $newBalance,
    'new_balance' => format_price($newBalance, $userCurrency, 'USD'),
    'message' => "Order #$orderId has been placed successfully! Total cost: $formattedCharge"
]);
