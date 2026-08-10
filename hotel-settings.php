<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api/lib/hotels.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';
$error = '';
$hotel = ha_hotel() ?? [];

$cols = [
    'address' => ha_col_exists('hotels', 'address', $pdo),
    'description' => ha_col_exists('hotels', 'description', $pdo),
    'phone' => ha_col_exists('hotels', 'phone', $pdo),
    'city' => ha_col_exists('hotels', 'city', $pdo),
    'is_open' => ha_col_exists('hotels', 'is_open', $pdo),
    'gst_enabled' => ha_col_exists('hotels', 'gst_enabled', $pdo),
    'gst_percent' => ha_col_exists('hotels', 'gst_percent', $pdo),
    'gst_number' => ha_col_exists('hotels', 'gst_number', $pdo),
    'service_charge_percent' => ha_col_exists('hotels', 'service_charge_percent', $pdo),
    'dining_total_tables' => ha_col_exists('hotels', 'dining_total_tables', $pdo),
    'operating_hours' => ha_col_exists('hotels', 'operating_hours', $pdo),
];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$defaultHours = [];
foreach ($days as $day) {
    $defaultHours[$day] = ['isOpen' => true, 'openTime' => '09:00', 'closeTime' => '22:00'];
}

$operatingHours = $defaultHours;
if ($cols['operating_hours'] && !empty($hotel['operating_hours'])) {
    $decoded = json_decode((string) $hotel['operating_hours'], true);
    if (is_array($decoded)) {
        foreach ($days as $day) {
            if (isset($decoded[$day]) && is_array($decoded[$day])) {
                $operatingHours[$day] = array_merge($defaultHours[$day], $decoded[$day]);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $image = trim((string) ($_POST['image'] ?? ''));
    $tags = trim((string) ($_POST['tags'] ?? ''));
    $latRaw = trim((string) ($_POST['latitude'] ?? ''));
    $lngRaw = trim((string) ($_POST['longitude'] ?? ''));
    $lat = $latRaw !== '' ? (float) $latRaw : 0.0;
    $lng = $lngRaw !== '' ? (float) $lngRaw : 0.0;
    $avgPrice = (float) ($_POST['avg_price'] ?? 0);
    $isOpen = !empty($_POST['is_open']) ? 1 : 0;
    $offerActive = !empty($_POST['offer_active']) ? 1 : 0;
    $gstEnabled = !empty($_POST['gst_enabled']) ? 1 : 0;
    $gstPercent = max(0, min(50, (float) ($_POST['gst_percent'] ?? 5)));
    $gstNumber = strtoupper(trim((string) ($_POST['gst_number'] ?? '')));
    $servicePct = max(0, min(50, (float) ($_POST['service_charge_percent'] ?? 0)));
    $tableCount = max(1, min(200, (int) ($_POST['dining_total_tables'] ?? 12)));

    $hoursPayload = $defaultHours;
    if ($cols['operating_hours'] && isset($_POST['hours']) && is_array($_POST['hours'])) {
        foreach ($days as $day) {
            $row = $_POST['hours'][$day] ?? [];
            $hoursPayload[$day] = [
                'isOpen' => !empty($row['isOpen']),
                'openTime' => (string) ($row['open'] ?? '09:00'),
                'closeTime' => (string) ($row['close'] ?? '22:00'),
            ];
        }
    }

    if ($name === '') {
        $error = 'Hotel name required';
    } else {
        try {
            $prep = fm_hotel_prep_mins($pdo, $hotelId);

            $sets = [
                'name = :name',
                'area = :area',
                'image = :image',
                'tags = :tags',
                'latitude = :lat',
                'longitude = :lng',
                'avg_price = :avg',
                'offer_active = :offer',
                'delivery_mins = :dm',
            ];
            $params = [
                ':name' => $name,
                ':area' => $area,
                ':image' => $image !== '' ? $image : ($hotel['image'] ?? ''),
                ':tags' => $tags !== '' ? $tags : 'Food • Delivery',
                ':lat' => $lat ?: null,
                ':lng' => $lng ?: null,
                ':avg' => $avgPrice,
                ':offer' => $offerActive,
                ':dm' => $prep + 15,
                ':id' => $hotelId,
            ];

            if ($cols['city']) {
                $sets[] = 'city = :city';
                $params[':city'] = $city !== '' ? $city : null;
            }
            if ($cols['address']) {
                $sets[] = 'address = :address';
                $params[':address'] = $address !== '' ? $address : null;
            }
            if ($cols['description']) {
                $sets[] = 'description = :description';
                $params[':description'] = $description !== '' ? $description : null;
            }
            if ($cols['phone']) {
                $sets[] = 'phone = :phone';
                $params[':phone'] = $phone !== '' ? $phone : null;
            }
            if ($cols['is_open']) {
                $sets[] = 'is_open = :open';
                $params[':open'] = $isOpen;
            }
            if ($cols['gst_enabled']) {
                $sets[] = 'gst_enabled = :gst_en';
                $params[':gst_en'] = $gstEnabled;
            }
            if ($cols['gst_percent']) {
                $sets[] = 'gst_percent = :gst_pct';
                $params[':gst_pct'] = $gstPercent;
            }
            if ($cols['gst_number']) {
                $sets[] = 'gst_number = :gst_no';
                $params[':gst_no'] = $gstNumber !== '' ? $gstNumber : null;
            }
            if ($cols['service_charge_percent']) {
                $sets[] = 'service_charge_percent = :svc';
                $params[':svc'] = $servicePct;
            }
            if ($cols['dining_total_tables']) {
                $sets[] = 'dining_total_tables = :tables';
                $params[':tables'] = $tableCount;
            }
            if ($cols['operating_hours']) {
                $sets[] = 'operating_hours = :hours';
                $params[':hours'] = json_encode($hoursPayload);
            }

            $pdo->prepare('UPDATE hotels SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            $flash = 'Settings saved';
            $hotel = ha_hotel() ?? $hotel;
            $operatingHours = $hoursPayload;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$prepVal = fm_hotel_prep_mins($pdo, $hotelId);
$latVal = (string) ($hotel['latitude'] ?? '');
$lngVal = (string) ($hotel['longitude'] ?? '');
$mapUrl = ($latVal !== '' && $lngVal !== '')
    ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode($latVal) . '&mlon=' . rawurlencode($lngVal) . '#map=17/' . rawurlencode($latVal) . '/' . rawurlencode($lngVal)
    : '';

ha_layout_start('Hotel settings', 'hotel-settings.php', 'Profile, location, GST, tables, and kitchen');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1rem;font-size:0.875rem"><?= ha_h($error) ?></div><?php endif; ?>

<form method="post" class="space-y-4 max-w-3xl">
  <div class="card">
    <h3>Profile</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
      <div class="sm:col-span-2"><label>Hotel name</label><input class="input" name="name" required value="<?= ha_h($hotel['name'] ?? '') ?>"></div>
      <?php if ($cols['description']): ?>
        <div class="sm:col-span-2"><label>Description / cuisines</label><textarea class="input" name="description" rows="2" maxlength="500"><?= ha_h($hotel['description'] ?? '') ?></textarea></div>
      <?php endif; ?>
      <?php if ($cols['address']): ?>
        <div class="sm:col-span-2"><label>Full address</label><textarea class="input" name="address" rows="2" maxlength="500"><?= ha_h($hotel['address'] ?? '') ?></textarea></div>
      <?php endif; ?>
      <div><label>Area</label><input class="input" name="area" value="<?= ha_h($hotel['area'] ?? '') ?>"></div>
      <?php if ($cols['city']): ?>
        <div><label>City</label><input class="input" name="city" value="<?= ha_h($hotel['city'] ?? '') ?>"></div>
      <?php endif; ?>
      <?php if ($cols['phone']): ?>
        <div><label>Phone</label><input class="input" name="phone" type="tel" maxlength="15" value="<?= ha_h($hotel['phone'] ?? '') ?>" placeholder="10-digit mobile"></div>
      <?php endif; ?>
      <div><label>Avg price ₹</label><input class="input" type="number" step="1" name="avg_price" value="<?= ha_h((string)($hotel['avg_price'] ?? '200')) ?>"></div>
      <div class="sm:col-span-2"><label>Image URL</label><input class="input" name="image" value="<?= ha_h($hotel['image'] ?? '') ?>"></div>
      <div class="sm:col-span-2"><label>Tags</label><input class="input" name="tags" value="<?= ha_h($hotel['tags'] ?? '') ?>" placeholder="Gujarati • Thali • Pure veg"></div>
    </div>
  </div>

  <div class="card">
    <h3>Location</h3>
    <p class="muted !mt-0 mb-3">Visible and editable. Used for nearby restaurant discovery in the app.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
      <div><label>Latitude</label><input class="input" type="number" step="any" name="latitude" id="inputLat" value="<?= ha_h($latVal) ?>" placeholder="22.3072"></div>
      <div><label>Longitude</label><input class="input" type="number" step="any" name="longitude" id="inputLng" value="<?= ha_h($lngVal) ?>" placeholder="73.1812"></div>
    </div>
    <div class="flex flex-wrap gap-2 mb-2">
      <button type="button" class="btn secondary !py-2" onclick="useMyLocation()">
        <span class="material-icons-outlined text-[18px]">my_location</span> Use current location
      </button>
      <?php if ($mapUrl): ?>
        <a class="btn secondary !py-2" href="<?= ha_h($mapUrl) ?>" target="_blank" rel="noopener">
          <span class="material-icons-outlined text-[18px]">map</span> Open in map
        </a>
      <?php endif; ?>
    </div>
    <p class="muted">Paste coordinates from Google Maps (right-click → coordinates) or OpenStreetMap.</p>
  </div>

  <div class="card">
    <h3>Kitchen & listing</h3>
    <p class="muted mb-3">
      Prep time is automatic: average of the last 5 completed orders (accept → ready).
      Current estimate: <strong><?= (int) $prepVal ?> min</strong>
      (default <?= (int) FM_DEFAULT_PREP_MINS ?> until 5 samples). Used for customer ETA.
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
      <?php if ($cols['dining_total_tables']): ?>
        <div>
          <label>Dining table count</label>
          <input class="input" type="number" min="1" max="200" name="dining_total_tables" value="<?= (int)($hotel['dining_total_tables'] ?? 12) ?>">
          <p class="muted !-mt-2 mb-3">Controls the POS floor map.</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-4 mb-2">
      <label class="!mb-0 flex items-center gap-2 text-sm font-medium">
        <input type="checkbox" name="is_open" value="1" <?= !isset($hotel['is_open']) || !empty($hotel['is_open']) ? 'checked' : '' ?> <?= $cols['is_open'] ? '' : 'disabled' ?>>
        Accepting online orders (open)
      </label>
      <label class="!mb-0 flex items-center gap-2 text-sm font-medium">
        <input type="checkbox" name="offer_active" value="1" <?= !empty($hotel['offer_active']) ? 'checked' : '' ?>>
        Show offer badge on app
      </label>
    </div>
  </div>

  <?php if ($cols['gst_enabled'] || $cols['gst_percent'] || $cols['gst_number'] || $cols['service_charge_percent']): ?>
  <div class="card">
    <h3>Tax & service charge</h3>
    <div class="flex flex-wrap gap-4 mb-3">
      <?php if ($cols['gst_enabled']): ?>
        <label class="!mb-0 flex items-center gap-2 text-sm font-medium">
          <input type="checkbox" name="gst_enabled" value="1" <?= !empty($hotel['gst_enabled']) ? 'checked' : '' ?>>
          GST enabled on POS bills
        </label>
      <?php endif; ?>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
      <?php if ($cols['gst_percent']): ?>
        <div><label>GST %</label><input class="input" type="number" step="0.01" min="0" max="50" name="gst_percent" value="<?= ha_h((string)($hotel['gst_percent'] ?? '5.00')) ?>"></div>
      <?php endif; ?>
      <?php if ($cols['gst_number']): ?>
        <div><label>GSTIN</label><input class="input" name="gst_number" maxlength="32" value="<?= ha_h($hotel['gst_number'] ?? '') ?>" placeholder="22AAAAA0000A1Z5"></div>
      <?php endif; ?>
      <?php if ($cols['service_charge_percent']): ?>
        <div><label>Service charge %</label><input class="input" type="number" step="0.01" min="0" max="50" name="service_charge_percent" value="<?= ha_h((string)($hotel['service_charge_percent'] ?? '0')) ?>"></div>
      <?php endif; ?>
    </div>
    <p class="muted">GST is only added on items marked “GST excluded”. Inclusive items skip tax.</p>
  </div>
  <?php endif; ?>

  <?php if ($cols['operating_hours']): ?>
  <div class="card">
    <h3>Opening hours</h3>
    <div class="space-y-2">
      <?php foreach ($days as $day):
          $data = $operatingHours[$day] ?? $defaultHours[$day];
          $isDayOpen = !empty($data['isOpen']);
      ?>
        <div class="flex flex-wrap items-center gap-3 py-2 border-b border-gray-50 last:border-0 day-hours-row">
          <div class="w-24 text-sm font-semibold text-gray-700"><?= ha_h($day) ?></div>
          <label class="!mb-0 flex items-center gap-2 text-sm">
            <input type="checkbox" name="hours[<?= ha_h($day) ?>][isOpen]" value="1" <?= $isDayOpen ? 'checked' : '' ?> onchange="toggleDayHours(this)">
            Open
          </label>
          <div class="hours-inputs flex items-center gap-2 <?= $isDayOpen ? '' : 'opacity-50 pointer-events-none' ?>">
            <input type="time" class="input !mb-0 !w-auto" name="hours[<?= ha_h($day) ?>][open]" value="<?= ha_h((string)($data['openTime'] ?? '09:00')) ?>">
            <span class="text-gray-400">–</span>
            <input type="time" class="input !mb-0 !w-auto" name="hours[<?= ha_h($day) ?>][close]" value="<?= ha_h((string)($data['closeTime'] ?? '22:00')) ?>">
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <button class="btn" type="submit"><span class="material-icons-outlined text-[18px]">save</span> Save settings</button>
</form>

<script>
function useMyLocation(){
  if (!navigator.geolocation) { alert('Geolocation not supported'); return; }
  navigator.geolocation.getCurrentPosition(function(pos){
    document.getElementById('inputLat').value = pos.coords.latitude.toFixed(7);
    document.getElementById('inputLng').value = pos.coords.longitude.toFixed(7);
  }, function(){ alert('Could not get location'); }, { enableHighAccuracy: true, timeout: 10000 });
}
function toggleDayHours(cb){
  var row = cb.closest('.day-hours-row');
  var inputs = row ? row.querySelector('.hours-inputs') : null;
  if (!inputs) return;
  if (cb.checked) { inputs.classList.remove('opacity-50','pointer-events-none'); }
  else { inputs.classList.add('opacity-50','pointer-events-none'); }
}
</script>
<?php ha_layout_end(); ?>
