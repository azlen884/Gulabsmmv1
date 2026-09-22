<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$key = $_REQUEST['key'] ?? '';

$db = getDB();

if ($action === 'services') {
    $services = $db->query("
        SELECT s.id AS service, s.name, c.name AS category, s.rate, s.min_quantity AS min, s.max_quantity AS max, s.type, s.description
        FROM services s 
        JOIN categories c ON s.category_id = c.id 
        WHERE s.status = 'active'
        ORDER BY s.sort_order ASC
    ")->fetchAll();
    echo json_encode($services);
    exit;
}

if (empty($key)) {
    echo json_encode(['error' => 'API key is required']);
    exit;
}

// Authenticate API Key
$uStmt = $db->prepare("SELECT * FROM users WHERE api_key = ? AND status = 'active' LIMIT 1");
$uStmt->execute([$key]);
$apiUser = $uStmt->fetch();

if (!$apiUser) {
    echo json_encode(['error' => 'Invalid or inactive API key']);
    exit;
}

if ($action === 'balance') {
    echo json_encode([
        'balance' => number_format($apiUser['balance'], 4, '.', ''),
        'currency' => $apiUser['currency'] ?: 'USD'
    ]);
    exit;
}

if ($action === 'add') {
    $serviceId = (int)($_REQUEST['service'] ?? 0);
    $link = trim($_REQUEST['link'] ?? '');
    $quantity = (int)($_REQUEST['quantity'] ?? 0);

    if ($serviceId <= 0 || empty($link) || $quantity <= 0) {
        echo json_encode(['error' => 'Incorrect parameters: service, link and quantity are required.']);
        exit;
    }

    $sStmt = $db->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
    $sStmt->execute([$serviceId]);
    $service = $sStmt->fetch();

    if (!$service) {
        echo json_encode(['error' => 'Service not found or inactive']);
        exit;
    }

    $minQty = isset($service['min_quantity']) ? (int)$service['min_quantity'] : (int)($service['min'] ?? 10);
    $maxQty = isset($service['max_quantity']) ? (int)$service['max_quantity'] : (int)($service['max'] ?? 100000);

    if ($quantity < $minQty || $quantity > $maxQty) {
        echo json_encode(['error' => "Quantity must be between $minQty and $maxQty"]);
        exit;
    }

    $charge = ($service['rate'] / 1000.0) * $quantity;

    if ($apiUser['balance'] < $charge) {
        echo json_encode(['error' => 'Not enough balance']);
        exit;
    }

    $db->beginTransaction();
    $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$charge, $apiUser['id']]);

    $insOrder = $db->prepare("
        INSERT INTO orders (user_id, service_id, provider_id, link, quantity, charge, start_count, remains, status)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'pending')
    ");
    $insOrder->execute([
        $apiUser['id'],
        $serviceId,
        $service['provider_id'] ?: null,
        $link,
        $quantity,
        $charge,
        $quantity
    ]);
    $orderId = $db->lastInsertId();

    $db->prepare("
        INSERT INTO transactions (user_id, order_id, amount, type, payment_method, status, transaction_id)
        VALUES (?, ?, ?, 'order', 'API Automated', 'completed', ?)
    ")->execute([$apiUser['id'], $orderId, $charge, 'API-ORD-' . $orderId]);

    $db->commit();

    echo json_encode(['order' => (int)$orderId]);
    exit;
}

if ($action === 'status') {
    $orderId = (int)($_REQUEST['order'] ?? 0);
    if ($orderId <= 0) {
        echo json_encode(['error' => 'Order ID is required']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $apiUser['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    echo json_encode([
        'charge' => number_format($order['charge'], 4, '.', ''),
        'start_count' => (string)$order['start_count'],
        'status' => ucfirst($order['status']),
        'remains' => (string)$order['remains'],
        'currency' => 'USD'
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid action parameter']);
