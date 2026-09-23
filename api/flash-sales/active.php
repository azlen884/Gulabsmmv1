<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/FlashSaleHelper.php';

header('Content-Type: application/json');

$sales = FlashSaleHelper::getActiveFlashSales();
echo json_encode(['success' => true, 'sales' => $sales]);
