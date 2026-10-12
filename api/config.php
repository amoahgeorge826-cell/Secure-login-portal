<?php
define('DB_PATH', __DIR__ . '/database.sqlite');

$appEnvironment = getenv('APP_ENV') ?: 'development';
$configuredPepper = getenv('APP_PEPPER');
if ($configuredPepper !== false && strlen($configuredPepper) < 32) {
    error_log('APP_PEPPER must be at least 32 characters long.');
    http_response_code(500);
    exit('Application configuration is invalid. Ask the administrator to check the server configuration.');
}
if ($appEnvironment === 'production' && $configuredPepper === false) {
    error_log('APP_PEPPER must be configured in production.');
    http_response_code(500);
    exit('Application configuration is invalid. Ask the administrator to check the server configuration.');
}

define('PEPPER', $configuredPepper !== false ? $configuredPepper : 'SuperSecretAppPepperKey2026!');

$configuredBackupDirectory = getenv('APP_BACKUP_DIR');
define(
    'BACKUP_DIR',
    $configuredBackupDirectory !== false && $configuredBackupDirectory !== ''
        ? $configuredBackupDirectory
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'secure-login-backups'
);

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        salt TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('Admin', 'User')),
        totp_secret TEXT,
        is_2fa_enabled INTEGER NOT NULL DEFAULT 0,
        totp_last_counter INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = array_column(
        $pdo->query('PRAGMA table_info(users)')->fetchAll(),
        'name'
    );
    if (!in_array('totp_secret', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN totp_secret TEXT');
    }
    if (!in_array('is_2fa_enabled', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_2fa_enabled INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('totp_last_counter', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN totp_last_counter INTEGER');
    }
} catch (PDOException $e) {
    error_log('Database initialization failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The application is temporarily unavailable because the database could not be initialized.');
}
