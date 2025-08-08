<?php
/**
 * Database Installation Script
 * Run this once to create the music collection table
 */

require_once '../config/database.php';

echo "<h1>Music Collection Manager - Database Installation</h1>";

try {
    $connection = getDBConnection();
    $connectionType = getConnectionType();
    
    echo "<p><strong>Database Connection Type:</strong> $connectionType</p>";
    
    echo "<p>✅ File-based database initialized successfully!</p>";
    
    // Insert some sample data
    $sampleData = [
        ['Pink Floyd', 'The Dark Side of the Moon', 1973, true, false],
        ['Led Zeppelin', 'Led Zeppelin IV', 1971, true, false],
        ['The Beatles', 'Abbey Road', 1969, false, true],
        ['Radiohead', 'OK Computer', 1997, true, false],
        ['Daft Punk', 'Random Access Memories', 2013, false, true]
    ];
    
    $insertSql = "INSERT INTO music_collection (artist_name, album_name, release_year, is_owned, want_to_own) 
                   VALUES (?, ?, ?, ?, ?)";
    
    foreach ($sampleData as $data) {
        $connection->query($insertSql, $data);
    }
    
    echo "<p>✅ Sample data inserted successfully!</p>";
    echo "<p>✅ Installation complete. You can now access your music collection application.</p>";
    echo "<p><a href='../index.php'>Go to Music Collection Manager</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Installation failed: " . $e->getMessage() . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ul>";
    echo "<li>Check your database credentials in config/database.php</li>";
    echo "<li>Ensure your database exists and is accessible</li>";
    echo "<li>Verify your hosting provider supports MySQL</li>";
    echo "</ul>";
}
?> 