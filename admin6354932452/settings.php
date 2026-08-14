<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/Settings.php';
sa_require_login();

$pdo = admin_db();
$flash = '';
$error = '';

function sa_settings_has(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'admin_settings\' AND COLUMN_NAME = :c'
    );
    $stmt->execute([':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commission = (float) ($_POST['delivery_commission_percent'] ?? 3);
    $radius = (float) ($_POST['max_delivery_radius_km'] ?? 10);
    $partnerRadius = (float) ($_POST['default_partner_radius_km'] ?? 5);
    $partnerEarn = (float) ($_POST['partner_earn_fixed'] ?? 10);
    $codHold = !empty($_POST['cod_hold_enabled']) ? 1 : 0;
    $offerTtl = (int) ($_POST['offer_ttl_seconds'] ?? 60);
    $supportPhone = preg_replace('/\D+/', '', (string) ($_POST['delivery_support_phone'] ?? '')) ?? '';
    $adminContact = preg_replace('/\D+/', '', (string) ($_POST['admin_contact_number'] ?? '')) ?? '';
    $paymentQr = trim((string) ($_POST['payment_qr_url'] ?? ''));
    $maintenance = !empty($_POST['maintenance_mode_delivery']) ? 1 : 0;
    $minAndroid = trim((string) ($_POST['delivery_app_min_version_android'] ?? '1.0.0'));
    $minIos = trim((string) ($_POST['delivery_app_min_version_ios'] ?? '1.0.0'));
    $dlAndroid = trim((string) ($_POST['delivery_app_download_url_android'] ?? ''));
    $dlIos = trim((string) ($_POST['delivery_app_download_url_ios'] ?? ''));

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

    try {
        $sets = [
            'delivery_commission_percent = :c',
            'max_delivery_radius_km = :r',
            'default_partner_radius_km = :pr',
            'partner_earn_fixed = :pe',
            'cod_hold_enabled = :cod',
            'offer_ttl_seconds = :ttl',
            'delivery_charges_config = :cfg',
        ];
        $params = [
            ':c' => $commission,
            ':r' => $radius,
            ':pr' => $partnerRadius,
            ':pe' => $partnerEarn,
            ':cod' => $codHold,
            ':ttl' => $offerTtl,
            ':cfg' => json_encode($ranges),
        ];

        $optional = [
            'platform_partner_per_order_revenue' => [':ppr', $partnerEarn],
            'delivery_support_phone' => [':sp', $supportPhone],
            'admin_contact_number' => [':ac', $adminContact],
            'payment_qr_url' => [':qr', $paymentQr !== '' ? $paymentQr : null],
            'maintenance_mode_delivery' => [':mm', $maintenance],
            'delivery_app_min_version_android' => [':va', $minAndroid !== '' ? $minAndroid : '1.0.0'],
            'delivery_app_min_version_ios' => [':vi', $minIos !== '' ? $minIos : '1.0.0'],
            'delivery_app_download_url_android' => [':da', $dlAndroid !== '' ? $dlAndroid : null],
            'delivery_app_download_url_ios' => [':di', $dlIos !== '' ? $dlIos : null],
        ];
        foreach ($optional as $col => [$bind, $val]) {
            if (sa_settings_has($pdo, $col)) {
                $sets[] = "$col = $bind";
                $params[$bind] = $val;
            }
        }

        $pdo->prepare('UPDATE admin_settings SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
        Settings::refresh();
        $flash = 'Settings saved';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$s = Settings::get();
$config = $s['delivery_charges_config'] ?? [];
if (!is_array($config) || !$config) {
    $config = [['from_km' => 0, 'to_km' => 10, 'charge' => 29]];
}

sa_layout_start('Settings', 'settings.php', 'Commission, radius, delivery app & support');
if ($flash): ?><div class="flash"><?= sa_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error"><?= sa_h($error) ?></div><?php endif; ?>
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
      <label>Partner earn rate (₹ per km)</label>
      <input class="input" type="number" step="0.01" min="0" name="partner_earn_fixed" value="<?= sa_h((string)(($s['platform_partner_per_order_revenue'] ?? 0) > 0 ? $s['platform_partner_per_order_revenue'] : ($s['partner_earn_fixed'] ?? 10))) ?>">
      <p class="muted !-mt-2 mb-3">Same as EatnSay: payout = hotel→customer km × this rate. Not the customer delivery fee.</p>
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

  <h3>Delivery partner app</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div>
      <label>Support phone (shown in partner app)</label>
      <input class="input" name="delivery_support_phone" maxlength="15" value="<?= sa_h((string)($s['delivery_support_phone'] ?? '')) ?>" placeholder="98XXXXXXXX">
    </div>
    <div>
      <label>Admin contact (maintenance screen)</label>
      <input class="input" name="admin_contact_number" maxlength="15" value="<?= sa_h((string)($s['admin_contact_number'] ?? '')) ?>" placeholder="98XXXXXXXX">
    </div>
    <div class="sm:col-span-2">
      <label>Payment QR URL (COD pay-online at drop)</label>
      <input class="input" name="payment_qr_url" value="<?= sa_h((string)($s['payment_qr_url'] ?? '')) ?>" placeholder="https://...">
    </div>
    <div>
      <label>Min Android app version</label>
      <input class="input" name="delivery_app_min_version_android" value="<?= sa_h((string)($s['delivery_app_min_version_android'] ?? '1.0.0')) ?>">
    </div>
    <div>
      <label>Min iOS app version</label>
      <input class="input" name="delivery_app_min_version_ios" value="<?= sa_h((string)($s['delivery_app_min_version_ios'] ?? '1.0.0')) ?>">
    </div>
    <div>
      <label>Android download URL</label>
      <input class="input" name="delivery_app_download_url_android" value="<?= sa_h((string)($s['delivery_app_download_url_android'] ?? '')) ?>">
    </div>
    <div>
      <label>iOS download URL</label>
      <input class="input" name="delivery_app_download_url_ios" value="<?= sa_h((string)($s['delivery_app_download_url_ios'] ?? '')) ?>">
    </div>
  </div>
  <label class="!mb-6 flex items-center gap-2 font-medium text-sm text-gray-700">
    <input type="checkbox" name="maintenance_mode_delivery" value="1" <?= !empty($s['maintenance_mode_delivery']) ? 'checked' : '' ?> class="rounded border-gray-300 text-primary">
    Put delivery partner app in maintenance mode
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
