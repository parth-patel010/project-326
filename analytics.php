<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$filter = $_GET['filter'] ?? 'today';
$valid = ['today','yesterday','last7','last30','month'];
if (!in_array($filter, $valid, true)) {
    $filter = 'today';
}

$dateSql = match ($filter) {
    'yesterday' => 'DATE(created_at) = CURDATE() - INTERVAL 1 DAY',
    'last7' => 'created_at >= NOW() - INTERVAL 7 DAY',
    'last30' => 'created_at >= NOW() - INTERVAL 30 DAY',
    'month' => 'YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())',
    default => 'DATE(created_at) = CURDATE()',
};

$hasOrderType = ha_col_exists('pos_orders', 'order_type', $pdo);
$hasTableNo = ha_col_exists('pos_orders', 'table_no', $pdo);

$posRev = 0.0;
$posCount = 0;
$posRows = [];
$recentPos = [];
try {
    $posRev = (float) $pdo->query(
        "SELECT COALESCE(SUM(total),0) FROM pos_orders WHERE hotel_id={$hotelId} AND status IN ('paid','completed','printed','ready') AND {$dateSql}"
    )->fetchColumn();
    $posCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM pos_orders WHERE hotel_id={$hotelId} AND {$dateSql}"
    )->fetchColumn();
    $posRows = $pdo->query(
        "SELECT items_json FROM pos_orders WHERE hotel_id={$hotelId} AND status IN ('paid','completed','printed','ready') AND {$dateSql} LIMIT 500"
    )->fetchAll();
    $selectExtra = '';
    if ($hasOrderType) {
        $selectExtra .= ', order_type';
    }
    if ($hasTableNo) {
        $selectExtra .= ', table_no';
    }
    $recentPos = $pdo->query(
        "SELECT public_id, customer_name, status, total, created_at{$selectExtra}
         FROM pos_orders WHERE hotel_id={$hotelId} AND {$dateSql} ORDER BY id DESC LIMIT 30"
    )->fetchAll();
} catch (Throwable $e) {
    // POS table may be empty / enum mismatch — show zeros
}

$hotel = ha_hotel() ?? [];
$publicId = $pdo->quote((string)($hotel['public_id'] ?? ''));
$appRev = 0.0;
$appCount = 0;
try {
    $appRev = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_paise),0)/100 FROM orders
         WHERE (hotel_db_id={$hotelId} OR restaurant_id={$publicId})
         AND status='delivered' AND {$dateSql}"
    )->fetchColumn();
    $appCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM orders
         WHERE (hotel_db_id={$hotelId} OR restaurant_id={$publicId}) AND {$dateSql}"
    )->fetchColumn();
} catch (Throwable $e) {
    $appRev = 0;
    $appCount = 0;
}

$top = [];
foreach ($posRows as $row) {
    $items = json_decode((string)$row['items_json'], true) ?: [];
    foreach ($items as $it) {
        $n = (string)($it['name'] ?? 'Item');
        $top[$n] = ($top[$n] ?? 0) + (int)($it['qty'] ?? 1);
    }
}
arsort($top);
$top = array_slice($top, 0, 8, true);

ha_layout_start('Analytics', 'analytics.php', 'Revenue and sales for your hotel');
?>
<div class="page-header">
  <div>
    <h2>Analytics</h2>
    <p class="sub">Revenue and sales for your hotel</p>
  </div>
</div>

<div class="card !py-3 flex flex-wrap gap-2">
  <?php foreach (['today'=>'Today','yesterday'=>'Yesterday','last7'=>'Last 7 days','last30'=>'Last 30 days','month'=>'This month'] as $k=>$label): ?>
    <a href="?filter=<?= $k ?>" class="px-3 py-1.5 rounded-lg text-sm font-semibold <?= $filter===$k ? 'bg-primary text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-primary/40 hover:text-primary' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="stat-grid">
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">point_of_sale</span></div>
    <p class="n">₹<?= number_format($posRev, 0) ?></p>
    <p class="l">POS revenue · <?= $posCount ?> orders</p>
  </div>
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">smartphone</span></div>
    <p class="n">₹<?= number_format($appRev, 0) ?></p>
    <p class="l">App revenue · <?= $appCount ?> orders</p>
  </div>
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">payments</span></div>
    <p class="n">₹<?= number_format($posRev + $appRev, 0) ?></p>
    <p class="l">Total revenue</p>
  </div>
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">star</span></div>
    <p class="n" style="font-size:1.25rem"><?= $top ? ha_h((string)array_key_first($top)) : '—' ?></p>
    <p class="l"><?= $top ? ((int)reset($top) . ' sold') : 'No POS sales' ?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
  <div class="card">
    <h3><span class="material-symbols-outlined text-[20px] text-primary">trending_up</span> Most sold (POS)</h3>
    <?php if (!$top): ?>
      <div class="empty-state">
        <span class="material-symbols-outlined">inventory_2</span>
        <p>No item data in this period</p>
      </div>
    <?php else: ?>
      <table>
        <thead><tr><th>Item</th><th>Qty</th></tr></thead>
        <tbody>
          <?php foreach ($top as $name => $qty): ?>
            <tr><td><?= ha_h((string)$name) ?></td><td class="font-semibold"><?= (int)$qty ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3><span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span> Recent POS orders</h3>
    <div class="overflow-x-auto">
      <table>
        <thead><tr><th>Bill</th><th>Type</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentPos as $r): ?>
            <tr>
              <td class="font-mono text-xs"><?= ha_h($r['public_id']) ?></td>
              <td><?php
                if ($hasOrderType && ($r['order_type'] ?? '') === 'pickup') {
                    echo 'Pickup';
                } elseif ($hasTableNo) {
                    echo 'T' . ha_h((string)($r['table_no'] ?? '—'));
                } else {
                    echo 'POS';
                }
              ?></td>
              <td>₹<?= number_format((float)$r['total'], 0) ?></td>
              <td><span class="badge badge-gray"><?= ha_h($r['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentPos): ?>
            <tr><td colspan="4">
              <div class="empty-state">
                <span class="material-symbols-outlined">receipt_long</span>
                <p>No orders</p>
              </div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php ha_layout_end(); ?>
