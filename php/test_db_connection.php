<?php
// Test database connection and Thumann products
try {
    echo "=== Database Connection Test ===" . PHP_EOL;
    
    // Test local database connection
    $pdo = new PDO('mysql:host=localhost;dbname=orders', 'root', '');
    echo "✅ Connected to local 'orders' database" . PHP_EOL;
    
    // Check if products table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Products table exists" . PHP_EOL;
        
        // Count all products
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        $total = $stmt->fetchColumn();
        echo "📦 Total products: $total" . PHP_EOL;
        
        // Count Thumann products
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE name LIKE '%thumann%' OR sku LIKE '%thumann%'");
        $thumann = $stmt->fetchColumn();
        echo "🥩 Thumann products: $thumann" . PHP_EOL;
        
        // Show sample Thumann products
        if ($thumann > 0) {
            echo PHP_EOL . "=== Sample Thumann Products ===" . PHP_EOL;
            $stmt = $pdo->query("SELECT sku, name, price, uom FROM products WHERE name LIKE '%thumann%' OR sku LIKE '%thumann%' LIMIT 5");
            while ($row = $stmt->fetch()) {
                echo "{$row['sku']} | {$row['name']} | {$row['price']} | {$row['uom']}" . PHP_EOL;
            }
        }
    } else {
        echo "❌ Products table does not exist" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    
    // Try alternative database name
    try {
        echo PHP_EOL . "Trying alternative database 'orders_db'..." . PHP_EOL;
        $pdo = new PDO('mysql:host=localhost;dbname=orders_db', 'root', '');
        echo "✅ Connected to 'orders_db' database" . PHP_EOL;
    } catch (Exception $e2) {
        echo "❌ Could not connect to orders_db either: " . $e2->getMessage() . PHP_EOL;
    }
}
?>