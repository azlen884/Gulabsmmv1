<?php
$pageTitle = 'Services - RoseSMM';
$activePage = 'services';
require_once __DIR__ . '/../layouts/user_header.php';

$db = getDB();
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedCategory = isset($_GET['category']) ? trim($_GET['category']) : 'all';

$sql = "
    SELECT s.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
    FROM services s
    JOIN categories c ON s.category_id = c.id
    WHERE s.status = 'active'
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($selectedCategory !== 'all' && !empty($selectedCategory)) {
    $sql .= " AND c.slug = ?";
    $params[] = $selectedCategory;
}

$sql .= " ORDER BY c.sort_order ASC, s.sort_order ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();
?>

<!-- Header Section -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Services Catalog</h1>
    <p class="text-xs sm:text-sm text-slate-500 mt-1">Browse all available social media growth services with real-time rates.</p>
  </div>
  <a href="/order" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gradient-to-r from-rose-500 to-rose-600 text-white font-bold text-xs sm:text-sm shadow-sm hover:shadow transition-all">
    <i data-lucide="plus" class="w-4 h-4"></i>
    <span>New Order</span>
  </a>
</div>

<!-- Filters and Category Tabs -->
<div class="bg-white p-4 rounded-3xl border border-[#FCE4E8] shadow-sm mb-6 space-y-4">
  <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
    <!-- Category Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-1 custom-scrollbar">
      <a href="/services" class="shrink-0 px-4 py-2 rounded-full text-xs font-bold transition-colors <?= $selectedCategory === 'all' ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100 hover:text-rose-600' ?>">
        All Services
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="/services?category=<?= e($cat['slug']) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="shrink-0 px-4 py-2 rounded-full text-xs font-bold transition-colors <?= $selectedCategory === $cat['slug'] ? 'bg-rose-500 text-white' : 'bg-rose-50 text-slate-600 hover:bg-rose-100 hover:text-rose-600' ?>">
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search input -->
    <form method="GET" action="/services" class="relative w-full md:w-72 shrink-0">
      <?php if ($selectedCategory !== 'all'): ?>
        <input type="hidden" name="category" value="<?= e($selectedCategory) ?>">
      <?php endif; ?>
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
      <input 
        type="text" 
        name="search" 
        value="<?= e($search) ?>" 
        placeholder="Search services..." 
        class="w-full pl-10 pr-4 py-2 bg-rose-50/30 border border-[#FCE4E8] rounded-full text-xs focus:outline-none focus:border-rose-400"
      >
    </form>
  </div>
</div>

<!-- Services Card Layout (NO TABLE UI!) -->
<?php if (empty($services)): ?>
  <div class="bg-white p-12 rounded-3xl border border-[#FCE4E8] text-center max-w-md mx-auto">
    <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="layers" class="w-8 h-8"></i>
    </div>
    <h3 class="text-base font-bold text-slate-800 mb-1">No Services Found</h3>
    <p class="text-xs text-slate-500 mb-4">No services match your active search or category filter.</p>
    <a href="/services" class="inline-block px-4 py-2 rounded-full bg-rose-500 text-white text-xs font-bold">Clear Filters</a>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
    <?php foreach ($services as $s): ?>
      <div class="bg-white rounded-3xl border border-[#FCE4E8] p-5 shadow-sm hover:border-rose-300 transition-all flex flex-col justify-between relative group overflow-hidden">
        <div class="min-w-0">
          <!-- Top Row: Icon, Category & Badge -->
          <div class="flex items-center justify-between gap-2 mb-3 min-w-0">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
              <div class="w-9 h-9 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center font-bold text-xs shrink-0">
                #<?= $s['id'] ?>
              </div>
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-600 truncate max-w-[140px]">
                <?= e($s['category_name']) ?>
              </span>
            </div>
            <?php if (!empty($s['badge'])): ?>
              <span class="text-[10px] font-bold text-white bg-rose-500 px-2.5 py-0.5 rounded-full shrink-0">
                <?= e($s['badge']) ?>
              </span>
            <?php endif; ?>
          </div>

          <!-- Service Title -->
          <h3 class="text-sm font-bold text-slate-800 mb-2 leading-snug break-words">
            <?= e($s['name']) ?>
          </h3>

          <!-- Description -->
          <p class="text-xs text-slate-500 mb-4 line-clamp-3 leading-relaxed break-words">
            <?= e($s['description'] ?: 'High quality, automated instant delivery. 100% safe profile boost.') ?>
          </p>
        </div>

        <!-- Bottom Stats & Action -->
        <div class="pt-4 border-t border-slate-100 space-y-3 shrink-0">
          <div class="flex items-center justify-between text-xs gap-2">
            <span class="text-slate-400 shrink-0">Rate per 1,000:</span>
            <span class="font-extrabold text-rose-600 text-sm truncate"><?= format_price($s['rate']) ?></span>
          </div>

          <div class="flex items-center justify-between text-[11px] text-slate-500 gap-2">
            <span class="truncate">Min: <?= number_format($s['min_quantity']) ?></span>
            <span class="truncate">Max: <?= number_format($s['max_quantity']) ?></span>
          </div>

          <a href="/order?service=<?= $s['id'] ?>" class="w-full py-2.5 px-4 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs text-center shadow-sm block transition-colors shrink-0 whitespace-nowrap">
            Order This Service
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
