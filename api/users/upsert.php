<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));

if (strlen($phone) !== 10) {
    fail('Valid 10-digit phone required');
}

$extra = [];
if (array_key_exists('name', $body)) {
    $extra['name'] = $body['name'];
}
if (array_key_exists('email', $body)) {
    $extra['email'] = $body['email'];
}
if (array_key_exists('avatar_url', $body)) {
    $extra['avatar_url'] = $body['avatar_url'];
}

$existed = find_user_by_phone($phone) !== null;

try {
    $user = upsert_user_by_phone($phone, $extra);
} catch (Throwable $e) {
    fail($e->getMessage(), 400);
}

respond([
    'ok' => true,
    'user' => present_user($user),
    'created' => !$existed,
]);
