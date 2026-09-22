<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
$method = isset($input['payment_method']) ? trim($input['payment_method']) : 'Credit Card';

if ($amount < 5.0) {
    echo json_encode(['success' => false, 'error' => 'Minimum deposit amount is $5.00.']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Get deposit bonus setting
$bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
$bonusAmount = ($bonusPercent / 100.0) * $amount;
$totalCredit = $amount + $bonusAmount;

$db->beginTransaction();

// Update user balance
$db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalCredit, $userId]);

// Log transaction for principal deposit
$txId = 'TXN-' . strtoupper(bin2hex(random_bytes(5)));
$db->prepare("
    INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id)
    VALUES (?, ?, 'deposit', ?, 'completed', ?)
")->execute([$userId, $amount, $method, $txId]);

// Log transaction for bonus if any
if ($bonusAmount > 0) {
    $db->prepare("
        INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id)
        VALUES (?, ?, 'bonus', 'Deposit Bonus (10%)', 'completed', ?)
    ")->execute([$userId, $bonusAmount, 'BONUS-' . $txId]);
}

// Notification
$db->prepare("
    INSERT INTO notifications (user_id, title, message, type)
    VALUES (?, 'Funds Added Successfully', ?, 'wallet')
")->execute([
    $userId,
    "Successfully deposited $" . number_format($amount, 2) . " via $method with a $" . number_format($bonusAmount, 2) . " bonus!"
]);

// Get updated balance
$uStmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$newBalance = (float)$uStmt->fetchColumn();

$db->commit();

// Process real referral commission if eligible
require_once __DIR__ . '/../../includes/ReferralHelper.php';
ReferralHelper::processCommission('deposit', $txId, (int)$userId, (float)$amount, 'USD');

echo json_encode([
    'success' => true,
    'balance' => $newBalance,
    'credited' => $totalCredit,
    'bonus' => $bonusAmount,
    'message' => "Successfully credited $" . number_format($totalCredit, 2) . " to your wallet!"
]);
