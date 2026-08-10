<?php

declare(strict_types=1);

/**
 * Generate a FoodMitra API key + SHA-256 hash.
 *
 * Usage (from api/ folder):
 *   php bin/generate-api-key.php
 *   php bin/generate-api-key.php --name=mobile --insert
 *
 * Put the PLAIN key in the Expo app .env.local as EXPO_PUBLIC_API_KEY
 * Put the HASH in api/.env as API_KEY_HASH and in the api_keys table.
 */

$opts = getopt('', ['name::', 'insert', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php bin/generate-api-key.php [--name=mobile] [--insert]\n";
    exit(0);
}

$name = is_string($opts['name'] ?? null) ? $opts['name'] : 'mobile';
$doInsert = array_key_exists('insert', $opts);

require_once __DIR__ . '/../lib/Env.php';

$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    Env::load($envPath);
}

$pepper = Env::get('API_KEY_PEPPER', '') ?? '';
$plain = 'fm_' . bin2hex(random_bytes(24));
$hash = hash('sha256', $pepper . $plain);

echo "\n=== FoodMitra API Key ===\n\n";
echo "Name:        {$name}\n";
echo "Plain key:   {$plain}\n";
echo "SHA-256:     {$hash}\n\n";
echo "1) App .env.local:\n";
echo "   EXPO_PUBLIC_API_KEY={$plain}\n\n";
echo "2) api/.env:\n";
echo "   API_KEY_HASH={$hash}\n\n";
echo "3) SQL (or use --insert):\n";
echo "   INSERT INTO api_keys (name, key_hash, is_active) VALUES ('{$name}', '{$hash}', 1);\n\n";

if ($doInsert) {
    $host = Env::get('DB_HOST', '127.0.0.1');
    $port = Env::int('DB_PORT', 3306);
    $dbName = Env::get('DB_NAME', 'foodmitra');
    $user = Env::get('DB_USER', 'root');
    $pass = Env::get('DB_PASS', '') ?? '';
    $charset = Env::get('DB_CHARSET', 'utf8mb4');

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare(
            'INSERT INTO api_keys (name, key_hash, is_active) VALUES (:n, :h, 1)'
        );
        $stmt->execute([':n' => $name, ':h' => $hash]);
        echo "Inserted into api_keys (id=" . $pdo->lastInsertId() . ").\n\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Insert failed: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Import api/sql/002_security.sql first, then retry --insert.\n");
        exit(1);
    }
}

echo "Done.\n";
