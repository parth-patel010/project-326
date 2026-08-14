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

function sa_hotel_col_edit(PDO $pdo, string $col): bool
{
    static $cache = [];
    if (array_key_exists($col, $cache)) {
        return $cache[$col];
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotels' AND COLUMN_NAME = :c"
    );
    $stmt->execute([':c' => $col]);
    return $cache[$col] = (int) $stmt->fetchColumn() > 0;
}

$cols = [
    'gst_enabled' => sa_hotel_col_edit($pdo, 'gst_enabled'),
    'gst_percent' => sa_hotel_col_edit($pdo, 'gst_percent'),
    'gst_number' => sa_hotel_col_edit($pdo, 'gst_number'),
    'service_charge_percent' => sa_hotel_col_edit($pdo, 'service_charge_percent'),
    'address' => sa_hotel_col_edit($pdo, 'address'),
    'phone' => sa_hotel_col_edit($pdo, 'phone'),
];

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
        $address = trim((string) ($_POST['address'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($_POST['phone'] ?? ''));
        $image = trim((string) ($_POST['image'] ?? ''));
        $lat = (float) ($_POST['latitude'] ?? 0);
        $lng = (float) ($_POST['longitude'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $isOpen = !empty($_POST['is_open']) ? 1 : 0;
        $gstEnabled = !empty($_POST['gst_enabled']) ? 1 : 0;
        $gstPercent = max(0, min(50, (float) ($_POST['gst_percent'] ?? 5)));
        $gstNumber = strtoupper(trim((string) ($_POST['gst_number'] ?? '')));
        $servicePct = max(0, min(30, (float) ($_POST['service_charge_percent'] ?? 0)));

        if ($name === '') {
            $error = 'Name required';
        } elseif ($gstEnabled && $cols['gst_number'] && $gstNumber === '') {
            $error = 'GST Number is required when GST is enabled';
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
            if ($cols['address']) {
                $sets[] = 'address=:address';
                $params[':address'] = $address !== '' ? $address : null;
            }
            if ($cols['phone']) {
                $sets[] = 'phone=:phone';
                $params[':phone'] = strlen($phone) === 10 ? $phone : null;
            }
            if ($cols['gst_enabled']) {
                $sets[] = 'gst_enabled=:gst_en';
                $params[':gst_en'] = $gstEnabled;
            }
            if ($cols['gst_percent']) {
                $sets[] = 'gst_percent=:gst_pct';
                $params[':gst_pct'] = $gstEnabled ? $gstPercent : 0;
            }
            if ($cols['gst_number']) {
                $sets[] = 'gst_number=:gst_no';
                $params[':gst_no'] = $gstEnabled && $gstNumber !== '' ? $gstNumber : null;
            }
            if ($cols['service_charge_percent']) {
                $sets[] = 'service_charge_percent=:svc';
                $params[':svc'] = $servicePct;
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
$gstOnUi = !empty($hotel['gst_enabled']);

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
    <?php if ($cols['address']): ?>
      <div class="sm:col-span-2"><label>Address</label><textarea class="input" name="address" rows="2"><?= sa_h((string)($hotel['address'] ?? '')) ?></textarea></div>
    <?php endif; ?>
    <?php if ($cols['phone']): ?>
      <div><label>Phone</label><input class="input" name="phone" type="tel" maxlength="10" value="<?= sa_h((string)($hotel['phone'] ?? '')) ?>"></div>
    <?php endif; ?>
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

  <?php if ($cols['gst_enabled'] || $cols['gst_percent'] || $cols['gst_number'] || $cols['service_charge_percent']): ?>
  <h3 class="!mt-2">Tax / GST</h3>
  <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 space-y-3 mb-4">
    <?php if ($cols['gst_enabled']): ?>
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-gray-900 !mb-0">Enable GST</p>
        <p class="text-xs text-gray-500 !mb-0">Add tax on POS bills and print GSTIN on receipts</p>
      </div>
      <label class="!mb-0 inline-flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" name="gst_enabled" value="1" id="toggleGstEdit" <?= $gstOnUi ? 'checked' : '' ?> onchange="toggleGstEdit(this.checked)">
        Yes
      </label>
    </div>
    <?php endif; ?>
    <div id="gstEditFields" class="<?= $gstOnUi ? '' : 'hidden' ?> space-y-3">
      <?php if ($cols['gst_percent']): ?>
      <div>
        <label>GST Percentage</label>
        <div class="relative">
          <input class="input !pr-8" type="number" step="0.01" min="0" max="50" name="gst_percent" value="<?= sa_h((string)($hotel['gst_percent'] ?? '5.00')) ?>">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-sm">%</span>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($cols['gst_number']): ?>
      <div>
        <label>GST Number (GSTIN)</label>
        <input class="input" name="gst_number" id="gstNumberEdit" maxlength="15" value="<?= sa_h((string)($hotel['gst_number'] ?? '')) ?>" placeholder="22AAAAA0000A1Z5">
      </div>
      <?php endif; ?>
    </div>
    <?php if ($cols['service_charge_percent']): ?>
    <div>
      <label>Service charge %</label>
      <div class="relative">
        <input class="input !pr-8" type="number" step="0.01" min="0" max="30" name="service_charge_percent" value="<?= sa_h((string)($hotel['service_charge_percent'] ?? '0')) ?>">
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-sm">%</span>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
<script>
function toggleGstEdit(on){
  var box = document.getElementById('gstEditFields');
  var gstNo = document.getElementById('gstNumberEdit');
  if (box) box.classList.toggle('hidden', !on);
  if (gstNo) {
    if (on) gstNo.setAttribute('required', 'required');
    else gstNo.removeAttribute('required');
  }
}
document.addEventListener('DOMContentLoaded', function(){
  var cb = document.getElementById('toggleGstEdit');
  if (cb) toggleGstEdit(cb.checked);
});
</script>
<?php sa_layout_end(); ?>
