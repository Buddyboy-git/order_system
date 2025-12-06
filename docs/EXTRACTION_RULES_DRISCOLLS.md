# Driscoll's Data Fetch & Import Rules

1. **Fetch Process**
   - Data is fetched using a crawler and saved directly to a CSV file for import.
   - The crawler handles pagination, authentication, and data retrieval as needed.

2. **Column Mapping**
   - Ensure the following fields are present in the CSV:
     - Item Code
     - Description
     - Quantity
     - Price
     - Category (if available)
     - Vendor
   - Confirm and document any additional columns as needed.

3. **Whitespace Handling**
   - Trim leading and trailing whitespace from all fields before import.

4. **Consistent Formatting**
   - Ensure CSV data is consistently formatted for downstream use (e.g., database import).

5. **Unknowns**
   - Document any columns or patterns that are unclear for later review.
