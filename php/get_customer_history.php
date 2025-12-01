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

$customer_id = $_GET['customer_id'] ?? '';

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get customer stats
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_spent,
            COALESCE(AVG(total_amount), 0) as avg_order,
            MAX(order_date) as last_order
        FROM order_history 
        WHERE customer_id = ?
        
        UNION ALL
        
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_spent,
            COALESCE(AVG(total_amount), 0) as avg_order,
            MAX(order_date) as last_order
        FROM orders 
        WHERE customer_id = ? AND status != 'draft'
    ");
    $stats_stmt->execute([$customer_id, $customer_id]);
    $stats_results = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine stats from both tables
    $total_orders = 0;
    $total_spent = 0;
    $last_order = null;
    
    foreach ($stats_results as $row) {
        $total_orders += intval($row['total_orders']);
        $total_spent += floatval($row['total_spent']);
        if ($row['last_order'] && ($last_order === null || $row['last_order'] > $last_order)) {
            $last_order = $row['last_order'];
        }
    }
    
    $avg_order = $total_orders > 0 ? $total_spent / $total_orders : 0;
    
    $stats = [
        'total_orders' => $total_orders,
        'total_spent' => $total_spent,
        'avg_order' => $avg_order,
        'last_order' => $last_order ? date('M j, Y', strtotime($last_order)) : null
    ];
    
    // Get order history (both archived and current orders)
    $orders_stmt = $pdo->prepare("
        (
            SELECT 
                oh.id,
                oh.order_number,
                oh.customer_id,
                c.name as customer_name,
                c.code as customer_code,
                oh.order_date,
                'archived' as status,
                oh.total_amount,
                oh.archived_at as created_at,
                oh.archived_at as updated_at,
                oh.item_count
            FROM order_history oh
            LEFT JOIN customers c ON oh.customer_id = c.id
            WHERE oh.customer_id = ?
        )
        UNION ALL
        (
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
            WHERE o.customer_id = ? AND o.status != 'draft'
            GROUP BY o.id
        )
        ORDER BY order_date DESC, created_at DESC
    ");
    
    $orders_stmt->execute([$customer_id, $customer_id]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    foreach ($orders as &$order) {
        $order['total_amount'] = floatval($order['total_amount']);
        $order['item_count'] = intval($order['item_count']);
        $order['order_date'] = date('M j, Y', strtotime($order['order_date']));
        $order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));
    }
    
    echo json_encode([
        'stats' => $stats,
        'orders' => $orders
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>