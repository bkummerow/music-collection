<?php
/**
 * Test Release Year Data from MusicBrainz
 */

require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🎵 Release Year Test</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();

$artist = $_GET['artist'] ?? 'Pink Floyd';
echo "<h2>Testing Albums by: $artist</h2>";

try {
    $albums = $musicBrainzAPI->searchAlbumsByArtist($artist, '', 10);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Album Title</th><th>Release Year</th><th>Artist</th></tr>";
    
    foreach ($albums as $album) {
        $year = $album['year'] ? $album['year'] : 'Unknown';
        $yearClass = $album['year'] ? 'year-available' : 'year-missing';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($album['title']) . "</td>";
        echo "<td class='$yearClass'>" . htmlspecialchars($year) . "</td>";
        echo "<td>" . htmlspecialchars($album['artist']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Count albums with years
    $withYears = array_filter($albums, function($album) {
        return !empty($album['year']);
    });
    
    echo "<h3>📊 Year Data Statistics</h3>";
    echo "<p>Total albums found: " . count($albums) . "</p>";
    echo "<p>Albums with year data: " . count($withYears) . "</p>";
    echo "<p>Coverage: " . round((count($withYears) / count($albums)) * 100, 1) . "%</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<h2>🎯 Test Different Artists</h2>";
echo "<form method='get'>";
echo "Artist: <input type='text' name='artist' value='$artist'>";
echo "<input type='submit' value='Test Year Data'>";
echo "</form>";

echo "<h3>Suggested Artists to Test:</h3>";
echo "<ul>";
echo "<li><a href='?artist=The Beatles'>The Beatles</a></li>";
echo "<li><a href='?artist=Radiohead'>Radiohead</a></li>";
echo "<li><a href='?artist=Queen'>Queen</a></li>";
echo "<li><a href='?artist=David Bowie'>David Bowie</a></li>";
echo "<li><a href='?artist=The Rolling Stones'>The Rolling Stones</a></li>";
echo "</ul>";

echo "<style>";
echo ".year-available { background-color: #d4edda; color: #155724; }";
echo ".year-missing { background-color: #f8d7da; color: #721c24; }";
echo "</style>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 