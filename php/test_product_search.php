<?php
$pdo = new PDO('mysql:host=localhost;dbname=orders_db;charset=utf8mb4', 'root', '');

$queries = [
    "SELECT id, sku, name FROM products WHERE name LIKE '%turkey%' OR sku LIKE '%turkey%' OR category LIKE '%turkey%' LIMIT 10",
    "SELECT id, sku, name FROM products WHERE name LIKE '%PIE%' OR sku LIKE '%PIE%' OR category LIKE '%PIE%' LIMIT 10"
];

foreach ($queries as $sql) {
    echo "\nQuery: $sql\n";
    foreach ($pdo->query($sql) as $row) {
        echo $row['id'] . ' | ' . $row['sku'] . ' | ' . $row['name'] . "\n";
    }
}
?>
