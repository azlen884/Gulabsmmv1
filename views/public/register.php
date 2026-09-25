<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ReferralHelper.php';
require_once __DIR__ . '/../../includes/WaveDecorationHelper.php';

if (is_logged_in()) {
    header("Location: /dashboard");
    exit;
}

$error = '';

// Capture referral code from URL query or existing session
$refCode = trim($_GET['ref'] ?? $_SESSION['pending_ref'] ?? '');
$referrerUser = null;
if (!empty($refCode)) {
    $_SESSION['pending_ref'] = $refCode;
    $referrerUser = ReferralHelper::getReferrerByCode($refCode);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $submittedRef = trim($_POST['ref'] ?? $_SESSION['pending_ref'] ?? '');

    if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username must be 3-30 characters and contain only letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please re-enter your password.';
    } else {
        try {
            $db = getDB();

            // Check if username or email already exists with specific messages
            $checkStmt = $db->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
            $checkStmt->execute([$username, $email]);
            $existingUser = $checkStmt->fetch();

            if ($existingUser) {
                if (strcasecmp($existingUser['username'], $username) === 0) {
                    $error = 'This username is already taken. Please choose another username.';
                } else {
                    $error = 'An account with this email address already exists. Please sign in or use another email.';
                }
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $apiKey = 'rose_' . bin2hex(random_bytes(16));

                $insertStmt = $db->prepare("
                    INSERT INTO users (full_name, username, email, password, role, balance, currency, api_key, status)
                    VALUES (?, ?, ?, ?, 'user', 0.0000, 'USD', ?, 'active')
                ");
                $insertStmt->execute([$fullName, $username, $email, $hash, $apiKey]);
                $newUserId = (int)$db->lastInsertId();

                if ($newUserId > 0) {
                    // Generate unique permanent referral code for the new user
                    try {
                        $userRefCode = ReferralHelper::generateUniqueReferralCode($newUserId, $username);
                        $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$userRefCode, $newUserId]);

                        // Server-side permanent referral attribution (anti-tamper & anti-loop)
                        if (!empty($submittedRef)) {
                            ReferralHelper::attributeReferral($newUserId, $submittedRef);
                            unset($_SESSION['pending_ref']);
                        }
                    } catch (Throwable $refEx) {
                        error_log("[RoseSMM Registration Notice] Referral hook: " . $refEx->getMessage());
                    }

                    // Welcome Notification
                    try {
                        $db->prepare("
                            INSERT INTO notifications (user_id, title, message, type)
                            VALUES (?, ?, 'Your account has been created successfully. Welcome aboard!', 'promo')
                        ")->execute([$newUserId, 'Welcome to ' . get_site_name() . '!']);
                    } catch (Throwable $notifEx) {
                        error_log("[Registration Notice] Notification hook: " . $notifEx->getMessage());
                    }

                    // Ensure clean session initialization
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }

                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['username'] = $username;
                    $_SESSION['user_currency'] = 'USD';

                    // Cleanly clear output buffers to prevent whitespace/BOM from breaking redirect headers
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }

                    $targetUrl = '/dashboard';
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl, true, 302);
                    }

                    // Complete fallback HTML representation preventing any blank white page
                    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($targetUrl) . '">
  <title>Account Created - ' . htmlspecialchars(get_site_name()) . '</title>
  <script>window.location.replace(' . json_encode($targetUrl) . ');</script>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; background: #FFF9FA; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px;">
  <div style="background: white; border: 1px solid #FCE4E8; border-radius: 24px; padding: 32px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.08);">
    <div style="width: 48px; height: 48px; border-radius: 16px; background: #FFF0F3; color: #E11D48; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-weight: bold; font-size: 20px;">✓</div>
    <h2 style="margin: 0 0 8px; font-size: 20px; font-weight: 800; color: #0f172a;">Welcome to ' . htmlspecialchars(get_site_name()) . '!</h2>
    <p style="margin: 0 0 20px; font-size: 13px; color: #64748b;">Your account was created successfully. Redirecting you to the dashboard...</p>
    <a href="' . htmlspecialchars($targetUrl) . '" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #FF3B69, #E11D48); color: white; text-decoration: none; border-radius: 16px; font-weight: bold; font-size: 13px; box-shadow: 0 4px 10px rgba(225, 29, 72, 0.25);">Proceed to Dashboard →</a>
  </div>
</body>
</html>';
                    exit;
                } else {
                    $error = 'Failed to generate user account record. Please try again.';
                }
            }
        } catch (PDOException $pdoEx) {
            error_log("[RoseSMM Registration Error] Database query failure: " . $pdoEx->getMessage());
            if ($pdoEx->getCode() === '23000' || strpos($pdoEx->getMessage(), 'Duplicate entry') !== false) {
                if (stripos($pdoEx->getMessage(), 'username') !== false) {
                    $error = 'This username is already taken. Please choose another username.';
                } elseif (stripos($pdoEx->getMessage(), 'email') !== false) {
                    $error = 'This email address is already registered. Please sign in or use another email.';
                } else {
                    $error = 'An account with this username or email already exists.';
                }
            } else {
                $error = 'Database service error during registration. Please try again or contact support.';
            }
        } catch (Throwable $ex) {
            error_log("[RoseSMM Registration Error] Unexpected error: " . $ex->getMessage());
            $error = 'An unexpected system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - <?= e(get_site_name()) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            rose: {
              50: '#FFF0F3',
              100: '#FFE2E8',
              200: '#FCD3DC',
              500: '#FF3B69',
              600: '#E11D48',
              700: '#BE123C',
            }
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <?php render_theme_head_tags(); ?>
</head>
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex flex-col justify-between p-4 relative <?= get_theme_body_class() ?>">
  <!-- Global Active Ambient Decorations (Aurora Glow & Corner Glow) -->
  <?= WaveDecorationHelper::renderGlobalDecorations(true) ?>

  <div class="max-w-md w-full mx-auto pt-6 pb-2 text-center">
    <a href="/" class="inline-flex items-center gap-3">
      <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 shadow-sm font-black text-lg">
        <?= strtoupper(substr(get_site_name(), 0, 1)) ?>
      </div>
      <div class="text-left">
        <span class="text-xl font-bold tracking-tight text-rose-600 block leading-tight"><?= e(get_site_name()) ?></span>
        <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block"><?= e(get_setting('site_tagline', 'Social Media Services')) ?></span>
      </div>
    </a>
  </div>

  <div class="max-w-md w-full mx-auto bg-white rounded-3xl border border-[#FCE4E8] p-6 sm:p-8 shadow-sm">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Create an Account</h1>
      <p class="text-xs text-slate-500 mt-1">Join <?= e(get_site_name()) ?> to boost your social media with instant automated delivery.</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($referrerUser): ?>
      <div class="mb-4 p-3 rounded-2xl bg-gradient-to-r from-rose-50 to-rose-100/50 border border-rose-200 text-slate-800 text-xs flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0">
            <i data-lucide="gift" class="w-3.5 h-3.5"></i>
          </div>
          <div>
            <span class="text-slate-500 text-[11px] block">Invited by</span>
            <span class="font-bold text-rose-600">@<?= e($referrerUser['username']) ?></span>
          </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-white text-rose-600 border border-rose-200 shadow-xs">Referral Active</span>
      </div>
    <?php endif; ?>

    <form method="POST" action="/register" class="space-y-3.5">
      <?php if (!empty($refCode)): ?>
        <input type="hidden" name="ref" value="<?= e($refCode) ?>">
      <?php endif; ?>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
        <input 
          type="text" 
          name="full_name" 
          value="<?= e($fullName ?? '') ?>"
          required 
          placeholder="e.g. John Doe" 
          class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Username</label>
        <input 
          type="text" 
          name="username" 
          value="<?= e($username ?? '') ?>"
          required 
          placeholder="e.g. johndoe" 
          class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
        <input 
          type="email" 
          name="email" 
          value="<?= e($email ?? '') ?>"
          required 
          placeholder="e.g. john@example.com" 
          class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
        <input 
          type="password" 
          name="password" 
          required 
          placeholder="At least 6 characters" 
          class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1">Confirm Password</label>
        <input 
          type="password" 
          name="confirm_password" 
          required 
          placeholder="Re-enter password" 
          class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
        >
      </div>

      <button 
        type="submit" 
        class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2 mt-4 cursor-pointer"
      >
        <span>Register & Get Started</span>
        <i data-lucide="arrow-right" class="w-4 h-4"></i>
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500">
      Already have an account? 
      <a href="/login" class="font-bold text-rose-600 hover:underline">Sign in here</a>
    </div>
  </div>

  <div class="text-center text-xs text-slate-400 py-4">
    &copy; <?= date('Y') ?> <?= e(get_site_name()) ?>. All rights reserved.
  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
