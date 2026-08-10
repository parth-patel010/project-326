<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/Fast2Sms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

global $CONFIG;
$body = json_body();

$phone = normalize_phone((string) ($body['phone'] ?? ''));
$purpose = trim((string) ($body['purpose'] ?? 'login'));
if ($purpose === '') {
    $purpose = 'login';
}

if (strlen($phone) !== 10) {
    fail('Valid 10-digit Indian mobile required');
}

$ip = fm_client_ip();
fm_rate_limit('otp_send:phone:' . $phone, (int) $CONFIG['otp_rate_per_hour'], 3600);
fm_rate_limit('otp_send:ip:' . $ip, (int) $CONFIG['otp_rate_per_hour'] * 3, 3600);

$length = max(4, min(8, (int) $CONFIG['otp_length']));
$max = (10 ** $length) - 1;
$min = 10 ** ($length - 1);
$otp = (string) random_int($min, $max);

$ttl = (int) $CONFIG['otp_ttl_seconds'];
$expiresAt = (new DateTimeImmutable('now'))->modify('+' . $ttl . ' seconds');

$pdo = db();
// Invalidate previous unused OTPs for this phone+purpose
$pdo->prepare(
    'UPDATE otp_codes SET verified_at = NOW()
     WHERE phone = :p AND purpose = :pur AND verified_at IS NULL AND expires_at > NOW()'
)->execute([':p' => $phone, ':pur' => $purpose]);

$stmt = $pdo->prepare(
    'INSERT INTO otp_codes (phone, otp_hash, purpose, expires_at, request_ip)
     VALUES (:phone, :otp_hash, :purpose, :expires_at, :ip)'
);
$stmt->execute([
    ':phone' => $phone,
    ':otp_hash' => hash_otp($phone, $otp, $purpose),
    ':purpose' => $purpose,
    ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
    ':ip' => $ip,
]);

try {
    $sms = new Fast2Sms($CONFIG['fast2sms']);
    $result = $sms->sendOtp($phone, $otp);
    if (!$result['ok']) {
        fail('Failed to send OTP: ' . ($result['error'] ?? 'unknown'), 502);
    }
} catch (Throwable $e) {
    fail('OTP provider error: ' . $e->getMessage(), 502);
}

$payload = [
    'ok' => true,
    'message' => 'OTP sent',
    'phone' => $phone,
    'expires_in' => $ttl,
];

// Dev helper — only when explicitly enabled
if ((Env::get('OTP_DEBUG', '0') ?? '0') === '1') {
    $payload['debug_otp'] = $otp;
}

respond($payload);
