<?php
require_once 'universal_search.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'environment_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = createPDOConnection();
    
    switch ($action) {
        case 'dashboard_stats':
            echo json_encode(getDashboardStats($pdo));
            break;
            
        case 'recent_activity':
            echo json_encode(getRecentActivity($pdo));
            break;
            
        // Customer CRUD operations
        case 'get_customers':
            echo json_encode(getCustomers($pdo, $_GET['search'] ?? ''));
            break;
            
        case 'get_customer':
            echo json_encode(getCustomer($pdo, $_GET['id']));
            break;
            
        case 'create_customer':
            echo json_encode(createCustomer($pdo, $_POST));
            break;
            
        case 'update_customer':
            echo json_encode(updateCustomer($pdo, $_POST));
            break;
            
        case 'delete_customer':
            echo json_encode(deleteCustomer($pdo, $_POST['id']));
            break;
            
        // Customer Abbreviations CRUD
        case 'get_customer_abbreviations':
            echo json_encode(getCustomerAbbreviations($pdo, $_GET['search'] ?? ''));
            break;
            
        case 'create_customer_abbreviation':
            echo json_encode(createCustomerAbbreviation($pdo, $_POST));
            break;
            
        case 'update_customer_abbreviation':
            echo json_encode(updateCustomerAbbreviation($pdo, $_POST));
            break;
            
        case 'delete_customer_abbreviation':
            echo json_encode(deleteCustomerAbbreviation($pdo, $_POST['id']));
            break;
            
        // Product CRUD operations
        case 'get_products':
            echo json_encode(getProducts($pdo, $_GET['search'] ?? '', $_GET['page'] ?? 1));
            break;
            
        case 'get_product':
            echo json_encode(getProduct($pdo, $_GET['id']));
            break;
            
        case 'create_product':
            echo json_encode(createProduct($pdo, $_POST));
            break;
            
        case 'update_product':
            echo json_encode(updateProduct($pdo, $_POST));
            break;
            
        case 'delete_product':
            echo json_encode(deleteProduct($pdo, $_POST['id']));
            break;
            
        // Product Abbreviations CRUD
        case 'get_product_abbreviations':
            echo json_encode(getProductAbbreviations($pdo, $_GET['search'] ?? ''));
            break;
            
        case 'create_product_abbreviation':
            echo json_encode(createProductAbbreviation($pdo, $_POST));
            break;
            
        case 'update_product_abbreviation':
            echo json_encode(updateProductAbbreviation($pdo, $_POST));
            break;
            
        case 'delete_product_abbreviation':
            echo json_encode(deleteProductAbbreviation($pdo, $_POST['id']));
            break;
            
        // UOM CRUD operations
        case 'get_uoms':
            echo json_encode(getUOMs($pdo));
            break;
            
        case 'get_uom':
            echo json_encode(getUOM($pdo, $_GET['id']));
            break;
            
        case 'create_uom':
            echo json_encode(createUOM($pdo, $_POST));
            break;
            
        case 'update_uom':
            echo json_encode(updateUOM($pdo, $_POST));
            break;
            
        case 'delete_uom':
            echo json_encode(deleteUOM($pdo, $_POST['id']));
            break;
            
        // Order management
        case 'get_all_orders':
            echo json_encode(getAllOrders($pdo, $_GET['status'] ?? '', $_GET['page'] ?? 1));
            break;
            
        case 'get_order_items':
            echo json_encode(getOrderItems($pdo, $_GET['order_id'] ?? ''));
            break;
            
        case 'get_order_history':
            echo json_encode(getOrderHistory($pdo, $_GET['page'] ?? 1));
            break;
            
        // Customer Items endpoints
        case 'get_customer_items':
            echo json_encode(getCustomerItems($pdo, $_GET['customer_id']));
            break;
            
        case 'add_customer_item':
            echo json_encode(addCustomerItem($pdo, $_POST));
            break;
            
        case 'delete_customer_item':
            echo json_encode(deleteCustomerItem($pdo, $_POST['id']));
            break;
            
        case 'get_customer_item':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID is required']);
                break;
            }
            echo json_encode(getCustomerItem($pdo, $_GET['id']));
            break;
            
        case 'update_customer_item':
            echo json_encode(updateCustomerItem($pdo, $_POST));
            break;
            
        case 'search_products':
            // Use universal_search for products table to unify logic
            $search = $_GET['q'] ?? '';
            $searchOpts = [
                'vendor' => $_GET['dc'] ?? '',
                'sort' => $_GET['sort_by'] ?? 'description',
                'order' => $_GET['sort_order'] ?? 'ASC',
                'perPage' => isset($_GET['perPage']) ? intval($_GET['perPage']) : 100,
                'page' => isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1
            ];
            $result = universal_search($pdo, 'products', $search, null, $searchOpts);
            echo json_encode($result);
            break;
            
        case 'get_vendors':
            echo json_encode(getVendors($pdo));
            break;
            
        case 'get_product_count':
            echo json_encode(getProductCount($pdo));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Dashboard functions
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_active = 1");
    $stats['total_customers'] = $stmt->fetchColumn();
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
    $stats['total_products'] = $stmt->fetchColumn();
    
    // Active orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered', 'archived', 'cancelled')");
    $stats['active_orders'] = $stmt->fetchColumn();
    
    // Total revenue (from delivered orders)
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'delivered'");
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    return $stats;
}

function getRecentActivity($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            'Order Created' as action,
            CONCAT('Order ', order_number, ' for customer ', 
                   COALESCE(c.business_name, 'Unknown')) as details,
            o.created_at as timestamp
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format timestamps
    foreach ($activities as &$activity) {
        $activity['timestamp'] = date('M j, Y g:i A', strtotime($activity['timestamp']));
    }
    
    return $activities;
}

// Customer CRUD functions
function getCustomers($pdo, $search = '') {
    $sql = "SELECT id, name, code, email, phone, business_name as address, is_active as active, created_at FROM customers";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE name LIKE ? OR code LIKE ? OR email LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($customers as &$customer) {
        $customer['active'] = (bool)$customer['active'];
        $customer['created_at'] = date('M j, Y', strtotime($customer['created_at']));
    }
    
    return $customers;
}

function getCustomer($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, name, code, email, phone, business_name as address, is_active as active, created_at FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $customer['active'] = (bool)$customer['active'];
    }
    
    return $customer;
}

function createCustomer($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO customers (name, code, email, phone, business_name, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['name'],
            $data['code'],
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['address'] ?: null,
            $data['active'] ? 1 : 0
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateCustomer($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = ?, code = ?, email = ?, phone = ?, business_name = ?, 
                is_active = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['name'],
            $data['code'],
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['address'] ?: null,
            $data['active'] ? 1 : 0,
            $data['id']
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteCustomer($pdo, $id) {
    try {
        // Check if customer has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Cannot delete customer with existing orders'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Customer Abbreviations CRUD
function getCustomerAbbreviations($pdo, $search = '') {
    $sql = "
        SELECT ca.*, c.name as customer_name, c.code as customer_code
        FROM customer_abbreviations ca
        JOIN customers c ON ca.customer_id = c.id
    ";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE ca.abbreviation LIKE ? OR c.name LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY c.name, ca.abbreviation";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createCustomerAbbreviation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO customer_abbreviations (customer_id, abbreviation, confidence_score, usage_count, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        
        $stmt->execute([
            $data['customer_id'],
            $data['abbreviation'],
            $data['confidence_score'] ?? 100
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateCustomerAbbreviation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE customer_abbreviations 
            SET customer_id = ?, abbreviation = ?, confidence_score = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['customer_id'],
            $data['abbreviation'],
            $data['confidence_score'] ?? 100,
            $data['id']
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteCustomerAbbreviation($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM customer_abbreviations WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Product Abbreviations CRUD
function getProductAbbreviations($pdo, $search = '') {
    $sql = "
        SELECT pa.*, p.name as product_name, p.code as product_code, c.name as customer_name
        FROM product_abbreviations pa
        JOIN products p ON pa.product_id = p.id
        JOIN customers c ON pa.customer_id = c.id
    ";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE pa.abbreviation LIKE ? OR p.name LIKE ? OR c.name LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY c.name, pa.abbreviation";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createProductAbbreviation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO product_abbreviations (customer_id, product_id, abbreviation, confidence_score, usage_count, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt->execute([
            $data['customer_id'],
            $data['product_id'],
            $data['abbreviation'],
            $data['confidence_score'] ?? 100
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateProductAbbreviation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE product_abbreviations 
            SET customer_id = ?, product_id = ?, abbreviation = ?, confidence_score = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['customer_id'],
            $data['product_id'],
            $data['abbreviation'],
            $data['confidence_score'] ?? 100,
            $data['id']
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteProductAbbreviation($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM product_abbreviations WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Product CRUD functions
function getProducts($pdo, $search = '', $page = 1) {
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT id, item_code as code, description as name, description, price as unit_price, uom_id, is_active as active, vendor, category, created_at FROM products";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE description LIKE ? OR item_code LIKE ? OR vendor LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY description LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($products as &$product) {
        $product['active'] = (bool)$product['active'];
        $product['unit_price'] = (float)$product['unit_price'];
        $product['created_at'] = date('M j, Y', strtotime($product['created_at']));
    }
    
    return $products;
}

function getProduct($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, item_code as code, description as name, description, price as unit_price, uom_id, is_active as active, vendor, category, created_at FROM products WHERE id = ?");
    $stmt->execute([$id]);
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        $product['active'] = (bool)$product['active'];
        $product['unit_price'] = (float)$product['unit_price'];
    }
    
    return $product;
}

function createProduct($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (item_code, description, price, uom_id, is_active, vendor, category, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['unit_price'] ?: 0,
            $data['uom_id'] ?: null,
            $data['active'] ? 1 : 0,
            $data['vendor'] ?: null,
            $data['category'] ?: null
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateProduct($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE products 
            SET item_code = ?, description = ?, price = ?, 
                uom_id = ?, is_active = ?, vendor = ?, category = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['unit_price'] ?: 0,
            $data['uom_id'] ?: null,
            $data['active'] ? 1 : 0,
            $data['vendor'] ?: null,
            $data['category'] ?: null,
            $data['id']
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteProduct($pdo, $id) {
    try {
        // Check if product is used in orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Cannot delete product used in orders'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// UOM functions
function getUOMs($pdo) {
    $stmt = $pdo->query("SELECT * FROM uom ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUOM($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM uom WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createUOM($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO uom (code, name, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'] ?: null
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateUOM($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE uom 
            SET code = ?, name = ?, description = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'] ?: null,
            $data['id']
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteUOM($pdo, $id) {
    try {
        // Check if UOM is used
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE uom_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'Cannot delete UOM used by products'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM uom WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Order management functions
function getAllOrders($pdo, $status = '', $page = 1) {
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT 
            o.id, o.order_number, o.customer_id, c.name as customer_name,
            o.order_date, o.status, o.total_amount, o.created_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
    ";
    $params = [];
    
    if ($status) {
        $sql .= " WHERE o.status = ?";
        $params[] = $status;
    }
    
    $sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($orders as &$order) {
        $order['total_amount'] = (float)$order['total_amount'];
        $order['item_count'] = (int)$order['item_count'];
        $order['order_date'] = date('M j, Y', strtotime($order['order_date']));
        $order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));
    }
    
    return $orders;
}

function getOrderItems($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.code as product_code
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$orderId]);
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($items as &$item) {
        $item['quantity'] = (float)$item['quantity'];
        $item['unit_price'] = (float)$item['unit_price'];
        $item['total_price'] = (float)$item['total_price'];
        $item['confidence'] = (int)$item['confidence'];
    }
    
    return $items;
}

function getOrderHistory($pdo, $page = 1) {
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT 
            oh.id, oh.order_number, oh.customer_id, c.name as customer_name,
            oh.order_date, oh.total_amount, oh.item_count, oh.archived_at
        FROM order_history oh
        LEFT JOIN customers c ON oh.customer_id = c.id
        ORDER BY oh.archived_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
    
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    foreach ($history as &$record) {
        $record['total_amount'] = (float)$record['total_amount'];
        $record['item_count'] = (int)$record['item_count'];
        $record['order_date'] = date('M j, Y', strtotime($record['order_date']));
        $record['archived_at'] = date('M j, Y g:i A', strtotime($record['archived_at']));
    }
    
    return $history;
}

// Customer Items functions
function getCustomerItems($pdo, $customerId) {
    if (!$customerId) {
        return ['success' => false, 'error' => 'Customer ID required'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ci.id, ci.customer_product_code, ci.customer_product_name, 
                   ci.nickname, ci.default_quantity, u.code as unit, ci.notes, 
                   p.item_code, p.description as product_description, p.price as vendor_price,
                   ci.created_at
            FROM customer_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            LEFT JOIN uom u ON ci.default_uom_id = u.id
            WHERE ci.customer_id = ? AND ci.is_active = 1
            ORDER BY ci.customer_product_code
        ");
        $stmt->execute([$customerId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $items];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function addCustomerItem($pdo, $data) {
    $required = ['customer_id', 'item_code', 'description'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'error' => "Field '$field' is required"];
        }
    }
    
    try {
        // First, find or create the product in products table
        $productStmt = $pdo->prepare("SELECT id FROM products WHERE item_code = ?");
        $productStmt->execute([$data['item_code']]);
        $product = $productStmt->fetch();
        
        $productId = null;
        if ($product) {
            $productId = $product['id'];
        } else {
            // Create new product entry
            $insertProductStmt = $pdo->prepare("
                INSERT INTO products (item_code, description, price, vendor, is_active, created_at)
                VALUES (?, ?, ?, 'CSV Import', 1, NOW())
            ");
            $insertProductStmt->execute([
                $data['item_code'],
                $data['description'],
                $data['vendor_price'] ?? 0
            ]);
            $productId = $pdo->lastInsertId();
        }
        
        // Check if item already exists for this customer
        $checkStmt = $pdo->prepare("SELECT id FROM customer_items WHERE customer_id = ? AND product_id = ?");
        $checkStmt->execute([$data['customer_id'], $productId]);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'error' => 'This product is already assigned to this customer'];
        }
        
        // Get UOM ID for the unit
        $uomId = 1; // Default
        if (!empty($data['unit'])) {
            $uomStmt = $pdo->prepare("SELECT id FROM uom WHERE code = ?");
            $uomStmt->execute([$data['unit']]);
            $uom = $uomStmt->fetch();
            if ($uom) {
                $uomId = $uom['id'];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO customer_items (customer_id, product_id, customer_product_code, customer_product_name, 
                                       default_quantity, default_uom_id, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['customer_id'],
            $productId,
            $data['item_code'], // Use item_code as customer_product_code
            $data['description'], // Use description as customer_product_name
            1, // default quantity
            $uomId,
            $data['notes'] ?? ''
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteCustomerItem($pdo, $id) {
    if (!$id) {
        return ['success' => false, 'error' => 'Item ID required'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM customer_items WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Item not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getCustomerItem($pdo, $id) {
    if (!$id) {
        return ['success' => false, 'error' => 'Item ID required'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ci.*, p.description as product_description, p.price as vendor_price, p.item_code
            FROM customer_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            WHERE ci.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            return ['success' => true, 'data' => $item];
        } else {
            return ['success' => false, 'error' => 'Item not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateCustomerItem($pdo, $data) {
    $required = ['id', 'customer_product_code', 'customer_product_name'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE customer_items 
            SET customer_product_code = ?, 
                customer_product_name = ?, 
                nickname = ?, 
                default_quantity = ?, 
                default_uom_id = ?, 
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['customer_product_code'],
            $data['customer_product_name'],
            $data['nickname'] ?? null,
            $data['default_quantity'] ?? 1,
            $data['default_uom_id'] ?? 1,
            $data['notes'] ?? null,
            $data['id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'No changes made or item not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function searchProducts($pdo, $params, $dbname) {
    $query = $params['q'] ?? '';
    $sortBy = $params['sort'] ?? 'description';
    $sortOrder = $params['order'] ?? 'ASC';
    $filterVendor = $params['dc'] ?? '';
    $page = max(1, intval($params['page'] ?? 1));
    $perPage = 100;
    try {
        $whereConditions = [];
        $sqlParams = [];
        $sql = "SELECT item_code, description, price, vendor, category, uom_id AS unit FROM products WHERE is_active = 1";
        if ($query && $query !== '*') {
            $words = array_filter(array_map('trim', explode(' ', $query)));
            if (count($words) == 1) {
                $whereConditions[] = "(item_code LIKE ? OR description LIKE ? OR category LIKE ?)";
                $sqlParams[] = "%$query%";
                $sqlParams[] = "%$query%";
                $sqlParams[] = "%$query%";
            } else {
                $wordConditions = [];
                foreach ($words as $word) {
                    $wordConditions[] = "(item_code LIKE ? OR description LIKE ? OR category LIKE ?)";
                    $sqlParams[] = "%$word%";
                    $sqlParams[] = "%$word%";
                    $sqlParams[] = "%$word%";
                }
                $whereConditions[] = "(" . implode(" AND ", $wordConditions) . ")";
            }
        }
        if ($filterVendor) {
            $whereConditions[] = "vendor = ?";
            $sqlParams[] = $filterVendor;
        }
        $filterCategory = $_GET['category'] ?? '';
        if ($filterCategory) {
            $whereConditions[] = "category = ?";
            $sqlParams[] = $filterCategory;
        }
        if (!empty($whereConditions)) {
            $sql .= " AND " . implode(" AND ", $whereConditions);
        }
        $sql .= " LIMIT $perPage OFFSET " . (($page - 1) * $perPage);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sqlParams);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $formattedResults = [];
        foreach ($results as $row) {
            $formattedResults[] = [
                'item_code' => $row['item_code'],
                'description' => $row['description'],
                'price' => number_format((float)$row['price'], 2),
                'unit' => $row['unit'] ?: 'EA',
                'dc' => $row['vendor'],
                'category' => $row['category'] ?: 'General'
            ];
        }
        $countSql = "SELECT COUNT(*) FROM products WHERE is_active = 1";
        if (!empty($whereConditions)) {
            $countSql .= " AND " . implode(" AND ", $whereConditions);
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($sqlParams);
        $totalResults = $countStmt->fetchColumn();
        return [
            'results' => $formattedResults,
            'total' => $totalResults,
            'page' => $page,
            'totalPages' => ceil($totalResults / $perPage),
            'query' => $query,
            'filterDC' => $filterVendor
        ];
    } catch (Exception $e) {
        return ['error' => 'Search failed: ' . $e->getMessage()];
    }
}

function getVendors($pdo) {
    try {
        // Get vendors from products (only active products)
        $stmt = $pdo->prepare("SELECT DISTINCT vendor FROM products WHERE vendor IS NOT NULL AND vendor != '' AND is_active = 1");
        $stmt->execute();
        $vendors = $stmt->fetchAll(PDO::FETCH_COLUMN);
        sort($vendors, SORT_NATURAL | SORT_FLAG_CASE);
        return ['vendors' => $vendors];
    } catch (Exception $e) {
        return ['error' => 'Failed to load vendors: ' . $e->getMessage()];
    }
}

function getProductCount($pdo) {
    try {
        // Get total product count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalProducts = $result['total'];
        // Get count by vendor
        $stmt = $pdo->query("SELECT vendor, COUNT(*) as count FROM products WHERE is_active = 1 GROUP BY vendor ORDER BY count DESC");
        $vendorCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'total' => $totalProducts,
            'vendors' => $vendorCounts,
            'formatted' => number_format($totalProducts)
        ];
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}
?>