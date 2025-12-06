# Reddy Raw Price Sheet Extraction Rules

1. **Column Mapping**
   - Extract the following fields for each product row:
     - Category (if available)
     - Item Code
     - Description
     - Quantity
     - Price
   - Confirm and document any additional columns as needed.

2. **Whitespace Handling**
   - Trim leading and trailing whitespace from all extracted fields.

3. **Consistent Formatting**
   - Ensure all extracted data is consistently formatted for downstream use (e.g., CSV, database import).

4. **Sheet/Page Structure**
   - If multiple pages or columns exist, process each as a separate block as appropriate.

5. **OCR Artifacts**
   - Clean up any OCR artifacts (e.g., misread characters, line breaks) before finalizing extracted data.

6. **Unknowns**
   - Document any columns or patterns that are unclear for later review.
