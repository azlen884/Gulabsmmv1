<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/WaveDecorationHelper.php';

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
  <title>Sign In - <?= e(get_site_name()) ?></title>
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

  <!-- Navbar -->
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

  <!-- Login Card -->
  <div class="max-w-md w-full mx-auto bg-white rounded-3xl border border-[#FCE4E8] p-6 sm:p-8 shadow-sm">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Welcome Back</h1>
      <p class="text-xs text-slate-500 mt-1">Sign in to access your dashboard, wallet and active orders.</p>
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
            placeholder="Username or email address" 
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
        class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-bold text-sm shadow-md hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer"
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
    &copy; <?= date('Y') ?> <?= e(get_site_name()) ?>. All rights reserved.
  </div>

  <script>
    if (window.lucide) lucide.createIcons();

    function togglePassVisibility() {
      const p = document.getElementById('password-input');
      p.type = p.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
