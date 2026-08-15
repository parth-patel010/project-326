<?php
declare(strict_types=1);

/** @var string $active */
$active = $active ?? '';

$nav = [
    [
        'label' => 'Overview',
        'items' => [
            ['href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard'],
            ['href' => 'online-orders.php', 'icon' => 'smartphone', 'label' => 'Online Orders'],
            ['href' => 'users.php', 'icon' => 'group', 'label' => 'Customers'],
        ],
    ],
    [
        'label' => 'Hotels',
        'items' => [
            ['href' => 'hotels.php', 'icon' => 'restaurant', 'label' => 'Hotels'],
            ['href' => 'hotels-add.php', 'icon' => 'add_business', 'label' => 'Add Hotel'],
        ],
    ],
    [
        'label' => 'Delivery',
        'items' => [
            ['href' => 'delivery-partners.php', 'icon' => 'two_wheeler', 'label' => 'Delivery Partners'],
            ['href' => 'delivery-partner-add.php', 'icon' => 'person_add', 'label' => 'Add Partner'],
        ],
    ],
    [
        'label' => 'Payouts',
        'items' => [
            ['href' => 'hotel-payouts.php', 'icon' => 'payments', 'label' => 'Hotel Payouts'],
            ['href' => 'partner-payouts.php', 'icon' => 'account_balance_wallet', 'label' => 'Partner Payouts'],
            ['href' => 'cod-holds.php', 'icon' => 'savings', 'label' => 'COD Holds'],
        ],
    ],
    [
        'label' => 'Marketing',
        'items' => [
            ['href' => 'user-notifications.php', 'icon' => 'campaign', 'label' => 'User notifications'],
        ],
    ],
    [
        'label' => 'Platform',
        'items' => [
            ['href' => 'settings.php', 'icon' => 'settings', 'label' => 'Settings'],
        ],
    ],
];
?>
<aside id="saSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col shadow-lg -translate-x-full lg:translate-x-0 transition-transform duration-200">
  <div class="p-5 flex items-center gap-3 border-b border-gray-100">
    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0">
      <span class="material-icons-outlined text-white text-[22px]">eco</span>
    </div>
    <div class="min-w-0">
      <h1 class="font-bold text-lg text-gray-900 leading-tight truncate">FoodMitra</h1>
      <p class="text-xs text-gray-500">Super Admin</p>
    </div>
  </div>

  <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto">
    <?php foreach ($nav as $group): ?>
      <div>
        <p class="px-3 mb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wider"><?= sa_h($group['label']) ?></p>
        <div class="space-y-0.5">
          <?php foreach ($group['items'] as $item):
              $isActive = ($active === $item['href']);
              $cls = $isActive
                  ? 'sidebar-item active text-white'
                  : 'sidebar-item text-gray-600';
          ?>
            <a href="<?= sa_h($item['href']) ?>" class="<?= $cls ?> flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium">
              <span class="material-icons-outlined text-[20px]"><?= sa_h($item['icon']) ?></span>
              <span><?= sa_h($item['label']) ?></span>
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
<div id="saSidebarBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="window.saCloseSidebar && saCloseSidebar()"></div>
