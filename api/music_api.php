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

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$discogsAPI = new DiscogsAPIService();
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
                    $response['data'] = $musicCollection->getAllAlbums($filter, $search);
                    $response['success'] = true;
                    break;
                    
                case 'album':
                    $id = $_GET['id'] ?? null;
                    if ($id) {
                        $response['data'] = $musicCollection->getAlbumById($id);
                        $response['success'] = true;
                    } else {
                        $response['message'] = 'Album ID required';
                    }
                    break;
                    
                case 'artists':
                    $search = $_GET['search'] ?? '';
                    
                    // Get local artists first
                    $localArtists = $musicCollection->getArtists($search);
                    
                    // Try to get additional artists from Discogs API
                    $externalArtists = [];
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
                    
                    if ($artist) {
                        // Get local albums first
                        $localAlbums = $musicCollection->getAlbumsByArtist($artist, $search);
                        
                        // Try to get additional albums from Discogs API
                        $externalAlbums = [];
                        if ($discogsAPI->isAvailable()) {
                            $externalAlbums = $discogsAPI->searchAlbumsByArtist($artist, $search, 15);
                            
                            // If strict search returns no results, try direct search as fallback
                            if (empty($externalAlbums) && !empty($search)) {
                                $externalAlbums = $discogsAPI->performDirectSearch("$artist $search", 15, $search);
                            }
                        }
                        
                        // Combine local and external results, prioritizing local
                        $allAlbums = [];
                        $seenAlbums = [];
                        
                        // Add local albums first
                        foreach ($localAlbums as $album) {
                            // Debug: Log the album structure
                            error_log('Local album structure: ' . json_encode($album));
                            
                            // Ensure consistent structure with external albums
                            $allAlbums[] = [
                                'album_name' => $album['album_name'] ?? $album['title'] ?? 'Unknown Album',
                                'year' => $album['release_year'] ?? null,
                                'artist' => $artist,
                                'cover_url' => $album['cover_url'] ?? null,
                                'cover_url_medium' => $album['cover_url_medium'] ?? $album['cover_url'] ?? null,
                                'cover_url_large' => $album['cover_url_large'] ?? $album['cover_url'] ?? null,
                                'id' => $album['discogs_release_id'] ?? null
                            ];
                            $seenAlbums[strtolower($album['album_name'] ?? $album['title'] ?? 'unknown album')] = true;
                        }
                        
                        // Add external albums that aren't already in local collection
                        foreach ($externalAlbums as $album) {
                            $albumName = $album['title']; // This is already cleaned by DiscogsAPIService
                            $year = $album['year'] ?? null;
                            
                            // Create a unique key that includes year to allow same-named albums with different years
                            $uniqueKey = strtolower($albumName) . '_' . ($year ?? 'unknown');
                            
                            if (!isset($seenAlbums[$uniqueKey])) {
                                $allAlbums[] = [
                                    'album_name' => $albumName,
                                    'year' => $year,
                                    'artist' => $album['artist'] ?? $artist,
                                    'cover_url' => $album['cover_url'] ?? null,
                                    'cover_url_medium' => $album['cover_url_medium'] ?? $album['cover_url'] ?? null,
                                    'cover_url_large' => $album['cover_url_large'] ?? $album['cover_url'] ?? null,
                                    'id' => $album['id'] ?? null // Include the Discogs release ID
                                ];
                                $seenAlbums[$uniqueKey] = true;
                            }
                        }
                        
                        $response['data'] = $allAlbums;
                        $response['success'] = true;
                    } else {
                        $response['message'] = 'Artist name required';
                    }
                    break;
                    
                case 'stats':
                    $response['data'] = $musicCollection->getStats();
                    $response['success'] = true;
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
                            
                            // If we don't have a stored release ID, try to fetch it
                            if (!$discogsReleaseId && $discogsAPI->isAvailable()) {
                                $albums = $discogsAPI->searchAlbumsByArtistForStorage($input['artist_name'], $input['album_name'], 1);
                                if (!empty($albums)) {
                                    $coverUrl = $albums[0]['cover_url'] ?? null;
                                    $discogsReleaseId = $albums[0]['id'] ?? null;
                                }
                            }
                            
                            $result = $musicCollection->addAlbum(
                                $input['artist_name'],
                                $input['album_name'],
                                $input['release_year'] ?? null,
                                normalizeBoolean($input['is_owned'] ?? false),
                                normalizeBoolean($input['want_to_own'] ?? false),
                                $coverUrl,
                                $discogsReleaseId
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
                            
                            // Try to fetch cover art from Discogs if not provided
                            if (!$discogsReleaseId && $discogsAPI->isAvailable()) {
                                try {
                                    $albums = $discogsAPI->searchAlbumsByArtistForStorage($input['artist_name'], $input['album_name'], 1);
                                    if (!empty($albums)) {
                                        $coverUrl = $albums[0]['cover_url'] ?? null;
                                        $discogsReleaseId = $albums[0]['id'] ?? null;
                                    }
                                } catch (Exception $discogsError) {
                                    // Log Discogs error but don't fail the whole update
                                    error_log('Discogs API error during update: ' . $discogsError->getMessage());
                                    // Continue with update even if Discogs fails
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
                                $discogsReleaseId
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
                    
                default:
                    $response['message'] = 'Invalid action';
            }
            break;
            
        default:
            $response['message'] = 'Method not allowed';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    // Log the full error for debugging
    error_log('Music API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
} catch (Error $e) {
    $response['message'] = 'Fatal Error: ' . $e->getMessage();
    // Log the full error for debugging
    error_log('Music API Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}

echo json_encode($response);
ob_end_flush();
?> 