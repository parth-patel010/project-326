<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'promo') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    if ($title !== '') {
        $pdo->prepare(
            'INSERT INTO hotel_offers (hotel_id, title, subtitle, sort_order, is_active) VALUES (:h, :t, :s, 0, 1)'
        )->execute([':h' => $hotelId, ':t' => $title, ':s' => $subtitle]);
        $flash = 'Promo line added';
    }
}
if (isset($_GET['del']) && ctype_digit((string) $_GET['del'])) {
    $pdo->prepare('DELETE FROM hotel_offers WHERE id=:id AND hotel_id=:h')
        ->execute([':id' => (int) $_GET['del'], ':h' => $hotelId]);
    $flash = 'Promo deleted';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'combo') {
    $title = trim((string) ($_POST['combo_title'] ?? ''));
    $buyItem = (int) ($_POST['buy_item_id'] ?? 0);
    $buyQty = max(1, (int) ($_POST['combo_buy_qty'] ?? 1));
    $getItem = (int) ($_POST['get_item_id'] ?? 0);
    $getQty = max(1, (int) ($_POST['combo_get_qty'] ?? 1));
    if ($title !== '' && $buyItem > 0 && $getItem > 0) {
        try {
            $pdo->prepare(
                'INSERT INTO combo_offers (hotel_id, title, buy_requirements, get_items, is_active)
                 VALUES (:h, :t, :buy, :get, 1)'
            )->execute([
                ':h' => $hotelId,
                ':t' => $title,
                ':buy' => json_encode([['item_id' => $buyItem, 'qty' => $buyQty]]),
                ':get' => json_encode([['item_id' => $getItem, 'qty' => $getQty]]),
            ]);
            $flash = 'Combo offer saved';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Combo needs title, buy item, and get item';
    }
}
if (isset($_GET['del_combo']) && ctype_digit((string) $_GET['del_combo'])) {
    try {
        $pdo->prepare('DELETE FROM combo_offers WHERE id=:id AND hotel_id=:h')
            ->execute([':id' => (int) $_GET['del_combo'], ':h' => $hotelId]);
        $flash = 'Combo deleted';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$promoStmt = $pdo->prepare('SELECT * FROM hotel_offers WHERE hotel_id = :h ORDER BY sort_order, id DESC');
$promoStmt->execute([':h' => $hotelId]);
$promos = $promoStmt->fetchAll();

$items = [];
try {
    $itemsStmt = $pdo->prepare(
        'SELECT mi.id, mi.name, mi.price, mi.discount_price, mi.offer_type, mi.buy_qty, mi.get_qty, mc.name AS category_name
         FROM menu_items mi
         LEFT JOIN menu_categories mc ON mc.id = mi.category_id
         WHERE mi.hotel_id = :h
         ORDER BY mc.sort_order ASC, mi.sort_order ASC, mi.name ASC'
    );
    $itemsStmt->execute([':h' => $hotelId]);
    $items = $itemsStmt->fetchAll();
} catch (Throwable $e) {
    $error = $error ?: 'Run migrate_feature_port.php to enable item offers: ' . $e->getMessage();
}

$combos = [];
try {
    $cStmt = $pdo->prepare('SELECT * FROM combo_offers WHERE hotel_id = :h ORDER BY id DESC');
    $cStmt->execute([':h' => $hotelId]);
    $combos = $cStmt->fetchAll();
} catch (Throwable $e) {
    // table may not exist yet
}

ha_layout_start('Offers', 'offers.php', 'Item discounts, BOGO, promo lines, and combos');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash-error"><?= ha_h($error) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2>Offers</h2>
    <p class="sub">Cut-price, Buy X Get Y, promo lines, and combos</p>
  </div>
</div>

<div class="card !p-0 overflow-hidden mb-4">
  <div class="card-header px-4 pt-4">
    <h3>Item offers</h3>
  </div>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th>Price</th>
          <th>Type</th>
          <th>Cut price</th>
          <th>Buy</th>
          <th>Get</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it):
            $ot = (string) ($it['offer_type'] ?? 'none');
            ?>
          <tr data-item-row="<?= (int) $it['id'] ?>">
            <td>
              <div class="font-semibold text-gray-900"><?= ha_h($it['name']) ?></div>
              <div class="muted text-xs"><?= ha_h($it['category_name'] ?? '') ?></div>
            </td>
            <td>₹<?= number_format((float) $it['price'], 2) ?></td>
            <td>
              <select class="input !mb-0 !py-1" data-field="offer_type">
                <option value="none" <?= $ot === 'none' ? 'selected' : '' ?>>None</option>
                <option value="discount" <?= $ot === 'discount' ? 'selected' : '' ?>>Discount</option>
                <option value="bogo" <?= $ot === 'bogo' ? 'selected' : '' ?>>BOGO</option>
              </select>
            </td>
            <td>
              <input class="input !mb-0 !py-1 !w-24" type="number" step="0.01" min="0"
                     data-field="discount_price"
                     value="<?= $it['discount_price'] !== null ? ha_h((string) $it['discount_price']) : '' ?>"
                     placeholder="—">
            </td>
            <td>
              <input class="input !mb-0 !py-1 !w-16" type="number" min="1"
                     data-field="buy_qty" value="<?= (int) ($it['buy_qty'] ?? 1) ?>">
            </td>
            <td>
              <input class="input !mb-0 !py-1 !w-16" type="number" min="0"
                     data-field="get_qty" value="<?= (int) ($it['get_qty'] ?? 0) ?>">
            </td>
            <td>
              <button type="button" class="btn sm" onclick="saveItemOffer(<?= (int) $it['id'] ?>)">Save</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
          <tr><td colspan="7"><div class="empty-state"><p>No menu items yet</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" class="card max-w-xl mb-4">
  <input type="hidden" name="form" value="promo">
  <div class="card-header"><h3>Promo line (hotel card)</h3></div>
  <label>Title</label>
  <input class="input" name="title" required placeholder="Items at ₹99">
  <label>Subtitle</label>
  <input class="input" name="subtitle" placeholder="On select items">
  <button class="btn" type="submit">
    <span class="material-symbols-outlined text-[18px]">local_offer</span> Add promo
  </button>
</form>

<div class="card !p-0 overflow-hidden mb-4">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Promo</th><th>Subtitle</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($promos as $r): ?>
          <tr>
            <td class="font-semibold"><?= ha_h($r['title']) ?></td>
            <td class="muted"><?= ha_h($r['subtitle']) ?></td>
            <td><a class="btn secondary sm" href="?del=<?= (int) $r['id'] ?>" onclick="return confirm('Delete?')">Delete</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$promos): ?>
          <tr><td colspan="3"><div class="empty-state"><p>No promo lines</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" class="card max-w-xl mb-4">
  <input type="hidden" name="form" value="combo">
  <div class="card-header"><h3>Combo offer</h3></div>
  <label>Title</label>
  <input class="input" name="combo_title" required placeholder="Buy Thali get sweet">
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div>
      <label>Buy item</label>
      <select class="input" name="buy_item_id" required>
        <option value="">Select</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= (int) $it['id'] ?>"><?= ha_h($it['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Buy qty</label>
      <input class="input" type="number" min="1" name="combo_buy_qty" value="1">
    </div>
    <div>
      <label>Get item (free)</label>
      <select class="input" name="get_item_id" required>
        <option value="">Select</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= (int) $it['id'] ?>"><?= ha_h($it['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Get qty</label>
      <input class="input" type="number" min="1" name="combo_get_qty" value="1">
    </div>
  </div>
  <button class="btn" type="submit">Save combo</button>
</form>

<?php if ($combos): ?>
<div class="card !p-0 overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Combo</th><th>Buy</th><th>Get</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($combos as $c):
            $buy = json_decode((string) ($c['buy_requirements'] ?? '[]'), true) ?: [];
            $get = json_decode((string) ($c['get_items'] ?? '[]'), true) ?: [];
            ?>
          <tr>
            <td class="font-semibold"><?= ha_h($c['title']) ?></td>
            <td class="muted text-xs"><?= ha_h(json_encode($buy)) ?></td>
            <td class="muted text-xs"><?= ha_h(json_encode($get)) ?></td>
            <td><a class="btn secondary sm" href="?del_combo=<?= (int) $c['id'] ?>" onclick="return confirm('Delete?')">Delete</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
async function saveItemOffer(itemId) {
  var row = document.querySelector('[data-item-row="' + itemId + '"]');
  if (!row) return;
  var payload = {
    item_id: itemId,
    offer_type: row.querySelector('[data-field="offer_type"]').value,
    discount_price: row.querySelector('[data-field="discount_price"]').value,
    buy_qty: row.querySelector('[data-field="buy_qty"]').value,
    get_qty: row.querySelector('[data-field="get_qty"]').value
  };
  try {
    var res = await fetch('save-offer-handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) {
      alert(data.error || 'Save failed');
      return;
    }
    alert('Offer saved');
  } catch (e) {
    alert('Network error');
  }
}
</script>
<?php ha_layout_end(); ?>
