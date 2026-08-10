<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$held = db()->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM cod_holds WHERE partner_id = :id AND status = 'held'"
);
$held->execute([':id' => $partner['id']]);

respond([
    'ok' => true,
    'wallet' => [
        'earnings_total' => (float) $partner['earnings_total'],
        'orders_completed' => (int) $partner['orders_completed'],
        'cod_wallet' => (float) $partner['cod_wallet'],
        'cod_held' => (float) $held->fetchColumn(),
    ],
    'partner' => present_partner($partner),
]);
