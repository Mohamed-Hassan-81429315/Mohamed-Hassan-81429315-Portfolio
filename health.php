<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    $pdo = db();
    $pdo->query('SELECT 1 FROM messages LIMIT 1')->closeCursor();
    $pdo->query('SELECT 1 FROM message_files LIMIT 1')->closeCursor();
    http_response_code(200);
    echo json_encode(['ok' => true, 'service' => 'portfolio']);
} catch (Throwable $e) {
    error_log('health.php: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'service' => 'portfolio', 'error' => 'Database unavailable or schema not initialized.']);
}
