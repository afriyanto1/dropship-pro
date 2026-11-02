<?php
declare(strict_types=1);

/**
 * Database Configuration
 * PHP 8.5 + PDO MySQL + AWS RDS SSL
 */

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Access denied.');
}

// Database credentials
$host = getenv('DB_HOST') ?: 'dropship-db.ctq2e6428ybb.ap-southeast-2.rds.amazonaws.com';
$db   = getenv('DB_NAME') ?: 'dropshipdb';
$user = getenv('DB_USER') ?: 'admin';
$pass = getenv('DB_PASS') ?: 'PapiAriyasa04';

$port = (int) (getenv('DB_PORT') ?: 3306);

// AWS RDS SSL Certificate
$sslCa = getenv('DB_SSL_CA')
    ?: '/var/www/html/database/global-bundle.pem';

// Validate configuration
if ($host === '' || $db === '' || $user === '') {
    error_log('Database configuration is incomplete.');
    http_response_code(500);
    exit('Database configuration error.');
}

// Validate SSL certificate
if (!is_file($sslCa) || !is_readable($sslCa)) {
    error_log('AWS RDS CA certificate missing or unreadable: ' . $sslCa);
    http_response_code(500);
    exit('Database SSL certificate error.');
}

// PDO DSN
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $db
);

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,

    // AWS RDS SSL verification
    PDO::MYSQL_ATTR_SSL_CA => $sslCa,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
];

try {

    // Establish database connection
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        $options
    );

    // Set database session timezone
    $pdo->exec("SET time_zone = '+00:00'");

} catch (PDOException $e) {

    // Log actual error on server
    error_log(
        'Database connection failed: ' . $e->getMessage()
    );

    // Hide database details from visitors
    http_response_code(500);

    exit('Koneksi database gagal. Silakan periksa konfigurasi database.');
}