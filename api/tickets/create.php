<?php
require_once __DIR__ . '/../../config/database.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$priority = trim($_POST['priority'] ?? 'medium');
$message = trim($_POST['message'] ?? '');

if (!empty($subject) && !empty($message)) {
    $db = getDB();
    $userId = $_SESSION['user_id'];

    $db->prepare("INSERT INTO tickets (user_id, subject, priority, status) VALUES (?, ?, ?, 'open')")
        ->execute([$userId, $subject, $priority]);
    $ticketId = $db->lastInsertId();

    $db->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)")
        ->execute([$ticketId, $userId, $message]);

    // Trigger Ticket Automation Rules (auto-reply, auto-tag priority/status)
    require_once __DIR__ . '/../../includes/TicketAutomationHelper.php';
    TicketAutomationHelper::processEvent($ticketId, 'ticket_created', $message, $priority, $subject);

    header("Location: /support?ticket_id=" . $ticketId);
    exit;
}

header("Location: /support");
exit;
