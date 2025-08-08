<?php
/**
 * Test Autocomplete Functionality
 */

echo "<h1>Autocomplete Test</h1>";

// Check if required files exist
if (!file_exists('models/MusicCollection.php')) {
    echo "❌ Error: models/MusicCollection.php not found<br>";
    echo "<p><a href='index.php'>Back to Music Collection</a></p>";
    exit;
}

try {
    require_once 'models/MusicCollection.php';
    
    $musicCollection = new MusicCollection();
    
    // Test artist search
    echo "<h2>Artist Search Test</h2>";
    $search = $_GET['search'] ?? 'pink';
    echo "Searching for: '$search'<br>";
    
    $artists = $musicCollection->getArtists($search);
    echo "Found " . count($artists) . " artists:<br>";
    foreach ($artists as $artist) {
        echo "- " . $artist['artist_name'] . "<br>";
    }
    
    // Test album search
    echo "<h2>Album Search Test</h2>";
    $artist = $_GET['artist'] ?? 'Pink Floyd';
    echo "Searching for albums by: '$artist'<br>";
    
    $albums = $musicCollection->getAlbumsByArtist($artist, '');
    echo "Found " . count($albums) . " albums:<br>";
    foreach ($albums as $album) {
        echo "- " . $album['album_name'] . "<br>";
    }
    
    echo "<h2>API Test</h2>";
    echo "<p>Test the API endpoints:</p>";
    echo "<ul>";
    echo "<li><a href='api/music_api.php?action=artists&search=pink'>Artists API</a></li>";
    echo "<li><a href='api/music_api.php?action=albums_by_artist&artist=Pink%20Floyd'>Albums API</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>This might be due to missing files or configuration issues.</p>";
}

echo "<p><a href='index.php'>Back to Music Collection</a></p>";
?> 