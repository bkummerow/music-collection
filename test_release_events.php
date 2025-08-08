<?php
/**
 * Test Release Events Data from MusicBrainz
 */

require_once 'services/MusicBrainzAPIService.php';

echo "<h1>🎵 Test Release Events Data</h1>";

$musicBrainzAPI = new MusicBrainzAPIService();

$artist = $_GET['artist'] ?? 'The Smiths';
echo "<h2>Testing Albums by: $artist</h2>";

try {
    $albums = $musicBrainzAPI->searchAlbumsByArtist($artist, '', 5);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Album Title</th><th>Release Year</th><th>Artist</th><th>Raw Data</th></tr>";
    
    foreach ($albums as $album) {
        $year = $album['year'] ? $album['year'] : 'Unknown';
        $yearClass = $album['year'] ? 'year-available' : 'year-missing';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($album['title']) . "</td>";
        echo "<td class='$yearClass'>" . htmlspecialchars($year) . "</td>";
        echo "<td>" . htmlspecialchars($album['artist']) . "</td>";
        echo "<td><pre>" . htmlspecialchars(json_encode($album, JSON_PRETTY_PRINT)) . "</pre></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<h2>🔍 Raw API Response</h2>";
echo "<p>Testing direct API call to see release events structure:</p>";

try {
    $url = 'https://musicbrainz.org/ws/2/release/';
    $params = [
        'query' => 'arid:40f5d9e4-2de7-4f2d-ad41-e31a9a9fea27',
        'limit' => 2,
        'fmt' => 'json',
        'inc' => 'release-events'
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    echo "API URL: " . $fullUrl . "<br><br>";
    
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
    
    echo "HTTP Code: $httpCode<br>";
    echo "<pre>" . htmlspecialchars(json_encode(json_decode($response, true), JSON_PRETTY_PRINT)) . "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<style>";
echo ".year-available { background-color: #d4edda; color: #155724; }";
echo ".year-missing { background-color: #f8d7da; color: #721c24; }";
echo "</style>";

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 