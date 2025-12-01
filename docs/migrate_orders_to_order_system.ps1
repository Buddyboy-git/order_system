# migrate_orders_to_order_system.ps1
# PowerShell script to COPY all files from D:\xampp\htdocs\orders to D:\users\miket\order_system, organized by type
# Safe: does not delete or overwrite source files

$source = "D:\xampp\htdocs\orders"
$dest = "D:\users\miket\order_system"

# Helper: Copy files by extension to target subfolder, preserving subfolders
function Copy-FilesByType {
    param(
        [string]$exts, [string]$target
    )
    $extArr = $exts -split ','
    foreach ($ext in $extArr) {
        Get-ChildItem -Path $source -Recurse -Include "*.$ext" | ForEach-Object {
            $rel = $_.FullName.Substring($source.Length).TrimStart('\')
            $out = Join-Path $target $rel
            $outDir = Split-Path $out -Parent
            if (!(Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
            Copy-Item $_.FullName $out -Force
        }
    }
}

# Data: CSV, TXT, PNG, etc.
Copy-FilesByType "csv,txt,png,jpg,jpeg,xlsx,xls" "$dest\data"

# Python scripts
Copy-FilesByType "py" "$dest\python"

# PHP scripts
Copy-FilesByType "php" "$dest\php"

# HTML
Copy-FilesByType "html" "$dest\php"

# JS
Copy-FilesByType "js" "$dest\php"

# SQL
Copy-FilesByType "sql" "$dest\sql"

# Docs: MD, guides, etc.
Copy-FilesByType "md" "$dest\docs"

# Exclude __pycache__ and .pyc files
# (No action needed, not included above)

Write-Host "Copy complete! All files organized in $dest. Review and test before deleting originals."
