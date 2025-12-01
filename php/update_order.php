<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if (!$input || !isset($input['order_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit();
}

$order_id = intval($input['order_id']);
$action = $input['action'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Call the Python order manager
    $python_cmd = "python order_manager.py --order-id $order_id --action $action";
    $output = shell_exec($python_cmd . ' 2>&1');
    
    // Parse the output to see if it was successful
    if (strpos($output, '✅') !== false) {
        echo json_encode([
            'success' => true,
            'message' => 'Order updated successfully',
            'output' => $output
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update order: ' . $output
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>