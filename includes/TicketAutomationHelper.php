<?php
/**
 * RoseSMM - Ticket Automation Engine
 * Real automated replies, SLA rules, priority triggers and status transitions.
 */

require_once __DIR__ . '/../config/database.php';

class TicketAutomationHelper {

    /**
     * Trigger automation rules for a ticket event
     *
     * @param int $ticketId
     * @param string $eventType 'ticket_created' | 'ticket_replied' | 'status_changed'
     * @param string $content Message text or subject
     * @param string $priority Ticket priority ('low', 'medium', 'high')
     * @param string $subject Ticket subject
     * @return array Array of executed actions
     */
    public static function processEvent($ticketId, $eventType = 'ticket_created', $content = '', $priority = 'medium', $subject = '') {
        $db = getDB();
        $executed = [];

        // Check if ticket automation is globally enabled
        $globEnabled = get_setting('ticket_automation_enabled', '1');
        if ($globEnabled !== '1') {
            return $executed;
        }

        // Fetch active rules ordered by rule_priority ASC
        $stmt = $db->prepare("
            SELECT * FROM ticket_automation_rules 
            WHERE is_enabled = 1 AND trigger_event = ? 
            ORDER BY rule_priority ASC, id ASC
        ");
        $stmt->execute([$eventType]);
        $rules = $stmt->fetchAll();

        if (empty($rules)) {
            return $executed;
        }

        // Fetch current ticket
        $tStmt = $db->prepare("SELECT * FROM tickets WHERE id = ?");
        $tStmt->execute([$ticketId]);
        $ticket = $tStmt->fetch();

        if (!$ticket) {
            return $executed;
        }

        $searchText = strtolower($subject . ' ' . $content);

        foreach ($rules as $rule) {
            $isMatch = true;

            // 1. Priority match
            if (!empty($rule['priority_filter']) && $rule['priority_filter'] !== 'all') {
                if (strtolower($ticket['priority']) !== strtolower($rule['priority_filter'])) {
                    $isMatch = false;
                }
            }

            // 2. Keyword match
            if ($isMatch && !empty($rule['keyword_contains'])) {
                $keywords = array_filter(array_map('trim', explode(',', strtolower($rule['keyword_contains']))));
                if (!empty($keywords)) {
                    $matchCount = 0;
                    foreach ($keywords as $kw) {
                        if (strpos($searchText, $kw) !== false) {
                            $matchCount++;
                        }
                    }

                    if ($rule['condition_match_type'] === 'all') {
                        if ($matchCount < count($keywords)) {
                            $isMatch = false;
                        }
                    } else { // 'any'
                        if ($matchCount === 0) {
                            $isMatch = false;
                        }
                    }
                }
            }

            if (!$isMatch) {
                continue;
            }

            // Rule matched! Execute actions
            $actionLogs = [];

            // Action A: Automatic reply
            if (!empty($rule['action_auto_reply']) && !empty($rule['reply_message'])) {
                $replyText = trim($rule['reply_message']);
                // Check if identical auto reply was already sent recently to avoid duplicates
                $checkDupe = $db->prepare("
                    SELECT id FROM ticket_messages 
                    WHERE ticket_id = ? AND is_admin = 1 AND message = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ");
                $checkDupe->execute([$ticketId, $replyText]);
                if (!$checkDupe->fetch()) {
                    $insMsg = $db->prepare("
                        INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin, created_at)
                        VALUES (?, 1, ?, 1, NOW())
                    ");
                    $insMsg->execute([$ticketId, $replyText]);
                    $actionLogs[] = "Sent automated reply";
                }
            }

            // Action B: Status change
            if (!empty($rule['action_change_status'])) {
                $validStatuses = ['open', 'answered', 'closed'];
                if (in_array($rule['action_change_status'], $validStatuses)) {
                    $db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$rule['action_change_status'], $ticketId]);
                    $ticket['status'] = $rule['action_change_status'];
                    $actionLogs[] = "Changed status to '{$rule['action_change_status']}'";
                }
            }

            // Action C: Priority change
            if (!empty($rule['action_change_priority'])) {
                $validPriorities = ['low', 'medium', 'high'];
                if (in_array($rule['action_change_priority'], $validPriorities)) {
                    $db->prepare("UPDATE tickets SET priority = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$rule['action_change_priority'], $ticketId]);
                    $ticket['priority'] = $rule['action_change_priority'];
                    $actionLogs[] = "Changed priority to '{$rule['action_change_priority']}'";
                }
            }

            if (!empty($actionLogs)) {
                $logText = implode('; ', $actionLogs);
                $db->prepare("
                    INSERT INTO ticket_automation_logs (rule_id, ticket_id, trigger_event, action_taken, status, created_at)
                    VALUES (?, ?, ?, ?, 'success', NOW())
                ")->execute([$rule['id'], $ticketId, $eventType, $logText]);

                $executed[] = [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'actions' => $actionLogs
                ];
            }
        }

        return $executed;
    }

    /**
     * Sweep tickets for stale answered tickets (Auto-close after 72h)
     */
    public static function sweepInactiveTickets() {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id FROM tickets 
            WHERE status = 'answered' AND updated_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ");
        $stmt->execute();
        $staleTickets = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $closedCount = 0;
        foreach ($staleTickets as $tid) {
            $db->prepare("UPDATE tickets SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$tid]);
            $db->prepare("
                INSERT INTO ticket_automation_logs (rule_id, ticket_id, trigger_event, action_taken, status, created_at)
                VALUES (NULL, ?, 'status_changed', 'Auto-closed due to 72-hour inactivity', 'success', NOW())
            ")->execute([$tid]);
            $closedCount++;
        }

        return $closedCount;
    }
}
