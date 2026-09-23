<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/MassOrderHelper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ordersText = trim($input['orders_text'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($ordersText)) {
    echo json_encode(['success' => false, 'error' => 'Please provide mass order input.']);
    exit;
}

$res = MassOrderHelper::processMassOrder($userId, $ordersText);
echo json_encode($res);
