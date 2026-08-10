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
$order = null;

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
                if ($action === 'kot') {
                    header('Location: print-kot.php?id=' . $orderId);
                    exit;
                }
                if ($action === 'print_bill') {
                    header('Location: print-bill.php?id=' . $orderId);
                    exit;
                }
                if ($action === 'settle') {
                    header('Location: pos-orders.php');
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
                if ($action === 'kot') {
                    header('Location: print-kot.php?id=' . $orderId);
                    exit;
                }
                if ($action === 'print_bill') {
                    header('Location: print-bill.php?id=' . $orderId);
                    exit;
                }
                if ($action === 'settle') {
                    header('Location: pos-orders.php');
                    exit;
                }
                header('Location: pos-billing.php?id=' . $orderId);
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

ha_layout_start($title, 'pos-orders.php', 'Add items, send KOT, print bill');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1rem"><?= ha_h($error) ?></div><?php endif; ?>

<div class="flex flex-col lg:flex-row gap-4 items-start">
  <div class="flex-1 w-full min-w-0">
    <div class="card !mb-3">
      <input type="search" id="menuSearch" class="input !mb-0" placeholder="Search menu…">
    </div>
    <div class="flex flex-wrap gap-2 mb-3" id="catChips">
      <button type="button" data-cat="0" class="cat-chip px-3 py-1.5 rounded-lg text-sm font-semibold bg-primary text-white">All</button>
      <?php foreach ($categories as $c): ?>
        <button type="button" data-cat="<?= (int)$c['id'] ?>" class="cat-chip px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-gray-200 text-gray-700"><?= ha_h($c['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="menuGrid">
      <?php foreach ($menuItems as $m):
          $variants = $menuHasVariants ? (json_decode((string)($m['variants_json'] ?? 'null'), true) ?: []) : [];
          $basePrice = (float)$m['price'];
          $gstInc = $menuHasGstInc ? (!empty($m['gst_inclusive']) ? '1' : '0') : '1';
      ?>
        <button type="button"
          class="menu-card text-left bg-white border border-gray-200 rounded-xl p-3 shadow-sm hover:border-primary hover:shadow transition"
          data-cat="<?= (int)$m['category_id'] ?>"
          data-name="<?= ha_h(strtolower($m['name'])) ?>"
          data-id="<?= ha_h($m['public_id']) ?>"
          data-label="<?= ha_h($m['name']) ?>"
          data-price="<?= $basePrice ?>"
          data-gst="<?= $gstInc ?>"
          data-variants="<?= ha_h(json_encode($variants)) ?>">
          <p class="font-semibold text-sm text-gray-900 leading-snug"><?= ha_h($m['name']) ?></p>
          <p class="text-primary font-bold mt-1">₹<?= number_format($basePrice, 0) ?></p>
          <?php if ($menuHasJain && !empty($m['is_jain'])): ?><span class="text-[10px] text-amber-700">Jain</span><?php endif; ?>
        </button>
      <?php endforeach; ?>
      <?php if (!$menuItems): ?>
        <p class="muted col-span-full">No menu items — add them under Menu items first.</p>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" id="billForm" class="w-full lg:w-[380px] shrink-0 card sticky top-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="!mb-0"><?= $type === 'pickup' ? 'Pickup' : 'Table ' . ha_h($table) ?></h3>
      <a href="pos-orders.php" class="text-sm text-gray-500">Floor</a>
    </div>
    <input type="hidden" name="items_json" id="itemsJson" value="<?= ha_h(json_encode($existingItems)) ?>">
    <label>Customer</label>
    <input class="input" name="customer_name" value="<?= ha_h($order['customer_name'] ?? 'Walk-in') ?>">
    <label>Phone</label>
    <input class="input" name="customer_phone" value="<?= ha_h($order['customer_phone'] ?? '') ?>">
    <label>Note / cooking request</label>
    <input class="input" name="note" value="<?= ha_h($order['note'] ?? '') ?>">

    <div id="cartList" class="border border-gray-100 rounded-lg divide-y max-h-56 overflow-y-auto mb-3 bg-gray-50"></div>

    <label>Discount ₹</label>
    <input class="input" type="number" step="0.01" name="discount" id="discountInput" value="<?= ha_h((string)($order['discount'] ?? '0')) ?>">

    <div class="text-sm space-y-1 mb-3">
      <div class="flex justify-between"><span class="muted">Subtotal</span><span id="tSub">₹0</span></div>
      <div class="flex justify-between"><span class="muted">Tax</span><span id="tTax">₹0</span></div>
      <div class="flex justify-between"><span class="muted">Service</span><span id="tSvc">₹0</span></div>
      <div class="flex justify-between font-bold text-base"><span>Total</span><span id="tTot" class="text-primary">₹0</span></div>
    </div>

    <label>Payment (for settle)</label>
    <select class="input" name="payment_mode" <?= $posCols['payment_mode'] ? '' : 'disabled' ?>>
      <option value="">—</option>
      <?php foreach (['cash','upi','card'] as $pm): ?>
        <option value="<?= $pm ?>" <?= ($order['payment_mode'] ?? '') === $pm ? 'selected' : '' ?>><?= strtoupper($pm) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="grid grid-cols-2 gap-2">
      <button class="btn secondary" name="action" value="save" type="submit">Save</button>
      <button class="btn" name="action" value="kot" type="submit">Send KOT</button>
      <button class="btn" name="action" value="print_bill" type="submit">Print bill</button>
      <button class="btn" name="action" value="settle" type="submit">Settle</button>
    </div>
    <?php if ($order): ?>
      <div class="flex gap-2 mt-2">
        <a class="btn secondary flex-1 text-center" href="print-kot.php?id=<?= (int)$order['id'] ?>">Reprint KOT</a>
        <a class="btn secondary flex-1 text-center" href="print-bill.php?id=<?= (int)$order['id'] ?>">Reprint bill</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<script>
const GST_ENABLED = <?= $gstEnabled ? 'true' : 'false' ?>;
const GST_PCT = <?= json_encode($gstPercent) ?>;
const SVC_PCT = <?= json_encode($servicePercent) ?>;
let cart = <?= json_encode(array_values($existingItems)) ?>;

function money(n){ return '₹' + (Math.round(n*100)/100).toFixed(0); }

function recalc(){
  let sub=0, taxable=0;
  cart.forEach(it=>{
    const line = (+it.price)*(+it.qty||1);
    sub += line;
    if (GST_ENABLED && !Number(it.gst_inclusive)) taxable += line;
  });
  const disc = Math.min(sub, Math.max(0, parseFloat(document.getElementById('discountInput').value||'0')));
  const after = Math.max(0, sub-disc);
  const taxBase = sub>0 ? taxable*(after/sub) : 0;
  const tax = GST_ENABLED ? taxBase*(GST_PCT/100) : 0;
  const svc = after*(SVC_PCT/100);
  const tot = after+tax+svc;
  document.getElementById('tSub').textContent = money(sub);
  document.getElementById('tTax').textContent = money(tax);
  document.getElementById('tSvc').textContent = money(svc);
  document.getElementById('tTot').textContent = money(tot);
  document.getElementById('itemsJson').value = JSON.stringify(cart);
  renderCart();
}

function renderCart(){
  const el = document.getElementById('cartList');
  if (!cart.length){ el.innerHTML = '<p class="p-3 text-sm text-gray-400">Cart empty — tap menu items</p>'; return; }
  el.innerHTML = cart.map((it,i)=>`
    <div class="flex items-center gap-2 p-2 text-sm">
      <div class="flex-1 min-w-0">
        <p class="font-semibold truncate">${it.name}${it.variant?(' · '+it.variant):''}</p>
        <p class="text-gray-500">₹${it.price} × ${it.qty}</p>
      </div>
      <button type="button" class="px-2" onclick="chgQty(${i},-1)">−</button>
      <span class="w-6 text-center font-bold">${it.qty}</span>
      <button type="button" class="px-2" onclick="chgQty(${i},1)">+</button>
    </div>`).join('');
}

function chgQty(i,d){
  cart[i].qty = Math.max(0, (+cart[i].qty||1)+d);
  if (cart[i].qty<=0) cart.splice(i,1);
  recalc();
}

function addItem(payload){
  const key = payload.id + '|' + (payload.variant||'');
  const found = cart.find(c => (c.id+'|'+(c.variant||''))===key);
  if (found) found.qty = (+found.qty||1)+1;
  else cart.push({...payload, qty:1});
  recalc();
}

document.getElementById('menuGrid').addEventListener('click', e=>{
  const btn = e.target.closest('.menu-card');
  if (!btn) return;
  let variants = [];
  try { variants = JSON.parse(btn.dataset.variants||'[]')||[]; } catch(x){}
  let variant = '';
  let price = parseFloat(btn.dataset.price||'0');
  if (variants.length){
    const names = variants.map((v,i)=>`${i+1}. ${v.name} (+₹${v.price})`).join('\\n');
    const pick = prompt('Choose variant number (or cancel for base):\\n'+names);
    const idx = parseInt(pick||'0',10)-1;
    if (idx>=0 && variants[idx]){
      variant = variants[idx].name||'';
      price = price + parseFloat(variants[idx].price||0);
    }
  }
  addItem({
    id: btn.dataset.id,
    name: btn.dataset.label,
    variant,
    price,
    gst_inclusive: btn.dataset.gst==='1'?1:0
  });
});

document.getElementById('menuSearch').addEventListener('input', e=>{
  const q = e.target.value.toLowerCase();
  document.querySelectorAll('.menu-card').forEach(c=>{
    c.style.display = (!q || c.dataset.name.includes(q)) ? '' : 'none';
  });
});

document.getElementById('catChips').addEventListener('click', e=>{
  const b = e.target.closest('.cat-chip');
  if (!b) return;
  document.querySelectorAll('.cat-chip').forEach(x=>{
    x.classList.remove('bg-primary','text-white');
    x.classList.add('bg-white','border','border-gray-200','text-gray-700');
  });
  b.classList.add('bg-primary','text-white');
  b.classList.remove('bg-white','text-gray-700');
  const cat = b.dataset.cat;
  document.querySelectorAll('.menu-card').forEach(c=>{
    c.style.display = (cat==='0' || c.dataset.cat===cat) ? '' : 'none';
  });
});

document.getElementById('discountInput').addEventListener('input', recalc);
recalc();
</script>
<?php ha_layout_end(); ?>
