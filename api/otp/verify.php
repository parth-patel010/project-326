<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));
$otp = trim((string) ($body['otp'] ?? ''));
$purpose = trim((string) ($body['purpose'] ?? 'login'));
if ($purpose === '') {
    $purpose = 'login';
}

if (strlen($phone) !== 10) {
    fail('Valid 10-digit Indian mobile required');
}
if ($otp === '' || !preg_match('/^\d{4,8}$/', $otp)) {
    fail('Invalid OTP');
}

$ip = fm_client_ip();
fm_rate_limit('otp_verify:phone:' . $phone, 20, 3600);
fm_rate_limit('otp_verify:ip:' . $ip, 40, 3600);

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT * FROM otp_codes
     WHERE phone = :p AND purpose = :pur AND verified_at IS NULL
     ORDER BY id DESC LIMIT 1'
);
$stmt->execute([':p' => $phone, ':pur' => $purpose]);
$row = $stmt->fetch();

if (!$row) {
    fail('No OTP pending. Request a new one.', 404);
}

if (strtotime((string) $row['expires_at']) < time()) {
    fail('OTP expired. Request a new one.', 410);
}

$maxAttempts = (int) ($row['max_attempts'] ?? 5);
if ((int) $row['attempts'] >= $maxAttempts) {
    fail('Too many attempts. Request a new OTP.', 429);
}

$expected = (string) $row['otp_hash'];
$got = hash_otp($phone, $otp, $purpose);

if (!hash_equals($expected, $got)) {
    $pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id')
        ->execute([':id' => $row['id']]);
    fail('Incorrect OTP', 401);
}

$pdo->prepare('UPDATE otp_codes SET verified_at = NOW() WHERE id = :id')
    ->execute([':id' => $row['id']]);

require_once __DIR__ . '/../lib/users.php';
$user = upsert_user_by_phone($phone);

respond([
    'ok' => true,
    'message' => 'OTP verified',
    'phone' => $phone,
    'purpose' => $purpose,
    'user' => present_user($user),
]);
