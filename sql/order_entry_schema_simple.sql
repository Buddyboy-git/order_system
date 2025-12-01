-- Order Entry System Database Schema (Simplified)
-- Compatible with MariaDB/MySQL

-- Create database if not exists
-- CREATE DATABASE IF NOT EXISTS orders;
-- USE orders;

-- Drop tables if they exist (for development)
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS customer_items;
DROP TABLE IF EXISTS product_abbreviations;
DROP TABLE IF EXISTS customer_abbreviations;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS uom;

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

-- Customer abbreviations for shorthand parsing
CREATE TABLE customer_abbreviations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    abbreviation VARCHAR(20) NOT NULL,
    confidence_score INT DEFAULT 100,
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
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
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_customer_abbreviation (customer_id, abbreviation),
    INDEX idx_product_abbreviation (product_id, abbreviation)
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
    frequency_score INT DEFAULT 1,
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
    original_input TEXT,
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
    customer_reference VARCHAR(100),
    parsed_from VARCHAR(50),
    line_number INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (uom_id) REFERENCES uom(id),
    INDEX idx_order_product (order_id, product_id),
    INDEX idx_item_code (item_code)
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

-- Insert sample customer data
INSERT INTO customers (code, name, business_name, phone, email) VALUES
('G18', 'Graceful 18th', 'Graceful 18th Restaurant', '555-0118', 'orders@graceful18th.com'),
('MS', 'Main Street', 'Main Street Deli', '555-0123', 'mainst@deli.com'),
('CP', 'Corner Pub', 'Corner Pub & Grill', '555-0456', 'orders@cornerpub.com'),
('GG', 'Green Garden', 'Green Garden Cafe', '555-0789', 'chef@greengarden.com');