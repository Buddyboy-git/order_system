<?php
// Migrate and deduplicate all data from vendor_products to products
// Run this script after updating the products table schema

require_once 'db_config.php';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. For each unique (item_code, dc) in vendor_products, get the most recent/complete row
    $sql = "SELECT * FROM vendor_products vp
            WHERE id = (
                SELECT id FROM vendor_products v2
                WHERE v2.item_code = vp.item_code AND v2.dc = vp.dc
                ORDER BY v2.last_updated DESC, v2.id DESC LIMIT 1
            )";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $imported = 0;
    foreach ($rows as $row) {
        // Map fields
        $item_code = $row['item_code'];
        $description = $row['description'];
        $price = $row['price'];
        $vendor = $row['dc'];
        $category = $row['category'];
        $uom_id = 1; // Default, or map from unit if needed
        $is_active = isset($row['is_active']) ? $row['is_active'] : 1;
        $created_at = isset($row['last_updated']) ? $row['last_updated'] : date('Y-m-d H:i:s');

        // Upsert into products
        $insert = $pdo->prepare("INSERT INTO products (item_code, description, price, vendor, category, uom_id, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                price = VALUES(price),
                category = VALUES(category),
                uom_id = VALUES(uom_id),
                is_active = VALUES(is_active),
                created_at = VALUES(created_at),
                vendor = VALUES(vendor)");
        $insert->execute([$item_code, $description, $price, $vendor, $category, $uom_id, $is_active, $created_at]);
        $imported++;
    }
    echo "Migrated $imported unique vendor_products to products table.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
