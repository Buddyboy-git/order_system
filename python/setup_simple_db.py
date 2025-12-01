#!/usr/bin/env python3
"""
Order Entry System - Simple Database Setup
Simplified setup without complex SQL functions and triggers
"""

import pandas as pd
import mysql.connector
from mysql.connector import Error
import os
from datetime import datetime

class SimpleOrderEntryDBSetup:
    def __init__(self):
        # Database connection settings
        self.db_config = {
            'host': 'localhost',
            'database': 'orders',
            'user': 'root',
            'password': '',
            'charset': 'utf8mb4'
        }
        
        self.master_csv_path = 'd:/xampp/htdocs/orders/master_vendor_prices.csv'
        self.connection = None

    def connect_database(self):
        """Connect to MySQL database"""
        try:
            self.connection = mysql.connector.connect(**self.db_config)
            if self.connection.is_connected():
                print("✅ Connected to MySQL database successfully")
                return True
        except Error as e:
            print(f"❌ Error connecting to MySQL: {e}")
            return False

    def create_database_schema(self):
        """Execute the simplified SQL schema file"""
        print("\n🔍 CREATING DATABASE SCHEMA")
        print("=" * 35)
        
        try:
            cursor = self.connection.cursor()
            
            # Read and execute the schema file
            with open('order_entry_schema_simple.sql', 'r', encoding='utf-8') as file:
                sql_content = file.read()
            
            # Split by semicolons and execute each statement
            statements = [stmt.strip() for stmt in sql_content.split(';') if stmt.strip()]
            
            for statement in statements:
                if statement and not statement.startswith('--'):
                    try:
                        cursor.execute(statement)
                        self.connection.commit()
                        print(f"   ✅ Executed: {statement[:50]}...")
                    except Error as e:
                        if "already exists" not in str(e).lower():
                            print(f"   ⚠️ Warning: {e}")
            
            print("   📊 Database schema created successfully!")
            return True
            
        except Exception as e:
            print(f"   ❌ Error creating schema: {e}")
            return False

    def load_master_products(self):
        """Load products from master_vendor_prices.csv"""
        print("\n📦 LOADING MASTER PRODUCTS")
        print("=" * 30)
        
        try:
            # Check if master CSV exists
            if not os.path.exists(self.master_csv_path):
                print(f"   ⚠️ Master CSV not found at {self.master_csv_path}")
                print("   📝 Continuing without product data...")
                return 0
            
            # Read the master CSV file
            df = pd.read_csv(self.master_csv_path)
            print(f"   📋 Found {len(df)} products in master file")
            
            # Get UOM mappings
            uom_mapping = self.get_uom_mappings()
            
            cursor = self.connection.cursor()
            
            # Clear existing products
            cursor.execute("TRUNCATE TABLE products")
            print("   🗑️ Cleared existing products")
            
            # Insert products
            insert_query = """
            INSERT INTO products (item_code, description, price, vendor, category, uom_id)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
            
            products_inserted = 0
            
            for index, row in df.iterrows():
                try:
                    # Map UOM - try to find best match
                    uom_id = self.map_uom(row.get('uom', 'EA'), uom_mapping)
                    
                    values = (
                        str(row.get('item_code', '')),
                        str(row.get('description', '')),
                        float(row.get('price', 0.0)),
                        str(row.get('dc', '')),  # vendor
                        str(row.get('category', '')),
                        uom_id
                    )
                    
                    cursor.execute(insert_query, values)
                    products_inserted += 1
                    
                    if products_inserted % 1000 == 0:
                        print(f"   📦 Inserted {products_inserted} products...")
                        self.connection.commit()
                        
                except Exception as e:
                    print(f"   ⚠️ Error inserting product {row.get('item_code', 'Unknown')}: {e}")
            
            self.connection.commit()
            print(f"   ✅ Successfully inserted {products_inserted} products!")
            
            return products_inserted
            
        except Exception as e:
            print(f"   ❌ Error loading products: {e}")
            return 0

    def get_uom_mappings(self):
        """Get UOM ID mappings from database"""
        cursor = self.connection.cursor()
        cursor.execute("SELECT id, code, name FROM uom")
        
        uom_mapping = {}
        for uom_id, code, name in cursor.fetchall():
            uom_mapping[code.upper()] = uom_id
            uom_mapping[name.upper()] = uom_id
        
        return uom_mapping

    def map_uom(self, uom_string, uom_mapping):
        """Map UOM string to UOM ID"""
        if not uom_string or pd.isna(uom_string):
            return uom_mapping.get('EA', 1)  # Default to Each
        
        uom_upper = str(uom_string).upper().strip()
        
        # Direct mapping
        if uom_upper in uom_mapping:
            return uom_mapping[uom_upper]
        
        # Common variations
        mappings = {
            'EACH': 'EA',
            'PIECE': 'PC', 
            'PIECES': 'PC',
            'POUND': 'LB',
            'POUNDS': 'LB',
            'CASE': 'CS',
            'CASES': 'CS',
            'BOX': 'BX',
            'BOXES': 'BX',
            'DOZEN': 'DZ',
            'GALLON': 'GAL',
            'GALLONS': 'GAL'
        }
        
        if uom_upper in mappings:
            return uom_mapping.get(mappings[uom_upper], uom_mapping.get('EA', 1))
        
        # Default to Each
        return uom_mapping.get('EA', 1)

    def create_sample_customer_data(self):
        """Create sample customer abbreviations and product mappings"""
        print("\n👥 CREATING SAMPLE CUSTOMER DATA")
        print("=" * 35)
        
        cursor = self.connection.cursor()
        
        # Sample customer abbreviations
        customer_abbreviations = [
            (1, 'g18', 100),  # Graceful 18th
            (1, 'grace', 90),
            (1, 'graceful', 95),
            (2, 'ms', 100),   # Main Street
            (2, 'main', 95),
            (3, 'cp', 100),   # Corner Pub
            (3, 'corner', 95),
            (3, 'pub', 90),
            (4, 'gg', 100),   # Green Garden
            (4, 'green', 95),
            (4, 'garden', 90)
        ]
        
        # Insert customer abbreviations
        insert_abbr_query = """
        INSERT INTO customer_abbreviations (customer_id, abbreviation, confidence_score)
        VALUES (%s, %s, %s)
        ON DUPLICATE KEY UPDATE confidence_score = VALUES(confidence_score)
        """
        
        cursor.executemany(insert_abbr_query, customer_abbreviations)
        print(f"   ✅ Created {len(customer_abbreviations)} customer abbreviations")
        
        # Sample product abbreviations (for Graceful 18th)
        # Find some sample products first
        cursor.execute("""
        SELECT id, item_code, description 
        FROM products 
        WHERE description LIKE '%turkey%' 
           OR description LIKE '%beef%' 
           OR description LIKE '%ham%'
           OR description LIKE '%cheese%'
           OR description LIKE '%salami%'
        LIMIT 20
        """)
        
        sample_products = cursor.fetchall()
        
        product_abbreviations = []
        customer_id = 1  # Graceful 18th
        
        for product_id, item_code, description in sample_products:
            desc_lower = description.lower()
            
            # Create abbreviations based on product type
            if 'turkey' in desc_lower:
                product_abbreviations.extend([
                    (customer_id, product_id, 't', 100),
                    (customer_id, product_id, 'turkey', 95),
                    (customer_id, product_id, 'turk', 90)
                ])
            elif 'beef' in desc_lower or 'roast' in desc_lower:
                product_abbreviations.extend([
                    (customer_id, product_id, 'rb', 100),
                    (customer_id, product_id, 'beef', 95),
                    (customer_id, product_id, 'roast', 90)
                ])
            elif 'ham' in desc_lower:
                product_abbreviations.extend([
                    (customer_id, product_id, 'h', 100),
                    (customer_id, product_id, 'ham', 95)
                ])
            elif 'cheese' in desc_lower:
                product_abbreviations.extend([
                    (customer_id, product_id, 'ch', 100),
                    (customer_id, product_id, 'cheese', 90)
                ])
            elif 'salami' in desc_lower:
                product_abbreviations.extend([
                    (customer_id, product_id, 'sm', 100),
                    (customer_id, product_id, 'sal', 90),
                    (customer_id, product_id, 'salami', 95)
                ])
        
        if product_abbreviations:
            insert_prod_abbr_query = """
            INSERT INTO product_abbreviations (customer_id, product_id, abbreviation, confidence_score)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE confidence_score = VALUES(confidence_score)
            """
            
            cursor.executemany(insert_prod_abbr_query, product_abbreviations)
            print(f"   ✅ Created {len(product_abbreviations)} product abbreviations")
        else:
            print("   ⚠️ No matching products found for abbreviations")
        
        self.connection.commit()
        print("   📊 Sample customer data created successfully!")

    def verify_setup(self):
        """Verify the database setup"""
        print("\n🔍 VERIFYING DATABASE SETUP")
        print("=" * 32)
        
        cursor = self.connection.cursor()
        
        # Check products
        cursor.execute("SELECT COUNT(*) FROM products")
        product_count = cursor.fetchone()[0]
        print(f"   📦 Products: {product_count:,}")
        
        # Check customers
        cursor.execute("SELECT COUNT(*) FROM customers")
        customer_count = cursor.fetchone()[0]
        print(f"   👥 Customers: {customer_count}")
        
        # Check UOM
        cursor.execute("SELECT COUNT(*) FROM uom")
        uom_count = cursor.fetchone()[0]
        print(f"   📏 Units of Measure: {uom_count}")
        
        # Check abbreviations
        cursor.execute("SELECT COUNT(*) FROM customer_abbreviations")
        cust_abbr_count = cursor.fetchone()[0]
        print(f"   🔤 Customer Abbreviations: {cust_abbr_count}")
        
        cursor.execute("SELECT COUNT(*) FROM product_abbreviations")
        prod_abbr_count = cursor.fetchone()[0]
        print(f"   🏷️ Product Abbreviations: {prod_abbr_count}")
        
        # Sample queries to test the shorthand system
        if cust_abbr_count > 0 and prod_abbr_count > 0:
            print(f"\n🧪 TESTING SAMPLE QUERIES:")
            
            # Test customer lookup
            cursor.execute("""
            SELECT c.name, ca.abbreviation, ca.confidence_score
            FROM customers c
            JOIN customer_abbreviations ca ON c.id = ca.customer_id
            WHERE ca.abbreviation = 'g18'
            LIMIT 1
            """)
            
            result = cursor.fetchone()
            if result:
                print(f"   ✅ Customer lookup 'g18' → {result[0]} (confidence: {result[2]})")
            
            # Test product lookup
            cursor.execute("""
            SELECT p.description, pa.abbreviation, pa.confidence_score
            FROM products p
            JOIN product_abbreviations pa ON p.id = pa.product_id
            WHERE pa.customer_id = 1 AND pa.abbreviation = 't'
            LIMIT 1
            """)
            
            result = cursor.fetchone()
            if result:
                print(f"   ✅ Product lookup 't' → {result[0][:50]}... (confidence: {result[2]})")
        
        print(f"\n🎉 DATABASE SETUP COMPLETE!")
        print(f"   Ready for intelligent shorthand order entry system!")

    def close_connection(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            print("📝 Database connection closed")

def main():
    setup = SimpleOrderEntryDBSetup()
    
    print("🚀 Order Entry System Database Setup (Simplified)")
    print("=" * 50)
    print("Setting up intelligent shorthand order entry system...")
    
    # Connect to database
    if not setup.connect_database():
        return
    
    try:
        # Create schema
        if not setup.create_database_schema():
            print("⚠️ Schema creation had issues, but continuing...")
        
        # Load products
        products_loaded = setup.load_master_products()
        if products_loaded == 0:
            print("⚠️ No products loaded - continuing with schema only")
        
        # Create sample data
        setup.create_sample_customer_data()
        
        # Verify setup
        setup.verify_setup()
        
    finally:
        setup.close_connection()

if __name__ == "__main__":
    main()