<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();

function sa_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute([':t' => $table]);
    return (int) $st->fetchColumn() > 0;
}

$q = trim((string) ($_GET['q'] ?? ''));
$hasLocations = sa_table_exists($pdo, 'user_locations');

$sql = 'SELECT u.id, u.public_id, u.name, u.phone, u.created_at, u.last_login_at, u.is_active,
               COALESCE(oc.order_count, 0) AS order_count,
               COALESCE(oc.total_spend_paise, 0) AS total_spend_paise';
if ($hasLocations) {
    $sql .= ',
      (SELECT latitude FROM user_locations loc
        WHERE loc.user_id = u.id OR loc.phone = u.phone
        ORDER BY loc.updated_at DESC LIMIT 1) AS last_lat,
      (SELECT longitude FROM user_locations loc
        WHERE loc.user_id = u.id OR loc.phone = u.phone
        ORDER BY loc.updated_at DESC LIMIT 1) AS last_lng,
      (SELECT updated_at FROM user_locations loc
        WHERE loc.user_id = u.id OR loc.phone = u.phone
        ORDER BY loc.updated_at DESC LIMIT 1) AS loc_updated_at';
}
$sql .= ' FROM users u
          LEFT JOIN (
            SELECT customer_phone,
                   COUNT(*) AS order_count,
                   SUM(CASE WHEN status NOT IN (\'cancelled\',\'awaiting_payment\') THEN total_paise ELSE 0 END) AS total_spend_paise
            FROM orders
            WHERE customer_phone IS NOT NULL AND customer_phone <> \'\'
            GROUP BY customer_phone
          ) oc ON oc.customer_phone = u.phone';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.phone LIKE :q OR u.name LIKE :q2';
    $params[':q'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}
$sql .= ' ORDER BY u.id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

sa_layout_start('Customers', 'users.php', 'App users — search, orders, and last location');
?>
<div class="card !p-4 mb-4">
  <form method="get" class="flex flex-wrap items-end gap-3">
    <div class="min-w-[220px] flex-1">
      <label>Search</label>
      <input class="input !mb-0" type="search" name="q" value="<?= sa_h($q) ?>" placeholder="Phone or name">
    </div>
    <button class="btn" type="submit">
      <span class="material-icons-outlined text-[18px]">search</span> Search
    </button>
    <?php if ($q !== ''): ?>
      <a class="btn secondary" href="users.php">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>Name</th><th>Phone</th><th>Joined</th><th>Last location</th><th>Orders</th><th>Spend</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
            $lat = $hasLocations && isset($r['last_lat']) ? (string) $r['last_lat'] : '';
            $lng = $hasLocations && isset($r['last_lng']) ? (string) $r['last_lng'] : '';
            $map = ($lat !== '' && $lng !== '' && $lat !== '0' && $lng !== '0')
                ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat) . '&mlon=' . rawurlencode($lng) . '#map=16/' . rawurlencode($lat) . '/' . rawurlencode($lng)
                : '';
        ?>
          <tr>
            <td class="font-semibold text-gray-900"><?= sa_h($r['name'] !== '' ? $r['name'] : '—') ?></td>
            <td><?= sa_h($r['phone']) ?></td>
            <td class="muted whitespace-nowrap"><?= sa_h($r['created_at']) ?></td>
            <td>
              <?php if ($map): ?>
                <a class="text-sm font-medium text-primary hover:underline" href="<?= sa_h($map) ?>" target="_blank" rel="noopener">
                  <?= sa_h(number_format((float) $lat, 5)) ?>, <?= sa_h(number_format((float) $lng, 5)) ?>
                </a>
                <?php if (!empty($r['loc_updated_at'])): ?>
                  <p class="muted"><?= sa_h((string) $r['loc_updated_at']) ?></p>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= (int) $r['order_count'] ?></td>
            <td class="font-semibold">₹<?= number_format(((int) $r['total_spend_paise']) / 100, 2) ?></td>
            <td><a class="text-sm font-medium text-primary hover:underline" href="user-view.php?id=<?= (int) $r['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-gray-500 py-10">No customers found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
