<?php
require_once __DIR__ . '/../../config/database.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($ticketId > 0 && !empty($message)) {
    $db = getDB();
    $userId = $_SESSION['user_id'];

    // Verify ticket ownership
    $stmt = $db->prepare("SELECT id FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticketId, $userId]);
    if ($stmt->fetch()) {
        $db->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)")
            ->execute([$ticketId, $userId, $message]);
        $db->prepare("UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
    }
}

header("Location: /support?ticket_id=" . $ticketId);
exit;
