<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$hotel = ha_hotel();
$publicId = (string) ($hotel['public_id'] ?? '');
$hotelName = (string) ($hotel['name'] ?? 'Hotel');

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

$deliveredToday = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND status='delivered' AND DATE(created_at)=CURDATE()"
);
$deliveredToday->execute([':hid' => $hotelId, ':pid' => $publicId]);
$deliveredToday = (int) $deliveredToday->fetchColumn();

$preparing = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND status='preparing'"
);
$preparing->execute([':hid' => $hotelId, ':pid' => $publicId]);
$preparing = (int) $preparing->fetchColumn();

$ready = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE (hotel_db_id = :hid OR restaurant_id = :pid) AND status='ready'"
);
$ready->execute([':hid' => $hotelId, ':pid' => $publicId]);
$ready = (int) $ready->fetchColumn();

ha_layout_start('Dashboard', 'dashboard.php', 'Welcome back — ' . $hotelName);
?>

<div class="mb-8">
  <h3 class="text-xl font-bold text-gray-900 mb-4">Order overview</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <div class="bg-indigo-50 rounded-xl border border-indigo-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-indigo-600 text-4xl opacity-80">smartphone</span>
      </div>
      <p class="text-3xl font-bold text-indigo-600"><?= $onlineOpen ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Online orders open</p>
    </div>
    <div class="bg-amber-50 rounded-xl border border-amber-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-amber-700 text-4xl opacity-80">point_of_sale</span>
      </div>
      <p class="text-3xl font-bold text-amber-700"><?= $posOpen ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">POS orders open</p>
    </div>
    <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-emerald-700 text-4xl opacity-80">today</span>
      </div>
      <p class="text-3xl font-bold text-emerald-700"><?= $todayOnline ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Online today</p>
    </div>
    <div class="bg-green-50 rounded-xl border border-green-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-green-600 text-4xl opacity-80">check_circle</span>
      </div>
      <p class="text-3xl font-bold text-green-600"><?= $deliveredToday ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Delivered today</p>
    </div>

    <div class="bg-orange-50 rounded-xl border border-orange-100 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4">
      <span class="material-icons-outlined text-orange-600 text-3xl shrink-0">skillet</span>
      <p class="text-sm font-medium text-gray-800 flex-1">Preparing</p>
      <p class="text-xl font-bold text-orange-600"><?= $preparing ?></p>
    </div>
    <div class="bg-teal-50 rounded-xl border border-teal-100 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4">
      <span class="material-icons-outlined text-teal-700 text-3xl shrink-0">takeout_dining</span>
      <p class="text-sm font-medium text-gray-800 flex-1">Ready for pickup</p>
      <p class="text-xl font-bold text-teal-700"><?= $ready ?></p>
    </div>
    <a href="online-orders.php" class="bg-primary/5 rounded-xl border border-primary/20 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4 hover:border-primary/40 hover:shadow transition-shadow sm:col-span-2">
      <span class="material-icons-outlined text-primary text-3xl shrink-0">list_alt</span>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Manage online orders</p>
        <p class="text-xs text-gray-500 mt-0.5">Accept, mark ready, show pickup OTP</p>
      </div>
      <span class="material-icons-outlined text-primary/60 shrink-0">chevron_right</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
  <a href="pos-orders.php" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">point_of_sale</span></div>
    <div><p class="font-semibold text-gray-900">POS orders</p><p class="text-xs text-gray-500">Walk-in / counter bills</p></div>
  </a>
  <a href="offers.php" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">local_offer</span></div>
    <div><p class="font-semibold text-gray-900">Offers</p><p class="text-xs text-gray-500">Promo banners</p></div>
  </a>
  <a href="discount-settings.php" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">percent</span></div>
    <div><p class="font-semibold text-gray-900">Discounts</p><p class="text-xs text-gray-500">Cart discounts</p></div>
  </a>
</div>

<?php ha_layout_end(); ?>
