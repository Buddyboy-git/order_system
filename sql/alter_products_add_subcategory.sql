-- Add subcategory column to products table
ALTER TABLE products ADD COLUMN subcategory VARCHAR(100) AFTER category;