<?php
// quick_cleanup_products_duplicates_by_item_code.php
require_once 'environment_config.php';
$pdo = createPDOConnection();

// 1. Find the lowest id for each item_code
$sql = "SELECT item_code, MIN(id) as min_id FROM products GROUP BY item_code";
$stmt = $pdo->query($sql);
$keep = [];
foreach ($stmt as $row) {
    $keep[$row['item_code']] = $row['min_id'];
}

// 2. For all other ids with the same item_code, update customer_items to point to the min_id
$sql = "SELECT id, item_code FROM products";
$stmt = $pdo->query($sql);
$update_count = 0;
foreach ($stmt as $row) {
    $id = $row['id'];
    $item_code = $row['item_code'];
    if ($id != $keep[$item_code]) {
        $min_id = $keep[$item_code];
        $update_count += $pdo->exec("UPDATE customer_items SET product_id = $min_id WHERE product_id = $id");
    }
}

// 3. Delete all products where id is not the min_id for their item_code
$ids_to_keep = implode(',', array_map('intval', array_values($keep)));
$del_sql = "DELETE FROM products WHERE id NOT IN ($ids_to_keep)";
$deleted = $pdo->exec($del_sql);
echo "Updated $update_count customer_items.\n";
echo "Deleted $deleted duplicate products.\n";
