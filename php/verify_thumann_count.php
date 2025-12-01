<?php
// Verify actual Thumann product count
try {
    $pdo = new PDO('mysql:host=localhost;dbname=orders', 'root', '');
    
    echo "=== Thumann Product Count Verification ===" . PHP_EOL;
    
    // Count by different criteria
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE description LIKE '%thumann%'");
    $by_description = $stmt->fetchColumn();
    echo "By description LIKE '%thumann%': $by_description" . PHP_EOL;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE vendor = 'Thumanns'");
    $by_vendor = $stmt->fetchColumn();
    echo "By vendor = 'Thumanns': $by_vendor" . PHP_EOL;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE vendor LIKE '%thumann%'");
    $by_vendor_like = $stmt->fetchColumn();
    echo "By vendor LIKE '%thumann%': $by_vendor_like" . PHP_EOL;
    
    // Count today's updates
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE DATE(created_at) = CURDATE()");
    $today = $stmt->fetchColumn();
    echo "Updated today: $today" . PHP_EOL;
    
    // Total products in database
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total = $stmt->fetchColumn();
    echo "Total products in database: $total" . PHP_EOL;
    
    // Sample of recent products
    echo PHP_EOL . "=== Recent Products ===" . PHP_EOL;
    $stmt = $pdo->query("
        SELECT item_code, LEFT(description, 30) as description, vendor, created_at
        FROM products 
        WHERE DATE(created_at) = CURDATE()
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    // Output all item_codes with vendor = 'Thumanns'
    echo PHP_EOL . "=== All Thumanns Item Codes in DB ===" . PHP_EOL;
    $stmt = $pdo->query("SELECT item_code FROM products WHERE vendor = 'Thumanns' ORDER BY item_code ASC");
    $item_codes = [];
    while ($row = $stmt->fetch()) {
        $item_codes[] = $row['item_code'];
    }
    echo implode(",", $item_codes) . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
?>