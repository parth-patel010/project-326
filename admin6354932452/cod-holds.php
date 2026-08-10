<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();
$pdo = admin_db();
$flash = '';

if (isset($_GET['release']) && ctype_digit((string)$_GET['release'])) {
    $id = (int) $_GET['release'];
    $row = $pdo->prepare('SELECT * FROM cod_holds WHERE id = :id');
    $row->execute([':id' => $id]);
    $hold = $row->fetch();
    if ($hold && $hold['status'] === 'held') {
        $pdo->prepare('UPDATE cod_holds SET status=\'released\', released_at=NOW() WHERE id=:id')->execute([':id' => $id]);
        $pdo->prepare('UPDATE delivery_partners SET cod_wallet = GREATEST(0, cod_wallet - :a) WHERE id=:pid')
            ->execute([':a' => $hold['amount'], ':pid' => $hold['partner_id']]);
        $flash = 'COD hold released';
    }
}

$rows = $pdo->query(
    "SELECT c.*, d.full_name, d.phone, o.public_id AS order_public_id
     FROM cod_holds c
     LEFT JOIN delivery_partners d ON d.id = c.partner_id
     LEFT JOIN orders o ON o.id = c.order_id
     ORDER BY c.id DESC LIMIT 200"
)->fetchAll();
$total = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM cod_holds WHERE status='held'")->fetchColumn();

sa_layout_start('COD Holds', 'cod-holds.php');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<div class="stat" style="margin-bottom:16px"><div class="n">₹<?= number_format($total, 2) ?></div><div class="l">Currently held</div></div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Partner</th><th>Order</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= sa_h($r['full_name'] ?? '') ?> <span class="muted"><?= sa_h($r['phone'] ?? '') ?></span></td>
          <td><?= sa_h($r['order_public_id'] ?? (string)$r['order_id']) ?></td>
          <td>₹<?= number_format((float)$r['amount'], 2) ?></td>
          <td><?= sa_h($r['status']) ?></td>
          <td><?php if ($r['status']==='held'): ?><a class="btn secondary" href="?release=<?= (int)$r['id'] ?>">Release</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php sa_layout_end(); ?>
