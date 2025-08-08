<?php
/**
 * Update existing albums with cover art
 */

require_once 'config/database.php';
require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🖼️ Update Existing Albums with Cover Art</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();
$db = getDBConnection();

if (!$musicBrainzAPI->isAvailable()) {
    echo "<p>❌ MusicBrainz API is not available. Cannot update cover art.</p>";
    exit;
}

// Load existing data
$data = $db->query("SELECT * FROM music_collection");
$updated = 0;
$errors = 0;

echo "<h2>📊 Processing Albums</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Artist</th><th>Album</th><th>Current Cover</th><th>New Cover</th><th>Status</th></tr>";

foreach ($data['albums'] as &$album) {
    $artistName = $album['artist_name'];
    $albumName = $album['album_name'];
    $currentCover = $album['cover_url'] ?? 'None';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($artistName) . "</td>";
    echo "<td>" . htmlspecialchars($albumName) . "</td>";
    echo "<td>" . htmlspecialchars($currentCover) . "</td>";
    
    // Skip if already has cover art
    if (!empty($album['cover_url'])) {
        echo "<td>Already has cover</td>";
        echo "<td>✅ Skipped</td>";
        echo "</tr>";
        continue;
    }
    
    try {
        // Search for the album in MusicBrainz
        $albums = $musicBrainzAPI->searchAlbumsByArtist($artistName, $albumName, 1);
        
        if (!empty($albums) && !empty($albums[0]['cover_url'])) {
            $newCover = $albums[0]['cover_url'];
            $album['cover_url'] = $newCover;
            $updated++;
            
            echo "<td><img src='" . htmlspecialchars($newCover) . "' style='width: 30px; height: 30px; object-fit: cover;'></td>";
            echo "<td>✅ Updated</td>";
        } else {
            echo "<td>No cover found</td>";
            echo "<td>❌ No cover available</td>";
            $errors++;
        }
    } catch (Exception $e) {
        echo "<td>Error</td>";
        echo "<td>❌ " . htmlspecialchars($e->getMessage()) . "</td>";
        $errors++;
    }
    
    echo "</tr>";
}

echo "</table>";

// Save updated data
if ($updated > 0) {
    echo "<h2>✅ Update Complete</h2>";
    echo "<p>Updated $updated albums with cover art.</p>";
    if ($errors > 0) {
        echo "<p>Failed to find cover art for $errors albums.</p>";
    }
} else {
    echo "<h2>ℹ️ No Updates Needed</h2>";
    echo "<p>No albums were updated. All albums already have cover art or no covers were found.</p>";
}

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 