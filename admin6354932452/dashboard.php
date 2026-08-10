<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$onlineOrders = (int) $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE status NOT IN ('cancelled','payment_failed','delivered') AND payment_mode IN ('cod','prepaid')"
)->fetchColumn();
$todayOnline = (int) $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();
$partnersOnline = (int) $pdo->query(
    "SELECT COUNT(*) FROM delivery_partners WHERE is_online = 1 AND status = 'active'"
)->fetchColumn();
$codHold = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM cod_holds WHERE status = 'held'"
)->fetchColumn();
$hotels = (int) $pdo->query('SELECT COUNT(*) FROM hotels WHERE is_active = 1')->fetchColumn();

sa_layout_start('Dashboard', 'dashboard.php');
?>
<div class="grid">
  <div class="stat"><div class="n"><?= $onlineOrders ?></div><div class="l">Active online orders</div></div>
  <div class="stat"><div class="n"><?= $todayOnline ?></div><div class="l">Orders today</div></div>
  <div class="stat"><div class="n"><?= $partnersOnline ?></div><div class="l">Partners online</div></div>
  <div class="stat"><div class="n"><?= $hotels ?></div><div class="l">Active hotels</div></div>
  <div class="stat"><div class="n">₹<?= number_format($codHold, 2) ?></div><div class="l">COD hold total</div></div>
</div>
<?php sa_layout_end(); ?>
