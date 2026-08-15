<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));
$password = (string) ($body['password'] ?? '');

if (strlen($phone) !== 10 || $password === '') {
    fail('phone and password required');
}

$user = find_user_by_phone($phone);
if (!$user || empty($user['password_hash'])) {
    fail('Invalid credentials', 401);
}
if (!password_verify($password, (string) $user['password_hash'])) {
    fail('Invalid credentials', 401);
}
if (empty($user['is_active'])) {
    fail('Account inactive', 403);
}

db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
    ->execute([':id' => $user['id']]);

respond([
    'ok' => true,
    'user' => present_user(find_user_by_phone($phone)),
]);
