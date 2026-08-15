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
$platform = strtolower(trim((string) ($body['platform'] ?? 'android')));
$deviceId = trim((string) ($body['device_id'] ?? ''));
$client = trim((string) ($body['client'] ?? 'eas'));

if (strlen($phone) !== 10) {
    fail('Valid 10-digit phone required');
}
if ($token === '' || strlen($token) < 20) {
    fail('push_token required');
}

$user = find_user_by_phone($phone);
if (!$user) {
    fail('User not found', 404);
}
if (empty($user['is_active'])) {
    fail('Account inactive', 403);
}

UserPush::registerToken(
    (int) $user['id'],
    $token,
    $platform,
    $client !== '' ? $client : 'eas',
    $deviceId !== '' ? $deviceId : null
);

respond(['ok' => true, 'success' => true]);
