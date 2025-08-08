<?php
/**
 * Test Discogs API Integration
 */

require_once 'services/DiscogsAPIService.php';

echo "<h1>Discogs API Test</h1>";

$discogsAPI = new DiscogsAPIService();

echo "<h2>API Availability Test</h2>";
if ($discogsAPI->isAvailable()) {
    echo "✅ Discogs API is available<br>";
} else {
    echo "❌ Discogs API is not available<br>";
    echo "<p>This might be due to network issues or API restrictions.</p>";
}

echo "<h2>Artist Search Test</h2>";
$search = $_GET['search'] ?? 'pink floyd';
echo "Searching for: '$search'<br>";

try {
    $artists = $discogsAPI->searchArtists($search, 5);
    echo "Found " . count($artists) . " artists:<br>";
    foreach ($artists as $artist) {
        echo "- " . $artist['name'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h2>Album Search Test</h2>";
$artist = $_GET['artist'] ?? 'Pink Floyd';
echo "Searching for albums by: '$artist'<br>";

try {
    $albums = $discogsAPI->searchAlbumsByArtist($artist, '', 5);
    echo "Found " . count($albums) . " albums:<br>";
    foreach ($albums as $album) {
        $year = $album['year'] ? " ({$album['year']})" : '';
        echo "- " . $album['title'] . $year . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h2>API Test Links</h2>";
echo "<p>Test the enhanced API endpoints:</p>";
echo "<ul>";
echo "<li><a href='api/music_api.php?action=artists&search=pink' target='_blank'>Enhanced Artists API</a></li>";
echo "<li><a href='api/music_api.php?action=albums_by_artist&artist=Pink%20Floyd' target='_blank'>Enhanced Albums API</a></li>";
echo "</ul>";

echo "<h2>How It Works</h2>";
echo "<p>The enhanced autocomplete now:</p>";
echo "<ul>";
echo "<li>✅ Shows artists from your local collection first</li>";
echo "<li>✅ Adds suggestions from Discogs API</li>";
echo "<li>✅ Avoids duplicates between local and external results</li>";
echo "<li>✅ Falls back gracefully if API is unavailable</li>";
echo "</ul>";

echo "<p><a href='index.php'>Back to Music Collection</a></p>";
?> 