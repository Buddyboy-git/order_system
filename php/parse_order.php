<?php
/**
 * Order Entry System - Parse Order API
 * Handles shorthand order parsing requests from the web interface
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

if (!$data || !isset($data['input'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$shorthandInput = $data['input'];

try {
    // Call Python parser
    $pythonScript = __DIR__ . '/parse_shorthand.py';
    $command = "python \"$pythonScript\" " . escapeshellarg($shorthandInput);
    
    // Execute command and capture output
    $output = shell_exec($command . ' 2>&1');
    
    if ($output === null) {
        throw new Exception('Failed to execute Python parser');
    }
    
    // Try to decode JSON output
    $result = json_decode($output, true);
    
    if ($result === null) {
        throw new Exception('Invalid JSON output from parser: ' . $output);
    }
    
    // Return parsed result
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'customer' => [
            'customer_name' => '',
            'customer_code' => '',
            'confidence' => 0,
            'alternatives' => []
        ],
        'items' => [],
        'parsing_errors' => ['Error: ' . $e->getMessage()]
    ]);
}
?>