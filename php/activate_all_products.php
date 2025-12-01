<?php
// activate_all_products.php
require_once 'environment_config.php';
$pdo = createPDOConnection();
$count = $pdo->exec('UPDATE products SET is_active=1 WHERE is_active=0');
echo "Updated $count products to is_active=1.\n";
