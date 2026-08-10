<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$hotel = ha_hotel() ?? [];
$hasTables = ha_col_exists('hotels', 'dining_total_tables', $pdo);
$hasOrderType = ha_col_exists('pos_orders', 'order_type', $pdo);
$hasTableNo = ha_col_exists('pos_orders', 'table_no', $pdo);
$tableCount = $hasTables ? max(1, (int) ($hotel['dining_total_tables'] ?? 12)) : 12;
$flash = '';

if (isset($_GET['clean']) && ctype_digit((string)$_GET['clean'])) {
    try {
        $pdo->prepare("UPDATE pos_orders SET status='completed' WHERE id=:id AND hotel_id=:h AND status IN ('paid','printed','ready','completed')")
            ->execute([':id' => (int)$_GET['clean'], ':h' => $hotelId]);
        $flash = 'Table cleaned';
    } catch (Throwable $e) {
        try {
            $pdo->prepare("UPDATE pos_orders SET status='completed' WHERE id=:id AND hotel_id=:h")
                ->execute([':id' => (int)$_GET['clean'], ':h' => $hotelId]);
            $flash = 'Table cleaned';
        } catch (Throwable $e2) {
            $flash = 'Could not clean table';
        }
    }
}
if (isset($_GET['pickup'])) {
    header('Location: pos-billing.php?type=pickup');
    exit;
}

$byTable = [];
$pickupRows = [];
try {
    if ($hasOrderType) {
        $open = $pdo->prepare(
            "SELECT * FROM pos_orders WHERE hotel_id=:h AND status NOT IN ('completed','cancelled')
             AND (order_type='dine_in' OR order_type IS NULL OR order_type='')
             ORDER BY id DESC"
        );
    } else {
        $open = $pdo->prepare(
            "SELECT * FROM pos_orders WHERE hotel_id=:h AND status NOT IN ('completed','cancelled') ORDER BY id DESC"
        );
    }
    $open->execute([':h' => $hotelId]);
    foreach ($open->fetchAll() as $row) {
        $t = $hasTableNo ? (string) ($row['table_no'] ?? '') : '';
        if ($t === '' && !$hasTableNo) {
            // Pre-migrate: show as open bills without floor map keys
            continue;
        }
        if ($t !== '' && !isset($byTable[$t])) {
            $byTable[$t] = $row;
        }
    }

    if ($hasOrderType) {
        $pickups = $pdo->prepare(
            "SELECT * FROM pos_orders WHERE hotel_id=:h AND order_type='pickup' AND status NOT IN ('completed','cancelled') ORDER BY id DESC LIMIT 20"
        );
        $pickups->execute([':h' => $hotelId]);
        $pickupRows = $pickups->fetchAll();
    }
} catch (Throwable $e) {
    $flash = $flash ?: ('POS data unavailable until migrate: ' . $e->getMessage());
}

// Fallback list of open bills when table_no missing
$openBills = [];
if (!$hasTableNo) {
    try {
        $st = $pdo->prepare("SELECT * FROM pos_orders WHERE hotel_id=:h AND status NOT IN ('completed','cancelled') ORDER BY id DESC LIMIT 40");
        $st->execute([':h' => $hotelId]);
        $openBills = $st->fetchAll();
    } catch (Throwable $e) {
        $openBills = [];
    }
}

$statusColor = static function (string $status): string {
    return match ($status) {
        'open', 'preparing' => 'bg-amber-100 border-amber-300 text-amber-900',
        'ready' => 'bg-blue-100 border-blue-300 text-blue-900',
        'printed' => 'bg-emerald-100 border-emerald-400 text-emerald-900',
        'paid' => 'bg-orange-100 border-orange-300 text-orange-900',
        default => 'bg-white border-gray-200 text-gray-700',
    };
};

ha_layout_start('POS Orders', 'pos-orders.php', 'Floor map — tap a table to bill');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>

<div class="card flex flex-wrap items-center justify-between gap-3 !py-3">
  <div class="flex flex-wrap gap-3 text-xs font-medium">
    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-gray-200 border"></span> Free</span>
    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-amber-400"></span> Running</span>
    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> Bill printed</span>
    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-orange-400"></span> Paid</span>
  </div>
  <div class="flex gap-2">
    <a class="btn secondary !py-2" href="pos-orders.php"><span class="material-icons-outlined text-[18px]">refresh</span> Refresh</a>
    <a class="btn secondary !py-2" href="pos-billing.php"><span class="material-icons-outlined text-[18px]">receipt_long</span> New bill</a>
    <a class="btn" href="pos-billing.php?type=pickup"><span class="material-icons-outlined text-[18px]">takeout_dining</span> New pickup</a>
  </div>
</div>

<?php if ($hasTableNo): ?>
<h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Tables</h3>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-8">
  <?php for ($i = 1; $i <= $tableCount; $i++):
      $key = (string) $i;
      $order = $byTable[$key] ?? null;
      $cls = $order ? $statusColor((string)$order['status']) : 'bg-white border-gray-200 hover:border-primary text-gray-800';
      $href = $order
          ? 'pos-billing.php?id=' . (int)$order['id']
          : 'pos-billing.php?type=dine_in&table=' . $i;
  ?>
    <a href="<?= $href ?>" class="rounded-xl border-2 p-4 shadow-sm transition hover:shadow <?= $cls ?> min-h-[110px] flex flex-col justify-between">
      <div class="flex justify-between items-start">
        <span class="text-2xl font-bold"><?= $i ?></span>
        <?php if ($order): ?>
          <span class="text-[10px] font-bold uppercase"><?= ha_h($order['status']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($order):
          $items = json_decode((string)$order['items_json'], true) ?: [];
          $qty = 0;
          foreach ($items as $it) { $qty += (int)($it['qty'] ?? 1); }
      ?>
        <div>
          <p class="text-sm font-semibold">₹<?= number_format((float)$order['total'], 0) ?></p>
          <p class="text-xs opacity-80"><?= $qty ?> items · <?= ha_h($order['customer_name'] ?: 'Guest') ?></p>
          <?php if (in_array($order['status'], ['paid','printed'], true)): ?>
            <span class="inline-block mt-1 text-[10px] underline" onclick="event.preventDefault(); location.href='?clean=<?= (int)$order['id'] ?>'">Clean table</span>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="text-xs text-gray-400">Tap to open bill</p>
      <?php endif; ?>
    </a>
  <?php endfor; ?>
</div>
<?php else: ?>
<div class="card mb-6">
  <p class="muted !mb-3">Floor map needs <code>migrate_hotel_pos_ops.php</code>. Showing open bills list instead.</p>
  <div class="space-y-2">
    <?php foreach ($openBills as $b): ?>
      <a href="pos-billing.php?id=<?= (int)$b['id'] ?>" class="flex justify-between items-center p-3 rounded-lg border border-gray-200 hover:border-primary">
        <span class="font-semibold"><?= ha_h($b['public_id']) ?> · <?= ha_h($b['customer_name']) ?></span>
        <span>₹<?= number_format((float)$b['total'], 0) ?> · <?= ha_h($b['status']) ?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$openBills): ?><p class="muted">No open bills</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($hasOrderType): ?>
<h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Active pickups</h3>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
  <?php foreach ($pickupRows as $p): ?>
    <a href="pos-billing.php?id=<?= (int)$p['id'] ?>" class="rounded-xl border-2 p-4 <?= $statusColor((string)$p['status']) ?>">
      <div class="flex justify-between"><span class="font-bold"><?= ha_h($p['public_id']) ?></span><span class="text-xs uppercase"><?= ha_h($p['status']) ?></span></div>
      <p class="text-sm mt-1"><?= ha_h($p['customer_name']) ?> · ₹<?= number_format((float)$p['total'], 0) ?></p>
    </a>
  <?php endforeach; ?>
  <?php if (!$pickupRows): ?>
    <p class="muted">No active pickups</p>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>setTimeout(function(){ location.reload(); }, 20000);</script>
<?php ha_layout_end(); ?>
