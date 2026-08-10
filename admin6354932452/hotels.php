<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();
$rows = admin_db()->query('SELECT * FROM hotels ORDER BY sort_order, id')->fetchAll();
sa_layout_start('Hotels', 'hotels.php', 'Restaurant partners on FoodMitra');
?>
<div class="card flex flex-wrap items-center justify-between gap-3 !py-4">
  <div class="muted"><?= count($rows) ?> hotels</div>
  <a class="btn" href="hotels-add.php">
    <span class="material-icons-outlined text-[18px]">add</span> Add hotel
  </a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Area</th><th>Lat / Lng</th><th>Active</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="font-mono text-xs text-gray-500"><?= sa_h($r['public_id']) ?></td>
            <td class="font-semibold text-gray-900"><?= sa_h($r['name']) ?></td>
            <td><?= sa_h($r['area']) ?></td>
            <td class="muted"><?= sa_h((string)$r['latitude']) ?>, <?= sa_h((string)$r['longitude']) ?></td>
            <td>
              <?php if (!empty($r['is_active'])): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Off</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="text-center text-gray-500 py-10">No hotels yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
