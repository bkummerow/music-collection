<?php
require_once __DIR__ . '/services/DiscogsAPIService.php';

echo "<h1>Artist Name Cleaning Test</h1>";

$discogsAPI = new DiscogsAPIService();

if (!$discogsAPI->isAvailable()) {
    echo "<p>❌ Discogs API is not available</p>";
    exit;
}

echo "<p>✅ Discogs API is available</p>";

$search = "Death";

echo "<h2>Testing artist search for: '$search'</h2>";

$artists = $discogsAPI->searchArtists($search, 5);

echo "<h3>Artist Results:</h3>";
if (!empty($artists)) {
    foreach ($artists as $artist) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>Artist Name:</strong> " . htmlspecialchars($artist['artist_name']) . "<br>";
        echo "<strong>ID:</strong> " . $artist['id'] . "<br>";
        echo "</div>";
    }
} else {
    echo "<p>❌ No artists found</p>";
}
?> 