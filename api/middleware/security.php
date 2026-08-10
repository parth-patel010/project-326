<?php

declare(strict_types=1);

/**
 * Security middleware: API key hash verification + rate limiting.
 * Webhooks should define FM_SKIP_SECURITY before bootstrap.
 */

function fm_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    foreach ($candidates as $c) {
        if (!$c) {
            continue;
        }
        $first = trim(explode(',', (string) $c)[0]);
        if ($first !== '') {
            return substr($first, 0, 45);
        }
    }
    return '0.0.0.0';
}

function fm_hash_api_key(string $plain): string
{
    $pepper = Env::get('API_KEY_PEPPER', '') ?? '';
    return hash('sha256', $pepper . $plain);
}

function fm_request_api_key(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $headers = is_array($headers) ? array_change_key_case($headers, CASE_LOWER) : [];

    if (!empty($headers['x-api-key'])) {
        return trim((string) $headers['x-api-key']);
    }
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim((string) $_SERVER['HTTP_X_API_KEY']);
    }
    // Authorization: Bearer <key>
    $auth = $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (is_string($auth) && preg_match('/^\s*Bearer\s+(\S+)/i', $auth, $m)) {
        return trim($m[1]);
    }
    return '';
}

function fm_verify_api_key(string $plain): bool
{
    if ($plain === '') {
        return false;
    }
    $hash = fm_hash_api_key($plain);

    // 1) Env allowlist (comma-separated hashes)
    $envHash = Env::get('API_KEY_HASH', '') ?? '';
    if ($envHash !== '') {
        foreach (array_map('trim', explode(',', $envHash)) as $h) {
            if ($h !== '' && hash_equals($h, $hash)) {
                return true;
            }
        }
    }

    // 2) Database
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT id FROM api_keys WHERE key_hash = :h AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':h' => $hash]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id')
                ->execute([':id' => $row['id']]);
            return true;
        }
    } catch (Throwable $e) {
        // Table missing / DB down — fall through
    }

    return false;
}

/**
 * Sliding fixed-window rate limit. Returns remaining hits or fails with 429.
 */
function fm_rate_limit(string $bucket, int $limit, int $windowSeconds = 60): void
{
    if ($limit <= 0) {
        return;
    }
    $now = time();
    $windowStart = (int) (floor($now / $windowSeconds) * $windowSeconds);
    $pdo = db();

    try {
        $pdo->prepare(
            'INSERT INTO rate_limits (bucket_key, window_start, hit_count)
             VALUES (:k, :w, 1)
             ON DUPLICATE KEY UPDATE hit_count = hit_count + 1'
        )->execute([':k' => $bucket, ':w' => $windowStart]);

        $stmt = $pdo->prepare(
            'SELECT hit_count FROM rate_limits WHERE bucket_key = :k AND window_start = :w LIMIT 1'
        );
        $stmt->execute([':k' => $bucket, ':w' => $windowStart]);
        $count = (int) ($stmt->fetchColumn() ?: 0);

        // Opportunistic cleanup of old windows
        if (random_int(1, 50) === 1) {
            $pdo->prepare('DELETE FROM rate_limits WHERE window_start < :cut')
                ->execute([':cut' => $now - ($windowSeconds * 5)]);
        }

        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . max(0, $limit - $count));
        header('X-RateLimit-Reset: ' . ($windowStart + $windowSeconds));

        if ($count > $limit) {
            fail('Too many requests. Please slow down.', 429);
        }
    } catch (Throwable $e) {
        // If rate table missing, do not hard-block API in early setup
    }
}

function fm_apply_security(): void
{
    global $CONFIG;

    $plain = fm_request_api_key();
    if (!fm_verify_api_key($plain)) {
        fail('Unauthorized. Missing or invalid API key.', 401);
    }

    $ip = fm_client_ip();
    $keyHashPrefix = substr(fm_hash_api_key($plain), 0, 12);
    $perMin = (int) ($CONFIG['rate_limit_per_minute'] ?? 60);

    fm_rate_limit('ip:' . $ip, $perMin, 60);
    fm_rate_limit('key:' . $keyHashPrefix, $perMin, 60);
}
