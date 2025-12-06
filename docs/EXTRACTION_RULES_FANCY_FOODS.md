# Fancy Foods Price Sheet Extraction Rules

1. **Category Cleaning**
   - Remove the keyword "PRICE SHEET" (case-insensitive) from any category name.
   - Example: "FANCY FOODS PRICE SHEET" → "FANCY FOODS"

2. **Column Mapping**
   - Extract the following fields for each product row:
     - Category
     - Subcategory
     - Item Code
     - Description
     - Quantity
     - Price
   - Confirm and document any additional columns as needed.

3. **Whitespace Handling**
   - Trim leading and trailing whitespace from all extracted fields.

4. **Consistent Formatting**
   - Ensure all extracted data is consistently formatted for downstream use (e.g., CSV, database import).

5. **Sheet Structure**
   - If multiple sheets or blocks exist, process each as a separate category or subcategory as appropriate.

6. **Unknowns**
   - Document any columns or patterns that are unclear for later review.
