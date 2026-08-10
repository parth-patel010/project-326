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

function ha_hotel(): ?array
{
    if (empty($_SESSION['ha_hotel_id'])) return null;
    $stmt = admin_db()->prepare('SELECT * FROM hotels WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['ha_hotel_id']]);
    return $stmt->fetch() ?: null;
}

function ha_layout_start(string $title, string $active = ''): void
{
    $hotel = ha_hotel();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . ha_h($title) . ' · Hotel Admin</title>';
    echo '<style>
:root{--g:#195510;--bg:#f4f6f4;--card:#fff;--bd:#e5e8e5;--muted:#6b6b6b}
*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,system-ui,sans-serif;background:var(--bg)}
.wrap{display:flex;min-height:100vh}.side{width:220px;background:#143d0e;color:#fff;padding:18px 12px}
.brand{font-weight:800;margin-bottom:8px;padding:0 8px}.sub{font-size:12px;opacity:.8;padding:0 8px 18px}
.side a{display:block;color:#d7ecd7;text-decoration:none;padding:10px 12px;border-radius:10px;margin-bottom:4px;font-weight:600;font-size:14px}
.side a.active,.side a:hover{background:rgba(255,255,255,.12);color:#fff}
.main{flex:1;padding:22px}h1{margin:0 0 16px;font-size:22px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.stat{background:linear-gradient(135deg,#e8f5e9,#fff);border:1px solid #c8e6c9;border-radius:14px;padding:16px}
.stat .n{font-size:28px;font-weight:800;color:var(--g)}.stat .l{color:var(--muted);font-size:13px}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 8px;border-bottom:1px solid var(--bd);text-align:left}
.btn{display:inline-block;background:var(--g);color:#fff;border:0;border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;text-decoration:none;font-size:13px}
.btn.secondary{background:#fff;color:var(--g);border:1px solid var(--g)}
.input,select,textarea{width:100%;padding:10px;border:1px solid var(--bd);border-radius:10px;margin-bottom:10px}
label{font-weight:700;font-size:13px;display:block;margin-bottom:4px}
.otp{font-size:28px;font-weight:800;letter-spacing:6px;color:var(--g)}
.flash{background:#e8f5e9;border:1px solid #a5d6a7;padding:10px;border-radius:10px;margin-bottom:12px}
.muted{color:var(--muted);font-size:13px}
</style></head><body><div class="wrap"><aside class="side">';
    echo '<div class="brand">FoodMitra Hotel</div><div class="sub">' . ha_h($hotel['name'] ?? '') . '</div>';
    $links = [
        'dashboard.php' => 'Dashboard',
        'online-orders.php' => 'Online Orders',
        'pos-orders.php' => 'POS Orders',
        'offers.php' => 'Offers',
        'discount-settings.php' => 'Discounts',
        'logout.php' => 'Logout',
    ];
    foreach ($links as $href => $label) {
        $cls = $active === $href ? 'active' : '';
        echo '<a class="' . $cls . '" href="' . $href . '">' . ha_h($label) . '</a>';
    }
    echo '</aside><main class="main"><h1>' . ha_h($title) . '</h1>';
}

function ha_layout_end(): void
{
    echo '</main></div></body></html>';
}
