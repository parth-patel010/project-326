<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/lib/Env.php';

$envPath = __DIR__ . '/.env';
if (!is_file($envPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing api/.env — copy api/.env.example to api/.env',
    ]);
    exit;
}

Env::load($envPath);

/** @var array $CONFIG */
$CONFIG = [
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'foodmitra'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', '') ?? '',
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],
    'razorpay' => [
        'key_id' => Env::get('RAZORPAY_KEY_ID', '') ?? '',
        'key_secret' => Env::get('RAZORPAY_KEY_SECRET', '') ?? '',
        'webhook_secret' => Env::get('RAZORPAY_WEBHOOK_SECRET', '') ?? '',
    ],
    'fast2sms' => [
        'api_key' => Env::get('FAST2SMS_API_KEY', '') ?? '',
        'route' => Env::get('FAST2SMS_ROUTE', 'otp') ?? 'otp',
        'sender_id' => Env::get('FAST2SMS_SENDER_ID', 'FSTSMS') ?? 'FSTSMS',
        'template_id' => Env::get('FAST2SMS_TEMPLATE_ID', '') ?? '',
    ],
    'cors_origin' => Env::get('CORS_ORIGIN', '*') ?? '*',
    'currency' => Env::get('CURRENCY', 'INR') ?? 'INR',
    'rate_limit_per_minute' => Env::int('RATE_LIMIT_PER_MINUTE', 60),
    'otp_ttl_seconds' => Env::int('OTP_TTL_SECONDS', 300),
    'otp_rate_per_hour' => Env::int('OTP_RATE_LIMIT_PER_HOUR', 5),
    'otp_length' => Env::int('OTP_LENGTH', 6),
    'osrm_base_url' => Env::get('OSRM_BASE_URL', 'https://router.project-osrm.org') ?? 'https://router.project-osrm.org',
    'realtime_emit_url' => Env::get('REALTIME_EMIT_URL', '') ?? '',
    'realtime_secret' => Env::get('REALTIME_SECRET', 'foodmitra_realtime') ?? 'foodmitra_realtime',
];

$origin = $CONFIG['cors_origin'];
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key, X-Partner-Token');
header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return $_POST ?: [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): void
{
    respond(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = $CONFIG['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function public_order_id(): string
{
    return 'FM' . strtoupper(bin2hex(random_bytes(6)));
}

function rupees_to_paise($amount): int
{
    return (int) round(((float) $amount) * 100);
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    return $digits;
}

function hash_otp(string $phone, string $otp, string $purpose): string
{
    $pepper = Env::get('API_KEY_PEPPER', '') ?? '';
    return hash('sha256', $pepper . '|' . $phone . '|' . $purpose . '|' . $otp);
}

// Security middleware (API key + rate limit). Skip for Razorpay webhooks.
if (!defined('FM_SKIP_SECURITY') || !FM_SKIP_SECURITY) {
    require_once __DIR__ . '/middleware/security.php';
    fm_apply_security();
}
