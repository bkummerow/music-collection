<?php
/**
 * Music Collection API
 * Handles AJAX requests for the music collection application
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Set proper caching headers to allow back/forward cache
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 300));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));

require_once '../models/MusicCollection.php';
require_once '../services/DiscogsAPIService.php';
require_once '../config/auth_config.php';

// Start session for authentication
session_start();

$musicCollection = new MusicCollection();
$discogsAPI = new DiscogsAPIService();
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
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
                            // Ensure consistent structure with external albums
                            $allAlbums[] = [
                                'album_name' => $album['album_name'],
                                'year' => null, // Local albums don't have year in this context
                                'artist' => $artist,
                                'cover_url' => null, // Local albums don't have cover in this context
                                'id' => null // Local albums don't have Discogs IDs
                            ];
                            $seenAlbums[strtolower($album['album_name'])] = true;
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
            $input = json_decode(file_get_contents('php://input'), true);
            
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
                                $input['is_owned'] ?? false,
                                $input['want_to_own'] ?? false,
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
                            
                            // If we don't have a stored release ID, try to fetch it
                            if (!$discogsReleaseId && $discogsAPI->isAvailable()) {
                                $albums = $discogsAPI->searchAlbumsByArtistForStorage($input['artist_name'], $input['album_name'], 1);
                                if (!empty($albums)) {
                                    $coverUrl = $albums[0]['cover_url'] ?? null;
                                    $discogsReleaseId = $albums[0]['id'] ?? null;
                                }
                            }
                            
                            $result = $musicCollection->updateAlbum(
                                $input['id'],
                                $input['artist_name'],
                                $input['album_name'],
                                $input['release_year'] ?? null,
                                $input['is_owned'] ?? false,
                                $input['want_to_own'] ?? false,
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
}

echo json_encode($response);
?> 