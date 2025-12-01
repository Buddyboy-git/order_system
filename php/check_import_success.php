<?php
// Check if Thumann products were actually updated (timestamps changed)
try {
    $pdo = new PDO('mysql:host=localhost;dbname=orders', 'root', '');
    
    echo "=== Thumann Import Success Analysis ===" . PHP_EOL;
    
    // Products with today's created_at timestamp (updated during import)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM products 
        WHERE (description LIKE '%thumann%' OR vendor LIKE '%thumann%') 
        AND DATE(created_at) = CURDATE()
    ");
    $updated_today = $stmt->fetchColumn();
    echo "Products with today's timestamp: $updated_today" . PHP_EOL;
    
    if ($updated_today > 0) {
        echo "✅ SUCCESS! $updated_today Thumann products were updated today" . PHP_EOL;
        echo "   (Import script updates created_at timestamp for price changes)" . PHP_EOL;
        
        // Show some examples
        echo PHP_EOL . "=== Sample Updated Products ===" . PHP_EOL;
        $stmt = $pdo->query("
            SELECT item_code, LEFT(description, 40) as description, price, created_at
            FROM products 
            WHERE (description LIKE '%thumann%' OR vendor LIKE '%thumann%')
            AND DATE(created_at) = CURDATE()
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        while ($row = $stmt->fetch()) {
            echo sprintf("%-8s | %-40s | $%-7.2f | %s", 
                $row['item_code'], 
                $row['description'], 
                $row['price'], 
                date('H:i:s', strtotime($row['created_at']))
            ) . PHP_EOL;
        }
    } else {
        echo "⚠️ No products updated today - checking recent activity..." . PHP_EOL;
        
        // Check last few days
        $stmt = $pdo->query("
            SELECT DATE(created_at) as import_date, COUNT(*) as count
            FROM products 
            WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'
            GROUP BY DATE(created_at)
            ORDER BY import_date DESC
            LIMIT 5
        ");
        
        echo "Recent import dates:" . PHP_EOL;
        while ($row = $stmt->fetch()) {
            echo "  {$row['import_date']}: {$row['count']} products" . PHP_EOL;
        }
    }
    
    // Total count verification
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'");
    $total = $stmt->fetchColumn();
    echo PHP_EOL . "Total Thumann products in database: $total" . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
?>