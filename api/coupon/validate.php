<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/CouponHelper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['valid' => false, 'error' => 'Please log in to apply coupons.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$code = trim($input['code'] ?? '');
$serviceId = (int)($input['service_id'] ?? 0);
$quantity = (int)($input['quantity'] ?? 0);
$userId = $_SESSION['user_id'];

if (empty($code)) {
    echo json_encode(['valid' => false, 'error' => 'Please enter coupon code.']);
    exit;
}

// Calculate subtotal in USD
$db = getDB();
$sStmt = $db->prepare("SELECT * FROM services WHERE id = ?");
$sStmt->execute([$serviceId]);
$service = $sStmt->fetch();

if (!$service) {
    echo json_encode(['valid' => false, 'error' => 'Please select a valid service first.']);
    exit;
}

$srvBaseCurr = $service['currency'] ?? 'USD';
$rateUSD = convert_price($service['rate'], $srvBaseCurr, 'USD');
$subtotalUSD = round(($rateUSD / 1000.0) * max(1, $quantity), 4);

$result = CouponHelper::validateCoupon($code, $userId, $subtotalUSD, $serviceId, $service['category_id']);
echo json_encode($result);
