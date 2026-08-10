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

sa_layout_start('Settings', 'settings.php');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<form method="post" class="card">
  <h3 style="margin-top:0">Commission & radius</h3>
  <label>Commission %</label>
  <input class="input" type="number" step="0.01" name="delivery_commission_percent" value="<?= sa_h((string)$s['delivery_commission_percent']) ?>">
  <label>Max delivery / nearby hotel radius (km)</label>
  <input class="input" type="number" step="0.1" name="max_delivery_radius_km" value="<?= sa_h((string)$s['max_delivery_radius_km']) ?>">
  <p class="muted">Users only see hotels within this radius of their GPS.</p>
  <label>Default delivery partner service radius (km)</label>
  <input class="input" type="number" step="0.1" name="default_partner_radius_km" value="<?= sa_h((string)$s['default_partner_radius_km']) ?>">
  <label>Partner earn fixed (₹ per order)</label>
  <input class="input" type="number" step="0.01" name="partner_earn_fixed" value="<?= sa_h((string)($s['partner_earn_fixed'] ?? 30)) ?>">
  <label>Offer TTL seconds</label>
  <input class="input" type="number" name="offer_ttl_seconds" value="<?= sa_h((string)($s['offer_ttl_seconds'] ?? 60)) ?>">
  <label><input type="checkbox" name="cod_hold_enabled" value="1" <?= !empty($s['cod_hold_enabled']) ? 'checked' : '' ?>> Enable COD hold on partners</label>

  <h3>Delivery charge by km range</h3>
  <div id="ranges">
    <?php foreach ($config as $i => $row): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
        <div><label>From km</label><input class="input" name="from_km[]" type="number" step="0.1" value="<?= sa_h((string)($row['from_km'] ?? 0)) ?>"></div>
        <div><label>To km</label><input class="input" name="to_km[]" type="number" step="0.1" value="<?= sa_h((string)($row['to_km'] ?? 0)) ?>"></div>
        <div><label>Charge ₹</label><input class="input" name="charge[]" type="number" step="0.01" value="<?= sa_h((string)($row['charge'] ?? 0)) ?>"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn secondary" onclick="addRange()">Add range</button>
  <div style="margin-top:16px"><button class="btn" type="submit">Save settings</button></div>
</form>
<script>
function addRange(){
  const wrap=document.getElementById('ranges');
  const div=document.createElement('div');
  div.style.cssText='display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px';
  div.innerHTML='<div><label>From km</label><input class="input" name="from_km[]" type="number" step="0.1" value="0"></div><div><label>To km</label><input class="input" name="to_km[]" type="number" step="0.1" value="10"></div><div><label>Charge ₹</label><input class="input" name="charge[]" type="number" step="0.01" value="29"></div>';
  wrap.appendChild(div);
}
</script>
<?php sa_layout_end(); ?>
