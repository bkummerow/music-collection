<?php
/**
 * MusicBrainz API Service
 * Provides enhanced autocomplete using MusicBrainz free API
 */

class MusicBrainzAPIService {
    private $baseUrl = 'https://musicbrainz.org/ws/2';
    private $userAgent = 'MusicCollectionApp/1.0';
    
    public function __construct() {
        // MusicBrainz allows free API access with proper User-Agent
    }
    
    /**
     * Search for artists using MusicBrainz API
     */
    public function searchArtists($query, $limit = 10) {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }
        
        try {
            $url = $this->baseUrl . '/artist/';
            $params = [
                'query' => $query,
                'limit' => $limit,
                'fmt' => 'json'
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['artists'])) {
                return array_map(function($artist) {
                    return [
                        'name' => $artist['name'],
                        'id' => $artist['id'],
                        'type' => 'artist'
                    ];
                }, $response['artists']);
            }
        } catch (Exception $e) {
            error_log('MusicBrainz API Error (Artists): ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Search for albums by artist using MusicBrainz API
     */
    public function searchAlbumsByArtist($artistName, $query = '', $limit = 10) {
        if (empty($artistName)) {
            return [];
        }
        
        try {
            // First, find the artist ID
            $artistId = $this->findArtistId($artistName);
            if (!$artistId) {
                return [];
            }
            
            // Then search for releases by that artist
            $url = $this->baseUrl . '/release/';
            
            // Build search query - always filter by artist ID first
            $searchQuery = "arid:$artistId";
            
            // Add album name filter if provided
            if (!empty($query)) {
                // Use the album name in the search query - allow partial matches
                $searchQuery .= " AND name:$query";
            }
            
            $params = [
                'query' => $searchQuery,
                'limit' => $limit,
                'fmt' => 'json',
                'inc' => 'release-events'
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['releases'])) {
                return array_map(function($release) {
                    // Get the first release year from release events
                    $firstReleaseYear = null;
                    if (isset($release['release-events']) && !empty($release['release-events'])) {
                        foreach ($release['release-events'] as $event) {
                            if (isset($event['date']) && $event['date']) {
                                $firstReleaseYear = substr($event['date'], 0, 4);
                                break; // Use the first valid date
                            }
                        }
                    }
                    
                    // Fallback to regular date if no release events
                    $year = $firstReleaseYear ?: (isset($release['date']) && $release['date'] ? substr($release['date'], 0, 4) : null);
                    
                    // Get cover art URL
                    $coverUrl = $this->getAlbumCover($release['id']);
                    
                    return [
                        'title' => $release['title'],
                        'artist' => $release['artist-credit'][0]['name'] ?? '',
                        'year' => $year,
                        'id' => $release['id'],
                        'cover_url' => $coverUrl,
                        'type' => 'album'
                    ];
                }, $response['releases']);
            }
        } catch (Exception $e) {
            error_log('MusicBrainz API Error (Albums): ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Find artist ID by name
     */
    private function findArtistId($artistName) {
        try {
            $url = $this->baseUrl . '/artist/';
            $params = [
                'query' => "\"$artistName\"", // Use exact match with quotes
                'limit' => 5, // Get more results to find the best match
                'fmt' => 'json'
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['artists']) && !empty($response['artists'])) {
                // Find the best match - prefer exact name matches
                foreach ($response['artists'] as $artist) {
                    if (strcasecmp($artist['name'], $artistName) === 0) {
                        return $artist['id'];
                    }
                }
                // If no exact match, return the first result
                return $response['artists'][0]['id'];
            }
        } catch (Exception $e) {
            error_log('MusicBrainz API Error (Find Artist): ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Make HTTP request to MusicBrainz API
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
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        throw new Exception("MusicBrainz API request failed with HTTP code: $httpCode");
    }
    
    /**
     * Check if API is available
     */
    public function isAvailable() {
        try {
            $response = $this->makeRequest($this->baseUrl . '/artist/', ['query' => 'test', 'limit' => 1, 'fmt' => 'json']);
            return isset($response['artists']);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get album cover image URL from Discogs only
     */
    public function getAlbumCover($releaseId) {
        if (empty($releaseId)) {
            return null;
        }
        
        // Use Discogs API exclusively for better performance and quality
        return $this->getCoverFromDiscogs($releaseId);
    }
    
    /**
     * Get cover art from Cover Art Archive (MusicBrainz's official source)
     */
    private function getCoverFromCoverArtArchive($releaseId) {
        try {
            // Try the Cover Art Archive API first to get available images
            $apiUrl = "https://coverartarchive.org/release/{$releaseId}";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['User-Agent: MusicCollectionApp/1.0']
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['images']) && !empty($data['images'])) {
                    // Find the front cover
                    foreach ($data['images'] as $image) {
                        if (isset($image['front']) && $image['front'] === true) {
                            return $image['image'];
                        }
                    }
                    // If no front cover, return the first image
                    return $data['images'][0]['image'];
                }
            }
            
            // Fallback: try direct image URLs
            $url = "https://coverartarchive.org/release/{$releaseId}/front-500.jpg";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return $url;
            }
            
            // Try smaller size if 500px doesn't exist
            $url = "https://coverartarchive.org/release/{$releaseId}/front-250.jpg";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return $url;
            }
            
        } catch (Exception $e) {
            error_log('Cover Art Archive Error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get cover art from Last.fm API
     */
    private function getCoverFromLastFM($releaseId) {
        try {
            // We need to get artist and album info from MusicBrainz first
            $releaseInfo = $this->getReleaseInfo($releaseId);
            if (!$releaseInfo) {
                return null;
            }
            
            $artistName = $releaseInfo['artist'];
            $albumName = $releaseInfo['title'];
            
            // Search Last.fm for the album
            $url = "http://ws.audioscrobbler.com/2.0/";
            $params = [
                'method' => 'album.getinfo',
                'artist' => $artistName,
                'album' => $albumName,
                'api_key' => 'YOUR_LASTFM_API_KEY', // Would need API key
                'format' => 'json'
            ];
            
            // For now, we'll use a public endpoint that doesn't require API key
            $searchUrl = "https://ws.audioscrobbler.com/2.0/?method=album.search&album=" . urlencode($albumName) . "&artist=" . urlencode($artistName) . "&format=json";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $searchUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['User-Agent: MusicCollectionApp/1.0']
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['results']['albummatches']['album'][0]['image'])) {
                    $images = $data['results']['albummatches']['album'][0]['image'];
                    // Get the largest available image
                    foreach (array_reverse($images) as $image) {
                        if (isset($image['size']) && $image['size'] === 'extralarge' && !empty($image['#text'])) {
                            return $image['#text'];
                        }
                    }
                    // Fallback to any available image
                    foreach ($images as $image) {
                        if (!empty($image['#text'])) {
                            return $image['#text'];
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('Last.fm API Error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get release information from MusicBrainz
     */
    private function getReleaseInfo($releaseId) {
        try {
            $url = "https://musicbrainz.org/ws/2/release/{$releaseId}?fmt=json&inc=artist-credits";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['User-Agent: MusicCollectionApp/1.0']
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['title']) && isset($data['artist-credit'][0]['name'])) {
                    return [
                        'title' => $data['title'],
                        'artist' => $data['artist-credit'][0]['name']
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log('MusicBrainz Release Info Error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get cover art from Discogs API
     */
    private function getCoverFromDiscogs($releaseId) {
        try {
            // Check if Discogs API is available
            if (!class_exists('DiscogsAPIService')) {
                require_once __DIR__ . '/DiscogsAPIService.php';
            }
            
            $discogsAPI = new DiscogsAPIService();
            if (!$discogsAPI->isAvailable()) {
                return null;
            }
            
            // Get release info from MusicBrainz first
            $releaseInfo = $this->getReleaseInfo($releaseId);
            if (!$releaseInfo) {
                return null;
            }
            
            $artistName = $releaseInfo['artist'];
            $albumName = $releaseInfo['title'];
            
            // Search Discogs for the album
            $albums = $discogsAPI->searchAlbumsByArtist($artistName, $albumName, 1);
            
            if (!empty($albums) && !empty($albums[0]['cover_url'])) {
                return $albums[0]['cover_url'];
            }
            
        } catch (Exception $e) {
            error_log('Discogs API Error: ' . $e->getMessage());
        }
        
        return null;
    }
}
?> 