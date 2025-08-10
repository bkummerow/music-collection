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
    public function searchArtists($query, $limit = 10) {
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
            error_log('Discogs API Error (Artist Search): ' . $e->getMessage());
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

        try {
            $results = [];
            
            // For Various Artists, be more flexible in search
            if (strtolower($artistName) === 'various' || strtolower($artistName) === 'various artists') {
                // Search 1: Try with "Various" + album name
                $searchQuery1 = "Various $query";
                $results1 = $this->performDirectSearch($searchQuery1, $limit);
                $results = array_merge($results, $results1);
                
                // Search 2: Try with just the album name (for Various Artists releases)
                if (!empty($query)) {
                    $results2 = $this->performDirectSearch($query, $limit);
                    $results = array_merge($results, $results2);
                }
                
                // Search 3: Try with "Various Artists" + album name
                $searchQuery3 = "Various Artists $query";
                $results3 = $this->performDirectSearch($searchQuery3, $limit);
                $results = array_merge($results, $results3);
            } else {
                // Use performDirectSearch for better results
                $searchQuery = "$artistName $query";
                $results = $this->performDirectSearch($searchQuery, $limit, $query);
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
            error_log('Discogs API Error (Search): ' . $e->getMessage());
        }
        
        return [];
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
            error_log('Discogs API Error (Search): ' . $e->getMessage());
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
        
        if (isset($response['results'])) {
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
                        'cover_url' => ImageOptimizationService::getThumbnailUrl($this->getCoverArt($release)),
                        'cover_url_medium' => ImageOptimizationService::getMediumUrl($this->getCoverArt($release)),
                        'cover_url_large' => ImageOptimizationService::getLargeUrl($this->getCoverArt($release)),
                        'type' => 'album'
                    ];
                }
            }
            
            return $filteredResults;
        }
        
        return [];
    }
    
    /**
     * Perform a direct search without strict filtering (for tracklist API)
     */
    public function performDirectSearch($searchQuery, $limit = 10, $albumSearchTerm = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit * 2, // Request more results to account for filtering
            'token' => $this->apiKey
        ];
        
        // If we have a specific album search term, try to make the search more specific
        if (!empty($albumSearchTerm)) {
            // Use quotes around the album search term to make it more exact
            $params['q'] = str_replace($albumSearchTerm, '"' . $albumSearchTerm . '"', $searchQuery);
            
            // Don't restrict format to 'album' only - include singles, EPs, etc.
            // This allows for releases like singles or EPs that might not be categorized as 'album'
        }
        
        $response = $this->makeRequest($url, $params);
        
        if (isset($response['results'])) {
            $results = [];
            
            foreach ($response['results'] as $release) {
                // If we have a specific album search term, filter by it
                if (!empty($albumSearchTerm)) {
                    $title = strtolower($release['title']);
                    $albumSearchLower = strtolower($albumSearchTerm);
                    
                    // Debug: Log what we're checking
                    error_log("Checking release: '{$release['title']}' for search term: '$albumSearchLower'");
                    
                    // Check for exact word match (not just substring)
                    $words = explode(' ', $title);
                    $hasExactMatch = false;
                    foreach ($words as $word) {
                        $word = trim($word);
                        if ($word === $albumSearchLower) {
                            $hasExactMatch = true;
                            error_log("Found exact match: '$word'");
                            break;
                        }
                    }
                    
                    // Check for substring match
                    $hasSubstringMatch = strpos($title, $albumSearchLower) !== false;
                    
                    // If we're searching for compilation-related terms, don't filter them out
                    $isCompilationTerm = preg_match('/(greatest hits|best of|collection|essential|anthology|compilation|box set)/i', $albumSearchLower);
                    
                    // Only exclude compilations if we're not explicitly searching for them
                    $isCompilation = false;
                    if (!$isCompilationTerm) {
                        // Be more specific about what we consider a compilation to avoid false positives
                        // Only exclude if it contains explicit compilation terms, not just "greatest"
                        $isCompilation = preg_match('/(greatest hits|best of|complete collection|essential collection|anthology|compilation album|box set)/i', $title);
                    }
                    
                    // If no exact word match and no substring match, skip
                    // Removed the compilation filter as it was too restrictive
                    if (!$hasExactMatch && !$hasSubstringMatch) {
                        error_log("Skipping release: '{$release['title']}' - no match");
                        continue;
                    }
                    
                    error_log("Including release: '{$release['title']}' - has match");
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
                
                $results[] = [
                    'id' => $release['id'],
                    'title' => $albumName,
                    'artist' => $artist,
                    'year' => $release['year'] ?? null,
                    'cover_url' => ImageOptimizationService::getThumbnailUrl($this->getCoverArt($release)),
                    'cover_url_medium' => ImageOptimizationService::getMediumUrl($this->getCoverArt($release)),
                    'cover_url_large' => ImageOptimizationService::getLargeUrl($this->getCoverArt($release)),
                    'type' => 'album'
                ];
                
                // Stop if we've reached the limit
                if (count($results) >= $limit) {
                    break;
                }
            }
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Perform a direct search for storage (returns original URLs)
     */
    public function performDirectSearchForStorage($searchQuery, $limit = 10, $albumSearchTerm = '') {
        $url = $this->baseUrl . '/database/search';
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => $limit * 2, // Request more results to account for filtering
            'token' => $this->apiKey
        ];
        
        // If we have a specific album search term, try to make the search more specific
        if (!empty($albumSearchTerm)) {
            // Use quotes around the album search term to make it more exact
            $params['q'] = str_replace($albumSearchTerm, '"' . $albumSearchTerm . '"', $searchQuery);
            
            // Don't restrict format to 'album' only - include singles, EPs, etc.
            // This allows for releases like singles or EPs that might not be categorized as 'album'
        }
        
        $response = $this->makeRequest($url, $params);
        
        if (isset($response['results'])) {
            $results = [];
            
            foreach ($response['results'] as $release) {
                // If we have a specific album search term, filter by it
                if (!empty($albumSearchTerm)) {
                    $title = strtolower($release['title']);
                    $albumSearchLower = strtolower($albumSearchTerm);
                    
                    // Debug: Log what we're checking
                    error_log("Checking release: '{$release['title']}' for search term: '$albumSearchLower'");
                    
                    // Check for exact word match (not just substring)
                    $words = explode(' ', $title);
                    $hasExactMatch = false;
                    foreach ($words as $word) {
                        $word = trim($word);
                        if ($word === $albumSearchLower) {
                            $hasExactMatch = true;
                            error_log("Found exact match: '$word'");
                            break;
                        }
                    }
                    
                    // Check for substring match
                    $hasSubstringMatch = strpos($title, $albumSearchLower) !== false;
                    
                    // If we're searching for compilation-related terms, don't filter them out
                    $isCompilationTerm = preg_match('/(greatest hits|best of|collection|essential|anthology|compilation|box set)/i', $albumSearchLower);
                    
                    // Only exclude compilations if we're not explicitly searching for them
                    $isCompilation = false;
                    if (!$isCompilationTerm) {
                        // Be more specific about what we consider a compilation to avoid false positives
                        // Only exclude if it contains explicit compilation terms, not just "greatest"
                        $isCompilation = preg_match('/(greatest hits|best of|complete collection|essential collection|anthology|compilation album|box set)/i', $title);
                    }
                    
                    // If no exact word match and no substring match, skip
                    // Removed the compilation filter as it was too restrictive
                    if (!$hasExactMatch && !$hasSubstringMatch) {
                        error_log("Skipping release: '{$release['title']}' - no match");
                        continue;
                    }
                    
                    error_log("Including release: '{$release['title']}' - has match");
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
                
                $results[] = [
                    'id' => $release['id'],
                    'title' => $albumName,
                    'artist' => $artist,
                    'year' => $release['year'] ?? null,
                    'cover_url' => $this->getCoverArt($release), // Store original URL
                    'type' => 'album'
                ];
                
                // Stop if we've reached the limit
                if (count($results) >= $limit) {
                    break;
                }
            }
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Get cover art for a release
     */
    private function getCoverArt($release) {
        // Try different possible cover art fields
        $coverFields = ['cover_image', 'thumb', 'image'];
        
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
            return false;
        }
    }
    
    /**
     * Make HTTP request to Discogs API
     */
    private function makeRequest($url, $params = []) {
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
        
        throw new Exception("Discogs API request failed with HTTP code: $httpCode");
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
        
        try {
            $url = $this->baseUrl . "/releases/{$releaseId}";
            $params = [
                'token' => $this->apiKey
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['title'])) {
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
                

                

                
                // Check if there are reviews with content by making a separate API call
                $hasReviewsWithContent = $this->hasReviewsWithContent($releaseId);
                
                return [
                    'title' => $response['title'],
                    'artist' => $response['artists'][0]['name'] ?? '',
                    'year' => $response['year'] ?? null,
                    'cover_url' => $this->getCoverArt($response),
                    'tracklist' => $tracklist,
                    'format' => $formatDetails,
                    'producer' => !empty($producers) ? implode(', ', array_unique($producers)) : '',
                    'rating' => isset($response['community']['rating']['average']) ? $response['community']['rating']['average'] : null,
                    'rating_count' => isset($response['community']['rating']['count']) ? $response['community']['rating']['count'] : null,
                    'has_reviews_with_content' => $hasReviewsWithContent,
                    'style' => isset($response['styles']) ? implode(', ', $response['styles']) : '',
                    'label' => $response['labels'][0]['name'] ?? '',
                    'released' => $response['released'] ?? null
                ];
            }
        } catch (Exception $e) {
            error_log('Discogs API Error (Release Info): ' . $e->getMessage());
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
                'per_page' => 1 // We only need to check if any reviews exist
            ];
            
            $response = $this->makeRequest($url, $params);

            if (isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $review) {
                    error_log('Review structure: ' . json_encode($review));
                    if (!empty($review['review_plaintext']) || !empty($review['review_html'])) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Discogs API Error (Reviews Check): ' . $e->getMessage());
        }
        
        return false;
    }
}
?> 