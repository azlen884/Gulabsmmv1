<?php
require_once __DIR__ . '/../../config/database.php';

// If already admin, redirect to /admin
if (is_admin()) {
    header("Location: /admin");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = 'admin' LIMIT 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['username'] = $admin['username'];
            header("Location: /admin");
            exit;
        } else {
            $error = 'Invalid administrator credentials.';
        }
    } else {
        $error = 'Please provide username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - <?= e(get_site_name()) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            rose: {
              50: '#FFF0F3',
              500: '#FF3B69',
              600: '#E11D48',
            }
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <?php render_theme_head_tags(); ?>
</head>
<body class="bg-slate-900 text-slate-100 antialiased min-h-screen flex items-center justify-center p-4 <?= get_theme_body_class() ?>">

  <div class="max-w-md w-full bg-slate-800 rounded-3xl border border-slate-700 p-8 shadow-2xl">
    <div class="text-center mb-6">
      <div class="w-12 h-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center mx-auto mb-3 font-bold text-lg shadow-lg">
        <?= strtoupper(substr(get_site_name(), 0, 1)) ?>
      </div>
      <h1 class="text-2xl font-black text-white tracking-tight">Admin Gateway</h1>
      <p class="text-xs text-slate-400 mt-1">Authorized personnel only.</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 p-3 rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="/admin/login" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-300 mb-1">Admin Username or Email</label>
        <input 
          type="text" 
          name="username" 
          id="admin-u"
          required 
          placeholder="admin" 
          class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-xs text-white focus:outline-none focus:border-rose-500"
        >
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-300 mb-1">Password</label>
        <input 
          type="password" 
          name="password" 
          id="admin-p"
          required 
          placeholder="••••••••" 
          class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-xs text-white focus:outline-none focus:border-rose-500"
        >
      </div>

      <button 
        type="submit" 
        class="w-full py-3.5 px-6 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-lg transition-colors flex items-center justify-center gap-2 mt-4 cursor-pointer"
      >
        <span>Authenticate & Access</span>
        <i data-lucide="arrow-right" class="w-4 h-4"></i>
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500">
      <a href="/" class="hover:text-rose-400">← Back to public website</a>
    </div>
  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
