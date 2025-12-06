# Sync Instructions

To update your live XAMPP server with the latest PHP scripts:

1. Edit and version-control PHP scripts in `D:/users/miket/order_system/php/`.
2. When ready, copy updated files to `D:/xampp/htdocs/orders/`.

## Example PowerShell Sync Script

```powershell
$source = "D:/users/miket/order_system/php/"
$dest = "D:/xampp/htdocs/orders/"
robocopy $source $dest *.php /XO /S
```

- This copies only newer `.php` files, including subfolders.
- Run from PowerShell or save as `sync_php.ps1`.

## Manual Copy
- You can also drag-and-drop files in Explorer.

---

**Tip:** Always test after syncing to ensure the live server works as expected.
