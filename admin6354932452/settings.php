<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/Settings.php';
sa_require_login();

$pdo = admin_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commission = (float) ($_POST['delivery_commission_percent'] ?? 3);
    $radius = (float) ($_POST['max_delivery_radius_km'] ?? 10);
    $partnerRadius = (float) ($_POST['default_partner_radius_km'] ?? 5);
    $partnerEarn = (float) ($_POST['partner_earn_fixed'] ?? 30);
    $codHold = !empty($_POST['cod_hold_enabled']) ? 1 : 0;
    $offerTtl = (int) ($_POST['offer_ttl_seconds'] ?? 60);

    $ranges = [];
    $from = $_POST['from_km'] ?? [];
    $to = $_POST['to_km'] ?? [];
    $charge = $_POST['charge'] ?? [];
    if (is_array($from)) {
        foreach ($from as $i => $f) {
            $ranges[] = [
                'from_km' => (float) $f,
                'to_km' => (float) ($to[$i] ?? 0),
                'charge' => (float) ($charge[$i] ?? 0),
            ];
        }
    }

    $pdo->prepare(
        'UPDATE admin_settings SET
          delivery_commission_percent = :c,
          max_delivery_radius_km = :r,
          default_partner_radius_km = :pr,
          partner_earn_fixed = :pe,
          cod_hold_enabled = :cod,
          offer_ttl_seconds = :ttl,
          delivery_charges_config = :cfg
         WHERE id = 1'
    )->execute([
        ':c' => $commission,
        ':r' => $radius,
        ':pr' => $partnerRadius,
        ':pe' => $partnerEarn,
        ':cod' => $codHold,
        ':ttl' => $offerTtl,
        ':cfg' => json_encode($ranges),
    ]);
    Settings::refresh();
    $flash = 'Settings saved';
}

$s = Settings::get();
$config = $s['delivery_charges_config'] ?? [];
if (!is_array($config) || !$config) {
    $config = [['from_km' => 0, 'to_km' => 10, 'charge' => 29]];
}

sa_layout_start('Settings', 'settings.php', 'Commission, radius, and delivery charges');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<form method="post" class="card max-w-3xl">
  <h3>Commission & radius</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div>
      <label>Commission %</label>
      <input class="input" type="number" step="0.01" name="delivery_commission_percent" value="<?= sa_h((string)$s['delivery_commission_percent']) ?>">
    </div>
    <div>
      <label>Max delivery / nearby hotel radius (km)</label>
      <input class="input" type="number" step="0.1" name="max_delivery_radius_km" value="<?= sa_h((string)$s['max_delivery_radius_km']) ?>">
      <p class="muted !-mt-2 mb-3">Users only see hotels within this radius of their GPS.</p>
    </div>
    <div>
      <label>Default partner service radius (km)</label>
      <input class="input" type="number" step="0.1" name="default_partner_radius_km" value="<?= sa_h((string)$s['default_partner_radius_km']) ?>">
    </div>
    <div>
      <label>Partner earn fixed (₹ per order)</label>
      <input class="input" type="number" step="0.01" name="partner_earn_fixed" value="<?= sa_h((string)($s['partner_earn_fixed'] ?? 30)) ?>">
    </div>
    <div>
      <label>Offer TTL seconds</label>
      <input class="input" type="number" name="offer_ttl_seconds" value="<?= sa_h((string)($s['offer_ttl_seconds'] ?? 60)) ?>">
    </div>
  </div>
  <label class="!mb-4 flex items-center gap-2 font-medium text-sm text-gray-700">
    <input type="checkbox" name="cod_hold_enabled" value="1" <?= !empty($s['cod_hold_enabled']) ? 'checked' : '' ?> class="rounded border-gray-300 text-primary">
    Enable COD hold on partners
  </label>

  <h3>Delivery charge by km range</h3>
  <div id="ranges" class="space-y-3 mb-3">
    <?php foreach ($config as $i => $row): ?>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
        <div><label>From km</label><input class="input !mb-0" name="from_km[]" type="number" step="0.1" value="<?= sa_h((string)($row['from_km'] ?? 0)) ?>"></div>
        <div><label>To km</label><input class="input !mb-0" name="to_km[]" type="number" step="0.1" value="<?= sa_h((string)($row['to_km'] ?? 0)) ?>"></div>
        <div><label>Charge ₹</label><input class="input !mb-0" name="charge[]" type="number" step="0.01" value="<?= sa_h((string)($row['charge'] ?? 0)) ?>"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="flex flex-wrap gap-3">
    <button type="button" class="btn secondary" onclick="addRange()">
      <span class="material-icons-outlined text-[18px]">add</span> Add range
    </button>
    <button class="btn" type="submit">
      <span class="material-icons-outlined text-[18px]">save</span> Save settings
    </button>
  </div>
</form>
<script>
function addRange(){
  const wrap=document.getElementById('ranges');
  const div=document.createElement('div');
  div.className='grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100';
  div.innerHTML='<div><label>From km</label><input class="input !mb-0" name="from_km[]" type="number" step="0.1" value="0"></div><div><label>To km</label><input class="input !mb-0" name="to_km[]" type="number" step="0.1" value="10"></div><div><label>Charge ₹</label><input class="input !mb-0" name="charge[]" type="number" step="0.01" value="29"></div>';
  wrap.appendChild(div);
}
</script>
<?php sa_layout_end(); ?>
