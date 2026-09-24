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
  <a href="/order" class="service-header-action-btn inline-flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-xs sm:text-sm shadow-sm hover:shadow transition-all">
    <i data-lucide="plus" class="w-4 h-4"></i>
    <span>New Order</span>
  </a>
</div>

<!-- Filters and Category Tabs -->
<div class="service-filters-card bg-white p-4 rounded-3xl border border-slate-200/80 shadow-sm mb-6 space-y-4">
  <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
    <!-- Category Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto pb-1 custom-scrollbar">
      <a href="/services" class="service-category-pill <?= $selectedCategory === 'all' ? 'active' : '' ?> shrink-0 px-4 py-2 rounded-full text-xs font-bold transition-all shadow-2xs">
        All Services
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="/services?category=<?= e($cat['slug']) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="service-category-pill <?= $selectedCategory === $cat['slug'] ? 'active' : '' ?> shrink-0 px-4 py-2 rounded-full text-xs font-bold transition-all">
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search input -->
    <form method="GET" action="/services" class="relative w-full md:w-72 shrink-0">
      <?php if ($selectedCategory !== 'all'): ?>
        <input type="hidden" name="category" value="<?= e($selectedCategory) ?>">
      <?php endif; ?>
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
      <input 
        type="text" 
        name="search" 
        value="<?= e($search) ?>" 
        placeholder="Search services..." 
        class="service-search-input w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-full text-xs focus:outline-none transition-all text-slate-700 placeholder-slate-400"
      >
    </form>
  </div>
</div>

<!-- Services Card Layout (NO TABLE UI!) -->
<?php if (empty($services)): ?>
  <div class="service-empty-card bg-white p-12 rounded-3xl border border-slate-200/80 text-center max-w-md mx-auto shadow-sm">
    <div class="service-empty-icon w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="layers" class="w-8 h-8"></i>
    </div>
    <h3 class="text-base font-bold text-slate-800 mb-1">No Services Found</h3>
    <p class="text-xs text-slate-500 mb-4">No services match your active search or category filter.</p>
    <a href="/services" class="service-empty-btn inline-block px-5 py-2 rounded-full font-bold text-xs transition-colors">Clear Filters</a>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
    <?php foreach ($services as $s): 
      $catSlug = strtolower($s['category_slug'] ?? '');
      $icon = 'sparkles';
      if (strpos($catSlug, 'youtube') !== false) $icon = 'youtube';
      elseif (strpos($catSlug, 'tiktok') !== false) $icon = 'music-2';
      elseif (strpos($catSlug, 'telegram') !== false) $icon = 'send';
      elseif (strpos($catSlug, 'twitter') !== false || strpos($catSlug, 'x') !== false) $icon = 'twitter';
      elseif (strpos($catSlug, 'facebook') !== false) $icon = 'thumbs-up';
      elseif (strpos($catSlug, 'linkedin') !== false) $icon = 'linkedin';
      elseif (strpos($catSlug, 'instagram') !== false) $icon = 'instagram';
    ?>
      <div class="service-item-card bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative group overflow-hidden">
        <div class="min-w-0">
          <!-- Top Row: Icon, Category & Badge -->
          <div class="flex items-center justify-between gap-2 mb-3 min-w-0">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
              <div class="service-item-icon w-9 h-9 rounded-2xl flex items-center justify-center font-bold text-xs shrink-0" title="<?= e($s['category_name']) ?>">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i>
              </div>
              <span class="service-item-cat text-xs font-semibold px-2.5 py-0.5 rounded-full truncate max-w-[140px]">
                <?= e($s['category_name']) ?>
              </span>
            </div>
            <?php if (!empty($s['badge'])): ?>
              <span class="service-item-badge text-[10px] font-bold px-2.5 py-0.5 rounded-full shrink-0 shadow-2xs">
                <?= e($s['badge']) ?>
              </span>
            <?php endif; ?>
          </div>

          <!-- Service Title -->
          <h3 class="service-item-title text-sm font-bold text-slate-800 mb-2 leading-snug break-words">
            <?= e($s['name']) ?>
          </h3>

          <!-- Description -->
          <p class="service-item-desc text-xs text-slate-500 mb-4 line-clamp-3 leading-relaxed break-words">
            <?= e($s['description'] ?: 'High quality, automated instant delivery. 100% safe profile boost.') ?>
          </p>
        </div>

        <!-- Bottom Stats & Action -->
        <div class="pt-4 border-t border-slate-100 space-y-3 shrink-0">
          <div class="flex items-center justify-between text-xs gap-2">
            <span class="text-slate-400 shrink-0">Rate per 1,000:</span>
            <span class="service-item-rate font-extrabold text-sm truncate"><?= format_price($s['rate'], null, $s['currency'] ?? 'USD') ?></span>
          </div>

          <div class="flex items-center justify-between text-[11px] text-slate-500 gap-2">
            <span class="truncate">Min: <?= number_format($s['min_quantity']) ?></span>
            <span class="truncate">Max: <?= number_format($s['max_quantity']) ?></span>
          </div>

          <a href="/order?service=<?= $s['id'] ?>" class="service-item-btn w-full py-2.5 px-4 rounded-xl font-bold text-xs text-center shadow-xs block transition-all shrink-0 whitespace-nowrap">
            Order This Service
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/user_footer.php'; ?>
