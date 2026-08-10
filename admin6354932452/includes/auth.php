<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/lib/admin_db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function sa_require_login(): void
{
    if (empty($_SESSION['sa_user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function sa_user(): ?array
{
    if (empty($_SESSION['sa_user_id'])) {
        return null;
    }
    $stmt = admin_db()->prepare('SELECT * FROM admin_users WHERE id = :id AND is_active = 1');
    $stmt->execute([':id' => $_SESSION['sa_user_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sa_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function sa_mask_email(string $email): string
{
    $at = strpos($email, '@');
    if ($at === false || $at < 2) {
        return $email;
    }
    return substr($email, 0, 1) . str_repeat('*', min(10, $at - 1)) . substr($email, $at);
}

/**
 * @param string $title Page title
 * @param string $active Active nav href (e.g. dashboard.php)
 * @param string $subtitle Optional subheading under title
 */
function sa_layout_start(string $title, string $active = '', string $subtitle = ''): void
{
    $user = sa_user();
    $adminName = (string) ($user['name'] ?? 'Admin');
    $adminEmail = (string) ($user['email'] ?? '');
    $maskedEmail = sa_mask_email($adminEmail);
    $active = $active; // used in sidebar partial

    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . sa_h($title) . ' · FoodMitra Super Admin</title>';
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
      .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1rem; }
      .stat { background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
      .stat .n { font-size:1.75rem; font-weight:700; color:#195510; line-height:1.2; }
      .stat .l { color:#6b7280; font-size:0.8125rem; margin-top:0.25rem; font-weight:500; }
      table { width:100%; border-collapse:collapse; font-size:0.875rem; }
      th, td { padding:0.75rem 1rem; border-bottom:1px solid #f3f4f6; text-align:left; vertical-align:middle; }
      th { font-size:0.7rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; background:#f9fafb; }
      tbody tr:hover { background:#f9fafb; }
      .tabs { display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; }
      .tabs a { padding:0.45rem 0.85rem; border-radius:0.5rem; border:1px solid #e5e7eb; text-decoration:none; color:#374151; font-size:0.8125rem; font-weight:600; background:#fff; }
      .tabs a.active { background:#195510; color:#fff; border-color:#195510; }
      .sa-alert-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:0.5rem; margin-bottom:1rem; font-size:0.875rem; }
    </style></head>';
    echo '<body class="bg-content-bg font-sans text-gray-900 min-h-screen flex antialiased">';

    require __DIR__ . '/../partials/sidebar.php';

    echo '<main class="flex-1 lg:ml-64 min-h-screen flex flex-col w-full">';
    echo '<header class="bg-white border-b border-gray-200 h-14 flex items-center justify-between px-4 sm:px-6 shrink-0 sticky top-0 z-30">';
    echo '<div class="flex items-center gap-3 flex-1 max-w-xl">';
    echo '<button type="button" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600" onclick="saOpenSidebar()" aria-label="Open menu">';
    echo '<span class="material-icons-outlined">menu</span></button>';
    echo '<div class="relative flex-1 hidden sm:block">';
    echo '<span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>';
    echo '<input type="text" placeholder="Search…" class="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:ring-2 focus:ring-primary/30 focus:bg-white outline-none">';
    echo '</div></div>';

    echo '<div class="flex items-center gap-2 relative">';
    echo '<div id="profileTrigger" class="flex items-center gap-3 pl-2 ml-1 cursor-pointer group select-none" role="button" tabindex="0">';
    echo '<div class="relative"><div class="w-9 h-9 rounded-full bg-primary-soft flex items-center justify-center">';
    echo '<span class="material-icons-outlined text-primary text-[20px]">person</span></div>';
    echo '<span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white"></span></div>';
    echo '<div class="text-left hidden md:block"><p class="text-sm font-semibold text-gray-800 leading-tight">' . sa_h($adminName) . '</p>';
    echo '<p class="text-xs text-gray-500 leading-tight">' . sa_h($maskedEmail) . '</p></div>';
    echo '<span class="material-icons-outlined text-gray-500 group-hover:text-gray-700">expand_more</span></div>';
    echo '<div id="profileDropdown" class="absolute right-0 top-full mt-1 w-64 bg-white rounded-xl shadow-lg border border-gray-200 py-3 z-50">';
    echo '<div class="flex items-center gap-3 px-4 pb-3 border-b border-gray-100">';
    echo '<div class="w-10 h-10 rounded-full bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">person</span></div>';
    echo '<div><p class="text-sm font-semibold text-gray-800">' . sa_h($adminName) . '</p><p class="text-xs text-gray-500">' . sa_h($maskedEmail) . '</p></div></div>';
    echo '<a href="settings.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"><span class="material-icons-outlined text-[18px]">settings</span> Settings</a>';
    echo '<a href="logout.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"><span class="material-icons-outlined text-[18px]">logout</span> Sign out</a>';
    echo '</div></div></header>';

    echo '<div class="bg-gray-50/80 border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between gap-4 shrink-0">';
    echo '<div><h2 class="text-lg font-semibold text-gray-900">' . sa_h($title) . '</h2>';
    if ($subtitle !== '') {
        echo '<p class="text-sm text-gray-500 mt-0.5">' . sa_h($subtitle) . '</p>';
    }
    echo '</div></div>';

    echo '<div class="flex-1 p-4 sm:p-6 lg:p-8 overflow-auto">';
}

function sa_layout_end(): void
{
    echo '</div></main>';
    echo '<script>
      function saOpenSidebar(){
        document.getElementById("saSidebar").classList.remove("-translate-x-full");
        document.getElementById("saSidebarBackdrop").classList.remove("hidden");
      }
      function saCloseSidebar(){
        document.getElementById("saSidebar").classList.add("-translate-x-full");
        document.getElementById("saSidebarBackdrop").classList.add("hidden");
      }
      (function(){
        var trigger = document.getElementById("profileTrigger");
        var drop = document.getElementById("profileDropdown");
        if (!trigger || !drop) return;
        function toggle(){ drop.classList.toggle("open"); }
        trigger.addEventListener("click", function(e){ e.stopPropagation(); toggle(); });
        document.addEventListener("click", function(){ drop.classList.remove("open"); });
      })();
    </script>';
    echo '</body></html>';
}
