<?php
// find_product_duplicates.php
require_once 'environment_config.php';
$pdo = createPDOConnection();
$sql = "SELECT item_code, vendor, COUNT(*) as cnt FROM products GROUP BY item_code, vendor HAVING cnt > 1 ORDER BY cnt DESC, item_code, vendor";
$stmt = $pdo->query($sql);
$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($dupes) {
    echo "Duplicate (item_code, vendor) pairs found:\n";
    foreach ($dupes as $row) {
        echo "{$row['item_code']} | {$row['vendor']} | count: {$row['cnt']}\n";
    }
} else {
    echo "No duplicate (item_code, vendor) pairs found.\n";
}
