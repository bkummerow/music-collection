<?php
/**
 * Check Database Script
 * Shows the current state of albums in the database
 */

require_once __DIR__ . '/config/database.php';

// Get database connection
$connection = getDBConnection();

// Get all albums
$sql = "SELECT id, artist_name, album_name, cover_url, discogs_release_id FROM music_collection ORDER BY id";
$albums = $connection->query($sql);

echo "<h2>Database Analysis</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Artist</th><th>Album</th><th>Cover URL</th><th>Discogs ID</th></tr>";

foreach ($albums as $album) {
    $coverUrl = $album['cover_url'] ?? 'NULL';
    $discogsId = $album['discogs_release_id'] ?? 'NULL';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($album['id']) . "</td>";
    echo "<td>" . htmlspecialchars($album['artist_name']) . "</td>";
    echo "<td>" . htmlspecialchars($album['album_name']) . "</td>";
    echo "<td style='word-break: break-all; max-width: 300px;'>" . htmlspecialchars($coverUrl) . "</td>";
    echo "<td>" . htmlspecialchars($discogsId) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Summary</h2>";
echo "<p>Total albums: " . count($albums) . "</p>";

$withCover = 0;
$withoutCover = 0;
$withDiscogsId = 0;
$withoutDiscogsId = 0;

foreach ($albums as $album) {
    if (!empty($album['cover_url'])) {
        $withCover++;
    } else {
        $withoutCover++;
    }
    
    if (!empty($album['discogs_release_id'])) {
        $withDiscogsId++;
    } else {
        $withoutDiscogsId++;
    }
}

echo "<p>Albums with cover URLs: $withCover</p>";
echo "<p>Albums without cover URLs: $withoutCover</p>";
echo "<p>Albums with Discogs IDs: $withDiscogsId</p>";
echo "<p>Albums without Discogs IDs: $withoutDiscogsId</p>";
?>
