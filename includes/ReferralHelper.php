<?php
/**
 * RoseSMM - Referral & Earn Helper
 * Production-grade, atomic referral attribution, idempotent commission calculation,
 * real MySQL persistence, and theme-neutral data helpers.
 */

require_once __DIR__ . '/../config/database.php';

class ReferralHelper {

    /**
     * Check if the Refer & Earn program is currently active
     */
    public static function isProgramEnabled(): bool {
        return get_setting('referral_system_enabled', '1') === '1';
    }

    /**
     * Get configured referral commission percentage (e.g., 5.00)
     */
    public static function getCommissionPercent(): float {
        $pct = (float)get_setting('referral_commission_percent', '5.00');
        return ($pct > 0) ? $pct : 5.00;
    }

    /**
     * Get qualifying commission trigger event: 'order', 'deposit', or 'both'
     */
    public static function getCommissionEvent(): string {
        $event = strtolower(trim((string)get_setting('referral_commission_event', 'order')));
        return in_array($event, ['order', 'deposit', 'both'], true) ? $event : 'order';
    }

    /**
     * Generate a unique, readable referral code for a user
     */
    public static function generateUniqueReferralCode(int $userId, string $username = ''): string {
        $db = getDB();
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $username);
        $prefix = strtoupper(substr($cleanName, 0, 4));
        if (strlen($prefix) < 3) {
            $prefix = 'ROSE';
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $code = $prefix . $userId . $rand;

            // Check collision in MySQL
            $chk = $db->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
            $chk->execute([$code]);
            if (!$chk->fetch()) {
                return $code;
            }
        }

        // Fallback guaranteed unique code
        return 'REF' . $userId . strtoupper(substr(md5(uniqid((string)$userId, true)), 0, 6));
    }

    /**
     * Get user referral code (creates one automatically in MySQL if not present)
     */
    public static function getUserReferralCode(int $userId): string {
        $db = getDB();
        $stmt = $db->prepare("SELECT referral_code, username FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        if (!$u) {
            return '';
        }

        if (!empty($u['referral_code'])) {
            return $u['referral_code'];
        }

        // Generate and persist
        $code = self::generateUniqueReferralCode($userId, $u['username'] ?? '');
        $upd = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
        $upd->execute([$code, $userId]);

        return $code;
    }

    /**
     * Build the full unique referral link for a user
     */
    public static function getReferralUrl(int $userId): string {
        $code = self::getUserReferralCode($userId);
        if (empty($code)) {
            return '';
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:3000';
        return $protocol . $host . '/register?ref=' . urlencode($code);
    }

    /**
     * Find active referrer by referral code
     */
    public static function getReferrerByCode(string $code): ?array {
        $cleanCode = trim($code);
        if (empty($cleanCode)) {
            return null;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, full_name, role, status FROM users WHERE referral_code = ? LIMIT 1");
        $stmt->execute([$cleanCode]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active') {
            return $user;
        }

        return null;
    }

    /**
     * Permanently attribute a new user to their referrer server-side
     * Enforces anti-abuse:
     * - Self-referral prevention
     * - Loop prevention
     * - Existing attribution protection (cannot be manipulated or overwritten)
     */
    public static function attributeReferral(int $newUserId, string $referralCode): bool {
        $cleanCode = trim($referralCode);
        if (empty($cleanCode) || $newUserId <= 0) {
            return false;
        }

        $referrer = self::getReferrerByCode($cleanCode);
        if (!$referrer) {
            return false;
        }

        $referrerId = (int)$referrer['id'];

        // Self-referral prevention
        if ($referrerId === $newUserId) {
            return false;
        }

        $db = getDB();

        // Check if new user already has a referrer (immutability check)
        $chkUser = $db->prepare("SELECT id, referrer_id, username FROM users WHERE id = ? LIMIT 1");
        $chkUser->execute([$newUserId]);
        $targetUser = $chkUser->fetch();

        if (!$targetUser || !empty($targetUser['referrer_id'])) {
            return false;
        }

        // Loop prevention: ensure referrer's referrer is not the new user
        $chkLoop = $db->prepare("SELECT referrer_id FROM users WHERE id = ? LIMIT 1");
        $chkLoop->execute([$referrerId]);
        $parentRefId = (int)$chkLoop->fetchColumn();
        if ($parentRefId === $newUserId) {
            return false;
        }

        // Save permanent referral attribution
        $upd = $db->prepare("UPDATE users SET referrer_id = ? WHERE id = ? AND referrer_id IS NULL");
        $upd->execute([$referrerId, $newUserId]);

        if ($upd->rowCount() > 0) {
            // Send in-app notification to the referrer
            $newUsername = $targetUser['username'] ?? 'User';
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'New Referral Joined!', ?, 'promo')
            ")->execute([
                $referrerId,
                "A new user (@{$newUsername}) has registered using your referral link. You will earn commission on their qualifying activity!"
            ]);
            return true;
        }

        return false;
    }

    /**
     * ATOMIC, IDEMPOTENT COMMISSION PROCESSING
     * Calculates commission from REAL user activity (Order or Deposit).
     *
     * @param string $sourceType 'order' or 'deposit'
     * @param string $sourceId Related order ID or payment internal ID
     * @param int $payerUserId The user who made the transaction
     * @param float $amount Real base qualifying amount
     * @param string $currency Currency of the transaction (e.g. USD, INR)
     * @return array [success => bool, commission => float, message => string]
     */
    public static function processCommission(
        string $sourceType,
        string $sourceId,
        int $payerUserId,
        float $amount,
        string $currency = 'USD'
    ): array {
        $sourceType = strtolower(trim($sourceType));
        $sourceId = trim($sourceId);

        if (!in_array($sourceType, ['order', 'deposit'], true) || empty($sourceId) || $payerUserId <= 0 || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid commission parameters'];
        }

        // 1. Check if referral program is active
        if (!self::isProgramEnabled()) {
            return ['success' => false, 'error' => 'Refer & Earn program is currently paused by admin'];
        }

        // 2. Check if the event qualifies under admin settings
        $configuredEvent = self::getCommissionEvent();
        if ($configuredEvent !== 'both' && $configuredEvent !== $sourceType) {
            return ['success' => false, 'error' => "Commission not enabled for {$sourceType} events"];
        }

        $db = getDB();

        // 3. IDEMPOTENCY CHECK: has this source already generated a referral reward?
        $existing = $db->prepare("SELECT id FROM referral_transactions WHERE source_type = ? AND source_id = ? LIMIT 1");
        $existing->execute([$sourceType, $sourceId]);
        if ($existing->fetch()) {
            return [
                'success' => true, 
                'already_credited' => true, 
                'message' => "Commission for {$sourceType} #{$sourceId} was already awarded."
            ];
        }

        // 4. Look up payer's referrer
        $pStmt = $db->prepare("SELECT id, username, referrer_id, currency FROM users WHERE id = ? LIMIT 1");
        $pStmt->execute([$payerUserId]);
        $payer = $pStmt->fetch();

        if (!$payer || empty($payer['referrer_id'])) {
            return ['success' => false, 'error' => 'User does not have a referrer'];
        }

        $referrerId = (int)$payer['referrer_id'];

        // Prevent self-referral or invalid loop
        if ($referrerId === $payerUserId) {
            return ['success' => false, 'error' => 'Self-referral is forbidden'];
        }

        // 5. Verify referrer is active
        $rStmt = $db->prepare("SELECT id, username, balance, currency, status FROM users WHERE id = ? LIMIT 1");
        $rStmt->execute([$referrerId]);
        $referrer = $rStmt->fetch();

        if (!$referrer || $referrer['status'] !== 'active') {
            return ['success' => false, 'error' => 'Referrer account is not eligible or active'];
        }

        // 6. Calculate commission
        $commissionPercent = self::getCommissionPercent();
        if ($commissionPercent <= 0) {
            return ['success' => false, 'error' => 'Commission rate is zero'];
        }

        $baseAmount = round($amount, 4);
        $commissionAmount = round(($commissionPercent / 100.0) * $baseAmount, 4);

        if ($commissionAmount <= 0) {
            return ['success' => false, 'error' => 'Commission amount calculated to zero'];
        }

        // 7. Atomic Database Execution with row lock
        $db->beginTransaction();
        try {
            // Re-check with FOR UPDATE to prevent race conditions
            $lockChk = $db->prepare("SELECT id FROM referral_transactions WHERE source_type = ? AND source_id = ? FOR UPDATE");
            $lockChk->execute([$sourceType, $sourceId]);
            if ($lockChk->fetch()) {
                $db->rollBack();
                return ['success' => true, 'already_credited' => true, 'message' => 'Duplicate processing prevented'];
            }

            // Lock referrer user row
            $lockRef = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $lockRef->execute([$referrerId]);
            $currentRefBal = (float)$lockRef->fetchColumn();

            // Credit referrer's real wallet balance
            $newRefBal = $currentRefBal + $commissionAmount;
            $db->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newRefBal, $referrerId]);

            // Create ledger entry in main transactions table
            $txnRef = 'REF-' . strtoupper(substr($sourceType, 0, 3)) . '-' . $sourceId;
            $desc = "Referral Commission ({$commissionPercent}%) from @" . ($payer['username'] ?? 'user') . " ({$sourceType} #{$sourceId})";

            $insTxn = $db->prepare("
                INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id, currency, created_at)
                VALUES (?, ?, 'referral', ?, 'completed', ?, ?, NOW())
            ");
            $insTxn->execute([
                $referrerId,
                $commissionAmount,
                $desc,
                $txnRef,
                $currency
            ]);
            $walletTxnId = (int)$db->lastInsertId();

            // Insert into referral_transactions
            $insRef = $db->prepare("
                INSERT INTO referral_transactions (
                    referrer_id, referred_id, source_type, source_id, 
                    base_amount, commission_percent, commission_amount, 
                    currency, status, wallet_transaction_id, created_at
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, ?, 
                    ?, 'completed', ?, NOW()
                )
            ");
            $insRef->execute([
                $referrerId,
                $payerUserId,
                $sourceType,
                $sourceId,
                $baseAmount,
                $commissionPercent,
                $commissionAmount,
                $currency,
                $walletTxnId
            ]);
            $refTxnId = (int)$db->lastInsertId();

            // Send notification to the referrer
            $formattedComm = format_price($commissionAmount, $referrer['currency'] ?? 'USD', 'USD');
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Referral Commission Earned!', ?, 'wallet')
            ")->execute([
                $referrerId,
                "Congratulations! You earned {$formattedComm} in referral commission ({$commissionPercent}%) from @" . ($payer['username'] ?? 'referral') . " on their recent {$sourceType}."
            ]);

            $db->commit();

            return [
                'success' => true,
                'already_credited' => false,
                'referral_transaction_id' => $refTxnId,
                'commission_amount' => $commissionAmount,
                'currency' => $currency,
                'referrer_id' => $referrerId,
                'new_balance' => $newRefBal
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Commission processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user-specific referral statistics from real MySQL data
     */
    public static function getUserStats(int $userId): array {
        $db = getDB();

        // 1. Total referred users count
        $refCountStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE referrer_id = ?");
        $refCountStmt->execute([$userId]);
        $totalReferrals = (int)$refCountStmt->fetchColumn();

        // 2. Active referrals (users who have placed at least 1 order or deposit)
        $activeRefStmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) 
            FROM users u
            WHERE u.referrer_id = ? 
              AND (EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)
                   OR EXISTS (SELECT 1 FROM payments p WHERE p.user_id = u.id AND p.status = 'SUCCESS'))
        ");
        $activeRefStmt->execute([$userId]);
        $activeReferrals = (int)$activeRefStmt->fetchColumn();

        // 3. Total commission earned
        $earnStmt = $db->prepare("
            SELECT COALESCE(SUM(commission_amount), 0) 
            FROM referral_transactions 
            WHERE referrer_id = ? AND status = 'completed'
        ");
        $earnStmt->execute([$userId]);
        $totalEarnings = (float)$earnStmt->fetchColumn();

        // 4. List of referred users
        $usersStmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.created_at, u.status,
                   (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS total_orders,
                   (SELECT COALESCE(SUM(commission_amount), 0) 
                    FROM referral_transactions 
                    WHERE referrer_id = ? AND referred_id = u.id AND status = 'completed') AS commission_generated
            FROM users u
            WHERE u.referrer_id = ?
            ORDER BY u.id DESC
            LIMIT 50
        ");
        $usersStmt->execute([$userId, $userId]);
        $referredUsers = $usersStmt->fetchAll();

        // 5. Recent referral commission transactions
        $txStmt = $db->prepare("
            SELECT rt.*, u.username AS referred_username, u.full_name AS referred_full_name
            FROM referral_transactions rt
            LEFT JOIN users u ON rt.referred_id = u.id
            WHERE rt.referrer_id = ?
            ORDER BY rt.id DESC
            LIMIT 50
        ");
        $txStmt->execute([$userId]);
        $transactions = $txStmt->fetchAll();

        return [
            'total_referrals' => $totalReferrals,
            'active_referrals' => $activeReferrals,
            'total_earnings' => $totalEarnings,
            'referred_users' => $referredUsers,
            'transactions' => $transactions,
            'is_enabled' => self::isProgramEnabled(),
            'commission_percent' => self::getCommissionPercent(),
            'commission_event' => self::getCommissionEvent()
        ];
    }

    /**
     * Get admin-level referral statistics from real MySQL data
     */
    public static function getAdminStats(): array {
        $db = getDB();

        // Total referred users
        $totalReferrals = (int)$db->query("SELECT COUNT(*) FROM users WHERE referrer_id IS NOT NULL")->fetchColumn();

        // Total active referrers
        $activeReferrers = (int)$db->query("SELECT COUNT(DISTINCT referrer_id) FROM users WHERE referrer_id IS NOT NULL")->fetchColumn();

        // Total commission paid out
        $totalCommissionPaid = (float)$db->query("SELECT COALESCE(SUM(commission_amount), 0) FROM referral_transactions WHERE status = 'completed'")->fetchColumn();

        // Total referral transactions
        $totalTransactions = (int)$db->query("SELECT COUNT(*) FROM referral_transactions")->fetchColumn();

        return [
            'total_referrals' => $totalReferrals,
            'active_referrers' => $activeReferrers,
            'total_commission_paid' => $totalCommissionPaid,
            'total_transactions' => $totalTransactions,
            'is_enabled' => self::isProgramEnabled(),
            'commission_percent' => self::getCommissionPercent(),
            'commission_event' => self::getCommissionEvent()
        ];
    }
}
