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

sa_layout_start('COD Holds', 'cod-holds.php', 'Cash collected by partners awaiting settlement');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>

<div class="bg-amber-50 rounded-xl border border-amber-100 p-5 shadow-sm mb-5 max-w-sm">
  <p class="text-sm font-medium text-gray-700">Currently held</p>
  <p class="text-3xl font-bold text-amber-700 mt-1">₹<?= number_format($total, 2) ?></p>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>ID</th><th>Partner</th><th>Order</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <p class="font-medium"><?= sa_h($r['full_name'] ?? '') ?></p>
              <p class="muted"><?= sa_h($r['phone'] ?? '') ?></p>
            </td>
            <td class="font-mono text-xs"><?= sa_h($r['order_public_id'] ?? (string)$r['order_id']) ?></td>
            <td class="font-semibold">₹<?= number_format((float)$r['amount'], 2) ?></td>
            <td>
              <?php if ($r['status'] === 'held'): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">held</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><?= sa_h($r['status']) ?></span>
              <?php endif; ?>
            </td>
            <td><?php if ($r['status']==='held'): ?><a class="btn secondary !py-1.5 !px-3 text-xs" href="?release=<?= (int)$r['id'] ?>">Release</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-gray-500 py-10">No COD holds</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
