<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['parsed_order'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit();
}

$parsed_order = $input['parsed_order'];
$action = $input['action'] ?? 'save_draft';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate order number
    $order_date = date('Y-m-d');
    $order_number = generateOrderNumber($pdo, $order_date);
    
    // Determine status based on action
    $status = ($action === 'save_and_submit') ? 'submitted' : 'draft';
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($parsed_order['items'] as $item) {
        $total_amount += ($item['quantity'] ?? 1) * ($item['price'] ?? 0);
    }
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, customer_id, order_date, status, 
                           total_amount, raw_input, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $customer_id = $parsed_order['customer']['customer_id'] ?? null;
    $raw_input = $parsed_order['raw_input'] ?? '';
    
    $stmt->execute([
        $order_number,
        $customer_id,
        $order_date,
        $status,
        $total_amount,
        $raw_input
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Insert order items
    $item_stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, 
                                unit_price, total_price, raw_input, confidence)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($parsed_order['items'] as $item) {
        $quantity = $item['quantity'] ?? 1;
        $unit_price = $item['price'] ?? 0;
        $total_price = $quantity * $unit_price;
        
        $item_stmt->execute([
            $order_id,
            $item['product_id'] ?? null,
            $item['product_name'] ?? $item['raw_input'],
            $quantity,
            $unit_price,
            $total_price,
            $item['raw_input'] ?? '',
            $item['confidence'] ?? 0
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'status' => $status,
        'message' => 'Order saved successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateOrderNumber($pdo, $order_date) {
    // Format: YYYYMMDD-XXXX (where XXXX is sequential number for the day)
    $date_prefix = str_replace('-', '', $order_date);
    
    // Get the last order number for today
    $stmt = $pdo->prepare("
        SELECT order_number 
        FROM orders 
        WHERE order_number LIKE ? 
        ORDER BY order_number DESC 
        LIMIT 1
    ");
    $stmt->execute([$date_prefix . '-%']);
    
    $last_order = $stmt->fetchColumn();
    
    if ($last_order) {
        // Extract the sequence number and increment
        $sequence = intval(substr($last_order, -4)) + 1;
    } else {
        $sequence = 1;
    }
    
    return $date_prefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
?>