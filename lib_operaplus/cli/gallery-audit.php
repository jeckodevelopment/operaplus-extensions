<?php
declare(strict_types=1);

// Standalone CLI maintenance utility: never expose through the web application.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('_JEXEC', 1);
require __DIR__ . '/../src/GalleryStore.php';

use OperaPlus\Gallery\Store;

$args = array_slice($argv, 1);
$purge = $args === ['--purge-expired-stages', '--offline'];
if ($args !== [] && !$purge) {
    fwrite(STDERR, "Usage: php libraries/operaplus/cli/gallery-audit.php [--purge-expired-stages --offline]\n");
    exit(2);
}
try {
    $store = new Store(dirname(__DIR__, 3) . '/images', static fn () => null);
    $now = time();
    $summary = ['retained' => 0, 'stage' => 0, 'expired-stage' => 0, 'review' => 0, 'purged' => 0, 'errors' => 0];
    foreach ($store->inventory($now) as $row) {
        $summary[$row['status']]++;
        if ($purge && $row['status'] === 'expired-stage') {
            try {
                $store->discard($row['article'], $row['gallery'], true, $now - Store::PURGE_GRACE);
                $row['result'] = 'purged';
                $summary['purged']++;
            } catch (\Throwable $e) {
                $row['result'] = 'review-required';
                $summary['errors']++;
            }
        }
        echo json_encode($row, JSON_THROW_ON_ERROR) . PHP_EOL;
    }
    fwrite(STDERR, json_encode(['summary' => $summary, 'mode' => $purge ? 'purge' : 'report'], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit($summary['errors'] > 0 ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Gallery audit failed. Check the installation and permissions.\n");
    exit(1);
}
