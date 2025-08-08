<?php
/**
 * Detailed PHP Extension Test
 * This script will provide detailed information about available extensions
 */

echo "<h1>Detailed PHP Extension Test</h1>";
echo "<p>Testing database extensions and PHP configuration...</p>";

echo "<h2>PHP Version and Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PHP SAPI: " . php_sapi_name() . "<br>";
echo "Loaded Extensions: " . implode(', ', get_loaded_extensions()) . "<br>";

echo "<h2>Detailed Extension Tests</h2>";

// Test PDO
echo "<h3>PDO Extension Test</h3>";
if (class_exists('PDO')) {
    echo "✅ PDO class exists<br>";
    echo "PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br>";
    
    // Test PDO MySQL driver
    if (in_array('mysql', PDO::getAvailableDrivers())) {
        echo "✅ PDO MySQL driver is available<br>";
    } else {
        echo "❌ PDO MySQL driver is NOT available<br>";
    }
} else {
    echo "❌ PDO class does not exist<br>";
}

// Test MySQLi
echo "<h3>MySQLi Extension Test</h3>";
if (function_exists('mysqli_connect')) {
    echo "✅ mysqli_connect function exists<br>";
} else {
    echo "❌ mysqli_connect function does not exist<br>";
}

if (class_exists('mysqli')) {
    echo "✅ mysqli class exists<br>";
} else {
    echo "❌ mysqli class does not exist<br>";
}

// Test MySQL (deprecated)
echo "<h3>MySQL Extension Test (Deprecated)</h3>";
if (function_exists('mysql_connect')) {
    echo "✅ mysql_connect function exists<br>";
} else {
    echo "❌ mysql_connect function does not exist<br>";
}

echo "<h2>Extension Loading Test</h2>";

// Check if extensions are loaded
$extensions_to_check = ['pdo', 'pdo_mysql', 'mysqli', 'mysql'];
foreach ($extensions_to_check as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext extension is loaded<br>";
    } else {
        echo "❌ $ext extension is NOT loaded<br>";
    }
}

echo "<h2>PHP Configuration</h2>";
echo "disable_functions: " . ini_get('disable_functions') . "<br>";
echo "extension_dir: " . ini_get('extension_dir') . "<br>";

echo "<h2>Alternative Connection Test</h2>";

// Try to create a simple connection test
try {
    if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
        echo "Attempting PDO connection test...<br>";
        // We'll use dummy credentials just to test if PDO works
        $pdo = new PDO('mysql:host=localhost', 'test', 'test');
        echo "✅ PDO connection object created successfully<br>";
    } elseif (class_exists('mysqli')) {
        echo "Attempting MySQLi connection test...<br>";
        $mysqli = new mysqli('localhost', 'test', 'test');
        echo "✅ MySQLi connection object created successfully<br>";
    } else {
        echo "❌ No database extensions available for connection testing<br>";
    }
} catch (Exception $e) {
    echo "⚠️ Connection test failed (expected with dummy credentials): " . $e->getMessage() . "<br>";
    echo "This is normal with dummy credentials - the important thing is that the extension loaded.<br>";
}

echo "<h2>Recommendations</h2>";

if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers())) {
    echo "<p style='color: green;'>✅ PDO with MySQL driver is available - this should work!</p>";
} elseif (class_exists('mysqli')) {
    echo "<p style='color: orange;'>⚠️ MySQLi is available - this should work!</p>";
} else {
    echo "<p style='color: red;'>❌ No database extensions are available. Contact your hosting provider.</p>";
}

echo "<h2>Next Steps</h2>";
echo "<p>If the extensions show as available above but your application still doesn't work:</p>";
echo "<ul>";
echo "<li>Check your database credentials in config/database.php</li>";
echo "<li>Ensure your database exists and is accessible</li>";
echo "<li>Try accessing your application directly: <a href='index.php'>index.php</a></li>";
echo "<li>Check the error logs for more specific error messages</li>";
echo "</ul>";

echo "<p><a href='index.php'>Go to Music Collection Manager</a></p>";
?> 