<?php
// Database configuration for production

$host = 'localhost';
$dbname = 'orders'; // Change to your local database name if different
$username = 'root';
$password = '';

// Set charset for proper UTF-8 handling
$charset = 'utf8mb4';

// You can override these settings by creating a local_config.php file
if (file_exists(__DIR__ . '/local_config.php')) {
    include __DIR__ . '/local_config.php';
}
?>