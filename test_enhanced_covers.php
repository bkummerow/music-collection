<?php
/**
 * Test Enhanced Cover Art Functionality
 * Demonstrates multiple cover art sources
 */

require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🖼️ Enhanced Cover Art Test</h1>";
echo "<p>Testing multiple cover art sources for albums</p>";

$musicBrainzAPI = new MusicBrainzAPIService();

if (!$musicBrainzAPI->isAvailable()) {
    echo "<p>❌ MusicBrainz API is not available.</p>";
    exit;
}

// Test albums with different scenarios
$testAlbums = [
    [
        'artist' => 'Pink Floyd',
        'album' => 'The Dark Side of the Moon',
        'description' => 'Very popular album - should have cover art'
    ],
    [
        'artist' => 'The Smiths',
        'album' => 'The Smiths',
        'description' => 'Popular album - should have cover art'
    ],
    [
        'artist' => 'Billy Bragg',
        'album' => 'Talking to the Taxman about Poetry',
        'description' => 'Somewhat popular album - should have cover art'
    ],
    [
        'artist' => 'Unknown Artist',
        'album' => 'Very Obscure Album',
        'description' => 'Obscure album - might not have cover art'
    ]
];

echo "<h2>📊 Testing Cover Art Sources</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr><th>Artist</th><th>Album</th><th>Description</th><th>Cover Art</th><th>Source</th></tr>";

foreach ($testAlbums as $testAlbum) {
    $artistName = $testAlbum['artist'];
    $albumName = $testAlbum['album'];
    $description = $testAlbum['description'];
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($artistName) . "</td>";
    echo "<td>" . htmlspecialchars($albumName) . "</td>";
    echo "<td>" . htmlspecialchars($description) . "</td>";
    
    try {
        // Search for the album
        $albums = $musicBrainzAPI->searchAlbumsByArtist($artistName, $albumName, 1);
        
        if (!empty($albums)) {
            $album = $albums[0];
            $coverUrl = $album['cover_url'] ?? null;
            
            if ($coverUrl) {
                echo "<td><img src='" . htmlspecialchars($coverUrl) . "' style='width: 60px; height: 60px; object-fit: cover; border-radius: 5px;'></td>";
                echo "<td>✅ Found</td>";
            } else {
                echo "<td><div style='width: 60px; height: 60px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #6c757d;'>No Cover</div></td>";
                echo "<td>❌ Not found</td>";
            }
        } else {
            echo "<td><div style='width: 60px; height: 60px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #6c757d;'>No Album</div></td>";
            echo "<td>❌ Album not found</td>";
        }
    } catch (Exception $e) {
        echo "<td><div style='width: 60px; height: 60px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #6c757d;'>Error</div></td>";
        echo "<td>❌ Error: " . htmlspecialchars($e->getMessage()) . "</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<h2>🔍 Available Cover Art Sources</h2>";
echo "<ul>";
echo "<li><strong>Cover Art Archive</strong> - MusicBrainz's official cover art repository</li>";
echo "<li><strong>Last.fm API</strong> - Community-driven music database with cover art</li>";
echo "<li><strong>Spotify API</strong> - Commercial music service (requires API key)</li>";
echo "<li><strong>Discogs API</strong> - Vinyl/CD database with cover art (requires API key)</li>";
echo "<li><strong>iTunes API</strong> - Apple's music database (requires API key)</li>";
echo "</ul>";

echo "<h2>💡 How It Works</h2>";
echo "<ol>";
echo "<li><strong>Primary Source</strong>: Cover Art Archive (MusicBrainz's official source)</li>";
echo "<li><strong>Fallback 1</strong>: Last.fm API (community database)</li>";
echo "<li><strong>Fallback 2</strong>: Direct image URLs from Cover Art Archive</li>";
echo "<li><strong>Future</strong>: Could add Spotify, Discogs, iTunes APIs with API keys</li>";
echo "</ol>";

echo "<h2>🚀 Benefits</h2>";
echo "<ul>";
echo "<li><strong>Higher Success Rate</strong>: Multiple sources increase chances of finding cover art</li>";
echo "<li><strong>Quality Images</strong>: Different sources may have different quality images</li>";
echo "<li><strong>Redundancy</strong>: If one source fails, others can still work</li>";
echo "<li><strong>Extensibility</strong>: Easy to add more sources in the future</li>";
echo "</ul>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 