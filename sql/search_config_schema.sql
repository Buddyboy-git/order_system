--
-- Backend Integration Plan: Using search_config in AJAX Search
--
-- 1. On AJAX search request, determine table_name and (optionally) scope_type/scope_value (e.g., vendor, category).
-- 2. Use load_search_config($pdo, $table_name, $scope_type, $scope_value) to get config.
-- 3. Use config['search_columns'] to build WHERE clause for search terms.
-- 4. Use config['display_columns'] to select and return only relevant columns.
-- 5. Use config['sort_order'] and config['filters'] as needed.
-- 6. Optionally, send config['column_labels'] to frontend for dynamic headers.
-- 7. Fallback to global config if no specific config found.
--
-- Example CRUD operations for search_config table
--

-- CREATE (Insert)
INSERT INTO search_config (table_name, scope_type, scope_value, config_json)
VALUES ('products', 'vendor', 'Thumanns', '{"search_columns": ["item_code", "description"]}');

-- READ (Select)
SELECT * FROM search_config WHERE table_name = 'products' AND scope_type = 'vendor' AND scope_value = 'Thumanns';

-- UPDATE (Replace whole config)
UPDATE search_config SET config_json = '{"search_columns": ["item_code", "description", "price"]}'
WHERE table_name = 'products' AND scope_type = 'vendor' AND scope_value = 'Thumanns';

-- UPDATE (Modify a field inside JSON)
UPDATE search_config
SET config_json = JSON_SET(config_json, '$.display_columns', JSON_ARRAY('item_code', 'description', 'price'))
WHERE table_name = 'products' AND scope_type = 'vendor' AND scope_value = 'Thumanns';

-- DELETE
DELETE FROM search_config WHERE table_name = 'products' AND scope_type = 'vendor' AND scope_value = 'Thumanns';
-- Sample insert: global config for products table
INSERT INTO search_config (table_name, scope_type, scope_value, config_json) VALUES (
    'products',
    'global',
    NULL,
    '{
        "search_columns": ["item_code", "description"],
        "display_columns": ["item_code", "description", "price", "vendor", "category", "uom_id"],
        "column_labels": {
            "item_code": "SKU",
            "description": "Product Name",
            "price": "Unit Price"
        },
        "sort_order": "description ASC",
        "filters": { "active_only": true }
    }'
);
-- Create the search_config table for dynamic search configuration
CREATE TABLE search_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,         -- e.g., 'products', 'orders', etc.
    scope_type VARCHAR(32) NOT NULL,         -- e.g., 'global', 'vendor', 'category'
    scope_value VARCHAR(64),                 -- e.g., 'Thumanns', 'Bakery', or NULL for global
    config_json JSON NOT NULL,               -- All config options here
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
