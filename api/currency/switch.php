<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$currencyCode = strtoupper(trim($input['currency'] ?? 'USD'));

$db = getDB();
$cStmt = $db->prepare("SELECT code FROM currencies WHERE code = ? AND status = 'active'");
$cStmt->execute([$currencyCode]);
if ($cStmt->fetch()) {
    $_SESSION['user_currency'] = $currencyCode;
    if (is_logged_in() && !empty($_SESSION['user_id'])) {
        $db->prepare("UPDATE users SET currency = ? WHERE id = ?")->execute([$currencyCode, $_SESSION['user_id']]);
    }
    session_write_close();
    echo json_encode(['success' => true, 'currency' => $currencyCode]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid currency selection.']);
}
