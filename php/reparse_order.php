<?php
/**
 * Order Entry System - Reparse Order API
 * Handles customer correction and re-contextualization
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['order']) || !isset($data['new_customer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input - need order and new_customer_id']);
    exit;
}

$order = $data['order'];
$newCustomerId = $data['new_customer_id'];

try {
    // Call Python reparser
    $pythonScript = __DIR__ . '/reparse_with_customer.py';
    $command = "python \"$pythonScript\" " . escapeshellarg(json_encode($order)) . " " . escapeshellarg($newCustomerId);
    
    // Execute command and capture output
    $output = shell_exec($command . ' 2>&1');
    
    if ($output === null) {
        throw new Exception('Failed to execute Python reparser');
    }
    
    // Try to decode JSON output
    $result = json_decode($output, true);
    
    if ($result === null) {
        throw new Exception('Invalid JSON output from reparser: ' . $output);
    }
    
    // Return reparsed result
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'customer' => $order['customer'] ?? [
            'customer_name' => '',
            'confidence' => 0
        ],
        'items' => $order['items'] ?? [],
        'parsing_errors' => ['Reparse error: ' . $e->getMessage()]
    ]);
}
?>