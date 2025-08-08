<?php
require_once __DIR__ . '/services/DiscogsAPIService.php';
require_once __DIR__ . '/models/MusicCollection.php';

// Test the API response directly
$artist = "Dumptruck";
$search = "D is for";

echo "<h1>API Response Test</h1>";
echo "<p>Artist: $artist</p>";
echo "<p>Search: $search</p>";

// Simulate the API logic
$musicCollection = new MusicCollection();
$discogsAPI = new DiscogsAPIService();

// Get local albums first
$localAlbums = $musicCollection->getAlbumsByArtist($artist, $search);

// Try to get additional albums from Discogs API
$externalAlbums = [];
if ($discogsAPI->isAvailable()) {
    $externalAlbums = $discogsAPI->searchAlbumsByArtist($artist, $search, 5);
}

// Combine local and external results, prioritizing local
$allAlbums = [];
$seenAlbums = [];

// Add local albums first
foreach ($localAlbums as $album) {
    $allAlbums[] = $album;
    $seenAlbums[strtolower($album['album_name'])] = true;
}

// Add external albums that aren't already in local collection
foreach ($externalAlbums as $album) {
    $albumName = $album['title']; // This is already cleaned by DiscogsAPIService
    if (!isset($seenAlbums[strtolower($albumName)])) {
        $allAlbums[] = [
            'album_name' => $albumName,
            'year' => $album['year'] ?? null,
            'artist' => $album['artist'] ?? $artist,
            'cover_url' => $album['cover_url'] ?? null
        ];
        $seenAlbums[strtolower($albumName)] = true;
    }
}

echo "<h2>Combined Results:</h2>";
echo "<pre>";
print_r($allAlbums);
echo "</pre>";

echo "<h2>Album Data:</h2>";
foreach ($allAlbums as $album) {
    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
    echo "<strong>Album Name:</strong> " . htmlspecialchars($album['album_name']) . "<br>";
    echo "<strong>Artist:</strong> " . htmlspecialchars($album['artist'] ?? 'N/A') . "<br>";
    echo "<strong>Year:</strong> " . ($album['year'] ?? 'N/A') . "<br>";
    echo "</div>";
}
?> 