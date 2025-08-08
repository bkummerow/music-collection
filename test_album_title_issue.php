<?php
require_once __DIR__ . '/services/DiscogsAPIService.php';

echo "<h1>Album Title Debug Test</h1>";

$discogsAPI = new DiscogsAPIService();

if (!$discogsAPI->isAvailable()) {
    echo "<p>❌ Discogs API is not available</p>";
    exit;
}

echo "<p>✅ Discogs API is available</p>";

// Test the specific case from the screenshot
$artistName = "Aaron Dilloway";
$albumQuery = "Corpse On Horseback";

echo "<h2>Testing: $artistName - $albumQuery</h2>";

try {
    $albums = $discogsAPI->searchAlbumsByArtist($artistName, $albumQuery, 5);
    
    echo "<h3>Raw API Response:</h3>";
    echo "<pre>";
    print_r($albums);
    echo "</pre>";
    
    if (empty($albums)) {
        echo "<p>❌ No albums found</p>";
    } else {
        echo "<h3>Processed Results:</h3>";
        foreach ($albums as $album) {
            echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
            echo "<strong>Title:</strong> " . htmlspecialchars($album['title']) . "<br>";
            echo "<strong>Artist:</strong> " . htmlspecialchars($album['artist']) . "<br>";
            echo "<strong>Year:</strong> " . ($album['year'] ?? 'N/A') . "<br>";
            echo "<strong>Cover URL:</strong> " . ($album['cover_url'] ?? 'N/A') . "<br>";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Testing with different search terms:</h2>";

// Test with just the album name
echo "<h3>Searching for just 'Corpse On Horseback':</h3>";
try {
    $albums2 = $discogsAPI->searchAlbumsByArtist($artistName, "Corpse", 3);
    echo "<pre>";
    print_r($albums2);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 