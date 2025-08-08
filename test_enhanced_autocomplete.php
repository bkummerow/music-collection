<?php
/**
 * Test Enhanced Autocomplete with MusicBrainz Integration
 */

require_once 'models/MusicCollection.php';
require_once 'services/MusicBrainzAPIService.php';

echo "<h1>Enhanced Autocomplete Test</h1>";

$musicCollection = new MusicCollection();
$musicBrainzAPI = new MusicBrainzAPIService();

echo "<h2>✅ MusicBrainz API Status</h2>";
if ($musicBrainzAPI->isAvailable()) {
    echo "✅ MusicBrainz API is available and working!<br>";
} else {
    echo "❌ MusicBrainz API is not available<br>";
}

echo "<h2>🎵 Test Artist Autocomplete</h2>";
$search = $_GET['search'] ?? 'pink';

echo "<h3>Searching for: '$search'</h3>";

// Get local artists
$localArtists = $musicCollection->getArtists($search);
echo "<h4>Local Artists (" . count($localArtists) . "):</h4>";
foreach ($localArtists as $artist) {
    echo "- " . $artist['name'] . "<br>";
}

// Get MusicBrainz artists
$externalArtists = [];
if ($musicBrainzAPI->isAvailable()) {
    $externalArtists = $musicBrainzAPI->searchArtists($search, 5);
}
echo "<h4>MusicBrainz Artists (" . count($externalArtists) . "):</h4>";
foreach ($externalArtists as $artist) {
    echo "- " . $artist['name'] . " (External)<br>";
}

// Combine and remove duplicates
$allArtists = $localArtists;
$localNames = array_column($localArtists, 'name');
foreach ($externalArtists as $artist) {
    if (!in_array($artist['name'], $localNames)) {
        $allArtists[] = $artist;
    }
}

echo "<h4>Combined Results (" . count($allArtists) . "):</h4>";
foreach ($allArtists as $artist) {
    $source = isset($artist['type']) ? " (External)" : " (Local)";
    echo "- " . $artist['name'] . $source . "<br>";
}

echo "<h2>🎼 Test Album Autocomplete</h2>";
$artist = $_GET['artist'] ?? 'Pink Floyd';
$albumSearch = $_GET['album_search'] ?? '';

echo "<h3>Searching for albums by: '$artist'</h3>";
if ($albumSearch) {
    echo "With filter: '$albumSearch'<br>";
}

// Get local albums
$localAlbums = $musicCollection->getAlbumsByArtist($artist, $albumSearch);
echo "<h4>Local Albums (" . count($localAlbums) . "):</h4>";
foreach ($localAlbums as $album) {
    $year = $album['year'] ? " ({$album['year']})" : '';
    echo "- " . $album['title'] . $year . "<br>";
}

// Get MusicBrainz albums
$externalAlbums = [];
if ($musicBrainzAPI->isAvailable()) {
    $externalAlbums = $musicBrainzAPI->searchAlbumsByArtist($artist, $albumSearch, 5);
}
echo "<h4>MusicBrainz Albums (" . count($externalAlbums) . "):</h4>";
foreach ($externalAlbums as $album) {
    $year = $album['year'] ? " ({$album['year']})" : '';
    echo "- " . $album['title'] . $year . " (External)<br>";
}

// Combine and remove duplicates
$allAlbums = $localAlbums;
$localTitles = array_column($localAlbums, 'title');
foreach ($externalAlbums as $album) {
    if (!in_array($album['title'], $localTitles)) {
        $allAlbums[] = $album;
    }
}

echo "<h4>Combined Results (" . count($allAlbums) . "):</h4>";
foreach ($allAlbums as $album) {
    $year = $album['year'] ? " ({$album['year']})" : '';
    $source = isset($album['type']) ? " (External)" : " (Local)";
    echo "- " . $album['title'] . $year . $source . "<br>";
}

echo "<h2>🔗 API Endpoint Tests</h2>";
echo "<p>Test the actual API endpoints:</p>";
echo "<ul>";
echo "<li><a href='api/music_api.php?action=artists&search=pink' target='_blank'>Artists API</a></li>";
echo "<li><a href='api/music_api.php?action=albums_by_artist&artist=Pink%20Floyd' target='_blank'>Albums API</a></li>";
echo "<li><a href='api/music_api.php?action=albums_by_artist&artist=Pink%20Floyd&search=wall' target='_blank'>Filtered Albums API</a></li>";
echo "</ul>";

echo "<h2>🎯 Interactive Test</h2>";
echo "<form method='get'>";
echo "Artist Search: <input type='text' name='search' value='$search'><br>";
echo "Artist for Albums: <input type='text' name='artist' value='$artist'><br>";
echo "Album Filter: <input type='text' name='album_search' value='$albumSearch'><br>";
echo "<input type='submit' value='Test Autocomplete'>";
echo "</form>";

echo "<h2>✨ Features</h2>";
echo "<ul>";
echo "<li>✅ Shows your local artists first</li>";
echo "<li>✅ Adds MusicBrainz suggestions</li>";
echo "<li>✅ Avoids duplicates</li>";
echo "<li>✅ Graceful fallback if API unavailable</li>";
echo "<li>✅ Real-time search and filtering</li>";
echo "</ul>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 