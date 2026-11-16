<?php
require __DIR__ . '/config.php';

header('Cache-Control: no-store, max-age=0');

const MAX_UPLOAD_FILES = 5;
const MAX_UPLOAD_BYTES = 5242880; // 5 MiB per file

function out(bool $ok, int $code = 200, array $extra = []): never
{
    http_response_code($code);
    if (str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/html')) {
        header('Content-Type: text/html; charset=utf-8');
        $title = $ok ? 'Message received' : 'Message not sent';
        $message = (string)($extra['message'] ?? $extra['error'] ?? 'Please try again or contact me directly.');
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#06122a"><title>' . $escape($title) . '</title><style>'
            . 'body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box;background:#06122a;color:#eaf1ff;font:17px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}'
            . 'main{width:min(560px,100%);padding:28px;border:1px solid #1f3a6b;border-radius:20px;background:#0f2449}h1{font-size:clamp(1.5rem,7vw,2.2rem);line-height:1.2}p{white-space:pre-line;color:#c5d3ec}a{color:#38e8c6;font-weight:700}'
            . '</style></head><body><main><h1>' . $escape($title) . '</h1><p>' . $escape($message) . '</p><a href="index.html#contact">Back to the portfolio</a></main></body></html>';
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
}

function ini_size_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    if ($unit === 'g') {
        $number *= 1024;
    }
    if ($unit === 'g' || $unit === 'm') {
        $number *= 1024;
    }
    if ($unit === 'g' || $unit === 'm' || $unit === 'k') {
        $number *= 1024;
    }
    return (int)$number;
}

function safe_filename_length(string $value, int $limit): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }
    if (function_exists('iconv')) {
        $valid = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($valid !== false) {
            $value = $valid;
        }
    }
    if (preg_match_all('/./us', $value, $characters) !== false) {
        return implode('', array_slice($characters[0], 0, $limit));
    }
    return substr($value, 0, $limit);
}

function confirmation_message(string $email): string
{
    return "Thanks for getting in touch.\n\nI received your message and will reply to {$email}.";
}

function collect_uploads(): array
{
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['error'] ?? null)) {
        return [];
    }

    $allowed = [
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-zip-compressed',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploads = [];

    foreach ($_FILES['attachments']['error'] as $index => $error) {
        $error = (int)$error;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('One of the selected files could not be uploaded.');
        }
        if (count($uploads) >= MAX_UPLOAD_FILES) {
            throw new InvalidArgumentException('Please upload no more than ' . MAX_UPLOAD_FILES . ' files.');
        }

        $tmp = (string)($_FILES['attachments']['tmp_name'][$index] ?? '');
        $size = (int)($_FILES['attachments']['size'][$index] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('One of the selected files is not a valid upload.');
        }
        if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Each project detail file must be smaller than 5 MB.');
        }

        $mime = strtolower((string)$finfo->file($tmp));
        if (!in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('Please upload PDF, Office, text, ZIP, JPG, PNG, or WebP project files only.');
        }

        $original = basename((string)($_FILES['attachments']['name'][$index] ?? 'attachment'));
        $original = preg_replace('/[\x00-\x1F\x7F]/', '_', $original) ?: 'attachment';
        $original = safe_filename_length($original, 255);
        $uploads[] = ['tmp' => $tmp, 'name' => $original, 'mime' => $mime, 'size' => $size];
    }

    return $uploads;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 405, ['error' => 'Method not allowed.']);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_starts_with($contentType, 'multipart/form-data')) {
    $postMax = ini_size_bytes((string)ini_get('post_max_size'));
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($postMax > 0 && $contentLength > $postMax && empty($_POST) && empty($_FILES)) {
        out(false, 413, ['error' => 'The total upload exceeds the server limit. Reduce the file sizes and try again.']);
    }
    $data = $_POST;
} else {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
}
if (!is_array($data)) {
    out(false, 400, ['error' => 'Invalid request.']);
}

// Honeypot: do not store bot submissions, but keep the visitor-facing confirmation consistent.
if (!empty($data['website']) || !empty($data['company_website'])) {
    $honeypotEmail = $data['email'] ?? '';
    $honeypotEmail = is_string($honeypotEmail) ? trim($honeypotEmail) : '';
    if (!filter_var($honeypotEmail, FILTER_VALIDATE_EMAIL)) {
        $honeypotEmail = '';
    }
    out(true, 200, ['email' => $honeypotEmail, 'message' => confirmation_message($honeypotEmail)]);
}

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$message = $data['message'] ?? '';
if (!is_string($name) || !is_string($email) || !is_string($message)) {
    out(false, 422, ['error' => 'Please check the name, email, and message fields.']);
}
$name = trim($name);
$email = trim($email);
$message = trim($message);
$projectDetails = !empty($data['project_details']) ? 1 : 0;

if ($name === '' || text_length($name) > 120) {
    out(false, 422, ['error' => 'Please enter a name of 120 characters or fewer.']);
}
if ($email === '' || text_length($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 422, ['error' => 'Please enter a valid email.']);
}
if ($message === '' || text_length($message) < 3 || text_length($message) > 5000) {
    out(false, 422, ['error' => 'Please enter a message.']);
}

try {
    $uploads = collect_uploads();
    if ($uploads) {
        $projectDetails = 1;
    }

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    $pdo = db();

    // Serialize requests from the same IP so concurrent submissions cannot all
    // pass the check before any of them is inserted. MySQL releases this lock
    // automatically when the connection closes, including on unexpected errors.
    $lockName = 'portfolio-contact-' . substr(hash('sha256', $ip), 0, 46);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        out(false, 503, ['error' => 'The form is busy. Please try again in a moment.']);
    }

    $rateLimited = false;
    try {
        $rate = $pdo->prepare(
            'SELECT COUNT(*) FROM messages WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)'
        );
        $rate->execute([$ip]);
        $rateLimited = (int)$rate->fetchColumn() >= 5;

        if (!$rateLimited) {
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO messages (name, email, message, project_details, ip) VALUES (?, ?, ?, ?, ?)'
                );
                $insert->execute([$name, $email, $message, $projectDetails, $ip]);
                $messageId = (int)$pdo->lastInsertId();

                if ($uploads) {
                    $fileInsert = $pdo->prepare(
                        'INSERT INTO message_files (message_id, original_name, mime_type, file_size, file_data) VALUES (?, ?, ?, ?, ?)'
                    );
                    foreach ($uploads as $upload) {
                        $contents = file_get_contents($upload['tmp']);
                        if ($contents === false) {
                            throw new RuntimeException('Could not read an uploaded file.');
                        }
                        $fileInsert->bindValue(1, $messageId, PDO::PARAM_INT);
                        $fileInsert->bindValue(2, $upload['name'], PDO::PARAM_STR);
                        $fileInsert->bindValue(3, $upload['mime'], PDO::PARAM_STR);
                        $fileInsert->bindValue(4, $upload['size'], PDO::PARAM_INT);
                        $fileInsert->bindValue(5, $contents, PDO::PARAM_LOB);
                        $fileInsert->execute();
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    } finally {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable $releaseError) {
            error_log('contact.php rate-limit lock release: ' . $releaseError->getMessage());
        }
    }

    if ($rateLimited) {
        out(false, 429, ['error' => 'Too many messages. Please try again later.']);
    }

    // Email notification is best-effort; database persistence is the source of truth.
    if (function_exists('mail') && filter_var(NOTIFY_EMAIL, FILTER_VALIDATE_EMAIL)) {
        $safeName = preg_replace('/[\r\n]+/', ' ', $name);
        $subject = 'New portfolio message from ' . $safeName;
        $attachmentNote = $uploads ? "\n\nProject detail files uploaded: " . count($uploads) : '';
        $headers = 'From: ' . NOTIFY_EMAIL . "\r\n" .
            'Reply-To: ' . $email . "\r\n" .
            'Content-Type: text/plain; charset=UTF-8';
        @mail(
            NOTIFY_EMAIL,
            '=?UTF-8?B?' . base64_encode($subject) . '?=',
            "From: {$name} <{$email}>\n\n{$message}{$attachmentNote}",
            $headers
        );
    }

    $success = confirmation_message($email);
    out(true, 200, ['email' => $email, 'message' => $success, 'files' => count($uploads)]);
} catch (InvalidArgumentException $e) {
    out(false, 422, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('contact.php: ' . $e->getMessage());
    out(false, 500, ['error' => 'The message could not be saved right now. Please email me directly.']);
}
