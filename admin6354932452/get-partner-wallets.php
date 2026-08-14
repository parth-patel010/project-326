<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/PartnerEarning.php';

header('Content-Type: application/json');

if (empty($_SESSION['sa_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$partnerId = (int) ($_GET['partner_id'] ?? 0);
if ($partnerId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid partner ID']);
    exit;
}

try {
    $pdo = admin_db();
    PartnerEarning::ensureEarnWalletColumn($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, full_name, phone, COALESCE(cod_wallet, 0) AS cod_wallet, COALESCE(earn_wallet, 0) AS earn_wallet
         FROM delivery_partners WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $partnerId]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Partner not found']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'partner' => [
            'id' => (int) $row['id'],
            'full_name' => (string) ($row['full_name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
        ],
        'wallets' => [
            'earn_wallet' => round((float) $row['earn_wallet'], 2),
            'cod_wallet' => round((float) $row['cod_wallet'], 2),
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
