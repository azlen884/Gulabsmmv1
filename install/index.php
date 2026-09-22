<?php
// Installer for RoseSMM Panel
error_reporting(E_ALL);
ini_set('display_errors', 1);

$installedLockFile = __DIR__ . '/installed.lock';
$isInstalled = file_exists($installedLockFile);

$step = isset($_GET['step']) ? (int)$_GET['step'] : ($isInstalled ? 4 : 1);
$message = '';
$error = '';

if ($isInstalled && $step !== 4 && !isset($_POST['reinstall'])) {
    $step = 4; // Locked
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_db') {
        $dbHost = $_POST['db_host'] ?? '127.0.0.1';
        $dbPort = $_POST['db_port'] ?? '3306';
        $dbName = $_POST['db_name'] ?? 'smm_panel';
        $dbUser = $_POST['db_user'] ?? 'root';
        $dbPass = $_POST['db_pass'] ?? '';

        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $message = "Database connection successful! Created/Verified database: $dbName.";
            $step = 2;
        } catch (Exception $e) {
            $error = "Database Connection Failed: " . $e->getMessage();
        }
    } elseif ($action === 'install_schema') {
        $dbHost = $_POST['db_host'] ?? '127.0.0.1';
        $dbPort = $_POST['db_port'] ?? '3306';
        $dbName = $_POST['db_name'] ?? 'smm_panel';
        $dbUser = $_POST['db_user'] ?? 'root';
        $dbPass = $_POST['db_pass'] ?? '';

        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminEmail = trim($_POST['admin_email'] ?? 'admin@rosesmm.com');
        $adminPass = $_POST['admin_pass'] ?? 'admin123';
        $siteName = trim($_POST['site_name'] ?? 'RoseSMM');

        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Execute schema.sql
            $schemaFile = __DIR__ . '/schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
            }

            // Update admin credentials with secure password hashing
            $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, role, balance, currency, api_key, status)
                VALUES (?, ?, ?, 'System Administrator', 'admin', 50000.0000, 'INR', ?, 'active')
                ON DUPLICATE KEY UPDATE password = ?, email = ?
            ");
            $apiKey = 'rose_adm_' . bin2hex(random_bytes(10));
            $stmt->execute([$adminUser, $adminEmail, $adminHash, $apiKey, $adminHash, $adminEmail]);

            // Update site name and default INR currency
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$siteName, $siteName]);
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('currency_default', 'INR') ON DUPLICATE KEY UPDATE setting_value = 'INR'")->execute();
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('currency_symbol', '₹') ON DUPLICATE KEY UPDATE setting_value = '₹'")->execute();
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('installed', '1') ON DUPLICATE KEY UPDATE setting_value = '1'")->execute();

            // Set INR as default currency in currencies table
            $pdo->exec("UPDATE currencies SET is_default = 0");
            $pdo->exec("UPDATE currencies SET is_default = 1, rate = 1.000000 WHERE code = 'INR'");

            // Lock the installer
            file_put_contents($installedLockFile, date('Y-m-d H:i:s'));
            $step = 3;
            $message = "RoseSMM has been successfully installed and configured!";
        } catch (Exception $e) {
            $error = "Installation Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RoseSMM Panel Installer</title>
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
</head>
<body class="bg-[#FFF9FA] text-slate-800 antialiased min-h-screen flex flex-col justify-between p-4">

  <div class="max-w-lg w-full mx-auto pt-8 pb-4 text-center">
    <div class="inline-flex items-center gap-3">
      <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 shadow-sm">
        <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
      </div>
      <div class="text-left">
        <span class="text-xl font-bold tracking-tight text-rose-600 block leading-tight">RoseSMM</span>
        <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block">System Installation Wizard</span>
      </div>
    </div>
  </div>

  <div class="max-w-lg w-full mx-auto bg-white rounded-3xl border border-[#FCE4E8] p-6 sm:p-8 shadow-sm">
    <?php if ($error): ?>
      <div class="mb-5 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="mb-5 p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        <span><?= e($message) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <!-- Step 1: Database Connection -->
      <h2 class="text-lg font-bold text-slate-800 mb-1">Step 1: Database Setup</h2>
      <p class="text-xs text-slate-500 mb-5">Configure your MySQL / MariaDB server credentials.</p>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="test_db">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Database Host</label>
          <input type="text" name="db_host" value="127.0.0.1" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Database Port</label>
          <input type="text" name="db_port" value="3306" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Database Name</label>
          <input type="text" name="db_name" value="smm_panel" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Database Username</label>
          <input type="text" name="db_user" value="root" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Database Password</label>
          <input type="password" name="db_pass" value="" placeholder="(empty for default root)" class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>

        <button type="submit" class="w-full py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
          Verify Database & Continue →
        </button>
      </form>

    <?php elseif ($step === 2): ?>
      <!-- Step 2: Site Settings & Admin Account -->
      <h2 class="text-lg font-bold text-slate-800 mb-1">Step 2: Admin & Site Configuration</h2>
      <p class="text-xs text-slate-500 mb-5">Create the administrator account and default panel settings.</p>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="install_schema">
        <input type="hidden" name="db_host" value="<?= e($_POST['db_host'] ?? '127.0.0.1') ?>">
        <input type="hidden" name="db_port" value="<?= e($_POST['db_port'] ?? '3306') ?>">
        <input type="hidden" name="db_name" value="<?= e($_POST['db_name'] ?? 'smm_panel') ?>">
        <input type="hidden" name="db_user" value="<?= e($_POST['db_user'] ?? 'root') ?>">
        <input type="hidden" name="db_pass" value="<?= e($_POST['db_pass'] ?? '') ?>">

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Site Name</label>
          <input type="text" name="site_name" value="RoseSMM" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Admin Username</label>
          <input type="text" name="admin_user" value="admin" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Admin Email</label>
          <input type="email" name="admin_email" value="admin@rosesmm.com" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Admin Password</label>
          <input type="password" name="admin_pass" value="admin123" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs font-medium focus:outline-none focus:border-rose-400">
        </div>

        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-500">
          Default currency is automatically set to <strong>INR (₹)</strong> as required by specifications, with USD, EUR, and GBP conversion rates preloaded.
        </div>

        <button type="submit" class="w-full py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
          Complete Installation & Lock →
        </button>
      </form>

    <?php elseif ($step === 3 || $step === 4): ?>
      <!-- Installed / Locked State (Prompt Rule 19: After installation: lock the installer) -->
      <div class="text-center py-4">
        <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4">
          <i data-lucide="shield-check" class="w-7 h-7"></i>
        </div>
        <h2 class="text-xl font-extrabold text-slate-800 mb-1">Installation Locked</h2>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">
          RoseSMM is already installed and securely locked. To reinstall, delete the <code class="font-mono text-rose-500">install/installed.lock</code> file.
        </p>

        <div class="space-y-3">
          <a href="/login" class="block w-full py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
            Go to User Login (/login)
          </a>
          <a href="/admin/login" class="block w-full py-3 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-sm transition-colors">
            Go to Admin Login (/admin/login)
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="text-center text-xs text-slate-400 py-4">
    &copy; 2025 RoseSMM. All rights reserved.
  </div>

  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
