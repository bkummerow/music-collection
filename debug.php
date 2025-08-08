<?php
/**
 * Debug File - Check what's happening with autocomplete
 */

echo "<h1>Debug Information</h1>";

// Check browser console for JavaScript errors
echo "<h2>JavaScript Debug</h2>";
echo "<p>Open your browser's developer tools (F12) and check the Console tab for any JavaScript errors.</p>";

// Test the API directly using PHP
echo "<h2>API Test (Direct PHP)</h2>";
echo "<p>Testing the artists API endpoint:</p>";

try {
    // Simulate the API call directly
    $_GET['action'] = 'artists';
    $_GET['search'] = 'pink';
    
    ob_start();
    include 'api/music_api.php';
    $response = ob_get_clean();
    
    echo "✅ API Response received<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
} catch (Exception $e) {
    echo "❌ API Error: " . $e->getMessage() . "<br>";
}

// Test the albums API
echo "<h2>Albums API Test (Direct PHP)</h2>";
try {
    // Simulate the API call directly
    $_GET['action'] = 'albums_by_artist';
    $_GET['artist'] = 'Pink Floyd';
    $_GET['search'] = '';
    
    ob_start();
    include 'api/music_api.php';
    $response2 = ob_get_clean();
    
    echo "✅ Albums API Response received<br>";
    echo "Response: " . htmlspecialchars($response2) . "<br>";
} catch (Exception $e) {
    echo "❌ Albums API Error: " . $e->getMessage() . "<br>";
}

// Check data file
echo "<h2>Data File Check</h2>";
$dataFile = 'data/music_collection.json';
if (file_exists($dataFile)) {
    echo "✅ Data file exists<br>";
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true);
    if ($data) {
        echo "✅ Data file is valid JSON<br>";
        echo "Contains " . count($data['albums']) . " albums<br>";
        foreach ($data['albums'] as $album) {
            echo "- " . $album['artist_name'] . " - " . $album['album_name'] . "<br>";
        }
    } else {
        echo "❌ Data file is not valid JSON<br>";
    }
} else {
    echo "❌ Data file does not exist<br>";
}

// Test the MusicCollection class directly
echo "<h2>Direct Class Test</h2>";
try {
    require_once 'models/MusicCollection.php';
    $musicCollection = new MusicCollection();
    
    echo "✅ MusicCollection class loaded successfully<br>";
    
    // Test artist search
    $artists = $musicCollection->getArtists('pink');
    echo "Found " . count($artists) . " artists matching 'pink'<br>";
    foreach ($artists as $artist) {
        echo "- " . $artist['artist_name'] . "<br>";
    }
    
    // Test album search
    $albums = $musicCollection->getAlbumsByArtist('Pink Floyd', '');
    echo "Found " . count($albums) . " albums by Pink Floyd<br>";
    foreach ($albums as $album) {
        echo "- " . $album['album_name'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Class Test Error: " . $e->getMessage() . "<br>";
}

echo "<h2>Manual Test</h2>";
echo "<p>Try these manual tests:</p>";
echo "<ol>";
echo "<li>Go to <a href='index.php'>index.php</a></li>";
echo "<li>Click '+ Add Album'</li>";
echo "<li>Type 'pink' in the Artist field</li>";
echo "<li>Check browser console for errors</li>";
echo "</ol>";

echo "<h2>Direct API Links</h2>";
echo "<p>Try these direct API links:</p>";
echo "<ul>";
echo "<li><a href='api/music_api.php?action=artists&search=pink' target='_blank'>Artists API</a></li>";
echo "<li><a href='api/music_api.php?action=albums_by_artist&artist=Pink%20Floyd' target='_blank'>Albums API</a></li>";
echo "<li><a href='api/music_api.php?action=stats' target='_blank'>Stats API</a></li>";
echo "</ul>";

echo "<p><a href='index.php'>Back to Music Collection</a></p>";
?> 