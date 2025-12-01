-- Migration script to update local database to match production schema
-- This script creates vendor_products table and migrates data from products table

USE orders;

-- Create the new vendor_products table to match production schema
CREATE TABLE IF NOT EXISTS vendor_products (
    id INT(11) NOT NULL AUTO_INCREMENT,
    item_code VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,4) DEFAULT 0.0000,
    dc VARCHAR(100) DEFAULT NULL COMMENT 'Distribution Center / Vendor',
    category VARCHAR(100) DEFAULT NULL,
    unit VARCHAR(20) DEFAULT 'EA' COMMENT 'Unit of Measure',
    uom_id INT(11) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_item_code (item_code),
    KEY idx_description (description(100)),
    KEY idx_dc (dc),
    KEY idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Product data matching production schema';

-- Migrate data from products to vendor_products
INSERT INTO vendor_products (
    id, 
    item_code, 
    description, 
    price, 
    dc,           -- vendor becomes dc
    category, 
    unit,         -- default to 'EA' since we don't have this data
    uom_id, 
    is_active, 
    created_at
)
SELECT 
    id,
    item_code,
    description,
    price,
    vendor as dc,  -- rename vendor column to dc
    category,
    'EA' as unit,  -- default unit since we don't have this data
    uom_id,
    is_active,
    created_at
FROM products;

-- Verify the migration
SELECT 
    'Original products count' as description, 
    COUNT(*) as count 
FROM products
UNION ALL
SELECT 
    'Migrated vendor_products count' as description, 
    COUNT(*) as count 
FROM vendor_products;

-- Show sample of migrated data
SELECT 'Sample migrated data:' as info;
SELECT item_code, description, price, dc, category, unit 
FROM vendor_products 
LIMIT 5;