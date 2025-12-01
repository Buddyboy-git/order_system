# Migration Script: Sync PHP Source to XAMPP Web Root

This PowerShell script will sync your organized PHP source files from your central project folder to your XAMPP web root, ensuring your live server always has the latest code.

## Usage
- Edit the `$source` and `$destination` variables if your paths differ.
- Run this script in PowerShell as Administrator for best results.

---

```
# sync_php_to_xampp.ps1
$source = "D:\users\miket\order_system\php"
$destination = "D:\xampp\htdocs\orders"

# Use robocopy for robust syncing
robocopy $source $destination /MIR /XD ".git" ".vscode" /XF ".env" "*.bak" "*.tmp"

Write-Host "Sync complete! PHP source is now live in XAMPP web root."
```

---

- `/MIR` mirrors the source to the destination (be careful: it deletes files in the destination not present in the source!)
- `/XD` excludes directories (like .git, .vscode)
- `/XF` excludes files (like .env, backups, temp files)

**Always review the script and test with non-critical files first!**
