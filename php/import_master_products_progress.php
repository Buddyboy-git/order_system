<?php
/**
 * Improved Import with Progress Tracking
 * Handles large CSV imports with real-time progress updates
 */

// Increase limits for large import
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '256M');

require_once 'db_config.php';

// Flush output immediately for progress display
if (ob_get_level()) ob_end_clean();
echo "<html><head><title>Product Import Progress</title></head><body>";
echo "<h2>Product Import in Progress</h2>";
echo "<div id='progress'></div>";
echo "<script>function updateProgress(msg) { document.getElementById('progress').innerHTML += msg + '<br>'; }</script>";
flush();

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<script>updateProgress('✓ Connected to database successfully');</script>";
    flush();
    
    // Check CSV file
    $csvFile = 'master_vendor_prices.csv';
    if (!file_exists($csvFile)) {
        die("Error: master_vendor_prices.csv not found");
    }
    
    echo "<script>updateProgress('✓ Found master_vendor_prices.csv file');</script>";
    flush();
    
    // Get existing UOMs
    $uomMap = [];
    $uomStmt = $pdo->prepare("SELECT id, code FROM uom");
    $uomStmt->execute();
    while ($row = $uomStmt->fetch()) {
        $uomMap[$row['code']] = $row['id'];
    }
    
    echo "<script>updateProgress('✓ Loaded existing UOMs: " . count($uomMap) . " found');</script>";
    flush();
    
    // Prepare statements
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
    
    $uomInsertStmt = $pdo->prepare("INSERT INTO uom (code, name) VALUES (?, ?)");
    
    // Open CSV
    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle); // Skip header
    
    $imported = 0;
    $errors = 0;
    $batchSize = 100;
    
    echo "<script>updateProgress('Starting import...');</script>";
    flush();
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        try {
            $item_code = trim($row[0]);
            $dc = trim($row[1]);
            $description = trim($row[2]);
            $price = floatval($row[3]);
            $unit = trim($row[4]);
            $category = trim($row[5]);
            
            if (empty($item_code)) continue;
            
            // Get or create UOM
            $uom_id = 1; // Default
            if (!empty($unit)) {
                if (isset($uomMap[$unit])) {
                    $uom_id = $uomMap[$unit];
                } else {
                    // Create new UOM
                    try {
                        $uomInsertStmt->execute([$unit, $unit]);
                        $uom_id = $pdo->lastInsertId();
                        $uomMap[$unit] = $uom_id;
                    } catch (Exception $e) {
                        // UOM might already exist, try to get it
                        $stmt = $pdo->prepare("SELECT id FROM uom WHERE code = ?");
                        $stmt->execute([$unit]);
                        $uom_id = $stmt->fetchColumn() ?: 1;
                        $uomMap[$unit] = $uom_id;
                    }
                }
            }
            
            // Insert product
            $insertStmt->execute([
                $item_code,
                $description,
                $price,
                $uom_id,
                $category,
                $dc
            ]);
            
            $imported++;
            
            // Show progress every batch
            if ($imported % $batchSize == 0) {
                echo "<script>updateProgress('Imported $imported products...');</script>";
                flush();
            }
            
        } catch (Exception $e) {
            $errors++;
            if ($errors <= 5) { // Show first 5 errors
                echo "<script>updateProgress('⚠ Error: " . addslashes($e->getMessage()) . "');</script>";
                flush();
            }
        }
    }
    
    fclose($handle);
    
    echo "<script>updateProgress('=== IMPORT COMPLETE ===');</script>";
    echo "<script>updateProgress('✓ Successfully imported: $imported products');</script>";
    echo "<script>updateProgress('⚠ Errors encountered: $errors');</script>";
    
    // Final count
    $totalCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<script>updateProgress('📊 Total products in database: $totalCount');</script>";
    
    // Show samples
    echo "<script>updateProgress('📋 Sample imported products:');</script>";
    $samples = $pdo->query("SELECT item_code, description, price, vendor FROM products ORDER BY created_at DESC LIMIT 5")->fetchAll();
    foreach ($samples as $sample) {
        $msg = "• {$sample['item_code']}: " . substr($sample['description'], 0, 50) . "... - \${$sample['price']} ({$sample['vendor']})";
        echo "<script>updateProgress('" . addslashes($msg) . "');</script>";
    }
    
    echo "<script>updateProgress('🎉 Import completed successfully!');</script>";
    echo "<script>updateProgress('<a href=\"admin.html\">← Go to Admin Dashboard</a>');</script>";
    
} catch (Exception $e) {
    echo "<script>updateProgress('❌ Error: " . addslashes($e->getMessage()) . "');</script>";
}

echo "</body></html>";
?>