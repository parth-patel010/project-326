<?php

declare(strict_types=1);

// Included from ha_render_sidebar() only.
if (!function_exists('ha_h')) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var string $active */
$active = $active ?? '';
/** @var string $hotelName */
$hotelName = $hotelName ?? 'Hotel';
/** @var list<array{label:string,items:list<array{href:string,icon:string,label:string}>}> $nav */
$nav = $nav ?? (function_exists('ha_nav_groups') ? ha_nav_groups() : []);
?>
<aside id="haSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-sidebar-bg border-r border-gray-200 h-full flex flex-col shrink-0 shadow-lg lg:shadow-none -translate-x-full lg:translate-x-0 transition-all duration-200">
  <div class="p-6 flex items-center gap-3 sb-brand-wrap border-b border-gray-100">
    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0">
      <span class="material-symbols-outlined text-white text-[22px]">restaurant</span>
    </div>
    <div class="sb-brand-text min-w-0">
      <h1 class="font-bold text-lg text-text-main leading-tight truncate">FoodMitra</h1>
      <p class="text-xs text-text-muted truncate"><?= ha_h($hotelName) ?></p>
    </div>
    <button type="button" class="lg:hidden ml-auto text-text-muted hover:text-text-main shrink-0" onclick="haCloseSidebar()" aria-label="Close menu">
      <span class="material-symbols-outlined">close</span>
    </button>
  </div>
  <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto thin-scrollbar">
    <?php foreach ($nav as $group): ?>
      <div>
        <p class="sb-group-label px-3 mb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wider"><?= ha_h($group['label']) ?></p>
        <div class="space-y-0.5">
          <?php foreach ($group['items'] as $item):
              $isActive = ($active === $item['href']);
              $cls = $isActive ? 'sidebar-item active text-white' : 'sidebar-item text-text-muted';
          ?>
            <a href="<?= ha_h($item['href']) ?>" class="<?= $cls ?> sb-nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors" title="<?= ha_h($item['label']) ?>">
              <span class="material-symbols-outlined text-[20px] shrink-0"><?= ha_h($item['icon']) ?></span>
              <span class="sb-nav-label truncate"><?= ha_h($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="logout.php" class="sidebar-item sb-nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-text-muted mt-2" title="Logout" onclick="return confirm('Sign out of hotel admin?');">
      <span class="material-symbols-outlined text-[20px] shrink-0">logout</span>
      <span class="sb-nav-label">Logout</span>
    </a>
  </nav>
</aside>
<div id="haSidebarBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="haCloseSidebar()"></div>
