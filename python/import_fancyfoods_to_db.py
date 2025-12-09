import csv
import mysql.connector
from mysql.connector import Error

# CONFIGURE THESE
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Set your MySQL root password
    'database': 'orders',
}
CSV_PATH = r'd:/xampp/htdocs/order_system/data/excel_price_sheets/fancyfoods/fancyfoods_extracted.csv'
VENDOR = 'Fancy Foods'

# Helper: get uom_id, insert if not exists
def get_uom_id(cursor, uom_code):
    cursor.execute("SELECT id FROM uom WHERE code = %s", (uom_code,))
    result = cursor.fetchone()
    if result:
        return result[0]
    cursor.execute("INSERT INTO uom (code, name, description) VALUES (%s, %s, %s)", (uom_code, uom_code, uom_code))
    return cursor.lastrowid

# Helper: upsert product
def upsert_product(cursor, row, uom_id):
    cursor.execute("""
        INSERT INTO products (item_code, description, price, vendor, category, uom_id, is_active)
        VALUES (%s, %s, %s, %s, %s, %s, TRUE)
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            price = VALUES(price),
            category = VALUES(category),
            uom_id = VALUES(uom_id),
            is_active = TRUE
    """, (
        row['Item Code'],
        row['Description'],
        row['Price'] if row['Price'] else 0.00,
        VENDOR,
        row['Category'],
        uom_id
    ))

def main():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        with open(CSV_PATH, newline='', encoding='utf-8') as csvfile:
            reader = csv.DictReader(csvfile)
            for row in reader:
                uom_code = row['UOM'].strip().upper() if row['UOM'] else 'LB'
                uom_id = get_uom_id(cursor, uom_code)
                upsert_product(cursor, row, uom_id)
        conn.commit()
        print('Import complete.')
    except Error as e:
        print(f'Error: {e}')
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == '__main__':
    main()
