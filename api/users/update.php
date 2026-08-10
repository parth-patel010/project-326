<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$id = trim((string) ($body['id'] ?? ''));
$phone = normalize_phone((string) ($body['phone'] ?? ''));

$user = null;
if ($id !== '') {
    $user = find_user_by_public_id($id);
} elseif ($phone !== '') {
    $user = find_user_by_phone($phone);
}

if (!$user) {
    fail('User not found', 404);
}

$name = array_key_exists('name', $body) ? trim((string) $body['name']) : (string) $user['name'];
$email = array_key_exists('email', $body)
    ? (trim((string) $body['email']) !== '' ? trim((string) $body['email']) : null)
    : $user['email'];
$avatar = array_key_exists('avatar_url', $body)
    ? (trim((string) $body['avatar_url']) !== '' ? trim((string) $body['avatar_url']) : null)
    : $user['avatar_url'];

db()->prepare(
    'UPDATE users SET name = :name, email = :email, avatar_url = :avatar WHERE id = :id'
)->execute([
    ':name' => $name,
    ':email' => $email,
    ':avatar' => $avatar,
    ':id' => $user['id'],
]);

$updated = find_user_by_public_id((string) $user['public_id']);

respond([
    'ok' => true,
    'user' => present_user($updated),
]);
