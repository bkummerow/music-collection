<?php
/**
 * Image Proxy API
 * Fetches images from external sources (like Discogs) and serves them locally
 * to avoid rate limiting and CORS issues
 */

header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$imageUrl = $_GET['url'] ?? null;

if (!$imageUrl) {
    http_response_code(400);
    echo json_encode(['error' => 'URL parameter is required']);
    exit;
}

// Validate URL
if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Only allow images from trusted sources
$allowedDomains = ['discogs.com', 'i.discogs.com', 'img.discogs.com'];
$parsedUrl = parse_url($imageUrl);
$domain = $parsedUrl['host'] ?? '';

if (!in_array($domain, $allowedDomains)) {
    http_response_code(403);
    echo json_encode(['error' => 'Domain not allowed']);
    exit;
}

try {
    // Set up cURL to fetch the image
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $imageUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MusicCollection/1.0)',
        CURLOPT_HTTPHEADER => [
            'Accept: image/*',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache'
        ]
    ]);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP error: $httpCode");
    }
    
    if (!$imageData) {
        throw new Exception('No image data received');
    }
    
    // Validate that we got an image
    if (!preg_match('/^image\//', $contentType)) {
        throw new Exception('Invalid content type: ' . $contentType);
    }
    
    // Set appropriate headers for the image
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: public, max-age=86400'); // Cache for 24 hours
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
    
    // Output the image data
    echo $imageData;
    
} catch (Exception $e) {
    error_log('Image proxy error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch image: ' . $e->getMessage()]);
}
?>
