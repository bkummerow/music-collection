<?php
/**
 * Check Current Music Collection
 */

echo "<h1>Current Music Collection</h1>";

$dataFile = 'data/music_collection.json';
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true);
    
    if ($data && isset($data['albums'])) {
        echo "<h2>Albums in Collection (" . count($data['albums']) . " total)</h2>";
        
        // Group by artist
        $artists = [];
        foreach ($data['albums'] as $album) {
            $artist = $album['artist_name'];
            if (!isset($artists[$artist])) {
                $artists[$artist] = [];
            }
            $artists[$artist][] = $album;
        }
        
        foreach ($artists as $artist => $albums) {
            echo "<h3>$artist (" . count($albums) . " albums)</h3>";
            echo "<ul>";
            foreach ($albums as $album) {
                $status = [];
                if ($album['is_owned']) $status[] = 'Owned';
                if ($album['want_to_own']) $status[] = 'Wanted';
                $statusText = !empty($status) ? ' (' . implode(', ', $status) . ')' : '';
                
                echo "<li>{$album['album_name']} ({$album['release_year']})$statusText</li>";
            }
            echo "</ul>";
        }
        
        echo "<h2>Available Artists for Autocomplete</h2>";
        echo "<ul>";
        foreach (array_keys($artists) as $artist) {
            echo "<li>$artist</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p>No albums found in collection.</p>";
    }
} else {
    echo "<p>Data file not found.</p>";
}

echo "<h2>Autocomplete Behavior</h2>";
echo "<p>Currently, the autocomplete only shows artists that are already in your collection.</p>";
echo "<p>This means:</p>";
echo "<ul>";
echo "<li>✅ You can easily add more albums by existing artists</li>";
echo "<li>❌ You can't get suggestions for new artists</li>";
echo "<li>❌ You can't get album suggestions for new artists</li>";
echo "</ul>";

echo "<h2>Options to Improve Autocomplete</h2>";
echo "<p>We could enhance this by:</p>";
echo "<ol>";
echo "<li><strong>Adding a simple artist database</strong> - Include common artists in the autocomplete</li>";
echo "<li><strong>Using a free music API</strong> - Like MusicBrainz or Last.fm (requires API key)</li>";
echo "<li><strong>Manual artist entry</strong> - Just type the artist name manually</li>";
echo "</ol>";

echo "<p><a href='index.php'>Back to Music Collection</a></p>";
?> 