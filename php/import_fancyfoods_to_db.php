<?php
// Fancy Foods CSV Import to MySQL Products Table
// CONFIGURE THESE
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'orders';
$csv_path = __DIR__ . '/../data/excel_price_sheets/fancyfoods/fancyfoods_extracted.csv';
$vendor = 'Fancy Foods';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8');

function get_uom_id($conn, $uom_code) {
    $uom_code = strtoupper(trim($uom_code));
    if ($uom_code === '') $uom_code = 'LB';
    $stmt = $conn->prepare('SELECT id FROM uom WHERE code = ?');
    $stmt->bind_param('s', $uom_code);
    $stmt->execute();
    $stmt->bind_result($id);
    if ($stmt->fetch()) {
        $stmt->close();
        return $id;
    }
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO uom (code, name, description) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $uom_code, $uom_code, $uom_code);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    return $new_id;
}

function upsert_product($conn, $row, $uom_id, $vendor) {
    $price = is_numeric($row['Price']) ? $row['Price'] : 0.00;
    $stmt = $conn->prepare('INSERT INTO products (item_code, description, price, vendor, category, subcategory, uom_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
        ON DUPLICATE KEY UPDATE description = VALUES(description), price = VALUES(price), category = VALUES(category), subcategory = VALUES(subcategory), uom_id = VALUES(uom_id), is_active = TRUE');
    $stmt->bind_param('ssdsssi', $row['Item Code'], $row['Description'], $price, $vendor, $row['Category'], $row['Subcategory'], $uom_id);
    $stmt->execute();
    $stmt->close();
}

if (($handle = fopen($csv_path, 'r')) !== FALSE) {
    $header = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== FALSE) {
        $row = array_combine($header, $data);
        $uom_id = get_uom_id($conn, $row['UOM']);
        upsert_product($conn, $row, $uom_id, $vendor);
    }
    fclose($handle);
    echo "Import complete.\n";
} else {
    echo "Failed to open CSV file.\n";
}
$conn->close();
?>
