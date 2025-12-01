#!/usr/bin/env python3
"""
Order Entry System - Intelligent Shorthand Parser
Recreates the brilliant shorthand parsing system with customer-context awareness
"""

import re
import mysql.connector
from mysql.connector import Error
from typing import List, Dict, Tuple, Optional
from dataclasses import dataclass
from fuzzywuzzy import fuzz, process
import logging

@dataclass
class ParsedItem:
    """Represents a parsed product item"""
    raw_input: str
    quantity: float
    product_code: str = ""
    product_name: str = ""
    product_id: int = None
    uom: str = "EA"
    uom_id: int = 1
    confidence: int = 0
    alternatives: List[Dict] = None
    
    def __post_init__(self):
        if self.alternatives is None:
            self.alternatives = []

@dataclass 
class ParsedCustomer:
    """Represents a parsed customer"""
    raw_input: str
    customer_id: int = None
    customer_name: str = ""
    customer_code: str = ""
    confidence: int = 0
    alternatives: List[Dict] = None
    
    def __post_init__(self):
        if self.alternatives is None:
            self.alternatives = []

@dataclass
class ParsedOrder:
    """Represents a complete parsed order"""
    customer: ParsedCustomer
    items: List[ParsedItem]
    raw_input: str
    parsing_errors: List[str] = None
    
    def __post_init__(self):
        if self.parsing_errors is None:
            self.parsing_errors = []

class ShorthandParser:
    """
    Intelligent shorthand parser that recreates the original system's magic
    
    Handles patterns like: g18↵1t2sm4rb
    - g18 = Graceful 18th (customer)
    - 1t = 1 piece turkey
    - 2sm = 2 pieces salami  
    - 4rb = 4 pieces roast beef
    """
    
    def __init__(self, db_config):
        self.db_config = db_config
        self.connection = None
        self.logger = self._setup_logging()
        
        # Parsing patterns
        self.customer_pattern = re.compile(r'^([a-zA-Z]+\d*)', re.IGNORECASE)
        self.product_pattern = re.compile(r'(\d+)([a-zA-Z]+)', re.IGNORECASE)
        
        # Cache for performance
        self.customer_cache = {}
        self.product_cache = {}

    def _setup_logging(self):
        """Set up logging for debugging"""
        logging.basicConfig(level=logging.INFO)
        return logging.getLogger(__name__)

    def connect_database(self):
        """Connect to the database"""
        try:
            self.connection = mysql.connector.connect(**self.db_config)
            if self.connection.is_connected():
                self.logger.info("Connected to database successfully")
                return True
        except Error as e:
            self.logger.error(f"Database connection error: {e}")
            return False

    def parse_order(self, input_text: str) -> ParsedOrder:
        """
        Parse complete order input with customer and products
        
        Input format: "g18↵1t2sm4rb" or "g18\n1t2sm4rb"
        - Double newline = new customer
        - Single newline = same customer, new product line
        """
        self.logger.info(f"Parsing order input: {input_text[:50]}...")
        
        # Split by double newlines for multiple customers
        customer_blocks = re.split(r'\n\n+', input_text.strip())
        
        # For now, handle single customer (first block)
        customer_block = customer_blocks[0]
        
        # Split by single newlines for customer + products
        lines = customer_block.split('\n')
        
        if not lines:
            return ParsedOrder(
                customer=ParsedCustomer("", confidence=0),
                items=[],
                raw_input=input_text,
                parsing_errors=["Empty input"]
            )
        
        # First line should contain customer + possibly first products
        first_line = lines[0].strip()
        
        # Parse customer from first line
        customer = self.parse_customer(first_line)
        
        # Parse all product lines
        items = []
        parsing_errors = []
        
        # Check if first line has products after customer code
        customer_match = self.customer_pattern.match(first_line)
        if customer_match:
            remaining_first_line = first_line[customer_match.end():]
            if remaining_first_line.strip():
                # Parse products from remainder of first line
                first_line_items, first_line_errors = self.parse_products(
                    remaining_first_line, customer.customer_id
                )
                items.extend(first_line_items)
                parsing_errors.extend(first_line_errors)
        
        # Parse remaining lines as products
        for line in lines[1:]:
            line = line.strip()
            if line:
                line_items, line_errors = self.parse_products(line, customer.customer_id)
                items.extend(line_items)
                parsing_errors.extend(line_errors)
        
        return ParsedOrder(
            customer=customer,
            items=items,
            raw_input=input_text,
            parsing_errors=parsing_errors
        )

    def parse_customer(self, input_text: str) -> ParsedCustomer:
        """
        Parse customer code from input
        Examples: g18 -> Graceful 18th, ms -> Main Street
        """
        # Extract customer code (letters + optional numbers at start)
        match = self.customer_pattern.match(input_text.strip())
        
        if not match:
            return ParsedCustomer(
                raw_input=input_text,
                confidence=0,
                alternatives=[]
            )
        
        customer_code = match.group(1).lower()
        self.logger.info(f"Parsing customer code: {customer_code}")
        
        # Check cache first
        if customer_code in self.customer_cache:
            cached = self.customer_cache[customer_code]
            return ParsedCustomer(
                raw_input=input_text,
                customer_id=cached['id'],
                customer_name=cached['name'],
                customer_code=cached['code'],
                confidence=cached['confidence']
            )
        
        # Query database for customer
        try:
            cursor = self.connection.cursor()
            
            # Direct match first
            cursor.execute("""
            SELECT c.id, c.name, c.code, ca.confidence_score
            FROM customers c
            JOIN customer_abbreviations ca ON c.id = ca.customer_id
            WHERE LOWER(ca.abbreviation) = %s
            ORDER BY ca.confidence_score DESC
            LIMIT 1
            """, (customer_code,))
            
            result = cursor.fetchone()
            
            if result:
                # Cache the result
                self.customer_cache[customer_code] = {
                    'id': result[0],
                    'name': result[1], 
                    'code': result[2],
                    'confidence': result[3]
                }
                
                return ParsedCustomer(
                    raw_input=input_text,
                    customer_id=result[0],
                    customer_name=result[1],
                    customer_code=result[2],
                    confidence=result[3]
                )
            
            # Fuzzy matching if no direct match
            cursor.execute("""
            SELECT c.id, c.name, c.code, ca.abbreviation, ca.confidence_score
            FROM customers c
            JOIN customer_abbreviations ca ON c.id = ca.customer_id
            """)
            
            all_customers = cursor.fetchall()
            alternatives = []
            
            for cust_id, name, code, abbr, conf in all_customers:
                # Calculate similarity
                similarity = fuzz.ratio(customer_code, abbr.lower())
                if similarity > 60:  # Threshold for suggestions
                    alternatives.append({
                        'customer_id': cust_id,
                        'name': name,
                        'code': code,
                        'abbreviation': abbr,
                        'similarity': similarity,
                        'confidence': conf
                    })
            
            # Sort by similarity
            alternatives.sort(key=lambda x: x['similarity'], reverse=True)
            
            return ParsedCustomer(
                raw_input=input_text,
                confidence=0,
                alternatives=alternatives[:5]  # Top 5 suggestions
            )
            
        except Error as e:
            self.logger.error(f"Database error in customer parsing: {e}")
            return ParsedCustomer(
                raw_input=input_text,
                confidence=0
            )

    def parse_products(self, input_text: str, customer_id: int) -> Tuple[List[ParsedItem], List[str]]:
        """
        Parse product codes from input
        Examples: 1t2sm4rb -> [1 turkey, 2 salami, 4 roast beef]
        """
        items = []
        errors = []
        
        # Find all quantity+product combinations
        matches = self.product_pattern.findall(input_text)
        
        if not matches:
            errors.append(f"No products found in: {input_text}")
            return items, errors
        
        self.logger.info(f"Found {len(matches)} product codes: {matches}")
        
        for quantity_str, product_code in matches:
            try:
                quantity = float(quantity_str)
                item = self.parse_single_product(product_code, quantity, customer_id)
                items.append(item)
                
                if item.confidence == 0:
                    errors.append(f"Could not identify product: {quantity_str}{product_code}")
                    
            except ValueError:
                errors.append(f"Invalid quantity: {quantity_str}")
        
        return items, errors

    def parse_single_product(self, product_code: str, quantity: float, customer_id: int) -> ParsedItem:
        """
        Parse a single product code with customer context
        Examples: t -> turkey (for customer), sm -> salami
        """
        product_code_lower = product_code.lower()
        cache_key = f"{customer_id}_{product_code_lower}"
        
        # Check cache
        if cache_key in self.product_cache:
            cached = self.product_cache[cache_key]
            return ParsedItem(
                raw_input=f"{quantity}{product_code}",
                quantity=quantity,
                product_id=cached['id'],
                product_name=cached['name'],
                product_code=cached['code'],
                confidence=cached['confidence'],
                uom=cached['uom'],
                uom_id=cached['uom_id']
            )
        
        try:
            cursor = self.connection.cursor()
            
            # Direct abbreviation match for this customer
            cursor.execute("""
            SELECT p.id, p.item_code, p.description, pa.confidence_score, u.code, u.id
            FROM products p
            JOIN product_abbreviations pa ON p.id = pa.product_id
            LEFT JOIN uom u ON p.uom_id = u.id
            WHERE pa.customer_id = %s 
            AND LOWER(pa.abbreviation) = %s
            ORDER BY pa.confidence_score DESC, pa.usage_count DESC
            LIMIT 1
            """, (customer_id, product_code_lower))
            
            result = cursor.fetchone()
            
            if result:
                # Cache the result
                self.product_cache[cache_key] = {
                    'id': result[0],
                    'code': result[1],
                    'name': result[2],
                    'confidence': result[3],
                    'uom': result[4] or 'EA',
                    'uom_id': result[5] or 1
                }
                
                return ParsedItem(
                    raw_input=f"{quantity}{product_code}",
                    quantity=quantity,
                    product_id=result[0],
                    product_code=result[1],
                    product_name=result[2],
                    confidence=result[3],
                    uom=result[4] or 'EA',
                    uom_id=result[5] or 1
                )
            
            # Fuzzy matching against customer's product history
            cursor.execute("""
            SELECT p.id, p.item_code, p.description, pa.abbreviation, 
                   pa.confidence_score, u.code, u.id
            FROM products p
            JOIN product_abbreviations pa ON p.id = pa.product_id
            LEFT JOIN uom u ON p.uom_id = u.id
            WHERE pa.customer_id = %s
            """, (customer_id,))
            
            all_products = cursor.fetchall()
            alternatives = []
            
            for prod_id, item_code, desc, abbr, conf, uom_code, uom_id in all_products:
                similarity = fuzz.ratio(product_code_lower, abbr.lower())
                if similarity > 70:  # Higher threshold for products
                    alternatives.append({
                        'product_id': prod_id,
                        'item_code': item_code,
                        'description': desc,
                        'abbreviation': abbr,
                        'similarity': similarity,
                        'confidence': conf,
                        'uom': uom_code or 'EA',
                        'uom_id': uom_id or 1
                    })
            
            # Sort by similarity
            alternatives.sort(key=lambda x: x['similarity'], reverse=True)
            
            return ParsedItem(
                raw_input=f"{quantity}{product_code}",
                quantity=quantity,
                confidence=0,
                alternatives=alternatives[:5]
            )
            
        except Error as e:
            self.logger.error(f"Database error in product parsing: {e}")
            return ParsedItem(
                raw_input=f"{quantity}{product_code}",
                quantity=quantity,
                confidence=0
            )

    def reparse_with_customer(self, parsed_order: ParsedOrder, new_customer_id: int) -> ParsedOrder:
        """
        Reparse all products with new customer context - the magic feature!
        When customer is corrected, all products get re-interpreted
        """
        self.logger.info(f"Re-parsing order with new customer ID: {new_customer_id}")
        
        # Get new customer info
        try:
            cursor = self.connection.cursor()
            cursor.execute("SELECT id, name, code FROM customers WHERE id = %s", (new_customer_id,))
            customer_info = cursor.fetchone()
            
            if not customer_info:
                parsed_order.parsing_errors.append(f"Customer ID {new_customer_id} not found")
                return parsed_order
            
            # Update customer
            parsed_order.customer.customer_id = customer_info[0]
            parsed_order.customer.customer_name = customer_info[1]
            parsed_order.customer.customer_code = customer_info[2]
            parsed_order.customer.confidence = 100
            
            # Re-parse all products with new customer context
            reparsed_items = []
            
            for item in parsed_order.items:
                # Extract original product code from raw input
                match = self.product_pattern.match(item.raw_input)
                if match:
                    quantity_str, product_code = match.groups()
                    quantity = float(quantity_str)
                    
                    # Re-parse with new customer context
                    reparsed_item = self.parse_single_product(product_code, quantity, new_customer_id)
                    reparsed_items.append(reparsed_item)
                else:
                    # Keep original if can't re-parse
                    reparsed_items.append(item)
            
            parsed_order.items = reparsed_items
            
            # Clear customer-related errors
            parsed_order.parsing_errors = [
                error for error in parsed_order.parsing_errors 
                if 'customer' not in error.lower()
            ]
            
            self.logger.info(f"Successfully re-parsed {len(reparsed_items)} items with new customer context")
            
        except Exception as e:
            self.logger.error(f"Error re-parsing with new customer: {e}")
            parsed_order.parsing_errors.append(f"Error re-parsing: {e}")
        
        return parsed_order

    def close_connection(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()

# Test function
def test_parser():
    """Test the shorthand parser"""
    db_config = {
        'host': 'localhost',
        'database': 'orders',
        'user': 'root',
        'password': '',
        'charset': 'utf8mb4'
    }
    
    parser = ShorthandParser(db_config)
    
    if not parser.connect_database():
        print("❌ Could not connect to database")
        return
    
    try:
        print("🧪 Testing Shorthand Parser")
        print("=" * 30)
        
        # Test cases
        test_inputs = [
            "g18\n1t2sm4rb",  # Graceful 18th: 1 turkey, 2 salami, 4 roast beef
            "ms\n3h1ch",     # Main Street: 3 ham, 1 cheese
            "cp\n2t5rb",     # Corner Pub: 2 turkey, 5 roast beef
        ]
        
        for i, test_input in enumerate(test_inputs, 1):
            print(f"\n📝 Test {i}: {test_input}")
            
            parsed = parser.parse_order(test_input)
            
            print(f"   Customer: {parsed.customer.customer_name} (confidence: {parsed.customer.confidence})")
            print(f"   Items: {len(parsed.items)}")
            
            for item in parsed.items:
                if item.product_name:
                    print(f"      • {item.quantity} x {item.product_name} (confidence: {item.confidence})")
                else:
                    print(f"      • {item.quantity} x {item.raw_input} (UNKNOWN - {len(item.alternatives)} alternatives)")
            
            if parsed.parsing_errors:
                print(f"   ⚠️ Errors: {parsed.parsing_errors}")
        
        print(f"\n✅ Parser testing complete!")
        
    finally:
        parser.close_connection()

if __name__ == "__main__":
    test_parser()