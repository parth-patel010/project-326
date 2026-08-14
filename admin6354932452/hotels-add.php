<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
sa_require_login();

$pdo = admin_db();
$error = '';
$flash = '';

function sa_hotel_col(PDO $pdo, string $col): bool
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
    'gst_enabled' => sa_hotel_col($pdo, 'gst_enabled'),
    'gst_percent' => sa_hotel_col($pdo, 'gst_percent'),
    'gst_number' => sa_hotel_col($pdo, 'gst_number'),
    'service_charge_percent' => sa_hotel_col($pdo, 'service_charge_percent'),
    'address' => sa_hotel_col($pdo, 'address'),
    'phone' => sa_hotel_col($pdo, 'phone'),
    'dining_total_tables' => sa_hotel_col($pdo, 'dining_total_tables'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $phone = preg_replace('/\D/', '', (string) ($_POST['phone'] ?? ''));
    $image = trim((string) ($_POST['image'] ?? 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=900&q=80'));
    $lat = (float) ($_POST['latitude'] ?? 0);
    $lng = (float) ($_POST['longitude'] ?? 0);
    $email = trim((string) ($_POST['login_email'] ?? ''));
    $password = (string) ($_POST['login_password'] ?? '');
    $publicId = trim((string) ($_POST['public_id'] ?? '')) ?: (string) time();
    $gstEnabled = !empty($_POST['gst_enabled']) ? 1 : 0;
    $gstPercent = max(0, min(50, (float) ($_POST['gst_percent'] ?? 5)));
    $gstNumber = strtoupper(trim((string) ($_POST['gst_number'] ?? '')));
    $servicePct = max(0, min(30, (float) ($_POST['service_charge_percent'] ?? 0)));

    if ($gstEnabled && $gstNumber === '') {
        $error = 'GST Number is required when GST is enabled';
    } elseif ($name === '' || $email === '' || strlen($password) < 4) {
        $error = 'Name, login email, and password (min 4) required';
    } else {
        try {
            $pdo->beginTransaction();
            $insertCols = [
                'public_id', 'name', 'image', 'rating', 'rating_count', 'area',
                'delivery_mins', 'distance_km', 'delivery_fee', 'avg_price', 'tags',
                'pure_veg', 'offer_active', 'is_active', 'latitude', 'longitude', 'sort_order',
            ];
            $insertVals = [
                ':pid', ':name', ':image', '4.0', '0', ':area',
                '30', '0', '29', '200', ':tags',
                '1', '0', '1', ':lat', ':lng', '100',
            ];
            $params = [
                ':pid' => $publicId,
                ':name' => $name,
                ':image' => $image,
                ':area' => $area,
                ':tags' => 'Food • Delivery',
                ':lat' => $lat ?: null,
                ':lng' => $lng ?: null,
            ];

            if ($cols['address']) {
                $insertCols[] = 'address';
                $insertVals[] = ':address';
                $params[':address'] = $address !== '' ? $address : null;
            }
            if ($cols['phone']) {
                $insertCols[] = 'phone';
                $insertVals[] = ':phone';
                $params[':phone'] = strlen($phone) === 10 ? $phone : null;
            }
            if ($cols['gst_enabled']) {
                $insertCols[] = 'gst_enabled';
                $insertVals[] = ':gst_en';
                $params[':gst_en'] = $gstEnabled;
            }
            if ($cols['gst_percent']) {
                $insertCols[] = 'gst_percent';
                $insertVals[] = ':gst_pct';
                $params[':gst_pct'] = $gstEnabled ? $gstPercent : 0;
            }
            if ($cols['gst_number']) {
                $insertCols[] = 'gst_number';
                $insertVals[] = ':gst_no';
                $params[':gst_no'] = $gstEnabled && $gstNumber !== '' ? $gstNumber : null;
            }
            if ($cols['service_charge_percent']) {
                $insertCols[] = 'service_charge_percent';
                $insertVals[] = ':svc';
                $params[':svc'] = $servicePct;
            }
            if ($cols['dining_total_tables']) {
                $insertCols[] = 'dining_total_tables';
                $insertVals[] = '12';
            }

            $pdo->prepare(
                'INSERT INTO hotels (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')'
            )->execute($params);
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
            $flash = 'Hotel created. Login: ' . $email
                . ($gstEnabled ? ' · GST ON @ ' . rtrim(rtrim(number_format($gstPercent, 2), '0'), '.') . '%' : ' · GST off');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

sa_layout_start('Add Hotel', 'hotels-add.php', 'Create restaurant + hotel admin login');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error"><?= sa_h($error) ?></div><?php endif; ?>
<form method="post" class="card max-w-2xl" id="addHotelForm">
  <h3>Hotel details</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div class="sm:col-span-2"><label>Hotel name <span class="text-red-500">*</span></label><input class="input" name="name" required value="<?= sa_h($_POST['name'] ?? '') ?>"></div>
    <div><label>Public ID (optional)</label><input class="input" name="public_id" placeholder="auto" value="<?= sa_h($_POST['public_id'] ?? '') ?>"></div>
    <div><label>Area</label><input class="input" name="area" value="<?= sa_h($_POST['area'] ?? '') ?>"></div>
    <?php if ($cols['address']): ?>
      <div class="sm:col-span-2"><label>Complete address</label><textarea class="input" name="address" rows="2"><?= sa_h($_POST['address'] ?? '') ?></textarea></div>
    <?php endif; ?>
    <?php if ($cols['phone']): ?>
      <div><label>Phone</label><input class="input" name="phone" type="tel" maxlength="10" pattern="[0-9]{10}" placeholder="10-digit mobile" value="<?= sa_h($_POST['phone'] ?? '') ?>"></div>
    <?php endif; ?>
    <div class="sm:col-span-2"><label>Image URL</label><input class="input" name="image" value="<?= sa_h($_POST['image'] ?? '') ?>"></div>
    <div><label>Latitude</label><input class="input" name="latitude" type="number" step="0.0000001" value="<?= sa_h($_POST['latitude'] ?? '22.3072') ?>"></div>
    <div><label>Longitude</label><input class="input" name="longitude" type="number" step="0.0000001" value="<?= sa_h($_POST['longitude'] ?? '73.1812') ?>"></div>
  </div>

  <?php if ($cols['gst_enabled'] || $cols['gst_percent'] || $cols['gst_number']): ?>
  <h3 class="!mt-4">Tax / GST</h3>
  <p class="muted !mt-0 mb-3">Same as EatnSay — enable GST to print GST invoices and add tax on POS bills.</p>
  <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 space-y-3">
    <?php if ($cols['gst_enabled']):
        $gstOn = !empty($_POST['gst_enabled']);
    ?>
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-gray-900 !mb-0">Enable GST?</p>
        <p class="text-xs text-gray-500 !mb-0">Turn on to automatically add tax and show GSTIN on bills</p>
      </div>
      <label class="!mb-0 inline-flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" name="gst_enabled" value="1" id="toggleGstCreate" <?= $gstOn ? 'checked' : '' ?> onchange="toggleGstCreate(this.checked)">
        Yes
      </label>
    </div>
    <?php else:
        $gstOn = false;
    endif; ?>

    <div id="gstCreateFields" class="<?= $gstOn ? '' : 'hidden' ?> space-y-3">
      <?php if ($cols['gst_percent']): ?>
      <div>
        <label>GST Percentage <span class="text-red-500">*</span></label>
        <div class="relative">
          <input class="input !pr-8" type="number" step="0.01" min="0" max="50" name="gst_percent" id="gstPercentCreate" value="<?= sa_h((string)($_POST['gst_percent'] ?? '5.00')) ?>">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-sm">%</span>
        </div>
        <p class="muted !mb-0 text-xs">Common rates: 5%, 12%, 18%, 28%</p>
      </div>
      <?php endif; ?>
      <?php if ($cols['gst_number']): ?>
      <div>
        <label>GST Number (GSTIN) <span class="text-red-500">*</span></label>
        <input class="input" name="gst_number" id="gstNumberCreate" maxlength="15" placeholder="22AAAAA0000A1Z5" value="<?= sa_h($_POST['gst_number'] ?? '') ?>">
      </div>
      <?php endif; ?>
      <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-800 space-y-1">
        <p class="font-bold !mb-1">How GST works</p>
        <p class="!mb-0">• GST is added only on menu items marked “GST excluded”</p>
        <p class="!mb-0">• Bills show Taxable + CGST + SGST separately</p>
        <p class="!mb-0">• Example: ₹100 item + 5% GST = ₹105 total</p>
      </div>
    </div>

    <?php if ($cols['service_charge_percent']): ?>
    <div>
      <label>Service charge %</label>
      <div class="relative">
        <input class="input !pr-8" type="number" step="0.01" min="0" max="30" name="service_charge_percent" value="<?= sa_h((string)($_POST['service_charge_percent'] ?? '0')) ?>">
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-sm">%</span>
      </div>
      <p class="muted !mb-0 text-xs">0% – 30% applied on bill subtotal after discount</p>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <h3 class="!mt-4">Hotel admin login</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div><label>Email <span class="text-red-500">*</span></label><input class="input" type="email" name="login_email" required value="<?= sa_h($_POST['login_email'] ?? '') ?>"></div>
    <div><label>Password <span class="text-red-500">*</span></label><input class="input" type="password" name="login_password" required minlength="4"></div>
  </div>
  <button class="btn mt-2" type="submit">
    <span class="material-icons-outlined text-[18px]">add_business</span> Create hotel
  </button>
</form>
<script>
function toggleGstCreate(on){
  var box = document.getElementById('gstCreateFields');
  var gstNo = document.getElementById('gstNumberCreate');
  if (box) box.classList.toggle('hidden', !on);
  if (gstNo) {
    if (on) gstNo.setAttribute('required', 'required');
    else gstNo.removeAttribute('required');
  }
}
document.addEventListener('DOMContentLoaded', function(){
  var cb = document.getElementById('toggleGstCreate');
  if (cb) toggleGstCreate(cb.checked);
});
</script>
<?php sa_layout_end(); ?>
