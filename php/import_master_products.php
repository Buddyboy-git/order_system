<?php
/**
 * Import Master Vendor Prices into Products Table
 * Local version for XAMPP/orders database
 */

require_once 'db_config.php';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Check if master_vendor_prices.csv exists
    $csvFile = 'master_vendor_prices.csv';
    if (!file_exists($csvFile)) {
        die("Error: master_vendor_prices.csv not found in current directory.\n");
    }
    
    echo "Found master_vendor_prices.csv file.\n";
    
    // Clear existing products (optional - comment out if you want to keep existing)
    $pdo->exec("DELETE FROM products WHERE item_code NOT IN ('TURKEY01', 'SALAMI01', 'ROASTBEEF01')");
    echo "Cleared existing products (kept test products).\n";
    
    // Get or create UOM mappings
    $uomMap = [];
    $uomStmt = $pdo->prepare("SELECT id, code FROM uom");
    $uomStmt->execute();
    while ($row = $uomStmt->fetch()) {
        $uomMap[$row['code']] = $row['id'];
    }
    
    // Function to get UOM ID, create if doesn't exist
    function getUOMId($unit, $pdo, &$uomMap) {
        if (empty($unit)) return 1; // Default UOM
        
        if (isset($uomMap[$unit])) {
            return $uomMap[$unit];
        }
        
        // Create new UOM
        $stmt = $pdo->prepare("INSERT INTO uom (code, name) VALUES (?, ?)");
        $stmt->execute([$unit, $unit]);
        $id = $pdo->lastInsertId();
        $uomMap[$unit] = $id;
        return $id;
    }
    
    // Prepare insert statement (matching actual table structure)
    // Upsert now works on (item_code, vendor) due to unique constraint
    $insertStmt = $pdo->prepare("
        INSERT INTO products (item_code, description, price, uom_id, category, vendor, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            price = VALUES(price),
            uom_id = VALUES(uom_id),
            category = VALUES(category),
            vendor = VALUES(vendor)
    ");
    
    // Open and read CSV file
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        die("Error: Could not open CSV file.\n");
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    echo "CSV Headers: " . implode(', ', $header) . "\n";
    
    $imported = 0;
    $errors = 0;
    
    // Process each row
    while (($row = fgetcsv($handle)) !== FALSE) {
        try {
            // Extract data from CSV row
            $item_code = trim($row[0]);
            $dc = trim($row[1]);
            $description = trim($row[2]);
            $price = floatval($row[3]);
            $unit = trim($row[4]);
            $category = trim($row[5]);
            $subcategory = trim($row[6]);
            
            // Skip empty item codes
            if (empty($item_code)) {
                continue;
            }
            
            // Get UOM ID for the unit
            $uom_id = getUOMId($unit, $pdo, $uomMap);
            
            // Insert into products table (matching actual structure)
            // Use dc (vendor) as vendor field for upsert
            $insertStmt->execute([
                $item_code,
                $description,
                $price,
                $uom_id,
                $category,
                $dc
            ]);
            
            $imported++;
            
            // Show progress every 1000 records
            if ($imported % 1000 == 0) {
                echo "Imported $imported products...\n";
            }
            
        } catch (Exception $e) {
            $errors++;
            if ($errors < 10) { // Only show first 10 errors
                echo "Error importing row: " . $e->getMessage() . "\n";
                echo "Row data: " . implode(', ', $row) . "\n";
            }
        }
    }
    
    fclose($handle);
    
    echo "\n=== IMPORT COMPLETE ===\n";
    echo "Successfully imported: $imported products\n";
    echo "Errors encountered: $errors\n";
    
    // Show final count
    $totalCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "Total products in database: $totalCount\n";
    
    // Show sample products
    echo "\nSample imported products:\n";
    $samples = $pdo->query("SELECT item_code, description, price, vendor FROM products ORDER BY created_at DESC LIMIT 5")->fetchAll();
    foreach ($samples as $sample) {
        echo "- {$sample['item_code']}: {$sample['description']} - \${$sample['price']} ({$sample['vendor']})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>