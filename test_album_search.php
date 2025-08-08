<?php
/**
 * Test Album Search Filtering
 * Verifies that album autocomplete only shows albums by the selected artist
 */

require_once __DIR__ . '/services/MusicBrainzAPIService.php';
require_once __DIR__ . '/models/MusicCollection.php';

echo "<h1>🔍 Test Album Search Filtering</h1>";
echo "<p>Testing that album autocomplete only shows albums by the selected artist</p>";

$musicBrainzAPI = new MusicBrainzAPIService();
$musicCollection = new MusicCollection();

if (!$musicBrainzAPI->isAvailable()) {
    echo "<p>❌ MusicBrainz API is not available.</p>";
    exit;
}

// Test cases
$testCases = [
    [
        'artist' => 'Earth',
        'album_search' => 'Earth 2',
        'expected_behavior' => 'Should only show albums by "Earth" band, not "Earth Wind and Fire"'
    ],
    [
        'artist' => 'Pink Floyd',
        'album_search' => 'Dark',
        'expected_behavior' => 'Should only show Pink Floyd albums with "Dark" in the name'
    ],
    [
        'artist' => 'The Smiths',
        'album_search' => 'Smiths',
        'expected_behavior' => 'Should only show Smiths albums with "Smiths" in the name'
    ]
];

echo "<h2>📊 Testing Album Search Results</h2>";

foreach ($testCases as $testCase) {
    $artistName = $testCase['artist'];
    $albumSearch = $testCase['album_search'];
    $expectedBehavior = $testCase['expected_behavior'];
    
    echo "<h3>Test: Artist = '$artistName', Album Search = '$albumSearch'</h3>";
    echo "<p><strong>Expected:</strong> $expectedBehavior</p>";
    
    // Test local database
    echo "<h4>Local Database Results:</h4>";
    $localAlbums = $musicCollection->getAlbumsByArtist($artistName, $albumSearch);
    if (!empty($localAlbums)) {
        echo "<ul>";
        foreach ($localAlbums as $album) {
            echo "<li>" . htmlspecialchars($album['album_name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No local albums found</p>";
    }
    
    // Test MusicBrainz API
    echo "<h4>MusicBrainz API Results:</h4>";
    try {
        $externalAlbums = $musicBrainzAPI->searchAlbumsByArtist($artistName, $albumSearch, 5);
        if (!empty($externalAlbums)) {
            echo "<ul>";
            foreach ($externalAlbums as $album) {
                $year = $album['year'] ? " ({$album['year']})" : '';
                echo "<li>" . htmlspecialchars($album['title']) . $year . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No external albums found</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>🔧 How the Fix Works</h2>";
echo "<ol>";
echo "<li><strong>Artist ID Lookup:</strong> First finds the exact artist ID for the selected artist</li>";
echo "<li><strong>Filtered Search:</strong> Uses 'arid:ARTIST_ID' to ensure only albums by that artist</li>";
echo "<li><strong>Album Name Filter:</strong> Adds 'name:\"ALBUM_SEARCH\"' to filter by album name</li>";
echo "<li><strong>Combined Query:</strong> Results in 'arid:ARTIST_ID AND name:\"ALBUM_SEARCH\"'</li>";
echo "</ol>";

echo "<h2>✅ Expected Behavior</h2>";
echo "<ul>";
echo "<li>When you select 'Earth' as artist and search 'Earth 2', it should only show albums by the 'Earth' band</li>";
echo "<li>It should NOT show albums by 'Earth Wind and Fire' or any other artist</li>";
echo "<li>The search is now properly scoped to the selected artist only</li>";
echo "</ul>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 