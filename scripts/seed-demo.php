<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/demo-data.php';
date_default_timezone_set('America/Bogota');
try {
    $dataset = new DemoDataset(); $dataset->acquire();
    try { $result = $dataset->seed(); } finally { $dataset->release(); }
    echo json_encode(['operation' => $result['operation'], 'owner' => 'tester', 'roots' => $result['roots'], 'observed' => $result['observed'], 'manifest' => $dataset->manifestPath], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) { fwrite(STDERR, 'DEMO no modificado: ' . $error->getMessage() . "\n"); exit(1); }
