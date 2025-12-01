<?php
// check_inactive_products.php
require_once 'environment_config.php';
$pdo = createPDOConnection();
$count = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=0')->fetchColumn();
echo "Products with is_active=0: $count\n";
