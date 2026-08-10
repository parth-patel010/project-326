<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();
$rows = admin_db()->query('SELECT * FROM delivery_partners ORDER BY id DESC')->fetchAll();
sa_layout_start('Delivery Partners', 'delivery-partners.php', 'Riders on the FoodMitra network');
?>
<div class="card flex flex-wrap items-center justify-between gap-3 !py-4">
  <div class="muted"><?= count($rows) ?> partners</div>
  <a class="btn" href="delivery-partner-add.php">
    <span class="material-icons-outlined text-[18px]">person_add</span> Add partner
  </a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr><th>Name</th><th>Phone</th><th>Radius</th><th>Online</th><th>Verified</th><th>Insurance</th><th>COD wallet</th><th>Completed</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="font-semibold text-gray-900"><?= sa_h($r['full_name']) ?></td>
            <td><?= sa_h($r['phone']) ?></td>
            <td><?= sa_h((string)$r['service_radius_km']) ?> km</td>
            <td>
              <?php if (!empty($r['is_online'])): ?>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700"><span class="w-2 h-2 rounded-full bg-green-500"></span> Online</span>
              <?php else: ?>
                <span class="text-xs text-gray-500">Offline</span>
              <?php endif; ?>
            </td>
            <td><?= !empty($r['is_verified']) ? 'Yes' : 'No' ?></td>
            <td><?= !empty($r['has_insurance']) ? 'Yes' : 'No' ?></td>
            <td class="font-semibold">₹<?= number_format((float)$r['cod_wallet'], 2) ?></td>
            <td><?= (int)$r['orders_completed'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-gray-500 py-10">No partners yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
