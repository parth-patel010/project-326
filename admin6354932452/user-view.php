<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: users.php');
    exit;
}

function sa_table_exists_uv(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute([':t' => $table]);
    return (int) $st->fetchColumn() > 0;
}

$st = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$st->execute([':id' => $id]);
$user = $st->fetch();
if (!$user) {
    http_response_code(404);
    echo 'Customer not found';
    exit;
}

$phone = (string) $user['phone'];
$hasLocations = sa_table_exists_uv($pdo, 'user_locations');
$hasAddresses = sa_table_exists_uv($pdo, 'user_addresses');

$location = null;
if ($hasLocations) {
    $ls = $pdo->prepare(
        'SELECT * FROM user_locations
         WHERE user_id = :uid OR phone = :p
         ORDER BY updated_at DESC LIMIT 1'
    );
    $ls->execute([':uid' => $id, ':p' => $phone]);
    $location = $ls->fetch() ?: null;
}

$addresses = [];
if ($hasAddresses) {
    $as = $pdo->prepare(
        'SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC'
    );
    $as->execute([':u' => $id]);
    $addresses = $as->fetchAll();
}

$statsSt = $pdo->prepare(
    "SELECT
       COUNT(*) AS total_orders,
       SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
       SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
       SUM(CASE WHEN status = 'delivered' THEN total_paise ELSE 0 END) AS gmv_paise,
       AVG(CASE WHEN status = 'delivered' THEN total_paise ELSE NULL END) AS aov_paise
     FROM orders
     WHERE customer_phone = :p"
);
$statsSt->execute([':p' => $phone]);
$stats = $statsSt->fetch() ?: [];

$ordersSt = $pdo->prepare(
    'SELECT id, public_id, restaurant_name, status, payment_mode, total_paise, created_at
     FROM orders WHERE customer_phone = :p
     ORDER BY id DESC LIMIT 40'
);
$ordersSt->execute([':p' => $phone]);
$orders = $ordersSt->fetchAll();

$lat = $location ? (string) ($location['latitude'] ?? '') : '';
$lng = $location ? (string) ($location['longitude'] ?? '') : '';
$mapUrl = ($lat !== '' && $lng !== '' && (float) $lat !== 0.0 && (float) $lng !== 0.0)
    ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat) . '&mlon=' . rawurlencode($lng) . '#map=17/' . rawurlencode($lat) . '/' . rawurlencode($lng)
    : '';

$totalOrders = (int) ($stats['total_orders'] ?? 0);
$delivered = (int) ($stats['delivered'] ?? 0);
$cancelled = (int) ($stats['cancelled'] ?? 0);
$gmv = ((int) ($stats['gmv_paise'] ?? 0)) / 100;
$aov = $stats['aov_paise'] !== null ? ((float) $stats['aov_paise']) / 100 : 0.0;

sa_layout_start(
    (string) ($user['name'] !== '' ? $user['name'] : $phone),
    'users.php',
    'Customer profile · ' . $phone
);
?>
<p class="mb-4"><a class="text-sm font-medium text-primary hover:underline" href="users.php">← Back to customers</a></p>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
  <div class="card lg:col-span-1 !mb-0">
    <h3>Profile</h3>
    <dl class="space-y-2 text-sm">
      <div><dt class="muted">Name</dt><dd class="font-semibold"><?= sa_h($user['name'] !== '' ? $user['name'] : '—') ?></dd></div>
      <div><dt class="muted">Phone</dt><dd class="font-semibold"><?= sa_h($phone) ?></dd></div>
      <div><dt class="muted">Public ID</dt><dd class="font-mono text-xs"><?= sa_h((string) $user['public_id']) ?></dd></div>
      <div><dt class="muted">Email</dt><dd><?= sa_h((string) ($user['email'] ?? '—')) ?></dd></div>
      <div><dt class="muted">Joined</dt><dd><?= sa_h((string) $user['created_at']) ?></dd></div>
      <div><dt class="muted">Last login</dt><dd><?= sa_h((string) ($user['last_login_at'] ?? '—')) ?></dd></div>
      <div><dt class="muted">Status</dt><dd><?= !empty($user['is_active']) ? 'Active' : 'Inactive' ?></dd></div>
    </dl>
  </div>

  <div class="card lg:col-span-1 !mb-0">
    <h3>Last GPS</h3>
    <?php if ($mapUrl): ?>
      <p class="font-semibold text-gray-900 mb-1"><?= sa_h(number_format((float) $lat, 6)) ?>, <?= sa_h(number_format((float) $lng, 6)) ?></p>
      <?php if (!empty($location['updated_at'])): ?>
        <p class="muted mb-3">Updated <?= sa_h((string) $location['updated_at']) ?></p>
      <?php endif; ?>
      <a class="btn secondary sm" href="<?= sa_h($mapUrl) ?>" target="_blank" rel="noopener">
        <span class="material-icons-outlined text-[18px]">map</span> Open map
      </a>
    <?php else: ?>
      <p class="muted">No location recorded</p>
    <?php endif; ?>
  </div>

  <div class="card lg:col-span-1 !mb-0">
    <h3>Order stats</h3>
    <div class="grid grid-cols-2 gap-3">
      <div class="stat !p-3 !mb-0"><div class="n text-xl"><?= $totalOrders ?></div><div class="l">Total</div></div>
      <div class="stat !p-3 !mb-0"><div class="n text-xl"><?= $delivered ?></div><div class="l">Delivered</div></div>
      <div class="stat !p-3 !mb-0"><div class="n text-xl"><?= $cancelled ?></div><div class="l">Cancelled</div></div>
      <div class="stat !p-3 !mb-0"><div class="n text-xl">₹<?= number_format($gmv, 0) ?></div><div class="l">GMV</div></div>
      <div class="stat !p-3 !mb-0 col-span-2"><div class="n text-xl">₹<?= number_format($aov, 2) ?></div><div class="l">AOV (delivered)</div></div>
    </div>
  </div>
</div>

<div class="card">
  <h3>Saved addresses</h3>
  <?php if (!$hasAddresses): ?>
    <p class="muted">Address table not available</p>
  <?php elseif (!$addresses): ?>
    <p class="muted">No saved addresses</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table>
        <thead>
          <tr><th>Label</th><th>Line</th><th>Receiver</th><th>Default</th></tr>
        </thead>
        <tbody>
          <?php foreach ($addresses as $a): ?>
            <tr>
              <td class="font-medium"><?= sa_h($a['label']) ?></td>
              <td>
                <?= sa_h($a['line']) ?>
                <?php if (!empty($a['details'])): ?><p class="muted"><?= sa_h($a['details']) ?></p><?php endif; ?>
              </td>
              <td>
                <?= sa_h((string) ($a['receiver_name'] ?? '')) ?>
                <p class="muted"><?= sa_h((string) ($a['receiver_phone'] ?? '')) ?></p>
              </td>
              <td><?= !empty($a['is_default']) ? 'Yes' : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
    <h3 class="!mb-0 text-base font-bold text-gray-900">Recent orders</h3>
    <a class="text-sm font-medium text-primary hover:underline" href="online-orders.php">All online orders</a>
  </div>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr><th>Order</th><th>Hotel</th><th>Status</th><th>Pay</th><th>Total</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>
              <p class="font-semibold text-primary"><?= sa_h($o['public_id']) ?></p>
              <p class="muted"><?= sa_h($o['created_at']) ?></p>
            </td>
            <td><?= sa_h($o['restaurant_name']) ?></td>
            <td><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?= sa_h($o['status']) ?></span></td>
            <td><?= sa_h($o['payment_mode']) ?></td>
            <td class="font-semibold">₹<?= number_format(((int) $o['total_paise']) / 100, 2) ?></td>
            <td><a class="text-sm font-medium text-primary hover:underline" href="online-orders.php?status=<?= rawurlencode((string) $o['status']) ?>">Open list</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
          <tr><td colspan="6" class="text-center text-gray-500 py-10">No orders</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
