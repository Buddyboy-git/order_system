# extract_reddyraw_pdf_page2.py
"""
Extracts both left and right columns from the second page of the Reddy Raw PDF pricebook.
Outputs a single CSV with all products from both columns, one per row, with fields:
item number, brand, description, pack/size, price, UOM.
"""
import os
import csv
import re
from pdf2image import convert_from_path
from PIL import Image
import pytesseract

def extract_column(image, crop_box, debug_prefix=None):
    column_img = image.crop(crop_box)
    if debug_prefix:
        column_img.save(f"{debug_prefix}_preprocessed.png")
    ocr_text = pytesseract.image_to_string(column_img, config="--psm 6")
    if debug_prefix:
        with open(f"{debug_prefix}_ocr.txt", "w", encoding="utf-8") as f:
            f.write(ocr_text)
    return ocr_text

def parse_products(ocr_text):
    # Regex: item number at start of line (6+ digits, tolerate OCR quirks)
    product_re = re.compile(r"^(0[cC0Oo]{0,2}\d{4,}|\d{6,})\b", re.MULTILINE)
    lines = ocr_text.splitlines()
    products = []
    current = []
    for line in lines:
        if product_re.match(line.strip()):
            if current:
                products.append(current)
            current = [line.strip()]
        elif line.strip():
            current.append(line.strip())
    if current:
        products.append(current)
    parsed = []
    for group in products:
        row = parse_product_group(group)
        if row:
            parsed.append(row)
    return parsed

def parse_product_group(group):
    # Join lines, split tokens
    text = " ".join(group)
    tokens = text.split()
    if len(tokens) < 6:
        return None
    # Filter out known non-product patterns
    # 1. Timestamp/header lines (e.g., 11/13/25,9:20 AM,...)
    if re.match(r"\d{1,2}/\d{1,2}/\d{2,4}", tokens[0]) and re.match(r"\d{1,2}:\d{2}", tokens[1]):
        return None
    # 2. Page marker lines (e.g., 00000K,UP FROM,...)
    if tokens[0].upper().startswith("00000") or (tokens[0].upper() == "UP" and tokens[1].upper() == "FROM"):
        return None
    # Item number: first token, zero-pad if needed
    item_number = tokens[0].replace('c', '0').replace('C', '0').replace('O', '0').replace('o', '0')
    item_number = item_number.zfill(6)
    # Brand: accumulate tokens after item number until a token looks like description/pack/size/price/UOM, but total chars (including spaces) <= 10
    brand_tokens = []
    char_count = 0
    for t in tokens[1:-3]:
        # Stop if token looks like start of description (contains digit, slash, or is long)
        if any(c.isdigit() for c in t) or '/' in t or len(t) > 10:
            break
        # Add 1 for space if not the first token
        add_len = len(t) + (1 if brand_tokens else 0)
        if char_count + add_len > 10:
            break
        brand_tokens.append(t)
        char_count += add_len
    brand = " ".join(brand_tokens)
    desc_start = 1 + len(brand_tokens)
    # Description: tokens[desc_start] up to the last 3 tokens
    description = " ".join(tokens[desc_start:-3])
    # Pack/size, price, UOM: last 3 tokens
    pack_size = tokens[-3]
    price = tokens[-2]
    uom = tokens[-1]
    # Filter out header/section rows by checking for known header words in brand/desc/pack/price/uom
    header_words = {"ITEM", "DESCRIPTION", "PRICE", "UP", "FROM", "PG:", "DATE", "LISTING", "BY", "CLASS", "FOR"}
    if any(x.upper() in header_words for x in [brand, description, pack_size, price, uom]):
        return None
    # Fix negative price values (likely missing decimal)
    if price.startswith("-") and price[1:].isdigit():
        # Try to infer decimal: e.g., -81 -> 8.10 or 0.81 (choose 0.81 as safer default)
        price = f"0.{price[1:]}"
    return [item_number, brand, description, pack_size, price, uom]

def main():
    pdf_path = "d:/Users/miket/Python_Projects/Vendor_Price_Import/excel_price_sheets/reddyraw/pricebook_001.pdf"
    output_csv = "excel_price_sheets/reddyraw/pricebook_001_extracted_allpages.csv"
    if not os.path.exists(pdf_path):
        print(f"PDF not found: {pdf_path}")
        return
    # Convert all pages
    images = convert_from_path(pdf_path)
    if not images:
        print("Could not load PDF.")
        return
    all_products = []
    per_page_counts = []
    for page_num, page in enumerate(images, 1):
        width, height = page.size
        center_x = width // 2
        left_crop = (0, 0, center_x - 100, height)
        right_crop = (center_x - 100, 0, width, height)
        # Extract left column
        left_ocr = extract_column(page, left_crop, debug_prefix=f"excel_price_sheets/reddyraw/page{page_num}_left_column")
        left_products = parse_products(left_ocr)
        # Extract right column
        right_ocr = extract_column(page, right_crop, debug_prefix=f"excel_price_sheets/reddyraw/page{page_num}_right_column")
        right_products = parse_products(right_ocr)
        page_products = left_products + right_products
        all_products.extend(page_products)
        per_page_counts.append(len(page_products))
    with open(output_csv, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["Item Number", "Brand", "Description", "Pack/Size", "Price", "UOM"])
        writer.writerows(all_products)
    print(f"Extracted {len(all_products)} products to {output_csv}")
    expected_total = 27 + 19*52
    print("\n--- Per-page product counts ---")
    for i, count in enumerate(per_page_counts, 1):
        flag = "" 
        if count > 52:
            flag = " <-- MORE than 52!"
        elif count < 52 and i != len(per_page_counts):
            flag = " <-- LESS than 52!"
        elif i == len(per_page_counts) and count != 27:
            flag = f" <-- Last page, expected 27"
        print(f"Page {i}: {count} products{flag}")
    print(f"\nTotal products: {len(all_products)}")
    print(f"Expected total: {expected_total}")
    print(f"Difference: {len(all_products) - expected_total}")

if __name__ == "__main__":
    main()
