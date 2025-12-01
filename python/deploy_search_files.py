"""
Deploy Updated Search Files to Production Server

This script uploads the files we updated for the advanced search functionality.
"""

import ftplib
import json
import os
from pathlib import Path

def deploy_search_files():
    """Deploy the updated search files to production."""
    
    # Load FTP config
    config_file = Path(__file__).parent / '../data/server_config.json'
    with open(config_file, 'r') as f:
        config = json.load(f)
    
    # Files to deploy (updated for advanced search)
    files_to_deploy = [
        {
            'local': str(Path(__file__).parent.parent / 'php' / 'ajax_search_library.js'),
            'remote': 'ajax_search_library.js'
        },
        {
            'local': str(Path(__file__).parent.parent / 'php' / 'admin_api.php'),
            'remote': 'admin_api.php'
        },
        {
            'local': str(Path(__file__).parent.parent / 'php' / 'ajax_search.html'),
            'remote': 'ajax_search.html'
        },
        {
            'local': str(Path(__file__).parent.parent / 'php' / 'admin.html'),
            'remote': 'admin.html'
        }
    ]
    
    print("Connecting to FTP server...")
    
    try:
        # Connect to FTP
        ftp = ftplib.FTP()
        ftp.connect(config['host'], config['port'])
        ftp.login(config['username'], config['password'])
        
        print(f"Connected to {config['host']}")
        print("Current directory:", ftp.pwd())
        
        # List available directories
        print("Available directories:")
        try:
            for item in ftp.nlst():
                print(f"  - {item}")
        except:
            print("  (Could not list directories)")
        
        # Upload each file
        for file_info in files_to_deploy:
            local_path = file_info['local']
            remote_path = file_info['remote']
            
            if not os.path.exists(local_path):
                print(f"❌ Local file not found: {local_path}")
                continue
                
            print(f"📤 Uploading {os.path.basename(local_path)}...")
            
            try:
                with open(local_path, 'rb') as local_file:
                    ftp.storbinary(f'STOR {remote_path}', local_file)
                print(f"✅ Successfully uploaded {remote_path}")
                
            except Exception as e:
                print(f"❌ Error uploading {local_path}: {e}")
        
        print("\n🎉 Deployment complete!")
        print("🔗 Test the updated search at: https://buddyboyprovisions.com/orders/ajax_search.html")
        print("🔗 Test the admin panel at: https://buddyboyprovisions.com/orders/admin.html")
        
        ftp.quit()
        
    except Exception as e:
        print(f"❌ FTP connection error: {e}")
        return False
    
    return True

if __name__ == "__main__":
    print("🚀 Starting deployment of advanced search files...")
    deploy_search_files()