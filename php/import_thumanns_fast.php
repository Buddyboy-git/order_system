<?php
// Fast Thumanns import - optimized to prevent hanging
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 5 minutes max

echo "=== Fast Thumanns Import ===" . PHP_EOL;
echo "Timestamp: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Database connection
$dsn = 'mysql:host=localhost;dbname=orders;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected" . PHP_EOL;
} catch (PDOException $e) {
    die("❌ DB connection failed: " . $e->getMessage() . PHP_EOL);
}

// Find CSV file
$csvPaths = [
    'd:/Users/miket/Python_Projects/Vendor_Price_Import/excel_price_sheets/current/thumanns_current.csv',
    'd:/Users/miket/Python_Projects/Vendor_Price_Import/thumanns_current.csv',
    'thumanns_current.csv'
];

$csvFile = null;
foreach ($csvPaths as $path) {
    if (file_exists($path)) {
        $csvFile = $path;
        break;
    }
}

if (!$csvFile) {
    die("❌ CSV file not found in any location" . PHP_EOL);
}

echo "📁 Using: " . basename($csvFile) . PHP_EOL;
echo "📦 Size: " . round(filesize($csvFile) / 1024, 1) . " KB" . PHP_EOL;

// UOM mapping
$uom_map = [
    'LB' => 1, 'EA' => 2, 'CS' => 3, 'OZ' => 4, 
    'GAL' => 5, 'QT' => 6, 'PT' => 7, 'BX' => 3
];

if (($handle = fopen($csvFile, 'r')) !== false) {
    $headers = fgetcsv($handle);
    echo "📋 Headers: " . implode(' | ', array_slice($headers, 0, 4)) . PHP_EOL;
    
    $processed = 0;
    $updated = 0;
    $errors = 0;
    $batch_size = 50;
    
    // Prepare statement once - only set vendor for actual Thumann products
    $stmt = $pdo->prepare("
        INSERT INTO products (item_code, description, price, uom_id, vendor)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            price = VALUES(price),
            uom_id = VALUES(uom_id),
            vendor = VALUES(vendor)
    ");
    
    // Process in batches
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 4) continue;
        
        try {
            $sku = trim($row[0]);
            $name = trim($row[1]);
            $price_str = trim($row[2]);
            $uom = trim($row[3]);
            // If vendor/dc is present in CSV, use it; otherwise default to 'Thumanns'
            $vendor = isset($row[4]) && trim($row[4]) !== '' ? trim($row[4]) : 'Thumanns';

            // Skip empty rows
            if (empty($sku) || empty($name)) continue;

            // Clean price
            $price = (float) str_replace(['$', ','], '', $price_str);

            // Map UOM
            $uom_id = $uom_map[strtoupper($uom)] ?? 1;

            // Execute upsert using both item_code and vendor
            $stmt->execute([$sku, $name, $price, $uom_id, $vendor]);

            if ($stmt->rowCount() > 0) {
                $updated++;
            }

            $processed++;

            // Progress every 100 items
            if ($processed % 100 == 0) {
                echo "📦 Processed: $processed (Updated: $updated)" . PHP_EOL;
            }

        } catch (Exception $e) {
            $errors++;
            if ($errors <= 3) {
                echo "⚠️ Error: " . $e->getMessage() . PHP_EOL;
            }
        }
    }
    
    fclose($handle);
    
    echo PHP_EOL . "✅ Import complete!" . PHP_EOL;
    echo "📊 Results:" . PHP_EOL;
    echo "   - Processed: $processed" . PHP_EOL;
    echo "   - Updated: $updated" . PHP_EOL;
    echo "   - Errors: $errors" . PHP_EOL;
    
} else {
    echo "❌ Failed to open CSV file" . PHP_EOL;
}
?>