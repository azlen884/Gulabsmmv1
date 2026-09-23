<?php
require_once __DIR__ . '/../../config/database.php';

// If already logged in, redirect to dashboard (Prompt Rule 14)
if (is_logged_in()) {
    header("Location: /dashboard");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($login) && !empty($password)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'banned') {
                $error = 'Your account has been suspended or banned. Please contact support.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_currency'] = $user['currency'] ?: 'USD';

                session_write_close();

                $targetUrl = ($user['role'] === 'admin') ? '/admin' : '/dashboard';
                if (!headers_sent()) {
                    header("Location: " . $targetUrl, true, 302);
                }
                echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($targetUrl) . '">';
                echo '<script>window.location.replace(' . json_encode($targetUrl) . ');</script></head>';
                echo '<body><p>Logged in successfully! Redirecting... <a href="' . htmlspecialchars($targetUrl) . '">Click here</a></p></body></html>';
                exit;
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
    } else {
        $error = 'Please enter both your username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - RoseSMM</title>
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

  <!-- Navbar -->
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

  <!-- Login Card -->
  <div class="max-w-md w-full mx-auto bg-white rounded-3xl border border-[#FCE4E8] p-6 sm:p-8 shadow-sm">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Welcome Back</h1>
      <p class="text-xs text-slate-500 mt-1">Sign in to access your dashboard, wallet and active orders.</p>
    </div>

    <!-- Quick Credentials Hint Helper for Instant Testing -->
    <div class="p-3 mb-5 rounded-2xl bg-rose-50/60 border border-rose-100 text-xs text-slate-600 space-y-1">
      <div class="font-bold text-rose-600 flex items-center gap-1">
        <i data-lucide="key" class="w-3.5 h-3.5"></i>
        <span>Ready-to-use Demo Accounts:</span>
      </div>
      <div class="flex items-center justify-between text-[11px] pt-1">
        <span>User: <strong>johndoe</strong> / <strong>password123</strong></span>
        <button type="button" onclick="fillCredentials('johndoe', 'password123')" class="text-rose-500 font-bold hover:underline">Auto-Fill</button>
      </div>
      <div class="flex items-center justify-between text-[11px]">
        <span>Admin: <strong>admin</strong> / <strong>admin123</strong></span>
        <button type="button" onclick="fillCredentials('admin', 'admin123')" class="text-rose-500 font-bold hover:underline">Auto-Fill</button>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="/login" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5">Username or Email</label>
        <div class="relative">
          <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
          <input 
            type="text" 
            name="username" 
            id="username-input" 
            required 
            placeholder="johndoe or johndoe@example.com" 
            class="w-full pl-11 pr-4 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
          >
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between text-xs mb-1.5">
          <label class="font-bold text-slate-700">Password</label>
        </div>
        <div class="relative">
          <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
          <input 
            type="password" 
            name="password" 
            id="password-input" 
            required 
            placeholder="••••••••" 
            class="w-full pl-11 pr-11 py-3 bg-rose-50/20 border border-[#FCE4E8] rounded-2xl text-xs sm:text-sm font-medium focus:outline-none focus:border-rose-400"
          >
          <button type="button" onclick="togglePassVisibility()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
            <i data-lucide="eye" id="pass-eye" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="remember" class="rounded border-slate-300 text-rose-500 focus:ring-rose-400">
          <span>Remember me</span>
        </label>
      </div>

      <button 
        type="submit" 
        class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2"
      >
        <span>Sign In</span>
        <i data-lucide="arrow-right" class="w-4 h-4"></i>
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500">
      Don't have an account? 
      <a href="/register" class="font-bold text-rose-600 hover:underline">Create an account</a>
    </div>
  </div>

  <div class="text-center text-xs text-slate-400 py-4">
    &copy; 2025 RoseSMM. All rights reserved.
  </div>

  <script>
    if (window.lucide) lucide.createIcons();

    function fillCredentials(u, p) {
      document.getElementById('username-input').value = u;
      document.getElementById('password-input').value = p;
    }

    function togglePassVisibility() {
      const p = document.getElementById('password-input');
      p.type = p.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
