<?php
$pageTitle = 'Profile & API - RoseSMM';
$activePage = 'profile';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$userId = $user['id'];
$successMsg = '';
$errorMsg = '';

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!empty($fullName) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $userId]);
            $successMsg = "Profile information updated successfully!";
            $user = current_user();
        } else {
            $errorMsg = "Please enter a valid full name and email address.";
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        if (password_verify($currentPass, $user['password'])) {
            if (strlen($newPass) >= 6) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
                $successMsg = "Password changed successfully!";
            } else {
                $errorMsg = "New password must be at least 6 characters long.";
            }
        } else {
            $errorMsg = "Current password does not match.";
        }
    } elseif ($action === 'generate_api_key') {
        $newKey = 'rose_' . bin2hex(random_bytes(16));
        $db->prepare("UPDATE users SET api_key = ? WHERE id = ?")->execute([$newKey, $userId]);
        $successMsg = "New API key generated successfully!";
        $user['api_key'] = $newKey;
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Account & Developer API</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage your account credentials and access the automated API documentation.</p>
  </div>

  <?php if ($successMsg): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i>
      <span><?= e($successMsg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4"></i>
      <span><?= e($errorMsg) ?></span>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Profile Info Form -->
    <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
      <h3 class="font-bold text-base text-slate-800 mb-4 flex items-center gap-2">
        <i data-lucide="user" class="w-4 h-4 text-rose-500"></i>
        <span>Personal Details</span>
      </h3>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="update_profile">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Username</label>
          <input type="text" value="<?= e($user['username']) ?>" disabled class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-400 cursor-not-allowed">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Full Name</label>
          <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Email Address</label>
          <input type="email" name="email" value="<?= e($user['email']) ?>" required class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400">
        </div>
        <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-sm transition-colors">
          Save Changes
        </button>
      </form>
    </div>

    <!-- Password Change Form -->
    <div class="bg-white p-6 rounded-3xl border border-[#FCE4E8] shadow-sm">
      <h3 class="font-bold text-base text-slate-800 mb-4 flex items-center gap-2">
        <i data-lucide="lock" class="w-4 h-4 text-rose-500"></i>
        <span>Security & Password</span>
      </h3>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="change_password">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Current Password</label>
          <input type="password" name="current_password" required placeholder="••••••••" class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">New Password</label>
          <input type="password" name="new_password" required placeholder="••••••••" class="w-full px-4 py-2.5 bg-rose-50/20 border border-[#FCE4E8] rounded-xl text-xs focus:outline-none focus:border-rose-400">
        </div>
        <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-sm transition-colors">
          Update Password
        </button>
      </form>
    </div>
  </div>

  <!-- API Key & Developer Integration Section -->
  <div id="api" class="bg-white p-6 sm:p-8 rounded-3xl border border-[#FCE4E8] shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
      <div>
        <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
          <i data-lucide="code" class="w-4 h-4 text-rose-500"></i>
          <span>Developer API Access</span>
        </h3>
        <p class="text-xs text-slate-400 mt-0.5">Integrate automated order placement directly into your own website or bot.</p>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="generate_api_key">
        <button type="submit" class="px-4 py-2 rounded-full border border-rose-300 text-rose-600 hover:bg-rose-50 text-xs font-bold transition-colors">
          <?= empty($user['api_key']) ? 'Generate API Key' : 'Regenerate API Key' ?>
        </button>
      </form>
    </div>

    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 mb-4">
      <span class="text-[11px] text-slate-400 uppercase font-bold block mb-1">Your API Key</span>
      <div class="font-mono text-xs sm:text-sm font-bold text-slate-800 select-all break-all">
        <?= e($user['api_key'] ?: 'No API key generated yet. Click "Generate API Key" above.') ?>
      </div>
    </div>

    <!-- API Reference sample -->
    <div class="text-xs text-slate-600 space-y-2">
      <div class="font-bold text-slate-800">HTTP POST Endpoint:</div>
      <code class="block p-3 rounded-xl bg-slate-900 text-rose-300 font-mono text-[11px]">
        POST /api/v2<br>
        key=<?= e($user['api_key'] ?: 'YOUR_API_KEY') ?>&action=add&service=1&link=https://instagram.com/user&quantity=1000
      </code>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
