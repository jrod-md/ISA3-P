<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Service\ExpirationService;
use Marketplace\Bus\Support\AppFactory;

$once = in_array('--once', $argv, true);
$interval = max(1, Config::int('CLEANUP_INTERVAL_SECONDS', 5));
fwrite(STDOUT, sprintf("[%s] Cleanup worker ready; interval=%ds\n", gmdate('c'), $interval));

do {
    try {
        $deleted = (new ExpirationService(AppFactory::repository()))->cleanup();
        fwrite(STDOUT, sprintf("[%s] expired_sessions_deleted=%d\n", gmdate('c'), $deleted));
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("[%s] cleanup_error=%s\n", gmdate('c'), $exception->getMessage()));
    }

    if (!$once) {
        sleep($interval);
    }
} while (!$once);

