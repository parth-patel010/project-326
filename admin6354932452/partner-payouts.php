<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/PartnerEarning.php';
sa_require_login();
$pdo = admin_db();
PartnerEarning::ensureEarnWalletColumn($pdo);
$flash = '';
$error = '';

function fm_payouts_has(PDO $pdo, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'payouts\' AND COLUMN_NAME = :c'
    );
    $stmt->execute([':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
}

$hasKind = fm_payouts_has($pdo, 'kind');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = (int) ($_POST['target_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));
    $kind = (string) ($_POST['kind'] ?? 'delivery_fee');
    $status = (string) ($_POST['status'] ?? 'pending');
    if (!in_array($kind, ['delivery_fee', 'bonus', 'adjustment', 'withdrawal'], true)) {
        $kind = 'delivery_fee';
    }
    if (!in_array($status, ['pending', 'paid'], true)) {
        $status = 'pending';
    }
    if ($target > 0 && $amount > 0) {
        try {
            $pdo->beginTransaction();
            if ($status === 'paid') {
                PartnerEarning::debitEarnWallet($pdo, $target, $amount, $kind === 'delivery_fee');
            }
            if ($hasKind) {
                $pdo->prepare(
                    'INSERT INTO payouts (payout_type, target_id, amount, status, note, kind)
                     VALUES (\'partner\', :t, :a, :s, :n, :k)'
                )->execute([':t' => $target, ':a' => $amount, ':s' => $status, ':n' => $note, ':k' => $kind]);
            } else {
                $pdo->prepare(
                    'INSERT INTO payouts (payout_type, target_id, amount, status, note)
                     VALUES (\'partner\', :t, :a, :s, :n)'
                )->execute([':t' => $target, ':a' => $amount, ':s' => $status, ':n' => $note]);
            }
            if ($status === 'paid') {
                $pdo->prepare('UPDATE payouts SET paid_at = NOW() WHERE id = LAST_INSERT_ID()')->execute();
            }
            $pdo->commit();
            $flash = $status === 'paid' ? 'Payout recorded and earn wallet debited' : 'Partner payout created';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['mark']) && ctype_digit((string) $_GET['mark'])) {
    $id = (int) $_GET['mark'];
    $rowStmt = $pdo->prepare("SELECT * FROM payouts WHERE id = :id AND payout_type = 'partner' LIMIT 1");
    $rowStmt->execute([':id' => $id]);
    $row = $rowStmt->fetch();
    if ($row && $row['status'] === 'pending') {
        try {
            $pdo->beginTransaction();
            $kind = (string) ($row['kind'] ?? 'delivery_fee');
            PartnerEarning::debitEarnWallet($pdo, (int) $row['target_id'], (float) $row['amount'], $kind === 'delivery_fee' || $kind === '');
            $pdo->prepare("UPDATE payouts SET status = 'paid', paid_at = NOW() WHERE id = :id")
                ->execute([':id' => $id]);
            $pdo->commit();
            $flash = 'Marked paid — earn wallet debited';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$rows = $pdo->query(
    "SELECT p.*, d.full_name, d.phone, COALESCE(d.earn_wallet, 0) AS earn_wallet, COALESCE(d.cod_wallet, 0) AS partner_cod
     FROM payouts p
     LEFT JOIN delivery_partners d ON d.id = p.target_id
     WHERE p.payout_type = 'partner' ORDER BY p.id DESC LIMIT 100"
)->fetchAll();
$partners = $pdo->query(
    'SELECT id, full_name, phone, COALESCE(earn_wallet, 0) AS earn_wallet, COALESCE(cod_wallet, 0) AS cod_wallet
     FROM delivery_partners ORDER BY full_name'
)->fetchAll();

$sumPaid = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE payout_type='partner' AND status='paid'")->fetchColumn();
$sumPending = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE payout_type='partner' AND status='pending'")->fetchColumn();

sa_layout_start('Partner Payouts', 'partner-payouts.php', 'Settle km × rate earnings from the earn wallet');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error"><?= sa_h($error) ?></div><?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5 max-w-2xl">
  <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
    <p class="text-xs font-semibold uppercase text-emerald-700">Paid out</p>
    <p class="text-2xl font-bold text-emerald-900 mt-1">₹<?= number_format($sumPaid, 2) ?></p>
  </div>
  <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
    <p class="text-xs font-semibold uppercase text-amber-700">Pending payouts</p>
    <p class="text-2xl font-bold text-amber-900 mt-1">₹<?= number_format($sumPending, 2) ?></p>
  </div>
</div>

<form method="post" class="card max-w-xl" id="payoutForm">
  <h3>New payout</h3>
  <label>Partner</label>
  <select name="target_id" id="target_id" class="input" required onchange="loadPartnerWallets(this.value)">
    <option value="">Select delivery partner</option>
    <?php foreach ($partners as $p): ?>
      <option value="<?= (int)$p['id'] ?>"><?= sa_h($p['full_name'] . ' · ' . $p['phone']) ?></option>
    <?php endforeach; ?>
  </select>
  <div id="partnerWalletSummary" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
      <p class="text-xs font-semibold uppercase text-emerald-700">Earn wallet</p>
      <p id="partnerEarnWallet" class="text-xl font-bold text-emerald-900 mt-1">₹0.00</p>
      <p class="text-xs text-emerald-800 mt-1">Debited when payout is marked paid</p>
    </div>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
      <p class="text-xs font-semibold uppercase text-amber-700">COD wallet</p>
      <p id="partnerCodWallet" class="text-xl font-bold text-amber-900 mt-1">₹0.00</p>
      <p class="text-xs text-amber-800 mt-1">Cash collected on COD</p>
    </div>
  </div>
  <label>Payout type</label>
  <select name="kind" class="input">
    <option value="delivery_fee">Delivery fee (km × rate)</option>
    <option value="bonus">Bonus</option>
    <option value="adjustment">Adjustment</option>
    <option value="withdrawal">Withdrawal</option>
  </select>
  <label>Amount ₹</label>
  <input class="input" type="number" step="0.01" min="0.01" name="amount" id="amount" required>
  <label>Note</label>
  <input class="input" name="note">
  <label>Status</label>
  <select name="status" class="input">
    <option value="pending">Pending</option>
    <option value="paid">Paid (debit earn wallet now)</option>
  </select>
  <button class="btn" type="submit">Create payout</button>
</form>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>ID</th><th>Partner</th><th>Type</th><th>Amount</th><th>Status</th><th>Note</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <p class="font-medium"><?= sa_h($r['full_name'] ?? (string)$r['target_id']) ?></p>
              <p class="muted text-xs"><?= sa_h($r['phone'] ?? '') ?></p>
            </td>
            <td class="text-sm"><?= sa_h((string)($r['kind'] ?? 'delivery_fee')) ?></td>
            <td class="font-semibold">₹<?= number_format((float)$r['amount'], 2) ?></td>
            <td>
              <?php if ($r['status'] === 'paid'): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">paid</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><?= sa_h($r['status']) ?></span>
              <?php endif; ?>
            </td>
            <td class="muted text-sm"><?= sa_h((string)$r['note']) ?></td>
            <td><?php if ($r['status']==='pending'): ?><a class="btn secondary !py-1.5 !px-3 text-xs" href="?mark=<?= (int)$r['id'] ?>">Mark paid</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-gray-500 py-10">No payouts yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
function formatMoney(n) {
  return '₹' + (Number(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function loadPartnerWallets(id) {
  var box = document.getElementById('partnerWalletSummary');
  if (!id) { box.classList.add('hidden'); return; }
  box.classList.remove('hidden');
  fetch('get-partner-wallets.php?partner_id=' + encodeURIComponent(id))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) return;
      document.getElementById('partnerEarnWallet').textContent = formatMoney(data.wallets.earn_wallet);
      document.getElementById('partnerCodWallet').textContent = formatMoney(data.wallets.cod_wallet);
      var amt = document.getElementById('amount');
      if (amt && (!amt.value || amt.value === '0')) {
        amt.value = Number(data.wallets.earn_wallet || 0).toFixed(2);
      }
    })
    .catch(function () {});
}
</script>
<?php sa_layout_end(); ?>
