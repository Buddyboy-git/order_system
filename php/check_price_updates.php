<?php
// Check for Thumann price updates, not just new products
try {
    $pdo = new PDO('mysql:host=localhost;dbname=orders', 'root', '');
    
    echo "=== Thumann Price Update Analysis ===" . PHP_EOL;
    
    // Total Thumann products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'");
    $total = $stmt->fetchColumn();
    echo "Total Thumann products: $total" . PHP_EOL;
    
    // Products updated today (not just created, but ANY update)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM products 
        WHERE (description LIKE '%thumann%' OR vendor LIKE '%thumann%') 
        AND (DATE(created_at) = CURDATE() OR DATE(updated_at) = CURDATE())
    ");
    $updated_today = $stmt->fetchColumn();
    echo "Products updated today: $updated_today" . PHP_EOL;
    
    // Check if there's an updated_at column
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'updated_at'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Updated_at column exists" . PHP_EOL;
        
        // Last update timestamp
        $stmt = $pdo->query("
            SELECT MAX(GREATEST(created_at, IFNULL(updated_at, created_at))) as last_change 
            FROM products 
            WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'
        ");
        $row = $stmt->fetch();
        echo "Last change: " . ($row['last_change'] ?? 'Never') . PHP_EOL;
    } else {
        echo "⚠️ No updated_at column - can't track price changes" . PHP_EOL;
        
        // Just show most recent created_at
        $stmt = $pdo->query("
            SELECT MAX(created_at) as last_import 
            FROM products 
            WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'
        ");
        $row = $stmt->fetch();
        echo "Last import: " . ($row['last_import'] ?? 'Never') . PHP_EOL;
    }
    
    // Sample of recent Thumann products with prices
    echo PHP_EOL . "=== Sample Thumann Products ===" . PHP_EOL;
    $stmt = $pdo->query("
        SELECT item_code, LEFT(description, 50) as description, price, created_at
        FROM products 
        WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch()) {
        echo sprintf("%-10s | %-50s | $%-7.2f | %s", 
            $row['item_code'], 
            $row['description'], 
            $row['price'], 
            $row['created_at']
        ) . PHP_EOL;
    }
    
    // Check import script to see what it actually does
    echo PHP_EOL . "=== Import Script Analysis ===" . PHP_EOL;
    $import_script = 'd:/xampp/htdocs/orders/import_thumanns_cli.php';
    if (file_exists($import_script)) {
        echo "Checking what the import script does..." . PHP_EOL;
        $content = file_get_contents($import_script);
        
        if (strpos($content, 'ON DUPLICATE KEY UPDATE') !== false) {
            echo "✅ Script uses ON DUPLICATE KEY UPDATE - should update prices" . PHP_EOL;
        } else {
            echo "⚠️ Script may not update existing products" . PHP_EOL;
        }
        
        if (strpos($content, 'updated_at') !== false) {
            echo "✅ Script updates timestamp on changes" . PHP_EOL;
        } else {
            echo "⚠️ Script doesn't track update timestamps" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
?>