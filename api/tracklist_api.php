<?php
/**
 * Tracklist API
 * Fetches tracklist information from Discogs for a specific artist and album
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Set proper caching headers to allow back/forward cache
header('Cache-Control: public, max-age=600'); // Cache for 10 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 600));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));

require_once __DIR__ . '/../services/DiscogsAPIService.php';
require_once __DIR__ . '/../models/MusicCollection.php';
require_once __DIR__ . '/../services/LyricsService.php';

$discogsAPI = new DiscogsAPIService();
$musicCollection = new MusicCollection();
$lyricsService = new LyricsService();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $artistName = $_GET['artist'] ?? '';
        $albumName = $_GET['album'] ?? '';
        $releaseYear = $_GET['year'] ?? '';
        $albumId = $_GET['album_id'] ?? null;
        $releaseId = $_GET['release_id'] ?? null;
    } else {
        $artistName = $input['artist'] ?? '';
        $albumName = $input['album'] ?? '';
        $releaseYear = $input['year'] ?? '';
        $albumId = $input['album_id'] ?? null;
        $releaseId = $input['release_id'] ?? null;
    }
    
    // If we have a release ID, we can skip the artist/album requirement
    if (empty($releaseId)) {
        if (empty($artistName) || empty($albumName)) {
            $response['message'] = 'Artist and album names are required';
            echo json_encode($response);
            exit;
        }
    } else {
        // If we have a release ID, we don't need artist/album names
        $artistName = $artistName ?: 'Unknown Artist';
        $albumName = $albumName ?: 'Unknown Album';
    }
    
    if (!$discogsAPI->isAvailable()) {
        $response['message'] = 'Discogs API is not available';
        echo json_encode($response);
        exit;
    }
    
    // If we have a release ID, use it directly
    if ($releaseId) {
        $discogsReleaseId = $releaseId;
    } else {
        // If we have an album ID, try to get the stored Discogs release ID first
        if ($albumId) {
            $album = $musicCollection->getAlbumById($albumId);
            if ($album && isset($album['discogs_release_id']) && $album['discogs_release_id']) {
                $discogsReleaseId = $album['discogs_release_id'];
            }
        }
    }
    
    // If we have a stored Discogs release ID, use it directly
    if ($discogsReleaseId) {
        $releaseInfo = $discogsAPI->getReleaseInfo($discogsReleaseId);
        if ($releaseInfo) {
            $response['success'] = true;

            // Check if we have existing cover art, format, label, and producer data in our collection
            $existingCoverUrl = null;
            $existingFormat = null;
            $existingLabel = null;
            $existingProducer = null;
            if ($albumId && $album) {
                $existingCoverUrl = $album['cover_url'] ?? null;
                $existingFormat = $album['format'] ?? null;
                $existingLabel = $album['label'] ?? null;
                $existingProducer = $album['producer'] ?? null;
            }

            // Enhance tracklist with lyrics information
            $enhancedTracklist = enhanceTracklistWithLyrics($releaseInfo['tracklist'] ?? [], $releaseInfo['artist']);
            
            $response['data'] = [
                'artist' => $releaseInfo['artist'],
                'album' => $releaseInfo['title'],
                'year' => $releaseInfo['year'],
                'master_year' => $releaseInfo['master_year'] ?? null,
                'cover_url' => $existingCoverUrl ?: $releaseInfo['cover_url'], // Prioritize existing cover art
                'tracklist' => $enhancedTracklist,
                'format' => $existingFormat ?: $releaseInfo['format'] ?? '', // Prioritize existing format data
                'producer' => $existingProducer ?: $releaseInfo['producer'] ?? '', // Prioritize existing producer data
                'rating' => $releaseInfo['rating'] ?? null,
                'rating_count' => $releaseInfo['rating_count'] ?? null,
                'has_reviews_with_content' => $releaseInfo['has_reviews_with_content'] ?? false,
                'style' => $releaseInfo['style'] ?? '',
                'label' => $existingLabel ?: $releaseInfo['label'] ?? '', // Prioritize existing label data
                'released' => $releaseInfo['released'] ?? null,
                'discogs_url' => "https://www.discogs.com/release/{$discogsReleaseId}",
                'search_url' => "https://www.discogs.com/search/?q=" . urlencode($artistName . ' ' . $albumName) . "&type=release",
                'matched_reason' => 'stored_release_id'
            ];
            $response['message'] = 'Tracklist information retrieved successfully using stored release ID';
            echo json_encode($response);
            exit;
        } else {
            // If API call failed due to rate limiting or other issues, continue to fallback search
            // Discogs API call failed, falling back to search
        }
    }
    
    // Fall back to search-based matching if no stored ID or if stored ID failed
    // Use a more flexible search for tracklist API since we're looking for a specific album
    $albums = $discogsAPI->searchAlbumsByArtist($artistName, $albumName, 10);
    
    if (empty($albums)) {
        // Try a broader search if the strict search fails
        $albums = $discogsAPI->performDirectSearch($artistName . ' ' . $albumName, 10);
    }
    
    if (empty($albums)) {
        $response['message'] = 'No albums found for this artist and album combination';
        echo json_encode($response);
        exit;
    }
    
    // Try to find the best match based on year and exact title match
    $bestMatch = null;
    $exactTitleMatch = null;
    $yearMatch = null;
    
    foreach ($albums as $album) {
        $albumTitle = strtolower(trim($album['title']));
        $searchTitle = strtolower(trim($albumName));
        $albumYear = $album['year'] ?? null;
        
        // Check for exact title match
        if ($albumTitle === $searchTitle) {
            if (!$exactTitleMatch) {
                $exactTitleMatch = $album;
            }
            // If we have a year and it matches, this is our best match
            if ($releaseYear && $albumYear == $releaseYear) {
                $bestMatch = $album;
                break;
            }
        }
        
        // Check for year match if we have a year
        if ($releaseYear && $albumYear == $releaseYear) {
            if (!$yearMatch) {
                $yearMatch = $album;
            }
        }
    }
    
    // Use the best match found, or fall back to the first result
    $selectedAlbum = $bestMatch ?: $exactTitleMatch ?: $yearMatch ?: $albums[0];
    
    // Get detailed information for the selected album
    $releaseInfo = $discogsAPI->getReleaseInfo($selectedAlbum['id']);
    
    if ($releaseInfo) {
        $response['success'] = true;
        
        // Check if we have existing cover art, format, label, and producer data in our collection for the fallback case
        $existingCoverUrl = null;
        $existingFormat = null;
        $existingLabel = null;
        $existingProducer = null;
        if ($albumId) {
            $album = $musicCollection->getAlbumById($albumId);
            if ($album) {
                $existingCoverUrl = $album['cover_url'] ?? null;
                $existingFormat = $album['format'] ?? null;
                $existingLabel = $album['label'] ?? null;
                $existingProducer = $album['producer'] ?? null;
            }
        }
        
        // Enhance tracklist with lyrics information
        $enhancedTracklist = enhanceTracklistWithLyrics($releaseInfo['tracklist'] ?? [], $releaseInfo['artist']);
        
        $response['data'] = [
            'artist' => $releaseInfo['artist'],
            'album' => $releaseInfo['title'],
            'year' => $releaseInfo['year'],
            'master_year' => $releaseInfo['master_year'] ?? null,
            'cover_url' => $existingCoverUrl ?: $releaseInfo['cover_url'], // Prioritize existing cover art
            'tracklist' => $enhancedTracklist,
            'format' => $existingFormat ?: $releaseInfo['format'] ?? '', // Prioritize existing format data
            'producer' => $existingProducer ?: $releaseInfo['producer'] ?? '', // Prioritize existing producer data
            'rating' => $releaseInfo['rating'] ?? null,
            'rating_count' => $releaseInfo['rating_count'] ?? null,
            'has_reviews_with_content' => $releaseInfo['has_reviews_with_content'] ?? false,
            'style' => $releaseInfo['style'] ?? '',
            'label' => $existingLabel ?: $releaseInfo['label'] ?? '', // Prioritize existing label data
            'released' => $releaseInfo['released'] ?? null,
            'discogs_url' => "https://www.discogs.com/release/{$selectedAlbum['id']}",
            'search_url' => "https://www.discogs.com/search/?q=" . urlencode($artistName . ' ' . $albumName) . "&type=release",
            'matched_reason' => $bestMatch ? 'exact_title_and_year' : 
                               ($exactTitleMatch ? 'exact_title' : 
                               ($yearMatch ? 'year_match' : 'first_result'))
        ];
        $response['message'] = 'Tracklist information retrieved successfully';
    } else {
        // If API call failed due to rate limiting or other issues, provide a graceful fallback
        $response['message'] = 'Could not retrieve detailed album information due to API rate limiting. Please try again later.';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

/**
 * Enhance tracklist with lyrics information
 */
function enhanceTracklistWithLyrics($tracklist, $artist) {
    global $lyricsService;
    
    if (empty($tracklist) || !is_array($tracklist)) {
        return $tracklist;
    }
    
    $enhancedTracklist = [];
    
    foreach ($tracklist as $track) {
        $enhancedTrack = $track;
        
        // Only add lyrics for actual tracks, not section headers
        // Section headers typically don't have a position or have empty titles
        $position = $track['position'] ?? '';
        $title = $track['title'] ?? '';
        
        // Skip if this looks like a section header (no position or very short title)
        if (empty($position) || empty($title) || strlen(trim($title)) < 3) {
            $enhancedTrack['lyrics_urls'] = null;
            $enhancedTrack['has_lyrics'] = false;
            $enhancedTracklist[] = $enhancedTrack;
            continue;
        }
        
        // Clean track title (remove common suffixes and prefixes)
        $cleanTitle = cleanTrackTitle($title);
        
        if (!empty($cleanTitle)) {
            // Get lyrics search URLs
            $lyricsUrls = $lyricsService->getLyricsSearchUrls($artist, $cleanTitle);
            
            $enhancedTrack['lyrics_urls'] = $lyricsUrls;
            $enhancedTrack['has_lyrics'] = $lyricsService->hasLyrics($artist, $cleanTitle);
        } else {
            $enhancedTrack['lyrics_urls'] = null;
            $enhancedTrack['has_lyrics'] = false;
        }
        
        $enhancedTracklist[] = $enhancedTrack;
    }
    
    return $enhancedTracklist;
}

/**
 * Clean track title for better lyrics matching
 */
function cleanTrackTitle($title) {
    if (empty($title)) {
        return '';
    }
    
    // Remove common suffixes that might interfere with lyrics search
    $title = preg_replace('/\s*\([^)]*\)\s*$/', '', $title); // Remove trailing parentheses
    $title = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $title); // Remove trailing brackets
    $title = preg_replace('/\s*-\s*[^-]*$/', '', $title); // Remove trailing dash content
    $title = preg_replace('/\s*feat\.?\s*.*$/i', '', $title); // Remove "feat." content
    $title = preg_replace('/\s*ft\.?\s*.*$/i', '', $title); // Remove "ft." content
    
    // Remove leading track numbers
    $title = preg_replace('/^\d+\.?\s*/', '', $title);
    
    return trim($title);
}

echo json_encode($response);
?> 