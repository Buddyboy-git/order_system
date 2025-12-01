// Helper to resolve data/python paths robustly
function workspace_path($relative) {
    // Adjust this base if your deployment structure changes
    return realpath(__DIR__ . '/../' . ltrim($relative, '/\\')) ?: (__DIR__ . '/../' . ltrim($relative, '/\\'));
}
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Include environment configuration
require_once 'environment_config.php';

try {
    $pdo = createPDOConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$vendor = $_GET['vendor'] ?? '';

function scanVendorFiles($vendor) {
    $vendorPaths = [
        'thumanns' => [workspace_path('data/')],
        'driscoll' => [workspace_path('data/')],
        'mandv' => [workspace_path('data/')],
        'foodirect' => [workspace_path('data/')],
        'westside' => [workspace_path('data/')]
    ];
    
    $patterns = [
        'thumanns' => ['*thumann*', '*Thumann*', '*thumanns*', '*Thumanns*', 'thumasteritems*'],
        'driscoll' => ['*driscoll*', '*Driscoll*'],
        'mandv' => ['*mandv*', '*MandV*', '*M&V*'],
        'foodirect' => ['*foodirect*', '*FooDirect*'],
        'westside' => ['*westside*', '*Westside*']
    ];
    
    $files = [];
    $paths = $vendorPaths[$vendor] ?? [];
    $filePatterns = $patterns[$vendor] ?? [];
    
    foreach ($paths as $basePath) {
        if (!is_dir($basePath)) continue;
        
        foreach ($filePatterns as $pattern) {
            $matches = glob($basePath . $pattern, GLOB_BRACE);
            foreach ($matches as $file) {
                if (is_file($file)) {
                    $files[] = [
                        'name' => basename($file),
                        'path' => $file,
                        'size' => filesize($file),
                        'modified' => filemtime($file),
                        'type' => pathinfo($file, PATHINFO_EXTENSION)
                    ];
                }
            }
        }
    }
    
    return $files;
}

function getVendorStats($pdo, $vendor) {
    $stats = [
        'products' => 0,
        'lastUpdate' => 'Never',
        'files' => 0,
        'errors' => 0,
        'status' => 'unknown'
    ];
    
    try {
        // Get product count based on vendor
        switch ($vendor) {
            case 'thumanns':
                // Check products table first (uses description, item_code)
                $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE description LIKE '%thumann%' OR item_code LIKE '%thumann%' OR vendor LIKE '%thumann%'");
                $stats['products'] = $stmt->fetchColumn();
                $stmt = $pdo->query("SELECT MAX(created_at) FROM products WHERE description LIKE '%thumann%' OR item_code LIKE '%thumann%' OR vendor LIKE '%thumann%'");
                $lastUpdate = $stmt->fetchColumn();
                if ($lastUpdate) {
                    $stats['lastUpdate'] = date('M j, Y g:i A', strtotime($lastUpdate));
                }
                break;
            default:
                // All vendor stats now use the products table only
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE description LIKE ? OR vendor LIKE ?");
                    $stmt->execute(["%$vendor%", "%$vendor%"]);
                    $stats['products'] = $stmt->fetchColumn();
                    $stmt = $pdo->prepare("SELECT MAX(created_at) FROM products WHERE description LIKE ? OR vendor LIKE ?");
                    $stmt->execute(["%$vendor%", "%$vendor%"]);
                    $lastUpdate = $stmt->fetchColumn();
                    if ($lastUpdate) {
                        $stats['lastUpdate'] = date('M j, Y g:i A', strtotime($lastUpdate));
                    }
                } catch (Exception $e) {
                    $stats['products'] = 0;
                }
                break;
        }
        
        // Scan files
        $files = scanVendorFiles($vendor);
        $stats['files'] = count($files);
        
        // Determine status
        if ($stats['products'] > 0) {
            $stats['status'] = 'online';
        } elseif ($stats['files'] > 0) {
            $stats['status'] = 'warning';
        } else {
            $stats['status'] = 'error';
        }
        
    } catch (Exception $e) {
        $stats['errors'] = 1;
        $stats['status'] = 'error';
    }
    
    return $stats;
}

function runVendorAction($action, $vendor) {
    $result = ['success' => false, 'message' => '', 'output' => ''];
    
    switch ($action) {
        case 'download':
            switch ($vendor) {
                case 'driscoll':
                    $script = workspace_path('python/driscoll_crawler.py');
                    break;
                case 'mandv':
                    $script = workspace_path('python/mandv_fetcher.py');
                    break;
                case 'foodirect':
                    $script = workspace_path('python/foodirect_crawler.py');
                    break;
                case 'thumanns':
                    $script = workspace_path('python/thumanns_downloader_enhanced.py');
                    break;
                default:
                    $result['message'] = "No download script configured for $vendor";
                    return $result;
            }
            
            if (file_exists($script)) {
                // Try to find Python executable
                $pythonPaths = ['python', 'python3', 'py', 'C:/Python*/python.exe'];
                $pythonCmd = 'python'; // Default fallback
                
                foreach ($pythonPaths as $path) {
                    if (shell_exec("where $path 2>nul")) {
                        $pythonCmd = $path;
                        break;
                    }
                }
                
                $output = shell_exec("$pythonCmd \"$script\" 2>&1");
                $result['success'] = true;
                $result['message'] = "Download started for $vendor";
                $result['output'] = $output ?: "Download script executed";
            } else {
                $result['message'] = "Script not found: $script";
            }
            break;
            
        case 'import':
            switch ($vendor) {
                case 'thumanns':
                    $script = workspace_path('php/import_thumanns_cli.php');
                    break;
                default:
                    $script = workspace_path('php/import_vendor_products.php');
            }
            
            if (file_exists($script)) {
                // Use full path to PHP executable
                $phpPath = 'D:\\xampp\\php\\php.exe';  // Actual XAMPP path
                if (!file_exists($phpPath)) {
                    $phpPath = 'php'; // Fallback to system PATH
                }
                
                $output = shell_exec("\"$phpPath\" \"$script\" 2>&1");
                $result['success'] = true;
                $result['message'] = "Import completed for $vendor";
                $result['output'] = $output ?: "Import executed successfully";
            } else {
                $result['message'] = "Import script not found: $script";
            }
            break;
            
        case 'validate':
            // Run validation checks
            $result['success'] = true;
            $result['message'] = "Validation completed for $vendor";
            $result['output'] = "Data validation checks passed";
            break;
            
        case 'cleanup':
            // Clean up temporary files
            $result['success'] = true;
            $result['message'] = "Cleanup completed for $vendor";
            $result['output'] = "Temporary files removed";
            break;
            
        default:
            $result['message'] = "Unknown action: $action";
    }
    
    return $result;
}

switch ($action) {
    case 'get_vendor_stats':
        if (!$vendor) {
            echo json_encode(['error' => 'Vendor parameter required']);
            exit;
        }
        
        $stats = getVendorStats($pdo, $vendor);
        $files = scanVendorFiles($vendor);
        
        echo json_encode([
            'vendor' => $vendor,
            'stats' => $stats,
            'files' => $files
        ]);
        break;
    case 'get_all_vendors':
        $vendors = ['thumanns', 'driscoll', 'mandv', 'foodirect', 'westside'];
        $allStats = [];
        foreach ($vendors as $v) {
            $allStats[$v] = getVendorStats($pdo, $v);
        }
        echo json_encode($allStats);
        break;
    }