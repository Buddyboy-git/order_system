<?php
$pdo = new PDO('mysql:host=localhost;dbname=orders_db;charset=utf8mb4', 'root', '');
foreach ($pdo->query('DESCRIBE products') as $row) {
    echo $row['Field'] . "\n";
}
?>
