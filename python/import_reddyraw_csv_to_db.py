import csv
import pymysql

# CONFIGURE THESE
CSV_PATH = 'data/excel_price_sheets/reddyraw/pricebook_001_extracted_allpages.csv'
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'orders'
TABLE = 'products'



# UOM code to uom_id mapping (adjust as needed to match your uom table)

# UOM code to uom_id mapping (must match the uom table in DB)
UOM_MAP = {
    'ea': 1,   # Each
    'pc': 2,   # Piece
    'lb': 3,   # Pound
    'cs': 4,   # Case
    'dz': 5,   # Dozen
    'un': 8,   # Unit
    'qt': 9,   # Quart
    'lbw': 10, # LBw
    'bx': 11,  # Box
    'bxw': 12, # BXw
    'tr': 13,  # Tray
    'tb': 14,  # Tub
    'bg': 15,  # Bag
    'csw': 16, # CSw
    'set': 17, # Set
    'rol': 18, # Roll
    'mil': 19, # Mil
    'gal': 20, # Gallon
    'tub': 22, # Tub
    'box': 23, # Box
}

def get_uom_id(uom_str):
    return UOM_MAP.get(uom_str.strip().lower(), 3)  # Default to 'cs' if unknown

# Connect to DB
conn = pymysql.connect(
    host=DB_HOST,
    user=DB_USER,
    password=DB_PASS,
    database=DB_NAME,
    charset='utf8mb4'
)
cursor = conn.cursor()


def get_uom_id(uom_str):
    # Map UOM string to uom_id using UOM_MAP above
    return UOM_MAP.get(uom_str.strip().lower(), 1)  # Default to 'ea' if unknown


# Make sure your products table has columns for brand and pack_size
with open(CSV_PATH, newline='', encoding='utf-8') as csvfile:
    reader = csv.DictReader(csvfile)
    count = 0
    for row in reader:
        item_code = row['Item Number']
        brand = row['Brand']
        description = row['Description']
        pack_size = row['Pack/Size']
        price = row['Price']
        uom_id = get_uom_id(row['UOM'])
        vendor = 'ReddyRaw'
        sql = f"""
            INSERT INTO {TABLE} (item_code, brand, description, pack_size, price, vendor, uom_id)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                brand=VALUES(brand),
                description=VALUES(description),
                pack_size=VALUES(pack_size),
                price=VALUES(price),
                vendor=VALUES(vendor),
                uom_id=VALUES(uom_id)
        """
        values = [item_code, brand, description, pack_size, price, vendor, uom_id]
        cursor.execute(sql, values)
        count += 1
    conn.commit()
    print(f"Imported {count} products into {TABLE}.")

cursor.close()
conn.close()
