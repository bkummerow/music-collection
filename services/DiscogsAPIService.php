<?php
/**
 * Discogs API Service
 * Handles album searches and cover art retrieval from Discogs
 */

require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/ImageOptimizationService.php';

class DiscogsAPIService {
    private $apiKey;
    private $userAgent;
    private $baseUrl = 'https://api.discogs.com';
    private static $lastRequestTime = 0;
    private static $requestDelay = 1000000; // 1 second in microseconds
    private static $cache = [];
    private static $cacheExpiry = 3600; // 1 hour cache cache
    
    public function __construct() {
        $this->apiKey = DISCOGS_API_KEY;
        $this->userAgent = DISCOGS_USER_AGENT;
    }
    
    /**
     * Check if Discogs API is available
     */
    public function isAvailable() {
        return !empty($this->apiKey) && $this->apiKey !== 'YOUR_DISCOGS_API_KEY_HERE';
    }
    
    /**
     * Clean artist name by removing Discogs identifiers
     */
    private function cleanArtistName($artistName) {
        // Remove Discogs identifiers like (8), (2), etc.
        return preg_replace('/\s*\(\d+\)\s*$/', '', $artistName);
    }
    
    /**
     * Search for artists
     */
    public function searchArtists($query, $limit = 99) {
        if (!$this->isAvailable()) {
            return [];
        }
        
        try {
            $url = $this->baseUrl . '/database/search';
            $params = [
                'q' => $query,
                'type' => 'artist',
                'per_page' => $limit,
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['results'])) {
                return array_map(function($artist) {
                    return [
                        'artist_name' => $this->cleanArtistName($artist['title']),
                        'id' => $artist['id'],
                        'type' => 'artist'
                    ];
                }, $response['results']);
            }
        } catch (Exception $e) {
            // API call failed, return null
            // API call failed, return empty results
        }
        
        return [];
    }
    
    /**
     * Search for albums by artist and album name
     */
    public function searchAlbumsByArtist($artistName, $query = '', $limit = 10) {
        if (!$this->isAvailable()) {
            return [];
        }

        $results = [];
        
        // If artist and album name are the same, use more targeted search strategies
        if (strtolower(trim($artistName)) === strtolower(trim($query))) {
            // Strategy 1: Search with "artist - album" format (most common Discogs format)
            $searchQuery1 = "$artistName - $query";
            $results1 = $this->performDirectSearch($searchQuery1, $limit, $query);
            $results = array_merge($results, $results1);
            
            // Strategy 2: Search with quoted album name for exact match
            $searchQuery2 = "$artistName \"$query\"";
            $results2 = $this->performDirectSearch($searchQuery2, $limit, $query);
            $results = array_merge($results, $results2);
            
            // Strategy 3: Search with artist name and album name separated
            $searchQuery3 = "$artistName $query";
            $results3 = $this->performDirectSearch($searchQuery3, $limit, $query);
            $results = array_merge($results, $results3);
        } else {
            // Use performDirectSearch for better results - simplified for performance
            $searchQuery = "$artistName $query";
            $results = $this->performDirectSearch($searchQuery, $limit, $query);
        }
        
        // Remove duplicates and limit results
        $uniqueResults = [];
        $seenIds = [];
        $duplicateCount = 0;
        foreach ($results as $result) {
            if (!isset($seenIds[$result['id']])) {
                $uniqueResults[] = $result;
                $seenIds[$result['id']] = true;
            } else {
                $duplicateCount++;
            }
            if (count($uniqueResults) >= $limit) {
                break;
            }
        }
        
        return $uniqueResults;
    }
    
    /**
     * Search albums by artist for storage (returns original URLs)
     */
    public function searchAlbumsByArtistForStorage($artistName, $query = '', $limit = 10) {
        if (!$this->isAvailable()) {
            return [];
        }

        try {
            $results = [];
            
            // For Various Artists, be more flexible in search
            if (strtolower($artistName) === 'various' || strtolower($artistName) === 'various artists') {
                // Search 1: Try with "Various" + album name
                $searchQuery1 = "Various $query";
                $results1 = $this->performDirectSearchForStorage($searchQuery1, $limit);
                $results = array_merge($results, $results1);
                
                // Search 2: Try with just the album name (for Various Artists releases)
                if (!empty($query)) {
                    $results2 = $this->performDirectSearchForStorage($query, $limit);
                    $results = array_merge($results, $results2);
                }
                
                // Search 3: Try with "Various Artists" + album name
                $searchQuery3 = "Various Artists $query";
                $results3 = $this->performDirectSearchForStorage($searchQuery3, $limit);
                $results = array_merge($results, $results3);
            } else {
                // Use performDirectSearch for better results
                $searchQuery = "$artistName $query";
                $results = $this->performDirectSearchForStorage($searchQuery, $limit, $query);
            }
            
            // Remove duplicates and limit results
            $uniqueResults = [];
            $seenIds = [];
            foreach ($results as $result) {
                if (!isset($seenIds[$result['id']])) {
                    $uniqueResults[] = $result;
                    $seenIds[$result['id']] = true;
                }
                if (count($uniqueResults) >= $limit) {
                    break;
                }
            }
            
            return $uniqueResults;
            
        } catch (Exception $e) {
            // API call failed, return null
            // API call failed, return empty results
        }
        
        return [];
    }
    
    /**
     * Perform a single search request
     */
    private function performSearch($searchQuery, $limit, $artistName = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit,
            'token' => $this->apiKey
        ];
        
        $response = $this->makeRequest($url, $params);
        
        if ($response && isset($response['results'])) {
            $filteredResults = [];
            
            foreach ($response['results'] as $release) {
                // Extract artist and album name from the title
                $title = $release['title'];
                $artist = $release['artist'] ?? '';
                
                // If artist field is empty, try to extract it from the title
                if (empty($artist)) {
                    // Try to find common separators to split artist and album
                    $separators = [' - ', ' – ', ' / ', ' : '];
                    $albumName = $title;
                    
                    foreach ($separators as $separator) {
                        $parts = explode($separator, $title);
                        if (count($parts) > 1) {
                            $artist = trim($parts[0]);
                            $albumName = trim($parts[1]);
                            break;
                        }
                    }
                    
                    // If no separator found, assume the whole title is the album name
                    if (empty($artist)) {
                        $albumName = $title;
                    }
                } else {
                    // If we have an artist field, extract album name from title
                    $albumName = $title;
                    if (stripos($title, $artist) === 0) {
                        $albumName = trim(substr($title, strlen($artist)));
                        // Remove any leading separators like " - " or " – "
                        $albumName = preg_replace('/^[\s\-\–]+/', '', $albumName);
                    }
                    
                    // If we still have the artist name in the title, try to extract it
                    if (empty($albumName) || $albumName === $title) {
                        // Try to find common separators
                        $separators = [' - ', ' – ', ' / ', ' : '];
                        foreach ($separators as $separator) {
                            $parts = explode($separator, $title);
                            if (count($parts) > 1) {
                                $albumName = trim($parts[1]);
                                break;
                            }
                        }
                    }
                }
                
                // If we couldn't extract a clean album name, use the full title
                if (empty($albumName)) {
                    $albumName = $title;
                }
                
                // Split search query into individual terms
                $searchTerms = array_filter(array_map('trim', explode(' ', $searchQuery)));
                
                // Check if ALL search terms appear in the album name (case insensitive)
                $albumNameLower = strtolower($albumName);
                $allTermsFound = true;
                $hasSearchTerms = false;
                foreach ($searchTerms as $term) {
                    if (!empty($term)) {
                        $hasSearchTerms = true;
                        if (strpos($albumNameLower, $term) === false) {
                            $allTermsFound = false;
                            break;
                        }
                    }
                }
                if (!$hasSearchTerms) { // If no search terms (artist-only search), don't filter by album title
                    $allTermsFound = true;
                }
                
                // Also check if the artist matches (case insensitive)
                $artistMatches = false;
                if (!empty($artistName)) {
                    // Check if the search query contains the artist name (most reliable method)
                    $searchQueryLower = strtolower($searchQuery);
                    $searchArtistLower = strtolower($artistName);
                    
                    // Try exact match first
                    $artistMatches = strpos($searchQueryLower, $searchArtistLower) !== false;
                    
                    // If that doesn't work, try partial matches (e.g., "nick cave" in "nick cave & the bad seeds")
                    if (!$artistMatches) {
                        $artistWords = explode(' ', $searchArtistLower);
                        $mainArtistWords = array_slice($artistWords, 0, 2); // Take first 2 words (e.g., "nick cave")
                        $mainArtistPhrase = implode(' ', $mainArtistWords);
                        $artistMatches = strpos($searchQueryLower, $mainArtistPhrase) !== false;
                    }
                    
                    // If that doesn't work, try the artist field from the API response
                    if (!$artistMatches && !empty($artist)) {
                        $artistLower = strtolower($artist);
                        $artistMatches = strpos($artistLower, $searchArtistLower) !== false || 
                                       strpos($searchArtistLower, $artistLower) !== false;
                    }
                    
                    // If artist field is empty but search query contains artist name, assume it matches
                    if (!$artistMatches && empty($artist) && strpos($searchQueryLower, $searchArtistLower) !== false) {
                        $artistMatches = true;
                    }
                } else {
                    $artistMatches = true; // If no artist name provided, assume it matches
                }
                
                // Only include results where all search terms appear in the album title AND artist matches
                if ($allTermsFound && $artistMatches) {
                    $filteredResults[] = [
                        'id' => $release['id'],
                        'title' => $albumName,
                        'artist' => $artist,
                        'year' => $release['year'] ?? null,
                                            'cover_url' => $this->getCoverArtForSize($release, 'thumbnail'),
                    'cover_url_medium' => $this->getCoverArtForSize($release, 'medium'),
                    'cover_url_large' => $this->getCoverArtForSize($release, 'large'),
                        'type' => 'album'
                    ];
                }
            }
            
            return $filteredResults;
        }
        
        return [];
    }
    
    /**
     * Perform a direct search for autocomplete (optimized for speed)
     */
    public function performDirectSearch($searchQuery, $limit = 99, $albumSearchTerm = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit, // Reduced for better performance
            'token' => $this->apiKey
        ];
        
        // If we have a specific album search term, try to make the search more specific
        if (!empty($albumSearchTerm)) {
            // Use quotes around the album search term to make it more exact
            $params['q'] = str_replace($albumSearchTerm, '"' . $albumSearchTerm . '"', $searchQuery);
        }
        
        $response = $this->makeRequest($url, $params);
        
        if ($response && isset($response['results'])) {
            $results = [];
            
            // Extract the artist name from the search query for filtering
            $searchParts = explode(' ', $searchQuery);
            $expectedArtist = '';
            if (count($searchParts) > 1) {
                // Assume the first part is the artist name
                $expectedArtist = strtolower(trim($searchParts[0]));
            }
            
            foreach ($response['results'] as $release) {
                // If we have a specific album search term, filter by it
                if (!empty($albumSearchTerm)) {
                    $title = strtolower($release['title']);
                    $albumSearchLower = strtolower($albumSearchTerm);
                    
                    // Check for exact word match (not just substring)
                    $words = explode(' ', $title);
                    $hasExactMatch = false;
                    foreach ($words as $word) {
                        $word = trim($word);
                        if ($word === $albumSearchLower) {
                            $hasExactMatch = true;
                            break;
                        }
                    }
                    
                    // Check for substring match
                    $hasSubstringMatch = strpos($title, $albumSearchLower) !== false;
                    
                    // If no exact word match and no substring match, skip
                    if (!$hasExactMatch && !$hasSubstringMatch) {
                        continue;
                    }
                }
                
                // Extract artist and album name from the title
                $title = $release['title'];
                $artist = $release['artist'] ?? '';
                
                // If artist field is empty, try to extract it from the title
                if (empty($artist)) {
                    // Try to find common separators to split artist and album
                    $separators = [' - ', ' – ', ' / ', ' : '];
                    $albumName = $title;
                    
                    foreach ($separators as $separator) {
                        $parts = explode($separator, $title);
                        if (count($parts) > 1) {
                            $artist = trim($parts[0]);
                            $albumName = trim($parts[1]);
                            break;
                        }
                    }
                    
                    // If no separator found, assume the whole title is the album name
                    if (empty($artist)) {
                        $albumName = $title;
                    }
                } else {
                    // If we have an artist field, extract album name from title
                    $albumName = $title;
                    if (stripos($title, $artist) === 0) {
                        $albumName = trim(substr($title, strlen($artist)));
                        // Remove any leading separators like " - " or " – "
                        $albumName = preg_replace('/^[-\s–—]+/', '', $albumName);
                    }
                }
                
                // If we couldn't extract a clean album name, use the full title
                if (empty($albumName)) {
                    $albumName = $title;
                }
                
                // Filter by artist if we have an expected artist
                if (!empty($expectedArtist)) {
                    $extractedArtist = strtolower(trim($artist));
                    
                    // Use very strict artist matching - require exact match or artist name contains the expected artist
                    // This prevents results like "Tom Jones" when searching for "Green"
                    if ($extractedArtist !== $expectedArtist && !str_contains($extractedArtist, $expectedArtist)) {
                        // Skip this result if the artist doesn't match
                        continue;
                    }
                    
                    // Additional check: if the expected artist is a single word, ensure it's not just a partial match
                    // This prevents "Green Day" from matching when searching for "Green"
                    if (strpos($expectedArtist, ' ') === false) {
                        // Single word artist search - check if the extracted artist starts with the expected artist
                        $artistWords = explode(' ', $extractedArtist);
                        $firstWord = $artistWords[0];
                        if ($firstWord !== $expectedArtist && !str_starts_with($firstWord, $expectedArtist)) {
                            continue; // Skip if the first word doesn't match exactly
                        }
                    }
                }
                
                // Extract format information from the release
                $formatInfo = '';
                if (isset($release['format'])) {
                    $formatInfo = $release['format'];
                } elseif (isset($release['formats']) && is_array($release['formats'])) {
                    $formatInfo = $this->extractFormatDetails($release['formats']);
                }
                
                // Skip master year fetching for autocomplete performance
                $masterYear = null;
                
                $results[] = [
                    'id' => $release['id'],
                    'title' => $albumName,
                    'artist' => $artist,
                    'year' => $release['year'] ?? null,
                    'master_year' => $masterYear,
                    'format' => $formatInfo,
                    'cover_url' => $this->getCoverArtFast($release),
                    'cover_url_medium' => $this->getCoverArtFast($release),
                    'cover_url_large' => $this->getCoverArtFast($release),
                    'type' => 'album'
                ];
                
                // Stop if we've reached the limit
                if (count($results) >= $limit) {
                    break;
                }
            }
            
            // Sort results by year (older first) for autocomplete performance
            usort($results, function($a, $b) {
                return ($a['year'] ?? 0) - ($b['year'] ?? 0);
            });
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Perform a direct search for storage (returns original URLs) - optimized for speed
     */
    public function performDirectSearchForStorage($searchQuery, $limit = 10, $albumSearchTerm = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit, // Reduced for better performance
            'token' => $this->apiKey
        ];
        
        // If we have a specific album search term, try to make the search more specific
        if (!empty($albumSearchTerm)) {
            // Use quotes around the album search term to make it more exact
            $params['q'] = str_replace($albumSearchTerm, '"' . $albumSearchTerm . '"', $searchQuery);
        }
        
        $response = $this->makeRequest($url, $params);
        
        if ($response && isset($response['results'])) {
            $results = [];
            
            // Extract the artist name from the search query for filtering
            $searchParts = explode(' ', $searchQuery);
            $expectedArtist = '';
            if (count($searchParts) > 1) {
                // Assume the first part is the artist name
                $expectedArtist = strtolower(trim($searchParts[0]));
            }
            
            foreach ($response['results'] as $release) {
                // If we have a specific album search term, filter by it
                if (!empty($albumSearchTerm)) {
                    $title = strtolower($release['title']);
                    $albumSearchLower = strtolower($albumSearchTerm);
                    
                    // Check for exact word match (not just substring)
                    $words = explode(' ', $title);
                    $hasExactMatch = false;
                    foreach ($words as $word) {
                        $word = trim($word);
                        if ($word === $albumSearchLower) {
                            $hasExactMatch = true;
                            break;
                        }
                    }
                    
                    // Check for substring match
                    $hasSubstringMatch = strpos($title, $albumSearchLower) !== false;
                    
                    // If no exact word match and no substring match, skip
                    if (!$hasExactMatch && !$hasSubstringMatch) {
                        continue;
                    }
                }
                // Extract artist and album name from the title
                $title = $release['title'];
                $artist = $release['artist'] ?? '';
                
                // If artist field is empty, try to extract it from the title
                if (empty($artist)) {
                    // Try to find common separators to split artist and album
                    $separators = [' - ', ' – ', ' / ', ' : '];
                    $albumName = $title;
                    
                    foreach ($separators as $separator) {
                        $parts = explode($separator, $title);
                        if (count($parts) > 1) {
                            $artist = trim($parts[0]);
                            $albumName = trim($parts[1]);
                            break;
                        }
                    }
                    
                    // If no separator found, assume the whole title is the album name
                    if (empty($artist)) {
                        $albumName = $title;
                    }
                } else {
                    // If we have an artist field, extract album name from title
                    $albumName = $title;
                    if (stripos($title, $artist) === 0) {
                        $albumName = trim(substr($title, strlen($artist)));
                        // Remove any leading separators like " - " or " – "
                        $albumName = preg_replace('/^[-\s–—]+/', '', $albumName);
                    }
                }
                
                // If we couldn't extract a clean album name, use the full title
                if (empty($albumName)) {
                    $albumName = $title;
                }
                
                // Filter by artist if we have an expected artist
                if (!empty($expectedArtist)) {
                    $extractedArtist = strtolower(trim($artist));
                    
                    // Use very strict artist matching - require exact match or artist name contains the expected artist
                    // This prevents results like "Tom Jones" when searching for "Green"
                    if ($extractedArtist !== $expectedArtist && !str_contains($extractedArtist, $expectedArtist)) {
                        // Skip this result if the artist doesn't match
                        continue;
                    }
                    
                    // Additional check: if the expected artist is a single word, ensure it's not just a partial match
                    // This prevents "Green Day" from matching when searching for "Green"
                    if (strpos($expectedArtist, ' ') === false) {
                        // Single word artist search - check if the extracted artist starts with the expected artist
                        $artistWords = explode(' ', $extractedArtist);
                        $firstWord = $artistWords[0];
                        if ($firstWord !== $expectedArtist && !str_starts_with($firstWord, $expectedArtist)) {
                            continue; // Skip if the first word doesn't match exactly
                        }
                    }
                }
                
                // Extract format information from the release
                $formatInfo = '';
                if (isset($release['format'])) {
                    $formatInfo = $release['format'];
                } elseif (isset($release['formats']) && is_array($release['formats'])) {
                    $formatInfo = $this->extractFormatDetails($release['formats']);
                }
                
                $results[] = [
                    'id' => $release['id'],
                    'title' => $albumName,
                    'artist' => $artist,
                    'year' => $release['year'] ?? null,
                    'master_year' => null, // Will be fetched when needed
                    'format' => $formatInfo,
                    'cover_url' => $this->getCoverArtForSize($release, 'large'), // Store original URL
                    'type' => 'album'
                ];
                
                // Stop if we've reached the limit
                if (count($results) >= $limit) {
                    break;
                }
            }
            
            // Sort results by year (older first) for autocomplete performance
            usort($results, function($a, $b) {
                return ($a['year'] ?? 0) - ($b['year'] ?? 0);
            });
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Get cover art for a release (fast version without validation)
     */
    private function getCoverArtFast($release) {
        // Try different possible cover art fields, prioritizing uri150 for thumbnails
        $coverFields = ['uri150', 'cover_image', 'thumb', 'image'];
        
        foreach ($coverFields as $field) {
            if (isset($release[$field]) && !empty($release[$field])) {
                $coverUrl = $release[$field];
                
                // Force HTTPS for the cover URL
                $coverUrl = ImageOptimizationService::forceHttps($coverUrl);
                
                // Skip validation for speed - just return the URL
                return $coverUrl;
            }
        }
        
        return null;
    }
    
    /**
     * Get cover art for a release (full size)
     */
    private function getCoverArt($release) {
        // Try different possible cover art fields, prioritizing full-size images
        $coverFields = ['cover_image', 'thumb', 'image', 'uri150'];
        
        foreach ($coverFields as $field) {
            if (isset($release[$field]) && !empty($release[$field])) {
                $coverUrl = $release[$field];
                
                // Force HTTPS for the cover URL
                $coverUrl = ImageOptimizationService::forceHttps($coverUrl);
                
                // Check if the image URL is valid
                if ($this->isValidImageUrl($coverUrl)) {
                    return $coverUrl;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get optimized cover art URL for specific size
     */
    private function getCoverArtForSize($release, $size = 'thumbnail') {
        // Map sizes to Discogs URI fields
        $sizeMap = [
            'thumbnail' => ['uri150', 'thumb'],
            'medium' => ['uri500', 'uri150', 'thumb'],
            'large' => ['cover_image', 'image', 'uri500', 'uri150']
        ];
        
        $fields = $sizeMap[$size] ?? ['cover_image', 'thumb', 'image'];
        
        foreach ($fields as $field) {
            if (isset($release[$field]) && !empty($release[$field])) {
                $coverUrl = $release[$field];
                
                // Force HTTPS for the cover URL
                $coverUrl = ImageOptimizationService::forceHttps($coverUrl);
                
                // Skip validation for speed - just return the URL
                return $coverUrl;
            }
        }
        
        // Fallback to the fast method
        return $this->getCoverArtFast($release);
    }
    
    /**
     * Check if an image URL is valid
     */
    private function isValidImageUrl($url) {
        if (empty($url) || $url === 'https://img.discogs.com/') {
            return false;
        }
        
        // Force HTTPS for validation
        $url = ImageOptimizationService::forceHttps($url);
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['User-Agent: ' . $this->userAgent]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            // API call failed, return null
            return false;
        }
    }
    
    /**
     * Make HTTP request to Discogs API with rate limiting and retry logic
     */
    private function makeRequest($url, $params = [], $retryCount = 0) {
        // Rate limiting: ensure we don't make requests too frequently
        // Skip rate limiting for the first request to avoid hanging during initialization
        if (self::$lastRequestTime > 0) {
            $this->enforceRateLimit();
        }
        
        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json'
        ];
        
        $fullUrl = $url;
        if (!empty($params)) {
            $fullUrl .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        // Handle rate limiting (429) with custom retry intervals
        if ($httpCode === 429 && $retryCount < 3) {
            $retryDelays = [1, 3, 6]; // 1, 3, 6 seconds
            $waitTime = $retryDelays[$retryCount];
            sleep($waitTime);
            return $this->makeRequest($url, $params, $retryCount + 1);
        }
        
        // Handle other errors gracefully
        if ($httpCode === 429) {
            return null; // Return null instead of throwing exception
        }
        
        throw new Exception("Discogs API request failed with HTTP code: $httpCode");
    }
    
    /**
     * Enforce rate limiting between API requests
     */
    private function enforceRateLimit() {
        $currentTime = microtime(true) * 1000000; // Convert to microseconds
        $timeSinceLastRequest = $currentTime - self::$lastRequestTime;
        
        if ($timeSinceLastRequest < self::$requestDelay) {
            $sleepTime = self::$requestDelay - $timeSinceLastRequest;
            usleep($sleepTime);
        }
        
        self::$lastRequestTime = microtime(true) * 1000000;
    }
    
    /**
     * Extract detailed format information from Discogs formats array
     */
    private function extractFormatDetails($formats) {
        if (empty($formats)) {
            return '';
        }
        
        $formatParts = [];
        
        foreach ($formats as $format) {
            $formatInfo = [];
            
            // Add the main format name (e.g., "Vinyl", "CD", "Cassette")
            if (isset($format['name'])) {
                $formatInfo[] = $format['name'];
            }
            
            // Add descriptive text (e.g., "7"", "10"", "45 RPM", "33 ⅓ RPM")
            if (isset($format['descriptions']) && is_array($format['descriptions'])) {
                $formatInfo = array_merge($formatInfo, $format['descriptions']);
            }
            
            // Add quantity if more than 1
            if (isset($format['qty']) && $format['qty'] > 1) {
                $formatInfo[] = "×{$format['qty']}";
            }
            
            // Add text field if present (additional format details)
            if (isset($format['text']) && !empty($format['text'])) {
                $formatInfo[] = $format['text'];
            }
            
            $formatParts[] = implode(', ', $formatInfo);
        }
        
        return implode(' + ', $formatParts);
    }
    
    /**
     * Get detailed release information including tracklist
     */
    public function getReleaseInfo($releaseId) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Check cache first
        $cacheKey = "release_{$releaseId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            $url = $this->baseUrl . "/releases/{$releaseId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && isset($response['title'])) {
                // Extract tracklist information
                $tracklist = [];
                if (isset($response['tracklist']) && is_array($response['tracklist'])) {
                    foreach ($response['tracklist'] as $track) {
                        $tracklist[] = [
                            'position' => $track['position'] ?? '',
                            'title' => $track['title'] ?? '',
                            'duration' => $track['duration'] ?? ''
                        ];
                    }
                }
                
                // Extract detailed format information
                $formatDetails = $this->extractFormatDetails($response['formats'] ?? []);
                
                // Extract producer information from companies
                $producers = [];
                if (isset($response['companies']) && is_array($response['companies'])) {
                    foreach ($response['companies'] as $company) {
                        if (isset($company['entity_type_name']) && 
                            $company['entity_type_name'] === 'Producer') {
                            $producers[] = $company['name'];
                        }
                    }
                }
                
                // Also check extraartists as fallback
                if (empty($producers) && isset($response['extraartists']) && is_array($response['extraartists'])) {
                    foreach ($response['extraartists'] as $extraArtist) {
                        if (isset($extraArtist['role']) && 
                            (stripos($extraArtist['role'], 'Producer') !== false || 
                             stripos($extraArtist['role'], 'Production') !== false)) {
                            $producers[] = $extraArtist['name'];
                        }
                    }
                }
                

                

                
                // Check if there are actual reviews with content
                $hasReviewsWithContent = $this->hasReviewsWithContent($releaseId);
                
                // Get master release information if available
                $masterYear = null;
                $masterReleased = null;
                if (isset($response['master_id']) && $response['master_id']) {
                    $masterInfo = $this->getMasterReleaseInfo($response['master_id']);
                    $masterYear = $masterInfo['year'] ?? null;
                    $masterReleased = $masterInfo['released'] ?? null;
                }
                
                // Determine the released date to use
                $releasedDate = null;
                if ($masterReleased) {
                    // Use master release date if available
                    $releasedDate = $masterReleased;
                } elseif ($masterYear) {
                    // Use master release year if no specific date is available
                    $releasedDate = $masterYear;
                } else {
                    // Fall back to specific release date
                    $releasedDate = $response['released'] ?? null;
                }
                
                $result = [
                    'title' => $response['title'],
                    'artist' => $response['artists'][0]['name'] ?? '',
                    'year' => $response['year'] ?? null,
                    'master_year' => $masterYear,
                    'cover_url' => $this->getCoverArtForSize($response, 'large'),
                    'tracklist' => $tracklist,
                    'format' => $formatDetails,
                    'producer' => !empty($producers) ? implode(', ', array_unique($producers)) : '',
                    'rating' => isset($response['community']['rating']['average']) ? $response['community']['rating']['average'] : null,
                    'rating_count' => isset($response['community']['rating']['count']) ? $response['community']['rating']['count'] : null,
                    'has_reviews_with_content' => $hasReviewsWithContent,
                    'style' => isset($response['styles']) ? implode(', ', $response['styles']) : '',
                    'label' => $response['labels'][0]['name'] ?? '',
                    'released' => $releasedDate
                ];
                
                // Cache the result
                self::$cache[$cacheKey] = [
                    'data' => $result,
                    'expiry' => time() + self::$cacheExpiry
                ];
                
                return $result;
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return null;
    }
    
    /**
     * Check if a release has reviews with content
     */
    private function hasReviewsWithContent($releaseId) {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $url = $this->baseUrl . "/releases/{$releaseId}/reviews";
            $params = [
                'token' => $this->apiKey,
                'per_page' => 3 // Check a few reviews to see if any have content
            ];
            
            $response = $this->makeRequest($url, $params);

            if ($response && isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $review) {
                    if (!empty($review['review_plaintext']) || !empty($review['review_html'])) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return false;
    }
    
    /**
     * Get master release information from master release ID
     */
    private function getMasterReleaseInfo($masterId) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Check cache first
        $cacheKey = "master_{$masterId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            $url = $this->baseUrl . "/masters/{$masterId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && (isset($response['year']) || isset($response['released']))) {
                $result = [
                    'year' => $response['year'] ?? null,
                    'released' => $response['released'] ?? null
                ];
                
                // Cache the result
                self::$cache[$cacheKey] = [
                    'data' => $result,
                    'expiry' => time() + self::$cacheExpiry
                ];
                
                return $result;
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return null;
    }
    
    /**
     * Get master year for a release ID (lightweight version)
     */
    public function getMasterYear($releaseId) {
        if (!$this->isAvailable()) {
            return null;
        }
        
        // Check cache first
        $cacheKey = "master_year_{$releaseId}";
        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiry'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }
        
        try {
            // Get basic release info to find master_id
            $url = $this->baseUrl . "/releases/{$releaseId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if ($response && isset($response['master_id']) && $response['master_id']) {
                // Get master year from master release
                $masterInfo = $this->getMasterReleaseInfo($response['master_id']);
                $masterYear = $masterInfo['year'] ?? null;
                
                // Cache the result
                self::$cache[$cacheKey] = [
                    'data' => $masterYear,
                    'expiry' => time() + self::$cacheExpiry
                ];
                
                return $masterYear;
            }
        } catch (Exception $e) {
            // API call failed, return null
            
        }
        
        return null;
    }
}
?> 