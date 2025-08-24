<?php
/**
 * Music Collection API
 * Handles AJAX requests for the music collection application
 */

// Start output buffering to prevent any output before headers
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Set proper caching headers - no cache for API responses to ensure fresh data
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Pragma: no-cache');

require_once __DIR__ . '/../models/MusicCollection.php';
require_once __DIR__ . '/../services/DiscogsAPIService.php';
require_once __DIR__ . '/../config/auth_config.php';

// Ensure session is started with proper configuration
ensureSessionStarted();

/**
 * Convert boolean values to integers for database consistency
 */
if (!function_exists('normalizeBoolean')) {
    function normalizeBoolean($value) {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if ($value === true || $value === 'true' || $value === '1') {
            return 1;
        }
        if ($value === false || $value === 'false' || $value === '0') {
            return 0;
        }
        return $value ? 1 : 0;
    }
}

$musicCollection = new MusicCollection();
$discogsAPI = new DiscogsAPIService(); // Keep original initialization
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get action from appropriate source based on method
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
    } else {
        // For POST requests, try to get action from multiple sources
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            // Fallback to $_POST if php://input is empty
            $input = $_POST;
        }
        // Check if action is in GET (for mixed GET/POST requests like login)
        $action = $_GET['action'] ?? $input['action'] ?? '';
        
        // If we still don't have input data, try to get it from the raw input
        if (empty($input) && $method === 'POST') {
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)) {
                $input = json_decode($rawInput, true);
            }
        }
    }
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'albums':
                    $filter = $_GET['filter'] ?? null;
                    $search = $_GET['search'] ?? '';
                    $albums = $musicCollection->getAllAlbums($filter, $search);
                    
                    // For now, use release year as master year to ensure fast loading
                    // Master years will be fetched asynchronously by the frontend
                    foreach ($albums as &$album) {
                        $album['master_year'] = $album['release_year'];
                    }
                    
                    $response['data'] = $albums;
                    $response['success'] = true;
                    break;
                    
                case 'album':
                    $id = $_GET['id'] ?? null;
                    if ($id) {
                        $album = $musicCollection->getAlbumById($id);
                        if ($album) {
                            // Ensure consistent structure with other album endpoints
                            $response['data'] = [
                                'id' => $album['id'],
                                'artist_name' => $album['artist_name'],
                                'album_name' => $album['album_name'],
                                'release_year' => $album['release_year'],
                                'is_owned' => $album['is_owned'],
                                'want_to_own' => $album['want_to_own'],
                                'cover_url' => $album['cover_url'] ?? null,
                                'cover_url_medium' => $album['cover_url_medium'] ?? $album['cover_url'] ?? null,
                                'cover_url_large' => $album['cover_url_large'] ?? $album['cover_url'] ?? null,
                                'discogs_release_id' => $album['discogs_release_id'],
                                'tracklist' => $album['tracklist'] ?? null
                            ];
                            $response['success'] = true;
                        } else {
                            $response['message'] = 'Album not found';
                        }
                    } else {
                        $response['message'] = 'Album ID required';
                    }
                    break;
                    
                case 'stats':
                    $response['data'] = $musicCollection->getStats();
                    $response['success'] = true;
                    break;
                    

                    
                case 'search_discogs':
                    $artist = $_GET['artist'] ?? '';
                    $album = $_GET['album'] ?? '';
                    $limit = (int)($_GET['limit'] ?? 8);
                    
                    if ($artist && $album) {
                        try {
                            // Initialize DiscogsAPI only when needed
                            if ($discogsAPI === null) {
                                $discogsAPI = new DiscogsAPIService();
                            }
                            $results = $discogsAPI->searchAlbumsByArtist($artist, $album, $limit);
                            $response['data'] = $results;
                            $response['success'] = true;
                        } catch (Exception $e) {
                            $response['message'] = 'Discogs search failed: ' . $e->getMessage();
                        }
                    } else {
                        $response['message'] = 'Artist and album name required';
                    }
                    break;
                    
                case 'master_year':
                    $releaseId = $_GET['release_id'] ?? '';
                    
                    if ($releaseId) {
                        try {
                            // Initialize DiscogsAPI only when needed
                            if ($discogsAPI === null) {
                                $discogsAPI = new DiscogsAPIService();
                            }
                            $masterYear = $discogsAPI->getMasterYear($releaseId);
                            if ($masterYear) {
                                $response['data'] = ['master_year' => $masterYear];
                                $response['success'] = true;
                            } else {
                                $response['message'] = 'Master year not available';
                            }
                        } catch (Exception $e) {
                            $response['message'] = 'Failed to fetch master year: ' . $e->getMessage();
                        }
                    } else {
                        $response['message'] = 'Release ID required';
                    }
                    break;
                    
                case 'artists':
                    $search = $_GET['search'] ?? '';
                    
                    // Get local artists first
                    $localArtists = $musicCollection->getArtists($search);
                    
                    // Try to get additional artists from Discogs API
                    $externalArtists = [];
                    // Initialize DiscogsAPI only when needed
                    if ($discogsAPI === null) {
                        $discogsAPI = new DiscogsAPIService();
                    }
                    if ($discogsAPI->isAvailable()) {
                        $externalArtists = $discogsAPI->searchArtists($search, 10);
                    }
                    
                    // Combine local and external results, prioritizing local
                    $allArtists = [];
                    $seenArtists = [];
                    
                    // Add local artists first
                    foreach ($localArtists as $artist) {
                        $allArtists[] = $artist;
                        $seenArtists[strtolower($artist['artist_name'])] = true;
                    }
                    
                    // Add external artists that aren't already in local collection
                    foreach ($externalArtists as $artist) {
                        $artistName = $artist['artist_name'];
                        if (!isset($seenArtists[strtolower($artistName)])) {
                            $allArtists[] = ['artist_name' => $artistName];
                            $seenArtists[strtolower($artistName)] = true;
                        }
                    }
                    
                    $response['data'] = $allArtists;
                    $response['success'] = true;
                    break;
                    
                case 'albums_by_artist':
                    $artist = $_GET['artist'] ?? '';
                    $search = $_GET['search'] ?? '';
                    $format = $_GET['format'] ?? '';
                    
                    if ($artist) {
                        // Get local albums first
                        $localAlbums = $musicCollection->getAlbumsByArtist($artist, $search);
                        
                        // Try to get additional albums from Discogs API
                        $externalAlbums = [];
                        if ($discogsAPI->isAvailable()) {
                            $externalAlbums = $discogsAPI->searchAlbumsByArtist($artist, $search, 99);
                            
                            // If strict search returns no results, try direct search as fallback
                            if (empty($externalAlbums) && !empty($search)) {
                                $externalAlbums = $discogsAPI->performDirectSearch("$artist $search", 99, $search);
                            }
                        }
                        

                        
                                                // Filter external albums by format if specified
                        if (!empty($format)) {
                            $filteredExternalAlbums = [];
                            foreach ($externalAlbums as $album) {
                                $albumFormat = $album['format'] ?? '';
                                
                                // Handle both string and array formats
                                if (is_array($albumFormat)) {
                                    $albumFormat = implode(', ', $albumFormat);
                                }
                                
                                // Ensure it's a string before using stripos
                                if (is_string($albumFormat) && stripos($albumFormat, $format) !== false) {
                                    $filteredExternalAlbums[] = $album;
                                }
                            }
                            $externalAlbums = $filteredExternalAlbums;
                        }
                        
                        // Combine local and external results, prioritizing local
                        $allAlbums = [];
                        $seenAlbums = [];
                        
                        // Add local albums first
                        foreach ($localAlbums as $album) {
                            // For local albums, use the stored release_year as master_year since we store master years
                            $masterYear = $album['release_year'] ?? null;
                            
                            // Ensure consistent structure with external albums
                            $allAlbums[] = [
                                'album_name' => $album['album_name'] ?? $album['title'] ?? 'Unknown Album',
                                'year' => $album['release_year'] ?? null,
                                'artist' => $artist,
                                'master_year' => $masterYear, // Use stored master year for local albums
                                'cover_url' => $album['cover_url'] ?? null,
                                'cover_url_medium' => $album['cover_url_medium'] ?? $album['cover_url'] ?? null,
                                'cover_url_large' => $album['cover_url_large'] ?? $album['cover_url'] ?? null,
                                'id' => $album['discogs_release_id'] ?? null
                            ];
                            $seenAlbums[strtolower($album['album_name'] ?? $album['title'] ?? 'unknown album')] = true;
                        }
                        
                        // Add external albums that aren't already in local collection
                        $filteredCount = 0;
                        foreach ($externalAlbums as $album) {
                            $albumName = $album['title']; // This is already cleaned by DiscogsAPIService
                            $year = $album['year'] ?? null;
                            
                            // Create a unique key that includes year and release ID to allow same-named albums with different years and releases
                            $uniqueKey = strtolower($albumName) . '_' . ($year ?? 'unknown') . '_' . ($album['id'] ?? 'unknown');
                            
                            if (!isset($seenAlbums[$uniqueKey])) {
                                // Skip master year fetching for autocomplete performance
                                $masterYear = $album['master_year'] ?? null;
                                
                                $allAlbums[] = [
                                    'album_name' => $albumName,
                                    'year' => $year,
                                    'artist' => $album['artist'] ?? $artist,
                                    'master_year' => $masterYear,
                                    'format' => $album['format'] ?? null,
                                    'cover_url' => $album['cover_url'] ?? null,
                                    'cover_url_medium' => $album['cover_url_medium'] ?? $album['cover_url'] ?? null,
                                    'cover_url_large' => $album['cover_url_large'] ?? $album['cover_url'] ?? null,
                                    'id' => $album['id'] ?? null // Include the Discogs release ID
                                ];
                                $seenAlbums[$uniqueKey] = true;
                            } else {
                                $filteredCount++;
                            }
                        }
                        

                        
                        $response['data'] = $allAlbums;
                        $response['success'] = true;
                    } else {
                        $response['message'] = 'Artist name required';
                    }
                    break;
                    
                case 'auth_check':
                    $isAuthenticated = AuthHelper::isAuthenticated();
                    $response['success'] = true;
                    $response['data'] = [
                        'authenticated' => $isAuthenticated,
                        'session_id' => session_id()
                    ];
                    break;
                    
                case 'get_setup_status':
                    // Get current API key (masked for security)
                    $currentApiKey = '';
                    $configFile = __DIR__ . '/../config/api_config.php';
                    if (file_exists($configFile)) {
                        $configContent = file_get_contents($configFile);
                        if (preg_match("/define\('DISCOGS_API_KEY',\s*'([^']*)'\);\s*/", $configContent, $matches)) {
                            $currentApiKey = $matches[1];
                            // Mask the API key for display (show first 4 and last 4 characters)
                            if (strlen($currentApiKey) > 8) {
                                $currentApiKey = substr($currentApiKey, 0, 4) . '...' . substr($currentApiKey, -4);
                            } else {
                                $currentApiKey = 'Not set';
                            }
                        }
                    }
                    
                    // Check if password is set
                    $passwordSet = false;
                    $authFile = __DIR__ . '/../config/auth_config.php';
                    if (file_exists($authFile)) {
                        $authContent = file_get_contents($authFile);
                        if (preg_match("/define\('ADMIN_PASSWORD_HASH',\s*'([^']*)'\);\s*/", $authContent, $matches)) {
                            $passwordSet = !empty($matches[1]) && $matches[1] !== 'YOUR_PASSWORD_HASH_HERE';
                        }
                    }
                    
                    $apiKeySet = !empty($currentApiKey) && $currentApiKey !== 'Not set';
                    $setupComplete = $apiKeySet && $passwordSet;
                    
                    $response['success'] = true;
                    $response['data'] = [
                        'api_key_set' => $apiKeySet,
                        'password_set' => $passwordSet,
                        'setup_complete' => $setupComplete,
                        'current_api_key' => $currentApiKey
                    ];
                    break;
                    
                default:
                    $response['message'] = 'Invalid action';
            }
            break;
            
        case 'POST':
            
            switch ($action) {
                case 'add':
                    // Check authentication
                    if (!AuthHelper::isAuthenticated()) {
                        $response['message'] = 'Authentication required';
                        $response['auth_required'] = true;
                        echo json_encode($response);
                        exit;
                    }
                    
                    if (isset($input['artist_name']) && isset($input['album_name'])) {
                        try {
                            // Use provided cover art URL and Discogs release ID if available
                            $coverUrl = $input['cover_url'] ?? null;
                            $discogsReleaseId = $input['discogs_release_id'] ?? null;
                            $style = $input['style'] ?? null;
                            
                            // Initialize DiscogsAPI only when needed
                            if ($discogsAPI === null) {
                                $discogsAPI = new DiscogsAPIService();
                            }
                            
                            // If we don't have a stored release ID, try to fetch it
                            if (!$discogsReleaseId && $discogsAPI->isAvailable()) {
                                $albums = $discogsAPI->searchAlbumsByArtistForStorage($input['artist_name'], $input['album_name'], 1);
                                if (!empty($albums)) {
                                    $coverUrl = $albums[0]['cover_url'] ?? null;
                                    $discogsReleaseId = $albums[0]['id'] ?? null;
                                }
                            }
                            
                            // If we have a Discogs release ID but no style, try to fetch style information
                            if ($discogsReleaseId && !$style && $discogsAPI->isAvailable()) {
                                $releaseInfo = $discogsAPI->getReleaseInfo($discogsReleaseId);
                                if ($releaseInfo && !empty($releaseInfo['style'])) {
                                    $style = $releaseInfo['style'];
                                }
                            }
                            
                            $result = $musicCollection->addAlbum(
                                $input['artist_name'],
                                $input['album_name'],
                                $input['release_year'] ?? null,
                                normalizeBoolean($input['is_owned'] ?? false),
                                normalizeBoolean($input['want_to_own'] ?? false),
                                $coverUrl,
                                $discogsReleaseId,
                                $style
                            );
                            $response['success'] = $result;
                            $response['message'] = $result ? 'Album added successfully' : 'Failed to add album';
                        } catch (Exception $e) {
                            $response['success'] = false;
                            $response['message'] = $e->getMessage();
                        }
                    } else {
                        $response['message'] = 'Artist name and album name are required';
                    }
                    break;
                    
                case 'update':
                    // Check authentication
                    if (!AuthHelper::isAuthenticated()) {
                        $response['message'] = 'Authentication required';
                        $response['auth_required'] = true;
                        echo json_encode($response);
                        exit;
                    }
                    
                    if (isset($input['id']) && isset($input['artist_name']) && isset($input['album_name'])) {
                        try {
                            // Use provided cover art URL and Discogs release ID if available
                            $coverUrl = $input['cover_url'] ?? null;
                            $discogsReleaseId = $input['discogs_release_id'] ?? null;
                            $style = $input['style'] ?? null;
                            
                            // Try to fetch cover art from Discogs if not provided
                            if (!$discogsReleaseId && $discogsAPI->isAvailable()) {
                                try {
                                    $albums = $discogsAPI->searchAlbumsByArtistForStorage($input['artist_name'], $input['album_name'], 1);
                                    if (!empty($albums)) {
                                        $coverUrl = $albums[0]['cover_url'] ?? null;
                                        $discogsReleaseId = $albums[0]['id'] ?? null;
                                    }
                                } catch (Exception $discogsError) {
                                    // Discogs API error during update, continue with update
                                }
                            }
                            
                            // If we have a Discogs release ID but no style, try to fetch style information
                            if ($discogsReleaseId && !$style && $discogsAPI->isAvailable()) {
                                try {
                                    $releaseInfo = $discogsAPI->getReleaseInfo($discogsReleaseId);
                                    if ($releaseInfo && !empty($releaseInfo['style'])) {
                                        $style = $releaseInfo['style'];
                                    }
                                } catch (Exception $discogsError) {
                                    // Discogs API error fetching style, continue with update
                                }
                            }
                            
                            $result = $musicCollection->updateAlbum(
                                $input['id'],
                                $input['artist_name'],
                                $input['album_name'],
                                $input['release_year'] ?? null,
                                normalizeBoolean($input['is_owned'] ?? false),
                                normalizeBoolean($input['want_to_own'] ?? false),
                                $coverUrl,
                                $discogsReleaseId,
                                $style
                            );
                            $response['success'] = $result;
                            $response['message'] = $result ? 'Album updated successfully' : 'Failed to update album';
                        } catch (Exception $e) {
                            $response['success'] = false;
                            $response['message'] = $e->getMessage();
                        }
                    } else {
                        $response['message'] = 'ID, artist name, and album name are required';
                    }
                    break;
                    
                case 'delete':
                    // Check authentication
                    if (!AuthHelper::isAuthenticated()) {
                        $response['message'] = 'Authentication required';
                        $response['auth_required'] = true;
                        echo json_encode($response);
                        exit;
                    }
                    
                    if (isset($input['id'])) {
                        $result = $musicCollection->deleteAlbum($input['id']);
                        $response['success'] = $result;
                        $response['message'] = $result ? 'Album deleted successfully' : 'Failed to delete album';
                    } else {
                        $response['message'] = 'Album ID required';
                    }
                    break;
                    
                case 'login':
                    if (isset($input['password'])) {
                        $authResult = AuthHelper::authenticate($input['password']);
                        $response['success'] = $authResult['success'];
                        $response['message'] = $authResult['message'];
                        
                        if ($authResult['success']) {
                            $response['data'] = ['authenticated' => true];
                        }
                    } else {
                        $response['message'] = 'Password required';
                    }
                    break;
                    
                case 'logout':
                    AuthHelper::logout();
                    $response['success'] = true;
                    $response['message'] = 'Logged out successfully';
                    break;
                    
                case 'auth_status':
                    $response['success'] = true;
                    $response['data'] = [
                        'authenticated' => AuthHelper::isAuthenticated(),
                        'lockout_remaining' => AuthHelper::getLockoutTimeRemaining()
                    ];
                    break;
                    
                case 'reset_password':
                    // Check authentication first
                    if (!AuthHelper::isAuthenticated()) {
                        $response['message'] = 'Authentication required';
                        $response['auth_required'] = true;
                        echo json_encode($response);
                        exit;
                    }
                    
                    if (isset($input['current_password']) && isset($input['new_password']) && isset($input['confirm_password'])) {
                        $currentPassword = $input['current_password'];
                        $newPassword = $input['new_password'];
                        $confirmPassword = $input['confirm_password'];
                        
                        // Validate inputs
                        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                            $response['message'] = 'All fields are required.';
                        } elseif ($newPassword !== $confirmPassword) {
                            $response['message'] = 'New passwords do not match.';
                        } elseif (strlen($newPassword) < 6) {
                            $response['message'] = 'New password must be at least 6 characters long.';
                        } else {
                            // Verify current password
                            if (password_verify($currentPassword, ADMIN_PASSWORD_HASH)) {
                                // Hash the new password
                                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                                
                                // Read current auth config file
                                $authFile = __DIR__ . '/../config/auth_config.php';
                                $authContent = file_get_contents($authFile);
                                
                                if ($authContent === false) {
                                    $response['message'] = 'Could not read authentication configuration file.';
                                } else {
                                    // Replace the password hash in the config
                                    $lines = explode("\n", $authContent);
                                    $newLines = [];
                                    $found = false;
                                    
                                    foreach ($lines as $line) {
                                        if (strpos($line, "define('ADMIN_PASSWORD_HASH'") !== false) {
                                            $newLines[] = "define('ADMIN_PASSWORD_HASH', '" . addslashes($newPasswordHash) . "');";
                                            $found = true;
                                        } else {
                                            $newLines[] = $line;
                                        }
                                    }
                                    
                                    if ($found) {
                                        $newAuthContent = implode("\n", $newLines);
                                        
                                        // Write the updated config back to file
                                        if (file_put_contents($authFile, $newAuthContent) !== false) {
                                            $response['success'] = true;
                                            $response['message'] = 'Password updated successfully! You can now log in with your new password.';
                                        } else {
                                            $response['message'] = 'Could not write to authentication configuration file. Please check file permissions.';
                                        }
                                    } else {
                                        $response['message'] = 'Could not find ADMIN_PASSWORD_HASH in configuration file.';
                                    }
                                }
                            } else {
                                $response['message'] = 'Current password is incorrect.';
                            }
                        }
                    } else {
                        $response['message'] = 'All password fields are required.';
                    }
                    break;
                    
                case 'setup_config':
                    // Check authentication first
                    if (!AuthHelper::isAuthenticated()) {
                        $response['message'] = 'Authentication required';
                        $response['auth_required'] = true;
                        echo json_encode($response);
                        exit;
                    }
                    
                    if (isset($input['discogs_api_key'])) {
                        $discogsApiKey = trim($input['discogs_api_key']);
                        
                        // Validate API key
                        if (empty($discogsApiKey)) {
                            $response['message'] = 'Discogs API key is required.';
                        } elseif (strlen($discogsApiKey) < 10) {
                            $response['message'] = 'Discogs API key appears to be too short. Please check your key.';
                        } else {
                            // Read current config file
                            $configFile = __DIR__ . '/../config/api_config.php';
                            $configContent = file_get_contents($configFile);
                            
                            if ($configContent === false) {
                                $response['message'] = 'Could not read configuration file.';
                            } else {
                                // Replace the API key in the config
                                $newConfigContent = preg_replace(
                                    "/define\('DISCOGS_API_KEY',\s*'[^']*'\);/",
                                    "define('DISCOGS_API_KEY', '" . addslashes($discogsApiKey) . "');",
                                    $configContent
                                );
                                
                                // Write the updated config back to file
                                if (file_put_contents($configFile, $newConfigContent) !== false) {
                                    $response['success'] = true;
                                    $response['message'] = 'Discogs API key updated successfully!';
                                } else {
                                    $response['message'] = 'Could not write to configuration file. Please check file permissions.';
                                }
                            }
                        }
                    } else {
                        $response['message'] = 'Discogs API key is required.';
                    }
                    break;
                    
                default:
                    $response['message'] = 'Invalid action';
            }
            break;
            
        default:
            $response['message'] = 'Method not allowed';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
} catch (Error $e) {
    $response['message'] = 'Fatal Error: ' . $e->getMessage();
}

echo json_encode($response);
ob_end_flush();
?> 