<?php
// This script is deprecated. All vendor product import logic now uses the products table. Please use import_master_products_progress.php or import_thumanns_fast.php for current imports.
// ...existing code...
    
try {
    // Show statistics
    // getImportStats($pdo); // Disabled: function not defined
    
    echo "\n✅ Vendor products import completed successfully!\n";
    
    // If called via POST, return JSON response
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'imported' => $imported,
            'message' => 'Import completed successfully'
        ]);
    }
} catch (Exception $e) {
    echo "❌ Import failed: " . $e->getMessage() . "\n";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>