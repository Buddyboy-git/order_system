<?php
$pdo = new PDO('mysql:host=localhost;dbname=orders_db;charset=utf8mb4', 'root', '');

echo "=== Products table (Thumann) ===" . PHP_EOL;
$stmt = $pdo->query("SELECT sku, name, price, uom FROM products WHERE name LIKE '%thumann%' OR sku LIKE '%thumann%' LIMIT 5");
while($row = $stmt->fetch()) { 
    echo $row['sku'] . ' | ' . $row['name'] . ' | ' . $row['price'] . ' | ' . $row['uom'] . PHP_EOL; 
}

echo PHP_EOL . "=== Count by table ===" . PHP_EOL;
echo PHP_EOL . "=== Count by table ===" . PHP_EOL;
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE name LIKE '%thumann%' OR sku LIKE '%thumann%' OR vendor LIKE '%thumann%'");
$products_count = $stmt->fetchColumn();
echo "Products table Thumann records: " . $products_count . PHP_EOL;
?>