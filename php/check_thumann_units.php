<?php
require 'environment_config.php';

try {
    // Use global database variables from environment config
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare('SELECT p.item_code, p.description, u.code AS unit, p.vendor FROM products p LEFT JOIN uom u ON p.uom_id = u.id WHERE p.vendor = ? AND p.is_active = 1 LIMIT 10');
    $stmt->execute(['Thumanns']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Thumann products in database:\n";
    foreach($results as $row) {
        echo $row['item_code'] . ' | ' . substr($row['description'], 0, 40) . ' | Unit: ' . $row['unit'] . "\n";
    }
    
    // Also check the CSV import process
    echo "\nChecking consolidated CSV file...\n";
    $csvFile = '../Vendor_Price_Import/master_vendor_prices.csv';
    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle);
        echo "CSV Headers: " . implode(', ', $header) . "\n";
        
        // Find a few Thumann entries
        $count = 0;
        while (($data = fgetcsv($handle)) !== FALSE && $count < 5) {
            if ($data[5] == 'Thumanns') { // assuming vendor is column 5
                echo implode(' | ', $data) . "\n";
                $count++;
            }
        }
        fclose($handle);
    } else {
        echo "Master CSV not found at: $csvFile\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>