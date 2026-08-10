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

ha_layout_start('POS Orders', 'pos-orders.php', 'Counter / walk-in bills for your hotel');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>

<form method="post" class="card max-w-xl">
  <h3>New POS order</h3>
  <input type="hidden" name="action" value="create">
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div><label>Customer name</label><input class="input" name="customer_name" value="Walk-in"></div>
    <div><label>Total ₹</label><input class="input" type="number" step="0.01" name="total" required></div>
    <div class="sm:col-span-2"><label>Note</label><input class="input" name="note"></div>
  </div>
  <button class="btn" type="submit">
    <span class="material-icons-outlined text-[18px]">add</span> Create POS order
  </button>
</form>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>ID</th><th>Customer</th><th>Status</th><th>Total</th><th>Created</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="font-mono text-xs text-gray-500"><?= ha_h($r['public_id']) ?></td>
            <td class="font-medium"><?= ha_h($r['customer_name']) ?></td>
            <td>
              <?php if ($r['status'] === 'completed'): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">completed</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><?= ha_h($r['status']) ?></span>
              <?php endif; ?>
            </td>
            <td class="font-semibold">₹<?= number_format((float)$r['total'], 2) ?></td>
            <td class="muted whitespace-nowrap"><?= ha_h($r['created_at']) ?></td>
            <td>
              <?php if ($r['status'] !== 'completed'): ?>
                <a class="btn secondary !py-1.5 !px-3 text-xs" href="?complete=<?= (int)$r['id'] ?>">Complete</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-gray-500 py-10">No POS orders yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php ha_layout_end(); ?>
