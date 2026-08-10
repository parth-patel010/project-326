<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();
$pdo = admin_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = (int) ($_POST['target_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($target > 0 && $amount > 0) {
        $pdo->prepare(
            'INSERT INTO payouts (payout_type, target_id, amount, status, note) VALUES (\'partner\', :t, :a, \'pending\', :n)'
        )->execute([':t' => $target, ':a' => $amount, ':n' => $note]);
        $flash = 'Partner payout created';
    }
}
if (isset($_GET['mark']) && ctype_digit((string)$_GET['mark'])) {
    $pdo->prepare('UPDATE payouts SET status=\'paid\', paid_at=NOW() WHERE id=:id AND payout_type=\'partner\'')
        ->execute([':id' => (int)$_GET['mark']]);
    $flash = 'Marked paid';
}

$rows = $pdo->query(
    "SELECT p.*, d.full_name FROM payouts p
     LEFT JOIN delivery_partners d ON d.id = p.target_id
     WHERE p.payout_type = 'partner' ORDER BY p.id DESC LIMIT 100"
)->fetchAll();
$partners = $pdo->query('SELECT id, full_name, phone FROM delivery_partners ORDER BY full_name')->fetchAll();

sa_layout_start('Partner Payouts', 'partner-payouts.php', 'Settle delivery partner earnings');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<form method="post" class="card max-w-xl">
  <h3>New payout</h3>
  <label>Partner</label>
  <select name="target_id" class="input">
    <?php foreach ($partners as $p): ?>
      <option value="<?= (int)$p['id'] ?>"><?= sa_h($p['full_name'] . ' · ' . $p['phone']) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Amount ₹</label>
  <input class="input" type="number" step="0.01" name="amount" required>
  <label>Note</label>
  <input class="input" name="note">
  <button class="btn" type="submit">Create payout</button>
</form>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>ID</th><th>Partner</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td class="font-medium"><?= sa_h($r['full_name'] ?? (string)$r['target_id']) ?></td>
            <td class="font-semibold">₹<?= number_format((float)$r['amount'], 2) ?></td>
            <td>
              <?php if ($r['status'] === 'paid'): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">paid</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><?= sa_h($r['status']) ?></span>
              <?php endif; ?>
            </td>
            <td><?php if ($r['status']==='pending'): ?><a class="btn secondary !py-1.5 !px-3 text-xs" href="?mark=<?= (int)$r['id'] ?>">Mark paid</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-gray-500 py-10">No payouts yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php sa_layout_end(); ?>
