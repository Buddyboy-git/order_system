#!/usr/bin/env python3
"""
Order Entry System - Order Management
Handles order lifecycle: draft → submitted → delivered → archived
"""

import mysql.connector
from mysql.connector import Error
from datetime import datetime, date
from typing import List, Dict, Optional
import json

class OrderManager:
    """
    Manages the complete order lifecycle and customer history
    """
    
    def __init__(self, db_config):
        self.db_config = db_config
        self.connection = None
    
    def connect_database(self):
        """Connect to the database"""
        try:
            self.connection = mysql.connector.connect(**self.db_config)
            if self.connection.is_connected():
                return True
        except Error as e:
            print(f"Database connection error: {e}")
            return False
    
    def generate_order_number(self) -> str:
        """Generate unique order number: YYYYMMDD-NNNN"""
        try:
            cursor = self.connection.cursor()
            today = datetime.now().strftime('%Y%m%d')
            
            # Find the next number for today
            cursor.execute("""
                SELECT MAX(CAST(SUBSTRING(order_number, 10) AS UNSIGNED)) 
                FROM orders 
                WHERE order_number LIKE %s
            """, (f"{today}-%",))
            
            result = cursor.fetchone()
            next_num = 1 if result[0] is None else result[0] + 1
            
            return f"{today}-{next_num:04d}"
            
        except Exception as e:
            # Fallback to timestamp-based number
            return f"{datetime.now().strftime('%Y%m%d-%H%M%S')}"
    
    def save_order(self, parsed_order, order_method='shorthand') -> Optional[int]:
        """
        Save a parsed order to the database as draft
        Returns order_id if successful
        """
        try:
            cursor = self.connection.cursor()
            
            # Generate order number
            order_number = self.generate_order_number()
            
            # Calculate totals (simplified - no tax for now)
            subtotal = 0.0
            
            # Get customer ID
            customer_id = parsed_order.customer.customer_id
            if not customer_id:
                raise ValueError("No valid customer found in parsed order")
            
            # Create order record
            insert_order_query = """
            INSERT INTO orders (
                order_number, customer_id, order_date, status, order_method,
                subtotal, total_amount, original_input, created_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            order_values = (
                order_number,
                customer_id,
                date.today(),
                'draft',
                order_method,
                subtotal,
                subtotal,  # total = subtotal for now
                parsed_order.raw_input,
                datetime.now()
            )
            
            cursor.execute(insert_order_query, order_values)
            order_id = cursor.lastrowid
            
            # Add order items
            insert_item_query = """
            INSERT INTO order_items (
                order_id, product_id, item_code, product_name, quantity,
                uom_id, unit_price, line_total, customer_reference, 
                parsed_from, line_number
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            line_number = 1
            total_amount = 0.0
            
            for item in parsed_order.items:
                if item.product_id and item.confidence > 0:
                    # Get current price from products table
                    cursor.execute("SELECT price FROM products WHERE id = %s", (item.product_id,))
                    price_result = cursor.fetchone()
                    unit_price = float(price_result[0]) if price_result else 0.0
                    
                    line_total = float(item.quantity) * unit_price
                    total_amount += line_total
                    
                    item_values = (
                        order_id,
                        item.product_id,
                        item.product_code,
                        item.product_name,
                        item.quantity,
                        item.uom_id,
                        unit_price,
                        line_total,
                        item.raw_input,  # customer reference
                        item.raw_input,  # parsed from
                        line_number
                    )
                    
                    cursor.execute(insert_item_query, item_values)
                    line_number += 1
            
            # Update order totals
            cursor.execute("""
                UPDATE orders 
                SET subtotal = %s, total_amount = %s 
                WHERE id = %s
            """, (total_amount, total_amount, order_id))
            
            # Add to order history
            self.add_order_history(order_id, None, 'draft', 'System', 'Order created from shorthand input')
            
            self.connection.commit()
            
            print(f"✅ Order {order_number} saved successfully (ID: {order_id})")
            return order_id
            
        except Exception as e:
            print(f"❌ Error saving order: {e}")
            if self.connection:
                self.connection.rollback()
            return None
    
    def submit_order(self, order_id: int, notes: str = '') -> bool:
        """Submit order for processing"""
        return self._change_order_status(order_id, 'submitted', 'Order submitted for processing', notes)
    
    def start_delivery(self, order_id: int, delivery_date: date = None, notes: str = '') -> bool:
        """Mark order as out for delivery"""
        try:
            cursor = self.connection.cursor()
            
            if delivery_date is None:
                delivery_date = date.today()
            
            # Update order
            cursor.execute("""
                UPDATE orders 
                SET status = 'out_for_delivery', delivery_date = %s, updated_at = %s
                WHERE id = %s AND status IN ('submitted', 'processing')
            """, (delivery_date, datetime.now(), order_id))
            
            if cursor.rowcount == 0:
                return False
            
            # Add to history
            self.add_order_history(order_id, None, 'out_for_delivery', 'System', f'Out for delivery on {delivery_date}. {notes}')
            
            self.connection.commit()
            return True
            
        except Exception as e:
            print(f"Error starting delivery: {e}")
            return False
    
    def complete_delivery(self, order_id: int, delivered_quantities: Dict[int, float] = None, notes: str = '') -> bool:
        """Mark order as delivered and update quantities"""
        try:
            cursor = self.connection.cursor()
            
            delivered_at = datetime.now()
            delivered_date = delivered_at.date()
            
            # Update delivered quantities if provided
            if delivered_quantities:
                for item_id, delivered_qty in delivered_quantities.items():
                    cursor.execute("""
                        UPDATE order_items 
                        SET delivered_quantity = %s 
                        WHERE id = %s AND order_id = %s
                    """, (delivered_qty, item_id, order_id))
            else:
                # Default: delivered quantity = ordered quantity
                cursor.execute("""
                    UPDATE order_items 
                    SET delivered_quantity = quantity 
                    WHERE order_id = %s
                """, (order_id,))
            
            # Update order status
            cursor.execute("""
                UPDATE orders 
                SET status = 'delivered', delivered_date = %s, delivered_at = %s, 
                    delivery_notes = %s, updated_at = %s
                WHERE id = %s AND status = 'out_for_delivery'
            """, (delivered_date, delivered_at, notes, datetime.now(), order_id))
            
            if cursor.rowcount == 0:
                return False
            
            # Add to history
            self.add_order_history(order_id, 'out_for_delivery', 'delivered', 'System', f'Delivered on {delivered_date}. {notes}')
            
            # Update customer product frequency (learning system)
            self._update_customer_product_frequency(order_id)
            
            self.connection.commit()
            return True
            
        except Exception as e:
            print(f"Error completing delivery: {e}")
            return False
    
    def archive_order(self, order_id: int, notes: str = '') -> bool:
        """Archive a delivered order"""
        try:
            cursor = self.connection.cursor()
            
            archived_at = datetime.now()
            
            # Update order status
            cursor.execute("""
                UPDATE orders 
                SET status = 'archived', archived_at = %s, updated_at = %s
                WHERE id = %s AND status = 'delivered'
            """, (archived_at, datetime.now(), order_id))
            
            if cursor.rowcount == 0:
                return False
            
            # Add to history
            self.add_order_history(order_id, 'delivered', 'archived', 'System', f'Order archived. {notes}')
            
            self.connection.commit()
            return True
            
        except Exception as e:
            print(f"Error archiving order: {e}")
            return False
    
    def get_customer_order_history(self, customer_id: int, status: str = None, limit: int = 50) -> List[Dict]:
        """Get order history for a specific customer"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            
            base_query = """
            SELECT 
                o.id, o.order_number, o.order_date, o.delivery_date, o.delivered_date,
                o.status, o.order_method, o.subtotal, o.total_amount, o.notes,
                o.delivery_notes, o.created_at, o.delivered_at, o.archived_at,
                COUNT(oi.id) as item_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.delivered_quantity) as total_delivered
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.customer_id = %s
            """
            
            params = [customer_id]
            
            if status:
                base_query += " AND o.status = %s"
                params.append(status)
            
            base_query += """
            GROUP BY o.id
            ORDER BY o.order_date DESC, o.created_at DESC
            LIMIT %s
            """
            params.append(limit)
            
            cursor.execute(base_query, params)
            orders = cursor.fetchall()
            
            # Get items for each order
            for order in orders:
                cursor.execute("""
                    SELECT 
                        oi.id, oi.product_name, oi.quantity, oi.delivered_quantity,
                        oi.unit_price, oi.line_total, oi.customer_reference,
                        u.code as uom_code
                    FROM order_items oi
                    LEFT JOIN uom u ON oi.uom_id = u.id
                    WHERE oi.order_id = %s
                    ORDER BY oi.line_number
                """, (order['id'],))
                
                order['items'] = cursor.fetchall()
            
            return orders
            
        except Exception as e:
            print(f"Error getting customer order history: {e}")
            return []
    
    def get_order_details(self, order_id: int) -> Optional[Dict]:
        """Get complete order details"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            
            # Get order info
            cursor.execute("""
                SELECT 
                    o.*, c.name as customer_name, c.code as customer_code,
                    c.business_name, c.phone, c.email
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE o.id = %s
            """, (order_id,))
            
            order = cursor.fetchone()
            if not order:
                return None
            
            # Get order items
            cursor.execute("""
                SELECT 
                    oi.*, u.code as uom_code, u.name as uom_name,
                    p.vendor, p.category
                FROM order_items oi
                LEFT JOIN uom u ON oi.uom_id = u.id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = %s
                ORDER BY oi.line_number
            """, (order_id,))
            
            order['items'] = cursor.fetchall()
            
            # Get order history
            cursor.execute("""
                SELECT * FROM order_history
                WHERE order_id = %s
                ORDER BY created_at
            """, (order_id,))
            
            order['history'] = cursor.fetchall()
            
            return order
            
        except Exception as e:
            print(f"Error getting order details: {e}")
            return None
    
    def _change_order_status(self, order_id: int, new_status: str, default_notes: str = '', notes: str = '') -> bool:
        """Helper method to change order status"""
        try:
            cursor = self.connection.cursor()
            
            # Get current status
            cursor.execute("SELECT status FROM orders WHERE id = %s", (order_id,))
            result = cursor.fetchone()
            if not result:
                return False
            
            old_status = result[0]
            
            # Update status
            update_query = "UPDATE orders SET status = %s"
            params = [new_status]
            
            if new_status == 'submitted':
                update_query += ", submitted_at = %s"
                params.append(datetime.now())
            
            update_query += ", updated_at = %s WHERE id = %s"
            params.extend([datetime.now(), order_id])
            
            cursor.execute(update_query, params)
            
            if cursor.rowcount == 0:
                return False
            
            # Add to history
            history_notes = f"{default_notes}. {notes}".strip('. ')
            self.add_order_history(order_id, old_status, new_status, 'System', history_notes)
            
            self.connection.commit()
            return True
            
        except Exception as e:
            print(f"Error changing order status: {e}")
            return False
    
    def add_order_history(self, order_id: int, old_status: str, new_status: str, changed_by: str, notes: str = ''):
        """Add entry to order history"""
        try:
            cursor = self.connection.cursor()
            cursor.execute("""
                INSERT INTO order_history (order_id, old_status, new_status, changed_by, change_notes)
                VALUES (%s, %s, %s, %s, %s)
            """, (order_id, old_status, new_status, changed_by, notes))
        except Exception as e:
            print(f"Error adding order history: {e}")
    
    def _update_customer_product_frequency(self, order_id: int):
        """Update customer product frequency scores for learning"""
        try:
            cursor = self.connection.cursor()
            
            # Get order details
            cursor.execute("""
                SELECT o.customer_id, oi.product_id, oi.delivered_quantity
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.id = %s AND oi.delivered_quantity > 0
            """, (order_id,))
            
            items = cursor.fetchall()
            
            for customer_id, product_id, delivered_qty in items:
                # Update or create customer_items record
                cursor.execute("""
                    INSERT INTO customer_items (customer_id, product_id, frequency_score, last_ordered)
                    VALUES (%s, %s, 1, %s)
                    ON DUPLICATE KEY UPDATE 
                        frequency_score = frequency_score + 1,
                        last_ordered = %s
                """, (customer_id, product_id, date.today(), date.today()))
                
                # Update abbreviation usage
                cursor.execute("""
                    UPDATE product_abbreviations 
                    SET usage_count = usage_count + 1, last_used = %s
                    WHERE customer_id = %s AND product_id = %s
                """, (datetime.now(), customer_id, product_id))
            
        except Exception as e:
            print(f"Error updating customer product frequency: {e}")
    
    def close_connection(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()


# Test functions
def test_order_lifecycle():
    """Test the complete order lifecycle"""
    db_config = {
        'host': 'localhost',
        'database': 'orders',
        'user': 'root',
        'password': '',
        'charset': 'utf8mb4'
    }
    
    manager = OrderManager(db_config)
    
    if not manager.connect_database():
        print("❌ Could not connect to database")
        return
    
    try:
        print("🧪 Testing Order Lifecycle")
        print("=" * 30)
        
        # Create a mock parsed order (you'd normally get this from the parser)
        from shorthand_parser import ParsedOrder, ParsedCustomer, ParsedItem
        
        customer = ParsedCustomer(
            raw_input="g18",
            customer_id=1,
            customer_name="Graceful 18th",
            customer_code="G18",
            confidence=100
        )
        
        items = [
            ParsedItem(
                raw_input="2t",
                quantity=2.0,
                product_id=1,
                product_code="TURKEY01",
                product_name="Sliced Turkey Breast - Premium",
                uom="PC",
                uom_id=2,
                confidence=100
            ),
            ParsedItem(
                raw_input="1sm",
                quantity=1.0,
                product_id=2,
                product_code="SALAMI01", 
                product_name="Genoa Salami - Imported",
                uom="PC",
                uom_id=2,
                confidence=100
            )
        ]
        
        parsed_order = ParsedOrder(
            customer=customer,
            items=items,
            raw_input="g18\n2t1sm"
        )
        
        # Test lifecycle
        print("📝 1. Saving order as draft...")
        order_id = manager.save_order(parsed_order)
        
        if order_id:
            print("✅ 2. Submitting order...")
            manager.submit_order(order_id, "Customer confirmed via phone")
            
            print("🚚 3. Starting delivery...")
            manager.start_delivery(order_id, notes="Out for delivery at 10 AM")
            
            print("📦 4. Completing delivery...")
            manager.complete_delivery(order_id, notes="Delivered successfully")
            
            print("📚 5. Archiving order...")
            manager.archive_order(order_id, "Order completed successfully")
            
            print(f"\n📊 6. Getting customer history...")
            history = manager.get_customer_order_history(1, limit=5)
            
            for order in history:
                print(f"   Order {order['order_number']}: {order['status']} - ${order['total_amount']:.2f}")
                print(f"   Items: {order['item_count']}, Date: {order['order_date']}")
        
        print(f"\n✅ Order lifecycle test completed!")
        
    finally:
        manager.close_connection()

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description='Order Management System')
    parser.add_argument('--order-id', type=int, help='Order ID to process')
    parser.add_argument('--action', choices=['submit', 'start_delivery', 'complete_delivery', 'archive'], 
                        help='Action to perform on the order')
    parser.add_argument('--customer-id', type=int, help='Customer ID for history lookup')
    parser.add_argument('--test', action='store_true', help='Run test suite')
    
    args = parser.parse_args()
    
    # Default database configuration
    db_config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'orders'
    }
    
    manager = OrderManager(db_config)
    
    try:
        if args.test:
            # Run the full test suite
            test_order_lifecycle()
        
        elif args.order_id and args.action:
            # Execute specific action on order
            order_id = args.order_id
            action = args.action
            
            manager.connect_database()
            
            if action == 'submit':
                success = manager.submit_order(order_id)
                message = "Order submitted for processing"
            elif action == 'start_delivery':
                success = manager.start_delivery(order_id)
                message = "Started delivery"
            elif action == 'complete_delivery':
                success = manager.complete_delivery(order_id)
                message = "Delivery completed"
            elif action == 'archive':
                success = manager.archive_order(order_id)
                message = "Order archived"
            else:
                print(f"❌ Unknown action: {action}")
                exit(1)
            
            if success:
                print(f"✅ {message} for order ID {order_id}")
            else:
                print(f"❌ Failed to {action} order ID {order_id}")
                
        elif args.customer_id:
            # Get customer history
            try:
                manager.connect_database()
                history = manager.get_customer_order_history(args.customer_id)
                print(f"Customer {args.customer_id} Order History:")
                for order in history:
                    print(f"Order {order['order_number']}: {order['status']} - ${order['total_amount']:.2f}, Items: {order.get('item_count', 'N/A')}, Date: {order['order_date']}")
            except Exception as e:
                print(f"Error getting customer history: {e}")
        
        else:
            parser.print_help()
            
    except Exception as e:
        print(f"❌ Error: {str(e)}")
        exit(1)
    
    finally:
        manager.close_connection()