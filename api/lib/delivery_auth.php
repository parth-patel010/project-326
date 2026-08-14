<?php

declare(strict_types=1);

/**
 * Delivery partner Bearer token helpers.
 */
function delivery_issue_token(int $partnerId): string
{
    $token = bin2hex(random_bytes(32));
    $pdo = db();
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS delivery_sessions (
              token CHAR(64) NOT NULL PRIMARY KEY,
              partner_id BIGINT UNSIGNED NOT NULL,
              expires_at DATETIME NOT NULL,
              KEY idx_ds_partner (partner_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } catch (Throwable $e) {
        // ignore
    }
    $pdo->prepare('DELETE FROM delivery_sessions WHERE partner_id = :id OR expires_at < NOW()')
        ->execute([':id' => $partnerId]);
    $pdo->prepare(
        'INSERT INTO delivery_sessions (token, partner_id, expires_at)
         VALUES (:t, :p, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    )->execute([':t' => $token, ':p' => $partnerId]);
    return $token;
}

function delivery_partner_from_request(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $headers = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];
    $auth = $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = '';
    if (is_string($auth) && preg_match('/^\s*Bearer\s+(\S+)/i', $auth, $m)) {
        $token = $m[1];
    }
    if ($token === '' && !empty($_SERVER['HTTP_X_PARTNER_TOKEN'])) {
        $token = trim((string) $_SERVER['HTTP_X_PARTNER_TOKEN']);
    }
    if ($token === '') {
        fail('Partner token required', 401);
    }
    $stmt = db()->prepare(
        'SELECT p.* FROM delivery_sessions s
         INNER JOIN delivery_partners p ON p.id = s.partner_id
         WHERE s.token = :t AND s.expires_at > NOW() AND p.status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([':t' => $token]);
    $partner = $stmt->fetch();
    if (!$partner) {
        fail('Invalid or expired partner session', 401);
    }
    return $partner;
}

function present_partner(array $p): array
{
    return [
        'id' => (int) $p['id'],
        'public_id' => $p['public_id'],
        'full_name' => $p['full_name'],
        'phone' => $p['phone'],
        'service_radius_km' => (float) $p['service_radius_km'],
        'is_online' => (bool) $p['is_online'],
        'is_verified' => (bool) $p['is_verified'],
        'has_insurance' => (bool) $p['has_insurance'],
        'orders_completed' => (int) $p['orders_completed'],
        'earnings_total' => (float) $p['earnings_total'],
        'earn_wallet' => (float) ($p['earn_wallet'] ?? $p['earnings_total'] ?? 0),
        'cod_wallet' => (float) $p['cod_wallet'],
        'latitude' => $p['current_latitude'] !== null ? (float) $p['current_latitude'] : null,
        'longitude' => $p['current_longitude'] !== null ? (float) $p['current_longitude'] : null,
    ];
}
