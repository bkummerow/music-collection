<?php
/**
 * Test Discogs API Integration
 * Verifies that Discogs API is working and can find cover art
 */

require_once __DIR__ . '/services/DiscogsAPIService.php';
require_once __DIR__ . '/services/MusicBrainzAPIService.php';

echo "<h1>🔗 Test Discogs API Integration</h1>";
echo "<p>Testing Discogs API functionality and cover art retrieval</p>";

$discogsAPI = new DiscogsAPIService();
$musicBrainzAPI = new MusicBrainzAPIService();

// Test Discogs API availability
echo "<h2>📊 API Availability</h2>";
echo "<p><strong>Discogs API:</strong> " . ($discogsAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";
echo "<p><strong>MusicBrainz API:</strong> " . ($musicBrainzAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";

if (!$discogsAPI->isAvailable()) {
    echo "<h3>🔧 Setup Instructions</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='https://www.discogs.com/settings/developers' target='_blank'>Discogs API Settings</a></li>";
    echo "<li>Click 'Generate new token'</li>";
    echo "<li>Copy the token</li>";
    echo "<li>Edit <code>config/api_config.php</code></li>";
    echo "<li>Replace <code>YOUR_DISCOGS_API_KEY_HERE</code> with your token</li>";
    echo "</ol>";
    echo "<p><strong>Note:</strong> Discogs API is free and doesn't require payment.</p>";
    exit;
}

// Test albums
$testAlbums = [
    [
        'artist' => 'Pink Floyd',
        'album' => 'The Dark Side of the Moon',
        'description' => 'Very popular album - should have cover art'
    ],
    [
        'artist' => 'The Beatles',
        'album' => 'Abbey Road',
        'description' => 'Classic album - should have cover art'
    ],
    [
        'artist' => 'Led Zeppelin',
        'album' => 'Led Zeppelin IV',
        'description' => 'Rock classic - should have cover art'
    ]
];

echo "<h2>🔍 Testing Discogs Album Search</h2>";

foreach ($testAlbums as $testAlbum) {
    $artistName = $testAlbum['artist'];
    $albumName = $testAlbum['album'];
    $description = $testAlbum['description'];
    
    echo "<h3>Test: $artistName - $albumName</h3>";
    echo "<p><strong>Description:</strong> $description</p>";
    
    try {
        // Search Discogs for the album
        $albums = $discogsAPI->searchAlbumsByArtist($artistName, $albumName, 3);
        
        if (!empty($albums)) {
            echo "<ul>";
            foreach ($albums as $album) {
                $year = $album['year'] ? " ({$album['year']})" : '';
                $cover = $album['cover_url'] ? '✅' : '❌';
                echo "<li>{$cover} " . htmlspecialchars($album['title']) . $year . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ No albums found</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>🖼️ Testing Cover Art Integration</h2>";

// Test the combined fallback system
if ($musicBrainzAPI->isAvailable()) {
    echo "<p>Testing cover art fallback system:</p>";
    echo "<ol>";
    echo "<li>1. Cover Art Archive (MusicBrainz)</li>";
    echo "<li>2. Last.fm API</li>";
    echo "<li>3. Discogs API (NEW!)</li>";
    echo "</ol>";
    
    // Test with a known album
    $testReleaseId = '12345'; // This would be a real MusicBrainz release ID
    echo "<p><strong>Note:</strong> To test the full fallback system, you would need a valid MusicBrainz release ID.</p>";
} else {
    echo "<p>❌ MusicBrainz API not available - cannot test full fallback system</p>";
}

echo "<h2>📈 Benefits of Adding Discogs</h2>";
echo "<ul>";
echo "<li><strong>More Cover Art:</strong> Discogs has extensive vinyl/CD cover art</li>";
echo "<li><strong>Better Quality:</strong> High-resolution images from physical releases</li>";
echo "<li><strong>Rare Albums:</strong> Covers for obscure or limited releases</li>";
echo "<li><strong>Multiple Formats:</strong> Different versions of the same album</li>";
echo "<li><strong>Community Driven:</strong> User-uploaded cover art</li>";
echo "</ul>";

echo "<h2>⚙️ Configuration</h2>";
echo "<p>To enable Discogs API:</p>";
echo "<ol>";
echo "<li>Get your API key from <a href='https://www.discogs.com/settings/developers' target='_blank'>Discogs</a></li>";
echo "<li>Edit <code>config/api_config.php</code></li>";
echo "<li>Replace <code>YOUR_DISCOGS_API_KEY_HERE</code> with your key</li>";
echo "<li>Save the file</li>";
echo "<li>Test this page again</li>";
echo "</ol>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 