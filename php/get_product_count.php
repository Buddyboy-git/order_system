<?php
/**
 * Get Product Count API
 * Returns the current total number of products in the database
 */

require_once 'environment_config.php';

try {
    $pdo = createPDOConnection();
    
    // Get total product count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $result['total'];
    // Get count by vendor
    $stmt = $pdo->query("SELECT vendor, COUNT(*) as count FROM products WHERE is_active = 1 GROUP BY vendor ORDER BY count DESC");
    $vendorCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'total' => $totalProducts,
        'vendors' => $vendorCounts,
        'formatted' => number_format($totalProducts)
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
}
?>