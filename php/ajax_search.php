<?php
require_once 'environment_config.php';
require_once 'universal_search.php';

try {
    $pdo = createPDOConnection();
} catch (PDOException $e) {
    die("DB connection failed.");
}

$query = $_GET['q'] ?? '';
$sortBy = $_GET['sort'] ?? 'description';
$sortOrder = $_GET['order'] ?? 'ASC';
$filterDC = $_GET['dc'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 100;

// Use universal_search for robust, deduplicated search
$searchOpts = [
    'vendor' => $filterDC,
    'sort' => $sortBy,
    'order' => $sortOrder,
    'perPage' => $perPage,
    'page' => $page
];
$result = universal_search($pdo, 'products', $query, null, $searchOpts);

// DEBUG: Print SQL and params if available from universal_search
if (isset($result['debug_sql']) || isset($result['debug_params'])) {
    echo "<div style='color:blue'>DEBUG SQL: [" . htmlspecialchars($result['debug_sql'] ?? '') . "]<br>Params: [" . htmlspecialchars(json_encode($result['debug_params'] ?? [])) . "]</div>";
}
// DEBUG: Show the raw query value
echo "<div style='color:red'>DEBUG: Query received: [" . htmlspecialchars($query) . "]</div>";

if (!empty($result['results'])) {
    echo "<table border='1'><tr><th>Item Code</th><th>Description</th><th>Price</th><th>Unit</th><th>Vendor</th><th>Category</th></tr>";
    foreach ($result['results'] as $row) {
        $price = number_format((float)$row['price'], 2);
        $description = htmlspecialchars($row['description']);
        $category = htmlspecialchars($row['category'] ? $row['category'] : 'General');
        $unit = htmlspecialchars($row['unit'] ? $row['unit'] : 'EA');
        $vendor = htmlspecialchars($row['vendor'] ? $row['vendor'] : 'Unknown');
        echo "<tr>
            <td>{$row['item_code']}</td>
            <td>{$description}</td>
            <td>\${$price}</td>
            <td>{$unit}</td>
            <td>{$vendor}</td>
            <td><span class=\"category\">{$category}</span></td>
          </tr>";
    }
    echo "</table>";
} else {
    echo "No results found.";
}
?>