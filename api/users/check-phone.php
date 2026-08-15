<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$phone = normalize_phone((string) ($_GET['phone'] ?? ''));
if (strlen($phone) !== 10) {
    fail('Valid 10-digit phone required');
}

$user = find_user_by_phone($phone);
$hasPassword = $user && !empty($user['password_hash']);

respond([
    'ok' => true,
    'exists' => $user !== null,
    'has_password' => (bool) $hasPassword,
]);
