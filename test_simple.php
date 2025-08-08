<?php
/**
 * Simple Test for Web Server
 */

echo "<h1>🧪 Simple Web Server Test</h1>";
echo "<p>Testing basic PHP functionality</p>";

// Test 1: Basic PHP
echo "<h2>✅ Test 1: PHP is working</h2>";
echo "<p>PHP version: " . phpversion() . "</p>";

// Test 2: File includes
echo "<h2>✅ Test 2: File includes</h2>";
try {
    if (file_exists(__DIR__ . '/config/api_config.php')) {
        echo "<p>✅ api_config.php exists</p>";
    } else {
        echo "<p>❌ api_config.php not found</p>";
    }
    
    if (file_exists(__DIR__ . '/services/DiscogsAPIService.php')) {
        echo "<p>✅ DiscogsAPIService.php exists</p>";
    } else {
        echo "<p>❌ DiscogsAPIService.php not found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: API Configuration
echo "<h2>✅ Test 3: API Configuration</h2>";
try {
    require_once __DIR__ . '/config/api_config.php';
    echo "<p>✅ API config loaded</p>";
    echo "<p>Discogs API Key: " . (defined('DISCOGS_API_KEY') ? 'Set' : 'Not set') . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error loading API config: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Discogs Service
echo "<h2>✅ Test 4: Discogs Service</h2>";
try {
    require_once __DIR__ . '/services/DiscogsAPIService.php';
    $discogsAPI = new DiscogsAPIService();
    echo "<p>✅ Discogs service created</p>";
    echo "<p>Discogs available: " . ($discogsAPI->isAvailable() ? 'Yes' : 'No') . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error creating Discogs service: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='index.php'>🎵 Back to Music Collection</a></p>";
?> 