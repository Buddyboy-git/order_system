-- Cleanup script: Remove duplicates from products, keeping only the most recent per (item_code, vendor)
DELETE p1 FROM products p1
INNER JOIN products p2
  ON p1.item_code = p2.item_code AND IFNULL(p1.vendor, '') = IFNULL(p2.vendor, '')
  AND p1.id < p2.id;

-- After running this, only the latest row per (item_code, vendor) will remain.