

# Fancy Foods Price Sheet Extraction Rules

1. **Library Requirement**
   - The `openpyxl` library must be installed and used for reading Excel files and detecting cell colors/formatting.
   - Confirmed: `openpyxl` is installed and available in the environment.

2. The beginning of a table is identified by a row that contains a cell with the text "Code" or "Codes" that is underlined.
3. Each table may have two blocks of data side by side; some tables do, some do not.

4. Column headers are usually underlined.
5. Subcategory/Description column logic:
    - For each table, the column labeled "Description" is always present.
    - For each data row:
       - If the cell in the "Description" column is yellow-filled, treat its value as a new **Subcategory**.
       - All following product rows are labeled with the most recent subcategory until another yellow-filled cell appears or the table ends.
       - If the cell is not yellow-filled, treat its value as **Description** and assign the most recent subcategory (if any).
    - This rule applies independently for each table (left and right).

6. After identifying a new table, the row above will have a category. Refer to the category cleaning rules.

# Column Mapping

Code = Item Code
Next column or Description = Description or Subcategory (see rules above for color logic)
Z1 = Price
Vendor = "Fancy Foods" (default)
Category = Row above new table (see category cleaning rule)
Qty = Amount in stock (dash means no stock)




1. **Category Processing/Cleaning**
   - The header row of each table contains the word CODE.
   - The row immediately above the header row contains the CATEGORY for that table.


   - The category is above the header row. and it contains "price sheet".

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



# Rules, Patterns, and Edge Cases Discovered (Dec 7, 2025)

## Header Detection
- The header row may not be present as a single row; must scan for rows containing all expected columns: Category, Subcategory, Item Code, Description, Qty, Price.
- If header is not found, extraction should fail gracefully and log the issue.

## Product Row Identification
- Product rows are defined as those with non-empty Item Code, Description, and Price fields.
- Rows with missing values in these columns are dropped from output.

## UOM Splitting
- Quantity field may contain both quantity and unit of measure (UOM), e.g., "12 CT". Split into separate columns.

## Output Columns
- Always output: Category, Subcategory, Item Code, Description, Qty, Price, UOM.

## DataFrame Processing
- Read the Excel sheet with header=None to allow for flexible header detection.
- Rebuild DataFrame from detected header row onward.

## Edge Cases
- Initial rows in the sheet may be notes, headers, or blank; skip until header is detected.
- If multiple tables/blocks are present, process each separately (future enhancement).

## Script Logging
- Print sample DataFrame rows, detected header, and anomaly summary for debugging and verification.
