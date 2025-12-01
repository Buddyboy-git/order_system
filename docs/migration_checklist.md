# order_system Project Migration Checklist

This checklist will help you migrate all relevant scripts, data, and documentation from your old scattered locations into the new, organized project structure at `D:/users/miket/order_system`.

## 1. Inventory Existing Files
- [ ] List all Python scripts (e.g., from `D:/Users/miket/Python_Projects/Vendor_Price_Import`)
- [ ] List all PHP scripts (e.g., from `D:/xampp/htdocs/orders`)
- [ ] List all data files (CSVs, Excel, etc.)
- [ ] List all SQL files
- [ ] List all documentation (README, notes, etc.)

## 2. Move/Copy Files
- [ ] Move Python scripts to `/python/`
- [ ] Move PHP scripts to `/php/`
- [ ] Move data files to `/data/`
- [ ] Move SQL files to `/sql/`
- [ ] Move documentation to `/docs/`

## 3. Update Paths and Imports
- [ ] Update any hardcoded file paths in scripts to use the new structure
- [ ] Update documentation to reference new locations

## 4. Test Everything
- [ ] Run Python scripts from the new location
- [ ] Test PHP scripts in the new `/php/` folder (sync to XAMPP as needed)
- [ ] Verify data and SQL files are accessible

## 5. Version Control
- [ ] Initialize a Git repository (if not already done)
- [ ] Add and commit all files
- [ ] Push to remote (GitHub, etc.) if desired

## 6. Clean Up Old Locations
- [ ] Remove or archive old files/folders to avoid confusion

---

**Tip:** Use the provided `docs/sync_instructions.md` for syncing PHP files to your XAMPP web root.

**Need help?** Ask for migration scripts or automation tips!
