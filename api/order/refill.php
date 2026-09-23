<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/RefillHelper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orderId = (int)($input['order_id'] ?? 0);
$userId = $_SESSION['user_id'];
$isAdmin = is_admin();

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid order ID is required.']);
    exit;
}

$res = RefillHelper::requestRefill($orderId, $isAdmin ? 0 : $userId, false);
echo json_encode($res);
