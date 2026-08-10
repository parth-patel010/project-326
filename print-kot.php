<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int) $_GET['id'] : 0;
$pdo = admin_db();
$hotel = ha_hotel() ?? [];

$st = $pdo->prepare('SELECT * FROM pos_orders WHERE id=:id AND hotel_id=:h');
$st->execute([':id' => $id, ':h' => $hotelId]);
$order = $st->fetch();
if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

try {
    if (ha_col_exists('pos_orders', 'kot_printed', $pdo)) {
        $pdo->prepare('UPDATE pos_orders SET kot_printed=1 WHERE id=:id AND hotel_id=:h')
            ->execute([':id' => $id, ':h' => $hotelId]);
    }
    try {
        $pdo->prepare("UPDATE pos_orders SET status='preparing' WHERE id=:id AND hotel_id=:h AND status='open'")
            ->execute([':id' => $id, ':h' => $hotelId]);
    } catch (Throwable $e) {
        // preparing may be missing from very old enums — ignore
    }
} catch (Throwable $e) {
    // Print still works
}

$items = json_decode((string)$order['items_json'], true) ?: [];
$totalQty = 0;
foreach ($items as $it) {
    $totalQty += (int)($it['qty'] ?? 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>KOT <?= htmlspecialchars($order['public_id']) ?></title>
  <style>
    body{font-family:ui-monospace,Consolas,monospace;font-size:13px;width:280px;margin:12px auto;color:#000}
    h1{font-size:18px;margin:0;text-align:center;letter-spacing:2px}
    .muted{text-align:center;font-size:11px}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    td{padding:3px 0;vertical-align:top}
    .r{text-align:right}
    .dash{border-top:1px dashed #000;margin:8px 0}
    .big{font-size:15px;font-weight:700}
    @media print{body{margin:0}.noprint{display:none}}
  </style>
</head>
<body onload="window.print()">
  <h1>KOT</h1>
  <p class="muted"><?= htmlspecialchars($hotel['name'] ?? 'FoodMitra') ?></p>
  <div class="dash"></div>
  <p class="big">#<?= htmlspecialchars($order['public_id']) ?></p>
  <p><?= ($order['order_type'] ?? '') === 'pickup' ? 'PICKUP' : ('TABLE ' . htmlspecialchars((string)($order['table_no'] ?? '—'))) ?></p>
  <p><?= htmlspecialchars($order['created_at']) ?></p>
  <?php if (!empty($order['note'])): ?><p>Note: <?= htmlspecialchars($order['note']) ?></p><?php endif; ?>
  <div class="dash"></div>
  <table>
    <tr><td><strong>Item</strong></td><td class="r"><strong>Qty</strong></td></tr>
    <?php foreach ($items as $i => $it):
      $label = $it['name'] . (!empty($it['variant']) ? ' ('.$it['variant'].')' : '');
    ?>
      <tr>
        <td><?= ($i+1) ?>. <?= htmlspecialchars($label) ?><?php if (!empty($it['note'])): ?><br><em><?= htmlspecialchars($it['note']) ?></em><?php endif; ?></td>
        <td class="r big"><?= (int)$it['qty'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="dash"></div>
  <p class="big">Total qty: <?= $totalQty ?></p>
  <p class="noprint" style="text-align:center;margin-top:16px">
    <button onclick="window.print()">Print</button>
    <a href="pos-billing.php?id=<?= (int)$id ?>">Back</a>
  </p>
</body>
</html>
