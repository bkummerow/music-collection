<?php
/**
 * Debug The Smiths Album Data
 */

require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🔍 Debug: The Smiths Album Data</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();

echo "<h2>🎵 Testing 'The Smiths' Artist Search</h2>";

// Test artist search
$artists = $musicBrainzAPI->searchArtists('The Smiths', 5);
echo "<h3>Found Artists:</h3>";
foreach ($artists as $artist) {
    echo "- " . $artist['name'] . " (ID: " . $artist['id'] . ")<br>";
}

echo "<h2>🎼 Testing Albums by 'The Smiths'</h2>";

// Test album search
$albums = $musicBrainzAPI->searchAlbumsByArtist('The Smiths', '', 10);
echo "<h3>Found Albums:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Album Title</th><th>Release Year</th><th>Artist</th><th>Raw Date</th></tr>";

foreach ($albums as $album) {
    $year = $album['year'] ? $album['year'] : 'Unknown';
    $yearClass = $album['year'] ? 'year-available' : 'year-missing';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($album['title']) . "</td>";
    echo "<td class='$yearClass'>" . htmlspecialchars($year) . "</td>";
    echo "<td>" . htmlspecialchars($album['artist']) . "</td>";
    echo "<td>" . htmlspecialchars(json_encode($album)) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🔍 Raw API Response</h2>";
echo "<pre>";
try {
    $url = 'https://musicbrainz.org/ws/2/artist/';
    $params = [
        'query' => 'The Smiths',
        'limit' => 1,
        'fmt' => 'json'
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    echo "Artist Search URL: " . $fullUrl . "\n\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['User-Agent: MusicCollectionApp/1.0'],
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response:\n";
    echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";

echo "<style>";
echo ".year-available { background-color: #d4edda; color: #155724; }";
echo ".year-missing { background-color: #f8d7da; color: #721c24; }";
echo "</style>";

echo "<h2>🎯 Test API Endpoint</h2>";
echo "<p>Test the actual API endpoint:</p>";
echo "<a href='api/music_api.php?action=albums_by_artist&artist=The%20Smiths' target='_blank'>API Test</a>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 