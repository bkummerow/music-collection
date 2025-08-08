<?php
require_once __DIR__ . '/services/DiscogsAPIService.php';

echo "<h1>Direct Discogs Service Test</h1>";

$discogsAPI = new DiscogsAPIService();

if (!$discogsAPI->isAvailable()) {
    echo "<p>❌ Discogs API is not available</p>";
    exit;
}

echo "<p>✅ Discogs API is available</p>";

$artist = "Aaron Dilloway";
$search = "corpse";

echo "<h2>Testing: $artist - $search</h2>";

$albums = $discogsAPI->searchAlbumsByArtist($artist, $search, 5);

echo "<h3>Raw Discogs Results:</h3>";
echo "<pre>";
print_r($albums);
echo "</pre>";

if (!empty($albums)) {
    echo "<h3>Processed Results:</h3>";
    foreach ($albums as $album) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>Title:</strong> " . htmlspecialchars($album['title']) . "<br>";
        echo "<strong>Artist:</strong> " . htmlspecialchars($album['artist']) . "<br>";
        echo "<strong>Year:</strong> " . ($album['year'] ?? 'N/A') . "<br>";
        echo "<strong>Cover URL:</strong> " . ($album['cover_url'] ?? 'N/A') . "<br>";
        echo "</div>";
    }
} else {
    echo "<p>❌ No albums found</p>";
}
?> 