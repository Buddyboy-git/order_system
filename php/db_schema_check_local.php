<?php
$pdo = new PDO('mysql:host=localhost;dbname=orders;charset=utf8mb4', 'root', '');
echo "Tables in orders DB:\n";
foreach ($pdo->query('SHOW TABLES') as $row) {
    echo $row[0] . "\n";
}
echo "\nColumns in products table:\n";
foreach ($pdo->query('DESCRIBE products') as $row) {
    echo $row['Field'] . "\n";
}
?>
