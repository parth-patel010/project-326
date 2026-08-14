<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/PartnerEarning.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
PartnerEarning::ensureEarnWalletColumn(db());
$held = db()->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM cod_holds WHERE partner_id = :id AND status = 'held'"
);
$held->execute([':id' => $partner['id']]);
$earnWallet = PartnerEarning::getEarnWallet(db(), (int) $partner['id']);

respond([
    'ok' => true,
    'wallet' => [
        'earnings_total' => (float) ($partner['earnings_total'] ?? $earnWallet),
        'earn_wallet' => $earnWallet,
        'orders_completed' => (int) $partner['orders_completed'],
        'cod_wallet' => (float) $partner['cod_wallet'],
        'cod_held' => (float) $held->fetchColumn(),
    ],
    'stats' => [
        'total_earning' => (float) ($partner['earnings_total'] ?? $earnWallet),
        'earn_wallet' => $earnWallet,
        'total_orders' => (int) $partner['orders_completed'],
        'cod_cash' => (float) $partner['cod_wallet'],
        'cod_cash_limit' => 1000,
    ],
    'partner' => present_partner($partner),
]);
