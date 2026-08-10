<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));
$password = (string) ($body['password'] ?? '');

if (strlen($phone) !== 10 || $password === '') {
    fail('phone and password required');
}

$stmt = db()->prepare('SELECT * FROM delivery_partners WHERE phone = :p LIMIT 1');
$stmt->execute([':p' => $phone]);
$partner = $stmt->fetch();
if (!$partner || !password_verify($password, $partner['password_hash'])) {
    fail('Invalid credentials', 401);
}
if ($partner['status'] !== 'active') {
    fail('Partner account inactive', 403);
}

$token = delivery_issue_token((int) $partner['id']);

respond([
    'ok' => true,
    'token' => $token,
    'partner' => present_partner($partner),
]);
