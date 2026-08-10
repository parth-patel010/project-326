<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$hotel = ha_hotel();
$publicId = (string) ($hotel['public_id'] ?? '');

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid)
     AND status IN ('placed','preparing','ready','out_for_delivery','awaiting_payment')"
);
$stmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
$onlineOpen = (int) $stmt->fetchColumn();

$ps = $pdo->prepare(
    "SELECT COUNT(*) FROM pos_orders WHERE hotel_id = :hid AND status IN ('open','preparing','ready')"
);
$ps->execute([':hid' => $hotelId]);
$posOpen = (int) $ps->fetchColumn();

$todayOnline = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND DATE(created_at)=CURDATE()"
);
$todayOnline->execute([':hid' => $hotelId, ':pid' => $publicId]);
$todayOnline = (int) $todayOnline->fetchColumn();

ha_layout_start('Dashboard', 'dashboard.php');
?>
<div class="grid">
  <div class="stat"><div class="n"><?= $onlineOpen ?></div><div class="l">Online Orders (open)</div></div>
  <div class="stat"><div class="n"><?= $posOpen ?></div><div class="l">POS Orders (open)</div></div>
  <div class="stat"><div class="n"><?= $todayOnline ?></div><div class="l">Online today</div></div>
</div>
<?php ha_layout_end(); ?>
