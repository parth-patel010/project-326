<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

ensure_addresses_table();
$body = json_body();
$phone = normalize_phone((string) ($body['phone'] ?? ''));
if (strlen($phone) !== 10) {
    fail('Valid 10-digit phone required');
}
$user = find_user_by_phone($phone);
if (!$user) {
    fail('User not found', 404);
}

$id = trim((string) ($body['id'] ?? ''));
$label = trim((string) ($body['label'] ?? 'Home'));
$line = trim((string) ($body['line'] ?? ''));
$details = trim((string) ($body['details'] ?? ''));
$receiverName = trim((string) ($body['receiverName'] ?? $body['receiver_name'] ?? ''));
$receiverPhone = normalize_phone((string) ($body['receiverPhone'] ?? $body['receiver_phone'] ?? $phone));
$lat = isset($body['latitude']) ? (float) $body['latitude'] : null;
$lng = isset($body['longitude']) ? (float) $body['longitude'] : null;
$isDefault = !empty($body['is_default']) || !empty($body['isDefault']) ? 1 : 0;

if ($line === '') {
    fail('Address line required');
}

$pdo = db();
if ($isDefault) {
    $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u')
        ->execute([':u' => $user['id']]);
}

if ($id !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM user_addresses WHERE public_id = :id AND user_id = :u LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':u' => $user['id']]);
    $existing = $stmt->fetch();
    if (!$existing) {
        fail('Address not found', 404);
    }
    $pdo->prepare(
        'UPDATE user_addresses SET
           label = :label, line = :line, details = :details,
           receiver_name = :rn, receiver_phone = :rp,
           latitude = :lat, longitude = :lng, is_default = :def
         WHERE id = :id'
    )->execute([
        ':label' => $label,
        ':line' => $line,
        ':details' => $details,
        ':rn' => $receiverName,
        ':rp' => $receiverPhone,
        ':lat' => $lat,
        ':lng' => $lng,
        ':def' => $isDefault,
        ':id' => $existing['id'],
    ]);
    $publicId = $id;
} else {
    $publicId = 'AD' . strtoupper(bin2hex(random_bytes(6)));
    $pdo->prepare(
        'INSERT INTO user_addresses
         (public_id, user_id, label, line, details, receiver_name, receiver_phone, latitude, longitude, is_default)
         VALUES (:pid, :uid, :label, :line, :details, :rn, :rp, :lat, :lng, :def)'
    )->execute([
        ':pid' => $publicId,
        ':uid' => $user['id'],
        ':label' => $label,
        ':line' => $line,
        ':details' => $details,
        ':rn' => $receiverName,
        ':rp' => $receiverPhone,
        ':lat' => $lat,
        ':lng' => $lng,
        ':def' => $isDefault,
    ]);
}

$stmt = $pdo->prepare('SELECT * FROM user_addresses WHERE public_id = :id LIMIT 1');
$stmt->execute([':id' => $publicId]);

respond([
    'ok' => true,
    'address' => present_address($stmt->fetch()),
]);
