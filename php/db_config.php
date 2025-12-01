<?php
// Database configuration
$host = 'localhost';
$dbname = 'orders';
$username = 'root';
$password = '';

// You can override these settings by creating a local_config.php file
if (file_exists(__DIR__ . '/local_config.php')) {
    include __DIR__ . '/local_config.php';
}
?>