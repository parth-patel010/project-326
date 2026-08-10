<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$user = sa_user();
$adminName = (string) ($user['name'] ?? 'Admin');

$onlineOrders = 0;
$todayOnline = 0;
$delivered = 0;
$cancelled = 0;
$partnersOnline = 0;
$partnersTotal = 0;
$codHold = 0.0;
$hotels = 0;
$unassigned = 0;
$warn = '';

$count = static function (PDO $pdo, string $sql): int {
    return (int) $pdo->query($sql)->fetchColumn();
};

try {
    $onlineOrders = $count(
        $pdo,
        "SELECT COUNT(*) FROM orders WHERE status NOT IN ('cancelled','payment_failed','delivered') AND payment_mode IN ('cod','prepaid')"
    );
    $todayOnline = $count($pdo, 'SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()');
    $delivered = $count($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'delivered'");
    $cancelled = $count($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'cancelled'");
} catch (Throwable $e) {
    $warn = 'Orders stats failed: ' . $e->getMessage();
    error_log('sa dashboard orders: ' . $e->getMessage());
}

try {
    $partnersOnline = $count($pdo, "SELECT COUNT(*) FROM delivery_partners WHERE is_online = 1 AND status = 'active'");
    $partnersTotal = $count($pdo, "SELECT COUNT(*) FROM delivery_partners WHERE status = 'active'");
} catch (Throwable $e) {
    error_log('sa dashboard partners: ' . $e->getMessage());
}

try {
    $codHold = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM cod_holds WHERE status = 'held'")->fetchColumn();
} catch (Throwable $e) {
    error_log('sa dashboard cod: ' . $e->getMessage());
}

try {
    $hotels = $count($pdo, 'SELECT COUNT(*) FROM hotels WHERE is_active = 1');
} catch (Throwable $e) {
    error_log('sa dashboard hotels: ' . $e->getMessage());
}

try {
    $unassigned = $count(
        $pdo,
        "SELECT COUNT(*) FROM orders WHERE assigned_partner_id IS NULL AND status NOT IN ('cancelled','payment_failed','delivered','awaiting_payment')"
    );
} catch (Throwable $e) {
    // Column missing until migrate_orders_dispatch.php is run
    $warn = ($warn !== '' ? $warn . ' · ' : '')
        . 'Run: php /var/www/foodmitra/api/bin/migrate_orders_dispatch.php';
    error_log('sa dashboard unassigned: ' . $e->getMessage());
}

sa_layout_start('Dashboard', 'dashboard.php', 'Welcome, ' . $adminName . '. Manage platform orders and partners.');
if ($warn !== ''): ?>
  <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><?= sa_h($warn) ?></div>
<?php endif; ?>

<div class="mb-8">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-xl font-bold text-gray-900">Order statistics</h3>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <div class="bg-green-50 rounded-xl border border-green-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-green-600 text-4xl opacity-80">check_circle</span>
      </div>
      <p class="text-3xl font-bold text-green-600"><?= $delivered ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Delivered orders</p>
    </div>
    <div class="bg-orange-50 rounded-xl border border-orange-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-orange-600 text-4xl opacity-80">cancel</span>
      </div>
      <p class="text-3xl font-bold text-orange-600"><?= $cancelled ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Cancelled orders</p>
    </div>
    <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-emerald-700 text-4xl opacity-80">today</span>
      </div>
      <p class="text-3xl font-bold text-emerald-700"><?= $todayOnline ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Orders today</p>
    </div>
    <div class="bg-indigo-50 rounded-xl border border-indigo-100 p-6 shadow-sm h-[140px] flex flex-col">
      <div class="flex justify-end mb-1">
        <span class="material-icons-outlined text-indigo-600 text-4xl opacity-80">smartphone</span>
      </div>
      <p class="text-3xl font-bold text-indigo-600"><?= $onlineOrders ?></p>
      <p class="text-sm font-medium text-gray-800 mt-1">Active online orders</p>
    </div>

    <div class="bg-blue-50 rounded-xl border border-blue-100 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4">
      <span class="material-icons-outlined text-blue-600 text-3xl shrink-0">assignment_late</span>
      <p class="text-sm font-medium text-gray-800 flex-1">Unassigned orders</p>
      <p class="text-xl font-bold text-blue-600"><?= $unassigned ?></p>
    </div>
    <div class="bg-purple-50 rounded-xl border border-purple-100 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4">
      <span class="material-icons-outlined text-purple-600 text-3xl shrink-0">two_wheeler</span>
      <p class="text-sm font-medium text-gray-800 flex-1">Partners online</p>
      <p class="text-xl font-bold text-purple-600"><?= $partnersOnline ?>/<?= $partnersTotal ?></p>
    </div>
    <div class="bg-teal-50 rounded-xl border border-teal-100 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4">
      <span class="material-icons-outlined text-teal-700 text-3xl shrink-0">restaurant</span>
      <p class="text-sm font-medium text-gray-800 flex-1">Active hotels</p>
      <p class="text-xl font-bold text-teal-700"><?= $hotels ?></p>
    </div>
    <div class="bg-amber-50 rounded-xl border border-amber-100 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4">
      <span class="material-icons-outlined text-amber-700 text-3xl shrink-0">savings</span>
      <p class="text-sm font-medium text-gray-800 flex-1">COD hold total</p>
      <p class="text-xl font-bold text-amber-700">₹<?= number_format($codHold, 0) ?></p>
    </div>

    <a href="online-orders.php" class="bg-primary/5 rounded-xl border border-primary/20 p-4 shadow-sm h-[100px] flex items-center justify-between gap-4 hover:border-primary/40 hover:shadow transition-shadow col-span-1 sm:col-span-2 lg:col-span-4">
      <span class="material-icons-outlined text-primary text-3xl shrink-0">list_alt</span>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Online Orders</p>
        <p class="text-xs text-gray-500 mt-0.5">View and filter live app orders</p>
      </div>
      <p class="text-xl font-bold text-primary shrink-0"><?= $onlineOrders ?></p>
      <span class="material-icons-outlined text-primary/60 shrink-0">chevron_right</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
  <a href="hotels.php" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">restaurant</span></div>
    <div><p class="font-semibold text-gray-900">Hotels</p><p class="text-xs text-gray-500">Manage restaurants</p></div>
  </a>
  <a href="delivery-partners.php" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">two_wheeler</span></div>
    <div><p class="font-semibold text-gray-900">Delivery partners</p><p class="text-xs text-gray-500">Drivers & wallets</p></div>
  </a>
  <a href="settings.php" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-primary/30 hover:shadow transition-all flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-primary-soft flex items-center justify-center"><span class="material-icons-outlined text-primary">settings</span></div>
    <div><p class="font-semibold text-gray-900">Settings</p><p class="text-xs text-gray-500">Commission & radius</p></div>
  </a>
</div>

<?php sa_layout_end(); ?>
