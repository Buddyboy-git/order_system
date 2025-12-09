<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/environment_config.php';

header('Content-Type: application/json');

function universal_search($pdo, $table, $query, $config = null, $options = []) {
    // Only support products table for now, but can be extended
    if ($table !== 'products') {
        throw new Exception('Universal search currently only supports products table.');
    }
    $filterDC = $options['vendor'] ?? ($_GET['dc'] ?? '');
    $sortBy = $options['sort'] ?? ($_GET['sort_by'] ?? 'description');
    $sortOrder = $options['order'] ?? ($_GET['sort_order'] ?? 'ASC');
    $perPage = $options['perPage'] ?? (isset($_GET['perPage']) ? intval($_GET['perPage']) : 100);
    $page = $options['page'] ?? (isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1);
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT p.item_code, p.description, p.price, p.vendor, p.category, p.uom_id AS unit FROM products p WHERE p.is_active = 1";
    $params = [];
    if ($query && trim($query) !== '' && $query !== '*') {
        $is_quoted = false;
        // Detect if the query is quoted (single or double)
        if (preg_match('/^("|\')(.*)\1$/', $query, $m)) {
            $is_quoted = true;
            $query = $m[2]; // Strip quotes
        }
        $words = array_filter(array_map('trim', explode(' ', $query)));
        if ($is_quoted && count($words) == 1) {
            // Exact word match using REGEXP word boundaries
            $sql .= " AND (LOWER(item_code) REGEXP ? OR LOWER(description) REGEXP ? OR LOWER(category) REGEXP ?)";
            $re = '[[:<:]]' . preg_quote(strtolower($query), '/') . '[[:>:]]';
            $params[] = $re;
            $params[] = $re;
            $params[] = $re;
        } elseif (count($words) == 1) {
            $sql .= " AND (item_code LIKE ? OR description LIKE ? OR category LIKE ?)";
            $params[] = "%$query%";
            $params[] = "%$query%";
            $params[] = "%$query%";
        } else {
            $conditions = [];
            foreach ($words as $word) {
                if ($is_quoted) {
                    $conditions[] = "(LOWER(item_code) REGEXP ? OR LOWER(description) REGEXP ? OR LOWER(category) REGEXP ?)";
                    $re = '[[:<:]]' . preg_quote(strtolower($word), '/') . '[[:>:]]';
                    $params[] = $re;
                    $params[] = $re;
                    $params[] = $re;
                } else {
                    $conditions[] = "(item_code LIKE ? OR description LIKE ? OR category LIKE ?)";
                    $params[] = "%$word%";
                    $params[] = "%$word%";
                    $params[] = "%$word%";
                }
            }
            $sql .= " AND (" . implode(" AND ", $conditions) . ")";
        }
    }
    if ($filterDC) {
        $sql .= " AND vendor = ?";
        $params[] = $filterDC;
    }
    $validSortFields = ['item_code', 'description', 'price', 'vendor', 'category', 'unit'];
    // No GROUP BY; deduplication will be done in PHP
    // Fix: If sorting by unit, use u.code in SQL
    if ($sortBy === 'unit') {
        $sql .= " ORDER BY u.code $sortOrder";
    } elseif (in_array($sortBy, $validSortFields)) {
        $sortOrder = ($sortOrder === 'DESC') ? 'DESC' : 'ASC';
        $sql .= " ORDER BY p.$sortBy $sortOrder";
    } else {
        $sql .= " ORDER BY p.description ASC";
    }
    // Get total count for pagination (count unique products)
    $countSql = "SELECT COUNT(DISTINCT item_code, description) FROM products WHERE is_active = 1";
    $countParams = [];
    if ($query && $query !== '*') {
        $is_quoted = false;
        if (preg_match('/^("|\')(.*)\1$/', $query, $m)) {
            $is_quoted = true;
            $query = $m[2];
        }
        $words = array_filter(array_map('trim', explode(' ', $query)));
        if ($is_quoted && count($words) == 1) {
            $countSql .= " AND (LOWER(item_code) REGEXP ? OR LOWER(description) REGEXP ? OR LOWER(category) REGEXP ?)";
            $re = '[[:<:]]' . preg_quote(strtolower($query), '/') . '[[:>:]]';
            $countParams[] = $re;
            $countParams[] = $re;
            $countParams[] = $re;
        } elseif (count($words) == 1) {
            $countSql .= " AND (item_code LIKE ? OR description LIKE ? OR category LIKE ?)";
            $countParams[] = "%$query%";
            $countParams[] = "%$query%";
            $countParams[] = "%$query%";
        } else {
            $conditions = [];
            foreach ($words as $word) {
                if ($is_quoted) {
                    $conditions[] = "(LOWER(item_code) REGEXP ? OR LOWER(description) REGEXP ? OR LOWER(category) REGEXP ?)";
                    $re = '[[:<:]]' . preg_quote(strtolower($word), '/') . '[[:>:]]';
                    $countParams[] = $re;
                    $countParams[] = $re;
                    $countParams[] = $re;
                } else {
                    $conditions[] = "(item_code LIKE ? OR description LIKE ? OR category LIKE ?)";
                    $countParams[] = "%$word%";
                    $countParams[] = "%$word%";
                    $countParams[] = "%$word%";
                }
            }
            $countSql .= " AND (" . implode(" AND ", $conditions) . ")";
        }
    }
    if ($filterDC) {
        $countSql .= " AND vendor = ?";
        $countParams[] = $filterDC;
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();
    $sql .= " LIMIT 10000"; // fetch enough to deduplicate in PHP
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Build uom_id => code lookup
    $uomLookup = [];
    try {
        $uomStmt = $pdo->query("SELECT id, code FROM uom");
        foreach ($uomStmt->fetchAll(PDO::FETCH_ASSOC) as $uom) {
            $uomLookup[$uom['id']] = $uom['code'];
        }
    } catch (Exception $e) {
        // Table may not exist, fallback to EA
    }
    // Deduplicate in PHP: group by (item_code, vendor)
    $grouped = [];
    foreach ($allRows as $row) {
        $key = $row['item_code'] . '||' . ($row['vendor'] ?? '');
        // Map uom_id to code for 'unit' field
        $row['unit'] = isset($uomLookup[$row['unit']]) ? $uomLookup[$row['unit']] : 'EA';
        if (!isset($grouped[$key])) {
            $grouped[$key] = $row;
        } else {
            // Prefer non-null category/unit/price
            foreach (['category','unit','price'] as $field) {
                if ((empty($grouped[$key][$field]) || $grouped[$key][$field] === null) && !empty($row[$field])) {
                    $grouped[$key][$field] = $row[$field];
                }
            }
        }
    }
    // Pagination in PHP
    $allResults = array_values($grouped);
    $total = count($allResults);
    $totalPages = $perPage > 0 ? ceil($total / $perPage) : 1;
    $offset = ($page - 1) * $perPage;
    $results = array_slice($allResults, $offset, $perPage);

    return [
        'results' => $results,
        'total' => $total,
        'page' => $page,
        'totalPages' => $totalPages,
        'debug_sql' => $sql,
        'debug_params' => $params
    ];
}

// === AJAX HANDLER ===
try {
    $pdo = createPDOConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_vendors') {
    // Return unique vendor list for dropdown
    try {
        $stmt = $pdo->query("SELECT DISTINCT vendor FROM products WHERE is_active = 1 AND vendor IS NOT NULL AND vendor != '' ORDER BY vendor");
        $vendors = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'vendor');
        echo json_encode(['vendors' => $vendors]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch vendors', 'details' => $e->getMessage()]);
    }
    exit;
}

// Default: search/list
$query = $_GET['q'] ?? $_POST['q'] ?? '';
$sortBy = $_GET['sort_by'] ?? $_POST['sort_by'] ?? 'description';
$sortOrder = $_GET['sort_order'] ?? $_POST['sort_order'] ?? 'ASC';
$filterDC = $_GET['dc'] ?? $_POST['dc'] ?? '';
$page = max(1, intval($_GET['page'] ?? $_POST['page'] ?? 1));
$perPage = 100;

try {
    $searchOpts = [
        'vendor' => $filterDC,
        'sort' => $sortBy,
        'order' => $sortOrder,
        'perPage' => $perPage,
        'page' => $page
    ];
    $result = universal_search($pdo, 'products', $query, null, $searchOpts);
    // Format for frontend: rows array
    $rows = [];
    foreach ($result['results'] as $row) {
        $rows[] = [
            'item_code' => $row['item_code'],
            'description' => $row['description'],
            'price' => $row['price'],
            'unit' => $row['unit'],
            'vendor' => $row['vendor'],
            'category' => $row['category']
        ];
    }
    echo json_encode([
        'rows' => $rows,
        'total' => $result['total'],
        'page' => $result['page'],
        'totalPages' => $result['totalPages']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed', 'details' => $e->getMessage()]);
}
