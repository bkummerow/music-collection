<?php
/**
 * Debug Ministry - Stigmata Cover Art Issue
 */

require_once __DIR__ . '/services/MusicBrainzAPIService.php';
require_once __DIR__ . '/services/DiscogsAPIService.php';

echo "<h1>🔍 Debug: Ministry - Stigmata Cover Art</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();
$discogsAPI = new DiscogsAPIService();

$artistName = 'Ministry';
$albumName = 'Stigmata';

echo "<h2>📊 API Availability</h2>";
echo "<p><strong>Discogs API:</strong> " . ($discogsAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";
echo "<p><strong>MusicBrainz API:</strong> " . ($musicBrainzAPI->isAvailable() ? '✅ Available' : '❌ Not Available') . "</p>";

if (!$discogsAPI->isAvailable()) {
    echo "<p>❌ Discogs API not available. Please configure your API key.</p>";
    exit;
}

echo "<h2>🔍 Step-by-Step Debug</h2>";

// Step 1: Test Discogs search
echo "<h3>Step 1: Discogs Artist Search</h3>";
try {
    $discogsAlbums = $discogsAPI->searchAlbumsByArtist($artistName, $albumName, 5);
    echo "<p><strong>Found " . count($discogsAlbums) . " albums:</strong></p>";
    
    if (!empty($discogsAlbums)) {
        foreach ($discogsAlbums as $index => $album) {
            $coverStatus = $album['cover_url'] ? '✅' : '❌';
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
            echo "<p><strong>{$coverStatus} Album " . ($index + 1) . ":</strong></p>";
            echo "<p><strong>Title:</strong> " . htmlspecialchars($album['title']) . "</p>";
            echo "<p><strong>Artist:</strong> " . htmlspecialchars($album['artist']) . "</p>";
            echo "<p><strong>Year:</strong> " . htmlspecialchars($album['year'] ?? 'Unknown') . "</p>";
            echo "<p><strong>Cover URL:</strong> " . ($album['cover_url'] ? htmlspecialchars($album['cover_url']) : 'None') . "</p>";
            
            if ($album['cover_url']) {
                echo "<p><img src='" . htmlspecialchars($album['cover_url']) . "' style='width: 150px; height: 150px; object-fit: cover; border-radius: 5px;'></p>";
            }
            echo "</div>";
        }
    } else {
        echo "<p>❌ No Discogs results found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Discogs Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 2: Test MusicBrainz search
echo "<h3>Step 2: MusicBrainz Search</h3>";
try {
    $musicBrainzAlbums = $musicBrainzAPI->searchAlbumsByArtist($artistName, $albumName, 5);
    echo "<p><strong>Found " . count($musicBrainzAlbums) . " albums:</strong></p>";
    
    if (!empty($musicBrainzAlbums)) {
        foreach ($musicBrainzAlbums as $index => $album) {
            $coverStatus = $album['cover_url'] ? '✅' : '❌';
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
            echo "<p><strong>{$coverStatus} Album " . ($index + 1) . ":</strong></p>";
            echo "<p><strong>Title:</strong> " . htmlspecialchars($album['title']) . "</p>";
            echo "<p><strong>Artist:</strong> " . htmlspecialchars($album['artist']) . "</p>";
            echo "<p><strong>Year:</strong> " . htmlspecialchars($album['year'] ?? 'Unknown') . "</p>";
            echo "<p><strong>Cover URL:</strong> " . ($album['cover_url'] ? htmlspecialchars($album['cover_url']) : 'None') . "</p>";
            
            if ($album['cover_url']) {
                echo "<p><img src='" . htmlspecialchars($album['cover_url']) . "' style='width: 150px; height: 150px; object-fit: cover; border-radius: 5px;'></p>";
            }
            echo "</div>";
        }
    } else {
        echo "<p>❌ No MusicBrainz results found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ MusicBrainz Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 3: Test the integrated cover art function
echo "<h3>Step 3: Integrated Cover Art Test</h3>";
try {
    // Simulate what happens when adding an album
    $albums = $musicBrainzAPI->searchAlbumsByArtist($artistName, $albumName, 1);
    if (!empty($albums)) {
        $album = $albums[0];
        $coverUrl = $album['cover_url'] ?? null;
        
        echo "<p><strong>Selected Album:</strong> " . htmlspecialchars($album['title']) . "</p>";
        echo "<p><strong>Cover URL:</strong> " . ($coverUrl ? htmlspecialchars($coverUrl) : 'None') . "</p>";
        
        if ($coverUrl) {
            echo "<p><img src='" . htmlspecialchars($coverUrl) . "' style='width: 200px; height: 200px; object-fit: cover; border-radius: 5px;'></p>";
        }
    } else {
        echo "<p>❌ No albums found for integrated test</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Integrated Test Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 4: Test different search variations
echo "<h3>Step 4: Search Variations</h3>";
$searchVariations = [
    ['artist' => 'Ministry', 'album' => 'Stigmata'],
    ['artist' => 'Ministry', 'album' => 'stigmata'],
    ['artist' => 'MINISTRY', 'album' => 'STIGMATA'],
    ['artist' => 'Ministry', 'album' => 'Stigmata EP'],
    ['artist' => 'Ministry', 'album' => 'Stigmata (EP)']
];

foreach ($searchVariations as $variation) {
    echo "<h4>Testing: {$variation['artist']} - {$variation['album']}</h4>";
    
    try {
        $discogsResults = $discogsAPI->searchAlbumsByArtist($variation['artist'], $variation['album'], 1);
        $musicBrainzResults = $musicBrainzAPI->searchAlbumsByArtist($variation['artist'], $variation['album'], 1);
        
        echo "<p><strong>Discogs:</strong> " . (empty($discogsResults) ? 'No results' : 'Found ' . count($discogsResults)) . "</p>";
        echo "<p><strong>MusicBrainz:</strong> " . (empty($musicBrainzResults) ? 'No results' : 'Found ' . count($musicBrainzResults)) . "</p>";
        
        if (!empty($discogsResults)) {
            $cover = $discogsResults[0]['cover_url'] ? '✅' : '❌';
            echo "<p>{$cover} Discogs cover available</p>";
        }
        
        if (!empty($musicBrainzResults)) {
            $cover = $musicBrainzResults[0]['cover_url'] ? '✅' : '❌';
            echo "<p>{$cover} MusicBrainz cover available</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<h2>🔧 Possible Issues</h2>";
echo "<ul>";
echo "<li><strong>Album Name:</strong> 'Stigmata' might be listed as 'Stigmata EP' or similar</li>";
echo "<li><strong>Artist Name:</strong> Might be listed differently in Discogs</li>";
echo "<li><strong>Release Type:</strong> Could be an EP, single, or compilation</li>";
echo "<li><strong>Cover Art:</strong> Might not have cover art uploaded to Discogs</li>";
echo "<li><strong>API Limits:</strong> Discogs might have rate limiting</li>";
echo "</ul>";

echo "<h2>💡 Recommendations</h2>";
echo "<ul>";
echo "<li>Try searching for 'Stigmata EP' instead of just 'Stigmata'</li>";
echo "<li>Check if it's listed under a different artist name</li>";
echo "<li>Verify the exact release title in Discogs database</li>";
echo "<li>Consider that some releases might not have cover art</li>";
echo "</ul>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 