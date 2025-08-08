<?php
/**
 * Test Cover Art Save Functionality
 * Verifies that cover art URLs are saved when albums are added
 */

require_once __DIR__ . '/models/MusicCollection.php';

echo "<h1>🖼️ Test Cover Art Save</h1>";
echo "<p>Testing that cover art URLs are saved when albums are added</p>";

$musicCollection = new MusicCollection();

// Test data
$testAlbums = [
    [
        'artist' => 'Billy Bragg',
        'album' => 'Don\'t Try This at Home',
        'cover_url' => 'https://coverartarchive.org/release/12345/front-500.jpg'
    ],
    [
        'artist' => 'Pink Floyd',
        'album' => 'The Dark Side of the Moon',
        'cover_url' => 'https://coverartarchive.org/release/67890/front-500.jpg'
    ]
];

echo "<h2>📊 Testing Cover Art Save</h2>";

foreach ($testAlbums as $testAlbum) {
    $artistName = $testAlbum['artist'];
    $albumName = $testAlbum['album'];
    $coverUrl = $testAlbum['cover_url'];
    
    echo "<h3>Test: $artistName - $albumName</h3>";
    echo "<p><strong>Cover URL:</strong> $coverUrl</p>";
    
    try {
        // Add the album with cover art
        $result = $musicCollection->addAlbum(
            $artistName,
            $albumName,
            1991, // Sample year
            1, // Owned
            0, // Not wanted
            $coverUrl
        );
        
        if ($result) {
            echo "<p>✅ Album added successfully</p>";
            
            // Verify the album was saved with cover art
            $albums = $musicCollection->getAllAlbums();
            $foundAlbum = null;
            
            foreach ($albums as $album) {
                if ($album['artist_name'] === $artistName && $album['album_name'] === $albumName) {
                    $foundAlbum = $album;
                    break;
                }
            }
            
            if ($foundAlbum) {
                echo "<p><strong>Saved Cover URL:</strong> " . ($foundAlbum['cover_url'] ?? 'None') . "</p>";
                if ($foundAlbum['cover_url'] === $coverUrl) {
                    echo "<p>✅ Cover art saved correctly!</p>";
                } else {
                    echo "<p>❌ Cover art not saved correctly</p>";
                }
            } else {
                echo "<p>❌ Album not found after saving</p>";
            }
        } else {
            echo "<p>❌ Failed to add album</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>🔍 Current Database Contents</h2>";
$albums = $musicCollection->getAllAlbums();
if (!empty($albums)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Artist</th><th>Album</th><th>Cover URL</th></tr>";
    foreach ($albums as $album) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($album['artist_name']) . "</td>";
        echo "<td>" . htmlspecialchars($album['album_name']) . "</td>";
        echo "<td>" . htmlspecialchars($album['cover_url'] ?? 'None') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No albums in database</p>";
}

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 