#!/usr/bin/env python3
"""
Order Entry System - Shorthand Parser CLI
Command line interface for the shorthand parser used by PHP
"""

import sys
import json
from shorthand_parser import ShorthandParser

def serialize_parsed_order(parsed_order):
    """Convert ParsedOrder to JSON-serializable dict"""
    return {
        'customer': {
            'raw_input': parsed_order.customer.raw_input,
            'customer_id': parsed_order.customer.customer_id,
            'customer_name': parsed_order.customer.customer_name,
            'customer_code': parsed_order.customer.customer_code,
            'confidence': parsed_order.customer.confidence,
            'alternatives': parsed_order.customer.alternatives
        },
        'items': [
            {
                'raw_input': item.raw_input,
                'quantity': item.quantity,
                'product_code': item.product_code,
                'product_name': item.product_name,
                'product_id': item.product_id,
                'uom': item.uom,
                'uom_id': item.uom_id,
                'confidence': item.confidence,
                'alternatives': item.alternatives
            }
            for item in parsed_order.items
        ],
        'raw_input': parsed_order.raw_input,
        'parsing_errors': parsed_order.parsing_errors
    }

def main():
    if len(sys.argv) != 2:
        print(json.dumps({'error': 'Usage: python parse_shorthand.py "<shorthand_input>"'}))
        sys.exit(1)
    
    shorthand_input = sys.argv[1]
    
    # Database configuration
    db_config = {
        'host': 'localhost',
        'database': 'orders',
        'user': 'root',
        'password': '',  # Adjust as needed
        'charset': 'utf8mb4'
    }
    
    parser = ShorthandParser(db_config)
    
    try:
        if not parser.connect_database():
            print(json.dumps({
                'error': 'Database connection failed',
                'customer': {'customer_name': '', 'confidence': 0, 'alternatives': []},
                'items': [],
                'parsing_errors': ['Could not connect to database']
            }))
            sys.exit(1)
        
        # Parse the order
        parsed_order = parser.parse_order(shorthand_input)
        
        # Serialize and output as JSON
        result = serialize_parsed_order(parsed_order)
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps({
            'error': str(e),
            'customer': {'customer_name': '', 'confidence': 0, 'alternatives': []},
            'items': [],
            'parsing_errors': [f'Parser error: {str(e)}']
        }))
        sys.exit(1)
    
    finally:
        parser.close_connection()

if __name__ == '__main__':
    main()