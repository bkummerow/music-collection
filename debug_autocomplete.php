<?php
/**
 * Debug Autocomplete API Response
 */

require_once 'models/MusicCollection.php';
require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🔍 Debug Autocomplete API Response</h1>";

$musicCollection = new MusicCollection();
$musicBrainzAPI = new MusicBrainzAPIService();

echo "<h2>🎵 Testing Artist Autocomplete</h2>";
$search = $_GET['search'] ?? 'smiths';
echo "Search: '$search'<br>";

// Simulate the API response
$localArtists = $musicCollection->getArtists($search);
$externalArtists = [];
if ($musicBrainzAPI->isAvailable()) {
    $externalArtists = $musicBrainzAPI->searchArtists($search, 5);
}

// Combine results (same logic as API)
$allArtists = $localArtists;
$localNames = array_column($localArtists, 'name');
foreach ($externalArtists as $artist) {
    if (!in_array($artist['name'], $localNames)) {
        $allArtists[] = $artist;
    }
}

echo "<h3>API Response for Artists:</h3>";
echo "<pre>" . json_encode($allArtists, JSON_PRETTY_PRINT) . "</pre>";

echo "<h2>🎼 Testing Album Autocomplete</h2>";
$artist = $_GET['artist'] ?? 'The Smiths';
$albumSearch = $_GET['album_search'] ?? 'smiths';
echo "Artist: '$artist'<br>";
echo "Album Search: '$albumSearch'<br>";

// Simulate the API response
$localAlbums = $musicCollection->getAlbumsByArtist($artist, $albumSearch);
$externalAlbums = [];
if ($musicBrainzAPI->isAvailable()) {
    $externalAlbums = $musicBrainzAPI->searchAlbumsByArtist($artist, $albumSearch, 5);
}

// Combine results (same logic as API)
$allAlbums = $localAlbums;
$localTitles = array_column($localAlbums, 'title');
foreach ($externalAlbums as $album) {
    if (!in_array($album['title'], $localTitles)) {
        $allAlbums[] = $album;
    }
}

echo "<h3>API Response for Albums:</h3>";
echo "<pre>" . json_encode($allAlbums, JSON_PRETTY_PRINT) . "</pre>";

echo "<h2>🔗 Test Actual API Endpoints</h2>";
echo "<p>Test the real API endpoints:</p>";
echo "<ul>";
echo "<li><a href='api/music_api.php?action=artists&search=$search' target='_blank'>Artists API</a></li>";
echo "<li><a href='api/music_api.php?action=albums_by_artist&artist=" . urlencode($artist) . "&search=$albumSearch' target='_blank'>Albums API</a></li>";
echo "</ul>";

echo "<h2>🎯 JavaScript Debug</h2>";
echo "<p>Check browser console for these logs:</p>";
echo "<ul>";
echo "<li>Autocomplete API calls</li>";
echo "<li>Selected item data</li>";
echo "<li>Year field population</li>";
echo "</ul>";

echo "<h2>🔧 Manual Test Steps</h2>";
echo "<ol>";
echo "<li>Open browser console (F12)</li>";
echo "<li>Go to your music collection</li>";
echo "<li>Click '+ Add Album'</li>";
echo "<li>Type 'smiths' in Artist field</li>";
echo "<li>Select 'The Smiths'</li>";
echo "<li>Type 'smiths' in Album field</li>";
echo "<li>Check console for logs</li>";
echo "<li>Select 'The Smiths' album</li>";
echo "<li>Check if year field populates</li>";
echo "</ol>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 