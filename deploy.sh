#!/bin/bash

# Music Collection Manager Deployment Script
# This script helps deploy the application to GoDaddy hosting

echo "🎵 Music Collection Manager - Deployment Script"
echo "=============================================="

# Check if required files exist
echo "Checking required files..."

if [ ! -f "config/database.php" ]; then
    echo "❌ Error: config/database.php not found"
    echo "Please configure your database settings first"
    exit 1
fi

if [ ! -f "config/api_config.php" ]; then
    echo "❌ Error: config/api_config.php not found"
    echo "Please configure your API settings first"
    exit 1
fi

echo "✅ All required files found"

# Create deployment package
echo "Creating deployment package..."

# Create a temporary directory for deployment
TEMP_DIR="music_collection_deploy_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$TEMP_DIR"

# Copy application files
cp -r config/ "$TEMP_DIR/"
cp -r models/ "$TEMP_DIR/"
cp -r api/ "$TEMP_DIR/"
cp -r assets/ "$TEMP_DIR/"
cp -r services/ "$TEMP_DIR/"
cp -r setup/ "$TEMP_DIR/"
cp index.php "$TEMP_DIR/"
cp README.md "$TEMP_DIR/"

# Create deployment instructions
cat > "$TEMP_DIR/DEPLOYMENT_INSTRUCTIONS.txt" << 'EOF'
MUSIC COLLECTION MANAGER - DEPLOYMENT INSTRUCTIONS
==================================================

1. UPLOAD FILES TO GODADDY:
   - Upload all files in this directory to your GoDaddy hosting
   - Recommended path: public_html/music_collection/

2. CONFIGURE DATABASE:
   - Edit config/database.php with your GoDaddy MySQL credentials
   - Update DB_HOST, DB_NAME, DB_USER, and DB_PASS

3. CONFIGURE API (OPTIONAL):
   - Edit config/api_config.php to add your API keys
   - Get Discogs API key from: https://www.discogs.com/settings/developers
   - Or get Last.fm API key from: https://www.last.fm/api/account/create

4. INSTALL DATABASE:
   - Run the installation script via browser:
     yourdomain.com/music_collection/setup/install.php
   - Or via command line: php setup/install.php

5. ACCESS APPLICATION:
   - Navigate to: yourdomain.com/music_collection/
   - Start adding your music collection!

TROUBLESHOOTING:
- If you get database connection errors, verify your credentials
- If autocomplete doesn't work, check your API keys
- If files don't load, check file permissions (should be 644 for files, 755 for directories)

SUPPORT:
- Check the README.md file for detailed documentation
- Review browser console for JavaScript errors
- Check server error logs for PHP issues
EOF

# Create a simple installation checker
cat > "$TEMP_DIR/check_installation.php" << 'EOF'
<?php
/**
 * Installation Checker
 * Run this to verify your setup
 */

echo "<h1>Music Collection Manager - Installation Check</h1>";

// Check PHP version
echo "<h2>PHP Version</h2>";
echo "Current PHP version: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "✅ PHP version is compatible<br>";
} else {
    echo "❌ PHP version should be 7.4 or higher<br>";
}

// Check required extensions
echo "<h2>Required Extensions</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext extension is loaded<br>";
    } else {
        echo "❌ $ext extension is missing<br>";
    }
}

// Check database configuration
echo "<h2>Database Configuration</h2>";
if (file_exists('config/database.php')) {
    echo "✅ Database config file exists<br>";
    
    // Test database connection
    try {
        require_once 'config/database.php';
        $pdo = getDBConnection();
        echo "✅ Database connection successful<br>";
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'music_collection'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Database table exists<br>";
        } else {
            echo "❌ Database table not found - run setup/install.php<br>";
        }
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Database config file not found<br>";
}

// Check API configuration
echo "<h2>API Configuration</h2>";
if (file_exists('config/api_config.php')) {
    echo "✅ API config file exists<br>";
    require_once 'config/api_config.php';
    
    if (isExternalAPIAvailable()) {
        echo "✅ External API is configured<br>";
    } else {
        echo "⚠️ External API not configured (autocomplete will use local database only)<br>";
    }
} else {
    echo "❌ API config file not found<br>";
}

// Check file permissions
echo "<h2>File Permissions</h2>";
$directories = ['config', 'models', 'api', 'assets', 'services'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "✅ $dir directory exists<br>";
    } else {
        echo "❌ $dir directory missing<br>";
    }
}

echo "<h2>Installation Complete!</h2>";
echo "If all checks pass, your application should be ready to use.<br>";
echo "<a href='../index.php'>Go to Music Collection Manager</a>";
?>
EOF

# Create zip file
echo "Creating deployment package..."
zip -r "$TEMP_DIR.zip" "$TEMP_DIR/"

echo "✅ Deployment package created: $TEMP_DIR.zip"
echo ""
echo "📋 NEXT STEPS:"
echo "1. Upload $TEMP_DIR.zip to your GoDaddy hosting"
echo "2. Extract the files in your web directory"
echo "3. Configure database settings in config/database.php"
echo "4. Run setup/install.php to create the database table"
echo "5. Access your application at yourdomain.com/music_collection/"
echo ""
echo "📖 See DEPLOYMENT_INSTRUCTIONS.txt for detailed steps"
echo "🔧 Run check_installation.php to verify your setup"

# Clean up temporary directory
rm -rf "$TEMP_DIR"

echo ""
echo "🎵 Your Music Collection Manager is ready for deployment!" 