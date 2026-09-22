<?php
/**
 * RoseSMM - Secure Payment Checkout Page
 * Handles official standard checkouts for Razorpay, Cashfree, PayU, and PhonePe.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/payments/PaymentHelper.php';
require_once __DIR__ . '/../../includes/payments/RazorpayService.php';
require_once __DIR__ . '/../../includes/payments/CashfreeService.php';
require_once __DIR__ . '/../../includes/payments/PhonePeService.php';
require_once __DIR__ . '/../../includes/payments/PayUService.php';

if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$internalId = trim($_GET['internal_id'] ?? '');

if (empty($internalId)) {
    header("Location: /add-funds");
    exit;
}

$payment = PaymentHelper::getPaymentByInternalId($internalId);
if (!$payment || (int)$payment['user_id'] !== $userId) {
    header("Location: /add-funds?error=" . urlencode("Payment session not found."));
    exit;
}

// If already credited, redirect to verify success page
$isCredited = ((int)($payment['is_credited'] ?? 0) === 1 || ($payment['status'] ?? '') === 'SUCCESS');
$gatewayCode = $payment['gateway'] ?? ($payment['gateway_code'] ?? '');
if ($isCredited) {
    header("Location: /payment/verify?gateway=" . urlencode($gatewayCode) . "&internal_id=" . urlencode($internalId));
    exit;
}

$user = $db->query("SELECT id, username, email FROM users WHERE id = {$userId}")->fetch();
$gatewayConfig = PaymentHelper::getGateway($gatewayCode, false);
$extraData = !empty($payment['gateway_response']) ? json_decode($payment['gateway_response'], true) : [];

$pageTitle = 'Secure Checkout - RoseSMM';
$activePage = 'wallet';
require_once __DIR__ . '/../layouts/user_header.php';
?>

<div class="max-w-xl mx-auto py-8">
  <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center font-bold">
          <i data-lucide="shield-check" class="w-6 h-6"></i>
        </div>
        <div>
          <h1 class="text-xl font-black text-slate-800 tracking-tight">Complete Your Payment</h1>
          <p class="text-xs text-slate-400 mt-0.5">Secure encrypted transaction via <?= e($gatewayConfig['name'] ?? ucfirst($gatewayCode)) ?></p>
        </div>
      </div>
      <div class="text-right">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Amount</span>
        <span class="text-xl font-black text-slate-900"><?= e($payment['currency']) ?> <?= number_format($payment['amount'], 2) ?></span>
      </div>
    </div>

    <!-- Order summary box -->
    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 mb-6 text-xs space-y-2">
      <div class="flex items-center justify-between text-slate-500">
        <span>Payment Reference:</span>
        <span class="font-mono text-slate-800 font-bold"><?= e($payment['internal_payment_id']) ?></span>
      </div>
      <div class="flex items-center justify-between text-slate-500">
        <span>Payment Gateway:</span>
        <span class="font-bold text-slate-800"><?= e($gatewayConfig['name'] ?? ucfirst($gatewayCode)) ?></span>
      </div>
      <div class="flex items-center justify-between text-slate-500">
        <span>Account User:</span>
        <span class="font-bold text-slate-800"><?= e($user['username']) ?> (<?= e($user['email']) ?>)</span>
      </div>
      <div class="flex items-center justify-between text-slate-500 pt-2 border-t border-slate-200">
        <span>Status:</span>
        <span id="payment-status-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 uppercase">
          Awaiting Payment
        </span>
      </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- GATEWAY 1: RAZORPAY                                           -->
    <!-- ------------------------------------------------------------- -->
    <?php if ($gatewayCode === 'razorpay'): 
      $rzpKey = trim($gatewayConfig['api_key'] ?? '');
      $rzpOrderId = $payment['gateway_order_id'];
      $amountInPaise = (int)round($payment['amount'] * 100);
    ?>
      <div class="text-center py-4 space-y-4">
        <p class="text-xs text-slate-600">Click below to open the Razorpay payment window and complete your deposit via UPI, Cards, Netbanking, or Wallets.</p>
        
        <button id="rzp-button" class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-sm shadow-md transition-all flex items-center justify-center gap-2">
          <i data-lucide="lock" class="w-4 h-4"></i>
          <span>Pay <?= e($payment['currency']) ?> <?= number_format($payment['amount'], 2) ?> with Razorpay</span>
        </button>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
          <span id="poll-indicator" class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Listening for completion...
          </span>
          <button type="button" onclick="checkStatusManual()" class="text-blue-600 hover:underline font-bold">
            Already Paid? Check Status
          </button>
        </div>
      </div>

      <!-- Razorpay Checkout Script -->
      <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
      <script>
        const rzpOptions = {
          "key": "<?= e($rzpKey) ?>",
          "amount": "<?= $amountInPaise ?>",
          "currency": "<?= e($payment['currency']) ?>",
          "name": "RoseSMM",
          "description": "Wallet Deposit #<?= e($internalId) ?>",
          "order_id": "<?= e($rzpOrderId) ?>",
          "prefill": {
            "name": "<?= e($user['username']) ?>",
            "email": "<?= e($user['email']) ?>"
          },
          "theme": {
            "color": "#e11d48"
          },
          "handler": function (response) {
            document.getElementById('payment-status-badge').textContent = 'Verifying with Server...';
            document.getElementById('payment-status-badge').className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200 uppercase';
            
            // Post to verify page
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/payment/verify';
            
            const fields = {
              'gateway': 'razorpay',
              'internal_id': '<?= e($internalId) ?>',
              'razorpay_payment_id': response.razorpay_payment_id,
              'razorpay_order_id': response.razorpay_order_id,
              'razorpay_signature': response.razorpay_signature
            };

            for (const k in fields) {
              const inp = document.createElement('input');
              inp.type = 'hidden';
              inp.name = k;
              inp.value = fields[k];
              form.appendChild(inp);
            }
            document.body.appendChild(form);
            form.submit();
          },
          "modal": {
            "ondismiss": function() {
              console.log("Checkout modal dismissed by user. Polling continues.");
            }
          }
        };

        const rzp1 = new Razorpay(rzpOptions);
        document.getElementById('rzp-button').onclick = function(e) {
          rzp1.open();
          e.preventDefault();
        };

        // Automatically open on desktop
        window.addEventListener('load', () => {
          setTimeout(() => {
            rzp1.open();
          }, 400);
        });
      </script>

    <!-- ------------------------------------------------------------- -->
    <!-- GATEWAY 2: CASHFREE                                           -->
    <!-- ------------------------------------------------------------- -->
    <?php elseif ($gatewayCode === 'cashfree'): 
      $cfSessionId = $extraData['payment_session_id'] ?? $payment['gateway_order_id'];
      $isLive = ($gatewayConfig['mode'] ?? 'test') === 'live';
      $cfMode = $isLive ? 'production' : 'sandbox';
    ?>
      <div class="text-center py-4 space-y-4">
        <p class="text-xs text-slate-600">Connecting to Cashfree Secure Payments. If checkout does not launch automatically, click the button below.</p>
        
        <button id="cf-button" class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white font-extrabold text-sm shadow-md transition-all flex items-center justify-center gap-2">
          <i data-lucide="lock" class="w-4 h-4"></i>
          <span>Open Cashfree Checkout</span>
        </button>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Listening for completion...
          </span>
          <button type="button" onclick="checkStatusManual()" class="text-violet-600 hover:underline font-bold">
            Already Paid? Check Status
          </button>
        </div>
      </div>

      <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
      <script>
        const cashfree = Cashfree({
          mode: "<?= $cfMode ?>"
        });

        function launchCashfree() {
          cashfree.checkout({
            paymentSessionId: "<?= e($cfSessionId) ?>",
            redirectTarget: "_self"
          });
        }

        document.getElementById('cf-button').onclick = launchCashfree;
        window.addEventListener('load', () => {
          setTimeout(launchCashfree, 400);
        });
      </script>

    <!-- ------------------------------------------------------------- -->
    <!-- GATEWAY 3: PAYU                                               -->
    <!-- ------------------------------------------------------------- -->
    <?php elseif ($gatewayCode === 'payu'): 
      $payuService = new PayUService($gatewayConfig);
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
      $host = $_SERVER['HTTP_HOST'] ?? 'localhost:3000';
      $baseUrl = $protocol . $host;
      
      $surl = $baseUrl . '/payment/verify?gateway=payu&internal_id=' . urlencode($internalId);
      $furl = $baseUrl . '/payment/verify?gateway=payu&internal_id=' . urlencode($internalId);

      $checkoutData = $payuService->prepareCheckoutData(
          $userId,
          (float)$payment['amount'],
          $payment['currency'],
          $internalId,
          $user['username'],
          $user['email'],
          '9876543210',
          $surl,
          $furl
      );
    ?>
      <div class="text-center py-6 space-y-4">
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
          <i data-lucide="loader" class="w-6 h-6 animate-spin"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-800">Redirecting to PayU Secure Gateway...</h2>
          <p class="text-xs text-slate-500 mt-1">Please wait while we transfer you securely to PayU's hosted payment page.</p>
        </div>

        <form id="payu-checkout-form" method="POST" action="<?= e($checkoutData['action_url']) ?>" class="space-y-3">
          <?php foreach ($checkoutData['fields'] as $k => $v): ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
          <?php endforeach; ?>
          <button type="submit" class="px-6 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm">
            Click here if not redirected in 3 seconds
          </button>
        </form>
      </div>

      <script>
        window.addEventListener('load', () => {
          setTimeout(() => {
            document.getElementById('payu-checkout-form').submit();
          }, 300);
        });
      </script>

    <!-- ------------------------------------------------------------- -->
    <!-- GATEWAY 4: PHONEPE OR OTHER HOSTED                            -->
    <!-- ------------------------------------------------------------- -->
    <?php else: 
      $redir = $extraData['redirect_url'] ?? '';
    ?>
      <div class="text-center py-6 space-y-4">
        <p class="text-xs text-slate-600">Please continue to the secure payment portal.</p>
        <?php if (!empty($redir)): ?>
          <a href="<?= e($redir) ?>" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md">
            <span>Proceed to Payment Portal</span>
            <i data-lucide="external-link" class="w-4 h-4"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
      <a href="/add-funds" class="hover:text-slate-600 font-bold flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
        <span>Cancel & Change Method</span>
      </a>
      <span class="flex items-center gap-1 font-mono text-[10px]">
        <i data-lucide="lock" class="w-3 h-3 text-emerald-500"></i>
        256-Bit SSL Encrypted
      </span>
    </div>
  </div>
</div>

<!-- Background Polling Script -->
<script>
let pollInterval = setInterval(checkStatus, 3000);

function checkStatus() {
  fetch('/api/payment/status?internal_id=<?= urlencode($internalId) ?>')
    .then(r => r.json())
    .then(data => {
      if (data.success && data.credited) {
        clearInterval(pollInterval);
        window.location.href = data.redirect_url;
      }
    })
    .catch(() => {});
}

function checkStatusManual() {
  const btn = event.target;
  btn.textContent = 'Checking...';
  fetch('/api/payment/status?internal_id=<?= urlencode($internalId) ?>')
    .then(r => r.json())
    .then(data => {
      if (data.success && data.credited) {
        window.location.href = data.redirect_url;
      } else {
        alert("Payment is currently awaiting confirmation. If you have already completed payment on your app, please wait a few seconds while the bank processes it.");
        btn.textContent = 'Already Paid? Check Status';
      }
    })
    .catch(() => {
      btn.textContent = 'Already Paid? Check Status';
    });
}
</script>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
