-- Order Entry System Database Schema
-- Supports intelligent shorthand parsing with customer-product context
-- Created: November 11, 2025

-- Drop tables if they exist (for development)
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS customer_items;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS uom;
DROP TABLE IF EXISTS products;

-- Units of Measure table
CREATE TABLE uom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table (links to master_vendor_prices.csv)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,4) DEFAULT 0.00,
    vendor VARCHAR(100),
    category VARCHAR(100),
    uom_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uom_id) REFERENCES uom(id),
    UNIQUE KEY unique_item_vendor (item_code, vendor),
    INDEX idx_item_code (item_code),
    INDEX idx_description (description(100)),
    INDEX idx_vendor (vendor)
);

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    business_name VARCHAR(255),
    contact_person VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    delivery_instructions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_name (name)
);

-- Customer Items table - Customer-specific product nicknames and preferences
CREATE TABLE customer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    customer_product_code VARCHAR(50),
    customer_product_name VARCHAR(255),
    nickname VARCHAR(100),
    default_quantity DECIMAL(10,2) DEFAULT 1.00,
    default_uom_id INT,
    frequency_score INT DEFAULT 1, -- How often they order this item
    last_ordered DATE,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (default_uom_id) REFERENCES uom(id),
    UNIQUE KEY unique_customer_product (customer_id, product_id),
    INDEX idx_customer_product_code (customer_id, customer_product_code),
    INDEX idx_nickname (customer_id, nickname),
    INDEX idx_frequency (customer_id, frequency_score DESC)
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE,
    status ENUM('draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'draft',
    order_method ENUM('shorthand', 'email', 'text', 'voice', 'online', 'phone') DEFAULT 'shorthand',
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    delivery_instructions TEXT,
    original_input TEXT, -- Store the original shorthand input for reference
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_order_number (order_number),
    INDEX idx_customer_date (customer_id, order_date),
    INDEX idx_status (status),
    INDEX idx_order_date (order_date)
);

-- Order Items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    uom_id INT NOT NULL,
    unit_price DECIMAL(10,4) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    customer_reference VARCHAR(100), -- What the customer called this item
    parsed_from VARCHAR(50), -- The shorthand code that generated this line
    line_number INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (uom_id) REFERENCES uom(id),
    INDEX idx_order_product (order_id, product_id),
    INDEX idx_item_code (item_code)
);

-- Customer abbreviations for shorthand parsing
CREATE TABLE customer_abbreviations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    abbreviation VARCHAR(20) NOT NULL,
    confidence_score INT DEFAULT 100, -- Higher = more likely match
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_abbreviation (abbreviation, customer_id),
    INDEX idx_abbreviation (abbreviation)
);

-- Product abbreviations for shorthand parsing (customer-specific)
CREATE TABLE product_abbreviations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    abbreviation VARCHAR(20) NOT NULL,
    confidence_score INT DEFAULT 100,
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_customer_abbreviation (customer_id, abbreviation),
    INDEX idx_product_abbreviation (product_id, abbreviation)
);

-- Insert sample UOM data
INSERT INTO uom (code, name, description) VALUES
('EA', 'Each', 'Individual items'),
('PC', 'Piece', 'Individual pieces'),
('LB', 'Pound', 'Weight in pounds'),
('KG', 'Kilogram', 'Weight in kilograms'),
('CS', 'Case', 'Case quantity'),
('BX', 'Box', 'Box quantity'),
('DZ', 'Dozen', '12 pieces'),
('GAL', 'Gallon', 'Volume in gallons'),
('QT', 'Quart', 'Volume in quarts'),
('PT', 'Pint', 'Volume in pints'),
('OZ', 'Ounce', 'Weight in ounces'),
('FL', 'Fluid Ounce', 'Volume in fluid ounces'),
('SL', 'Slice', 'Sliced items'),
('PK', 'Pack', 'Package quantity');

-- Insert sample customer data (for testing the shorthand system)
INSERT INTO customers (code, name, business_name, phone, email) VALUES
('G18', 'Graceful 18th', 'Graceful 18th Restaurant', '555-0118', 'orders@graceful18th.com'),
('MS', 'Main Street', 'Main Street Deli', '555-0123', 'mainst@deli.com'),
('CP', 'Corner Pub', 'Corner Pub & Grill', '555-0456', 'orders@cornerpub.com'),
('GG', 'Green Garden', 'Green Garden Cafe', '555-0789', 'chef@greengarden.com');

-- Create views for easier querying
CREATE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    c.name as customer_name,
    c.code as customer_code,
    o.order_date,
    o.status,
    o.total_amount,
    COUNT(oi.id) as item_count
FROM orders o
JOIN customers c ON o.customer_id = c.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

CREATE VIEW customer_product_history AS
SELECT 
    c.id as customer_id,
    c.name as customer_name,
    p.id as product_id,
    p.item_code,
    p.description as product_name,
    COUNT(oi.id) as order_count,
    SUM(oi.quantity) as total_quantity,
    AVG(oi.quantity) as avg_quantity,
    MAX(o.order_date) as last_ordered,
    AVG(oi.unit_price) as avg_price
FROM customers c
JOIN orders o ON c.id = o.customer_id
JOIN order_items oi ON o.id = oi.order_id
JOIN products p ON oi.product_id = p.id
WHERE o.status != 'cancelled'
GROUP BY c.id, p.id;

-- Indexes for performance
CREATE INDEX idx_customer_items_lookup ON customer_items(customer_id, customer_product_code, nickname);
CREATE INDEX idx_abbreviation_lookup ON customer_abbreviations(abbreviation, confidence_score DESC);
CREATE INDEX idx_product_abbreviation_lookup ON product_abbreviations(customer_id, abbreviation, confidence_score DESC);

DELIMITER //

-- Trigger to update customer_items frequency when orders are placed
CREATE TRIGGER update_customer_item_frequency
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    INSERT INTO customer_items (customer_id, product_id, frequency_score, last_ordered)
    SELECT o.customer_id, NEW.product_id, 1, o.order_date
    FROM orders o WHERE o.id = NEW.order_id
    ON DUPLICATE KEY UPDATE 
        frequency_score = frequency_score + 1,
        last_ordered = (SELECT order_date FROM orders WHERE id = NEW.order_id);
END//

-- Function to generate order numbers
CREATE FUNCTION generate_order_number() RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE next_num INT;
    DECLARE order_num VARCHAR(50);
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, 9) AS UNSIGNED)), 0) + 1 
    INTO next_num 
    FROM orders 
    WHERE order_number LIKE CONCAT(DATE_FORMAT(NOW(), '%Y%m%d'), '%');
    
    SET order_num = CONCAT(DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(next_num, 4, '0'));
    
    RETURN order_num;
END//

DELIMITER ;

-- Comments for documentation
ALTER TABLE orders COMMENT = 'Main orders table supporting multiple input methods including intelligent shorthand parsing';
ALTER TABLE order_items COMMENT = 'Individual line items for orders with parsing context';
ALTER TABLE customer_items COMMENT = 'Customer-specific product nicknames and preferences for shorthand parsing';
ALTER TABLE customer_abbreviations COMMENT = 'Customer abbreviation codes for shorthand parsing (g18 = Graceful 18th)';
ALTER TABLE product_abbreviations COMMENT = 'Product abbreviation codes per customer for shorthand parsing (1t = 1pc turkey)';

SELECT 'Order Entry Database Schema Created Successfully!' as status;