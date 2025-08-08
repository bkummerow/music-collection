<?php
/**
 * Test MusicBrainz API Integration
 */

require_once 'services/MusicBrainzAPIService.php';

echo "<h1>MusicBrainz API Test</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();

echo "<h2>API Availability Test</h2>";
if ($musicBrainzAPI->isAvailable()) {
    echo "✅ MusicBrainz API is available<br>";
} else {
    echo "❌ MusicBrainz API is not available<br>";
    echo "<p>This might be due to network issues or API restrictions.</p>";
}

echo "<h2>Artist Search Test</h2>";
$search = $_GET['search'] ?? 'pink floyd';
echo "Searching for: '$search'<br>";

try {
    $artists = $musicBrainzAPI->searchArtists($search, 5);
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
    $albums = $musicBrainzAPI->searchAlbumsByArtist($artist, '', 5);
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

echo "<h2>About MusicBrainz</h2>";
echo "<p>MusicBrainz is a free, open-source music database that:</p>";
echo "<ul>";
echo "<li>✅ Has comprehensive artist and album data</li>";
echo "<li>✅ Provides free API access</li>";
echo "<li>✅ Is community-maintained and accurate</li>";
echo "<li>✅ Has good uptime and reliability</li>";
echo "</ul>";

echo "<p><a href='index.php'>Back to Music Collection</a></p>";
?> 