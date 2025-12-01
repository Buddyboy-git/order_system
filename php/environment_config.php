<?php
/**
 * Environment Detection and Configuration
 * Automatically detects if we're in local or production environment
 */

// Environment detection - you can customize these indicators
function detectEnvironment() {
    // Check for specific production indicators
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'buddyboyprovisionsllc.com') !== false) {
        return 'production';
    }
    
    // Check if production database exists
    try {
        $testPdo = new PDO("mysql:host=localhost;dbname=buddyboy_orders_db", 'buddyboy_orders', 'Trotta123$');
        return 'production';
    } catch (PDOException $e) {
        // Production DB not accessible, assume local
    }
    
    // Check for local development indicators
    if (isset($_SERVER['HTTP_HOST']) && (
        $_SERVER['HTTP_HOST'] === 'localhost' || 
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
        strpos($_SERVER['HTTP_HOST'], '.local') !== false
    )) {
        return 'local';
    }
    
    // Default to local for safety
    return 'local';
}

// Get current environment
$environment = detectEnvironment();

// Load appropriate configuration
if ($environment === 'production') {
    // Production settings
    $host = 'localhost';
    $dbname = 'buddyboy_orders_db';
    $username = 'buddyboy_orders';
    $password = 'Trotta123$';
    $charset = 'utf8mb4';
    
    // Production-specific settings
    $debug = false;
    $error_reporting = false;
} else {
    // Local development settings
    $host = 'localhost';
    $dbname = 'orders';
    $username = 'root';
    $password = '';
    $charset = 'utf8mb4';
    
    // Development-specific settings
    $debug = true;
    $error_reporting = true;
}

// Set error reporting based on environment
if ($error_reporting) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Create PDO DSN
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Optional: Create PDO connection function
function createPDOConnection() {
    global $dsn, $username, $password;
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    return new PDO($dsn, $username, $password, $options);
}

// Debug information (only in development)
if ($debug && isset($_GET['debug_env'])) {
    echo "Environment: $environment\n";
    echo "Database: $dbname\n";
    echo "Host: $host\n";
    echo "User: $username\n";
    exit();
}
?>