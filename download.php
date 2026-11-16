<?php
require __DIR__ . '/config.php';

$forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https' || setting('FORCE_HTTPS') === '1';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => $isHttps ? 'None' : 'Lax',
    'secure' => $isHttps,
]);
session_start();

if (empty($_SESSION['ok'])) {
    http_response_code(404);
    exit('Not found.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit('Not found.');
}

$stmt = db()->prepare('SELECT original_name, mime_type, file_size, file_data FROM message_files WHERE id = ?');
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) {
    http_response_code(404);
    exit('Not found.');
}

$name = (string)$file['original_name'];
$fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'project-detail-file';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Type: ' . (string)$file['mime_type']);
header('Content-Length: ' . (int)$file['file_size']);
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
echo $file['file_data'];
