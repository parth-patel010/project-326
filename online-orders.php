<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api/lib/Env.php';
Env::load(__DIR__ . '/api/.env');
require_once __DIR__ . '/api/lib/Realtime.php';
require_once __DIR__ . '/api/lib/Settings.php';
require_once __DIR__ . '/api/lib/H3.php';
require_once __DIR__ . '/api/lib/Dispatch.php';

ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$hotel = ha_hotel();
$publicId = (string) ($hotel['public_id'] ?? '');
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid = (int) ($_POST['order_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $map = [
        'accept' => 'preparing',
        'ready' => 'ready',
        'cancel' => 'cancelled',
    ];
    if ($oid && isset($map[$action])) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND (hotel_db_id = :hid OR restaurant_id = :pid) LIMIT 1');
        $stmt->execute([':id' => $oid, ':hid' => $hotelId, ':pid' => $publicId]);
        $order = $stmt->fetch();
        if ($order) {
            $newStatus = $map[$action];
            if ($action === 'accept' && empty($order['hotel_db_id'])) {
                $pdo->prepare('UPDATE orders SET hotel_db_id = :hid, status = :s WHERE id = :id')
                    ->execute([':hid' => $hotelId, ':s' => $newStatus, ':id' => $oid]);
            } else {
                $pdo->prepare('UPDATE orders SET status = :s WHERE id = :id')
                    ->execute([':s' => $newStatus, ':id' => $oid]);
            }
            Realtime::emit('order.status', [
                'order_id' => $order['public_id'],
                'status' => $newStatus,
            ], 'order:' . $order['public_id']);

            if ($action === 'ready') {
                $fresh = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
                $fresh->execute([':id' => $oid]);
                $row = $fresh->fetch();
                if ($row && empty($row['assigned_partner_id'])) {
                    Dispatch::offerToNext($row);
                }
            }
            $flash = 'Order updated to ' . $newStatus;
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT * FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) ORDER BY id DESC LIMIT 100'
);
$stmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
$rows = $stmt->fetchAll();

ha_layout_start('Online Orders', 'online-orders.php', 'Accept app orders and share pickup OTP with riders');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Status</th>
          <th>Total</th>
          <th>Pickup OTP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <p class="font-semibold text-primary"><?= ha_h($r['public_id']) ?></p>
              <p class="muted whitespace-nowrap"><?= ha_h($r['created_at']) ?></p>
            </td>
            <td>
              <p class="font-medium text-gray-900"><?= ha_h($r['customer_name']) ?></p>
              <p class="muted"><?= ha_h($r['customer_phone']) ?></p>
            </td>
            <td>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?= ha_h($r['status']) ?></span>
              <p class="muted mt-1"><?= ha_h($r['payment_mode'] ?? '') ?></p>
            </td>
            <td class="font-semibold">₹<?= number_format(((int)$r['total_paise'])/100, 2) ?></td>
            <td>
              <?php if (!empty($r['hotel_otp']) && in_array($r['status'], ['ready','out_for_delivery','preparing'], true)): ?>
                <div class="otp"><?= ha_h($r['hotel_otp']) ?></div>
                <div class="muted">Show to delivery partner</div>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex flex-wrap gap-2">
                <?php if (in_array($r['status'], ['placed','paid'], true)): ?>
                  <form method="post" class="inline">
                    <input type="hidden" name="order_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="accept">
                    <button class="btn !py-1.5 !px-3 text-xs" type="submit">
                      <span class="material-icons-outlined text-[16px]">check</span> Accept
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($r['status'] === 'preparing'): ?>
                  <form method="post" class="inline">
                    <input type="hidden" name="order_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="ready">
                    <button class="btn !py-1.5 !px-3 text-xs" type="submit">
                      <span class="material-icons-outlined text-[16px]">done_all</span> Mark ready
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-gray-500 py-10">No online orders yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php ha_layout_end(); ?>
