<?php
$pageTitle = 'Services Catalog - SMM Pro';
$activePage = 'services';
require_once __DIR__ . '/../layouts/header.php';

$db = getDB();
$userCurrency = get_user_currency();
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();

$serviceSql = "
    SELECT s.*, c.name AS category_name, c.slug AS category_slug 
    FROM services s 
    JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'active'
";
$params = [];

if ($selectedCategory > 0) {
    $serviceSql .= " AND s.category_id = ?";
    $params[] = $selectedCategory;
}

if (!empty($search)) {
    $serviceSql .= " AND (s.name LIKE ? OR s.id = ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = (int)$search;
    $params[] = "%$search%";
}

$serviceSql .= " ORDER BY c.sort_order ASC, s.sort_order ASC, s.id ASC";
$stmt = $db->prepare($serviceSql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Group services by category for clean presentation
$groupedServices = [];
foreach ($services as $srv) {
    $catName = $srv['category_name'];
    if (!isset($groupedServices[$catName])) {
        $groupedServices[$catName] = [];
    }
    $groupedServices[$catName][] = $srv;
}
?>

<div class="space-y-6 max-w-7xl mx-auto">
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
        <i data-lucide="sparkles" class="w-6 h-6 text-[#FF2D78]"></i>
        Services & Pricing
      </h1>
      <p class="text-xs text-[#9D9DB8] mt-1">High-speed automated API services for all major social networks.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="/order" class="smm-btn-pink px-5 py-2 text-xs">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        <span>Place Order</span>
      </a>
    </div>
  </div>

  <!-- Search and Category Filters -->
  <div class="smm-card p-4 sm:p-5 space-y-4">
    <!-- Category Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
      <a href="/services<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $selectedCategory === 0 ? 'active' : '' ?>">
        <span>All Categories</span>
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="/services?category=<?= $cat['id'] ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="smm-cat-pill <?= $selectedCategory === (int)$cat['id'] ? 'active' : '' ?>">
          <span><?= e($cat['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search Form -->
    <form method="GET" action="/services" class="flex gap-2">
      <?php if ($selectedCategory > 0): ?>
        <input type="hidden" name="category" value="<?= $selectedCategory ?>">
      <?php endif; ?>
      <div class="relative flex-1">
        <i data-lucide="search" class="w-4 h-4 text-[#6C6C8A] absolute left-3.5 top-1/2 -translate-y-1/2"></i>
        <input 
          type="text" 
          name="search" 
          value="<?= e($search) ?>" 
          placeholder="Search by service name, platform, or ID..." 
          class="smm-input pl-9 text-xs"
        >
      </div>
      <button type="submit" class="smm-btn-pink px-5 text-xs">Filter</button>
      <?php if (!empty($search) || $selectedCategory > 0): ?>
        <a href="/services" class="smm-btn-dark px-3 text-xs">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Services Grouped List -->
  <?php if (empty($groupedServices)): ?>
    <div class="smm-card p-12 text-center text-[#9D9DB8]">
      <div class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-3 text-[#FF2D78]">
        <i data-lucide="search-x" class="w-6 h-6"></i>
      </div>
      <h3 class="text-base font-bold text-white mb-1">No services found</h3>
      <p class="text-xs text-[#6C6C8A] mb-4">Try clearing your search query or choosing another category.</p>
      <a href="/services" class="smm-btn-dark px-4 py-2 text-xs">Show All Services</a>
    </div>
  <?php else: ?>
    <?php foreach ($groupedServices as $categoryName => $catServices): ?>
      <div class="smm-card overflow-hidden">
        <!-- Category Banner Header -->
        <div class="px-5 py-3.5 bg-gradient-to-r from-[#18182D] to-[#131322] border-b border-white/5 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-[#FF2D78]"></span>
            <h2 class="text-sm font-black text-white uppercase tracking-wider"><?= e($categoryName) ?></h2>
          </div>
          <span class="text-xs text-[#9D9DB8] font-bold"><?= count($catServices) ?> Services</span>
        </div>

        <div class="overflow-x-auto">
          <table class="smm-table">
            <thead>
              <tr>
                <th class="w-16">ID</th>
                <th>Service Name</th>
                <th>Rate / 1k</th>
                <th>Min / Max</th>
                <th>Speed & Guarantee</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($catServices as $srv): ?>
                <tr>
                  <td class="font-mono text-xs font-bold text-[#FF2D78]">
                    #<?= (int)$srv['id'] ?>
                  </td>
                  <td class="max-w-md">
                    <div class="font-bold text-white text-xs mb-1">
                      <?= e($srv['name']) ?>
                    </div>
                    <?php if (!empty($srv['description'])): ?>
                      <p class="text-[11px] text-[#9D9DB8] line-clamp-1">
                        <?= e(strip_tags($srv['description'])) ?>
                      </p>
                    <?php endif; ?>
                  </td>
                  <td class="font-bold text-white font-mono text-xs whitespace-nowrap">
                    <span class="text-[#FF2D78] font-black text-sm"><?= format_price($srv['price_per_k'] ?? $srv['rate']) ?></span>
                  </td>
                  <td class="text-xs text-[#9D9DB8] whitespace-nowrap">
                    <span class="text-white"><?= number_format($srv['min_quantity']) ?></span> / 
                    <span class="text-white"><?= number_format($srv['max_quantity']) ?></span>
                  </td>
                  <td class="whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                      <span class="px-2 py-0.5 rounded-md bg-[#10B981]/15 text-[#34D399] font-bold text-[10px]">Instant</span>
                      <?php if (!empty($srv['refill']) || !empty($srv['refill_enabled'])): ?>
                        <span class="px-2 py-0.5 rounded-md bg-[#3B82F6]/15 text-[#60A5FA] font-bold text-[10px]">Refill</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="whitespace-nowrap">
                    <a href="/order?service_id=<?= (int)$srv['id'] ?>" class="smm-btn-pink px-3.5 py-1.5 text-xs">
                      <span>Order</span>
                      <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
