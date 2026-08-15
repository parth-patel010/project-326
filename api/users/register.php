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
$name = trim((string) ($body['name'] ?? ''));

if (strlen($phone) !== 10) {
    fail('Valid 10-digit phone required');
}
if (strlen($password) < 4) {
    fail('Password must be at least 4 characters');
}

$existing = find_user_by_phone($phone);
if ($existing && !empty($existing['password_hash'])) {
    fail('Account already exists. Please login.', 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    if ($existing) {
        db()->prepare(
            'UPDATE users SET password_hash = :h, name = COALESCE(NULLIF(:n, \'\'), name), last_login_at = NOW() WHERE id = :id'
        )->execute([
            ':h' => $hash,
            ':n' => $name,
            ':id' => $existing['id'],
        ]);
        $user = find_user_by_phone($phone);
    } else {
        $publicId = public_user_id();
        db()->prepare(
            'INSERT INTO users (public_id, phone, name, password_hash, last_login_at)
             VALUES (:pid, :phone, :name, :hash, NOW())'
        )->execute([
            ':pid' => $publicId,
            ':phone' => $phone,
            ':name' => $name,
            ':hash' => $hash,
        ]);
        $user = find_user_by_public_id($publicId);
    }
} catch (Throwable $e) {
    fail($e->getMessage(), 400);
}

respond([
    'ok' => true,
    'user' => present_user($user),
    'created' => !$existing,
]);
