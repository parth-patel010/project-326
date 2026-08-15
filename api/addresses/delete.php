<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

ensure_addresses_table();
$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));
$id = trim((string) ($body['id'] ?? ''));
if (strlen($phone) !== 10 || $id === '') {
    fail('phone and id required');
}
$user = find_user_by_phone($phone);
if (!$user) {
    fail('User not found', 404);
}

$stmt = db()->prepare(
    'DELETE FROM user_addresses WHERE public_id = :id AND user_id = :u'
);
$stmt->execute([':id' => $id, ':u' => $user['id']]);

respond(['ok' => true, 'deleted' => $stmt->rowCount() > 0]);
