<?php
// Helper to resolve data paths robustly
function workspace_path($relative) {
    return realpath(__DIR__ . '/../' . ltrim($relative, '/\\')) ?: (__DIR__ . '/../' . ltrim($relative, '/\\'));
}
// Command-line version of Thumanns import
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== Thumanns Products Import ===" . PHP_EOL;
echo "Timestamp: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Database connection
$dsn = 'mysql:host=localhost;dbname=orders;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully" . PHP_EOL;
} catch (PDOException $e) {
    die("❌ DB connection failed: " . $e->getMessage() . PHP_EOL);
}

// Look for CSV file


$csvFile = workspace_path('data/thumasteritems-current.csv');
if (!file_exists($csvFile)) {
    die("❌ CSV file not found: $csvFile" . PHP_EOL);
}

echo "📁 Found CSV file: $csvFile" . PHP_EOL;
echo "📦 File size: " . round(filesize($csvFile) / 1024, 2) . " KB" . PHP_EOL;

if (($handle = fopen($csvFile, 'r')) !== false) {
    $headers = fgetcsv($handle); // skip header row
    echo "📋 CSV Headers: " . implode(', ', $headers) . PHP_EOL;
    
    $imported = 0;
    $errors = 0;
    $startTime = microtime(true);
    
    while (!feof($handle)) {
        $row = fgetcsv($handle);
        if ($row === false || count($row) < 4) continue;

        try {
            // CSV columns: Item #, Description, Price, PriceUnit, PickUnit, Avg Weight
            $sku = $row[0];
            $name = $row[1];
            $price = str_replace(['$', ','], '', $row[2]);
            $price = floatval($price);
            $price_unit = isset($row[3]) ? $row[3] : '';

            // Map UOM string to ID using PriceUnit column
            $uom_map = [
                'EA' => 1,  // Each
                'PC' => 2,  // Piece
                'LB' => 3,  // Pound
                'CS' => 4,  // Case
                'GAL' => 20, // Gallon
                'QT' => 9,  // Quart
                'PT' => 7,  // Pint
                'OZ' => 0,  // Ounce (add correct ID if exists)
                'DZ' => 5,  // Dozen
                // Add more as needed
            ];
            $uom_id = isset($uom_map[strtoupper($price_unit)]) ? $uom_map[strtoupper($price_unit)] : 3; // Default to LB

            // Always set vendor to 'Thumanns' for this import
            $vendor = 'Thumanns';

            $stmt = $pdo->prepare("
                INSERT INTO products (item_code, description, price, uom_id, vendor)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    price = VALUES(price),
                    uom_id = VALUES(uom_id),
                    vendor = VALUES(vendor),
                    created_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([$sku, $name, $price, $uom_id, $vendor]);
            $imported++;

            if ($imported % 100 == 0) {
                echo "📦 Imported $imported products..." . PHP_EOL;
            }

        } catch (Exception $e) {
            $errors++;
            if ($errors < 5) { // Only show first few errors
                echo "⚠️ Error importing row: " . $e->getMessage() . PHP_EOL;
            }
        }
    }
    
    fclose($handle);
    $duration = round(microtime(true) - $startTime, 2);
    
    echo PHP_EOL . "✅ Import complete!" . PHP_EOL;
    echo "📊 Results:" . PHP_EOL;
    echo "   - Products imported: $imported" . PHP_EOL;
    echo "   - Errors: $errors" . PHP_EOL;
    echo "   - Duration: {$duration}s" . PHP_EOL;
    
} else {
    echo "❌ Failed to open CSV file." . PHP_EOL;
}
?>