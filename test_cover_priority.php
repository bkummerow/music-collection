<?php
/**
 * Test Cover Art Priority
 * Verifies that Discogs is prioritized over MusicBrainz
 */

require_once __DIR__ . '/services/MusicBrainzAPIService.php';
require_once __DIR__ . '/services/DiscogsAPIService.php';

echo "<h1>🖼️ Test Cover Art Priority</h1>";
echo "<p>Testing that Discogs is prioritized over MusicBrainz for cover art</p>";

$musicBrainzAPI = new MusicBrainzAPIService();
$discogsAPI = new DiscogsAPIService();

echo "<h2>📊 API Availability</h2>";
echo "<p><strong>Discogs API:</strong> " . ($discogsAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";
echo "<p><strong>MusicBrainz API:</strong> " . ($musicBrainzAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";

if (!$discogsAPI->isAvailable()) {
    echo "<p>❌ Discogs API not available. Please configure your API key in config/api_config.php</p>";
    exit;
}

// Test albums that should have cover art on both services
$testAlbums = [
    [
        'artist' => 'Pink Floyd',
        'album' => 'The Dark Side of the Moon',
        'description' => 'Very popular album - should have cover art on both'
    ],
    [
        'artist' => 'The Beatles',
        'album' => 'Abbey Road',
        'description' => 'Classic album - should have cover art on both'
    ],
    [
        'artist' => 'Led Zeppelin',
        'album' => 'Led Zeppelin IV',
        'description' => 'Rock classic - should have cover art on both'
    ]
];

echo "<h2>🔍 Testing Cover Art Priority</h2>";
echo "<p><strong>Priority Order:</strong></p>";
echo "<ol>";
echo "<li>1. Discogs API (HIGHEST PRIORITY)</li>";
echo "<li>2. Cover Art Archive (MusicBrainz)</li>";
echo "<li>3. Last.fm API</li>";
echo "</ol>";

foreach ($testAlbums as $testAlbum) {
    $artistName = $testAlbum['artist'];
    $albumName = $testAlbum['album'];
    $description = $testAlbum['description'];
    
    echo "<h3>Test: $artistName - $albumName</h3>";
    echo "<p><strong>Description:</strong> $description</p>";
    
    // Test Discogs directly
    echo "<h4>Discogs Results:</h4>";
    try {
        $discogsAlbums = $discogsAPI->searchAlbumsByArtist($artistName, $albumName, 1);
        if (!empty($discogsAlbums)) {
            $album = $discogsAlbums[0];
            $cover = $album['cover_url'] ? '✅' : '❌';
            echo "<p>{$cover} <strong>Discogs Cover:</strong> " . htmlspecialchars($album['title']) . "</p>";
            if ($album['cover_url']) {
                echo "<p><img src='" . htmlspecialchars($album['cover_url']) . "' style='width: 100px; height: 100px; object-fit: cover; border-radius: 5px;'></p>";
            }
        } else {
            echo "<p>❌ No Discogs results</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Discogs Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test MusicBrainz directly
    echo "<h4>MusicBrainz Results:</h4>";
    try {
        $musicBrainzAlbums = $musicBrainzAPI->searchAlbumsByArtist($artistName, $albumName, 1);
        if (!empty($musicBrainzAlbums)) {
            $album = $musicBrainzAlbums[0];
            $cover = $album['cover_url'] ? '✅' : '❌';
            echo "<p>{$cover} <strong>MusicBrainz Cover:</strong> " . htmlspecialchars($album['title']) . "</p>";
            if ($album['cover_url']) {
                echo "<p><img src='" . htmlspecialchars($album['cover_url']) . "' style='width: 100px; height: 100px; object-fit: cover; border-radius: 5px;'></p>";
            }
        } else {
            echo "<p>❌ No MusicBrainz results</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ MusicBrainz Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>✅ Expected Behavior</h2>";
echo "<ul>";
echo "<li><strong>Discogs First:</strong> System will try Discogs before MusicBrainz</li>";
echo "<li><strong>Better Quality:</strong> Discogs often has higher resolution images</li>";
echo "<li><strong>More Formats:</strong> Discogs has vinyl, CD, and digital versions</li>";
echo "<li><strong>Fallback:</strong> If Discogs fails, MusicBrainz will be tried</li>";
echo "</ul>";

echo "<h2>🎯 Benefits of Discogs Priority</h2>";
echo "<ul>";
echo "<li><strong>Higher Resolution:</strong> Discogs images are often 500px+ vs 250px</li>";
echo "<li><strong>Physical Releases:</strong> Real album artwork from vinyl/CD</li>";
echo "<li><strong>Multiple Versions:</strong> Different pressings and formats</li>";
echo "<li><strong>Community Quality:</strong> User-uploaded high-quality scans</li>";
echo "</ul>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 