<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ReferralHelper.php';

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
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = getDB();
        // Check if username or email already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->fetch()) {
            $error = 'Username or email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $apiKey = 'rose_' . bin2hex(random_bytes(16));

            $insertStmt = $db->prepare("
                INSERT INTO users (full_name, username, email, password, role, balance, currency, api_key, status)
                VALUES (?, ?, ?, ?, 'user', 0.0000, 'USD', ?, 'active')
            ");
            $insertStmt->execute([$fullName, $username, $email, $hash, $apiKey]);
            $newUserId = (int)$db->lastInsertId();

            // Generate unique permanent referral code for the new user
            $userRefCode = ReferralHelper::generateUniqueReferralCode($newUserId, $username);
            $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$userRefCode, $newUserId]);

            // Server-side permanent referral attribution (anti-tamper & anti-loop)
            if (!empty($submittedRef)) {
                ReferralHelper::attributeReferral($newUserId, $submittedRef);
                unset($_SESSION['pending_ref']);
            }

            // Auto-login
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_role'] = 'user';
            $_SESSION['username'] = $username;
            $_SESSION['user_currency'] = 'USD';

            // Welcome Notification
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Welcome to RoseSMM!', 'Your account has been created successfully. Claim your 10% deposit bonus today!', 'promo')
            ")->execute([$newUserId]);

            header("Location: /dashboard");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - RoseSMM</title>
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
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex flex-col justify-between p-4 <?= get_theme_body_class() ?>">

  <div class="max-w-md w-full mx-auto pt-6 pb-2 text-center">
    <a href="/" class="inline-flex items-center gap-3">
      <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 shadow-sm">
        <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
          <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
        </svg>
      </div>
      <div class="text-left">
        <span class="text-xl font-bold tracking-tight text-rose-600 block leading-tight">RoseSMM</span>
        <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block">Social Media Services</span>
      </div>
    </a>
  </div>

  <div class="max-w-md w-full mx-auto bg-white rounded-3xl border border-[#FCE4E8] p-6 sm:p-8 shadow-sm">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Create an Account</h1>
      <p class="text-xs text-slate-500 mt-1">Join RoseSMM to boost your social media with instant automated delivery.</p>
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
        class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2 mt-4"
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
    &copy; 2025 RoseSMM. All rights reserved.
  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
