<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$status = trim((string) ($_GET['status'] ?? ''));
$sql = 'SELECT * FROM orders';
$params = [];
if ($status !== '') {
    $sql .= ' WHERE status = :s';
    $params[':s'] = $status;
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

sa_layout_start('Online Orders', 'online-orders.php');
?>
<div class="card">
  <form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <div style="min-width:180px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['awaiting_payment','placed','preparing','ready','out_for_delivery','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">Filter</button>
  </form>
</div>
<div class="card">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Hotel</th><th>Customer</th><th>Status</th><th>Pay</th><th>Total</th><th>Partner</th><th>Created</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= sa_h($r['public_id']) ?></td>
          <td><?= sa_h($r['restaurant_name']) ?></td>
          <td><?= sa_h($r['customer_name']) ?><br><span class="muted"><?= sa_h($r['customer_phone']) ?></span></td>
          <td><?= sa_h($r['status']) ?></td>
          <td><?= sa_h($r['payment_mode']) ?></td>
          <td>₹<?= number_format(((int)$r['total_paise'])/100, 2) ?></td>
          <td><?= sa_h((string)($r['assigned_partner_id'] ?? '—')) ?></td>
          <td><?= sa_h($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted">No orders</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php sa_layout_end(); ?>
