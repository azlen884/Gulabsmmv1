<?php
$pageTitle = 'Profile & API - SMM Pro';
$activePage = 'profile';
require_once __DIR__ . '/../layouts/header.php';

$db = getDB();
$userId = $user['id'];
$successMsg = '';
$errorMsg = '';

// Handle Profile Updates
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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
        $newKey = 'smmpro_' . bin2hex(random_bytes(16));
        $db->prepare("UPDATE users SET api_key = ? WHERE id = ?")->execute([$newKey, $userId]);
        $successMsg = "New API key generated successfully!";
        $user['api_key'] = $newKey;
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
  <!-- Header -->
  <div>
    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
      <i data-lucide="settings" class="w-6 h-6 text-[#FF2D78]"></i>
      Profile & API Access
    </h1>
    <p class="text-xs text-[#9D9DB8] mt-1">Manage personal credentials, security keys, and automated developer API integration.</p>
  </div>

  <?php if ($successMsg): ?>
    <div class="p-4 rounded-2xl bg-[#10B981]/15 border border-[#10B981]/30 text-[#34D399] text-xs font-bold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
      <span><?= e($successMsg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
    <div class="p-4 rounded-2xl bg-[#EF4444]/15 border border-[#EF4444]/30 text-[#F87171] text-xs font-bold flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
      <span><?= e($errorMsg) ?></span>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Profile Info Form -->
    <div class="smm-card p-6 space-y-4">
      <h2 class="text-base font-black text-white flex items-center gap-2 border-b border-white/5 pb-3">
        <i data-lucide="user" class="w-4.5 h-4.5 text-[#FF2D78]"></i>
        Account Details
      </h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="update_profile">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Username</label>
          <input type="text" value="<?= e($user['username']) ?>" disabled class="smm-input opacity-60 cursor-not-allowed text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Full Name</label>
          <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required class="smm-input text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Email Address</label>
          <input type="email" name="email" value="<?= e($user['email']) ?>" required class="smm-input text-xs">
        </div>
        <div class="pt-2">
          <button type="submit" class="smm-btn-pink w-full py-2.5 text-xs font-bold">
            Save Profile
          </button>
        </div>
      </form>
    </div>

    <!-- Change Password Form -->
    <div class="smm-card p-6 space-y-4">
      <h2 class="text-base font-black text-white flex items-center gap-2 border-b border-white/5 pb-3">
        <i data-lucide="lock" class="w-4.5 h-4.5 text-[#FF2D78]"></i>
        Change Password
      </h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="change_password">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">Current Password</label>
          <input type="password" name="current_password" required placeholder="••••••••" class="smm-input text-xs">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-1.5">New Password (Min 6 chars)</label>
          <input type="password" name="new_password" required placeholder="••••••••" class="smm-input text-xs">
        </div>
        <div class="pt-2">
          <button type="submit" class="smm-btn-dark w-full py-2.5 text-xs font-bold">
            Update Password
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Developer API Key Card -->
  <div class="smm-card p-6 sm:p-7 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
      <div>
        <h2 class="text-base font-black text-white flex items-center gap-2">
          <i data-lucide="key" class="w-4.5 h-4.5 text-[#FF2D78]"></i>
          Developer API Key
        </h2>
        <p class="text-xs text-[#9D9DB8] mt-0.5">Use this secret key to automate order placement and tracking via our REST API v2.</p>
      </div>
      <form method="POST" onsubmit="return confirm('Regenerating your API key will immediately invalidate your previous key. Continue?');">
        <input type="hidden" name="action" value="generate_api_key">
        <button type="submit" class="smm-btn-dark px-4 py-2 text-xs">
          <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
          <span>Regenerate Key</span>
        </button>
      </form>
    </div>

    <div>
      <label class="block text-xs font-bold uppercase tracking-wider text-[#9D9DB8] mb-2">Secret API Key</label>
      <div class="flex gap-2">
        <input 
          type="text" 
          id="api-key-input" 
          readonly 
          value="<?= e($user['api_key'] ?? 'No API key generated yet') ?>" 
          class="smm-input font-mono text-xs tracking-wider"
        >
        <button 
          type="button" 
          onclick="copyApiKey()" 
          id="copy-key-btn" 
          class="smm-btn-pink px-4 py-2 text-xs shrink-0"
        >
          <i data-lucide="copy" class="w-3.5 h-3.5"></i>
          <span id="copy-text">Copy</span>
        </button>
      </div>
    </div>

    <!-- API Endpoint Box -->
    <div class="p-4 rounded-2xl bg-[#18182D] border border-white/10 space-y-2 text-xs">
      <div class="text-[#9D9DB8]">API Endpoint URL:</div>
      <div class="font-mono text-[#FF2D78] font-bold break-all">
        <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/api/v2" ?>
      </div>
      <p class="text-[11px] text-[#6C6C8A]">Supports standard standard SMM methods: <code>action=services</code>, <code>action=add</code>, <code>action=status</code>, <code>action=balance</code>.</p>
    </div>
  </div>
</div>

<script>
function copyApiKey() {
  const input = document.getElementById('api-key-input');
  input.select();
  navigator.clipboard.writeText(input.value).then(() => {
    const text = document.getElementById('copy-text');
    text.textContent = 'Copied!';
    setTimeout(() => { text.textContent = 'Copy'; }, 2000);
  });
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
