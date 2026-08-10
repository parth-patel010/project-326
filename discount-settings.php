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

ha_layout_start('Discount Settings', 'discount-settings.php');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<form method="post" class="card">
  <label>Title</label>
  <input class="input" name="title" required>
  <label>Type</label>
  <select class="input" name="discount_type"><option value="percent">Percent</option><option value="flat">Flat ₹</option></select>
  <label>Value</label>
  <input class="input" type="number" step="0.01" name="discount_value" required>
  <label>Min order ₹</label>
  <input class="input" type="number" step="0.01" name="min_order" value="0">
  <button class="btn" type="submit">Save discount</button>
</form>
<div class="card">
  <table>
    <thead><tr><th>Title</th><th>Type</th><th>Value</th><th>Min</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= ha_h($r['title']) ?></td>
          <td><?= ha_h($r['discount_type']) ?></td>
          <td><?= ha_h((string)$r['discount_value']) ?></td>
          <td>₹<?= number_format((float)$r['min_order'], 2) ?></td>
          <td><a class="btn secondary" href="?del=<?= (int)$r['id'] ?>">Delete</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php ha_layout_end(); ?>
