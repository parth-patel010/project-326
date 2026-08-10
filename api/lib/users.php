<?php

declare(strict_types=1);

function public_user_id(): string
{
    return 'US' . strtoupper(bin2hex(random_bytes(6)));
}

function find_user_by_phone(string $phone): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE phone = :p LIMIT 1');
    $stmt->execute([':p' => $phone]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_user_by_public_id(string $publicId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE public_id = :id LIMIT 1');
    $stmt->execute([':id' => $publicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Create user if missing; refresh last_login_at.
 */
function upsert_user_by_phone(string $phone, array $extra = []): array
{
    $phone = normalize_phone($phone);
    if (strlen($phone) !== 10) {
        throw new InvalidArgumentException('Valid 10-digit phone required');
    }

    $existing = find_user_by_phone($phone);
    $pdo = db();

    if ($existing) {
        $name = array_key_exists('name', $extra)
            ? trim((string) $extra['name'])
            : (string) $existing['name'];
        $email = array_key_exists('email', $extra)
            ? ($extra['email'] !== null ? trim((string) $extra['email']) : null)
            : $existing['email'];
        $avatar = array_key_exists('avatar_url', $extra)
            ? ($extra['avatar_url'] !== null ? trim((string) $extra['avatar_url']) : null)
            : $existing['avatar_url'];

        $pdo->prepare(
            'UPDATE users
             SET name = :name, email = :email, avatar_url = :avatar, last_login_at = NOW()
             WHERE id = :id'
        )->execute([
            ':name' => $name,
            ':email' => $email !== '' ? $email : null,
            ':avatar' => $avatar !== '' ? $avatar : null,
            ':id' => $existing['id'],
        ]);

        return find_user_by_public_id((string) $existing['public_id']) ?? $existing;
    }

    $publicId = public_user_id();
    $pdo->prepare(
        'INSERT INTO users (public_id, phone, name, email, avatar_url, last_login_at)
         VALUES (:public_id, :phone, :name, :email, :avatar, NOW())'
    )->execute([
        ':public_id' => $publicId,
        ':phone' => $phone,
        ':name' => trim((string) ($extra['name'] ?? '')),
        ':email' => isset($extra['email']) && trim((string) $extra['email']) !== ''
            ? trim((string) $extra['email'])
            : null,
        ':avatar' => isset($extra['avatar_url']) && trim((string) $extra['avatar_url']) !== ''
            ? trim((string) $extra['avatar_url'])
            : null,
    ]);

    return find_user_by_public_id($publicId) ?? [];
}

function present_user(?array $user): ?array
{
    if (!$user) {
        return null;
    }
    return [
        'id' => $user['public_id'],
        'phone' => $user['phone'],
        'name' => $user['name'] ?? '',
        'email' => $user['email'],
        'avatar_url' => $user['avatar_url'],
        'is_active' => (bool) $user['is_active'],
        'last_login_at' => $user['last_login_at'],
        'created_at' => $user['created_at'],
    ];
}
