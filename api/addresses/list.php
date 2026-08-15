<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

ensure_addresses_table();
$user = addresses_require_user_from_request();

$stmt = db()->prepare(
    'SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC'
);
$stmt->execute([':u' => $user['id']]);
$rows = $stmt->fetchAll();

respond([
    'ok' => true,
    'addresses' => array_map('present_address', $rows),
]);
