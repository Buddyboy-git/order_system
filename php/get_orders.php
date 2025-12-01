<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$status_filter = $_GET['status'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Build query with optional status filter
    $where_clause = "WHERE o.status != 'archived'";
    $params = [];
    
    if ($status_filter) {
        $where_clause .= " AND o.status = ?";
        $params[] = $status_filter;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            c.name as customer_name,
            c.code as customer_code,
            o.order_date,
            o.status,
            o.total_amount,
            o.created_at,
            o.updated_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $where_clause
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    foreach ($orders as &$order) {
        $order['total_amount'] = floatval($order['total_amount']);
        $order['item_count'] = intval($order['item_count']);
        $order['order_date'] = date('M j, Y', strtotime($order['order_date']));
        $order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));
    }
    
    echo json_encode($orders);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>