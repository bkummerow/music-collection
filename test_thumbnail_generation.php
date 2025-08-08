<?php
/**
 * Test Thumbnail Generation
 * Shows what happens when we try to optimize null/empty cover URLs
 */

require_once __DIR__ . '/services/ImageOptimizationService.php';
require_once __DIR__ . '/models/MusicCollection.php';

echo "<h2>Testing Thumbnail Generation</h2>";

// Test with null URL
echo "<h3>Test 1: Null URL</h3>";
$nullUrl = null;
$thumbnailUrl = ImageOptimizationService::getThumbnailUrl($nullUrl);
echo "<p><strong>Input:</strong> null</p>";
echo "<p><strong>Output:</strong> " . ($thumbnailUrl ?? 'NULL') . "</p>";

// Test with empty URL
echo "<h3>Test 2: Empty URL</h3>";
$emptyUrl = '';
$thumbnailUrl = ImageOptimizationService::getThumbnailUrl($emptyUrl);
echo "<p><strong>Input:</strong> ''</p>";
echo "<p><strong>Output:</strong> " . ($thumbnailUrl ?? 'NULL') . "</p>";

// Test with a real Discogs URL
echo "<h3>Test 3: Real Discogs URL</h3>";
$realUrl = 'https://i.discogs.com/XWCVClRJbtZzzpy04FyQvEYmx47VUtcSn28pBeIhAcY/rs:fit/g:sm/q:90/h:598/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTQxNzI4/Mi0xNDM2MTk1NTIz/LTk4MjcuanBlZw.jpeg';
$thumbnailUrl = ImageOptimizationService::getThumbnailUrl($realUrl);
echo "<p><strong>Input:</strong> " . htmlspecialchars($realUrl) . "</p>";
echo "<p><strong>Output:</strong> " . htmlspecialchars($thumbnailUrl) . "</p>";

// Test the MusicCollection model
echo "<h3>Test 4: MusicCollection Model</h3>";
$musicCollection = new MusicCollection();
$albums = $musicCollection->getAllAlbums();

echo "<p><strong>Total albums:</strong> " . count($albums) . "</p>";

foreach ($albums as $album) {
    echo "<h4>Album: " . htmlspecialchars($album['artist_name']) . " - " . htmlspecialchars($album['album_name']) . "</h4>";
    echo "<p><strong>Original cover_url:</strong> " . ($album['cover_url'] ?? 'NULL') . "</p>";
    echo "<p><strong>Optimized cover_url:</strong> " . ($album['cover_url'] ?? 'NULL') . "</p>";
    echo "<p><strong>Medium URL:</strong> " . ($album['cover_url_medium'] ?? 'NULL') . "</p>";
    echo "<p><strong>Large URL:</strong> " . ($album['cover_url_large'] ?? 'NULL') . "</p>";
    echo "<hr>";
}

echo "<h2>Conclusion</h2>";
echo "<p>The issue is that albums without cover URLs are returning null, which causes the frontend to try to load images from null URLs, resulting in 'Forbidden' errors.</p>";
echo "<p>We need to handle null/empty cover URLs in the frontend JavaScript.</p>";
?>
