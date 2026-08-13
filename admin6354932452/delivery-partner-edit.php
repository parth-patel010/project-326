<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: delivery-partners.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM delivery_partners WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$partner = $stmt->fetch();
if (!$partner) {
    header('Location: delivery-partners.php');
    exit;
}

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    try {
        if ($action === 'save') {
            $name = trim((string) ($_POST['full_name'] ?? ''));
            $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
            if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
                $phone = substr($phone, 2);
            }
            $radius = (float) ($_POST['service_radius_km'] ?? 5);
            $verified = !empty($_POST['is_verified']) ? 1 : 0;
            $insurance = !empty($_POST['has_insurance']) ? 1 : 0;
            $status = (string) ($_POST['status'] ?? 'active');
            if (!in_array($status, ['active', 'inactive', 'blocked'], true)) {
                $status = 'active';
            }
            $vehicleType = trim((string) ($_POST['vehicle_type'] ?? 'bike'));
            $vehicleNumber = trim((string) ($_POST['vehicle_number'] ?? ''));

            if ($name === '' || strlen($phone) !== 10) {
                $error = 'Name and 10-digit phone required';
            } else {
                $pdo->prepare(
                    'UPDATE delivery_partners SET
                       full_name = :name,
                       phone = :phone,
                       service_radius_km = :radius,
                       is_verified = :ver,
                       has_insurance = :ins,
                       status = :status,
                       vehicle_type = :vtype,
                       vehicle_number = :vnum
                     WHERE id = :id'
                )->execute([
                    ':name' => $name,
                    ':phone' => $phone,
                    ':radius' => $radius,
                    ':ver' => $verified,
                    ':ins' => $insurance,
                    ':status' => $status,
                    ':vtype' => $vehicleType !== '' ? $vehicleType : 'bike',
                    ':vnum' => $vehicleNumber !== '' ? $vehicleNumber : null,
                    ':id' => $id,
                ]);
                $flash = 'Partner updated';
            }
        } elseif ($action === 'password') {
            $pass = (string) ($_POST['password'] ?? '');
            if (strlen($pass) < 4) {
                $error = 'Password min 4 characters';
            } else {
                $pdo->prepare('UPDATE delivery_partners SET password_hash = :h WHERE id = :id')
                    ->execute([':h' => password_hash($pass, PASSWORD_BCRYPT), ':id' => $id]);
                $flash = 'Password reset';
            }
        } elseif ($action === 'deactivate_tokens') {
            $pdo->prepare('UPDATE partner_push_tokens SET is_active = 0 WHERE partner_id = :id')
                ->execute([':id' => $id]);
            $flash = 'Push tokens deactivated';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $stmt->execute([':id' => $id]);
    $partner = $stmt->fetch();
}

$tokens = [];
try {
    $tstmt = $pdo->prepare(
        'SELECT * FROM partner_push_tokens WHERE partner_id = :id ORDER BY updated_at DESC LIMIT 20'
    );
    $tstmt->execute([':id' => $id]);
    $tokens = $tstmt->fetchAll();
} catch (Throwable $e) {
    $tokens = [];
}

sa_layout_start('Edit partner', 'delivery-partners.php', (string) $partner['full_name']);
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error"><?= sa_h($error) ?></div><?php endif; ?>

<form method="post" class="card max-w-2xl mb-4">
  <input type="hidden" name="action" value="save">
  <div class="flex items-center justify-between mb-3">
    <h3 class="!mb-0">Partner details</h3>
    <a href="delivery-partners.php" class="text-sm text-gray-500 hover:text-primary">← Back</a>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div class="sm:col-span-2"><label>Full name</label><input class="input" name="full_name" required value="<?= sa_h($partner['full_name']) ?>"></div>
    <div><label>Public ID</label><input class="input" value="<?= sa_h($partner['public_id']) ?>" disabled></div>
    <div><label>Phone</label><input class="input" name="phone" required maxlength="10" value="<?= sa_h($partner['phone']) ?>"></div>
    <div><label>Service radius (km)</label><input class="input" type="number" step="0.1" name="service_radius_km" value="<?= sa_h((string)$partner['service_radius_km']) ?>"></div>
    <div>
      <label>Status</label>
      <select class="input" name="status">
        <?php foreach (['active', 'inactive', 'blocked'] as $st): ?>
          <option value="<?= $st ?>" <?= ($partner['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Vehicle type</label><input class="input" name="vehicle_type" value="<?= sa_h((string)($partner['vehicle_type'] ?? 'bike')) ?>"></div>
    <div><label>Vehicle number</label><input class="input" name="vehicle_number" value="<?= sa_h((string)($partner['vehicle_number'] ?? '')) ?>"></div>
  </div>
  <div class="flex flex-wrap gap-4 mb-4 mt-1">
    <label class="!mb-0 flex items-center gap-2 font-medium text-sm text-gray-700"><input type="checkbox" name="is_verified" value="1" <?= !empty($partner['is_verified']) ? 'checked' : '' ?> class="rounded border-gray-300 text-primary"> Driver verified</label>
    <label class="!mb-0 flex items-center gap-2 font-medium text-sm text-gray-700"><input type="checkbox" name="has_insurance" value="1" <?= !empty($partner['has_insurance']) ? 'checked' : '' ?> class="rounded border-gray-300 text-primary"> Has insurance</label>
  </div>
  <div class="muted mb-3 text-sm">
    Online: <?= !empty($partner['is_online']) ? 'Yes' : 'No' ?> ·
    Completed: <?= (int) $partner['orders_completed'] ?> ·
    Earnings: ₹<?= number_format((float) $partner['earnings_total'], 2) ?> ·
    COD wallet: ₹<?= number_format((float) $partner['cod_wallet'], 2) ?>
  </div>
  <button class="btn" type="submit">
    <span class="material-icons-outlined text-[18px]">save</span> Save partner
  </button>
</form>

<form method="post" class="card max-w-2xl mb-4">
  <input type="hidden" name="action" value="password">
  <h3>Reset app password</h3>
  <div class="max-w-sm"><label>New password</label><input class="input" type="password" name="password" required minlength="4"></div>
  <button class="btn secondary" type="submit">
    <span class="material-icons-outlined text-[18px]">lock_reset</span> Reset password
  </button>
</form>

<div class="card max-w-3xl">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <h3 class="!mb-0">FCM / push tokens</h3>
    <form method="post" onsubmit="return confirm('Deactivate all tokens for this partner?')">
      <input type="hidden" name="action" value="deactivate_tokens">
      <button class="btn secondary" type="submit" <?= $tokens ? '' : 'disabled' ?>>
        <span class="material-icons-outlined text-[18px]">notifications_off</span> Deactivate all
      </button>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr><th>Platform</th><th>Client</th><th>Active</th><th>Updated</th><th>Token</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tokens as $t): ?>
          <tr>
            <td><?= sa_h((string)$t['platform']) ?></td>
            <td><?= sa_h((string)$t['client']) ?></td>
            <td><?= !empty($t['is_active']) ? 'Yes' : 'No' ?></td>
            <td><?= sa_h((string)$t['updated_at']) ?></td>
            <td class="text-xs break-all max-w-xs"><?= sa_h(substr((string)$t['push_token'], 0, 48)) ?>…</td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tokens): ?>
          <tr><td colspan="5" class="text-center text-gray-500 py-8">No push tokens registered yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted mt-3 text-sm">Tokens come from the delivery partner app after FCM / Expo push setup.</p>
</div>
<?php sa_layout_end(); ?>
