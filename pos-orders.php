<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name = trim((string) ($_POST['customer_name'] ?? 'Walk-in'));
    $total = (float) ($_POST['total'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));
    $publicId = 'POS' . strtoupper(bin2hex(random_bytes(4)));
    $items = [['name' => 'POS Bill', 'qty' => 1, 'price' => $total]];
    $pdo->prepare(
        'INSERT INTO pos_orders (public_id, hotel_id, customer_name, status, subtotal, total, items_json, note)
         VALUES (:pid, :hid, :name, \'open\', :sub, :total, :items, :note)'
    )->execute([
        ':pid' => $publicId,
        ':hid' => $hotelId,
        ':name' => $name,
        ':sub' => $total,
        ':total' => $total,
        ':items' => json_encode($items),
        ':note' => $note,
    ]);
    $flash = 'POS order created';
}

if (isset($_GET['complete']) && ctype_digit((string)$_GET['complete'])) {
    $pdo->prepare('UPDATE pos_orders SET status=\'completed\' WHERE id=:id AND hotel_id=:hid')
        ->execute([':id' => (int)$_GET['complete'], ':hid' => $hotelId]);
    $flash = 'POS order completed';
}

$stmt = $pdo->prepare('SELECT * FROM pos_orders WHERE hotel_id = :hid ORDER BY id DESC LIMIT 100');
$stmt->execute([':hid' => $hotelId]);
$rows = $stmt->fetchAll();

ha_layout_start('POS Orders', 'pos-orders.php');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<form method="post" class="card">
  <input type="hidden" name="action" value="create">
  <label>Customer name</label>
  <input class="input" name="customer_name" value="Walk-in">
  <label>Total ₹</label>
  <input class="input" type="number" step="0.01" name="total" required>
  <label>Note</label>
  <input class="input" name="note">
  <button class="btn" type="submit">Create POS order</button>
</form>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Customer</th><th>Status</th><th>Total</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= ha_h($r['public_id']) ?></td>
          <td><?= ha_h($r['customer_name']) ?></td>
          <td><?= ha_h($r['status']) ?></td>
          <td>₹<?= number_format((float)$r['total'], 2) ?></td>
          <td><?= ha_h($r['created_at']) ?></td>
          <td><?php if ($r['status'] !== 'completed'): ?><a class="btn secondary" href="?complete=<?= (int)$r['id'] ?>">Complete</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php ha_layout_end(); ?>
