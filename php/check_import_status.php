<?php
// Check what happened with the Thumann import
try {
    $pdo = new PDO('mysql:host=localhost;dbname=orders', 'root', '');
    
    echo "=== Thumann Import Status Check ===" . PHP_EOL;
    
    // Total Thumann products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'");
    $total = $stmt->fetchColumn();
    echo "Total Thumann products: $total" . PHP_EOL;
    
    // Products added today
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE (description LIKE '%thumann%' OR vendor LIKE '%thumann%') AND DATE(created_at) = CURDATE()");
    $today = $stmt->fetchColumn();
    echo "Added today: $today" . PHP_EOL;
    
    // Last import timestamp
    $stmt = $pdo->query("SELECT MAX(created_at) as last_import FROM products WHERE description LIKE '%thumann%' OR vendor LIKE '%thumann%'");
    $row = $stmt->fetch();
    echo "Last import: " . ($row['last_import'] ?? 'Never') . PHP_EOL;
    
    // Check if import_thumanns.php exists and what it does
    echo PHP_EOL . "=== Import Script Status ===" . PHP_EOL;
    $import_script = 'd:/xampp/htdocs/orders/import_thumanns_cli.php';
    if (file_exists($import_script)) {
        echo "✅ Import script exists: $import_script" . PHP_EOL;
    } else {
        echo "❌ Import script not found: $import_script" . PHP_EOL;
        
        // Check alternative
        $alt_script = 'd:/xampp/htdocs/orders/import_thumanns.php';
        if (file_exists($alt_script)) {
            echo "✅ Alternative script exists: $alt_script" . PHP_EOL;
        }
    }
    
    // Check for CSV files
    echo PHP_EOL . "=== CSV File Status ===" . PHP_EOL;
    $csv_path = 'd:/Users/miket/Python_Projects/Vendor_Price_Import/excel_price_sheets/current/thumanns_current.csv';
    if (file_exists($csv_path)) {
        $size = filesize($csv_path);
        $modified = date('Y-m-d H:i:s', filemtime($csv_path));
        echo "✅ CSV file exists: " . basename($csv_path) . PHP_EOL;
        echo "   Size: " . round($size/1024, 1) . " KB" . PHP_EOL;
        echo "   Modified: $modified" . PHP_EOL;
    } else {
        echo "❌ CSV file not found: $csv_path" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
?>