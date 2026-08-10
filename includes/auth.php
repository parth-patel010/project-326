<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/lib/admin_db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ha_require_login(): void
{
    if (empty($_SESSION['ha_user_id']) || empty($_SESSION['ha_hotel_id'])) {
        header('Location: hotel-login.php');
        exit;
    }
}

function ha_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Cached SHOW COLUMNS check — missing migrate columns must not crash admin pages. */
function ha_col_exists(string $table, string $column, ?PDO $pdo = null): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $pdo = $pdo ?? admin_db();
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
    if ($table === '' || $column === '') {
        return $cache[$key] = false;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        $cache[$key] = (bool) ($stmt && $stmt->fetch());
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function ha_hotel(): ?array
{
    if (empty($_SESSION['ha_hotel_id'])) {
        return null;
    }
    $stmt = admin_db()->prepare('SELECT * FROM hotels WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['ha_hotel_id']]);
    return $stmt->fetch() ?: null;
}

function ha_user(): ?array
{
    if (empty($_SESSION['ha_user_id'])) {
        return null;
    }
    $stmt = admin_db()->prepare('SELECT * FROM hotel_users WHERE id = :id AND is_active = 1');
    $stmt->execute([':id' => $_SESSION['ha_user_id']]);
    return $stmt->fetch() ?: null;
}

function ha_render_sidebar(string $active, string $hotelName): void
{
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

    echo '<aside id="haSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col shadow-lg -translate-x-full lg:translate-x-0 transition-transform duration-200">';
    echo '<div class="p-5 flex items-center gap-3 border-b border-gray-100">';
    echo '<div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0"><span class="material-icons-outlined text-white text-[22px]">restaurant</span></div>';
    echo '<div class="min-w-0"><h1 class="font-bold text-lg text-gray-900 leading-tight truncate">FoodMitra</h1>';
    echo '<p class="text-xs text-gray-500 truncate">' . ha_h($hotelName) . '</p></div></div>';
    echo '<nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto">';
    foreach ($nav as $group) {
        echo '<div><p class="px-3 mb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">' . ha_h($group['label']) . '</p><div class="space-y-0.5">';
        foreach ($group['items'] as $item) {
            $cls = ($active === $item['href']) ? 'sidebar-item active text-white' : 'sidebar-item text-gray-600';
            echo '<a href="' . ha_h($item['href']) . '" class="' . $cls . ' flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium">';
            echo '<span class="material-icons-outlined text-[20px]">' . ha_h($item['icon']) . '</span><span>' . ha_h($item['label']) . '</span></a>';
        }
        echo '</div></div>';
    }
    echo '<a href="logout.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 mt-2">';
    echo '<span class="material-icons-outlined text-[20px]">logout</span><span>Logout</span></a>';
    echo '</nav></aside>';
    echo '<div id="haSidebarBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden" onclick="window.haCloseSidebar && haCloseSidebar()"></div>';
}

/**
 * @param string $title Page title
 * @param string $active Active nav href
 * @param string $subtitle Optional subtitle
 */
function ha_layout_start(string $title, string $active = '', string $subtitle = ''): void
{
    $hotel = ha_hotel();
    $user = ha_user();
    $hotelName = (string) ($hotel['name'] ?? 'Hotel');
    $adminName = (string) ($user['name'] ?? $hotelName);
    $adminEmail = (string) ($user['email'] ?? '');
    $active = $active;

    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . ha_h($title) . ' · FoodMitra Hotel</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: "#195510",
              "primary-hover": "#1F7A32",
              "primary-soft": "#e8f5e9",
              "content-bg": "#f9fafb"
            },
            fontFamily: { sans: ["Inter", "sans-serif"] }
          }
        }
      };
    </script>';
    echo '<style>
      .sidebar-item.active { background-color: #195510; color: #fff; }
      .sidebar-item:hover:not(.active) { background-color: #f3f4f6; color: #111827; }
      #profileDropdown { opacity: 0; transform: translateY(8px); pointer-events: none; transition: opacity .2s ease, transform .2s ease; }
      #profileDropdown.open { opacity: 1; transform: translateY(0); pointer-events: auto; }
      .card { background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1.25rem; margin-bottom:1rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
      .card h3 { font-size:1rem; font-weight:700; color:#111827; margin:0 0 1rem; }
      label { display:block; font-size:0.8125rem; font-weight:600; color:#374151; margin:0 0 0.35rem; }
      .input, select.input, textarea.input, .card select, .card input[type=text], .card input[type=email], .card input[type=password], .card input[type=number], .card textarea, .card select {
        width:100%; padding:0.65rem 0.85rem; border:1px solid #d1d5db; border-radius:0.5rem; font-size:0.875rem; margin-bottom:0.75rem; background:#fff; color:#111827;
      }
      .input:focus, .card select:focus, .card input:focus, .card textarea:focus {
        outline:none; border-color:#195510; box-shadow:0 0 0 3px rgba(25,85,16,.15);
      }
      .btn { display:inline-flex; align-items:center; justify-content:center; gap:0.35rem; background:#195510; color:#fff; border:0; border-radius:0.5rem; padding:0.65rem 1rem; font-weight:600; font-size:0.875rem; cursor:pointer; text-decoration:none; }
      .btn:hover { background:#1F7A32; }
      .btn.secondary { background:#fff; color:#195510; border:1px solid #195510; }
      .btn.secondary:hover { background:#e8f5e9; }
      .btn.danger { background:#dc2626; }
      .flash { background:#e8f5e9; border:1px solid #a5d6a7; color:#14532d; padding:0.75rem 1rem; border-radius:0.5rem; margin-bottom:1rem; font-size:0.875rem; font-weight:500; }
      .muted { color:#6b7280; font-size:0.8125rem; }
      .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1rem; }
      .stat { background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
      .stat .n { font-size:1.75rem; font-weight:700; color:#195510; line-height:1.2; }
      .stat .l { color:#6b7280; font-size:0.8125rem; margin-top:0.25rem; font-weight:500; }
      table { width:100%; border-collapse:collapse; font-size:0.875rem; }
      th, td { padding:0.75rem 1rem; border-bottom:1px solid #f3f4f6; text-align:left; vertical-align:middle; }
      th { font-size:0.7rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; background:#f9fafb; }
      tbody tr:hover { background:#f9fafb; }
      .otp { font-size:1.75rem; font-weight:800; letter-spacing:0.35em; color:#195510; font-variant-numeric:tabular-nums; }
    </style></head>';
    echo '<body class="bg-content-bg font-sans text-gray-900 min-h-screen flex antialiased">';

    ha_render_sidebar($active, $hotelName);

    echo '<main class="flex-1 lg:ml-64 min-h-screen flex flex-col w-full">';
    echo '<header class="bg-white border-b border-gray-200 h-14 flex items-center justify-between px-4 sm:px-6 shrink-0 sticky top-0 z-30">';
    echo '<div class="flex items-center gap-3 flex-1">';
    echo '<button type="button" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600" onclick="haOpenSidebar()" aria-label="Open menu">';
    echo '<span class="material-icons-outlined">menu</span></button>';
    echo '<div class="hidden sm:flex items-center gap-2 text-sm text-gray-500">';
    echo '<span class="material-icons-outlined text-primary text-[20px]">storefront</span>';
    echo '<span class="font-medium text-gray-800">' . ha_h($hotelName) . '</span>';
    echo '</div></div>';

    echo '<div class="flex items-center gap-2 relative">';
    echo '<div id="profileTrigger" class="flex items-center gap-3 pl-2 ml-1 cursor-pointer group select-none" role="button" tabindex="0">';
    echo '<div class="relative"><div class="w-9 h-9 rounded-full bg-primary-soft flex items-center justify-center">';
    echo '<span class="material-icons-outlined text-primary text-[20px]">person</span></div>';
    echo '<span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white"></span></div>';
    echo '<div class="text-left hidden md:block"><p class="text-sm font-semibold text-gray-800 leading-tight">' . ha_h($adminName) . '</p>';
    echo '<p class="text-xs text-gray-500 leading-tight">' . ha_h($adminEmail) . '</p></div>';
    echo '<span class="material-icons-outlined text-gray-500">expand_more</span></div>';
    echo '<div id="profileDropdown" class="absolute right-0 top-full mt-1 w-64 bg-white rounded-xl shadow-lg border border-gray-200 py-3 z-50">';
    echo '<div class="px-4 pb-3 border-b border-gray-100"><p class="text-sm font-semibold text-gray-800">' . ha_h($adminName) . '</p>';
    echo '<p class="text-xs text-gray-500">' . ha_h($hotelName) . '</p></div>';
    echo '<a href="logout.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"><span class="material-icons-outlined text-[18px]">logout</span> Sign out</a>';
    echo '</div></div></header>';

    echo '<div class="bg-gray-50/80 border-b border-gray-200 px-4 sm:px-8 py-4 shrink-0">';
    echo '<h2 class="text-lg font-semibold text-gray-900">' . ha_h($title) . '</h2>';
    if ($subtitle !== '') {
        echo '<p class="text-sm text-gray-500 mt-0.5">' . ha_h($subtitle) . '</p>';
    }
    echo '</div>';

    echo '<div class="flex-1 p-4 sm:p-6 lg:p-8 overflow-auto">';
}

function ha_layout_end(): void
{
    echo '</div></main>';
    echo '<script>
      function haOpenSidebar(){
        document.getElementById("haSidebar").classList.remove("-translate-x-full");
        document.getElementById("haSidebarBackdrop").classList.remove("hidden");
      }
      function haCloseSidebar(){
        document.getElementById("haSidebar").classList.add("-translate-x-full");
        document.getElementById("haSidebarBackdrop").classList.add("hidden");
      }
      (function(){
        var trigger = document.getElementById("profileTrigger");
        var drop = document.getElementById("profileDropdown");
        if (!trigger || !drop) return;
        trigger.addEventListener("click", function(e){ e.stopPropagation(); drop.classList.toggle("open"); });
        document.addEventListener("click", function(){ drop.classList.remove("open"); });
      })();
    </script>';
    echo '</body></html>';
}
