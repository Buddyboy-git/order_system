<?php
/**
 * Enhanced CSV Product Search with Advanced Features
 * Searches master_vendor_prices.csv directly with advanced filtering, sorting, and pagination
 * Based on the advanced ajax_search functionality
 */

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$sortBy = $_GET['sort'] ?? 'description';
$sortOrder = $_GET['order'] ?? 'ASC';
$filterDC = $_GET['dc'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 100; // Match the advanced search behavior

$csvFile = 'master_vendor_prices.csv';

if (!file_exists($csvFile)) {
    echo json_encode(['error' => 'CSV file not found']);
    exit;
}

$results = [];
$totalResults = 0;

try {
    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle); // Skip header: item_code,dc,description,price,unit,category,subcategory,pick_unit,avg_weight,vendor_file,last_updated
    
    $allResults = [];
    
    // Read and filter data
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 6) continue; // Skip incomplete rows
        
        $item_code = trim($row[0]);
        $dc = trim($row[1]);
        $description = trim($row[2]);
        $price = floatval($row[3]);
        $unit = trim($row[4]);
        $category = trim($row[5]);
        
        // Skip empty records
        if (empty($item_code) || empty($description)) continue;
        
        // Apply DC filter if specified
        if ($filterDC && $dc !== $filterDC) continue;
        
        // Apply search query if specified (enhanced multi-word search)
        if ($query && $query !== '*') {
            // Split query into individual words for multi-word search (like advanced version)
            $words = array_filter(array_map('trim', explode(' ', strtolower($query))));
            $searchText = strtolower($item_code . ' ' . $description . ' ' . $category);
            
            if (count($words) == 1) {
                // Single word search
                $word = $words[0];
                if (strpos($searchText, $word) === false) {
                    continue;
                }
            } else {
                // Multi-word search - all words must be found (like advanced version)
                $matches = true;
                foreach ($words as $word) {
                    if (strpos($searchText, $word) === false) {
                        $matches = false;
                        break;
                    }
                }
                if (!$matches) continue;
            }
        }
        
        $allResults[] = [
            'item_code' => $item_code,
            'description' => $description,
            'price' => number_format($price, 2),
            'unit' => $unit,
            'dc' => $dc,
            'category' => $category
        ];
    }
    
    fclose($handle);
    
    // Sort results
    $sortColumn = $sortBy;
    if ($sortColumn === 'price') {
        usort($allResults, function($a, $b) use ($sortOrder) {
            $aVal = floatval(str_replace(',', '', $a['price']));
            $bVal = floatval(str_replace(',', '', $b['price']));
            return $sortOrder === 'ASC' ? $aVal <=> $bVal : $bVal <=> $aVal;
        });
    } else {
        usort($allResults, function($a, $b) use ($sortColumn, $sortOrder) {
            $cmp = strcasecmp($a[$sortColumn], $b[$sortColumn]);
            return $sortOrder === 'ASC' ? $cmp : -$cmp;
        });
    }
    
    $totalResults = count($allResults);
    
    // Paginate
    $offset = ($page - 1) * $perPage;
    $results = array_slice($allResults, $offset, $perPage);
    
    // Get unique vendors for filter dropdown
    $vendors = [];
    $vendorHandle = fopen($csvFile, 'r');
    fgetcsv($vendorHandle); // Skip header
    while (($row = fgetcsv($vendorHandle)) !== FALSE) {
        if (count($row) >= 2) {
            $dc = trim($row[1]);
            if (!empty($dc) && !in_array($dc, $vendors)) {
                $vendors[] = $dc;
            }
        }
    }
    fclose($vendorHandle);
    sort($vendors);
    
    echo json_encode([
        'results' => $results,
        'total' => $totalResults,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($totalResults / $perPage),
        'vendors' => $vendors,
        'query' => $query,
        'filterDC' => $filterDC
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}
?>