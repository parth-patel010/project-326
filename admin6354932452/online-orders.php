<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
require_once dirname(__DIR__) . '/api/lib/order_status.php';
$status = trim((string) ($_GET['status'] ?? ''));
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid = (int) ($_POST['order_id'] ?? 0);
    $newStatus = trim((string) ($_POST['new_status'] ?? ''));
    $allowed = ['placed','preparing','ready','out_for_delivery','delivered','cancelled'];
    if ($oid > 0 && in_array($newStatus, $allowed, true)) {
        fm_order_set_status($pdo, $oid, $newStatus);
        $flash = 'Order #' . $oid . ' set to ' . $newStatus;
    }
}

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

sa_layout_start('Online Orders', 'online-orders.php', 'Live app orders — filter and force status');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<div class="card !p-4 mb-4">
  <form method="get" class="flex flex-wrap items-end gap-3">
    <div class="min-w-[200px] flex-1">
      <label>Status</label>
      <select name="status" class="input !mb-0">
        <option value="">All statuses</option>
        <?php foreach (['awaiting_payment','placed','preparing','ready','out_for_delivery','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">
      <span class="material-icons-outlined text-[18px]">filter_list</span> Filter
    </button>
  </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Order</th><th>Hotel</th><th>Customer</th><th>Status</th><th>Pay</th><th>Total</th><th>Partner</th><th>Override</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <p class="font-semibold text-primary"><?= sa_h($r['public_id']) ?></p>
              <p class="muted whitespace-nowrap"><?= sa_h($r['created_at']) ?></p>
            </td>
            <td><?= sa_h($r['restaurant_name']) ?></td>
            <td>
              <p class="font-medium text-gray-900"><?= sa_h($r['customer_name']) ?></p>
              <p class="muted"><?= sa_h($r['customer_phone']) ?></p>
            </td>
            <td><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?= sa_h($r['status']) ?></span></td>
            <td><?= sa_h($r['payment_mode']) ?></td>
            <td class="font-semibold">₹<?= number_format(((int)$r['total_paise'])/100, 2) ?></td>
            <td class="muted"><?= sa_h((string)($r['assigned_partner_id'] ?? '—')) ?></td>
            <td>
              <form method="post" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="order_id" value="<?= (int)$r['id'] ?>">
                <select name="new_status" class="input !mb-0 !w-auto text-xs py-1">
                  <?php foreach (['placed','preparing','ready','out_for_delivery','delivered','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn !py-1.5 !px-3 text-xs" type="submit">Set</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-gray-500 py-10">No orders found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
