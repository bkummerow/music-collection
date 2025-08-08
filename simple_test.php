<?php
/**
 * Simple Test - Check if basic functionality works
 */

echo "<h1>Simple Test</h1>";

// Test 1: Check if files exist
echo "<h2>File Check</h2>";
$files = [
    'config/database.php',
    'models/MusicCollection.php',
    'api/music_api.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

// Test 2: Check if we can include the database config
echo "<h2>Database Config Test</h2>";
try {
    require_once 'config/database.php';
    echo "✅ Database config loaded successfully<br>";
    echo "Connection type: " . getConnectionType() . "<br>";
} catch (Exception $e) {
    echo "❌ Database config error: " . $e->getMessage() . "<br>";
}

// Test 3: Check if we can create a connection
echo "<h2>Database Connection Test</h2>";
try {
    $connection = getDBConnection();
    echo "✅ Database connection created successfully<br>";
} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "<br>";
}

// Test 4: Check if data file exists
echo "<h2>Data File Test</h2>";
$dataFile = 'data/music_collection.json';
if (file_exists($dataFile)) {
    echo "✅ Data file exists<br>";
    $data = json_decode(file_get_contents($dataFile), true);
    if ($data && isset($data['albums'])) {
        echo "✅ Data file contains " . count($data['albums']) . " albums<br>";
    } else {
        echo "❌ Data file is empty or corrupted<br>";
    }
} else {
    echo "❌ Data file does not exist<br>";
}

echo "<h2>API Test Links</h2>";
echo "<ul>";
echo "<li><a href='api/music_api.php?action=stats'>Test Stats API</a></li>";
echo "<li><a href='api/music_api.php?action=albums'>Test Albums API</a></li>";
echo "<li><a href='api/music_api.php?action=artists&search=pink'>Test Artists API</a></li>";
echo "</ul>";

echo "<p><a href='index.php'>Back to Music Collection</a></p>";
?> 