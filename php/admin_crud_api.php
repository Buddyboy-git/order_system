<?php
// admin_crud_api.php
// Universal CRUD API for admin panel (table-agnostic, config-driven)

require_once 'environment_config.php';
require_once 'search_config_loader.php';
require_once 'universal_search.php';

header('Content-Type: application/json');

$pdo = createPDOConnection();

// Whitelist tables that can be managed
$allowed_tables = ['products', 'search_config']; // Add more as needed


$table = $_GET['table'] ?? $_POST['table'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;


// List all tables in the current database
if ($action === 'list_tables') {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['tables' => $tables]);
    exit;
}

// List all unique vendors for products table (for UI filter)
if ($action === 'get_vendors') {
    $stmt = $pdo->query("SELECT DISTINCT vendor FROM products ORDER BY vendor ASC");
    $vendors = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['vendors' => $vendors]);
    exit;
}

// For all other actions, require a table name and load config if available
if (!$table) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing table']);
    exit;
}

// Set error handler to return JSON errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => "PHP error: $errstr in $errfile on line $errline"]);
    exit;
});
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['error' => "Exception: " . $e->getMessage()]);
    exit;
});

// Try to load config for this table, but skip if search_config table is missing
$config = null;
try {
    $config = load_search_config($pdo, $table);
} catch (Exception $e) {
    // If search_config table is missing, just fallback to schema
    if (strpos($e->getMessage(), 'search_config') === false) {
        throw $e;
    }
}
// Helper: get columns to display/edit (universal)
function get_table_columns($pdo, $table) {
    $stmt = $pdo->prepare("DESCRIBE `$table`");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $cols;
}
function get_columns($config, $pdo, $table) {
function get_text_columns($pdo, $table) {
    $stmt = $pdo->prepare("DESCRIBE `$table`");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $text_cols = [];
    foreach ($cols as $col) {
        if (stripos($col['Type'], 'char') !== false || stripos($col['Type'], 'text') !== false) {
            $text_cols[] = $col['Field'];
        }
    }
    return $text_cols;
}
    if ($config && isset($config['display_columns'])) {
        return $config['display_columns'];
    }
    // Fallback: get all columns from schema
    return get_table_columns($pdo, $table);
}

// List all tables in the current database
if ($action === 'list_tables') {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['tables' => $tables]);
    exit;
}

switch ($action) {
    case 'search_products':
        // Compatibility: support legacy search_products action for AJAX search
        $table = 'products';
        $search = $_GET['q'] ?? '';
        $searchOpts = [
            'vendor' => $_GET['dc'] ?? '',
            'sort' => $_GET['sort_by'] ?? 'description',
            'order' => $_GET['sort_order'] ?? 'ASC',
            'perPage' => isset($_GET['perPage']) ? intval($_GET['perPage']) : 100,
            'page' => isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1
        ];
        $config = null;
        try {
            $config = load_search_config($pdo, $table);
        } catch (Exception $e) {}
        $result = universal_search($pdo, $table, $search, $config, $searchOpts);
        echo json_encode([
            'results' => $result['results'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'columns' => isset($columns) ? $columns : [],
            'labels' => $config['column_labels'] ?? [],
            'search_columns' => $config['search_columns'] ?? ['item_code','description','category']
        ]);
        break;
    case 'list':
        // List/search rows (with optional search query)
        $columns = get_columns($config, $pdo, $table);
        $search = $_GET['q'] ?? '';
        // Use universal_search for products table
        if ($table === 'products') {
            $searchOpts = [
                'vendor' => $_GET['dc'] ?? '',
                'sort' => $_GET['sort_by'] ?? 'description',
                'order' => $_GET['sort_order'] ?? 'ASC',
                'perPage' => isset($_GET['perPage']) ? intval($_GET['perPage']) : 100,
                'page' => isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1
            ];
            $result = universal_search($pdo, $table, $search, $config, $searchOpts);
            echo json_encode([
                'rows' => $result['results'],
                'total' => $result['total'],
                'page' => $result['page'],
                'totalPages' => $result['totalPages'],
                'columns' => $columns,
                'labels' => $config['column_labels'] ?? [],
                'search_columns' => $config['search_columns'] ?? ['item_code','description','category']
            ]);
            break;
        }
        // Fallback: generic search for other tables
        $where = '';
        $params = [];
        $search_cols = [];
        if ($config && !empty($config['search_columns'])) {
            $search_cols = $config['search_columns'];
        } else {
            $search_cols = get_text_columns($pdo, $table);
        }
        if ($search && !empty($search_cols)) {
            $search_clauses = [];
            foreach ($search_cols as $col) {
                $search_clauses[] = "$col LIKE ?";
                $params[] = "%$search%";
            }
            $where = 'WHERE ' . implode(' OR ', $search_clauses);
        }
        $sql = "SELECT " . implode(',', $columns) . " FROM `$table` $where LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'rows' => $rows,
            'columns' => $columns,
            'labels' => $config['column_labels'] ?? [],
            'search_columns' => $search_cols
        ]);
        break;
    case 'get':
        // Get a single row by primary key (assume 'id')
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            exit;
        }
        $columns = get_columns($config, $pdo, $table);
        $sql = "SELECT " . implode(',', $columns) . " FROM `$table` WHERE id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['row' => $row, 'columns' => $columns, 'labels' => $config['column_labels'] ?? []]);
        break;
    case 'create':
        // Create a new row
        $columns = get_columns($config, $pdo, $table);
        $data = json_decode(file_get_contents('php://input'), true);
        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            if (isset($data[$col])) {
                $fields[] = $col;
                $placeholders[] = '?';
                $values[] = $data[$col];
            }
        }
        $sql = "INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;
    case 'update':
        // Update a row by primary key (assume 'id')
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            exit;
        }
        $columns = get_columns($config, $pdo, $table);
        $data = json_decode(file_get_contents('php://input'), true);
        $sets = [];
        $values = [];
        foreach ($columns as $col) {
            if (isset($data[$col])) {
                $sets[] = "$col = ?";
                $values[] = $data[$col];
            }
        }
        if (count($sets) === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        $values[] = $id;
        $sql = "UPDATE `$table` SET " . implode(',', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        echo json_encode(['success' => true]);
        break;
    case 'delete':
        // Delete a row by primary key (assume 'id')
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            exit;
        }
        $sql = "DELETE FROM `$table` WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
