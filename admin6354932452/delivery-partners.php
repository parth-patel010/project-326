<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();
$rows = admin_db()->query('SELECT * FROM delivery_partners ORDER BY id DESC')->fetchAll();
sa_layout_start('Delivery Partners', 'delivery-partners.php');
?>
<div class="card" style="display:flex;justify-content:space-between;align-items:center">
  <div class="muted"><?= count($rows) ?> partners</div>
  <a class="btn" href="delivery-partner-add.php">Add partner</a>
</div>
<div class="card">
  <table>
    <thead>
      <tr><th>Name</th><th>Phone</th><th>Radius</th><th>Online</th><th>Verified</th><th>Insurance</th><th>COD wallet</th><th>Completed</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= sa_h($r['full_name']) ?></td>
          <td><?= sa_h($r['phone']) ?></td>
          <td><?= sa_h((string)$r['service_radius_km']) ?> km</td>
          <td><?= !empty($r['is_online']) ? 'Yes' : 'No' ?></td>
          <td><?= !empty($r['is_verified']) ? 'Yes' : 'No' ?></td>
          <td><?= !empty($r['has_insurance']) ? 'Yes' : 'No' ?></td>
          <td>₹<?= number_format((float)$r['cod_wallet'], 2) ?></td>
          <td><?= (int)$r['orders_completed'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php sa_layout_end(); ?>
