<?php
// Diagnostic script to check what's wrong with admin_api.php on production
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PHP Diagnostic Report ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Check if PDO is available
echo "=== PDO Check ===\n";
if (class_exists('PDO')) {
    echo "✅ PDO is available\n";
    echo "Available drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
} else {
    echo "❌ PDO is NOT available\n";
}

echo "\n=== Database Connection Test ===\n";

// Try to include db_config.php
if (file_exists('db_config.php')) {
    echo "✅ db_config.php exists\n";
    try {
        include 'db_config.php';
        echo "✅ db_config.php loaded successfully\n";
        
        // Test database connection
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✅ Database connection successful\n";
            
            // Test a simple query
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM products LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Query test successful - Found {$result['count']} products\n";
            
        } catch (PDOException $e) {
            echo "❌ Database connection failed: " . $e->getMessage() . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error loading db_config.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ db_config.php does not exist\n";
}

echo "\n=== API Test ===\n";
echo "Testing admin_api.php with action=search_products...\n";

// Simulate the API call
$_GET['action'] = 'search_products';
$_GET['q'] = 'test';

ob_start();
try {
    // Capture any output from admin_api.php
    include 'admin_api.php';
} catch (Exception $e) {
    echo "❌ Error including admin_api.php: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

echo "Output length: " . strlen($output) . " bytes\n";
if (strlen($output) > 0) {
    echo "First 200 characters of output:\n";
    echo substr($output, 0, 200) . "\n";
    
    // Check if it's valid JSON
    $json = json_decode($output);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Output is valid JSON\n";
    } else {
        echo "❌ Output is NOT valid JSON\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
}

echo "\n=== End Report ===\n";
?>