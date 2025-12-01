import csv
import pymysql

# CONFIGURE THESE
CSV_PATH = 'excel_price_sheets/reddyraw/pricebook_001_extracted_allpages.csv'
DB_HOST = 'localhost'
DB_USER = 'your_db_user'
DB_PASS = 'your_db_password'
DB_NAME = 'your_db_name'
TABLE = 'product'

# Map CSV columns to DB columns
CSV_COLUMNS = [
    'Item Number', 'Brand', 'Description', 'Pack/Size', 'Price', 'UOM'
]
DB_COLUMNS = [
    'item_number', 'brand', 'description', 'pack_size', 'price', 'uom'
]

# Connect to DB
conn = pymysql.connect(
    host=DB_HOST,
    user=DB_USER,
    password=DB_PASS,
    database=DB_NAME,
    charset='utf8mb4'
)
cursor = conn.cursor()

with open(CSV_PATH, newline='', encoding='utf-8') as csvfile:
    reader = csv.DictReader(csvfile)
    count = 0
    for row in reader:
        values = [row[c] for c in CSV_COLUMNS]
        # Prepare SQL (adjust for your schema)
        sql = f"""
            INSERT INTO {TABLE} ({', '.join(DB_COLUMNS)})
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                brand=VALUES(brand),
                description=VALUES(description),
                pack_size=VALUES(pack_size),
                price=VALUES(price),
                uom=VALUES(uom)
        """
        cursor.execute(sql, values)
        count += 1
    conn.commit()
    print(f"Imported {count} products into {TABLE}.")

cursor.close()
conn.close()
