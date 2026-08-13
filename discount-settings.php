<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $type = ($_POST['discount_type'] ?? 'percent') === 'flat' ? 'flat' : 'percent';
    $value = (float) ($_POST['discount_value'] ?? 0);
    $min = (float) ($_POST['min_order'] ?? 0);
    if ($title !== '' && $value > 0) {
        $pdo->prepare(
            'INSERT INTO hotel_discount_settings (hotel_id, title, discount_type, discount_value, min_order, is_active)
             VALUES (:h, :t, :ty, :v, :m, 1)'
        )->execute([':h' => $hotelId, ':t' => $title, ':ty' => $type, ':v' => $value, ':m' => $min]);
        $flash = 'Discount saved';
    }
}
if (isset($_GET['del']) && ctype_digit((string)$_GET['del'])) {
    $pdo->prepare('DELETE FROM hotel_discount_settings WHERE id=:id AND hotel_id=:h')
        ->execute([':id' => (int)$_GET['del'], ':h' => $hotelId]);
    $flash = 'Deleted';
}

$stmt = $pdo->prepare('SELECT * FROM hotel_discount_settings WHERE hotel_id = :h ORDER BY id DESC');
$stmt->execute([':h' => $hotelId]);
$rows = $stmt->fetchAll();

ha_layout_start('Discount Settings', 'discount-settings.php', 'Cart-level discounts for your hotel');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2>Discounts</h2>
    <p class="sub">Cart-level discounts for your hotel</p>
  </div>
</div>

<form method="post" class="card max-w-xl">
  <div class="card-header">
    <h3>New discount</h3>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div class="sm:col-span-2"><label>Title</label><input class="input" name="title" required></div>
    <div>
      <label>Type</label>
      <select class="input" name="discount_type">
        <option value="percent">Percent</option>
        <option value="flat">Flat ₹</option>
      </select>
    </div>
    <div><label>Value</label><input class="input" type="number" step="0.01" name="discount_value" required></div>
    <div><label>Min order ₹</label><input class="input" type="number" step="0.01" name="min_order" value="0"></div>
  </div>
  <button class="btn" type="submit">
    <span class="material-symbols-outlined text-[18px]">percent</span> Save discount
  </button>
</form>

<div class="card !p-0 overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Title</th><th>Type</th><th>Value</th><th>Min</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="font-semibold text-gray-900"><?= ha_h($r['title']) ?></td>
            <td><span class="badge badge-gray"><?= ha_h($r['discount_type']) ?></span></td>
            <td class="font-medium"><?= ha_h((string)$r['discount_value']) ?></td>
            <td>₹<?= number_format((float)$r['min_order'], 2) ?></td>
            <td><a class="btn secondary sm" href="?del=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this discount?')">Delete</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <span class="material-symbols-outlined">percent</span>
              <p>No discounts yet</p>
            </div>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php ha_layout_end(); ?>
