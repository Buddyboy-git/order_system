<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dsn = 'mysql:host=localhost;dbname=orders_db;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv']['tmp_name'];
    if (($handle = fopen($file, 'r')) !== false) {
        $headers = fgetcsv($handle); // skip header row
        while (!feof($handle)) {
            $row = fgetcsv($handle);
            if ($row === false || count($row) < 4) continue;

            [$sku, $name, $price, $uom] = $row;
            $price = str_replace(['$', ','], '', $price); // Strip dollar sign and commas
            $price = floatval($price); // Convert to float for DB insert
            $stmt = $pdo->prepare("
                -- Upsert now works on (item_code, vendor) due to unique constraint
                INSERT INTO products (item_code, description, price, uom_id, vendor)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    price = VALUES(price),
                    uom_id = VALUES(uom_id),
                    vendor = 'Thumanns',
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$sku, $name, $price, $uom]);
        }
        fclose($handle);
        echo "Import complete.";
    } else {
        echo "Failed to open CSV.";
    }
}
?>