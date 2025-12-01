# Deployment Guide: Local ↔ Production

## Environment Auto-Detection

The system now automatically detects whether it's running in local or production environment and uses the appropriate database configuration.

### Environment Detection Logic

1. **Production Detection**:
   - Domain contains `buddyboyprovisionsllc.com`
   - Production database `buddyboy_orders_db` is accessible
   - Uses production credentials

2. **Local Detection**:
   - Domain is `localhost`, `127.0.0.1`, or contains `.local`
   - Fallback when production DB is not accessible
   - Uses local XAMPP credentials

## Files Updated for Environment Compatibility

### Core Files
- ✅ `admin_api.php` - Main API backend
- ✅ `get_product_count.php` - Product count API  
- ✅ `ajax_search.php` - Legacy search API
- ✅ `environment_config.php` - Environment detection system

### Configuration Files
- `db_config.php` - Local configuration (XAMPP)
- `db_config_production.php` - Production configuration
- `environment_config.php` - Unified environment detection

## Deployment Steps

### To Production Server

1. **Upload Files**:
   ```bash
   # Copy all files to production server
   rsync -avz /local/path/ production_server:/path/to/web/root/
   ```

2. **Database Schema**:
   - Ensure `vendor_products` table exists in production
   - Run migration if needed: `migrate_to_production_schema.sql`

3. **File Permissions**:
   ```bash
   chmod 644 *.php
   chmod 644 *.html
   chmod 644 *.css
   chmod 644 *.js
   ```

4. **Test Environment Detection**:
   ```bash
   curl "https://yourdomain.com/orders/environment_config.php?debug_env=1"
   # Should show: Environment: production
   ```

### From Production to Local

1. **Pull Files**:
   ```bash
   # Copy files from production
   rsync -avz production_server:/path/to/web/root/ /local/path/
   ```

2. **Database Sync** (if needed):
   ```bash
   # Export from production
   mysqldump -u buddyboy_orders -p buddyboy_orders_db vendor_products > production_products.sql
   
   # Import to local
   mysql -u root orders < production_products.sql
   ```

## Testing Both Environments

### Local Testing
```bash
# Test environment detection
curl "http://localhost/orders/environment_config.php?debug_env=1"

# Test APIs
curl "http://localhost/orders/admin_api.php?action=get_product_count"
curl "http://localhost/orders/admin_api.php?action=get_vendors"
curl "http://localhost/orders/admin_api.php?action=search_products&q=PIE"

# Test search interface
open http://localhost/orders/ajax_search.html
```

### Production Testing
```bash
# Test environment detection
curl "https://yourdomain.com/orders/environment_config.php?debug_env=1"

# Test APIs
curl "https://yourdomain.com/orders/admin_api.php?action=get_product_count"
curl "https://yourdomain.com/orders/admin_api.php?action=get_vendors"
curl "https://yourdomain.com/orders/admin_api.php?action=search_products&q=PIE"

# Test search interface
open https://yourdomain.com/orders/ajax_search.html
```

## Environment-Specific Settings

### Local Environment
```php
// Automatic settings for local development
$host = 'localhost';
$dbname = 'orders';
$username = 'root';
$password = '';
$debug = true;
$error_reporting = true;
```

### Production Environment  
```php
// Automatic settings for production
$host = 'localhost';
$dbname = 'buddyboy_orders_db';
$username = 'buddyboy_orders';  
$password = 'Trotta123$';
$debug = false;
$error_reporting = false;
```

## Troubleshooting

### Environment Detection Issues
- Check `environment_config.php?debug_env=1` to see detected environment
- Verify database connectivity for production detection
- Check domain name patterns

### Database Connection Issues
- Verify credentials in each environment
- Check if `vendor_products` table exists
- Ensure proper permissions

### API Issues
- Check HTTP response codes
- Enable debug mode locally to see errors
- Verify all files have proper environment includes

## Maintenance

### Adding New PHP Files
When creating new PHP files that need database access:

```php
<?php
require_once 'environment_config.php';

try {
    $pdo = createPDOConnection();
    // Your code here
} catch (PDOException $e) {
    // Error handling
}
```

### Schema Updates
When updating database schema:
1. Update both local and production databases
2. Test environment detection still works
3. Verify all APIs function correctly

## Current Status

✅ **Environment Detection**: Working automatically  
✅ **Local Development**: All APIs functional  
✅ **Production Ready**: Configuration auto-switches  
✅ **Database Schema**: Unified `vendor_products` table  
✅ **Search Interface**: Works in both environments  
✅ **Product Count**: Dynamic loading in both environments  

The system is now production-ready with automatic environment detection!