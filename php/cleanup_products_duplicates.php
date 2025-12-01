<?php
// cleanup_products_duplicates.php
// Removes duplicate products, keeping only the row with the lowest id for each (item_code, vendor) pair.

require_once 'db_config.php';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database.\n";

    // Find and delete duplicates, keeping the lowest id for each (item_code, vendor)
    $sql = "DELETE p1 FROM products p1
            INNER JOIN products p2
            ON p1.item_code = p2.item_code
            AND ((p1.vendor IS NULL AND p2.vendor IS NULL) OR (p1.vendor = p2.vendor))
            AND p1.id > p2.id";
    $deleted = $pdo->exec($sql);

    echo "Deleted $deleted duplicate rows from products table.\n";

    // Show remaining count
    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "Remaining products: $count\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
