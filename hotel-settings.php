<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dining.php';
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
    'dining_has_tents' => ha_col_exists('hotels', 'dining_has_tents', $pdo),
    'dining_total_tents' => ha_col_exists('hotels', 'dining_total_tents', $pdo),
    'dining_has_garden_tables' => ha_col_exists('hotels', 'dining_has_garden_tables', $pdo),
    'dining_total_garden_tables' => ha_col_exists('hotels', 'dining_total_garden_tables', $pdo),
    'dining_has_bar_tables' => ha_col_exists('hotels', 'dining_has_bar_tables', $pdo),
    'dining_total_bar_tables' => ha_col_exists('hotels', 'dining_total_bar_tables', $pdo),
    'dining_has_rooms' => ha_col_exists('hotels', 'dining_has_rooms', $pdo),
    'dining_total_rooms' => ha_col_exists('hotels', 'dining_total_rooms', $pdo),
    'dining_room_labels' => ha_col_exists('hotels', 'dining_room_labels', $pdo),
    'dining_has_ac_tables' => ha_col_exists('hotels', 'dining_has_ac_tables', $pdo),
    'dining_total_ac_tables' => ha_col_exists('hotels', 'dining_total_ac_tables', $pdo),
    'dining_has_counter' => ha_col_exists('hotels', 'dining_has_counter', $pdo),
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
    $servicePct = max(0, min(30, (float) ($_POST['service_charge_percent'] ?? 0)));
    $tableCount = max(1, min(200, (int) ($_POST['dining_total_tables'] ?? 12)));
    $hasTents = !empty($_POST['dining_has_tents']) ? 1 : 0;
    $tents = max(0, min(100, (int) ($_POST['dining_total_tents'] ?? 0)));
    $hasGarden = !empty($_POST['dining_has_garden_tables']) ? 1 : 0;
    $garden = max(0, min(100, (int) ($_POST['dining_total_garden_tables'] ?? 0)));
    $hasBar = !empty($_POST['dining_has_bar_tables']) ? 1 : 0;
    $bar = max(0, min(100, (int) ($_POST['dining_total_bar_tables'] ?? 0)));
    $hasRooms = !empty($_POST['dining_has_rooms']) ? 1 : 0;
    $rooms = max(0, min(50, (int) ($_POST['dining_total_rooms'] ?? 0)));
    $hasAc = !empty($_POST['dining_has_ac_tables']) ? 1 : 0;
    $ac = max(0, min(100, (int) ($_POST['dining_total_ac_tables'] ?? 0)));
    $hasCounter = !empty($_POST['dining_has_counter']) ? 1 : 0;
    $roomLabels = [];
    if ($hasRooms) {
        if (isset($_POST['room_label']) && is_array($_POST['room_label'])) {
            foreach ($_POST['room_label'] as $num => $label) {
                $n = (int) $num;
                if ($n < 1 || $n > $rooms) {
                    continue;
                }
                $lab = trim((string) $label);
                if ($lab !== '') {
                    $roomLabels[(string) $n] = $lab;
                }
            }
        }
        for ($i = 1; $i <= $rooms; $i++) {
            $key = 'room_label_' . $i;
            if (!isset($_POST[$key])) {
                continue;
            }
            $lab = trim((string) $_POST[$key]);
            if ($lab !== '') {
                $roomLabels[(string) $i] = $lab;
            }
        }
    }

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
    } elseif ($gstEnabled && $cols['gst_number'] && $gstNumber === '') {
        $error = 'GST Number is required when GST is enabled';
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
            if ($cols['dining_has_tents']) {
                $sets[] = 'dining_has_tents = :has_tents';
                $params[':has_tents'] = $hasTents;
            }
            if ($cols['dining_total_tents']) {
                $sets[] = 'dining_total_tents = :tents';
                $params[':tents'] = $hasTents ? $tents : 0;
            }
            if ($cols['dining_has_garden_tables']) {
                $sets[] = 'dining_has_garden_tables = :has_garden';
                $params[':has_garden'] = $hasGarden;
            }
            if ($cols['dining_total_garden_tables']) {
                $sets[] = 'dining_total_garden_tables = :garden';
                $params[':garden'] = $hasGarden ? $garden : 0;
            }
            if ($cols['dining_has_bar_tables']) {
                $sets[] = 'dining_has_bar_tables = :has_bar';
                $params[':has_bar'] = $hasBar;
            }
            if ($cols['dining_total_bar_tables']) {
                $sets[] = 'dining_total_bar_tables = :bar';
                $params[':bar'] = $hasBar ? $bar : 0;
            }
            if ($cols['dining_has_rooms']) {
                $sets[] = 'dining_has_rooms = :has_rooms';
                $params[':has_rooms'] = $hasRooms;
            }
            if ($cols['dining_total_rooms']) {
                $sets[] = 'dining_total_rooms = :rooms';
                $params[':rooms'] = $hasRooms ? $rooms : 0;
            }
            if ($cols['dining_room_labels']) {
                $sets[] = 'dining_room_labels = :room_labels';
                $params[':room_labels'] = $hasRooms && $roomLabels !== [] ? json_encode($roomLabels) : null;
            }
            if ($cols['dining_has_ac_tables']) {
                $sets[] = 'dining_has_ac_tables = :has_ac';
                $params[':has_ac'] = $hasAc;
            }
            if ($cols['dining_total_ac_tables']) {
                $sets[] = 'dining_total_ac_tables = :ac';
                $params[':ac'] = $hasAc ? $ac : 0;
            }
            if ($cols['dining_has_counter']) {
                $sets[] = 'dining_has_counter = :has_counter';
                $params[':has_counter'] = $hasCounter;
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

ha_layout_start('Hotel settings', 'hotel-settings.php', 'Profile, location, GST, dining floor, and kitchen');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash-error"><?= ha_h($error) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2>Hotel settings</h2>
    <p class="sub">Profile, location, GST, dining floor, and kitchen</p>
  </div>
</div>

<form method="post" class="space-y-4 max-w-3xl">
  <div class="card">
    <div class="card-header">
      <h3>Profile</h3>
      <button class="btn sm" type="submit"><span class="material-symbols-outlined text-[16px]">save</span> Save</button>
    </div>
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
    <div class="card-header">
      <h3>Location</h3>
      <button class="btn sm" type="submit"><span class="material-symbols-outlined text-[16px]">save</span> Save</button>
    </div>
    <p class="muted !mt-0 mb-3">Visible and editable. Used for nearby restaurant discovery in the app.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
      <div><label>Latitude</label><input class="input" type="number" step="any" name="latitude" id="inputLat" value="<?= ha_h($latVal) ?>" placeholder="22.3072"></div>
      <div><label>Longitude</label><input class="input" type="number" step="any" name="longitude" id="inputLng" value="<?= ha_h($lngVal) ?>" placeholder="73.1812"></div>
    </div>
    <div class="flex flex-wrap gap-2 mb-2">
      <button type="button" class="btn secondary sm" onclick="useMyLocation()">
        <span class="material-symbols-outlined text-[18px]">my_location</span> Use current location
      </button>
      <?php if ($mapUrl): ?>
        <a class="btn secondary sm" href="<?= ha_h($mapUrl) ?>" target="_blank" rel="noopener">
          <span class="material-symbols-outlined text-[18px]">map</span> Open in map
        </a>
      <?php endif; ?>
    </div>
    <p class="muted">Paste coordinates from Google Maps (right-click → coordinates) or OpenStreetMap.</p>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Kitchen & listing</h3>
      <button class="btn sm" type="submit"><span class="material-symbols-outlined text-[16px]">save</span> Save</button>
    </div>
    <p class="muted mb-3">
      Prep time is automatic: average of the last 5 completed orders (accept → ready).
      Current estimate: <strong><?= (int) $prepVal ?> min</strong>
      (default <?= (int) FM_DEFAULT_PREP_MINS ?> until 5 samples). Used for customer ETA.
    </p>
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

  <?php
  $diningUiAvailable = $cols['dining_total_tables']
      || $cols['dining_has_tents']
      || $cols['dining_has_garden_tables']
      || $cols['dining_has_bar_tables']
      || $cols['dining_has_rooms']
      || $cols['dining_has_ac_tables'];
  $roomLabelsSaved = [];
  if (!empty($hotel['dining_room_labels'])) {
      $decodedLabels = is_array($hotel['dining_room_labels'])
          ? $hotel['dining_room_labels']
          : json_decode((string) $hotel['dining_room_labels'], true);
      if (is_array($decodedLabels)) {
          $roomLabelsSaved = $decodedLabels;
      }
  }
  $roomsCountUi = max(0, (int) ($hotel['dining_total_rooms'] ?? 0));
  ?>
  <?php if ($diningUiAvailable): ?>
  <div class="card">
    <div class="card-header">
      <h3>Dining settings</h3>
      <button class="btn sm" type="submit"><span class="material-symbols-outlined text-[16px]">save</span> Save</button>
    </div>
    <p class="muted !mt-0 mb-4">Controls the POS floor map sections (tables, tents, garden, bar, rooms, AC).</p>

    <?php if ($cols['dining_total_tables']): ?>
    <div class="mb-4">
      <label>Total tables <span class="text-red-500">*</span></label>
      <input class="input" type="number" min="1" max="200" name="dining_total_tables" value="<?= (int)($hotel['dining_total_tables'] ?? 12) ?>" required>
    </div>
    <?php endif; ?>

    <div class="space-y-3">
      <?php if ($cols['dining_has_rooms']):
          $hasRoomsUi = !empty($hotel['dining_has_rooms']);
      ?>
      <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
        <div class="flex items-center justify-between gap-3 mb-2">
          <div class="flex items-center gap-2 font-semibold text-sm text-gray-800">
            <span class="material-symbols-outlined text-text-muted text-[20px]">meeting_room</span> Private rooms
          </div>
          <label class="!mb-0 inline-flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="dining_has_rooms" value="1" id="toggleRooms" <?= $hasRoomsUi ? 'checked' : '' ?> onchange="toggleDiningBlock('roomsBlock', this.checked)">
            Enable
          </label>
        </div>
        <div id="roomsBlock" class="<?= $hasRoomsUi ? '' : 'hidden' ?> space-y-3">
          <?php if ($cols['dining_total_rooms']): ?>
          <div>
            <label class="!text-xs">Number of rooms</label>
            <input class="input" type="number" min="0" max="50" name="dining_total_rooms" id="dining_total_rooms_input" value="<?= $roomsCountUi ?>" onchange="updateRoomLabelInputs(this.value)">
          </div>
          <?php endif; ?>
          <?php if ($cols['dining_room_labels']): ?>
          <div class="border-t border-gray-200 pt-3">
            <p class="text-xs font-semibold text-gray-700 mb-2">Custom room labels (optional)</p>
            <div id="roomLabelsContainer" class="space-y-2 max-h-60 overflow-y-auto">
              <?php for ($i = 1; $i <= $roomsCountUi; $i++):
                  $lab = (string) ($roomLabelsSaved[(string) $i] ?? $roomLabelsSaved[$i] ?? '');
              ?>
              <div class="flex items-center gap-2 room-label-row">
                <span class="text-xs font-medium text-gray-600 w-16 shrink-0">Room <?= $i ?>:</span>
                <input class="input !mb-0" type="text" name="room_label[<?= $i ?>]" value="<?= ha_h($lab) ?>" maxlength="50" placeholder="e.g. Deluxe Suite">
              </div>
              <?php endfor; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php
      $diningToggles = [
          ['has' => 'dining_has_tents', 'count' => 'dining_total_tents', 'id' => 'tents', 'symbol' => 'camping', 'title' => 'Outdoor tents', 'countLabel' => 'Number of tents'],
          ['has' => 'dining_has_ac_tables', 'count' => 'dining_total_ac_tables', 'id' => 'ac', 'symbol' => 'ac_unit', 'title' => 'AC tables', 'countLabel' => 'Number of AC tables'],
          ['has' => 'dining_has_garden_tables', 'count' => 'dining_total_garden_tables', 'id' => 'garden', 'symbol' => 'yard', 'title' => 'Garden tables', 'countLabel' => 'Number of garden tables'],
          ['has' => 'dining_has_bar_tables', 'count' => 'dining_total_bar_tables', 'id' => 'bar', 'symbol' => 'local_bar', 'title' => 'Bar tables', 'countLabel' => 'Number of bar tables'],
      ];
      foreach ($diningToggles as $tg):
          if (!$cols[$tg['has']]) {
              continue;
          }
          $on = !empty($hotel[$tg['has']]);
          $cnt = (int) ($hotel[$tg['count']] ?? 0);
      ?>
      <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
        <div class="flex items-center justify-between gap-3 mb-2">
          <div class="flex items-center gap-2 font-semibold text-sm text-gray-800">
            <span class="material-symbols-outlined text-text-muted text-[20px]"><?= ha_h($tg['symbol']) ?></span> <?= ha_h($tg['title']) ?>
          </div>
          <label class="!mb-0 inline-flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="<?= ha_h($tg['has']) ?>" value="1" <?= $on ? 'checked' : '' ?> onchange="toggleDiningBlock('<?= ha_h($tg['id']) ?>Block', this.checked)">
            Enable
          </label>
        </div>
        <?php if ($cols[$tg['count']]): ?>
        <div id="<?= ha_h($tg['id']) ?>Block" class="<?= $on ? '' : 'hidden' ?>">
          <label class="!text-xs"><?= ha_h($tg['countLabel']) ?></label>
          <input class="input" type="number" min="0" max="100" name="<?= ha_h($tg['count']) ?>" value="<?= $cnt ?>">
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php if ($cols['dining_has_counter']): ?>
      <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 font-semibold text-sm text-gray-800">
            <span class="material-symbols-outlined text-text-muted text-[20px]">point_of_sale</span> Counter / walk-in
          </div>
          <label class="!mb-0 inline-flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="dining_has_counter" value="1" <?= !empty($hotel['dining_has_counter']) ? 'checked' : '' ?>>
            Enable
          </label>
        </div>
        <p class="muted !mb-0 mt-2 text-xs">Reserved for future counter billing on the floor map.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($cols['gst_enabled'] || $cols['gst_percent'] || $cols['gst_number'] || $cols['service_charge_percent']):
      $gstOnSettings = !empty($hotel['gst_enabled']);
  ?>
  <div class="card">
    <div class="card-header">
      <h3><span class="material-symbols-outlined text-[18px] text-primary align-middle mr-1">percent</span> Tax management</h3>
      <button class="btn sm" type="submit"><span class="material-symbols-outlined text-[16px]">save</span> Save</button>
    </div>

    <div class="space-y-5">
      <?php if ($cols['gst_enabled']): ?>
      <div class="flex items-center justify-between gap-3">
        <div>
          <h4 class="text-sm font-bold text-gray-900 !mb-0.5">Enable GST</h4>
          <p class="text-xs text-gray-500 !mb-0">Turn on GST to automatically add tax to menu prices and generate GST invoices</p>
        </div>
        <label class="!mb-0 inline-flex items-center gap-2 text-sm font-medium cursor-pointer">
          <input type="checkbox" name="gst_enabled" value="1" id="toggleGst" <?= $gstOnSettings ? 'checked' : '' ?> onchange="toggleDiningBlock('gstInput', this.checked)">
          Enable
        </label>
      </div>
      <?php endif; ?>

      <div id="gstInput" class="<?= $gstOnSettings || !$cols['gst_enabled'] ? '' : 'hidden' ?> space-y-4">
        <?php if ($cols['gst_percent']): ?>
        <div>
          <label>GST Percentage <span class="text-red-500">*</span></label>
          <div class="relative max-w-xs">
            <input class="input !pr-8" type="number" step="0.01" min="0" max="50" name="gst_percent" value="<?= ha_h((string)($hotel['gst_percent'] ?? '5.00')) ?>">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-sm">%</span>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($cols['gst_number']): ?>
        <div>
          <label>GST Number (GSTIN)</label>
          <input class="input max-w-md" name="gst_number" maxlength="15" value="<?= ha_h($hotel['gst_number'] ?? '') ?>" placeholder="22AAAAA0000A1Z5">
          <p class="muted !mb-0 text-xs">Printed on customer bills when GST is enabled</p>
        </div>
        <?php endif; ?>
        <p class="muted !mb-0 text-xs">Common GST rates: 5%, 12%, 18%, 28%</p>
      </div>

      <hr class="border-gray-100">

      <?php if ($cols['service_charge_percent']): ?>
      <div>
        <label>Service Charge (%)</label>
        <div class="relative max-w-xs">
          <input class="input !pr-8" type="number" step="0.01" min="0" max="30" name="service_charge_percent" value="<?= ha_h((string)($hotel['service_charge_percent'] ?? '0')) ?>" placeholder="0.00">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-sm">%</span>
        </div>
        <p class="muted !mb-0 text-xs">Percentage between 0% – 30% applied to bill subtotal after discount</p>
      </div>
      <?php endif; ?>

      <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
        <h5 class="flex items-center gap-2 text-sm font-bold text-blue-800 !mb-2">
          <span class="material-symbols-outlined text-[18px]">info</span> How GST Works
        </h5>
        <ul class="text-xs text-blue-700 space-y-1.5 list-disc list-inside !mb-0">
          <li>GST is added only on items marked “GST excluded” (inclusive items skip tax)</li>
          <li>Bills show Taxable Amount + CGST + SGST separately</li>
          <li>Final total includes GST and service charge</li>
          <li>Example: ₹100 item + 5% GST = ₹105 total</li>
        </ul>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($cols['operating_hours']): ?>
  <div class="card">
    <div class="card-header">
      <h3>Opening hours</h3>
      <button class="btn sm" type="submit"><span class="material-symbols-outlined text-[16px]">save</span> Save</button>
    </div>
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
function toggleDiningBlock(id, on){
  var el = document.getElementById(id);
  if (!el) return;
  if (on) el.classList.remove('hidden');
  else el.classList.add('hidden');
}
function updateRoomLabelInputs(count){
  var container = document.getElementById('roomLabelsContainer');
  if (!container) return;
  count = Math.max(0, Math.min(50, parseInt(count, 10) || 0));
  var existing = {};
  container.querySelectorAll('input[name^="room_label"]').forEach(function(inp){
    var m = inp.name.match(/\[(\d+)\]/);
    if (m) existing[m[1]] = inp.value;
  });
  container.innerHTML = '';
  for (var i = 1; i <= count; i++) {
    var row = document.createElement('div');
    row.className = 'flex items-center gap-2 room-label-row';
    row.innerHTML = '<span class="text-xs font-medium text-gray-600 w-16 shrink-0">Room ' + i + ':</span>'
      + '<input class="input !mb-0" type="text" name="room_label[' + i + ']" value="'
      + (existing[String(i)] || '').replace(/"/g, '&quot;')
      + '" maxlength="50" placeholder="e.g. Deluxe Suite">';
    container.appendChild(row);
  }
}
</script>
<?php ha_layout_end(); ?>
