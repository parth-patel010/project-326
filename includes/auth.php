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

/** @return list<array{label:string,items:list<array{href:string,icon:string,label:string}>}> */
function ha_nav_groups(): array
{
    return [
        [
            'label' => 'Orders',
            'items' => [
                ['href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard'],
                ['href' => 'online-orders.php', 'icon' => 'shopping_bag', 'label' => 'Online Orders'],
                ['href' => 'pos-orders.php', 'icon' => 'receipt_long', 'label' => 'POS Orders'],
                ['href' => 'pos-billing.php', 'icon' => 'point_of_sale', 'label' => 'New bill'],
            ],
        ],
        [
            'label' => 'Menu',
            'items' => [
                ['href' => 'categories.php', 'icon' => 'folder', 'label' => 'Categories'],
                ['href' => 'menu-items.php', 'icon' => 'list', 'label' => 'Menu items'],
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
}

function ha_render_sidebar(string $active, string $hotelName): void
{
    $active = $active !== '' ? $active : '';
    $hotelName = $hotelName;
    $nav = ha_nav_groups();
    require dirname(__DIR__) . '/partials/sidebar.php';
}

/**
 * @param string $title Page title
 * @param string $active Active nav href (e.g. pos-billing.php)
 * @param string $subtitle Optional subtitle
 */
function ha_layout_start(string $title, string $active = '', string $subtitle = ''): void
{
    $hotel = ha_hotel();
    $user = ha_user();
    $hotelName = (string) ($hotel['name'] ?? 'Hotel');
    $adminName = (string) ($user['name'] ?? $hotelName);
    $adminEmail = (string) ($user['email'] ?? '');
    $isOpen = !isset($hotel['is_open']) || (int) ($hotel['is_open'] ?? 1) === 1;

    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . ha_h($title) . ' · FoodMitra Hotel</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: "#195510",
              "primary-hover": "#1F7A32",
              "primary-soft": "#e8f5e9",
              "sidebar-bg": "#ffffff",
              "content-bg": "#f9fafb",
              "text-main": "#1f2937",
              "text-muted": "#6b7280"
            },
            fontFamily: { sans: ["DM Sans", "system-ui", "sans-serif"] }
          }
        }
      };
    </script>';
    echo '<style>
      .material-symbols-outlined { font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24; }
      .sidebar-item.active { background-color: #195510; color: #fff; }
      .sidebar-item:hover:not(.active) { background-color: #f3f4f6; color: #111827; }
      #haSidebar.sidebar-collapsed { width: 4.5rem; }
      #haSidebar.sidebar-collapsed .sb-nav-label,
      #haSidebar.sidebar-collapsed .sb-brand-text,
      #haSidebar.sidebar-collapsed .sb-group-label { display: none; }
      #haSidebar.sidebar-collapsed .sb-brand-wrap { justify-content: center; }
      #haSidebar.sidebar-collapsed .sb-nav-link { justify-content: center; padding-left: 0.75rem; padding-right: 0.75rem; }
      @media (min-width: 1024px) {
        body.ha-sidebar-collapsed .ha-main { margin-left: 4.5rem !important; }
      }
      #profileDropdown { opacity: 0; transform: translateY(8px); pointer-events: none; transition: opacity .2s ease, transform .2s ease; }
      #profileDropdown.open { opacity: 1; transform: translateY(0); pointer-events: auto; }
      .card { background:#fff; border:1px solid #f3f4f6; border-radius:0.75rem; padding:1.5rem; margin-bottom:1.5rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
      .card h3 { font-size:1rem; font-weight:700; color:#1f2937; margin:0 0 1rem; display:flex; align-items:center; gap:0.5rem; }
      .card-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
      .card-header h3 { margin:0; }
      label { display:block; font-size:0.8125rem; font-weight:600; color:#374151; margin:0 0 0.35rem; }
      .input, select.input, textarea.input, .card select, .card input[type=text], .card input[type=email], .card input[type=password], .card input[type=number], .card input[type=tel], .card input[type=url], .card input[type=time], .card textarea, .card select {
        width:100%; padding:0.65rem 0.85rem; border:1px solid #d1d5db; border-radius:0.5rem; font-size:0.875rem; margin-bottom:0.75rem; background:#fff; color:#1f2937;
      }
      .input:focus, .card select:focus, .card input:focus, .card textarea:focus {
        outline:none; border-color:#195510; box-shadow:0 0 0 3px rgba(25,85,16,.15);
      }
      .btn { display:inline-flex; align-items:center; justify-content:center; gap:0.35rem; background:#195510; color:#fff; border:0; border-radius:0.5rem; padding:0.65rem 1rem; font-weight:600; font-size:0.875rem; cursor:pointer; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,.06); transition:background .15s ease; }
      .btn:hover { background:#1F7A32; }
      .btn.secondary { background:#fff; color:#195510; border:1px solid #d1d5db; box-shadow:none; }
      .btn.secondary:hover { background:#e8f5e9; border-color:#195510; }
      .btn.ghost { background:transparent; color:#195510; border:1px dashed #195510; box-shadow:none; }
      .btn.ghost:hover { background:#e8f5e9; }
      .btn.danger { background:#dc2626; }
      .btn.danger:hover { background:#b91c1c; }
      .btn.sm { padding:0.4rem 0.75rem; font-size:0.8125rem; }
      .flash { background:#e8f5e9; border:1px solid #a5d6a7; color:#14532d; padding:0.75rem 1rem; border-radius:0.5rem; margin-bottom:1rem; font-size:0.875rem; font-weight:500; }
      .flash-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:0.5rem; margin-bottom:1rem; font-size:0.875rem; font-weight:500; }
      .muted { color:#6b7280; font-size:0.8125rem; }
      .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1.25rem; margin-bottom:1.5rem; }
      .stat { background:#fff; border:1px solid #f3f4f6; border-radius:0.75rem; padding:1.25rem 1.5rem; box-shadow:0 1px 2px rgba(0,0,0,.04); position:relative; overflow:hidden; }
      .stat .n { font-size:1.75rem; font-weight:700; color:#195510; line-height:1.2; }
      .stat .l { color:#6b7280; font-size:0.8125rem; margin-top:0.25rem; font-weight:500; }
      .stat .stat-icon { position:absolute; right:1rem; top:1rem; width:2.5rem; height:2.5rem; border-radius:0.75rem; background:#e8f5e9; color:#195510; display:flex; align-items:center; justify-content:center; }
      .page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
      .page-header h2 { font-size:1.5rem; font-weight:700; color:#1f2937; margin:0; line-height:1.25; }
      .page-header .sub { color:#6b7280; font-size:0.875rem; margin-top:0.25rem; }
      .badge { display:inline-flex; align-items:center; gap:0.25rem; padding:0.2rem 0.55rem; border-radius:9999px; font-size:0.7rem; font-weight:600; letter-spacing:.02em; }
      .badge-green { background:#e8f5e9; color:#14532d; }
      .badge-amber { background:#fffbeb; color:#92400e; }
      .badge-red { background:#fef2f2; color:#991b1b; }
      .badge-blue { background:#eff6ff; color:#1e40af; }
      .badge-gray { background:#f3f4f6; color:#4b5563; }
      .empty-state { text-align:center; padding:2.5rem 1.5rem; color:#6b7280; }
      .empty-state .material-symbols-outlined { font-size:2.5rem; color:#d1d5db; margin-bottom:0.5rem; }
      table { width:100%; border-collapse:collapse; font-size:0.875rem; }
      th, td { padding:0.85rem 1rem; border-bottom:1px solid #f3f4f6; text-align:left; vertical-align:middle; }
      th { font-size:0.7rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; background:#f9fafb; }
      tbody tr:hover { background:#f9fafb; }
      .otp { font-size:1.75rem; font-weight:800; letter-spacing:0.35em; color:#195510; font-variant-numeric:tabular-nums; }
      .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #b8bcc4 #f3f4f6; }
      .thin-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
      .thin-scrollbar::-webkit-scrollbar-thumb { background: #b8bcc4; border-radius: 0; }
    </style></head>';
    echo '<body class="bg-content-bg font-sans text-text-main h-screen overflow-hidden flex antialiased">';

    ha_render_sidebar($active, $hotelName);

    echo '<main class="ha-main flex-1 lg:ml-64 min-h-0 flex flex-col w-full transition-[margin] duration-200">';
    echo '<header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 sm:px-6 shrink-0 z-30">';
    echo '<div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">';
    echo '<button type="button" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-text-muted" onclick="haOpenSidebar()" aria-label="Open menu">';
    echo '<span class="material-symbols-outlined">menu</span></button>';
    echo '<button type="button" id="sidebarCollapseBtn" class="hidden lg:inline-flex p-2 rounded-lg hover:bg-gray-100 text-text-muted" onclick="haToggleCollapse()" aria-label="Collapse sidebar" title="Collapse menu">';
    echo '<span class="material-symbols-outlined">menu</span></button>';
    echo '<div class="min-w-0"><p class="text-sm font-semibold text-text-main truncate">' . ha_h($title) . '</p>';
    if ($subtitle !== '') {
        echo '<p class="text-xs text-text-muted truncate">' . ha_h($subtitle) . '</p>';
    } else {
        echo '<p class="text-xs text-text-muted truncate hidden sm:block">Hotel Admin</p>';
    }
    echo '</div></div>';

    echo '<div class="flex items-center gap-2 sm:gap-4 shrink-0">';
    echo '<button type="button" id="statusToggleBtn" onclick="toggleHotelStatus()" title="Click to go ' . ($isOpen ? 'Offline' : 'Online') . '" class="px-3 md:px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-300 flex items-center gap-2 shrink-0 ' . ($isOpen ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-gray-400 hover:bg-gray-500 text-white') . '">';
    echo '<span id="statusDot" class="w-2 h-2 rounded-full ' . ($isOpen ? 'bg-white animate-pulse' : 'bg-gray-200') . '"></span>';
    echo '<span id="statusText" class="hidden sm:inline">' . ($isOpen ? 'Online' : 'Offline') . '</span></button>';
    echo '<div class="text-right hidden md:block min-w-0">';
    echo '<p class="text-sm font-semibold text-text-main truncate max-w-[160px]">' . ha_h($hotelName) . '</p>';
    echo '<p class="text-xs text-text-muted">Hotel Admin</p></div>';

    echo '<div class="relative">';
    echo '<div id="profileTrigger" class="flex items-center gap-2 cursor-pointer select-none p-1 rounded-lg hover:bg-gray-50" role="button" tabindex="0">';
    echo '<div class="w-9 h-9 rounded-full bg-primary-soft flex items-center justify-center">';
    echo '<span class="material-symbols-outlined text-primary text-[20px]">person</span></div>';
    echo '<span class="material-symbols-outlined text-text-muted text-[20px] hidden sm:inline">expand_more</span></div>';
    echo '<div id="profileDropdown" class="absolute right-0 top-full mt-1 w-64 bg-white rounded-xl shadow-lg border border-gray-200 py-3 z-50">';
    echo '<div class="px-4 pb-3 border-b border-gray-100"><p class="text-sm font-semibold text-text-main">' . ha_h($adminName) . '</p>';
    echo '<p class="text-xs text-text-muted truncate">' . ha_h($adminEmail !== '' ? $adminEmail : $hotelName) . '</p></div>';
    echo '<a href="hotel-settings.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-main hover:bg-gray-50"><span class="material-symbols-outlined text-[18px]">settings</span> Settings</a>';
    echo '<a href="logout.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-main hover:bg-gray-50"><span class="material-symbols-outlined text-[18px]">logout</span> Sign out</a>';
    echo '</div></div></div></header>';

    echo '<div class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto thin-scrollbar min-h-0">';
}

function ha_layout_end(): void
{
    echo '</div></main>';
    echo '<script>
      function haOpenSidebar(){
        var s = document.getElementById("haSidebar");
        var b = document.getElementById("haSidebarBackdrop");
        if (s) s.classList.remove("-translate-x-full");
        if (b) b.classList.remove("hidden");
      }
      function haCloseSidebar(){
        var s = document.getElementById("haSidebar");
        var b = document.getElementById("haSidebarBackdrop");
        if (s) s.classList.add("-translate-x-full");
        if (b) b.classList.add("hidden");
      }
      function haToggleCollapse(){
        var s = document.getElementById("haSidebar");
        if (!s || window.innerWidth < 1024) return;
        var next = !s.classList.contains("sidebar-collapsed");
        s.classList.toggle("sidebar-collapsed", next);
        document.body.classList.toggle("ha-sidebar-collapsed", next);
        var btn = document.getElementById("sidebarCollapseBtn");
        if (btn) {
          var icon = btn.querySelector(".material-symbols-outlined");
          if (icon) icon.textContent = next ? "menu_open" : "menu";
        }
        try { localStorage.setItem("fm_ha_sidebar_collapsed", next ? "1" : "0"); } catch (e) {}
      }
      function toggleHotelStatus(){
        var btn = document.getElementById("statusToggleBtn");
        if (btn) { btn.disabled = true; btn.classList.add("opacity-70"); }
        fetch("toggle-hotel-status.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "X-Requested-With": "XMLHttpRequest" }
        }).then(function(r){ return r.json(); }).then(function(data){
          if (!data || !data.success) {
            alert((data && data.message) ? data.message : "Could not update status");
            return;
          }
          var online = !!data.is_online;
          if (btn) {
            btn.className = "px-3 md:px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-300 flex items-center gap-2 shrink-0 " +
              (online ? "bg-green-500 hover:bg-green-600 text-white" : "bg-gray-400 hover:bg-gray-500 text-white");
            btn.title = online ? "Click to go Offline" : "Click to go Online";
          }
          var text = document.getElementById("statusText");
          if (text) text.textContent = online ? "Online" : "Offline";
          var dot = document.getElementById("statusDot");
          if (dot) {
            dot.className = "w-2 h-2 rounded-full " + (online ? "bg-white animate-pulse" : "bg-gray-200");
          }
          alert(data.message || (online ? "Hotel is now Online" : "Hotel is now Offline"));
        }).catch(function(){
          alert("Connection error while updating status");
        }).finally(function(){
          if (btn) { btn.disabled = false; btn.classList.remove("opacity-70"); }
        });
      }
      (function(){
        try {
          if (localStorage.getItem("fm_ha_sidebar_collapsed") === "1" && window.innerWidth >= 1024) {
            var s = document.getElementById("haSidebar");
            if (s) {
              s.classList.add("sidebar-collapsed");
              document.body.classList.add("ha-sidebar-collapsed");
              var btn = document.getElementById("sidebarCollapseBtn");
              if (btn) {
                var icon = btn.querySelector(".material-symbols-outlined");
                if (icon) icon.textContent = "menu_open";
              }
            }
          }
        } catch (e) {}
        var trigger = document.getElementById("profileTrigger");
        var drop = document.getElementById("profileDropdown");
        if (!trigger || !drop) return;
        trigger.addEventListener("click", function(e){ e.stopPropagation(); drop.classList.toggle("open"); });
        document.addEventListener("click", function(){ drop.classList.remove("open"); });
      })();
    </script>';
    echo '<script src="js/order-notification.js" defer></script>';
    echo '</body></html>';
}
