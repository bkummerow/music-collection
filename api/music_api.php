<?php
/**
 * Music Collection API
 * Handles AJAX requests for the music collection application
 */

// Start output buffering to prevent any output before headers
ob_start();

header('Content-Type: application/json');

// Set proper caching headers - no cache for API responses to ensure fresh data
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Pragma: no-cache');

require_once __DIR__ . '/../models/MusicCollection.php';
require_once __DIR__ . '/../services/DiscogsAPIService.php';
require_once __DIR__ . '/../services/DiscogsImportService.php';
require_once __DIR__ . '/../services/DiscogsExportService.php';
require_once __DIR__ . '/../services/CatalogBackupService.php';
require_once __DIR__ . '/../services/AlbumPersonalFields.php';
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../config/webauthn_helper.php';

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

/**
 * Normalize format strings for better matching
 */
if (!function_exists('normalizeFormatString')) {
    function normalizeFormatString($formatString) {
        if (empty($formatString)) {
            return '';
        }
        
        // Convert to lowercase
        $normalized = strtolower($formatString);
        
        // Normalize different quote types and inch symbols
        $normalized = str_replace(['"', '"', '"', '″', 'in', 'inch'], '"', $normalized);
        
        // Remove extra spaces and normalize separators
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = str_replace([', ', ' + '], ',', $normalized);
        
        return trim($normalized);
    }
}

/**
 * Check if a format string matches a search format
 */
if (!function_exists('formatMatches')) {
    function formatMatches($albumFormat, $searchFormat) {
        if (empty($albumFormat) || empty($searchFormat)) {
            return false;
        }
        
        // Split both formats into parts
        $albumParts = explode(',', $albumFormat);
        $searchParts = explode(',', $searchFormat);
        
        // Check if any search part matches any album part
        foreach ($searchParts as $searchPart) {
            $searchPart = trim($searchPart);
            foreach ($albumParts as $albumPart) {
                $albumPart = trim($albumPart);
                if ($searchPart === $albumPart || strpos($albumPart, $searchPart) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

/**
 * Add original and version release years from Discogs when available
 */
if (!function_exists('enrichAlbumYearInfo')) {
    function enrichAlbumYearInfo($album, $discogsAPI) {
        if (!is_array($album)) {
            return $album;
        }

        $album['master_year'] = $album['release_year'] ?? null;
        $album['version_year'] = null;

        $releaseId = $album['discogs_release_id'] ?? null;
        if ($releaseId && $discogsAPI && $discogsAPI->isAvailable()) {
            $releaseInfo = $discogsAPI->getReleaseInfo($releaseId);
            if ($releaseInfo) {
                if (!empty($releaseInfo['master_year'])) {
                    $album['master_year'] = $releaseInfo['master_year'];
                }
                if (!empty($releaseInfo['year'])) {
                    $album['version_year'] = $releaseInfo['year'];
                }
            }
        }

        return $album;
    }
}

/**
 * Build display payload for a duplicate-album comparison modal
 */
if (!function_exists('buildDuplicateAlbumDisplayPayload')) {
    function buildDuplicateAlbumDisplayPayload($input, $coverUrl, $coverImages, $discogsReleaseId, $isOwned, $wantToOwn) {
        return [
            'artist_name' => $input['artist_name'],
            'album_name' => $input['album_name'],
            'release_year' => $input['release_year'] ?? null,
            'cover_url' => $coverUrl,
            'cover_images' => $coverImages,
            'discogs_release_id' => $discogsReleaseId,
            'format' => $input['format'] ?? null,
            'is_owned' => $isOwned,
            'want_to_own' => $wantToOwn,
        ];
    }
}

/**
 * Determine duplicate modal context for messaging
 */
if (!function_exists('getDuplicateAlbumContext')) {
    function getDuplicateAlbumContext($existingAlbum, $isOwned, $wantToOwn) {
        if (!$existingAlbum) {
            return 'duplicate';
        }

        $isWantedOnly = !empty($existingAlbum['want_to_own']) && empty($existingAlbum['is_owned']);
        $addingAsOwned = !empty($isOwned) && empty($wantToOwn);

        if ($isWantedOnly && $addingAsOwned) {
            return 'want_to_owned';
        }

        return 'duplicate';
    }
}

/**
 * Find an existing album by artist and title (works with older MusicCollection builds)
 */
if (!function_exists('findExistingAlbumByArtistAndName')) {
    function findExistingAlbumByArtistAndName($musicCollection, $artistName, $albumName) {
        if (method_exists($musicCollection, 'getAlbumByArtistAndName')) {
            return $musicCollection->getAlbumByArtistAndName($artistName, $albumName);
        }

        $albums = $musicCollection->getAllAlbums();
        foreach ($albums as $album) {
            if (strcasecmp(trim($album['artist_name']), trim($artistName)) === 0 &&
                strcasecmp(trim($album['album_name']), trim($albumName)) === 0) {
                return $album;
            }
        }

        return null;
    }
}

/**
 * Add an album, optionally skipping the duplicate-name check when supported.
 * Optional personal fields are local-only (media/sleeve condition, notes).
 */
if (!function_exists('addAlbumToCollection')) {
    function addAlbumToCollection($musicCollection, $artistName, $albumName, $releaseYear, $isOwned, $wantToOwn, $coverUrl, $coverImages, $discogsReleaseId, $style, $format, $artistType, $label, $producer, $skipDuplicateCheck = false, $mediaCondition = '', $sleeveCondition = '', $notes = '') {
        $addMethod = new ReflectionMethod($musicCollection, 'addAlbum');
        $supportsSkipDuplicate = $addMethod->getNumberOfParameters() >= 14;
        $supportsPersonal = $addMethod->getNumberOfParameters() >= 17;

        if ($skipDuplicateCheck && !$supportsSkipDuplicate) {
            throw new Exception('Keep both is unavailable until models/MusicCollection.php is deployed on the server.');
        }

        if ($supportsPersonal) {
            return $musicCollection->addAlbum(
                $artistName,
                $albumName,
                $releaseYear,
                $isOwned,
                $wantToOwn,
                $coverUrl,
                $coverImages,
                $discogsReleaseId,
                $style,
                $format,
                $artistType,
                $label,
                $producer,
                $skipDuplicateCheck,
                $mediaCondition,
                $sleeveCondition,
                $notes
            );
        }

        if ($skipDuplicateCheck && $supportsSkipDuplicate) {
            return $musicCollection->addAlbum(
                $artistName,
                $albumName,
                $releaseYear,
                $isOwned,
                $wantToOwn,
                $coverUrl,
                $coverImages,
                $discogsReleaseId,
                $style,
                $format,
                $artistType,
                $label,
                $producer,
                true
            );
        }

        return $musicCollection->addAlbum(
            $artistName,
            $albumName,
            $releaseYear,
            $isOwned,
            $wantToOwn,
            $coverUrl,
            $coverImages,
            $discogsReleaseId,
            $style,
            $format,
            $artistType,
            $label,
            $producer
        );
    }
}

/**
 * Persist media/sleeve/notes after a catalog update without touching Discogs import paths.
 *
 * @param MusicCollection $musicCollection
 * @param int|string $albumId
 * @param string $artistName
 * @param string $albumName
 * @param array $personal Result from AlbumPersonalFields::normalizeFromInput
 * @return bool
 */
if (!function_exists('persistAlbumPersonalFields')) {
    function persistAlbumPersonalFields($musicCollection, $albumId, $artistName, $albumName, $personal) {
        return $musicCollection->updateAlbumRaw([
            'id' => $albumId,
            'artist_name' => $artistName,
            'album_name' => $albumName,
            'media_condition' => $personal['media_condition'],
            'sleeve_condition' => $personal['sleeve_condition'],
            'notes' => $personal['notes'],
        ]);
    }
}

/**
 * Require authenticated admin for read-only import settings (no CSRF on GET).
 */
function discogsImportRequireAdminRead() {
    AuthHelper::requireAuthenticated();
    if (AuthHelper::mustChangePassword()) {
        AuthHelper::jsonExit(403, 'Password change required', ['must_change_password' => true]);
    }
}

/**
 * Default cumulative import count structure.
 *
 * @return array{added:int,updated:int,skipped:int,errors:int}
 */
function discogsImportEmptyCounts() {
    return [
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];
}

/**
 * Merge page counts into cumulative session totals.
 *
 * @param array $cumulative Existing totals
 * @param array $pageCounts Counts from one processed page
 * @return array
 */
function discogsImportMergeCounts(array $cumulative, array $pageCounts) {
    foreach (discogsImportEmptyCounts() as $key => $zero) {
        if (isset($pageCounts[$key])) {
            $cumulative[$key] = (int) $cumulative[$key] + (int) $pageCounts[$key];
        }
    }
    return $cumulative;
}

/**
 * Determine the next page request after processing a Discogs API page.
 *
 * @param string $phase collection|wantlist
 * @param int $page Current page number
 * @param int $pages Total pages in current phase
 * @return array|null Next {phase,page} or null when import is complete
 */
function discogsImportComputeNext($phase, $page, $pages) {
    $page = (int) $page;
    $pages = max(1, (int) $pages);

    if ($page < $pages) {
        return [
            'phase' => $phase,
            'page' => $page + 1,
        ];
    }

    if ($phase === 'collection') {
        return [
            'phase' => 'wantlist',
            'page' => 1,
        ];
    }

    return null;
}

/**
 * Validate import phase name.
 *
 * @param string $phase Phase from client
 * @return bool
 */
function discogsImportIsValidPhase($phase) {
    return $phase === 'collection' || $phase === 'wantlist';
}

/**
 * Load saved Discogs username from app settings (via theme settings helpers).
 *
 * @return string
 */
function discogsImportLoadSavedUsername() {
    if (!function_exists('loadAppSettings')) {
        if (!defined('MUSIC_COLLECTION_THEME_SETTINGS_LIB')) {
            define('MUSIC_COLLECTION_THEME_SETTINGS_LIB', true);
        }
        require_once __DIR__ . '/theme_api.php';
    }
    $app = loadAppSettings();
    return isset($app['discogs_username']) ? trim((string) $app['discogs_username']) : '';
}

/**
 * Persist Discogs username into app settings using theme validation/save.
 *
 * @param string $username Username to save
 * @return array{success:bool,message?:string}
 */
function discogsImportSaveUsernameToSettings($username) {
    if (!function_exists('loadAppSettings')) {
        if (!defined('MUSIC_COLLECTION_THEME_SETTINGS_LIB')) {
            define('MUSIC_COLLECTION_THEME_SETTINGS_LIB', true);
        }
        require_once __DIR__ . '/theme_api.php';
    }

    $validated = validateDiscogsUsernameSetting($username);
    if (!$validated['ok']) {
        return ['success' => false, 'message' => $validated['message']];
    }

    $app = loadAppSettings();
    $app['discogs_username'] = $validated['value'];

    return saveAppSettings($app);
}

/** Albums processed per export_discogs_page request. */
if (!defined('DISCOGS_EXPORT_BATCH_SIZE')) {
    define('DISCOGS_EXPORT_BATCH_SIZE', 15);
}

/**
 * Default cumulative export count structure.
 *
 * @return array{added:int,skipped:int,missing_id:int,errors:int}
 */
function discogsExportEmptyCounts() {
    return [
        'added' => 0,
        'skipped' => 0,
        'missing_id' => 0,
        'errors' => 0,
    ];
}

/**
 * Merge batch counts into cumulative session totals.
 *
 * @param array $cumulative Existing totals
 * @param array $batchCounts Counts from one processed batch
 * @return array
 */
function discogsExportMergeCounts(array $cumulative, array $batchCounts) {
    foreach (discogsExportEmptyCounts() as $key => $zero) {
        if (isset($batchCounts[$key])) {
            $cumulative[$key] = (int) $cumulative[$key] + (int) $batchCounts[$key];
        }
    }
    return $cumulative;
}

/**
 * Total 1-based pages for a local export queue.
 *
 * @param int $queueLength Number of albums in the phase queue
 * @return int
 */
function discogsExportQueueTotalPages($queueLength) {
    $queueLength = (int) $queueLength;
    if ($queueLength < 1) {
        return 0;
    }
    return (int) ceil($queueLength / DISCOGS_EXPORT_BATCH_SIZE);
}

/**
 * First page to process after export start.
 *
 * @param array $queues Session queues with collection and wantlist keys
 * @return array|null Next {phase,page} or null when nothing to push
 */
function discogsExportInitialNext(array $queues) {
    $collection = isset($queues['collection']) && is_array($queues['collection'])
        ? $queues['collection']
        : [];
    $wantlist = isset($queues['wantlist']) && is_array($queues['wantlist'])
        ? $queues['wantlist']
        : [];
    if (count($collection) > 0) {
        return ['phase' => 'collection', 'page' => 1];
    }
    if (count($wantlist) > 0) {
        return ['phase' => 'wantlist', 'page' => 1];
    }
    return null;
}

/**
 * Determine the next export page after processing a local queue page.
 *
 * @param string $phase collection|wantlist
 * @param int $page Current page number (just processed)
 * @param array $queues Session queues
 * @return array|null Next {phase,page} or null when export is complete
 */
function discogsExportComputeNext($phase, $page, array $queues) {
    $page = (int) $page;
    $collection = isset($queues['collection']) && is_array($queues['collection'])
        ? $queues['collection']
        : [];
    $wantlist = isset($queues['wantlist']) && is_array($queues['wantlist'])
        ? $queues['wantlist']
        : [];
    $collectionPages = discogsExportQueueTotalPages(count($collection));
    $wantlistPages = discogsExportQueueTotalPages(count($wantlist));

    if ($phase === 'collection') {
        if ($collectionPages > 0 && $page < $collectionPages) {
            return [
                'phase' => 'collection',
                'page' => $page + 1,
            ];
        }
        if ($wantlistPages > 0) {
            return [
                'phase' => 'wantlist',
                'page' => 1,
            ];
        }
        return null;
    }

    if ($wantlistPages > 0 && $page < $wantlistPages) {
        return [
            'phase' => 'wantlist',
            'page' => $page + 1,
        ];
    }

    return null;
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
        // For POST requests, read JSON body once (php://input is not reusable).
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
        $GLOBALS['__request_csrf'] = '';
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $GLOBALS['__request_csrf'] = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (!empty($input['csrf_token'])) {
            $GLOBALS['__request_csrf'] = $input['csrf_token'];
        }
        // Action may be in query string (e.g. login) or in the JSON body.
        $action = $_GET['action'] ?? $input['action'] ?? '';
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
                            // Return the complete album data with all fields
                            $response['data'] = $album;
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
                    
                case 'auth_status':
                    $response['success'] = true;
                    $response['data'] = [
                        'authenticated' => AuthHelper::isAuthenticated(),
                        'lockout_remaining' => AuthHelper::getLockoutTimeRemaining(),
                        'csrf_token' => AuthHelper::getCsrfToken(),
                        'must_change_password' => AuthHelper::mustChangePassword(),
                    ];
                    break;
                    

                    
                case 'search_discogs_barcode':
                    $barcode = trim($_GET['barcode'] ?? '');
                    if ($barcode !== '') {
                        try {
                            $releaseInfo = $discogsAPI->getReleaseInfoByBarcode($barcode);
                            if ($releaseInfo) {
                                $response['data'] = $releaseInfo;
                                $response['success'] = true;
                            } else {
                                $response['message'] = 'No release found for this barcode.';
                            }
                        } catch (Exception $e) {
                            $response['message'] = 'Discogs barcode lookup failed: ' . $e->getMessage();
                        }
                    } else {
                        $response['message'] = 'Barcode is required.';
                    }
                    break;

                case 'search_discogs':
                    $artist = $_GET['artist'] ?? '';
                    $album = $_GET['album'] ?? '';
                    $format = $_GET['format'] ?? '';
                    $limit = (int)($_GET['limit'] ?? 8);
                    
                    if ($artist && $album) {
                        try {
                            // Initialize DiscogsAPI only when needed
                            if ($discogsAPI === null) {
                                $discogsAPI = new DiscogsAPIService();
                            }
                            $results = $discogsAPI->searchAlbumsByArtist($artist, $album, $limit);
                            
                            // Filter results by format if specified
                            if (!empty($format) && !empty($results)) {
                                $results = array_filter($results, function($item) use ($format) {
                                    if (!isset($item['format'])) {
                                        return false;
                                    }
                                    
                                    // Check if the format matches (case-insensitive)
                                    $itemFormats = is_array($item['format']) ? $item['format'] : [$item['format']];
                                    foreach ($itemFormats as $itemFormat) {
                                        if (stripos($itemFormat, $format) !== false) {
                                            return true;
                                        }
                                    }
                                    return false;
                                });
                                
                                // Re-index the array after filtering
                                $results = array_values($results);
                            }
                            
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
                        $externalArtists = $discogsAPI->searchArtists($search, 99);
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
                                
                                // Normalize format strings for better matching
                                $normalizedAlbumFormat = normalizeFormatString($albumFormat);
                                $normalizedSearchFormat = normalizeFormatString($format);
                                
                                // Ensure it's a string before using stripos
                                if (is_string($normalizedAlbumFormat) && formatMatches($normalizedAlbumFormat, $normalizedSearchFormat)) {
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
                            // Filter local albums by format if specified
                            if (!empty($format)) {
                                $albumFormat = $album['format'] ?? '';
                                $normalizedAlbumFormat = normalizeFormatString($albumFormat);
                                $normalizedSearchFormat = normalizeFormatString($format);
                                
                                if (empty($normalizedAlbumFormat) || !formatMatches($normalizedAlbumFormat, $normalizedSearchFormat)) {
                                    continue; // Skip this album if format doesn't match
                                }
                            }
                            
                            // For local albums, use the stored release_year as master_year since we store master years
                            $masterYear = $album['release_year'] ?? null;
                            
                            // Ensure consistent structure with external albums
                            $allAlbums[] = [
                                'album_name' => $album['album_name'] ?? $album['title'] ?? 'Unknown Album',
                                'year' => $album['release_year'] ?? null,
                                'artist' => $artist,
                                'master_year' => $masterYear, // Use stored master year for local albums
                                'format' => $album['format'] ?? null, // Include format for consistency
                                'cover_url' => $album['cover_url'] ?? null,
                                'cover_url_medium' => $album['cover_url_medium'] ?? $album['cover_url'] ?? null,
                                'cover_url_large' => $album['cover_url_large'] ?? $album['cover_url'] ?? null,
                                'cover_images' => $album['cover_images'] ?? (
                                    !empty($album['cover_url_large']) ? [$album['cover_url_large']] : []
                                ),
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
                                    'cover_images' => $album['cover_images'] ?? (
                                        !empty($album['cover_url_large']) ? [$album['cover_url_large']] : []
                                    ),
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
                    $response['success'] = true;
                    $response['data'] = [
                        'authenticated' => AuthHelper::isAuthenticated(),
                        'lockout_remaining' => AuthHelper::getLockoutTimeRemaining(),
                        'csrf_token' => AuthHelper::getCsrfToken(),
                        'must_change_password' => AuthHelper::mustChangePassword(),
                    ];
                    break;
                    
                case 'get_setup_status':
                    // Check API key from environment variable first, then gitignored local file
                    $apiKeySource = '';
                    $currentApiKey = '';

                    $envKey = getenv('DISCOGS_API_KEY');
                    if ($envKey === false || $envKey === '') {
                        $envKey = isset($_ENV['DISCOGS_API_KEY']) ? $_ENV['DISCOGS_API_KEY'] : '';
                    }
                    if ($envKey !== '' && $envKey !== 'your_discogs_api_key_here') {
                        $currentApiKey = $envKey;
                        $apiKeySource = 'environment';
                    } elseif (defined('DISCOGS_API_KEY')) {
                        $constantValue = DISCOGS_API_KEY;
                        if (!empty($constantValue) && $constantValue !== 'your_discogs_api_key_here') {
                            $currentApiKey = $constantValue;
                            $apiKeySource = 'local_file';
                        }
                    }
                    
                    // Mask the API key for display (show first 4 and last 4 characters)
                    if (!empty($currentApiKey)) {
                        if (strlen($currentApiKey) > 8) {
                            $displayKey = substr($currentApiKey, 0, 4) . '...' . substr($currentApiKey, -4);
                        } else {
                            $displayKey = 'Set';
                        }
                    } else {
                        $displayKey = 'Not set';
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
                    
                    $apiKeySet = !empty($currentApiKey);
                    $setupComplete = $apiKeySet; // Password is always set, so only check API key
                    
                    $response['success'] = true;
                    $response['data'] = [
                        'api_key_set' => $apiKeySet,
                        'password_set' => $passwordSet,
                        'setup_complete' => $setupComplete,
                        'current_api_key' => $displayKey,
                        'api_key_source' => $apiKeySource
                    ];
                    break;

                case 'get_discogs_import_settings':
                    discogsImportRequireAdminRead();
                    $response['success'] = true;
                    $response['data'] = [
                        'discogs_username' => discogsImportLoadSavedUsername(),
                        'api_key_set' => $discogsAPI->isAvailable(),
                        'csrf_token' => AuthHelper::getCsrfToken(),
                    ];
                    break;

                case 'get_discogs_export_settings':
                    discogsImportRequireAdminRead();
                    $tokenIdentity = $discogsAPI->isAvailable()
                        ? $discogsAPI->getTokenIdentity()
                        : ['username' => null, 'error' => null];
                    $tokenUsername = $tokenIdentity['username'];
                    $savedUsername = discogsImportLoadSavedUsername();
                    $response['success'] = true;
                    $response['data'] = [
                        'discogs_username' => $savedUsername !== '' ? $savedUsername : ($tokenUsername ? $tokenUsername : ''),
                        'token_username' => $tokenUsername,
                        'token_error' => $tokenIdentity['error'],
                        'api_key_set' => $discogsAPI->isAvailable(),
                        'csrf_token' => AuthHelper::getCsrfToken(),
                    ];
                    break;
                    
                case 'get_notifications':
                    $musicCollection = new MusicCollection();
                    $response = $musicCollection->getNotifications();
                    break;
                    
                default:
                    $response['message'] = 'Invalid action';
            }
            break;
            
        case 'POST':
            
            switch ($action) {
                case 'add':
                    AuthHelper::requireAdminAction();

                    if (isset($input['artist_name']) && isset($input['album_name'])) {
                        try {
                            // Use provided cover art URL and Discogs release ID if available
                            $coverUrl = $input['cover_url'] ?? null;
                            $coverImages = [];
                            if (!empty($input['cover_images']) && is_array($input['cover_images'])) {
                                $coverImages = $input['cover_images'];
                            } elseif (!empty($input['cover_url_large'])) {
                                // Migration compatibility for older clients.
                                $coverImages = [$input['cover_url_large']];
                            }
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
                                    if (!empty($albums[0]['cover_images']) && is_array($albums[0]['cover_images'])) {
                                        $coverImages = $albums[0]['cover_images'];
                                    } elseif (!empty($albums[0]['cover_url_large'])) {
                                        $coverImages = [$albums[0]['cover_url_large']];
                                    }
                                    $discogsReleaseId = $albums[0]['id'] ?? null;
                                }
                            }
                            
                            // Get artist type information from Discogs
                            $artistType = null;
                            if ($discogsAPI->isAvailable()) {
                                $artistInfo = $discogsAPI->getArtistInfo($input['artist_name']);
                                
                                if ($artistInfo && isset($artistInfo['type']) && $artistInfo['type'] !== 'unknown') {
                                    $artistType = $artistInfo['type'];
                                }
                                                            }
                            
                            // If we have a Discogs release ID, try to fetch additional information
                            $label = $input['label'] ?? null;
                            $producer = $input['producer'] ?? null;
                            
                            if ($discogsReleaseId && $discogsAPI->isAvailable()) {
                                $releaseInfo = $discogsAPI->getReleaseInfo($discogsReleaseId);
                                if ($releaseInfo) {
                                    // Fetch style if not provided
                                    if (!$style && !empty($releaseInfo['style'])) {
                                        $style = $releaseInfo['style'];
                                    }
                                    // Fetch label if not provided
                                    if (!$label && !empty($releaseInfo['label'])) {
                                        $label = $releaseInfo['label'];
                                    }
                                    // Fetch producer if not provided
                                    if (!$producer && !empty($releaseInfo['producer'])) {
                                        $producer = $releaseInfo['producer'];
                                    }
                                    if (!empty($releaseInfo['cover_images']) && is_array($releaseInfo['cover_images'])) {
                                        $coverImages = $releaseInfo['cover_images'];
                                    } elseif (!empty($releaseInfo['cover_url_large'])) {
                                        $coverImages = [$releaseInfo['cover_url_large']];
                                    }
                                    if (!empty($releaseInfo['cover_url'])) {
                                        $coverUrl = $releaseInfo['cover_url'];
                                    }
                                }
                            }
                            
                            $isOwned = normalizeBoolean($input['is_owned'] ?? false);
                            $wantToOwn = normalizeBoolean($input['want_to_own'] ?? false);
                            $personal = AlbumPersonalFields::normalizeFromInput($input);
                            if (!$personal['ok']) {
                                $response['success'] = false;
                                $response['message'] = $personal['error'];
                                break;
                            }
                            $replaceExisting = !empty($input['replace_existing']);
                            $keepBoth = !empty($input['keep_both']);
                            $existingAlbum = findExistingAlbumByArtistAndName(
                                $musicCollection,
                                $input['artist_name'],
                                $input['album_name']
                            );
                            
                            if ($existingAlbum && $replaceExisting) {
                                $result = $musicCollection->updateAlbum(
                                    $existingAlbum['id'],
                                    $input['artist_name'],
                                    $input['album_name'],
                                    $input['release_year'] ?? null,
                                    $isOwned,
                                    $wantToOwn,
                                    $coverUrl,
                                    $coverImages,
                                    $discogsReleaseId,
                                    $style,
                                    $input['format'] ?? null,
                                    $artistType,
                                    $label,
                                    $producer
                                );
                                if ($result) {
                                    persistAlbumPersonalFields(
                                        $musicCollection,
                                        $existingAlbum['id'],
                                        $input['artist_name'],
                                        $input['album_name'],
                                        $personal
                                    );
                                }
                                $response['success'] = $result;
                                $response['message'] = $result ? 'Album updated successfully' : 'Failed to update album';
                                $response['replaced'] = true;
                            } elseif ($existingAlbum && $keepBoth) {
                                $result = addAlbumToCollection(
                                    $musicCollection,
                                    $input['artist_name'],
                                    $input['album_name'],
                                    $input['release_year'] ?? null,
                                    $isOwned,
                                    $wantToOwn,
                                    $coverUrl,
                                    $coverImages,
                                    $discogsReleaseId,
                                    $style,
                                    $input['format'] ?? null,
                                    $artistType,
                                    $label,
                                    $producer,
                                    true,
                                    $personal['media_condition'],
                                    $personal['sleeve_condition'],
                                    $personal['notes']
                                );
                                $response['success'] = $result;
                                $response['message'] = $result ? 'Album added successfully' : 'Failed to add album';
                            } elseif ($existingAlbum) {
                                $duplicateContext = getDuplicateAlbumContext($existingAlbum, $isOwned, $wantToOwn);
                                $newAlbumPayload = buildDuplicateAlbumDisplayPayload(
                                    $input,
                                    $coverUrl,
                                    $coverImages,
                                    $discogsReleaseId,
                                    $isOwned,
                                    $wantToOwn
                                );

                                $response['success'] = false;
                                $response['duplicate_available'] = true;
                                $response['replace_available'] = true;
                                $response['duplicate_context'] = $duplicateContext;
                                $response['existing_album'] = enrichAlbumYearInfo($existingAlbum, $discogsAPI);
                                $response['new_album'] = enrichAlbumYearInfo($newAlbumPayload, $discogsAPI);
                                $response['message'] = $duplicateContext === 'want_to_owned'
                                    ? 'This album is already on your Want list.'
                                    : 'This album already exists in your collection.';
                            } else {
                                $result = addAlbumToCollection(
                                    $musicCollection,
                                    $input['artist_name'],
                                    $input['album_name'],
                                    $input['release_year'] ?? null,
                                    $isOwned,
                                    $wantToOwn,
                                    $coverUrl,
                                    $coverImages,
                                    $discogsReleaseId,
                                    $style,
                                    $input['format'] ?? null,
                                    $artistType,
                                    $label,
                                    $producer,
                                    false,
                                    $personal['media_condition'],
                                    $personal['sleeve_condition'],
                                    $personal['notes']
                                );
                                $response['success'] = $result;
                                $response['message'] = $result ? 'Album added successfully' : 'Failed to add album';
                            }
                        } catch (Exception $e) {
                            $response['success'] = false;
                            $response['message'] = $e->getMessage();
                        }
                    } else {
                        $response['message'] = 'Artist name and album name are required';
                    }
                    break;
                    
                case 'update_raw':
                    AuthHelper::requireAdminAction();

                    if (isset($input['id'])) {
                        try {
                            // Validate required fields
                            if (empty($input['artist_name']) || empty($input['album_name'])) {
                                throw new Exception('Artist name and album name are required');
                            }

                            // When personal fields are present, validate grades/notes length
                            if (array_key_exists('media_condition', $input)
                                || array_key_exists('sleeve_condition', $input)
                                || array_key_exists('notes', $input)) {
                                $personal = AlbumPersonalFields::normalizeFromInput($input);
                                if (!$personal['ok']) {
                                    throw new Exception($personal['error']);
                                }
                                $input['media_condition'] = $personal['media_condition'];
                                $input['sleeve_condition'] = $personal['sleeve_condition'];
                                $input['notes'] = $personal['notes'];
                            }
                            
                            // Update the album with raw data
                            $result = $musicCollection->updateAlbumRaw($input);
                            
                            if ($result) {
                                $response['success'] = true;
                                $response['message'] = 'Album updated successfully';
                            } else {
                                $response['success'] = false;
                                $response['message'] = 'Failed to update album';
                            }
                        } catch (Exception $e) {
                            $response['success'] = false;
                            $response['message'] = $e->getMessage();
                        }
                    } else {
                        $response['success'] = false;
                        $response['message'] = 'Album ID required';
                    }
                    break;
                    
                case 'update':
                    AuthHelper::requireAdminAction();

                    if (isset($input['id']) && isset($input['artist_name']) && isset($input['album_name'])) {
                        try {
                            $personal = AlbumPersonalFields::normalizeFromInput($input);
                            if (!$personal['ok']) {
                                $response['success'] = false;
                                $response['message'] = $personal['error'];
                                break;
                            }

                            // Use provided cover art URL and Discogs release ID if available
                            $coverUrl = $input['cover_url'] ?? null;
                            $coverImages = [];
                            if (!empty($input['cover_images']) && is_array($input['cover_images'])) {
                                $coverImages = $input['cover_images'];
                            } elseif (!empty($input['cover_url_large'])) {
                                // Migration compatibility for older clients.
                                $coverImages = [$input['cover_url_large']];
                            }
                            $discogsReleaseId = $input['discogs_release_id'] ?? null;
                            $style = $input['style'] ?? null;
                            
                            // Try to fetch cover art from Discogs if not provided
                            if (!$discogsReleaseId && $discogsAPI->isAvailable()) {
                                try {
                                    $albums = $discogsAPI->searchAlbumsByArtistForStorage($input['artist_name'], $input['album_name'], 1);
                                    if (!empty($albums)) {
                                        $coverUrl = $albums[0]['cover_url'] ?? null;
                                        if (!empty($albums[0]['cover_images']) && is_array($albums[0]['cover_images'])) {
                                            $coverImages = $albums[0]['cover_images'];
                                        } elseif (!empty($albums[0]['cover_url_large'])) {
                                            $coverImages = [$albums[0]['cover_url_large']];
                                        }
                                        $discogsReleaseId = $albums[0]['id'] ?? null;
                                    }
                                } catch (Exception $discogsError) {
                                    // Discogs API error during update, continue with update
                                }
                            }
                            
                            // Get artist type information from Discogs
                            $artistType = null;
                            if ($discogsAPI->isAvailable()) {
                                try {
                                    $artistInfo = $discogsAPI->getArtistInfo($input['artist_name']);
                                    if ($artistInfo && isset($artistInfo['type'])) {
                                        $artistType = $artistInfo['type'];
                                    }
                                } catch (Exception $discogsError) {
                                    // Discogs API error occurred
                                }
                            }
                            
                            // If we have a Discogs release ID, try to fetch additional information
                            $label = $input['label'] ?? null;
                            $producer = $input['producer'] ?? null;
                            $format = $input['format'] ?? null;
                            $releaseYear = $input['release_year'] ?? null;
                            
                            if ($discogsReleaseId && $discogsAPI->isAvailable()) {
                                try {
                                    $releaseInfo = $discogsAPI->getReleaseInfo($discogsReleaseId);
                                    if ($releaseInfo) {
                                        // Always fetch fresh style from Discogs during updates
                                        if (!empty($releaseInfo['style'])) {
                                            $style = $releaseInfo['style'];
                                        }
                                        // Always fetch fresh label from Discogs during updates
                                        if (!empty($releaseInfo['label'])) {
                                            $label = $releaseInfo['label'];
                                        }
                                        // Always fetch fresh producer from Discogs during updates
                                        if (!empty($releaseInfo['producer'])) {
                                            $producer = $releaseInfo['producer'];
                                        }
                                        // Always fetch fresh format from Discogs during updates
                                        if (!empty($releaseInfo['format'])) {
                                            $format = $releaseInfo['format'];
                                        }
                                        // Always fetch fresh year from Discogs during updates
                                        // Prioritize master year (original release) over specific release year
                                        if (!empty($releaseInfo['master_year'])) {
                                            $releaseYear = $releaseInfo['master_year'];
                                        } elseif (!empty($releaseInfo['year'])) {
                                            $releaseYear = $releaseInfo['year'];
                                        }
                                        if (!empty($releaseInfo['cover_images']) && is_array($releaseInfo['cover_images'])) {
                                            $coverImages = $releaseInfo['cover_images'];
                                        } elseif (!empty($releaseInfo['cover_url_large'])) {
                                            $coverImages = [$releaseInfo['cover_url_large']];
                                        }
                                        if (!empty($releaseInfo['cover_url'])) {
                                            $coverUrl = $releaseInfo['cover_url'];
                                        }
                                    }
                                } catch (Exception $discogsError) {
                                    // Discogs API error fetching additional info, continue with update
                                }
                            }
                            
                            $result = $musicCollection->updateAlbum(
                                $input['id'],
                                $input['artist_name'],
                                $input['album_name'],
                                $releaseYear,
                                normalizeBoolean($input['is_owned'] ?? false),
                                normalizeBoolean($input['want_to_own'] ?? false),
                                $coverUrl,
                                $coverImages,
                                $discogsReleaseId,
                                $style,
                                $format,
                                $artistType,
                                $label,
                                $producer
                            );
                            if ($result) {
                                persistAlbumPersonalFields(
                                    $musicCollection,
                                    $input['id'],
                                    $input['artist_name'],
                                    $input['album_name'],
                                    $personal
                                );
                            }
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
                    AuthHelper::requireAdminAction();

                    if (isset($input['id'])) {
                        $result = $musicCollection->deleteAlbum($input['id']);
                        $response['success'] = $result;
                        $response['message'] = $result ? 'Album deleted successfully' : 'Failed to delete album';
                    } else {
                        $response['message'] = 'Album ID required';
                    }
                    break;
                    
                case 'login':
                    AuthHelper::requireCsrf();

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

                case 'webauthn_status':
                    AuthHelper::requireCsrf();

                    $response['success'] = true;
                    $response['data'] = WebAuthnHelper::getStatus();
                    $response['message'] = 'WebAuthn status retrieved';
                    break;

                case 'webauthn_register_options':
                    AuthHelper::requireAdminAction();
                    $response = array_merge($response, WebAuthnHelper::getRegisterOptions());
                    break;

                case 'webauthn_register':
                    AuthHelper::requireAdminAction();
                    $response = array_merge($response, WebAuthnHelper::processRegister($input));
                    break;

                case 'webauthn_login_options':
                    AuthHelper::requireCsrf();
                    $response = array_merge($response, WebAuthnHelper::getLoginOptions());
                    break;

                case 'webauthn_login':
                    AuthHelper::requireCsrf();
                    $response = array_merge($response, WebAuthnHelper::processLogin($input));
                    break;

                case 'webauthn_delete':
                    AuthHelper::requireAdminAction();
                    $response = array_merge($response, WebAuthnHelper::deleteAllCredentials());
                    break;
                    
                case 'logout':
                    AuthHelper::requireCsrf();
                    AuthHelper::logout();
                    $response['success'] = true;
                    $response['message'] = 'Logged out successfully';
                    break;
                    
                case 'reset_password':
                    AuthHelper::requireAuthenticated();
                    AuthHelper::requireCsrf();

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
                        } elseif ($newPassword === 'admin123' && !AuthHelper::isDemoMode()) {
                            $response['message'] = 'Choose a password other than the default.';
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
                                            AuthHelper::clearMustChangePassword();
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
                    AuthHelper::requireAdminAction();

                    if (isset($input['discogs_api_key'])) {
                        $discogsApiKey = trim($input['discogs_api_key']);
                        
                        $envKey = getenv('DISCOGS_API_KEY');
                        if ($envKey === false || $envKey === '') {
                            $envKey = isset($_ENV['DISCOGS_API_KEY']) ? $_ENV['DISCOGS_API_KEY'] : '';
                        }
                        if ($envKey !== '' && $envKey !== 'your_discogs_api_key_here') {
                            $response['success'] = false;
                            $response['message'] = 'API key is set via environment variable and cannot be changed through this interface. To update the API key, modify the DISCOGS_API_KEY environment variable in your hosting platform.';
                        } else {
                            // Validate API key
                            if (empty($discogsApiKey)) {
                                $response['message'] = 'Discogs API key is required.';
                            } elseif (strlen($discogsApiKey) < 10) {
                                $response['message'] = 'Discogs API key appears to be too short. Please check your key.';
                            } else {
                                $localFile = __DIR__ . '/../config/api_config.local.php';
                                $payload = "<?php\nreturn [\n    'DISCOGS_API_KEY' => '" . addslashes($discogsApiKey) . "',\n];\n";
                                if (file_put_contents($localFile, $payload) !== false) {
                                    $response['success'] = true;
                                    $response['message'] = 'Discogs API key saved to config/api_config.local.php';
                                } else {
                                    $response['message'] = 'Could not write api_config.local.php. Check permissions.';
                                }
                            }
                        }
                    } else {
                        $response['message'] = 'Discogs API key is required.';
                    }
                    break;

                case 'save_discogs_import_settings':
                    AuthHelper::requireAdminAction();
                    if (!array_key_exists('discogs_username', $input)) {
                        $response['message'] = 'Discogs username is required';
                        break;
                    }
                    $saveResult = discogsImportSaveUsernameToSettings($input['discogs_username']);
                    $response['success'] = !empty($saveResult['success']);
                    $response['message'] = isset($saveResult['message']) ? $saveResult['message'] : '';
                    if ($response['success']) {
                        $response['data'] = [
                            'discogs_username' => discogsImportLoadSavedUsername(),
                        ];
                    }
                    break;

                case 'import_discogs_start':
                    AuthHelper::requireAdminAction();
                    if (!function_exists('validateDiscogsUsernameSetting')) {
                        if (!defined('MUSIC_COLLECTION_THEME_SETTINGS_LIB')) {
                            define('MUSIC_COLLECTION_THEME_SETTINGS_LIB', true);
                        }
                        require_once __DIR__ . '/theme_api.php';
                    }
                    $username = isset($input['username']) ? trim((string) $input['username']) : '';
                    if ($username === '') {
                        $response['message'] = 'Discogs username is required';
                        break;
                    }
                    $usernameCheck = validateDiscogsUsernameSetting($username);
                    if (!$usernameCheck['ok']) {
                        $response['message'] = $usernameCheck['message'];
                        break;
                    }
                    $username = $usernameCheck['value'];
                    if (!$discogsAPI->isAvailable()) {
                        $response['message'] = 'Discogs API key is not configured. Set it in Setup before importing.';
                        break;
                    }
                    if (!empty($input['save_username'])) {
                        $saveResult = discogsImportSaveUsernameToSettings($username);
                        if (empty($saveResult['success'])) {
                            $response['message'] = isset($saveResult['message'])
                                ? $saveResult['message']
                                : 'Could not save Discogs username';
                            break;
                        }
                    }
                    $_SESSION['discogs_import'] = [
                        'counts' => discogsImportEmptyCounts(),
                        'username' => $username,
                        'started' => time(),
                    ];
                    $response['success'] = true;
                    $response['message'] = 'Discogs import started';
                    $response['data'] = [
                        'phase' => 'collection',
                        'page' => 1,
                        'done' => false,
                        'next' => [
                            'phase' => 'collection',
                            'page' => 1,
                        ],
                        'counts' => discogsImportEmptyCounts(),
                        'hint' => 'POST import_discogs_page with phase and page until done is true',
                    ];
                    break;

                case 'import_discogs_page':
                    AuthHelper::requireAdminAction();
                    if (empty($_SESSION['discogs_import']) || !is_array($_SESSION['discogs_import'])) {
                        $response['message'] = 'No Discogs import in progress. Call import_discogs_start first.';
                        break;
                    }
                    $phase = isset($input['phase']) ? (string) $input['phase'] : '';
                    $page = isset($input['page']) ? (int) $input['page'] : 0;
                    $perPage = isset($input['per_page']) ? (int) $input['per_page'] : 50;
                    if (!discogsImportIsValidPhase($phase)) {
                        $response['message'] = 'Invalid import phase';
                        break;
                    }
                    if ($page < 1) {
                        $response['message'] = 'Page must be at least 1';
                        break;
                    }
                    if (!$discogsAPI->isAvailable()) {
                        $response['message'] = 'Discogs API key is not configured';
                        break;
                    }
                    $importUsername = isset($_SESSION['discogs_import']['username'])
                        ? trim((string) $_SESSION['discogs_import']['username'])
                        : '';
                    if ($importUsername === '') {
                        unset($_SESSION['discogs_import']);
                        $response['message'] = 'Import session is invalid (missing username)';
                        break;
                    }
                    try {
                        if ($phase === 'collection') {
                            $pageResult = $discogsAPI->getCollectionPage($importUsername, $page, $perPage);
                        } else {
                            $pageResult = $discogsAPI->getWantlistPage($importUsername, $page, $perPage);
                        }
                    } catch (Exception $fetchError) {
                        $response['message'] = 'Discogs fetch failed: ' . $fetchError->getMessage();
                        break;
                    }
                    $importService = new DiscogsImportService($musicCollection);
                    $processed = $importService->processPage($phase, $pageResult['releases']);
                    $sessionCounts = isset($_SESSION['discogs_import']['counts'])
                        && is_array($_SESSION['discogs_import']['counts'])
                        ? $_SESSION['discogs_import']['counts']
                        : discogsImportEmptyCounts();
                    $finalCounts = discogsImportMergeCounts($sessionCounts, $processed['counts']);
                    $_SESSION['discogs_import']['counts'] = $finalCounts;
                    $pagination = isset($pageResult['pagination']) && is_array($pageResult['pagination'])
                        ? $pageResult['pagination']
                        : ['page' => $page, 'pages' => 1, 'items' => 0];
                    $currentPage = isset($pagination['page']) ? (int) $pagination['page'] : $page;
                    $totalPages = isset($pagination['pages']) ? (int) $pagination['pages'] : 1;
                    $next = discogsImportComputeNext($phase, $currentPage, $totalPages);
                    $done = ($next === null);
                    if ($done) {
                        unset($_SESSION['discogs_import']);
                    }
                    $response['success'] = true;
                    $response['data'] = [
                        'phase' => $phase,
                        'page' => $currentPage,
                        'pages' => max(1, $totalPages),
                        'done' => $done,
                        'next' => $next,
                        'counts' => $finalCounts,
                        'errors_sample' => $processed['errors_sample'],
                    ];
                    break;

                case 'import_discogs_cancel':
                    AuthHelper::requireAdminAction();
                    unset($_SESSION['discogs_import']);
                    $response['success'] = true;
                    $response['message'] = 'Discogs import cancelled';
                    break;

                case 'export_discogs_start':
                    AuthHelper::requireAdminAction();
                    if (!function_exists('validateDiscogsUsernameSetting')) {
                        if (!defined('MUSIC_COLLECTION_THEME_SETTINGS_LIB')) {
                            define('MUSIC_COLLECTION_THEME_SETTINGS_LIB', true);
                        }
                        require_once __DIR__ . '/theme_api.php';
                    }
                    $username = isset($input['username']) ? trim((string) $input['username']) : '';
                    if ($username === '') {
                        $response['message'] = 'Discogs username is required';
                        break;
                    }
                    $usernameCheck = validateDiscogsUsernameSetting($username);
                    if (!$usernameCheck['ok']) {
                        $response['message'] = $usernameCheck['message'];
                        break;
                    }
                    $username = $usernameCheck['value'];
                    if (!$discogsAPI->isAvailable()) {
                        $response['message'] = 'Discogs API key is not configured. Set it in Setup before exporting.';
                        break;
                    }
                    $tokenIdentity = $discogsAPI->getTokenIdentity();
                    $tokenUsername = $tokenIdentity['username'];
                    if ($tokenUsername === null || $tokenUsername === '') {
                        $response['message'] = !empty($tokenIdentity['error'])
                            ? $tokenIdentity['error']
                            : 'Could not verify Discogs personal access token. Generate a user token at Discogs Developer Settings and save it in API Config.';
                        break;
                    }
                    if (strcasecmp($username, $tokenUsername) !== 0) {
                        $response['message'] = 'Export username must match your Discogs token account ('
                            . $tokenUsername
                            . '). You entered "'
                            . $username
                            . '".';
                        break;
                    }
                    if (!empty($input['save_username'])) {
                        $saveResult = discogsImportSaveUsernameToSettings($username);
                        if (empty($saveResult['success'])) {
                            $response['message'] = isset($saveResult['message'])
                                ? $saveResult['message']
                                : 'Could not save Discogs username';
                            break;
                        }
                    }
                    try {
                        $existingCollection = $discogsAPI->collectReleaseIdSet($username, 'collection');
                        $existingWantlist = $discogsAPI->collectReleaseIdSet($username, 'wantlist');
                    } catch (Exception $fetchError) {
                        $response['message'] = 'Discogs fetch failed: ' . $fetchError->getMessage();
                        break;
                    }
                    $allAlbums = $musicCollection->getAllAlbums();
                    $built = DiscogsExportService::buildQueues($allAlbums);
                    $counts = discogsExportEmptyCounts();
                    $counts['missing_id'] = (int) $built['missing_id'];
                    $queues = [
                        'collection' => $built['collection'],
                        'wantlist' => $built['wantlist'],
                    ];
                    $_SESSION['discogs_export'] = [
                        'username' => $username,
                        'counts' => $counts,
                        'queues' => $queues,
                        'existing' => [
                            'collection' => $existingCollection,
                            'wantlist' => $existingWantlist,
                        ],
                        'started' => time(),
                    ];
                    $next = discogsExportInitialNext($queues);
                    $done = ($next === null);
                    if ($done) {
                        unset($_SESSION['discogs_export']);
                    }
                    $response['success'] = true;
                    $response['message'] = 'Discogs export started';
                    $response['data'] = [
                        'phase' => $next ? $next['phase'] : 'collection',
                        'page' => $next ? (int) $next['page'] : 1,
                        'done' => $done,
                        'next' => $next,
                        'counts' => $counts,
                        'hint' => 'POST export_discogs_page with phase and page until done is true',
                    ];
                    break;

                case 'export_discogs_page':
                    AuthHelper::requireAdminAction();
                    if (empty($_SESSION['discogs_export']) || !is_array($_SESSION['discogs_export'])) {
                        $response['message'] = 'No Discogs export in progress. Call export_discogs_start first.';
                        break;
                    }
                    $phase = isset($input['phase']) ? (string) $input['phase'] : '';
                    $page = isset($input['page']) ? (int) $input['page'] : 0;
                    if (!discogsImportIsValidPhase($phase)) {
                        $response['message'] = 'Invalid export phase';
                        break;
                    }
                    if ($page < 1) {
                        $response['message'] = 'Page must be at least 1';
                        break;
                    }
                    if (!$discogsAPI->isAvailable()) {
                        $response['message'] = 'Discogs API key is not configured';
                        break;
                    }
                    $exportUsername = isset($_SESSION['discogs_export']['username'])
                        ? trim((string) $_SESSION['discogs_export']['username'])
                        : '';
                    if ($exportUsername === '') {
                        unset($_SESSION['discogs_export']);
                        $response['message'] = 'Export session is invalid (missing username)';
                        break;
                    }
                    $queues = isset($_SESSION['discogs_export']['queues'])
                        && is_array($_SESSION['discogs_export']['queues'])
                        ? $_SESSION['discogs_export']['queues']
                        : ['collection' => [], 'wantlist' => []];
                    $queue = isset($queues[$phase]) && is_array($queues[$phase])
                        ? $queues[$phase]
                        : [];
                    $offset = ($page - 1) * DISCOGS_EXPORT_BATCH_SIZE;
                    $batch = array_slice($queue, $offset, DISCOGS_EXPORT_BATCH_SIZE);
                    $existingIds = isset($_SESSION['discogs_export']['existing'][$phase])
                        && is_array($_SESSION['discogs_export']['existing'][$phase])
                        ? $_SESSION['discogs_export']['existing'][$phase]
                        : [];
                    try {
                        $processed = DiscogsExportService::processBatch(
                            $phase,
                            $batch,
                            $existingIds,
                            $discogsAPI,
                            $exportUsername
                        );
                    } catch (Exception $exportError) {
                        $response['message'] = 'Discogs export failed: ' . $exportError->getMessage();
                        break;
                    }
                    $_SESSION['discogs_export']['existing'][$phase] = $processed['existing_ids'];
                    $sessionCounts = isset($_SESSION['discogs_export']['counts'])
                        && is_array($_SESSION['discogs_export']['counts'])
                        ? $_SESSION['discogs_export']['counts']
                        : discogsExportEmptyCounts();
                    $finalCounts = discogsExportMergeCounts($sessionCounts, $processed['counts']);
                    $_SESSION['discogs_export']['counts'] = $finalCounts;
                    $totalPages = discogsExportQueueTotalPages(count($queue));
                    $next = discogsExportComputeNext($phase, $page, $queues);
                    $done = ($next === null);
                    if ($done) {
                        unset($_SESSION['discogs_export']);
                    }
                    $response['success'] = true;
                    $response['data'] = [
                        'phase' => $phase,
                        'page' => $page,
                        'pages' => max(1, $totalPages),
                        'done' => $done,
                        'next' => $next,
                        'counts' => $finalCounts,
                        'errors_sample' => $processed['errors_sample'],
                    ];
                    break;

                case 'export_discogs_cancel':
                    AuthHelper::requireAdminAction();
                    unset($_SESSION['discogs_export']);
                    $response['success'] = true;
                    $response['message'] = 'Discogs export cancelled';
                    break;

                case 'backup_download':
                    AuthHelper::requireAdminAction();
                    $includeSettings = !empty($input['include_settings']);
                    $built = CatalogBackupService::buildZip($includeSettings);
                    if (empty($built['ok'])) {
                        $response['message'] = $built['error'] ?: 'Could not build backup';
                        break;
                    }
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $built['filename'] . '"');
                    header('Content-Length: ' . strlen($built['bytes']));
                    header('Cache-Control: no-store');
                    echo $built['bytes'];
                    exit;

                case 'backup_restore':
                    AuthHelper::requireAdminAction();
                    if (empty($_FILES['backup_file']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
                        $response['message'] = 'Backup file is required';
                        break;
                    }
                    if (!empty($_FILES['backup_file']['error'])) {
                        $response['message'] = 'Upload failed';
                        break;
                    }
                    $restoreSettings = false;
                    if (isset($_POST['restore_settings'])) {
                        $restoreSettings = $_POST['restore_settings'] === '1' || $_POST['restore_settings'] === 'true';
                    } elseif (isset($input['restore_settings'])) {
                        $restoreSettings = !empty($input['restore_settings']);
                    }
                    $result = CatalogBackupService::restoreFromUpload(
                        $_FILES['backup_file']['tmp_name'],
                        isset($_FILES['backup_file']['name']) ? $_FILES['backup_file']['name'] : '',
                        $restoreSettings
                    );
                    if (empty($result['ok'])) {
                        $response['message'] = $result['error'] ?: 'Restore failed';
                        break;
                    }
                    $response['success'] = true;
                    $response['message'] = 'Backup restored';
                    $response['data'] = [
                        'album_count' => $result['album_count'],
                        'settings_restored' => !empty($result['settings_restored']),
                        'bak_files' => $result['bak_files'],
                    ];
                    break;
                    
                case 'reset_demo':
                    if (!AuthHelper::isDemoMode()) {
                        $response['message'] = 'Demo reset not available';
                        break;
                    }
                    AuthHelper::requireCsrf();

                    // Reset password to default
                    $newPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
                    
                    // Read the current auth config file
                    $authConfigContent = file_get_contents(__DIR__ . '/../config/auth_config.php');
                    
                    // Use line-by-line replacement for more reliability
                    $lines = explode("\n", $authConfigContent);
                    $newLines = [];
                    
                    foreach ($lines as $line) {
                        if (strpos($line, "define('ADMIN_PASSWORD_HASH'") === 0) {
                            $newLines[] = "define('ADMIN_PASSWORD_HASH', '" . $newPasswordHash . "');";
                        } else {
                            $newLines[] = $line;
                        }
                    }
                    
                    $newContent = implode("\n", $newLines);
                    
                    // Write the updated content back
                    file_put_contents(__DIR__ . '/../config/auth_config.php', $newContent);
                    
                    // Reset demo data
                    $demoData = [
                        'albums' => [
                            '0' => [
                                'id' => 1,
                                'artist_name' => 'Radiohead',
                                'album_name' => 'OK Computer',
                                'release_year' => '1997',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/pfroyXpbmYcY-VAwtehfWCJTkFp846Z83468DHSuckY/rs:fit/g:sm/q:90/h:591/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTIzNzIz/MTk4LTE3MTkwNzg1/MDktMTM2Mi5qcGVn.jpeg',
                                'discogs_release_id' => 23723198,
                                'created_date' => '2025-08-11 13:17:15',
                                'updated_date' => '2025-09-04 01:14:00',
                                'style' => 'Alternative Rock',
                                'format' => 'Vinyl,LP,Album,Reissue,Stereo',
                                'artist_type' => 'Group',
                                'label' => 'XL Recordings',
                                'producer' => 'Andi Watson'
                            ],
                            '1' => [
                                'id' => 2,
                                'artist_name' => 'Scrawl',
                                'album_name' => 'Velvet Hammer',
                                'release_year' => '1993',
                                'is_owned' => 0,
                                'want_to_own' => 1,
                                'cover_url' => 'https://i.discogs.com/oY5SURjJtbTVqheVz8uy1u3YVfOSnvUu1rAO9kaxFQE/rs:fit/g:sm/q:90/h:583/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTY0Nzc5/NC0xNjkwNDE2ODIx/LTczNzcuanBlZw.jpeg',
                                'discogs_release_id' => 647794,
                                'created_date' => '2025-08-11 13:17:52',
                                'updated_date' => '2025-09-04 01:14:16',
                                'style' => 'Indie Rock',
                                'format' => 'Vinyl,LP,Album',
                                'artist_type' => 'Group',
                                'label' => 'Simple Machines',
                                'producer' => 'Steve Albini'
                            ],
                            '2' => [
                                'id' => 3,
                                'artist_name' => 'The Velvet Underground',
                                'album_name' => 'The Velvet Underground & Nico',
                                'release_year' => '1967',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/e0ZjfF8_hxL_mo0Ci60dgrRYtkx2ILwTux4btdFDgYk/rs:fit/g:sm/q:90/h:610/w:599/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTE2OTU0/MDQxLTE2MTQzOTEy/NDEtNjg3Ni5qcGVn.jpeg',
                                'discogs_release_id' => 16954041,
                                'created_date' => '2025-08-11 13:18:22',
                                'updated_date' => '2025-09-04 01:15:07',
                                'style' => 'Art Rock, Psychedelic Rock, Experimental',
                                'format' => 'Vinyl,LP,Album,Reissue,Stereo',
                                'artist_type' => 'Group',
                                'label' => 'Verve Records',
                                'producer' => 'Andy Warhol'
                            ],
                            '10' => [
                                'id' => 11,
                                'artist_name' => 'R.E.M.',
                                'album_name' => 'Reckoning',
                                'release_year' => '1984',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/w7m6c1jYOb3gNlhadAZuRziQxOuWb3VsXyOv2nRZRLg/rs:fit/g:sm/q:90/h:603/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTQxNDcy/MC0xNDI1NjgwNjY4/LTgzNzcuanBlZw.jpeg',
                                'discogs_release_id' => 414720,
                                'style' => 'Alternative Rock, Indie Rock, Jangle Pop',
                                'created_date' => '2025-08-22 19:30:52',
                                'updated_date' => '2025-09-04 01:13:40',
                                'format' => 'Vinyl,LP,Album',
                                'artist_type' => 'Group',
                                'label' => 'I.R.S. Records',
                                'producer' => 'Don Dixon, Mitch Easter'
                            ],
                            '17' => [
                                'id' => 21,
                                'artist_name' => 'Archers Of Loaf',
                                'album_name' => 'The Loaf\'s Revenge',
                                'release_year' => '1993',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/b2RhMXV-ted0qpbV0bTJG-3qmDoT6r0rSLH79Z_7YYA/rs:fit/g:sm/q:90/h:595/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTUyNDM5/NS0xMTU4MDY1MDQy/LmpwZWc.jpeg',
                                'discogs_release_id' => 524395,
                                'style' => 'Indie Rock',
                                'created_date' => '2025-08-23 19:16:53',
                                'updated_date' => '2025-09-04 01:11:53',
                                'format' => 'Vinyl,7",45 RPM',
                                'artist_type' => 'Group',
                                'label' => 'Alias',
                                'producer' => 'Archers Of Loaf, Caleb Southern'
                            ],
                            '19' => [
                                'id' => 24,
                                'artist_name' => 'Elvis Costello & The Attractions',
                                'album_name' => 'This Year\'s Model',
                                'release_year' => '1978',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/PthUds0tVy-dMR5zrUTikE3cKxwMhd80fnEco9HPvRg/rs:fit/g:sm/q:90/h:600/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTE5MzAz/NDE0LTE2MjQ4NzM1/NzEtNDM1NC5qcGVn.jpeg',
                                'discogs_release_id' => 19303414,
                                'style' => 'New Wave, Power Pop, Punk',
                                'created_date' => '2025-08-23 22:09:49',
                                'updated_date' => '2025-09-04 01:12:59',
                                'format' => 'Vinyl,LP,Album,Stereo',
                                'artist_type' => 'Group',
                                'label' => 'Radar Records (5)',
                                'producer' => 'Nick Lowe'
                            ],
                            '29' => [
                                'id' => 43,
                                'artist_name' => 'David Bowie',
                                'album_name' => 'Low',
                                'release_year' => '1977',
                                'is_owned' => 0,
                                'want_to_own' => 1,
                                'cover_url' => 'https://i.discogs.com/fcd6DN-Egh92gVBfcYbkpz6xDE10IPb2D-wBnu3mpS8/rs:fit/g:sm/q:90/h:587/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTExNTk3/MTEyLTE1MTkzMTY1/NzctODEzNS5qcGVn.jpeg',
                                'discogs_release_id' => 11597112,
                                'style' => 'Art Rock, Experimental, Ambient',
                                'format' => 'Vinyl,LP,Album,Reissue,Remastered',
                                'artist_type' => 'Person',
                                'created_date' => '2025-08-30 16:36:14',
                                'updated_date' => '2025-09-04 01:12:34',
                                'label' => 'Parlophone',
                                'producer' => 'David Bowie, Tony Visconti'
                            ],
                            '31' => [
                                'id' => 46,
                                'artist_name' => 'The Smiths',
                                'album_name' => 'The Smiths',
                                'release_year' => '1984',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/MEQsW4A2PZkbJRxVrx2V_BK5qS_sd0bcaZDRpCCF-ns/rs:fit/g:sm/q:90/h:938/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTMxMjgy/MzQtMTU1MTIwMzk4/OS05Nzg4LmpwZWc.jpeg',
                                'discogs_release_id' => 3128234,
                                'style' => 'Indie Rock',
                                'format' => 'Cassette,Album',
                                'artist_type' => 'Group',
                                'created_date' => '2025-08-31 16:04:15',
                                'updated_date' => '2025-09-04 01:14:34',
                                'label' => 'Sire',
                                'producer' => 'John Porter'
                            ],
                            '33' => [
                                'id' => 50,
                                'artist_name' => 'The The',
                                'album_name' => 'Uncertain Smile',
                                'release_year' => '1982',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/TOk5NObL7jaVT0Hm_ra4k7vizVYlk63Amp7s9KnKAIQ/rs:fit/g:sm/q:90/h:600/w:579/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTE0OTU3/NC0xMTI0OTgzOTcx/LmpwZw.jpeg',
                                'discogs_release_id' => 149574,
                                'style' => 'Synth-pop',
                                'format' => 'Vinyl,12",45 RPM,Maxi-Single',
                                'artist_type' => 'Person',
                                'created_date' => '2025-09-03 12:52:48',
                                'updated_date' => '2025-09-04 01:14:50',
                                'label' => 'Sire',
                                'producer' => 'Mike Thorne'
                            ],
                            '34' => [
                                'id' => 51,
                                'artist_name' => 'Archers Of Loaf',
                                'album_name' => 'White Trash Heroes',
                                'release_year' => '1998',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/H9Q-V1hMxNcm_zgP40VYlrLM7PQACw-6cPZaGN51Bbs/rs:fit/g:sm/q:90/h:530/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTg2MDI4/Ny0xNzQ2NTYzNzk0/LTMwODQuanBlZw.jpeg',
                                'discogs_release_id' => 860287,
                                'style' => 'Indie Rock',
                                'format' => 'CD,Album',
                                'artist_type' => 'Group',
                                'created_date' => '2025-09-03 13:15:14',
                                'updated_date' => '2025-09-04 01:12:06',
                                'label' => 'Alias',
                                'producer' => 'Archers Of Loaf, Brian Paulson'
                            ],
                            '35' => [
                                'id' => 55,
                                'artist_name' => 'The The',
                                'album_name' => 'We Can\'t Stop What\'s Coming',
                                'release_year' => '2017',
                                'is_owned' => 1,
                                'want_to_own' => 0,
                                'cover_url' => 'https://i.discogs.com/Lj4jhgG9kG_S0rbyXP18O_-YzH5bM87zIdFThaEz43Q/rs:fit/g:sm/q:90/h:604/w:600/czM6Ly9kaXNjb2dz/LWRhdGFiYXNlLWlt/YWdlcy9SLTEwMTcy/NTkyLTE0OTUwNTA5/MjQtMzkyMS5qcGVn.jpeg',
                                'discogs_release_id' => 10172592,
                                'style' => 'Alternative Rock',
                                'format' => 'Vinyl,7",45 RPM,Single Sided,Record Store Day,Single,Etched,Limited Edition',
                                'artist_type' => 'Person',
                                'label' => 'Cinéola',
                                'producer' => 'Matt Johnson',
                                'created_date' => '2025-09-04 21:28:06',
                                'updated_date' => '2025-09-04 21:28:06'
                            ]
                        ],
                        'next_id' => 56
                    ];
                    
                    file_put_contents(__DIR__ . '/../data/music_collection.json', json_encode($demoData, JSON_PRETTY_PRINT));
                    
                    // Add notification for all users
                    $notificationsFile = __DIR__ . '/../data/notifications.json';
                    $notifications = json_decode(file_get_contents($notificationsFile), true);
                    
                    $notification = [
                        'id' => ++$notifications['last_notification_id'],
                        'type' => 'demo_reset',
                        'message' => 'Demo has been reset! Password restored to admin123 and sample data refreshed. Closing this notification will log you out and reload the page.',
                        'timestamp' => time(),
                        'expires' => time() + 300 // 5 minutes
                    ];
                    
                    $notifications['notifications'][] = $notification;
                    
                    // Keep only recent notifications (last 10)
                    if (count($notifications['notifications']) > 10) {
                        $notifications['notifications'] = array_slice($notifications['notifications'], -10);
                    }
                    
                    file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
                    
                    $response['success'] = true;
                    $response['message'] = 'Demo reset successfully! Password restored to admin123 and sample data refreshed.';
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