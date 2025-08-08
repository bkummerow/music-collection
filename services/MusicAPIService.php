<?php
/**
 * Music API Service
 * Integrates with external music APIs for enhanced autocomplete and data validation
 */

class MusicAPIService {
    private $apiKey;
    private $baseUrl = 'https://api.discogs.com';
    private $config;
    
    public function __construct($apiKey = null) {
        require_once __DIR__ . '/../config/api_config.php';
        
        $this->config = getAPIConfig();
        $this->apiKey = $apiKey ?: $this->config['discogs_key'];
    }
    
    /**
     * Search for artists using Discogs API
     */
    public function searchArtists($query, $limit = 10) {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }
        
        // Check if external API is enabled
        if (!$this->config['use_external'] || empty($this->apiKey)) {
            return [];
        }
        
        try {
            $url = $this->baseUrl . '/database/search';
            $params = [
                'q' => $query,
                'type' => 'artist',
                'per_page' => $limit
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['results'])) {
                return array_map(function($artist) {
                    return [
                        'name' => $artist['title'],
                        'id' => $artist['id'],
                        'type' => 'artist'
                    ];
                }, $response['results']);
            }
        } catch (Exception $e) {
            error_log('Music API Error (Artists): ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Search for albums by artist using Discogs API
     */
    public function searchAlbumsByArtist($artistName, $query = '', $limit = 10) {
        if (empty($artistName)) {
            return [];
        }
        
        // Check if external API is enabled
        if (!$this->config['use_external'] || empty($this->apiKey)) {
            return [];
        }
        
        try {
            $url = $this->baseUrl . '/database/search';
            $params = [
                'q' => $artistName . ' ' . $query,
                'type' => 'release',
                'per_page' => $limit
            ];
            
            $response = $this->makeRequest($url, $params);
            
            if (isset($response['results'])) {
                $albums = [];
                foreach ($response['results'] as $release) {
                    // Filter to only include releases by the specified artist
                    if (isset($release['artist']) && 
                        stripos($release['artist'], $artistName) !== false) {
                        $albums[] = [
                            'title' => $release['title'],
                            'artist' => $release['artist'],
                            'year' => $release['year'] ?? null,
                            'id' => $release['id'],
                            'type' => 'album'
                        ];
                    }
                }
                return $albums;
            }
        } catch (Exception $e) {
            error_log('Music API Error (Albums): ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Get album details by ID
     */
    public function getAlbumDetails($albumId) {
        try {
            $url = $this->baseUrl . '/releases/' . $albumId;
            $response = $this->makeRequest($url);
            
            if ($response) {
                return [
                    'title' => $response['title'] ?? '',
                    'artist' => $response['artists'][0]['name'] ?? '',
                    'year' => $response['year'] ?? null,
                    'genre' => $response['genres'] ?? [],
                    'format' => $response['formats'] ?? []
                ];
            }
        } catch (Exception $e) {
            error_log('Music API Error (Album Details): ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Make HTTP request to API
     */
    private function makeRequest($url, $params = []) {
        $headers = [
            'User-Agent: MusicCollectionApp/1.0',
            'Accept: application/json'
        ];
        
        if ($this->apiKey) {
            $headers[] = 'Authorization: Discogs token=' . $this->apiKey;
        }
        
        $fullUrl = $url;
        if (!empty($params)) {
            $fullUrl .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        throw new Exception("API request failed with HTTP code: $httpCode");
    }
    
    /**
     * Fallback search using local database
     */
    public function searchLocalArtists($query, $limit = 10) {
        // This would be implemented to search your local database
        // as a fallback when external API is unavailable
        return [];
    }
    
    /**
     * Validate artist name against external API
     */
    public function validateArtist($artistName) {
        $results = $this->searchArtists($artistName, 1);
        if (!empty($results)) {
            $firstResult = $results[0];
            // Check if the first result is a close match
            $similarity = similar_text(strtolower($artistName), strtolower($firstResult['name']), $percent);
            return $percent > 80; // 80% similarity threshold
        }
        return false;
    }
    
    /**
     * Get suggested albums for an artist
     */
    public function getSuggestedAlbums($artistName, $limit = 5) {
        return $this->searchAlbumsByArtist($artistName, '', $limit);
    }
}

/**
 * Alternative Music API Service using Last.fm API
 * Uncomment and configure if you prefer Last.fm over Discogs
 */
/*
class LastFmMusicAPIService {
    private $apiKey;
    private $baseUrl = 'http://ws.audioscrobbler.com/2.0/';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    public function searchArtists($query, $limit = 10) {
        $params = [
            'method' => 'artist.search',
            'artist' => $query,
            'limit' => $limit,
            'api_key' => $this->apiKey,
            'format' => 'json'
        ];
        
        $response = $this->makeRequest($params);
        
        if (isset($response['results']['artistmatches']['artist'])) {
            return array_map(function($artist) {
                return [
                    'name' => $artist['name'],
                    'id' => $artist['mbid'],
                    'type' => 'artist'
                ];
            }, $response['results']['artistmatches']['artist']);
        }
        
        return [];
    }
    
    public function searchAlbumsByArtist($artistName, $query = '', $limit = 10) {
        $params = [
            'method' => 'artist.gettopalbums',
            'artist' => $artistName,
            'limit' => $limit,
            'api_key' => $this->apiKey,
            'format' => 'json'
        ];
        
        $response = $this->makeRequest($params);
        
        if (isset($response['topalbums']['album'])) {
            return array_map(function($album) {
                return [
                    'title' => $album['name'],
                    'artist' => $album['artist']['name'],
                    'year' => null, // Last.fm doesn't provide year in this endpoint
                    'id' => $album['mbid'],
                    'type' => 'album'
                ];
            }, $response['topalbums']['album']);
        }
        
        return [];
    }
    
    private function makeRequest($params) {
        $url = $this->baseUrl . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
*/
?> 