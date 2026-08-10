<?php

declare(strict_types=1);

/**
 * Shared DB bootstrap for hotel-admin / super-admin (no JSON API headers).
 */
require_once dirname(__DIR__) . '/lib/Env.php';

$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    Env::load($envPath);
}

function admin_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = Env::get('DB_HOST', '127.0.0.1');
    $port = Env::int('DB_PORT', 3306);
    $name = Env::get('DB_NAME', 'foodmitra');
    $user = Env::get('DB_USER', 'root');
    $pass = Env::get('DB_PASS', '') ?? '';
    $charset = Env::get('DB_CHARSET', 'utf8mb4');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

/** Alias so api/lib helpers work when included from admin panels */
if (!function_exists('db')) {
    function db(): PDO
    {
        return admin_db();
    }
}
