<?php
/**
 * Image Proxy API
 * Fetches images from external sources (like Discogs) and serves them locally
 * to avoid rate limiting and CORS issues
 * Now includes image resizing for better performance
 */

// Check if GD extension is available for image processing
if (!extension_loaded('gd')) {
    error_log('GD extension not available for image processing');
}

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$imageUrl = $_GET['url'] ?? null;
$width = isset($_GET['w']) ? (int)$_GET['w'] : null;
$height = isset($_GET['h']) ? (int)$_GET['h'] : null;



if (!$imageUrl) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL parameter is required']);
    exit;
}

// Validate URL
if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Only allow images from trusted sources
$allowedDomains = ['discogs.com', 'i.discogs.com', 'img.discogs.com'];
$parsedUrl = parse_url($imageUrl);
$domain = $parsedUrl['host'] ?? '';

if (!in_array($domain, $allowedDomains)) {
    http_response_code(403);
    header('Content-Type: application/json');
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Referer: https://www.discogs.com/',
            'Sec-Fetch-Dest: image',
            'Sec-Fetch-Mode: no-cors',
            'Sec-Fetch-Site: same-site'
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
    
    // Resize image if dimensions are specified and GD is available
    if (($width || $height) && extension_loaded('gd')) {
        $imageData = resizeImage($imageData, $contentType, $width, $height);
        $contentType = 'image/jpeg'; // Convert to JPEG for better compression
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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to fetch image: ' . $e->getMessage()]);
}

/**
 * Resize image using GD library
 */
function resizeImage($imageData, $contentType, $targetWidth, $targetHeight) {
    // Create image resource from data
    $image = imagecreatefromstring($imageData);
    if (!$image) {
        return $imageData; // Return original if we can't process it
    }
    
    // Get original dimensions
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    
    // Calculate new dimensions maintaining aspect ratio
    if ($targetWidth && $targetHeight) {
        // Both dimensions specified - fit within bounds
        $ratio = min($targetWidth / $originalWidth, $targetHeight / $originalHeight);
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
    } elseif ($targetWidth) {
        // Only width specified
        $ratio = $targetWidth / $originalWidth;
        $newWidth = $targetWidth;
        $newHeight = round($originalHeight * $ratio);
    } elseif ($targetHeight) {
        // Only height specified
        $ratio = $targetHeight / $originalHeight;
        $newWidth = round($originalWidth * $ratio);
        $newHeight = $targetHeight;
    } else {
        // No dimensions specified
        return $imageData;
    }
    
    // Create new image with target dimensions
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG images
    if (strpos($contentType, 'png') !== false) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize the image
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Output to buffer
    ob_start();
    imagejpeg($newImage, null, 85); // 85% quality for good balance
    $resizedData = ob_get_contents();
    ob_end_clean();
    
    // Clean up
    imagedestroy($image);
    imagedestroy($newImage);
    
    return $resizedData;
}
?>
