<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();
$rows = admin_db()->query('SELECT * FROM hotels ORDER BY sort_order, id')->fetchAll();
sa_layout_start('Hotels', 'hotels.php');
?>
<div class="card" style="display:flex;justify-content:space-between;align-items:center">
  <div class="muted"><?= count($rows) ?> hotels</div>
  <a class="btn" href="hotels-add.php">Add hotel</a>
</div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Area</th><th>Lat/Lng</th><th>Active</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= sa_h($r['public_id']) ?></td>
          <td><?= sa_h($r['name']) ?></td>
          <td><?= sa_h($r['area']) ?></td>
          <td class="muted"><?= sa_h((string)$r['latitude']) ?>, <?= sa_h((string)$r['longitude']) ?></td>
          <td><?= !empty($r['is_active']) ? 'Yes' : 'No' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php sa_layout_end(); ?>
