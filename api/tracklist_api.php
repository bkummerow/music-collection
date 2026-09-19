<?php
/**
 * Tracklist API
 * Fetches tracklist information from Discogs for a specific artist and album
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../services/DiscogsAPIService.php';
require_once __DIR__ . '/../models/MusicCollection.php';
require_once __DIR__ . '/../services/LyricsService.php';
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../services/TracklistCacheHelper.php';

/**
 * Cache successful tracklist payloads briefly; never cache errors
 * (e.g. missing API key) so a transient failure cannot stick for 10 minutes.
 */
function tracklistSendCacheHeaders($success) {
    if ($success) {
        header('Cache-Control: public, max-age=600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 600));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
    header('Pragma: no-cache');
}

/**
 * Emit JSON response with correct cache headers for success vs failure.
 */
function tracklistJsonExit($response) {
    tracklistSendCacheHeaders(!empty($response['success']));
    echo json_encode($response);
    exit;
}

$discogsAPI = new DiscogsAPIService();
$musicCollection = new MusicCollection();
$lyricsService = new LyricsService();

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $artistName = $_GET['artist'] ?? '';
        $albumName = $_GET['album'] ?? '';
        $releaseYear = $_GET['year'] ?? '';
        $albumId = $_GET['album_id'] ?? null;
        $releaseId = $_GET['release_id'] ?? null;
        $currency = strtoupper($_GET['currency'] ?? '');
        $enrich = !empty($_GET['enrich']);
        $masterId = $_GET['master_id'] ?? null;
    } else {
        $artistName = $input['artist'] ?? '';
        $albumName = $input['album'] ?? '';
        $releaseYear = $input['year'] ?? '';
        $albumId = $input['album_id'] ?? null;
        $releaseId = $input['release_id'] ?? null;
        $currency = strtoupper($input['currency'] ?? '');
        $enrich = !empty($input['enrich']);
        $masterId = $input['master_id'] ?? null;
    }

    $refresh = !empty($_GET['refresh']) || !empty($input['refresh']);
    if ($refresh) {
        AuthHelper::requireAdminAction();
    }

    $discogsReleaseId = null;
    $album = null;
    
    // If we have a release ID, we can skip the artist/album requirement
    if (empty($releaseId)) {
        if (empty($artistName) || empty($albumName)) {
            $response['message'] = 'Artist and album names are required';
            tracklistJsonExit($response);
        }
    } else {
        // If we have a release ID, we don't need artist/album names
        $artistName = $artistName ?: 'Unknown Artist';
        $albumName = $albumName ?: 'Unknown Album';
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
    
    if (!empty($currency)) {
        $discogsAPI->setPreferredCurrency($currency);
    }

    if ($albumId && !$album) {
        $album = $musicCollection->getAlbumById($albumId);
    }

    // Optional extras (artist links, marketplace currency, master year) load after tracks.
    if ($enrich) {
        if (!$discogsAPI->isAvailable()) {
            $response['message'] = 'Discogs API is not available';
            tracklistJsonExit($response);
        }
        if (empty($discogsReleaseId)) {
            $response['message'] = 'A Discogs release ID is required to load tracklist extras';
            tracklistJsonExit($response);
        }
        $artistForExtras = $artistName;
        if ($artistForExtras === '' && !empty($album['artist_name'])) {
            $artistForExtras = $album['artist_name'];
        }
        $response['success'] = true;
        $response['data'] = $discogsAPI->getTracklistExtras($discogsReleaseId, $artistForExtras, $masterId);
        $response['message'] = 'Tracklist extras retrieved successfully';
        tracklistJsonExit($response);
    }

    if (!$refresh && tracklistAlbumHasCache($album)) {
        $artistForLyrics = !empty($album['artist_name']) ? $album['artist_name'] : $artistName;
        $enhanced = enhanceTracklistWithLyrics($album['tracklist'], $artistForLyrics);
        $releaseIdForLinks = $album['tracklist_source_release_id']
            ?? ($album['discogs_release_id'] ?? null);
        $response['success'] = true;
        $response['source'] = 'cache';
        $response['data'] = [
            'artist' => $album['artist_name'],
            'album' => $album['album_name'],
            'year' => $album['release_year'] ?? null,
            'cover_url' => $album['cover_url'] ?? null,
            'tracklist' => $enhanced,
            'format' => $album['format'] ?? '',
            'producer' => $album['producer'] ?? '',
            'label' => $album['label'] ?? '',
            'total_runtime' => $album['total_runtime'] ?? null,
            'discogs_release_id' => $album['discogs_release_id'] ?? $releaseIdForLinks,
            'discogs_url' => $releaseIdForLinks
                ? ('https://www.discogs.com/release/' . $releaseIdForLinks)
                : ('https://www.discogs.com/search/?q=' . urlencode($artistName . ' ' . $albumName) . '&type=release'),
            'shop_url' => $releaseIdForLinks
                ? ('https://www.discogs.com/sell/release/' . $releaseIdForLinks)
                : null,
            'rating' => null,
            'rating_count' => null,
            'has_reviews_with_content' => false,
            'num_for_sale' => null,
            'lowest_price' => null,
            'matched_reason' => 'local_cache',
            'tracklist_cached_at' => $album['tracklist_cached_at'] ?? null,
        ];
        $response['message'] = 'Tracklist served from local cache';
        tracklistJsonExit($response);
    }

    if (!$discogsAPI->isAvailable()) {
        $response['message'] = 'Discogs API is not available';
        tracklistJsonExit($response);
    }

    // If we have a stored Discogs release ID, use it directly
    if ($discogsReleaseId) {
        $releaseInfo = $discogsAPI->getReleaseInfo($discogsReleaseId, false);
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
                'master_id' => $releaseInfo['master_id'] ?? null,
                'master_year' => $releaseInfo['master_year'] ?? null,
                'cover_url' => $existingCoverUrl ?: $releaseInfo['cover_url'], // Prioritize existing cover art
                'tracklist' => $enhancedTracklist,
                'format' => $existingFormat ?: $releaseInfo['format'] ?? '', // Prioritize existing format data
                'producer' => $existingProducer ?: $releaseInfo['producer'] ?? '', // Prioritize existing producer data
                'artist_website' => null,
                'rating' => $releaseInfo['rating'] ?? null,
                'rating_count' => $releaseInfo['rating_count'] ?? null,
                'has_reviews_with_content' => $releaseInfo['has_reviews_with_content'] ?? false,
                'style' => $releaseInfo['style'] ?? '',
                'label' => $existingLabel ?: $releaseInfo['label'] ?? '', // Prioritize existing label data
                'released' => $releaseInfo['released'] ?? null,
                'total_runtime' => $releaseInfo['total_runtime'] ?? null,
                'discogs_url' => "https://www.discogs.com/release/{$discogsReleaseId}",
                // Marketplace link: shop page for this release
                'shop_url' => "https://www.discogs.com/sell/release/{$discogsReleaseId}",
                'num_for_sale' => $releaseInfo['num_for_sale'] ?? null,
                'lowest_price' => $releaseInfo['lowest_price'] ?? null,
                'search_url' => "https://www.discogs.com/search/?q=" . urlencode($artistName . ' ' . $albumName) . "&type=release",
                'discogs_release_id' => $discogsReleaseId,
                'matched_reason' => 'stored_release_id'
            ];
            
            $response['message'] = 'Tracklist information retrieved successfully using stored release ID';
            $response['source'] = 'discogs';
            // Always reload local album by id — never reuse Discogs search shapes.
            if ($albumId) {
                $localAlbum = $musicCollection->getAlbumById($albumId);
                if ($localAlbum) {
                    tracklistPersistCache(
                        $musicCollection,
                        $localAlbum,
                        $response['data'],
                        $response['data']['discogs_release_id'] ?? $discogsReleaseId
                    );
                }
            }
            tracklistJsonExit($response);
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
        tracklistJsonExit($response);
    }
    
    // Try to find the best match based on year and exact title match
    $bestMatch = null;
    $exactTitleMatch = null;
    $yearMatch = null;
    
    // Use $discogsHit — do not overwrite $album (local collection row) used for cache persist.
    foreach ($albums as $discogsHit) {
        $albumTitle = strtolower(trim($discogsHit['title']));
        $searchTitle = strtolower(trim($albumName));
        $albumYear = $discogsHit['year'] ?? null;
        
        // Check for exact title match
        if ($albumTitle === $searchTitle) {
            if (!$exactTitleMatch) {
                $exactTitleMatch = $discogsHit;
            }
            // If we have a year and it matches, this is our best match
            if ($releaseYear && $albumYear == $releaseYear) {
                $bestMatch = $discogsHit;
                break;
            }
        }
        
        // Check for year match if we have a year
        if ($releaseYear && $albumYear == $releaseYear) {
            if (!$yearMatch) {
                $yearMatch = $discogsHit;
            }
        }
    }
    
    // Use the best match found, or fall back to the first result
    $selectedAlbum = $bestMatch ?: $exactTitleMatch ?: $yearMatch ?: $albums[0];
    
    // Get detailed information for the selected album
    $releaseInfo = $discogsAPI->getReleaseInfo($selectedAlbum['id'], false);
    
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
            'master_id' => $releaseInfo['master_id'] ?? null,
            'master_year' => $releaseInfo['master_year'] ?? null,
            'cover_url' => $existingCoverUrl ?: $releaseInfo['cover_url'], // Prioritize existing cover art
            'tracklist' => $enhancedTracklist,
            'format' => $existingFormat ?: $releaseInfo['format'] ?? '', // Prioritize existing format data
            'producer' => $existingProducer ?: $releaseInfo['producer'] ?? '', // Prioritize existing producer data
            'artist_website' => null,
            'rating' => $releaseInfo['rating'] ?? null,
            'rating_count' => $releaseInfo['rating_count'] ?? null,
            'has_reviews_with_content' => $releaseInfo['has_reviews_with_content'] ?? false,
            'style' => $releaseInfo['style'] ?? '',
            'label' => $existingLabel ?: $releaseInfo['label'] ?? '', // Prioritize existing label data
            'released' => $releaseInfo['released'] ?? null,
            'total_runtime' => $releaseInfo['total_runtime'] ?? null,
            'discogs_url' => "https://www.discogs.com/release/{$selectedAlbum['id']}",
            // Marketplace link: shop page for this release
            'shop_url' => "https://www.discogs.com/sell/release/{$selectedAlbum['id']}",
            'num_for_sale' => $releaseInfo['num_for_sale'] ?? null,
            'lowest_price' => $releaseInfo['lowest_price'] ?? null,
            'search_url' => "https://www.discogs.com/search/?q=" . urlencode($artistName . ' ' . $albumName) . "&type=release",
            'discogs_release_id' => $selectedAlbum['id'],
            'matched_reason' => $bestMatch ? 'exact_title_and_year' : 
                               ($exactTitleMatch ? 'exact_title' : 
                               ($yearMatch ? 'year_match' : 'first_result'))
        ];
        
        $response['message'] = 'Tracklist information retrieved successfully';
        $response['source'] = 'discogs';
        // Reload local album by id so Discogs search hits cannot shadow the collection row.
        if ($albumId) {
            $localAlbum = $musicCollection->getAlbumById($albumId);
            if ($localAlbum) {
                tracklistPersistCache(
                    $musicCollection,
                    $localAlbum,
                    $response['data'],
                    $response['data']['discogs_release_id'] ?? null
                );
            }
        }
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
        
        // Clean track title (remove common suffixes and prefixes) - used for other UI, but
        // for lyrics URL construction we want the full, original title so features in
        // parentheses (e.g., "(Fütter Mein Ego)") are preserved.
        $cleanTitle = cleanTrackTitle($title);
        
        if (!empty($title)) {
            // Build lyrics URLs from the original title for better slugging
            $lyricsUrls = $lyricsService->getLyricsSearchUrls($artist, $title);
            
            $enhancedTrack['lyrics_urls'] = $lyricsUrls;
            $enhancedTrack['has_lyrics'] = $lyricsService->hasLyrics($artist, $title);
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

tracklistJsonExit($response);
?> 