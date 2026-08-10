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

function sa_layout_start(string $title, string $active = ''): void
{
    $user = sa_user();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . sa_h($title) . ' · FoodMitra Super Admin</title>';
    echo '<style>
:root{--g:#195510;--g2:#1F7A32;--bg:#f4f6f4;--card:#fff;--bd:#e5e8e5;--tx:#1a1a1a;--muted:#6b6b6b}
*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,system-ui,sans-serif;background:var(--bg);color:var(--tx)}
.wrap{display:flex;min-height:100vh}.side{width:240px;background:#12350c;color:#fff;padding:20px 14px;flex-shrink:0}
.side .brand{font-weight:800;font-size:18px;margin-bottom:24px;padding:0 8px}
.side a{display:block;color:#d7ecd7;text-decoration:none;padding:10px 12px;border-radius:10px;margin-bottom:4px;font-size:14px;font-weight:600}
.side a:hover,.side a.active{background:rgba(255,255,255,.12);color:#fff}
.main{flex:1;padding:24px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
h1{margin:0;font-size:22px}.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:16px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.stat{background:linear-gradient(135deg,#e8f5e9,#fff);border:1px solid #c8e6c9;border-radius:14px;padding:16px}
.stat .n{font-size:28px;font-weight:800;color:var(--g)}.stat .l{color:var(--muted);font-size:13px;margin-top:4px}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 8px;border-bottom:1px solid var(--bd);text-align:left}
th{font-size:12px;color:var(--muted);text-transform:uppercase}
.btn{display:inline-block;background:var(--g);color:#fff;border:0;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none;font-size:14px}
.btn.secondary{background:#fff;color:var(--g);border:1px solid var(--g)}
.btn.danger{background:#c62828}.input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;font-size:14px;margin-bottom:10px}
label{font-size:13px;font-weight:700;display:block;margin-bottom:4px}.muted{color:var(--muted);font-size:13px}
.flash{background:#e8f5e9;border:1px solid #a5d6a7;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}.tabs a{padding:8px 12px;border-radius:20px;border:1px solid var(--bd);text-decoration:none;color:var(--tx);font-size:13px;font-weight:600}
.tabs a.active{background:var(--g);color:#fff;border-color:var(--g)}
@media(max-width:800px){.wrap{flex-direction:column}.side{width:100%}}
</style></head><body><div class="wrap"><aside class="side"><div class="brand">FoodMitra Super</div>';
    $links = [
        'dashboard.php' => 'Dashboard',
        'online-orders.php' => 'Online Orders',
        'hotels.php' => 'Hotels',
        'hotels-add.php' => 'Add Hotel',
        'delivery-partners.php' => 'Delivery Partners',
        'delivery-partner-add.php' => 'Add Partner',
        'hotel-payouts.php' => 'Hotel Payouts',
        'partner-payouts.php' => 'Partner Payouts',
        'cod-holds.php' => 'COD Holds',
        'settings.php' => 'Settings',
        'logout.php' => 'Logout',
    ];
    foreach ($links as $href => $label) {
        $cls = ($active === $href) ? ' active' : '';
        echo '<a class="' . trim($cls) . '" href="' . $href . '">' . sa_h($label) . '</a>';
    }
    echo '</aside><main class="main"><div class="top"><h1>' . sa_h($title) . '</h1>';
    echo '<div class="muted">' . sa_h($user['name'] ?? '') . '</div></div>';
}

function sa_layout_end(): void
{
    echo '</main></div></body></html>';
}
