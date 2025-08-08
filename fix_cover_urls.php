<?php
/**
 * Fix Cover URLs Script
 * Converts optimized Discogs URLs back to original URLs in the database
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/ImageOptimizationService.php';

// Get database connection
$connection = getDBConnection();

// Get all albums with cover URLs
$sql = "SELECT id, cover_url FROM music_collection WHERE cover_url IS NOT NULL AND cover_url != ''";
$albums = $connection->query($sql);

echo "<h2>Cover URL Analysis</h2>";

$updatedCount = 0;
$skippedCount = 0;

foreach ($albums as $album) {
    $originalUrl = $album['cover_url'];
    $albumId = $album['id'];
    
    echo "<h3>Album ID: $albumId</h3>";
    echo "<p><strong>Current URL:</strong> " . htmlspecialchars($originalUrl) . "</p>";
    
    // Check if this is an optimized URL (contains /h:60/w:60/ or similar)
    if (preg_match('/\/h:\d+\/w:\d+\//', $originalUrl)) {
        echo "<p><strong>Status:</strong> <span style='color: orange;'>Optimized URL detected</span></p>";
        
        // Try to convert back to original URL by removing the size parameters
        $originalDiscogsUrl = preg_replace('/\/rs:fit\/g:sm\/q:\d+\/h:\d+\/w:\d+\//', '/', $originalUrl);
        
        // Remove any query parameters that might have been added
        $originalDiscogsUrl = preg_replace('/\?.*$/', '', $originalDiscogsUrl);
        
        echo "<p><strong>Converted to:</strong> " . htmlspecialchars($originalDiscogsUrl) . "</p>";
        
        // Test if the original URL is valid
        $testOptimized = ImageOptimizationService::getThumbnailUrl($originalDiscogsUrl);
        echo "<p><strong>Test optimized:</strong> " . htmlspecialchars($testOptimized) . "</p>";
        
        // Update the database
        $updateSql = "UPDATE music_collection SET cover_url = ? WHERE id = ?";
        $result = $connection->query($updateSql, [$originalDiscogsUrl, $albumId]);
        
        if ($result) {
            echo "<p><strong>Status:</strong> <span style='color: green;'>Updated successfully</span></p>";
            $updatedCount++;
        } else {
            echo "<p><strong>Status:</strong> <span style='color: red;'>Update failed</span></p>";
        }
    } else {
        echo "<p><strong>Status:</strong> <span style='color: blue;'>Original URL (no change needed)</span></p>";
        $skippedCount++;
    }
    
    echo "<hr>";
}

echo "<h2>Summary</h2>";
echo "<p><strong>Updated:</strong> $updatedCount albums</p>";
echo "<p><strong>Skipped:</strong> $skippedCount albums</p>";
echo "<p><strong>Total processed:</strong> " . ($updatedCount + $skippedCount) . " albums</p>";

echo "<h2>Test Results</h2>";
echo "<p>After running this script, the thumbnails should now display correctly as 60x60 images.</p>";
?>
