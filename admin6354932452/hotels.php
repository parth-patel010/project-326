<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/hotels.php';
sa_require_login();
$pdo = admin_db();
$rows = $pdo->query('SELECT * FROM hotels ORDER BY sort_order, id')->fetchAll();
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
      <thead><tr><th>ID</th><th>Name</th><th>Area</th><th>Prep (auto)</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $autoPrep = fm_hotel_prep_mins($pdo, (int) $r['id']); ?>
          <tr>
            <td class="font-mono text-xs text-gray-500"><?= sa_h($r['public_id']) ?></td>
            <td class="font-semibold text-gray-900"><?= sa_h($r['name']) ?></td>
            <td><?= sa_h($r['area']) ?></td>
            <td class="muted"><?= (int) $autoPrep ?> min</td>
            <td>
              <?php if (!empty($r['is_active'])): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Off</span>
              <?php endif; ?>
              <?php if (isset($r['is_open']) && empty($r['is_open'])): ?>
                <span class="inline-flex ml-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Closed</span>
              <?php endif; ?>
            </td>
            <td><a class="btn secondary !py-1.5 !px-3 text-xs" href="hotels-edit.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-gray-500 py-10">No hotels yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
