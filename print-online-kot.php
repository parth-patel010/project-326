<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
$pdo = admin_db();
$hotel = ha_hotel() ?? [];
$publicId = (string) ($hotel['public_id'] ?? '');

$st = $pdo->prepare(
    'SELECT * FROM orders WHERE id=:id AND (hotel_db_id=:h OR restaurant_id=:pid) LIMIT 1'
);
$st->execute([':id' => $id, ':h' => $hotelId, ':pid' => $publicId]);
$order = $st->fetch();
if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

$items = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];
$totalQty = 0;
foreach ($items as $it) {
    $totalQty += (int) ($it['qty'] ?? 1);
}
$orderNote = trim((string) ($order['note'] ?? ''));
$hasNoCutleryCol = array_key_exists('no_cutlery', $order);
$noCutlery = $hasNoCutleryCol && !empty($order['no_cutlery']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>KOT <?= htmlspecialchars((string) $order['public_id']) ?></title>
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
  <p class="muted"><?= htmlspecialchars((string) ($hotel['name'] ?? 'FoodMitra')) ?></p>
  <div class="dash"></div>
  <p class="big">#<?= htmlspecialchars((string) $order['public_id']) ?></p>
  <p>DELIVERY</p>
  <p><?= htmlspecialchars((string) $order['created_at']) ?></p>
  <p><?= htmlspecialchars((string) $order['customer_name']) ?> <?= htmlspecialchars((string) ($order['customer_phone'] ?? '')) ?></p>
  <?php if (!empty($order['delivery_line'])): ?>
    <p><?= htmlspecialchars((string) ($order['delivery_label'] ?? '')) ?>: <?= htmlspecialchars((string) $order['delivery_line']) ?></p>
  <?php endif; ?>
  <?php if ($orderNote !== ''): ?><p><strong>Note:</strong> <?= htmlspecialchars($orderNote) ?></p><?php endif; ?>
  <?php if ($hasNoCutleryCol): ?>
    <p><strong>Cutlery:</strong> <?= $noCutlery ? 'NO CUTLERY' : 'Include cutlery' ?></p>
  <?php endif; ?>
  <div class="dash"></div>
  <table>
    <tr><td><strong>Item</strong></td><td class="r"><strong>Qty</strong></td></tr>
    <?php foreach ($items as $i => $it):
      $itemNote = trim((string) ($it['note'] ?? $it['special_note'] ?? $it['instructions'] ?? ''));
    ?>
      <tr>
        <td>
          <?= ($i + 1) ?>. <?= htmlspecialchars((string) ($it['name'] ?? 'Item')) ?>
          <?php if ($itemNote !== ''): ?><br><em>Note: <?= htmlspecialchars($itemNote) ?></em><?php endif; ?>
        </td>
        <td class="r big"><?= (int) ($it['qty'] ?? 1) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="dash"></div>
  <p class="big">Total qty: <?= $totalQty ?></p>
  <p class="noprint" style="text-align:center;margin-top:16px">
    <button onclick="window.print()">Print</button>
    <a href="online-orders.php">Back</a>
  </p>
</body>
</html>
