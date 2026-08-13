<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$hotel = ha_hotel() ?? [];
$publicId = (string) ($hotel['public_id'] ?? '');
$hotelName = (string) ($hotel['name'] ?? 'Hotel');

$onlineOpen = 0;
$posOpen = 0;
$todayOnline = 0;
$deliveredToday = 0;
$preparing = 0;
$ready = 0;
$dbError = '';

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid)
         AND status IN ('placed','preparing','ready','out_for_delivery','awaiting_payment')"
    );
    $stmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
    $onlineOpen = (int) $stmt->fetchColumn();

    $todayStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND DATE(created_at)=CURDATE()"
    );
    $todayStmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
    $todayOnline = (int) $todayStmt->fetchColumn();

    $delStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND status='delivered' AND DATE(created_at)=CURDATE()"
    );
    $delStmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
    $deliveredToday = (int) $delStmt->fetchColumn();

    $prepStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND status='preparing'"
    );
    $prepStmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
    $preparing = (int) $prepStmt->fetchColumn();

    $readyStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND status='ready'"
    );
    $readyStmt->execute([':hid' => $hotelId, ':pid' => $publicId]);
    $ready = (int) $readyStmt->fetchColumn();
} catch (Throwable $e) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :pid
             AND status IN ('placed','preparing','ready','out_for_delivery','awaiting_payment')"
        );
        $stmt->execute([':pid' => $publicId]);
        $onlineOpen = (int) $stmt->fetchColumn();

        $todayStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :pid AND DATE(created_at)=CURDATE()"
        );
        $todayStmt->execute([':pid' => $publicId]);
        $todayOnline = (int) $todayStmt->fetchColumn();

        $delStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :pid AND status='delivered' AND DATE(created_at)=CURDATE()"
        );
        $delStmt->execute([':pid' => $publicId]);
        $deliveredToday = (int) $delStmt->fetchColumn();

        $prepStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :pid AND status='preparing'"
        );
        $prepStmt->execute([':pid' => $publicId]);
        $preparing = (int) $prepStmt->fetchColumn();

        $readyStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :pid AND status='ready'"
        );
        $readyStmt->execute([':pid' => $publicId]);
        $ready = (int) $readyStmt->fetchColumn();
    } catch (Throwable $e2) {
        $dbError = 'Orders query failed: ' . $e2->getMessage();
        error_log('hotel dashboard orders: ' . $e2->getMessage());
    }
}

try {
    $ps = $pdo->prepare(
        "SELECT COUNT(*) FROM pos_orders WHERE hotel_id = :hid AND status IN ('open','preparing','ready')"
    );
    $ps->execute([':hid' => $hotelId]);
    $posOpen = (int) $ps->fetchColumn();
} catch (Throwable $e) {
    error_log('hotel dashboard pos: ' . $e->getMessage());
}

ha_layout_start('Dashboard', 'dashboard.php', 'Welcome back — ' . $hotelName);
if ($dbError !== ''): ?>
  <div class="flash-error"><?= ha_h($dbError) ?></div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h2>Order overview</h2>
    <p class="sub">Live snapshot of app and counter activity</p>
  </div>
  <a href="online-orders.php" class="btn"><span class="material-symbols-outlined text-[18px]">shopping_bag</span> Online orders</a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">smartphone</span></div>
    <p class="n"><?= $onlineOpen ?></p>
    <p class="l">Online orders open</p>
  </div>
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">receipt_long</span></div>
    <p class="n"><?= $posOpen ?></p>
    <p class="l">POS orders open</p>
  </div>
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">today</span></div>
    <p class="n"><?= $todayOnline ?></p>
    <p class="l">Online today</p>
  </div>
  <div class="stat">
    <div class="stat-icon"><span class="material-symbols-outlined text-[22px]">check_circle</span></div>
    <p class="n"><?= $deliveredToday ?></p>
    <p class="l">Delivered today</p>
  </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-symbols-outlined text-primary">restaurant</span></div>
      <p class="text-sm font-medium text-text-main">Preparing</p>
    </div>
    <p class="text-xl font-bold text-primary"><?= $preparing ?></p>
  </div>
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-symbols-outlined text-primary">takeout_dining</span></div>
      <p class="text-sm font-medium text-text-main">Ready for pickup</p>
    </div>
    <p class="text-xl font-bold text-primary"><?= $ready ?></p>
  </div>
  <a href="online-orders.php" class="bg-white rounded-xl border border-primary/20 shadow-sm p-4 flex items-center justify-between gap-4 hover:border-primary/40 hover:shadow transition-shadow">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-10 h-10 rounded-xl bg-primary-soft flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-primary">list_alt</span></div>
      <div class="min-w-0">
        <p class="text-sm font-semibold text-text-main">Manage online orders</p>
        <p class="text-xs text-text-muted">Accept, mark ready, show OTP</p>
      </div>
    </div>
    <span class="material-symbols-outlined text-primary/50">chevron_right</span>
  </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
  <a href="pos-orders.php" class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-symbols-outlined text-primary">point_of_sale</span></div>
    <div><p class="font-semibold text-text-main">POS orders</p><p class="text-xs text-text-muted">Walk-in / counter bills</p></div>
  </a>
  <a href="offers.php" class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-symbols-outlined text-primary">local_offer</span></div>
    <div><p class="font-semibold text-text-main">Offers</p><p class="text-xs text-text-muted">Promo banners</p></div>
  </a>
  <a href="discount-settings.php" class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-symbols-outlined text-primary">percent</span></div>
    <div><p class="font-semibold text-text-main">Discounts</p><p class="text-xs text-text-muted">Cart discounts</p></div>
  </a>
</div>

<?php ha_layout_end(); ?>
