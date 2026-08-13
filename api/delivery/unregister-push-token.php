<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$body = json_body();
$token = trim((string) ($body['push_token'] ?? ''));

try {
    if ($token !== '') {
        db()->prepare(
            'UPDATE partner_push_tokens SET is_active = 0
             WHERE partner_id = :p AND push_token = :t'
        )->execute([':p' => (int) $partner['id'], ':t' => $token]);
    } else {
        db()->prepare(
            'UPDATE partner_push_tokens SET is_active = 0 WHERE partner_id = :p'
        )->execute([':p' => (int) $partner['id']]);
    }
} catch (Throwable $e) {
    // Table may not exist yet
}

respond(['ok' => true, 'success' => true]);
