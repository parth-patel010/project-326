<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bill_tax.php';
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
    if (ha_col_exists('pos_orders', 'bill_printed', $pdo)) {
        $pdo->prepare('UPDATE pos_orders SET bill_printed=1 WHERE id=:id AND hotel_id=:h')
            ->execute([':id' => $id, ':h' => $hotelId]);
    }
    try {
        $pdo->prepare("UPDATE pos_orders SET status='printed' WHERE id=:id AND hotel_id=:h AND status IN ('open','preparing','ready')")
            ->execute([':id' => $id, ':h' => $hotelId]);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE pos_orders SET status='ready' WHERE id=:id AND hotel_id=:h AND status IN ('open','preparing')")
            ->execute([':id' => $id, ':h' => $hotelId]);
    }
} catch (Throwable $e) {
    // Print still works even if status update fails
}

$items = json_decode((string)$order['items_json'], true) ?: [];
$gstEnabled = !empty($hotel['gst_enabled']);
$gstPercent = (float)($hotel['gst_percent'] ?? 5);
$servicePercent = (float)($hotel['service_charge_percent'] ?? 0);
$discount = (float)($order['discount'] ?? 0);
$totals = fm_bill_totals($items, $discount, $gstPercent, $gstEnabled, $servicePercent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bill <?= htmlspecialchars($order['public_id']) ?></title>
  <style>
    body{font-family:ui-monospace,Consolas,monospace;font-size:12px;width:280px;margin:12px auto;color:#000}
    h1{font-size:16px;margin:0;text-align:center}
    .muted{color:#444;text-align:center;font-size:11px}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    td{padding:2px 0;vertical-align:top}
    .r{text-align:right}
    .dash{border-top:1px dashed #000;margin:8px 0}
    .tot{font-weight:700;font-size:13px}
    @media print{body{margin:0}.noprint{display:none}}
  </style>
</head>
<body onload="window.print()">
  <h1><?= htmlspecialchars($hotel['name'] ?? 'FoodMitra') ?></h1>
  <p class="muted"><?= htmlspecialchars($hotel['address'] ?? $hotel['area'] ?? '') ?></p>
  <?php if (!empty($hotel['gst_number'])): ?><p class="muted">GSTIN: <?= htmlspecialchars($hotel['gst_number']) ?></p><?php endif; ?>
  <div class="dash"></div>
  <p>Bill: <strong><?= htmlspecialchars($order['public_id']) ?></strong></p>
  <p>Date: <?= htmlspecialchars($order['created_at']) ?></p>
  <p><?= (($order['order_type'] ?? '') === 'pickup') ? 'Pickup' : ('Table ' . htmlspecialchars((string)($order['table_no'] ?? '—'))) ?></p>
  <p>Customer: <?= htmlspecialchars($order['customer_name']) ?> <?= htmlspecialchars($order['customer_phone'] ?? '') ?></p>
  <div class="dash"></div>
  <table>
    <tr><td><strong>Item</strong></td><td class="r"><strong>Qty</strong></td><td class="r"><strong>Amt</strong></td></tr>
    <?php foreach ($items as $it):
      $line = ((float)$it['price']) * ((int)$it['qty']);
      $label = $it['name'] . (!empty($it['variant']) ? ' ('.$it['variant'].')' : '');
    ?>
      <tr>
        <td><?= htmlspecialchars($label) ?></td>
        <td class="r"><?= (int)$it['qty'] ?></td>
        <td class="r"><?= number_format($line, 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="dash"></div>
  <table>
    <tr><td>Subtotal</td><td class="r"><?= number_format($totals['subtotal'], 2) ?></td></tr>
    <?php if ($totals['discount'] > 0): ?><tr><td>Discount</td><td class="r">-<?= number_format($totals['discount'], 2) ?></td></tr><?php endif; ?>
    <?php if ($gstEnabled && $totals['tax'] > 0): ?>
      <tr><td>CGST</td><td class="r"><?= number_format($totals['cgst'], 2) ?></td></tr>
      <tr><td>SGST</td><td class="r"><?= number_format($totals['sgst'], 2) ?></td></tr>
    <?php endif; ?>
    <?php if ($totals['service_charge'] > 0): ?><tr><td>Service</td><td class="r"><?= number_format($totals['service_charge'], 2) ?></td></tr><?php endif; ?>
    <tr class="tot"><td>Grand Total</td><td class="r">₹<?= number_format($totals['total'], 2) ?></td></tr>
  </table>
  <?php if (!empty($order['payment_mode'])): ?>
    <p>Paid: <?= htmlspecialchars(strtoupper($order['payment_mode'])) ?></p>
  <?php endif; ?>
  <div class="dash"></div>
  <p class="muted">Thank you · FoodMitra</p>
  <p class="noprint" style="text-align:center;margin-top:16px">
    <button onclick="window.print()">Print</button>
    <a href="pos-billing.php?id=<?= (int)$id ?>">Back</a>
  </p>
</body>
</html>
