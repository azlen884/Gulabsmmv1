<?php
$pageTitle = 'Tournaments & Matches - RoseSMM';
$activePage = 'tournaments';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$tournaments = $db->query("SELECT * FROM tournaments ORDER BY id DESC")->fetchAll();
$matches = $db->query("SELECT m.*, t.title AS tournament_title FROM matches m JOIN tournaments t ON m.tournament_id = t.id ORDER BY m.match_time DESC")->fetchAll();
$teams = $db->query("SELECT * FROM teams ORDER BY wins DESC")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Gaming Tournaments & Matches</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Join community gaming events, track live match scores, and win prizes.</p>
  </div>
</div>

<!-- Tournaments Card List (NO TABLE UI!) -->
<div class="mb-8">
  <h2 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
    <i data-lucide="trophy" class="w-5 h-5 text-rose-500"></i>
    <span>Active & Upcoming Tournaments</span>
  </h2>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($tournaments as $tn): ?>
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold px-3 py-1 rounded-full <?= $tn['status'] === 'live' ? 'bg-rose-50 text-rose-600 animate-pulse' : 'bg-slate-100 text-slate-600' ?>">
              ● <?= strtoupper($tn['status']) ?>
            </span>
            <span class="text-xs text-slate-400"><?= e($tn['game']) ?></span>
          </div>

          <h3 class="text-lg font-bold text-slate-800 mb-2"><?= e($tn['title']) ?></h3>

          <div class="grid grid-cols-2 gap-3 py-3 border-y border-slate-100 mb-4 text-xs">
            <div>
              <span class="text-slate-400 block">Prize Pool:</span>
              <span class="font-extrabold text-emerald-600 text-sm">$<?= number_format($tn['prize_pool'], 2) ?></span>
            </div>
            <div>
              <span class="text-slate-400 block">Entry Fee:</span>
              <span class="font-bold text-slate-800 text-sm"><?= $tn['entry_fee'] > 0 ? '$' . number_format($tn['entry_fee'], 2) : 'FREE' ?></span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-500">Teams: <strong><?= $tn['registered_teams'] ?> / <?= $tn['max_teams'] ?></strong></span>
          <button class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold transition-colors">
            Register Team
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Matches & Teams Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Matches List (Card layout, NO TABLES!) -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 mb-4 flex items-center gap-2">
      <i data-lucide="swords" class="w-5 h-5 text-rose-500"></i>
      <span>Live & Scheduled Matches</span>
    </h3>

    <div class="space-y-3">
      <?php foreach ($matches as $m): ?>
        <div class="p-4 rounded-2xl border border-slate-100 hover:border-rose-200 transition-all">
          <div class="text-[11px] text-slate-400 mb-2 flex items-center justify-between">
            <span><?= e($m['tournament_title']) ?></span>
            <span class="font-bold uppercase <?= $m['status'] === 'live' ? 'text-rose-600' : 'text-slate-400' ?>"><?= $m['status'] ?></span>
          </div>

          <div class="flex items-center justify-between">
            <div class="font-bold text-xs sm:text-sm text-slate-800"><?= e($m['team1_name']) ?></div>
            <div class="px-3 py-1 rounded-xl bg-rose-50 text-rose-600 font-extrabold text-sm">
              <?= $m['score1'] ?> - <?= $m['score2'] ?>
            </div>
            <div class="font-bold text-xs sm:text-sm text-slate-800"><?= e($m['team2_name']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Teams Leaderboard (Card layout, NO TABLES!) -->
  <div class="bg-white rounded-3xl border border-[#FCE4E8] p-6 shadow-sm">
    <h3 class="font-bold text-base text-slate-800 mb-4 flex items-center gap-2">
      <i data-lucide="shield" class="w-5 h-5 text-rose-500"></i>
      <span>Top Leaderboard Teams</span>
    </h3>

    <div class="space-y-3">
      <?php foreach ($teams as $idx => $tm): ?>
        <div class="p-4 rounded-2xl border border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl font-extrabold text-xs flex items-center justify-center <?= $idx === 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' ?>">
              #<?= $idx + 1 ?>
            </div>
            <div>
              <div class="font-bold text-xs sm:text-sm text-slate-800"><?= e($tm['name']) ?></div>
              <div class="text-[11px] text-slate-400"><?= $tm['members_count'] ?> Active Members</div>
            </div>
          </div>
          <div class="text-right">
            <span class="text-xs font-bold text-emerald-600"><?= $tm['wins'] ?> Wins</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
