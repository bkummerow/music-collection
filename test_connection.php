<?php
/**
 * Database Connection Test
 * Run this to check what database extensions are available
 */

echo "<h1>Database Connection Test</h1>";
echo "<p>This script will test what database extensions are available on your server.</p>";

echo "<h2>PHP Version</h2>";
echo "Current PHP version: " . phpversion() . "<br>";

echo "<h2>Available Database Extensions</h2>";

// Check PDO
if (class_exists('PDO')) {
    echo "✅ PDO is available<br>";
    $pdo_drivers = PDO::getAvailableDrivers();
    echo "Available PDO drivers: " . implode(', ', $pdo_drivers) . "<br>";
} else {
    echo "❌ PDO is not available<br>";
}

// Check MySQLi
if (function_exists('mysqli_connect')) {
    echo "✅ MySQLi is available<br>";
} else {
    echo "❌ MySQLi is not available<br>";
}

// Check MySQL (deprecated)
if (function_exists('mysql_connect')) {
    echo "✅ MySQL (deprecated) is available<br>";
} else {
    echo "❌ MySQL (deprecated) is not available<br>";
}

echo "<h2>Testing Database Connection</h2>";

// Test with our database configuration
if (file_exists('config/database.php')) {
    echo "✅ Database config file exists<br>";
    
    try {
        require_once 'config/database.php';
        
        echo "<p>Attempting to connect to database...</p>";
        
        $connection = getDBConnection();
        $connectionType = getConnectionType();
        
        echo "<p style='color: green;'>✅ Database connection successful using $connectionType!</p>";
        
        // Test a simple query
        $testSql = "SELECT 1 as test";
        switch ($connectionType) {
            case 'PDO':
                $result = $connection->query($testSql);
                $row = $result->fetch();
                break;
            case 'MySQLi':
                $result = $connection->query($testSql);
                $row = $result->fetch_assoc();
                break;
            case 'MySQL':
                $result = mysql_query($testSql, $connection);
                $row = mysql_fetch_assoc($result);
                break;
        }
        
        if ($row && isset($row['test'])) {
            echo "<p style='color: green;'>✅ Database query test successful!</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Database query test failed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
        echo "<p><strong>Possible solutions:</strong></p>";
        echo "<ul>";
        echo "<li>Check your database credentials in config/database.php</li>";
        echo "<li>Ensure your database exists</li>";
        echo "<li>Verify your hosting provider supports MySQL</li>";
        echo "<li>Contact your hosting provider to enable PDO or MySQLi</li>";
        echo "</ul>";
    }
} else {
    echo "❌ Database config file not found<br>";
    echo "<p>Please create config/database.php with your database credentials.</p>";
}

echo "<h2>Recommendations</h2>";

if (class_exists('PDO')) {
    echo "<p style='color: green;'>✅ Your server supports PDO - this is the best option!</p>";
} elseif (function_exists('mysqli_connect')) {
    echo "<p style='color: orange;'>⚠️ Your server supports MySQLi - this will work but PDO is preferred.</p>";
} elseif (function_exists('mysql_connect')) {
    echo "<p style='color: red;'>❌ Your server only supports the deprecated MySQL extension. Consider upgrading your hosting plan.</p>";
} else {
    echo "<p style='color: red;'>❌ No database extensions available. Contact your hosting provider.</p>";
}

echo "<p><a href='index.php'>Go to Music Collection Manager</a></p>";
?> 