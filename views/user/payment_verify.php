<?php
require_once __DIR__ . '/../../config/database.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$gatewayCode = trim($_GET['gateway'] ?? '');
$sessionId = trim($_GET['session_id'] ?? '');
$txnRecordId = (int)($_GET['txn_id'] ?? 0);
$paypalToken = trim($_GET['token'] ?? '');

$verified = false;
$alreadyCredited = false;
$statusMessage = '';
$creditedAmount = 0.0;
$bonusCredited = 0.0;
$newBalance = 0.0;
$txnDisplayId = '';
$gatewayName = 'Online Gateway';

try {
    // -------------------------------------------------------------
    // STRIPE VERIFICATION
    // -------------------------------------------------------------
    if ($gatewayCode === 'stripe') {
        if (empty($sessionId)) {
            throw new Exception("Missing Stripe Checkout Session ID.");
        }

        // Fetch gateway config
        $gw = $db->query("SELECT * FROM payment_gateways WHERE code = 'stripe' LIMIT 1")->fetch();
        if (!$gw || empty($gw['secret_key'])) {
            throw new Exception("Stripe gateway configuration not found.");
        }

        $gatewayName = $gw['name'];

        // Find transaction
        $tStmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id = ? OR (gateway_code = 'stripe' AND user_id = ? AND status = 'pending') ORDER BY id DESC LIMIT 1");
        $tStmt->execute([$sessionId, $userId]);
        $txn = $tStmt->fetch();

        if (!$txn) {
            throw new Exception("Payment record not found in system.");
        }

        $txnDisplayId = $txn['transaction_id'];
        $creditedAmount = (float)$txn['amount'];

        // Duplicate payment protection
        if ($txn['status'] === 'completed') {
            $verified = true;
            $alreadyCredited = true;
            $statusMessage = "This payment was already verified and credited to your wallet balance.";
            $newBalance = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
        } else {
            // Verify session with Stripe API directly
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $gw['secret_key']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception("Stripe connection error: " . $curlError);
            }

            $session = json_decode($res, true);

            if ($httpCode === 200 && ($session['payment_status'] ?? '') === 'paid') {
                // Verified real payment!
                $db->beginTransaction();

                // Bonus calculation
                $bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
                $bonusCredited = round(($bonusPercent / 100.0) * $creditedAmount, 2);
                $totalToCredit = $creditedAmount + $bonusCredited;

                // Credit user balance
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalToCredit, $userId]);

                // Update transaction status
                $db->prepare("
                    UPDATE transactions 
                    SET status = 'completed', transaction_id = ?, gateway_response = ?, updated_at = NOW() 
                    WHERE id = ?
                ")->execute([$sessionId, json_encode(['payment_intent' => $session['payment_intent'] ?? '', 'status' => $session['payment_status']]), $txn['id']]);

                // Bonus transaction if applicable
                if ($bonusCredited > 0) {
                    $db->prepare("
                        INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id, created_at)
                        VALUES (?, ?, 'bonus', 'Deposit Bonus (10%)', 'completed', ?, NOW())
                    ")->execute([$userId, $bonusCredited, 'BONUS-' . $sessionId]);
                }

                // Add notification
                $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'Funds Added Successfully', ?, 'wallet')
                ")->execute([
                    $userId,
                    "Successfully credited \${$creditedAmount} via Stripe with \${$bonusCredited} bonus."
                ]);

                $db->commit();

                $verified = true;
                $statusMessage = "Your payment of $" . number_format($creditedAmount, 2) . " has been verified by Stripe and added to your balance.";
                $newBalance = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
            } else {
                // Payment was not completed or failed on Stripe
                $db->prepare("UPDATE transactions SET status = 'failed', gateway_response = ? WHERE id = ?")
                   ->execute([$res, $txn['id']]);
                throw new Exception("Payment was not completed by Stripe (Status: " . ($session['payment_status'] ?? 'unknown') . "). No funds were credited.");
            }
        }
    }

    // -------------------------------------------------------------
    // PAYPAL VERIFICATION
    // -------------------------------------------------------------
    elseif ($gatewayCode === 'paypal') {
        $orderId = !empty($paypalToken) ? $paypalToken : '';
        
        $gw = $db->query("SELECT * FROM payment_gateways WHERE code = 'paypal' LIMIT 1")->fetch();
        if (!$gw || empty($gw['api_key']) || empty($gw['secret_key'])) {
            throw new Exception("PayPal gateway credentials not found.");
        }

        $gatewayName = $gw['name'];

        // Find transaction
        $tStmt = $db->prepare("SELECT * FROM transactions WHERE (transaction_id = ? OR id = ?) AND user_id = ? LIMIT 1");
        $tStmt->execute([$orderId, $txnRecordId, $userId]);
        $txn = $tStmt->fetch();

        if (!$txn) {
            throw new Exception("PayPal transaction record not found.");
        }

        $txnDisplayId = $txn['transaction_id'];
        $creditedAmount = (float)$txn['amount'];

        if ($txn['status'] === 'completed') {
            $verified = true;
            $alreadyCredited = true;
            $statusMessage = "This PayPal payment was already verified and credited to your wallet balance.";
            $newBalance = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
        } else {
            $isLive = $gw['mode'] === 'live';
            $paypalBase = $isLive ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

            // 1. Get OAuth Access Token
            $ch = curl_init($paypalBase . '/v1/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_USERPWD, $gw['api_key'] . ':' . $gw['secret_key']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $authRes = curl_exec($ch);
            curl_close($ch);
            $accessToken = json_decode($authRes, true)['access_token'] ?? null;

            if (!$accessToken) {
                throw new Exception("Unable to authenticate with PayPal API.");
            }

            // 2. Capture PayPal Order
            $targetOrderId = !empty($txn['transaction_id']) ? $txn['transaction_id'] : $orderId;
            $ch = curl_init($paypalBase . '/v2/checkout/orders/' . urlencode($targetOrderId) . '/capture');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $capRes = curl_exec($ch);
            $capHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $capData = json_decode($capRes, true);

            if (($capHttp === 200 || $capHttp === 201) && ($capData['status'] ?? '') === 'COMPLETED') {
                $db->beginTransaction();

                $bonusPercent = (float)get_setting('deposit_bonus_percent', '10');
                $bonusCredited = round(($bonusPercent / 100.0) * $creditedAmount, 2);
                $totalToCredit = $creditedAmount + $bonusCredited;

                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalToCredit, $userId]);
                $db->prepare("UPDATE transactions SET status = 'completed', gateway_response = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$capRes, $txn['id']]);

                if ($bonusCredited > 0) {
                    $db->prepare("
                        INSERT INTO transactions (user_id, amount, type, payment_method, status, transaction_id, created_at)
                        VALUES (?, ?, 'bonus', 'Deposit Bonus (10%)', 'completed', ?, NOW())
                    ")->execute([$userId, $bonusCredited, 'BONUS-' . $targetOrderId]);
                }

                $db->commit();
                $verified = true;
                $statusMessage = "Your PayPal payment of $" . number_format($creditedAmount, 2) . " has been verified and added to your wallet.";
                $newBalance = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
            } else {
                $db->prepare("UPDATE transactions SET status = 'failed', gateway_response = ? WHERE id = ?")
                   ->execute([$capRes, $txn['id']]);
                throw new Exception("PayPal payment verification did not complete. Status: " . ($capData['status'] ?? 'Incomplete'));
            }
        }
    } else {
        // Generic gateway or manual
        if ($txnRecordId > 0) {
            $tStmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ? LIMIT 1");
            $tStmt->execute([$txnRecordId, $userId]);
            $txn = $tStmt->fetch();
            if ($txn && $txn['status'] === 'completed') {
                $verified = true;
                $statusMessage = "Your deposit of $" . number_format($txn['amount'], 2) . " is verified.";
                $creditedAmount = (float)$txn['amount'];
                $txnDisplayId = $txn['transaction_id'];
                $newBalance = (float)$db->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();
            } else {
                throw new Exception("Payment status is currently pending verification.");
            }
        } else {
            throw new Exception("Invalid payment verification request.");
        }
    }

} catch (Throwable $e) {
    $verified = false;
    $statusMessage = $e->getMessage();
}

$pageTitle = $verified ? 'Payment Successful - RoseSMM' : 'Payment Verification Failed - RoseSMM';
$activePage = 'wallet';
require_once __DIR__ . '/../layouts/user_header.php';
?>

<div class="max-w-xl mx-auto py-6">
  <?php if ($verified): ?>
    <!-- Verification Success Card -->
    <div class="bg-white rounded-3xl border border-emerald-100 p-8 shadow-sm text-center">
      <div class="w-16 h-16 rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 border border-emerald-100 shadow-sm">
        <i data-lucide="check" class="w-8 h-8 stroke-[3]"></i>
      </div>

      <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold uppercase tracking-wider inline-block mb-2">
        Payment Verified
      </span>

      <h1 class="text-2xl font-black text-slate-800 tracking-tight mb-2">Funds Credited Successfully!</h1>
      <p class="text-xs text-slate-500 leading-relaxed mb-6 max-w-md mx-auto">
        <?= e($statusMessage) ?>
      </p>

      <!-- Deposit Breakdown -->
      <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 mb-6 text-xs text-left space-y-2">
        <div class="flex items-center justify-between text-slate-500">
          <span>Payment Method:</span>
          <span class="font-bold text-slate-800"><?= e($gatewayName) ?></span>
        </div>
        <div class="flex items-center justify-between text-slate-500">
          <span>Deposit Amount:</span>
          <span class="font-extrabold text-slate-800">$<?= number_format($creditedAmount, 2) ?></span>
        </div>
        <?php if ($bonusCredited > 0): ?>
          <div class="flex items-center justify-between text-emerald-600 font-bold">
            <span>Special 10% Bonus:</span>
            <span>+$<?= number_format($bonusCredited, 2) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex items-center justify-between text-slate-500 pt-1 border-t border-slate-200">
          <span>Transaction ID:</span>
          <span class="font-mono text-slate-700 text-[11px] truncate max-w-[200px]"><?= e($txnDisplayId) ?></span>
        </div>
        <div class="flex items-center justify-between text-slate-800 font-black text-sm pt-1 border-t border-slate-200">
          <span>Updated Wallet Balance:</span>
          <span class="text-rose-600 text-base">$<?= number_format($newBalance, 2) ?></span>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="/order" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2">
          <i data-lucide="shopping-cart" class="w-4 h-4"></i>
          <span>Place New Order</span>
        </a>
        <a href="/wallet" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-2">
          <i data-lucide="wallet" class="w-4 h-4"></i>
          <span>View Wallet & History</span>
        </a>
      </div>
    </div>

  <?php else: ?>
    <!-- Verification Failure Card -->
    <div class="bg-white rounded-3xl border border-rose-100 p-8 shadow-sm text-center">
      <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 border border-rose-100 shadow-sm">
        <i data-lucide="alert-triangle" class="w-8 h-8"></i>
      </div>

      <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-700 text-xs font-bold uppercase tracking-wider inline-block mb-2">
        Verification Incomplete
      </span>

      <h1 class="text-2xl font-black text-slate-800 tracking-tight mb-2">Payment Not Completed</h1>
      <p class="text-xs text-rose-600 leading-relaxed mb-6 max-w-md mx-auto font-medium">
        <?= e($statusMessage) ?>
      </p>

      <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs text-slate-500 mb-6 text-left">
        <p class="font-bold text-slate-700 mb-1">What happened?</p>
        <p class="leading-relaxed">
          No funds were credited to your wallet because the transaction could not be confirmed by the payment provider. If money was deducted from your bank or card, please contact support with your payment receipt.
        </p>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="/add-funds" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i>
          <span>Try Another Payment</span>
        </a>
        <a href="/support" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-2">
          <i data-lucide="life-buoy" class="w-4 h-4"></i>
          <span>Contact Support</span>
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
