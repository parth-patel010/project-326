<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bill_tax.php';
require_once __DIR__ . '/includes/dining.php';
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

$items = json_decode((string) $order['items_json'], true) ?: [];
$gstEnabled = !empty($hotel['gst_enabled']);
$gstPercent = $gstEnabled ? (float) ($hotel['gst_percent'] ?? 5) : 0.0;
$servicePercent = (float) ($hotel['service_charge_percent'] ?? 0);
$discount = (float) ($order['discount'] ?? 0);
$totals = fm_bill_totals($items, $discount, $gstPercent, $gstEnabled, $servicePercent);

$orderType = ($order['order_type'] ?? '') === 'pickup' ? 'pickup' : 'dine_in';
$tableRaw = trim((string) ($order['table_no'] ?? ''));
$loc = ha_dining_parse_loc($tableRaw);
if ($orderType === 'pickup') {
    $displayLocation = 'Pickup';
} elseif ($loc['number'] > 0) {
    $displayLocation = $loc['area'] === 'table'
        ? ('Table ' . $loc['number'])
        : ha_dining_display_label($loc['area'], $loc['number'], $hotel);
} else {
    $displayLocation = $tableRaw !== '' ? $tableRaw : '—';
}

$createdAt = !empty($order['created_at']) ? strtotime((string) $order['created_at']) : time();
$date = date('d M Y', $createdAt ?: time());
$time = date('h:i A');
$orderNo = (string) ($order['public_id'] ?? $id);
$paymentMode = strtoupper(trim((string) ($order['payment_mode'] ?? '')));
$halfGst = $gstPercent / 2;
$skipAutoPrint = isset($_GET['noprint']) && (string) $_GET['noprint'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bill #<?= htmlspecialchars($orderNo) ?> - <?= htmlspecialchars($displayLocation) ?></title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans', Arial, Helvetica, sans-serif;
      background-color: #f3f4f6;
      margin: 0;
      padding: 20px;
      display: flex;
      justify-content: center;
      font-weight: bold;
      color: #000;
    }
    .bill-container {
      background: white;
      width: 320px;
      padding: 15px;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
      font-size: 14px;
      font-weight: bold;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .bold { font-weight: bold; }
    .header { text-align: center; margin-bottom: 10px; }
    .hotel-name { font-size: 20px; font-weight: 900; margin: 0; word-break: break-word; }
    .hotel-address { font-size: 13px; margin: 2px 0; font-weight: 600; }
    .gst-number { font-weight: bold; color: black; font-size: 14px; margin-top: 5px; }
    .divider { border-bottom: 1px dashed #000; margin: 5px 0; }
    .meta-info {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      margin: 5px 0;
    }
    .items-header {
      display: grid;
      grid-template-columns: 3fr 1fr 1fr 1fr;
      font-weight: bold;
      border-bottom: 1px dashed #000;
      padding-bottom: 5px;
      margin-bottom: 5px;
      font-size: 12px;
    }
    .item-row {
      display: grid;
      grid-template-columns: 3fr 1fr 1fr 1fr;
      margin-bottom: 5px;
      font-size: 12px;
      align-items: start;
    }
    .item-row small { font-weight: 600; color: #333; }
    .totals-section {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      margin-top: 10px;
    }
    .total-row {
      display: flex;
      justify-content: space-between;
      width: 180px;
      margin-bottom: 3px;
      font-size: 12px;
    }
    .payment-mode {
      display: flex;
      justify-content: space-between;
      border-top: 1px dashed #000;
      padding-top: 5px;
      margin-top: 5px;
      font-weight: bold;
      font-size: 12px;
    }
    .footer {
      text-align: center;
      margin-top: 15px;
      font-size: 11px;
      border-top: 1px dashed #000;
      padding-top: 5px;
    }
    .noprint { text-align: center; margin-top: 16px; }
    @media print {
      body { background: white; padding: 0; }
      .bill-container { box-shadow: none; width: 100%; padding: 0; }
      .noprint { display: none; }
    }
  </style>
</head>
<body<?= $skipAutoPrint ? '' : ' onload="window.print()"' ?>>
  <div class="bill-container">
    <div class="header">
      <h1 class="hotel-name"><?= htmlspecialchars((string) ($hotel['name'] ?? 'FoodMitra')) ?></h1>
      <p class="hotel-address"><?= htmlspecialchars((string) ($hotel['address'] ?? $hotel['area'] ?? '')) ?></p>
      <?php if ($gstEnabled && !empty($hotel['gst_number'])): ?>
        <p class="gst-number">GST:- <?= htmlspecialchars((string) $hotel['gst_number']) ?></p>
      <?php endif; ?>
    </div>

    <div class="divider"><span style="background:white; padding: 0 5px; position: relative; top: 8px; font-size: 12px;">RECEIPT</span></div>
    <div style="text-align: center; margin-bottom: 5px;"></div>

    <div class="meta-info">
      <div>Bill No: #<?= htmlspecialchars($orderNo) ?></div>
      <div>Date: <?= htmlspecialchars($date) ?></div>
    </div>
    <div class="meta-info">
      <div><?= $orderType === 'pickup' ? 'Type' : 'Table' ?>: <?= htmlspecialchars($displayLocation) ?></div>
      <div></div>
    </div>

    <?php if (!empty($order['customer_name']) || !empty($order['customer_phone'])): ?>
    <div class="meta-info" style="margin-top: 0; display: block;">
      <?php if (!empty($order['customer_name'])): ?>
        <div style="font-weight: bold;"><?= htmlspecialchars((string) $order['customer_name']) ?></div>
      <?php endif; ?>
      <?php if (!empty($order['customer_phone'])): ?>
        <div><?= htmlspecialchars((string) $order['customer_phone']) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="items-header">
      <div class="text-left">Item</div>
      <div class="text-right">Price</div>
      <div class="text-center">Qty</div>
      <div class="text-right">Total</div>
    </div>

    <?php foreach ($items as $item):
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $price = (float) ($item['price'] ?? 0);
        $line = $price * $qty;
        $name = (string) ($item['name'] ?? '');
        $variant = trim((string) ($item['variant'] ?? ''));
    ?>
    <div class="item-row">
      <div class="text-left">
        <?= htmlspecialchars($name) ?>
        <?php if ($variant !== ''): ?><br><small>(<?= htmlspecialchars($variant) ?>)</small><?php endif; ?>
      </div>
      <div class="text-right"><?= $price > 0 ? ('&#8377;' . number_format($price, 2)) : 'FREE' ?></div>
      <div class="text-center"><?= $qty ?></div>
      <div class="text-right"><?= $line > 0 ? ('&#8377;' . number_format($line, 2)) : 'FREE' ?></div>
    </div>
    <?php endforeach; ?>

    <div class="divider"></div>

    <div class="totals-section">
      <div class="total-row">
        <span>Total Amount:</span>
        <span>&#8377;<?= number_format($totals['subtotal'], 2) ?></span>
      </div>
      <?php if ($totals['discount'] > 0): ?>
      <div class="total-row">
        <span>Discount:</span>
        <span>- &#8377;<?= number_format($totals['discount'], 2) ?></span>
      </div>
      <?php endif; ?>
      <div class="total-row">
        <span>Taxable Amount:</span>
        <span>&#8377;<?= number_format($totals['taxable'], 2) ?></span>
      </div>

      <?php if ($gstEnabled && $gstPercent > 0 && $totals['tax'] > 0): ?>
      <div class="total-row">
        <span>CGST (<?= rtrim(rtrim(number_format($halfGst, 2), '0'), '.') ?>%):</span>
        <span>&#8377;<?= number_format($totals['cgst'], 2) ?></span>
      </div>
      <div class="total-row">
        <span>SGST (<?= rtrim(rtrim(number_format($halfGst, 2), '0'), '.') ?>%):</span>
        <span>&#8377;<?= number_format($totals['sgst'], 2) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($totals['service_charge'] > 0): ?>
      <div class="total-row">
        <span>Service Charge (<?= rtrim(rtrim(number_format($servicePercent, 2), '0'), '.') ?>%):</span>
        <span>&#8377;<?= number_format($totals['service_charge'], 2) ?></span>
      </div>
      <?php endif; ?>

      <div style="width: 100%; border-bottom: 1px dashed #000; margin: 5px 0;"></div>

      <div class="total-row bold" style="font-size: 14px;">
        <span>Total:</span>
        <span>&#8377;<?= number_format($totals['total'], 0) ?></span>
      </div>
    </div>

    <?php if ($paymentMode !== ''): ?>
    <div class="payment-mode">
      <span>Payment:</span>
      <span><?= htmlspecialchars($paymentMode) ?></span>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="footer">
      <p>**SAVE PAPER SAVE NATURE !!**</p>
      <p>Time: <?= htmlspecialchars($time) ?></p>
      <p>FoodMitra</p>
    </div>

    <div class="divider"></div>
  </div>

  <div class="noprint">
    <button onclick="window.print()">Print</button>
    <a href="pos-billing.php?id=<?= (int) $id ?>">Back</a>
  </div>
</body>
</html>
