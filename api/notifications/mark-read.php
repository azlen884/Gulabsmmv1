<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = current_user();
$userId = $user['id'];
$db = getDB();

$notifId = $_POST['id'] ?? $_GET['id'] ?? 'all';

if ($notifId === 'all') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
    $stmt->execute([$userId]);
} else {
    $notifIdInt = (int)$notifId;
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$notifIdInt, $userId]);
}

// Get updated unread count
$countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
$countStmt->execute([$userId]);
$newUnreadCount = (int)$countStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'unread_count' => $newUnreadCount
]);
