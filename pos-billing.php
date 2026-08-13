<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bill_tax.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$hotel = ha_hotel() ?? [];

$posCols = [
    'table_no' => ha_col_exists('pos_orders', 'table_no', $pdo),
    'order_type' => ha_col_exists('pos_orders', 'order_type', $pdo),
    'payment_mode' => ha_col_exists('pos_orders', 'payment_mode', $pdo),
    'discount' => ha_col_exists('pos_orders', 'discount', $pdo),
    'tax_amount' => ha_col_exists('pos_orders', 'tax_amount', $pdo),
    'service_charge' => ha_col_exists('pos_orders', 'service_charge', $pdo),
    'kot_printed' => ha_col_exists('pos_orders', 'kot_printed', $pdo),
    'bill_printed' => ha_col_exists('pos_orders', 'bill_printed', $pdo),
];
$menuHasVariants = ha_col_exists('menu_items', 'variants_json', $pdo);
$menuHasGstInc = ha_col_exists('menu_items', 'gst_inclusive', $pdo);
$menuHasJain = ha_col_exists('menu_items', 'is_jain', $pdo);

$gstEnabled = !empty($hotel['gst_enabled']);
$gstPercent = (float) ($hotel['gst_percent'] ?? 5);
$servicePercent = (float) ($hotel['service_charge_percent'] ?? 0);

$orderId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int) $_GET['id'] : 0;
$type = ($_GET['type'] ?? 'dine_in') === 'pickup' ? 'pickup' : 'dine_in';
$table = trim((string) ($_GET['table'] ?? ''));
$isPopup = (isset($_GET['popup']) && (string) $_GET['popup'] === '1')
    || (isset($_POST['popup']) && (string) $_POST['popup'] === '1');
$autoPrint = isset($_GET['print']) ? strtolower(trim((string) $_GET['print'])) : '';
$justSettled = isset($_GET['settled']) && (string) $_GET['settled'] === '1';
$order = null;

if ($justSettled && $isPopup) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Settled</title></head><body>';
    echo '<script>try{if(window.opener){window.opener.location.reload();}}catch(e){}window.close();';
    echo 'setTimeout(function(){location.href="pos-orders.php";},400);</script>';
    echo '<p style="font-family:sans-serif;padding:2rem;text-align:center">Bill settled. Closing…</p></body></html>';
    exit;
}

if ($orderId > 0) {
    $st = $pdo->prepare('SELECT * FROM pos_orders WHERE id=:id AND hotel_id=:h');
    $st->execute([':id' => $orderId, ':h' => $hotelId]);
    $order = $st->fetch() ?: null;
    if (!$order) {
        header('Location: pos-orders.php');
        exit;
    }
    if ($posCols['order_type']) {
        $type = ($order['order_type'] ?? 'dine_in') === 'pickup' ? 'pickup' : 'dine_in';
    }
    if ($posCols['table_no']) {
        $table = (string) ($order['table_no'] ?? '');
    }
}

$cats = $pdo->prepare('SELECT id, name FROM menu_categories WHERE hotel_id=:h AND is_active=1 ORDER BY sort_order, name');
$cats->execute([':h' => $hotelId]);
$categories = $cats->fetchAll();

$itemsStmt = $pdo->prepare(
    'SELECT * FROM menu_items WHERE hotel_id=:h AND is_available=1 ORDER BY sort_order, name'
);
$itemsStmt->execute([':h' => $hotelId]);
$menuItems = $itemsStmt->fetchAll();

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $customer = trim((string) ($_POST['customer_name'] ?? 'Walk-in')) ?: 'Walk-in';
    $phone = trim((string) ($_POST['customer_phone'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));
    $discount = (float) ($_POST['discount'] ?? 0);
    $paymentMode = trim((string) ($_POST['payment_mode'] ?? ''));
    $rawItems = json_decode((string) ($_POST['items_json'] ?? '[]'), true);
    if (!is_array($rawItems)) {
        $rawItems = [];
    }
    $cart = [];
    foreach ($rawItems as $it) {
        $name = trim((string) ($it['name'] ?? ''));
        $price = (float) ($it['price'] ?? 0);
        $qty = max(1, (int) ($it['qty'] ?? 1));
        if ($name === '' || $price <= 0) {
            continue;
        }
        $cart[] = [
            'id' => (string) ($it['id'] ?? ''),
            'name' => $name,
            'variant' => (string) ($it['variant'] ?? ''),
            'price' => $price,
            'qty' => $qty,
            'gst_inclusive' => !empty($it['gst_inclusive']) ? 1 : 0,
            'note' => (string) ($it['note'] ?? ''),
        ];
    }

    if (!$cart && $action !== 'cancel') {
        $error = 'Add at least one item';
    } else {
        $totals = fm_bill_totals($cart, $discount, $gstPercent, $gstEnabled, $servicePercent);
        $statusMap = [
            'save' => 'open',
            'kot' => 'preparing',
            'print_bill' => 'printed',
            'settle' => 'paid',
        ];
        try {
            if ($order) {
                $newStatus = $statusMap[$action] ?? (string) $order['status'];
                if ($action === 'cancel') {
                    $newStatus = 'cancelled';
                }
                // Fall back if printed/paid not in enum yet
                if (in_array($newStatus, ['printed', 'paid'], true) && !$posCols['bill_printed']) {
                    if ($action === 'settle') {
                        $newStatus = 'completed';
                    } elseif ($action === 'print_bill') {
                        $newStatus = 'ready';
                    }
                }

                $sets = [
                    'customer_name=:n', 'customer_phone=:ph', 'status=:st',
                    'subtotal=:sub', 'total=:tot', 'items_json=:items', 'note=:note',
                ];
                $params = [
                    ':n' => $customer, ':ph' => $phone, ':st' => $newStatus,
                    ':sub' => $totals['subtotal'], ':tot' => $totals['total'],
                    ':items' => json_encode($cart), ':note' => $note,
                    ':id' => $orderId, ':h' => $hotelId,
                ];
                if ($posCols['discount']) {
                    $sets[] = 'discount=:disc';
                    $params[':disc'] = $totals['discount'];
                }
                if ($posCols['tax_amount']) {
                    $sets[] = 'tax_amount=:tax';
                    $params[':tax'] = $totals['tax'];
                }
                if ($posCols['service_charge']) {
                    $sets[] = 'service_charge=:svc';
                    $params[':svc'] = $totals['service_charge'];
                }
                if ($posCols['payment_mode']) {
                    $sets[] = 'payment_mode=:pay';
                    $params[':pay'] = $paymentMode !== '' ? $paymentMode : ($order['payment_mode'] ?? null);
                }
                if ($posCols['kot_printed']) {
                    $sets[] = 'kot_printed=:kot';
                    $params[':kot'] = !empty($order['kot_printed']) || $action === 'kot' ? 1 : 0;
                }
                if ($posCols['bill_printed']) {
                    $sets[] = 'bill_printed=:bill';
                    $params[':bill'] = !empty($order['bill_printed']) || $action === 'print_bill' || $action === 'settle' ? 1 : 0;
                }

                try {
                    $pdo->prepare('UPDATE pos_orders SET ' . implode(', ', $sets) . ' WHERE id=:id AND hotel_id=:h')->execute($params);
                } catch (Throwable $statusErr) {
                    // Status enum may lack printed/paid — retry with safer statuses
                    if (in_array($newStatus, ['printed', 'paid', 'preparing'], true)) {
                        $params[':st'] = $action === 'settle' ? 'completed' : ($action === 'kot' ? 'preparing' : 'ready');
                        if ($params[':st'] === 'preparing') {
                            // preparing might also fail on very old schema — use open
                            try {
                                $pdo->prepare('UPDATE pos_orders SET ' . implode(', ', $sets) . ' WHERE id=:id AND hotel_id=:h')->execute($params);
                            } catch (Throwable $e2) {
                                $params[':st'] = 'open';
                                $pdo->prepare('UPDATE pos_orders SET ' . implode(', ', $sets) . ' WHERE id=:id AND hotel_id=:h')->execute($params);
                            }
                        } else {
                            $pdo->prepare('UPDATE pos_orders SET ' . implode(', ', $sets) . ' WHERE id=:id AND hotel_id=:h')->execute($params);
                        }
                    } else {
                        throw $statusErr;
                    }
                }

                $flash = 'Order updated';
                $popupQ = $isPopup ? '&popup=1' : '';
                if ($action === 'kot') {
                    header('Location: pos-billing.php?id=' . $orderId . $popupQ . '&print=kot');
                    exit;
                }
                if ($action === 'print_bill') {
                    header('Location: pos-billing.php?id=' . $orderId . $popupQ . '&print=bill');
                    exit;
                }
                if ($action === 'settle') {
                    if ($isPopup) {
                        header('Location: pos-billing.php?popup=1&settled=1');
                    } else {
                        header('Location: pos-orders.php');
                    }
                    exit;
                }
                $st = $pdo->prepare('SELECT * FROM pos_orders WHERE id=:id');
                $st->execute([':id' => $orderId]);
                $order = $st->fetch();
            } else {
                $publicId = 'POS' . strtoupper(bin2hex(random_bytes(4)));
                $newStatus = $statusMap[$action] ?? 'open';
                if (in_array($newStatus, ['printed', 'paid'], true) && !$posCols['bill_printed']) {
                    $newStatus = $action === 'settle' ? 'completed' : 'ready';
                }

                $insertCols = ['public_id', 'hotel_id', 'customer_name', 'customer_phone', 'status', 'subtotal', 'total', 'items_json', 'note'];
                $insertVals = [':pid', ':h', ':n', ':ph', ':st', ':sub', ':tot', ':items', ':note'];
                $params = [
                    ':pid' => $publicId, ':h' => $hotelId, ':n' => $customer, ':ph' => $phone,
                    ':st' => $newStatus, ':sub' => $totals['subtotal'], ':tot' => $totals['total'],
                    ':items' => json_encode($cart), ':note' => $note,
                ];
                if ($posCols['table_no']) {
                    $insertCols[] = 'table_no';
                    $insertVals[] = ':tbl';
                    $params[':tbl'] = $type === 'dine_in' ? ($table !== '' ? $table : null) : null;
                }
                if ($posCols['order_type']) {
                    $insertCols[] = 'order_type';
                    $insertVals[] = ':otyp';
                    $params[':otyp'] = $type === 'pickup' ? 'pickup' : 'dine_in';
                }
                if ($posCols['discount']) {
                    $insertCols[] = 'discount';
                    $insertVals[] = ':disc';
                    $params[':disc'] = $totals['discount'];
                }
                if ($posCols['tax_amount']) {
                    $insertCols[] = 'tax_amount';
                    $insertVals[] = ':tax';
                    $params[':tax'] = $totals['tax'];
                }
                if ($posCols['service_charge']) {
                    $insertCols[] = 'service_charge';
                    $insertVals[] = ':svc';
                    $params[':svc'] = $totals['service_charge'];
                }
                if ($posCols['payment_mode']) {
                    $insertCols[] = 'payment_mode';
                    $insertVals[] = ':pay';
                    $params[':pay'] = $paymentMode !== '' ? $paymentMode : null;
                }
                if ($posCols['kot_printed']) {
                    $insertCols[] = 'kot_printed';
                    $insertVals[] = ':kot';
                    $params[':kot'] = $action === 'kot' ? 1 : 0;
                }
                if ($posCols['bill_printed']) {
                    $insertCols[] = 'bill_printed';
                    $insertVals[] = ':bill';
                    $params[':bill'] = ($action === 'print_bill' || $action === 'settle') ? 1 : 0;
                }

                try {
                    $pdo->prepare(
                        'INSERT INTO pos_orders (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $insertVals) . ')'
                    )->execute($params);
                } catch (Throwable $insErr) {
                    $params[':st'] = $action === 'settle' ? 'completed' : 'open';
                    $pdo->prepare(
                        'INSERT INTO pos_orders (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $insertVals) . ')'
                    )->execute($params);
                }
                $orderId = (int) $pdo->lastInsertId();
                $popupQ = $isPopup ? '&popup=1' : '';
                if ($action === 'kot') {
                    header('Location: pos-billing.php?id=' . $orderId . $popupQ . '&print=kot');
                    exit;
                }
                if ($action === 'print_bill') {
                    header('Location: pos-billing.php?id=' . $orderId . $popupQ . '&print=bill');
                    exit;
                }
                if ($action === 'settle') {
                    if ($isPopup) {
                        header('Location: pos-billing.php?popup=1&settled=1');
                    } else {
                        header('Location: pos-orders.php');
                    }
                    exit;
                }
                header('Location: pos-billing.php?id=' . $orderId . $popupQ);
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$existingItems = [];
if ($order) {
    $existingItems = json_decode((string) $order['items_json'], true) ?: [];
}

$title = $type === 'pickup'
    ? 'Pickup billing'
    : ('Table ' . ($table !== '' ? $table : '—') . ' · Billing');
$selectedLabel = $type === 'pickup'
    ? 'Pickup'
    : ('Table ' . ($table !== '' ? $table : '—'));
$orderIdForPrint = $order ? (int) $order['id'] : 0;

$posHead = static function () use ($title): void {
    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . ha_h($title) . ' · FoodMitra POS</title>';
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
              "text-main": "#1f2937",
              "text-muted": "#6b7280"
            },
            fontFamily: { display: ["DM Sans", "system-ui", "sans-serif"], sans: ["DM Sans", "system-ui", "sans-serif"] }
          }
        }
      };
    </script>';
};

if ($isPopup) {
    $posHead();
    ?>
<style>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
.category-item.active { background-color: #195510 !important; color: #fff !important; border-color: #195510 !important; }
@media (min-width: 768px) {
  .category-item.active { border-right: none; border-left: 3px solid #1F7A32; border-radius: 0; background: #e8f5e9 !important; color: #14532d !important; }
}
.thin-scrollbar { scrollbar-width: thin; scrollbar-color: #b8bcc4 #f3f4f6; }
.thin-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.thin-scrollbar::-webkit-scrollbar-thumb { background: #b8bcc4; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.pos-billing-panel { width: 100%; }
@media (min-width: 768px) {
  .pos-billing-panel { width: clamp(25.5rem, 34vw, 36.5rem); min-width: 25.5rem; max-width: 36.5rem; flex-shrink: 0; }
}
.pos-category-sidebar { width: 100%; }
@media (min-width: 768px) {
  .pos-category-sidebar { width: clamp(8.75rem, 10vw, 11.5rem); min-width: 8.75rem; max-width: 11.5rem; flex-shrink: 0; }
  .pos-category-sidebar .category-item { font-size: clamp(0.6875rem, 0.85vw, 0.8125rem); padding-top: 0.5rem; padding-bottom: 0.5rem; line-height: 1.25; white-space: normal; word-break: break-word; }
}
.pos-menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(6.75rem, 1fr)); gap: 0.5rem; }
@media (min-width: 640px) { .pos-menu-grid { grid-template-columns: repeat(auto-fill, minmax(7.25rem, 1fr)); gap: 0.625rem; } }
@media (min-width: 1024px) { .pos-menu-grid { grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr)); gap: 0.75rem; } }
.menu-item { padding: clamp(0.375rem, 0.8vw, 0.625rem); }
.menu-item .menu-item-name { font-size: clamp(0.6875rem, 0.95vw, 0.8125rem); line-height: 1.25; word-break: break-word; }
.menu-item .menu-item-price { font-size: clamp(0.625rem, 0.85vw, 0.75rem); }
.pos-order-type-tab { font-size: clamp(0.625rem, 0.9vw, 0.8125rem); padding: 0.5rem 0.25rem; line-height: 1.2; min-width: 0; }
.pos-billing-panel input, .pos-billing-panel select { font-size: clamp(0.75rem, 0.9vw, 0.875rem); }
.pos-billing-label { font-size: clamp(0.625rem, 0.8vw, 0.75rem); }
.pos-cart-header { padding: 0.2rem 0.5rem; font-size: 0.625rem; line-height: 1.2; }
.pos-cart-row { padding: 0.2rem 0.5rem; border-bottom: 1px solid #f3f4f6; }
.pos-cart-row:hover { background: #fafafa; }
.pos-cart-name { font-size: clamp(0.6875rem, 0.85vw, 0.75rem); line-height: 1.2; }
.pos-cart-qty { border: 1px solid #e5e7eb; border-radius: 0.2rem; overflow: hidden; height: 1.25rem; }
.pos-cart-qty-btn { width: 1.125rem; height: 1.25rem; display: flex; align-items: center; justify-content: center; font-size: 0.6875rem; background: #f3f4f6; color: #4b5563; flex-shrink: 0; }
.pos-cart-qty-btn-plus { background: #195510; color: #fff; }
.pos-cart-qty-btn-plus:hover { background: #1F7A32; }
.pos-cart-qty-num { min-width: 1.125rem; text-align: center; font-size: 0.6875rem; font-weight: 600; line-height: 1.25rem; padding: 0 0.125rem; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; }
.pos-cart-price { font-size: clamp(0.6875rem, 0.85vw, 0.75rem); }
.pos-billing-footer { padding: 0.625rem 0.75rem 1rem; }
.pos-billing-footer .pos-bill-line { font-size: clamp(0.6875rem, 0.85vw, 0.75rem); }
.pos-billing-footer .pos-bill-total-label { font-size: clamp(0.8125rem, 0.95vw, 0.875rem); }
.pos-billing-footer .pos-bill-total-value { font-size: clamp(1rem, 1.2vw, 1.125rem); }
.pos-billing-action-btn { padding-top: 0.4375rem; padding-bottom: 0.4375rem; font-size: clamp(0.6875rem, 0.85vw, 0.75rem); }
.pos-billing-action-btn .material-symbols-outlined { font-size: 1rem !important; }
.variant-btn.variant-btn-selected { background: #195510; border-color: #195510; color: #fff; }
.variant-btn.variant-btn-selected span { color: #fff !important; }
.flash { background:#e8f5e9; border:1px solid #a5d6a7; color:#14532d; padding:0.5rem 0.75rem; font-size:0.8125rem; }
.flash-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.5rem 0.75rem; font-size:0.8125rem; }
</style>
</head>
<body class="bg-gray-100 font-display text-text-main h-screen overflow-hidden flex relative">
<?php
} else {
    ha_layout_start($title, 'pos-billing.php', 'Add items, send KOT, print bill');
    ?>
<style>
.category-item.active { background-color: #195510 !important; color: #fff !important; border-color: #195510 !important; }
@media (min-width: 768px) {
  .category-item.active { border-right: none; border-left: 3px solid #1F7A32; border-radius: 0; background: #e8f5e9 !important; color: #14532d !important; }
}
.pos-billing-panel { width: 100%; }
@media (min-width: 768px) {
  .pos-billing-panel { width: clamp(25.5rem, 34vw, 36.5rem); min-width: 25.5rem; max-width: 36.5rem; flex-shrink: 0; }
}
.pos-category-sidebar { width: 100%; }
@media (min-width: 768px) {
  .pos-category-sidebar { width: clamp(8.75rem, 10vw, 11.5rem); min-width: 8.75rem; max-width: 11.5rem; flex-shrink: 0; }
}
.pos-menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(6.75rem, 1fr)); gap: 0.5rem; }
@media (min-width: 640px) { .pos-menu-grid { grid-template-columns: repeat(auto-fill, minmax(7.25rem, 1fr)); gap: 0.625rem; } }
@media (min-width: 1024px) { .pos-menu-grid { grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr)); gap: 0.75rem; } }
.menu-item { padding: clamp(0.375rem, 0.8vw, 0.625rem); }
.menu-item .menu-item-name { font-size: clamp(0.6875rem, 0.95vw, 0.8125rem); line-height: 1.25; }
.menu-item .menu-item-price { font-size: clamp(0.625rem, 0.85vw, 0.75rem); }
.pos-order-type-tab { font-size: clamp(0.625rem, 0.9vw, 0.8125rem); padding: 0.5rem 0.25rem; }
.pos-cart-header { padding: 0.2rem 0.5rem; font-size: 0.625rem; }
.pos-cart-row { padding: 0.2rem 0.5rem; border-bottom: 1px solid #f3f4f6; }
.pos-cart-name { font-size: clamp(0.6875rem, 0.85vw, 0.75rem); }
.pos-cart-qty { border: 1px solid #e5e7eb; border-radius: 0.2rem; overflow: hidden; height: 1.25rem; }
.pos-cart-qty-btn { width: 1.125rem; height: 1.25rem; display: flex; align-items: center; justify-content: center; font-size: 0.6875rem; background: #f3f4f6; color: #4b5563; flex-shrink: 0; }
.pos-cart-qty-btn-plus { background: #195510; color: #fff; }
.pos-cart-qty-num { min-width: 1.125rem; text-align: center; font-size: 0.6875rem; font-weight: 600; line-height: 1.25rem; padding: 0 0.125rem; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; }
.pos-cart-price { font-size: clamp(0.6875rem, 0.85vw, 0.75rem); }
.pos-billing-footer { padding: 0.625rem 0.75rem 1rem; }
.pos-billing-action-btn { padding-top: 0.4375rem; padding-bottom: 0.4375rem; font-size: clamp(0.6875rem, 0.85vw, 0.75rem); }
.pos-billing-action-btn .material-symbols-outlined { font-size: 1rem !important; }
.variant-btn.variant-btn-selected { background: #195510; border-color: #195510; color: #fff; }
.variant-btn.variant-btn-selected span { color: #fff !important; }
.ha-pos-shell { margin: -1rem -1rem -2rem; height: calc(100vh - 5rem); max-height: calc(100vh - 5rem); }
@media (min-width: 640px) { .ha-pos-shell { margin: -1.5rem -1.5rem -2rem; } }
@media (min-width: 1024px) { .ha-pos-shell { margin: -2rem -2rem -2rem; } }
</style>
<div class="ha-pos-shell relative flex h-[calc(100vh-8rem)] min-h-[520px] overflow-hidden bg-gray-100 rounded-xl border border-gray-200">
<?php } ?>

<?php if ($flash): ?><div class="absolute top-2 left-1/2 -translate-x-1/2 z-50 flash rounded-lg shadow"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="absolute top-2 left-1/2 -translate-x-1/2 z-50 flash-error rounded-lg shadow"><?= ha_h($error) ?></div><?php endif; ?>

<form method="post" id="billForm" class="contents">
<input type="hidden" name="items_json" id="itemsJson" value="<?= ha_h(json_encode($existingItems)) ?>">
<input type="hidden" name="payment_mode" id="paymentModeInput" value="<?= ha_h((string)($order['payment_mode'] ?? '')) ?>">
<input type="hidden" name="action" id="formAction" value="save">
<?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>

<!-- LEFT: MENU -->
<div class="flex-1 flex flex-col h-full overflow-hidden border-r border-gray-200 bg-white min-w-0">
  <div class="p-3 md:p-4 border-b border-gray-200 flex gap-2 shrink-0">
    <button type="button" onclick="goBackToFloor()" class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg shrink-0" title="Back">
      <span class="material-symbols-outlined">arrow_back</span>
    </button>
    <div class="relative flex-1">
      <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
        <span class="material-symbols-outlined">search</span>
      </span>
      <input type="text" id="itemSearch" onkeyup="filterItems()" placeholder="Search item..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 text-sm">
    </div>
  </div>

  <div class="flex flex-col md:flex-row flex-1 overflow-hidden min-h-0">
    <div class="pos-category-sidebar bg-gray-50 overflow-x-auto md:overflow-y-auto border-b md:border-b-0 md:border-r border-gray-200 thin-scrollbar flex flex-row md:flex-col shrink-0 h-auto md:h-full p-2 md:p-0 gap-2 md:gap-0">
      <button type="button" onclick="filterCategory('all')" data-id="all" class="category-item active shrink-0 rounded-full md:rounded-none w-auto md:w-full text-center md:text-left px-4 py-2 md:px-4 md:py-3 text-xs md:text-sm font-semibold border md:border-0 md:border-b border-gray-200 md:border-gray-100 transition-all shadow-sm md:shadow-none whitespace-nowrap bg-white md:bg-transparent">
        All Items
      </button>
      <?php foreach ($categories as $cat): ?>
        <button type="button" onclick="filterCategory(<?= (int)$cat['id'] ?>)" data-id="<?= (int)$cat['id'] ?>" class="category-item shrink-0 rounded-full md:rounded-none w-auto md:w-full text-center md:text-left px-4 py-2 md:px-4 md:py-3 text-xs md:text-sm font-medium text-gray-600 hover:bg-gray-100 border md:border-0 md:border-b border-gray-200 md:border-gray-100 transition-all shadow-sm md:shadow-none whitespace-nowrap bg-white md:bg-transparent">
          <?= ha_h($cat['name']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="flex-1 min-w-0 bg-white overflow-y-auto p-2 sm:p-3 lg:p-4 pb-24 md:pb-4 scrollbar-hide">
      <div class="pos-menu-grid" id="menuGrid">
        <?php foreach ($menuItems as $m):
            $variants = $menuHasVariants ? (json_decode((string)($m['variants_json'] ?? 'null'), true) ?: []) : [];
            if (!is_array($variants)) {
                $variants = [];
            }
            $basePrice = (float) $m['price'];
            $gstInc = $menuHasGstInc ? (!empty($m['gst_inclusive']) ? 1 : 0) : 1;
            $itemPayload = [
                'id' => (string) $m['public_id'],
                'name' => (string) $m['name'],
                'price' => $basePrice,
                'gst_inclusive' => $gstInc,
                'variants' => $variants,
                'category_id' => (int) $m['category_id'],
                'is_jain' => ($menuHasJain && !empty($m['is_jain'])) ? 1 : 0,
            ];
            $itemJson = json_encode($itemPayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            $safeJson = htmlspecialchars($itemJson !== false ? $itemJson : '{}', ENT_QUOTES, 'UTF-8');
        ?>
          <div onclick="addToCartFromData(this)" data-json="<?= $safeJson ?>"
               class="menu-item group cursor-pointer hover:border-primary hover:shadow-md bg-white border border-gray-200 rounded-lg transition-all relative overflow-hidden min-w-0"
               data-category="<?= (int)$m['category_id'] ?>"
               data-name="<?= ha_h(strtolower((string)$m['name'])) ?>">
            <div class="flex flex-col h-full justify-between min-w-0 gap-0.5">
              <h4 class="menu-item-name font-bold text-gray-800 leading-snug group-hover:text-primary transition-colors min-w-0">
                <span class="line-clamp-3"><?= ha_h($m['name']) ?></span>
                <?php if ($menuHasJain && !empty($m['is_jain'])): ?>
                  <span class="text-[10px] font-semibold text-amber-700">Jain</span>
                <?php endif; ?>
              </h4>
              <p class="menu-item-price font-semibold text-gray-500">&#8377;<?= number_format($basePrice, 2) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$menuItems): ?>
          <div class="col-span-full flex flex-col items-center justify-center py-16 text-gray-400">
            <span class="material-symbols-outlined text-4xl mb-2 opacity-40">restaurant_menu</span>
            <p class="text-sm">No menu items — add them under Menu first.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- RIGHT: BILLING PANEL -->
<div id="billingPanel" class="pos-billing-panel fixed inset-x-0 bottom-0 md:relative md:inset-auto bg-white shadow-[0_-4px_20px_rgba(0,0,0,0.1)] md:shadow-xl z-30 flex flex-col transition-transform duration-300 transform translate-y-[calc(100%-80px)] md:translate-y-0 h-[85vh] md:h-full rounded-t-2xl md:rounded-none shrink-0 min-w-0">
  <div class="md:hidden flex flex-col items-center justify-center py-2 shrink-0 cursor-pointer bg-gray-50 border-b border-gray-200 rounded-t-2xl select-none" onclick="toggleBillingPanel()">
    <div class="w-12 h-1.5 bg-gray-300 rounded-full mb-2"></div>
    <div class="flex justify-between w-full px-6 items-center">
      <div class="flex items-center gap-2">
        <span class="font-bold text-gray-700">Order Total</span>
        <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full" id="mobileItemCount">0</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="font-bold text-primary text-lg" id="mobileCartTotal">&#8377;0</span>
        <span class="material-symbols-outlined text-gray-500" id="panelArrow">expand_less</span>
      </div>
    </div>
  </div>

  <div class="flex min-w-0 shrink-0">
    <button type="button" id="btnDineIn" onclick="setOrderType('dine_in')" class="pos-order-type-tab flex-1 py-2 font-bold <?= $type === 'dine_in' ? 'bg-primary text-white' : 'bg-gray-800 text-white opacity-50' ?> transition-opacity truncate">Dine In</button>
    <button type="button" id="btnPickUp" onclick="setOrderType('pickup')" class="pos-order-type-tab flex-1 py-2 font-bold <?= $type === 'pickup' ? 'bg-primary text-white' : 'bg-gray-800 text-white opacity-50' ?> transition-opacity truncate">Pickup</button>
  </div>

  <div class="px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center justify-between gap-2 min-w-0 shrink-0">
    <div class="flex items-center gap-2 min-w-0" id="selectedInfoBlock">
      <span class="material-symbols-outlined text-primary text-[18px] shrink-0"><?= $type === 'pickup' ? 'takeout_dining' : 'table_restaurant' ?></span>
      <div class="min-w-0">
        <span class="pos-billing-label block text-gray-500 uppercase tracking-wider font-bold">Selected</span>
        <span class="block text-xs font-bold text-gray-900 truncate" id="selectedLabelText"><?= ha_h($selectedLabel) ?></span>
      </div>
    </div>
    <div class="text-right shrink-0">
      <span class="pos-billing-label block text-gray-500 uppercase tracking-wider font-bold">Order Type</span>
      <span class="block text-xs font-bold text-primary" id="orderTypeLabel"><?= $type === 'pickup' ? 'Pickup' : 'Dine In' ?></span>
    </div>
  </div>

  <div class="px-3 py-1.5 border-b border-gray-200 bg-white shrink-0 space-y-1.5">
    <div class="grid grid-cols-2 gap-1.5">
      <input type="text" name="customer_name" value="<?= ha_h($order['customer_name'] ?? 'Walk-in') ?>" placeholder="Customer" class="w-full px-2 py-1.5 bg-white border border-gray-300 rounded-md text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
      <input type="tel" name="customer_phone" value="<?= ha_h($order['customer_phone'] ?? '') ?>" placeholder="Phone" class="w-full px-2 py-1.5 bg-white border border-gray-300 rounded-md text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
    </div>
    <input type="text" name="note" value="<?= ha_h($order['note'] ?? '') ?>" placeholder="Cooking note / request" class="w-full px-2 py-1.5 bg-gray-50 border border-gray-300 rounded-md text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
  </div>

  <div class="pos-cart-header grid grid-cols-12 gap-1 bg-gray-100 font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 shrink-0">
    <div class="col-span-6">Items</div>
    <div class="col-span-3 text-center">Qty</div>
    <div class="col-span-3 text-right">Price</div>
  </div>

  <div class="pos-cart-list thin-scrollbar flex-1 min-h-0 overflow-y-auto" id="cartItems"></div>

  <div class="pos-billing-footer bg-gray-50 border-t border-gray-200 shrink-0">
    <div class="mb-2">
      <label class="pos-billing-label text-gray-500 uppercase tracking-wider font-bold">Discount &#8377;</label>
      <input type="number" step="0.01" name="discount" id="discountInput" value="<?= ha_h((string)($order['discount'] ?? '0')) ?>" class="w-full mt-0.5 px-2 py-1 bg-white border border-gray-300 rounded-md text-sm outline-none focus:border-primary">
    </div>
    <div class="space-y-0.5 pos-bill-line mb-2 text-gray-600">
      <div class="flex justify-between"><span>Subtotal</span><span id="tSub">&#8377;0</span></div>
      <div class="flex justify-between <?= $gstEnabled ? '' : 'hidden' ?>"><span>Tax</span><span id="tTax">&#8377;0</span></div>
      <div class="flex justify-between <?= $servicePercent > 0 ? '' : 'hidden' ?>"><span>Service</span><span id="tSvc">&#8377;0</span></div>
    </div>
    <div class="flex justify-between items-center mb-2 border-t border-dashed border-gray-300 pt-1.5">
      <span class="pos-bill-total-label font-bold text-gray-700">Total</span>
      <span class="pos-bill-total-value font-bold text-primary" id="tTot">&#8377;0</span>
    </div>
    <div class="space-y-1.5">
      <div class="grid grid-cols-2 gap-1.5">
        <button type="button" onclick="submitAction('kot')" class="pos-billing-action-btn bg-primary hover:bg-primary-hover text-white font-bold rounded-md shadow-sm flex items-center justify-center gap-1.5 uppercase tracking-wide">
          <span class="material-symbols-outlined">send</span> Send KOT
        </button>
        <button type="button" onclick="submitAction('save')" class="pos-billing-action-btn bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-bold rounded-md flex items-center justify-center gap-1.5 uppercase tracking-wide">
          <span class="material-symbols-outlined">save</span> Save
        </button>
      </div>
      <button type="button" onclick="submitAction('print_bill')" class="pos-billing-action-btn w-full bg-[#1F7A32] hover:bg-primary text-white font-bold rounded-md shadow-sm flex items-center justify-center gap-1.5 uppercase tracking-wide">
        <span class="material-symbols-outlined">receipt_long</span> Print Bill
      </button>
      <button type="button" onclick="openPaymentModal()" class="pos-billing-action-btn w-full bg-gray-800 hover:bg-gray-900 text-white font-bold rounded-md shadow-sm flex items-center justify-center gap-1.5 uppercase tracking-wide">
        <span class="material-symbols-outlined">payments</span> Settle
      </button>
      <?php if ($orderIdForPrint > 0): ?>
      <div class="grid grid-cols-2 gap-1.5">
        <button type="button" onclick="openPrintWindow('print-kot.php?id=<?= $orderIdForPrint ?>')" class="pos-billing-action-btn bg-white border border-gray-300 text-gray-700 font-bold rounded-md flex items-center justify-center gap-1">
          <span class="material-symbols-outlined">print</span> Reprint KOT
        </button>
        <button type="button" onclick="openPrintWindow('print-bill.php?id=<?= $orderIdForPrint ?>')" class="pos-billing-action-btn bg-white border border-gray-300 text-gray-700 font-bold rounded-md flex items-center justify-center gap-1">
          <span class="material-symbols-outlined">print</span> Reprint Bill
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</form>

<!-- Variant Modal -->
<div id="variantModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 flex justify-between items-center">
      <h3 class="font-bold text-lg text-gray-800" id="variantModalTitle">Select Variation</h3>
      <button type="button" onclick="closeVariantModal()" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="p-5 overflow-y-auto max-h-[60vh]">
      <p class="text-sm text-gray-500 mb-3">Base &#8377;<span id="variantBasePrice">0</span> — pick a variation or use base price.</p>
      <div class="grid grid-cols-2 gap-3" id="variantContainer"></div>
    </div>
    <div class="bg-gray-50 px-5 py-3 flex justify-end gap-2">
      <button type="button" onclick="closeVariantModal()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Cancel</button>
      <button type="button" onclick="confirmVariant()" class="px-5 py-2 rounded-lg bg-primary text-white font-bold text-sm">Add</button>
    </div>
  </div>
</div>

<!-- Payment Mode Modal -->
<div id="paymentModeModal" class="fixed inset-0 bg-black/50 z-[60] hidden items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 flex justify-between items-center bg-primary-soft/40">
      <h3 class="font-bold text-lg text-gray-900 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">payments</span> Select Payment Mode
      </h3>
      <button type="button" onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="p-5 space-y-4">
      <div class="flex justify-between items-center p-3 bg-gray-50 border border-gray-200 rounded-lg">
        <span class="text-sm font-semibold text-gray-600 uppercase">Bill Amount</span>
        <span id="pmBillAmount" class="text-xl font-bold text-primary">&#8377;0</span>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <?php foreach (['cash' => 'payments', 'upi' => 'qr_code_2', 'card' => 'credit_card'] as $pm => $icon): ?>
        <button type="button" data-mode="<?= $pm ?>" onclick="selectPaymentMode('<?= $pm ?>')" class="pm-mode-btn flex flex-col items-center justify-center gap-1 py-3 border-2 border-gray-200 rounded-lg hover:border-primary hover:bg-primary-soft/40 transition-colors">
          <span class="material-symbols-outlined text-2xl text-gray-700"><?= $icon ?></span>
          <span class="text-sm font-bold text-gray-700"><?= strtoupper($pm) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="px-5 py-3 bg-gray-50 border-t flex justify-end gap-2">
      <button type="button" onclick="closePaymentModal()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Cancel</button>
      <button type="button" id="pmConfirmBtn" onclick="confirmPaymentAndSettle()" disabled class="px-5 py-2 rounded-lg bg-primary text-white font-bold text-sm disabled:opacity-50 disabled:cursor-not-allowed">Confirm &amp; Settle</button>
    </div>
  </div>
</div>

<script>
const GST_ENABLED = <?= $gstEnabled ? 'true' : 'false' ?>;
const GST_PCT = <?= json_encode($gstPercent) ?>;
const SVC_PCT = <?= json_encode($servicePercent) ?>;
const IS_POPUP = <?= $isPopup ? 'true' : 'false' ?>;
const HAS_ORDER = <?= $order ? 'true' : 'false' ?>;
const CURRENT_TYPE = <?= json_encode($type) ?>;
const CURRENT_TABLE = <?= json_encode($table) ?>;
const ORDER_ID = <?= (int)$orderIdForPrint ?>;
const AUTO_PRINT = <?= json_encode($autoPrint) ?>;
let cart = <?= json_encode(array_values($existingItems), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
let billingPanelOpen = false;
let pendingVariantItem = null;
let selectedVariant = null;
let selectedPaymentMode = '';

function money(n) {
  return '₹' + (Math.round(n * 100) / 100).toFixed(0);
}
function money2(n) {
  return '₹' + (Math.round(n * 100) / 100).toFixed(2);
}

function goBackToFloor() {
  if (IS_POPUP) {
    try { if (window.opener) window.opener.location.reload(); } catch (e) {}
    window.close();
    setTimeout(function () { location.href = 'pos-orders.php'; }, 300);
    return;
  }
  location.href = 'pos-orders.php';
}

function popupQs() {
  return IS_POPUP ? '&popup=1' : '';
}

function setOrderType(t) {
  if (HAS_ORDER) return;
  if (t === CURRENT_TYPE) return;
  if (t === 'pickup') {
    location.href = 'pos-billing.php?type=pickup' + (IS_POPUP ? '&popup=1' : '');
  } else {
    var url = 'pos-billing.php?type=dine_in' + (CURRENT_TABLE ? '&table=' + encodeURIComponent(CURRENT_TABLE) : '') + (IS_POPUP ? '&popup=1' : '');
    location.href = url;
  }
}

function toggleBillingPanel() {
  var panel = document.getElementById('billingPanel');
  var arrow = document.getElementById('panelArrow');
  billingPanelOpen = !billingPanelOpen;
  if (billingPanelOpen) {
    panel.classList.remove('translate-y-[calc(100%-80px)]');
    panel.classList.add('translate-y-0');
    if (arrow) arrow.textContent = 'expand_more';
  } else {
    panel.classList.add('translate-y-[calc(100%-80px)]');
    panel.classList.remove('translate-y-0');
    if (arrow) arrow.textContent = 'expand_less';
  }
}

function filterItems() {
  var q = (document.getElementById('itemSearch').value || '').toLowerCase();
  var activeCat = document.querySelector('.category-item.active');
  var catId = activeCat ? activeCat.getAttribute('data-id') : 'all';
  document.querySelectorAll('#menuGrid .menu-item').forEach(function (el) {
    var name = el.getAttribute('data-name') || '';
    var matchCat = (catId === 'all' || el.getAttribute('data-category') === catId);
    var matchQ = !q || name.indexOf(q) !== -1;
    el.style.display = (matchCat && matchQ) ? '' : 'none';
  });
}

function filterCategory(id) {
  document.querySelectorAll('.category-item').forEach(function (b) {
    b.classList.remove('active');
    b.classList.add('font-medium', 'text-gray-600');
    b.classList.remove('font-semibold');
  });
  var btn = document.querySelector('.category-item[data-id="' + id + '"]');
  if (btn) {
    btn.classList.add('active', 'font-semibold');
    btn.classList.remove('font-medium', 'text-gray-600');
  }
  filterItems();
}

function openPrintWindow(url) {
  var w = 350, h = 600;
  var left = Math.max(0, (screen.width - w) / 2);
  var top = Math.max(0, (screen.height - h) / 2);
  window.open(url, 'FmPrint', 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes');
}

function recalc() {
  var sub = 0, taxable = 0, qtySum = 0;
  cart.forEach(function (it) {
    var line = (+it.price) * (+it.qty || 1);
    sub += line;
    qtySum += (+it.qty || 1);
    if (GST_ENABLED && !Number(it.gst_inclusive)) taxable += line;
  });
  var disc = Math.min(sub, Math.max(0, parseFloat(document.getElementById('discountInput').value || '0')));
  var after = Math.max(0, sub - disc);
  var taxBase = sub > 0 ? taxable * (after / sub) : 0;
  var tax = GST_ENABLED ? taxBase * (GST_PCT / 100) : 0;
  var svc = after * (SVC_PCT / 100);
  var tot = after + tax + svc;
  document.getElementById('tSub').textContent = money2(sub);
  document.getElementById('tTax').textContent = money2(tax);
  document.getElementById('tSvc').textContent = money2(svc);
  document.getElementById('tTot').textContent = money(tot);
  var mobTot = document.getElementById('mobileCartTotal');
  var mobCnt = document.getElementById('mobileItemCount');
  if (mobTot) mobTot.textContent = money(tot);
  if (mobCnt) mobCnt.textContent = String(qtySum);
  document.getElementById('itemsJson').value = JSON.stringify(cart);
  document.getElementById('pmBillAmount').textContent = money2(tot);
  renderCart();
  return tot;
}

function renderCart() {
  var el = document.getElementById('cartItems');
  if (!cart.length) {
    el.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-gray-400 py-10"><span class="material-symbols-outlined text-4xl mb-2 opacity-20">fastfood</span><p class="text-sm">No items selected</p></div>';
    return;
  }
  el.innerHTML = cart.map(function (it, i) {
    var line = (+it.price) * (+it.qty || 1);
    var label = (it.name || '') + (it.variant ? (' · ' + it.variant) : '');
    return '<div class="pos-cart-row grid grid-cols-12 gap-1 items-center">' +
      '<div class="col-span-6 min-w-0"><p class="pos-cart-name font-semibold text-gray-800 truncate">' + escapeHtml(label) + '</p></div>' +
      '<div class="col-span-3 flex justify-center"><div class="pos-cart-qty inline-flex items-stretch">' +
      '<button type="button" class="pos-cart-qty-btn" onclick="chgQty(' + i + ',-1)">−</button>' +
      '<span class="pos-cart-qty-num">' + (it.qty || 1) + '</span>' +
      '<button type="button" class="pos-cart-qty-btn pos-cart-qty-btn-plus" onclick="chgQty(' + i + ',1)">+</button>' +
      '</div></div>' +
      '<div class="col-span-3 text-right pos-cart-price font-semibold text-gray-700">' + money2(line) + '</div>' +
      '</div>';
  }).join('');
}

function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function chgQty(i, d) {
  cart[i].qty = Math.max(0, (+cart[i].qty || 1) + d);
  if (cart[i].qty <= 0) cart.splice(i, 1);
  recalc();
}

function addItem(payload) {
  var key = payload.id + '|' + (payload.variant || '');
  var found = cart.find(function (c) { return (c.id + '|' + (c.variant || '')) === key; });
  if (found) found.qty = (+found.qty || 1) + 1;
  else cart.push(Object.assign({}, payload, { qty: 1 }));
  recalc();
}

function addToCartFromData(el) {
  var item;
  try { item = JSON.parse(el.getAttribute('data-json') || '{}'); } catch (e) { return; }
  var variants = Array.isArray(item.variants) ? item.variants : [];
  if (variants.length) {
    openVariantModal(item);
    return;
  }
  addItem({
    id: item.id,
    name: item.name,
    variant: '',
    price: parseFloat(item.price) || 0,
    gst_inclusive: item.gst_inclusive ? 1 : 0
  });
}

function openVariantModal(item) {
  pendingVariantItem = item;
  selectedVariant = null;
  document.getElementById('variantModalTitle').textContent = item.name || 'Select Variation';
  document.getElementById('variantBasePrice').textContent = (parseFloat(item.price) || 0).toFixed(2);
  var container = document.getElementById('variantContainer');
  container.innerHTML = '';
  var baseBtn = document.createElement('button');
  baseBtn.type = 'button';
  baseBtn.className = 'variant-btn flex flex-col items-center justify-center p-3 border-2 border-primary bg-primary-soft rounded-lg';
  baseBtn.innerHTML = '<span class="text-sm font-bold">Base</span><span class="text-xs font-bold text-primary mt-1">₹' + (parseFloat(item.price) || 0).toFixed(2) + '</span>';
  baseBtn.onclick = function () { pickVariant(null, baseBtn); };
  container.appendChild(baseBtn);
  selectedVariant = { name: '', price: parseFloat(item.price) || 0 };
  (item.variants || []).forEach(function (v) {
    var addon = parseFloat(v.price) || 0;
    var full = (parseFloat(item.price) || 0) + addon;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'variant-btn flex flex-col items-center justify-center p-3 border border-gray-200 rounded-lg hover:border-primary hover:bg-primary-soft/50 transition-all';
    btn.innerHTML = '<span class="text-sm font-bold text-center">' + escapeHtml(v.name || '') + '</span><span class="text-xs font-bold text-primary mt-1">₹' + full.toFixed(2) + '</span>';
    btn.onclick = function () { pickVariant({ name: v.name || '', price: full }, btn); };
    container.appendChild(btn);
  });
  var modal = document.getElementById('variantModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function pickVariant(v, btnEl) {
  selectedVariant = v || { name: '', price: parseFloat(pendingVariantItem.price) || 0 };
  document.querySelectorAll('.variant-btn').forEach(function (b) {
    b.classList.remove('variant-btn-selected', 'border-primary', 'bg-primary-soft', 'border-2');
    b.classList.add('border', 'border-gray-200');
  });
  btnEl.classList.add('variant-btn-selected', 'border-primary', 'border-2');
  btnEl.classList.remove('border-gray-200');
}

function closeVariantModal() {
  var modal = document.getElementById('variantModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  pendingVariantItem = null;
  selectedVariant = null;
}

function confirmVariant() {
  if (!pendingVariantItem || !selectedVariant) return;
  addItem({
    id: pendingVariantItem.id,
    name: pendingVariantItem.name,
    variant: selectedVariant.name || '',
    price: parseFloat(selectedVariant.price) || 0,
    gst_inclusive: pendingVariantItem.gst_inclusive ? 1 : 0
  });
  closeVariantModal();
}

function submitAction(action) {
  document.getElementById('formAction').value = action;
  document.getElementById('itemsJson').value = JSON.stringify(cart);
  document.getElementById('billForm').submit();
}

function openPaymentModal() {
  if (!cart.length) { alert('Add at least one item'); return; }
  recalc();
  selectedPaymentMode = '';
  document.getElementById('pmConfirmBtn').disabled = true;
  document.querySelectorAll('.pm-mode-btn').forEach(function (b) {
    b.classList.remove('border-primary', 'bg-primary-soft');
    b.classList.add('border-gray-200');
  });
  var modal = document.getElementById('paymentModeModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closePaymentModal() {
  var modal = document.getElementById('paymentModeModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

function selectPaymentMode(mode) {
  selectedPaymentMode = mode;
  document.getElementById('paymentModeInput').value = mode;
  document.getElementById('pmConfirmBtn').disabled = false;
  document.querySelectorAll('.pm-mode-btn').forEach(function (b) {
    var on = b.getAttribute('data-mode') === mode;
    b.classList.toggle('border-primary', on);
    b.classList.toggle('bg-primary-soft', on);
    b.classList.toggle('border-gray-200', !on);
  });
}

function confirmPaymentAndSettle() {
  if (!selectedPaymentMode) return;
  closePaymentModal();
  submitAction('settle');
}

document.getElementById('discountInput').addEventListener('input', recalc);
recalc();

if (AUTO_PRINT === 'kot' && ORDER_ID) {
  openPrintWindow('print-kot.php?id=' + ORDER_ID);
} else if (AUTO_PRINT === 'bill' && ORDER_ID) {
  openPrintWindow('print-bill.php?id=' + ORDER_ID);
}

if (AUTO_PRINT && window.history && window.history.replaceState) {
  try {
    var u = new URL(location.href);
    u.searchParams.delete('print');
    window.history.replaceState({}, '', u.toString());
  } catch (e) {}
}
</script>

<?php if ($isPopup): ?>
</body></html>
<?php else: ?>
</div>
<?php ha_layout_end(); ?>
<?php endif; ?>

