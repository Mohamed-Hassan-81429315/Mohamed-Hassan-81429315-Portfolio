<?php
/**
 * Environment-aware configuration for the portfolio contact service.
 *
 * Managed deployment supplies DATABASE_URL, ADMIN_HASH and optionally
 * NOTIFY_EMAIL. For ordinary PHP hosting, the DB_* values below can be
 * replaced in this file or supplied as environment variables.
 */

function setting(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $fallback : $value;
}

define('NOTIFY_EMAIL', setting('NOTIFY_EMAIL', 'mhmdhsnbdalbdy6@gmail.com'));
define('ADMIN_HASH', setting('ADMIN_HASH', 'PASTE_PASSWORD_HASH_HERE'));
define('ADMIN_PASSWORD', setting('ADMIN_PASSWORD'));
define('AUTO_MIGRATE', setting('AUTO_MIGRATE', '0') === '1');

define('DB_HOST', setting('DB_HOST', 'localhost'));
define('DB_NAME', setting('DB_NAME', 'portfolio'));
define('DB_USER', setting('DB_USER', 'your_db_user'));
define('DB_PASS', setting('DB_PASS', 'your_db_password'));

function database_dsn(): array
{
    $databaseUrl = setting('DATABASE_URL');
    if ($databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL is not a valid MySQL URL.');
        }

        $host = $parts['host'];
        $port = isset($parts['port']) ? ';port=' . (int)$parts['port'] : '';
        $name = ltrim($parts['path'], '/');
        $user = isset($parts['user']) ? rawurldecode($parts['user']) : '';
        $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
        return [
            'mysql:host=' . $host . $port . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $pass,
        ];
    }

    return [
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    [$dsn, $user, $pass] = database_dsn();
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (setting('DATABASE_URL') !== '' && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        $caBundle = '/etc/ssl/certs/ca-certificates.crt';
        if (is_readable($caBundle) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caBundle;
        }
    }
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Opt-in only: schema DDL should not run in the request hot path.
    if (AUTO_MIGRATE) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        message TEXT NOT NULL,
        project_details TINYINT(1) NOT NULL DEFAULT 0,
        ip VARCHAR(45) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_messages_created_at (created_at),
        INDEX idx_messages_ip_created_at (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add the flag to databases created by an earlier version without touching existing rows.
    try {
        $pdo->exec('ALTER TABLE messages ADD COLUMN project_details TINYINT(1) NOT NULL DEFAULT 0 AFTER message');
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'duplicate column') === false) {
            throw $e;
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_files (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id INT UNSIGNED NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(127) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        file_data MEDIUMBLOB NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_message_files_message_id (message_id),
        CONSTRAINT fk_message_files_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    }

    return $pdo;
}
