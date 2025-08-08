<?php
/**
 * Simple Test: Ministry - Stigmata
 */

require_once __DIR__ . '/services/DiscogsAPIService.php';

echo "<h1>🎵 Simple Test: Ministry - Stigmata</h1>";

$discogsAPI = new DiscogsAPIService();

echo "<h2>📊 API Status</h2>";
echo "<p><strong>Discogs Available:</strong> " . ($discogsAPI->isAvailable() ? '✅ Yes' : '❌ No') . "</p>";

if (!$discogsAPI->isAvailable()) {
    echo "<p>❌ Discogs API not configured. Please check your API key.</p>";
    exit;
}

echo "<h2>🔍 Testing Different Search Terms</h2>";

$searchTerms = [
    'Ministry Stigmata',
    'Ministry Stigmata EP',
    'Stigmata Ministry',
    'Ministry',
    'Stigmata'
];

foreach ($searchTerms as $term) {
    echo "<h3>Searching: '$term'</h3>";
    
    try {
        $results = $discogsAPI->searchAlbumsByArtist('Ministry', 'Stigmata', 3);
        
        if (!empty($results)) {
            echo "<p>✅ Found " . count($results) . " results:</p>";
            foreach ($results as $index => $album) {
                $coverStatus = $album['cover_url'] ? '✅' : '❌';
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px 0;'>";
                echo "<p><strong>{$coverStatus} Result " . ($index + 1) . ":</strong></p>";
                echo "<p><strong>Title:</strong> " . htmlspecialchars($album['title']) . "</p>";
                echo "<p><strong>Artist:</strong> " . htmlspecialchars($album['artist']) . "</p>";
                echo "<p><strong>Year:</strong> " . htmlspecialchars($album['year'] ?? 'Unknown') . "</p>";
                echo "<p><strong>Cover:</strong> " . ($album['cover_url'] ? 'Available' : 'None') . "</p>";
                
                if ($album['cover_url']) {
                    echo "<p><img src='" . htmlspecialchars($album['cover_url']) . "' style='width: 100px; height: 100px; object-fit: cover; border-radius: 5px;'></p>";
                }
                echo "</div>";
            }
        } else {
            echo "<p>❌ No results found</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>💡 Notes</h2>";
echo "<ul>";
echo "<li>Ministry's 'Stigmata' is an EP, not a full album</li>";
echo "<li>It was released in 1986</li>";
echo "<li>It might be listed as 'Stigmata EP' in Discogs</li>";
echo "<li>Some releases might not have cover art uploaded</li>";
echo "</ul>";

echo "<p><a href='debug_ministry_stigmata.php'>🔍 Run Full Debug</a></p>";
echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 