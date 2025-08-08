#!/bin/bash

# Music Collection Manager - File Permissions Script
# Run this after uploading files to GoDaddy

echo "🎵 Setting file permissions for Music Collection Manager"
echo "======================================================"

# Set directory permissions (755)
echo "Setting directory permissions..."
find . -type d -exec chmod 755 {} \;
echo "✅ Directories set to 755"

# Set PHP file permissions (644)
echo "Setting PHP file permissions..."
find . -name "*.php" -type f -exec chmod 644 {} \;
echo "✅ PHP files set to 644"

# Set CSS and JS file permissions (644)
echo "Setting CSS and JS file permissions..."
find . -name "*.css" -type f -exec chmod 644 {} \;
find . -name "*.js" -type f -exec chmod 644 {} \;
echo "✅ CSS and JS files set to 644"

# Set documentation file permissions (644)
echo "Setting documentation file permissions..."
find . -name "*.md" -type f -exec chmod 644 {} \;
find . -name "*.txt" -type f -exec chmod 644 {} \;
echo "✅ Documentation files set to 644"

# Set executable permissions for scripts (755)
echo "Setting script permissions..."
chmod 755 deploy.sh
chmod 755 set_permissions.sh
echo "✅ Scripts set to 755"

# Verify critical files
echo ""
echo "🔍 Verifying critical files..."
if [ -f "config/database.php" ]; then
    echo "✅ config/database.php exists"
else
    echo "❌ config/database.php missing"
fi

if [ -f "index.php" ]; then
    echo "✅ index.php exists"
else
    echo "❌ index.php missing"
fi

if [ -f "setup/install.php" ]; then
    echo "✅ setup/install.php exists"
else
    echo "❌ setup/install.php missing"
fi

echo ""
echo "📋 Permission Summary:"
echo "   Directories: 755 (rwxr-xr-x)"
echo "   PHP Files:   644 (rw-r--r--)"
echo "   CSS/JS:      644 (rw-r--r--)"
echo "   Scripts:     755 (rwxr-xr-x)"
echo ""
echo "✅ All permissions set correctly!"
echo ""
echo "💡 Next steps:"
echo "1. Configure database settings in config/database.php"
echo "2. Run setup/install.php to create the database table"
echo "3. Access your application at yourdomain.com/music_collection/" 