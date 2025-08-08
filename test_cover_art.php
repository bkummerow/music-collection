<?php
/**
 * Test Cover Art Functionality
 */

require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🖼️ Test Cover Art Functionality</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();

$artist = $_GET['artist'] ?? 'Pink Floyd';
echo "<h2>Testing Albums by: $artist</h2>";

try {
    $albums = $musicBrainzAPI->searchAlbumsByArtist($artist, '', 5);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Album Title</th><th>Release Year</th><th>Release ID</th><th>Cover URL</th><th>Cover Image</th></tr>";
    
    foreach ($albums as $album) {
        $year = $album['year'] ? $album['year'] : 'Unknown';
        $coverUrl = $album['cover_url'] ?? 'No cover';
        $releaseId = $album['id'] ?? 'No ID';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($album['title']) . "</td>";
        echo "<td>" . htmlspecialchars($year) . "</td>";
        echo "<td>" . htmlspecialchars($releaseId) . "</td>";
        echo "<td>" . htmlspecialchars($coverUrl) . "</td>";
        echo "<td>";
        if ($coverUrl && $coverUrl !== 'No cover') {
            echo "<img src='" . htmlspecialchars($coverUrl) . "' style='width: 50px; height: 50px; object-fit: cover;' onerror='this.style.display=\"none\"'>";
        } else {
            echo "No image";
        }
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<h2>🔍 Manual Cover Art Test</h2>";
echo "<p>Test specific release IDs:</p>";

// Test with a known Pink Floyd release ID
$testReleaseId = '1203ea82-2db2-3fcd-bbb5-b8c64097cfbf'; // The Wall
echo "<p>Testing release ID: $testReleaseId</p>";

$coverUrl = $musicBrainzAPI->getAlbumCover($testReleaseId);
echo "<p>Cover URL: " . htmlspecialchars($coverUrl ?? 'No cover found') . "</p>";

if ($coverUrl) {
    echo "<img src='" . htmlspecialchars($coverUrl) . "' style='width: 100px; height: 100px; object-fit: cover; border: 1px solid #ccc;'>";
}

echo "<h2>🔧 Debug Information</h2>";
echo "<p>MusicBrainz API Status: " . ($musicBrainzAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";

echo "<h2>🎯 Test Different Artists</h2>";
echo "<form method='get'>";
echo "Artist: <input type='text' name='artist' value='$artist'>";
echo "<input type='submit' value='Test Cover Art'>";
echo "</form>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 