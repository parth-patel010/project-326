<?php

declare(strict_types=1);

/**
 * CLI: php bin/cron_reoffer.php
 * Re-offer delivery jobs whose exclusive lock expired.
 */
require_once __DIR__ . '/../lib/Env.php';
Env::load(__DIR__ . '/../.env');

require_once __DIR__ . '/../lib/admin_db.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Realtime.php';
require_once __DIR__ . '/../lib/Dispatch.php';

$count = Dispatch::reofferExpired();
echo date('c') . " reoffered={$count}\n";
