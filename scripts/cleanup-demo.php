<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/demo-data.php';
try {
    $dataset = new DemoDataset(); $dataset->acquire();
    try { $result = $dataset->cleanup(); } finally { $dataset->release(); }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) { fwrite(STDERR, 'Cleanup cancelado: ' . $error->getMessage() . "\n"); exit(1); }
