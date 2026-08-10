<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
require_once dirname(__DIR__) . '/api/lib/hotels.php';
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: hotels.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM hotels WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$hotel = $stmt->fetch();
if (!$hotel) {
    header('Location: hotels.php');
    exit;
}

$hu = $pdo->prepare('SELECT * FROM hotel_users WHERE hotel_id = :h ORDER BY id ASC LIMIT 1');
$hu->execute([':h' => $id]);
$hotelUser = $hu->fetch() ?: null;

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $area = trim((string) ($_POST['area'] ?? ''));
        $image = trim((string) ($_POST['image'] ?? ''));
        $lat = (float) ($_POST['latitude'] ?? 0);
        $lng = (float) ($_POST['longitude'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $isOpen = !empty($_POST['is_open']) ? 1 : 0;

        if ($name === '') {
            $error = 'Name required';
        } else {
            $hasOpen = (bool) $pdo->query("SHOW COLUMNS FROM hotels LIKE 'is_open'")->fetch();
            $prep = fm_hotel_prep_mins($pdo, $id);
            $sets = [
                'name=:name', 'area=:area', 'image=:image', 'latitude=:lat', 'longitude=:lng',
                'is_active=:active', 'delivery_mins=:dm',
            ];
            $params = [
                ':name' => $name, ':area' => $area,
                ':image' => $image !== '' ? $image : $hotel['image'],
                ':lat' => $lat ?: null, ':lng' => $lng ?: null,
                ':active' => $active, ':dm' => $prep + 15, ':id' => $id,
            ];
            if ($hasOpen) {
                $sets[] = 'is_open=:open';
                $params[':open'] = $isOpen;
            }
            $pdo->prepare('UPDATE hotels SET ' . implode(',', $sets) . ' WHERE id=:id')->execute($params);
            $flash = 'Hotel updated';
            $stmt->execute([':id' => $id]);
            $hotel = $stmt->fetch();
        }
    } elseif ($action === 'password' && $hotelUser) {
        $pass = (string) ($_POST['password'] ?? '');
        if (strlen($pass) < 4) {
            $error = 'Password min 4 characters';
        } else {
            $pdo->prepare('UPDATE hotel_users SET password_hash=:h WHERE id=:id')
                ->execute([':h' => password_hash($pass, PASSWORD_BCRYPT), ':id' => $hotelUser['id']]);
            $flash = 'Hotel login password reset';
        }
    }
}

$prepVal = fm_hotel_prep_mins($pdo, $id);

sa_layout_start('Edit hotel', 'hotels.php', (string) $hotel['name']);
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error"><?= sa_h($error) ?></div><?php endif; ?>

<form method="post" class="card max-w-2xl">
  <input type="hidden" name="action" value="save">
  <div class="flex items-center justify-between mb-3">
    <h3 class="!mb-0">Hotel details</h3>
    <a href="hotels.php" class="text-sm text-gray-500 hover:text-primary">← Back</a>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div class="sm:col-span-2"><label>Name</label><input class="input" name="name" required value="<?= sa_h($hotel['name']) ?>"></div>
    <div><label>Public ID</label><input class="input" value="<?= sa_h($hotel['public_id']) ?>" disabled></div>
    <div><label>Area</label><input class="input" name="area" value="<?= sa_h($hotel['area']) ?>"></div>
    <div class="sm:col-span-2"><label>Image URL</label><input class="input" name="image" value="<?= sa_h($hotel['image']) ?>"></div>
    <div><label>Latitude</label><input class="input" type="number" step="0.0000001" name="latitude" value="<?= sa_h((string)$hotel['latitude']) ?>"></div>
    <div><label>Longitude</label><input class="input" type="number" step="0.0000001" name="longitude" value="<?= sa_h((string)$hotel['longitude']) ?>"></div>
    <div class="sm:col-span-2">
      <label>Prep time (automatic)</label>
      <input class="input" value="<?= (int) $prepVal ?> min (avg of last 5 orders; default <?= (int) FM_DEFAULT_PREP_MINS ?>)" disabled>
    </div>
  </div>
  <div class="flex flex-wrap gap-4 mb-4">
    <label class="!mb-0 flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" <?= !empty($hotel['is_active']) ? 'checked' : '' ?>> Active on platform</label>
    <label class="!mb-0 flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_open" value="1" <?= !isset($hotel['is_open']) || !empty($hotel['is_open']) ? 'checked' : '' ?>> Open for orders</label>
  </div>
  <button class="btn" type="submit">Save hotel</button>
</form>

<?php if ($hotelUser): ?>
<form method="post" class="card max-w-xl">
  <input type="hidden" name="action" value="password">
  <h3>Reset hotel admin password</h3>
  <p class="muted mb-3">Login email: <strong><?= sa_h($hotelUser['email']) ?></strong></p>
  <label>New password</label>
  <input class="input" type="password" name="password" required minlength="4">
  <button class="btn secondary" type="submit">Reset password</button>
</form>
<?php endif; ?>
<?php sa_layout_end(); ?>
