<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/Settings.php';
sa_require_login();

$pdo = admin_db();
$error = '';
$flash = '';
$defaultRadius = (float) (Settings::get()['default_partner_radius_km'] ?? 5);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
    if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
        $phone = substr($phone, 2);
    }
    $password = (string) ($_POST['password'] ?? '');
    $radius = (float) ($_POST['service_radius_km'] ?? $defaultRadius);
    $verified = !empty($_POST['is_verified']) ? 1 : 0;
    $insurance = !empty($_POST['has_insurance']) ? 1 : 0;

    if ($name === '' || strlen($phone) !== 10 || strlen($password) < 4) {
        $error = 'Name, 10-digit phone, password required';
    } else {
        try {
            $publicId = 'DP' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->prepare(
                'INSERT INTO delivery_partners
                 (public_id, full_name, phone, password_hash, service_radius_km, is_verified, has_insurance, status)
                 VALUES (:pid, :name, :phone, :hash, :radius, :ver, :ins, \'active\')'
            )->execute([
                ':pid' => $publicId,
                ':name' => $name,
                ':phone' => $phone,
                ':hash' => password_hash($password, PASSWORD_BCRYPT),
                ':radius' => $radius,
                ':ver' => $verified,
                ':ins' => $insurance,
            ]);
            $flash = 'Partner created: ' . $phone;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

sa_layout_start('Add Delivery Partner', 'delivery-partner-add.php');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="border-color:#ef9a9a;background:#ffebee"><?= sa_h($error) ?></div><?php endif; ?>
<form method="post" class="card">
  <label>Full name</label>
  <input class="input" name="full_name" required>
  <label>Phone</label>
  <input class="input" name="phone" required maxlength="10">
  <label>Password</label>
  <input class="input" type="password" name="password" required>
  <label>Service radius (km)</label>
  <input class="input" type="number" step="0.1" name="service_radius_km" value="<?= sa_h((string)$defaultRadius) ?>">
  <label><input type="checkbox" name="is_verified" value="1"> Driver verified</label>
  <label><input type="checkbox" name="has_insurance" value="1"> Has insurance</label>
  <div style="margin-top:12px"><button class="btn" type="submit">Create partner</button></div>
</form>
<?php sa_layout_end(); ?>
