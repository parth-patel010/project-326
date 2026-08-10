<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$id = trim((string) ($_GET['id'] ?? ''));
$phone = normalize_phone((string) ($_GET['phone'] ?? ''));

if ($id === '' && $phone === '') {
    fail('id or phone required');
}

$user = null;
if ($id !== '') {
    $user = find_user_by_public_id($id);
} else {
    $user = find_user_by_phone($phone);
}

if (!$user || !(bool) $user['is_active']) {
    fail('User not found', 404);
}

respond([
    'ok' => true,
    'user' => present_user($user),
]);
