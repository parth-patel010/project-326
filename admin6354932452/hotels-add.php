<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$error = '';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $image = trim((string) ($_POST['image'] ?? 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=900&q=80'));
    $lat = (float) ($_POST['latitude'] ?? 0);
    $lng = (float) ($_POST['longitude'] ?? 0);
    $email = trim((string) ($_POST['login_email'] ?? ''));
    $password = (string) ($_POST['login_password'] ?? '');
    $publicId = trim((string) ($_POST['public_id'] ?? '')) ?: (string) time();

    if ($name === '' || $email === '' || strlen($password) < 4) {
        $error = 'Name, login email, and password (min 4) required';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO hotels (public_id, name, image, rating, rating_count, area, delivery_mins, distance_km, delivery_fee, avg_price, tags, pure_veg, offer_active, is_active, latitude, longitude, sort_order)
                 VALUES (:pid, :name, :image, 4.0, 0, :area, 30, 0, 29, 200, :tags, 1, 0, 1, :lat, :lng, 100)'
            )->execute([
                ':pid' => $publicId,
                ':name' => $name,
                ':image' => $image,
                ':area' => $area,
                ':tags' => 'Food • Delivery',
                ':lat' => $lat ?: null,
                ':lng' => $lng ?: null,
            ]);
            $hotelId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO hotel_users (hotel_id, email, password_hash, name) VALUES (:hid, :email, :hash, :name)'
            )->execute([
                ':hid' => $hotelId,
                ':email' => $email,
                ':hash' => password_hash($password, PASSWORD_BCRYPT),
                ':name' => $name,
            ]);
            $pdo->commit();
            $flash = 'Hotel created. Login: ' . $email;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

sa_layout_start('Add Hotel', 'hotels-add.php');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="border-color:#ef9a9a;background:#ffebee"><?= sa_h($error) ?></div><?php endif; ?>
<form method="post" class="card">
  <label>Hotel name</label>
  <input class="input" name="name" required>
  <label>Public ID (optional)</label>
  <input class="input" name="public_id" placeholder="auto">
  <label>Area</label>
  <input class="input" name="area">
  <label>Image URL</label>
  <input class="input" name="image">
  <label>Latitude</label>
  <input class="input" name="latitude" type="number" step="0.0000001" value="22.3072">
  <label>Longitude</label>
  <input class="input" name="longitude" type="number" step="0.0000001" value="73.1812">
  <h3>Hotel admin login</h3>
  <label>Email</label>
  <input class="input" type="email" name="login_email" required>
  <label>Password</label>
  <input class="input" type="password" name="login_password" required>
  <button class="btn" type="submit">Create hotel</button>
</form>
<?php sa_layout_end(); ?>
