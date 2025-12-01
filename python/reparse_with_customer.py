#!/usr/bin/env python3
"""
Order Entry System - Reparse with Customer CLI
Handles customer correction and re-contextualization
"""

import sys
import json
from shorthand_parser import ShorthandParser, ParsedOrder, ParsedCustomer, ParsedItem

def deserialize_parsed_order(data):
    """Convert JSON dict back to ParsedOrder object"""
    customer_data = data['customer']
    customer = ParsedCustomer(
        raw_input=customer_data['raw_input'],
        customer_id=customer_data['customer_id'],
        customer_name=customer_data['customer_name'],
        customer_code=customer_data['customer_code'],
        confidence=customer_data['confidence'],
        alternatives=customer_data['alternatives']
    )
    
    items = []
    for item_data in data['items']:
        item = ParsedItem(
            raw_input=item_data['raw_input'],
            quantity=item_data['quantity'],
            product_code=item_data['product_code'],
            product_name=item_data['product_name'],
            product_id=item_data['product_id'],
            uom=item_data['uom'],
            uom_id=item_data['uom_id'],
            confidence=item_data['confidence'],
            alternatives=item_data['alternatives']
        )
        items.append(item)
    
    return ParsedOrder(
        customer=customer,
        items=items,
        raw_input=data['raw_input'],
        parsing_errors=data['parsing_errors']
    )

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
    if len(sys.argv) != 3:
        print(json.dumps({'error': 'Usage: python reparse_with_customer.py "<order_json>" <new_customer_id>'}))
        sys.exit(1)
    
    order_json = sys.argv[1]
    new_customer_id = int(sys.argv[2])
    
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
        
        # Deserialize the order
        order_data = json.loads(order_json)
        parsed_order = deserialize_parsed_order(order_data)
        
        # Reparse with new customer
        reparsed_order = parser.reparse_with_customer(parsed_order, new_customer_id)
        
        # Serialize and output as JSON
        result = serialize_parsed_order(reparsed_order)
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps({
            'error': str(e),
            'customer': {'customer_name': '', 'confidence': 0, 'alternatives': []},
            'items': [],
            'parsing_errors': [f'Reparse error: {str(e)}']
        }))
        sys.exit(1)
    
    finally:
        parser.close_connection()

if __name__ == '__main__':
    main()