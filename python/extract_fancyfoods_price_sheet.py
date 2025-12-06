"""
Fancy Foods Price Sheet Extraction Script

This script extracts product data from the Fancy Foods price sheet Excel file.

Category Cleaning Rule:
- If the category name contains 'PRICE SHEET' (case-insensitive), remove it.
- Example: 'FANCY FOODS PRICE SHEET' -> 'FANCY FOODS'

Extraction Steps:
1. Read the Excel file.
2. For each product row, extract relevant fields (category, subcategory, item code, description, qty, price, etc.).
3. Clean the category name using the rule above.
4. Save or output the cleaned data.

TODO: Implement extraction logic after confirming column mappings and sheet structure.
"""

import re

def clean_category_name(category: str) -> str:
    """Remove 'PRICE SHEET' from category name (case-insensitive, trims whitespace)."""
    cleaned = re.sub(r'\s*PRICE SHEET\s*', '', category, flags=re.IGNORECASE)
    return cleaned.strip()

# Example usage:
if __name__ == "__main__":
    examples = [
        "FANCY FOODS PRICE SHEET",
        "Fancy Foods Price Sheet",
        "Fancy Foods",
        "PRICE SHEET FANCY FOODS",
        "Fancy Foods  Price Sheet  ",
        "Fancy Foods"
    ]
    for cat in examples:
        print(f"Original: '{cat}' -> Cleaned: '{clean_category_name(cat)}'")
