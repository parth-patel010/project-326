<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../lib/UserPush.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));
$token = trim((string) ($body['push_token'] ?? ''));

if (strlen($phone) !== 10) {
    fail('Valid 10-digit phone required');
}

$user = find_user_by_phone($phone);
if (!$user) {
    respond(['ok' => true, 'success' => true]);
}

UserPush::unregisterToken((int) $user['id'], $token !== '' ? $token : null);

respond(['ok' => true, 'success' => true]);
