<?php

declare(strict_types=1);

/**
 * Public app config for delivery partner (maintenance + force update).
 * GET ?app_type=delivery_app&platform=android|ios&current_version=1.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/Settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$appType = strtolower(trim((string) ($_GET['app_type'] ?? 'delivery_app')));
$platform = strtolower(trim((string) ($_GET['platform'] ?? 'android')));
$current = trim((string) ($_GET['current_version'] ?? '0.0.0'));

$s = Settings::get();

$maintenance = $appType === 'delivery_app' && !empty($s['maintenance_mode_delivery']);
$min = $platform === 'ios'
    ? (string) ($s['delivery_app_min_version_ios'] ?? '1.0.0')
    : (string) ($s['delivery_app_min_version_android'] ?? '1.0.0');
$download = $platform === 'ios'
    ? (string) ($s['delivery_app_download_url_ios'] ?? '')
    : (string) ($s['delivery_app_download_url_android'] ?? '');

$force = false;
if ($current !== '' && $min !== '') {
    $force = version_compare($current, $min, '<');
}

respond([
    'ok' => true,
    'success' => true,
    'maintenance_mode' => $maintenance,
    'admin_contact_number' => (string) ($s['admin_contact_number'] ?? ''),
    'maintenance_page_url' => '',
    'force_update' => $force,
    'version_control' => [
        'android' => [
            'min_version' => (string) ($s['delivery_app_min_version_android'] ?? '1.0.0'),
            'download_url' => (string) ($s['delivery_app_download_url_android'] ?? ''),
        ],
        'ios' => [
            'min_version' => (string) ($s['delivery_app_min_version_ios'] ?? '1.0.0'),
            'download_url' => (string) ($s['delivery_app_download_url_ios'] ?? ''),
        ],
    ],
    'download_url' => $download,
]);
