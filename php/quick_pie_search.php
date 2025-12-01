<?php
// quick_pie_search.php
// Search for PIE in item_code or description in products table
require_once 'db_config.php';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT item_code, description, vendor FROM products WHERE description LIKE '%PIE%' OR item_code LIKE '%PIE%' LIMIT 20";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) === 0) {
        echo "No results found for 'PIE'.\n";
    } else {
        foreach ($results as $row) {
            echo $row['item_code'] . " | " . $row['description'] . " | " . $row['vendor'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
