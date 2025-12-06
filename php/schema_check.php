<?php
// Check database schema on production
require_once 'db_config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Database Schema Check ===\n";
    
    // Check if products table exists
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available tables: " . implode(', ', $tables) . "\n\n";
    
    // Check products table structure
    if (in_array('products', $tables)) {
        echo "=== products table structure ===\n";
        $stmt = $pdo->query("DESCRIBE products");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
    }
    
    // Check vendor_products table structure (which ajax_search.php uses)
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>