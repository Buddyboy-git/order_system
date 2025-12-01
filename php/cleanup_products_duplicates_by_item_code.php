<?php
// cleanup_products_duplicates_by_item_code.php
require_once 'environment_config.php';
$pdo = createPDOConnection();
$sql = "DELETE FROM products WHERE id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM products GROUP BY item_code) as t)";
$count = $pdo->exec($sql);
echo "Deleted $count duplicate rows by item_code from products table.\n";
