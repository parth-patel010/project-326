<?php
declare(strict_types=1);

// Direct browser access is not allowed — included from layout only.
if (!function_exists('ha_h')) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var string $active */
$active = $active ?? '';
$hotelName = $hotelName ?? 'Hotel';

$nav = [
    [
        'label' => 'Orders',
        'items' => [
            ['href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard'],
            ['href' => 'online-orders.php', 'icon' => 'smartphone', 'label' => 'Online Orders'],
            ['href' => 'pos-orders.php', 'icon' => 'point_of_sale', 'label' => 'POS Orders'],
            ['href' => 'pos-billing.php', 'icon' => 'receipt_long', 'label' => 'New bill'],
        ],
    ],
    [
        'label' => 'Menu',
        'items' => [
            ['href' => 'categories.php', 'icon' => 'category', 'label' => 'Categories'],
            ['href' => 'menu-items.php', 'icon' => 'restaurant_menu', 'label' => 'Menu items'],
        ],
    ],
    [
        'label' => 'Marketing',
        'items' => [
            ['href' => 'offers.php', 'icon' => 'local_offer', 'label' => 'Offers'],
            ['href' => 'discount-settings.php', 'icon' => 'percent', 'label' => 'Discounts'],
        ],
    ],
    [
        'label' => 'Hotel',
        'items' => [
            ['href' => 'analytics.php', 'icon' => 'analytics', 'label' => 'Analytics'],
            ['href' => 'hotel-settings.php', 'icon' => 'settings', 'label' => 'Settings'],
        ],
    ],
];
?>
<aside id="haSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col shadow-lg -translate-x-full lg:translate-x-0 transition-transform duration-200">
  <div class="p-5 flex items-center gap-3 border-b border-gray-100">
    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0">
      <span class="material-icons-outlined text-white text-[22px]">restaurant</span>
    </div>
    <div class="min-w-0">
      <h1 class="font-bold text-lg text-gray-900 leading-tight truncate">FoodMitra</h1>
      <p class="text-xs text-gray-500 truncate"><?= ha_h($hotelName) ?></p>
    </div>
  </div>
  <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto">
    <?php foreach ($nav as $group): ?>
      <div>
        <p class="px-3 mb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wider"><?= ha_h($group['label']) ?></p>
        <div class="space-y-0.5">
          <?php foreach ($group['items'] as $item):
              $isActive = ($active === $item['href']);
              $cls = $isActive ? 'sidebar-item active text-white' : 'sidebar-item text-gray-600';
          ?>
            <a href="<?= ha_h($item['href']) ?>" class="<?= $cls ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium">
              <span class="material-icons-outlined text-[20px]"><?= ha_h($item['icon']) ?></span>
              <span><?= ha_h($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="logout.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 mt-2">
      <span class="material-icons-outlined text-[20px]">logout</span>
      <span>Logout</span>
    </a>
  </nav>
</aside>
<div id="haSidebarBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="window.haCloseSidebar && haCloseSidebar()"></div>
