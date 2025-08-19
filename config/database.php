<?php
/**
 * Database Configuration
 * Uses file-based storage since database extensions aren't available on this hosting
 */

define('DB_FILE', __DIR__ . '/../data/music_collection.json');
define('DB_LOCK_FILE', __DIR__ . '/../data/music_collection.lock');

/**
 * Create database connection
 */
function getDBConnection() {
    // Create data directory if it doesn't exist
    $dataDir = dirname(DB_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    // Initialize empty database if it doesn't exist
    if (!file_exists(DB_FILE)) {
        file_put_contents(DB_FILE, json_encode([
            'albums' => [],
            'next_id' => 1
        ]));
    }
    
    return new SimpleDB();
}

/**
 * Check database connection type
 */
function getConnectionType() {
    return 'SimpleDB';
}

/**
 * Simple Database Class using JSON files
 */
class SimpleDB {
    private $data;
    private $file;
    
    public function __construct() {
        $this->file = DB_FILE;
        $this->loadData();
    }
    
    private function loadData() {
        if (file_exists($this->file)) {
            $content = file_get_contents($this->file);
            $this->data = json_decode($content, true) ?: ['albums' => [], 'next_id' => 1];
            
            // Handle both array and object formats for albums
            if (isset($this->data['albums']) && !empty($this->data['albums'])) {
                // If albums is an object (production format), convert to array
                if (!array_key_exists(0, $this->data['albums'])) {
                    $this->data['albums'] = array_values($this->data['albums']);
                }
            }
        } else {
            $this->data = ['albums' => [], 'next_id' => 1];
        }
    }
    
    private function saveData() {
        // Use file locking to prevent concurrent writes
        $lockFile = fopen(DB_LOCK_FILE, 'w+');
        if (flock($lockFile, LOCK_EX)) {
            file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT));
            flock($lockFile, LOCK_UN);
        }
        fclose($lockFile);
    }
    
    /**
     * Create a sort key that handles both individual artists and band names
     * Individual artists: "Aaron Dilloway" → "Dilloway, Aaron"
     * Band names: "The Beatles" → "Beatles"
     */
    private function getSortKey($text) {
        $text = trim($text);
        
        // First, check if it's a band name with articles
        $articles = ['the ', 'a ', 'an '];
        foreach ($articles as $article) {
            if (strtolower(substr($text, 0, strlen($article))) === $article) {
                return trim(substr($text, strlen($article)));
            }
        }
        
        // Check if it looks like an individual artist (first name + last name)
        $words = explode(' ', $text);
        if (count($words) == 2) {
            // Two words - check if it's likely an individual artist
            $firstName = $words[0];
            $lastName = $words[1];
            
            // Check if both words are capitalized (typical for names)
            if (ctype_upper(substr($firstName, 0, 1)) && ctype_upper(substr($lastName, 0, 1))) {
                // Check for common band name patterns that shouldn't be treated as individual artists
                $lowerText = strtolower($text);
                $bandPatterns = [
                    'pink floyd', 'led zeppelin', 'daft punk', 'black sabbath',
                    'deep purple', 'iron maiden', 'judas priest', 'motorhead',
                    'queen', 'kiss', 'rush', 'yes', 'genesis', 'pink floyd',
                    'rolling stones', 'who', 'doors', 'cream', 'zeppelin'
                ];
                
                // If it matches a known band pattern, treat as band name
                if (in_array($lowerText, $bandPatterns)) {
                    return $text; // Keep as-is for band names
                }
                
                // Check if it looks like a typical first/last name combination
                // Common first names that are typically individual artists
                $commonFirstNames = [
                    'john', 'james', 'michael', 'david', 'robert', 'william',
                    'richard', 'joseph', 'thomas', 'christopher', 'charles',
                    'daniel', 'matthew', 'anthony', 'mark', 'donald', 'steven',
                    'paul', 'andrew', 'joshua', 'kenneth', 'kevin', 'brian',
                    'george', 'timothy', 'ronald', 'jason', 'edward', 'jeffrey',
                    'ryan', 'jacob', 'gary', 'nicholas', 'eric', 'stephen',
                    'jonathan', 'larry', 'justin', 'scott', 'brandon', 'benjamin',
                    'frank', 'samuel', 'gregory', 'raymond', 'alexander', 'patrick',
                    'jack', 'dennis', 'jerry', 'tyler', 'aaron', 'jose', 'henry',
                    'douglas', 'adam', 'peter', 'nathan', 'zachary', 'walter',
                    'kyle', 'harold', 'carl', 'jeremy', 'keith', 'roger', 'gavin',
                    'terrence', 'sean', 'christian', 'andrew', 'eric', 'stephen',
                    'ronald', 'larry', 'timothy', 'kurt', 'bob', 'miles', 'aaron'
                ];
                
                if (in_array(strtolower($firstName), $commonFirstNames)) {
                    // Sort by last name, then first name
                    return $lastName . ', ' . $firstName;
                }
            }
        }
        
        // Default: return as-is (for band names without articles)
        return $text;
    }
    
    public function query($sql, $params = []) {
        // Parse simple SQL-like queries
        $sql = strtolower(trim($sql));
        
        if (strpos($sql, 'select') === 0) {
            return $this->handleSelect($sql, $params);
        } elseif (strpos($sql, 'insert') === 0) {
            return $this->handleInsert($sql, $params);
        } elseif (strpos($sql, 'update') === 0) {
            return $this->handleUpdate($sql, $params);
        } elseif (strpos($sql, 'delete') === 0) {
            return $this->handleDelete($sql, $params);
        }
        
        return [];
    }
    
    private function handleSelect($sql, $params) {
        $albums = $this->data['albums'] ?? [];
        
        // Handle statistics query
        if (strpos($sql, 'count(*)') !== false && strpos($sql, 'sum(') !== false) {
            $totalAlbums = count($albums);
            $ownedCount = 0;
            $wantedCount = 0;
            $uniqueArtists = [];
            $styleCounts = [];
            
            foreach ($albums as $album) {
                if ($album['is_owned'] == 1) $ownedCount++;
                if ($album['want_to_own'] == 1) $wantedCount++;
                $uniqueArtists[$album['artist_name']] = true;
                
                // Count styles
                if (!empty($album['style'])) {
                    $styles = array_map('trim', explode(',', $album['style']));
                    foreach ($styles as $style) {
                        if (!empty($style)) {
                            $styleCounts[$style] = ($styleCounts[$style] ?? 0) + 1;
                        }
                    }
                }
            }
            
            // Sort styles by count (descending)
            arsort($styleCounts);
            
            return [[
                'total_albums' => $totalAlbums,
                'owned_count' => $ownedCount,
                'wanted_count' => $wantedCount,
                'unique_artists' => count($uniqueArtists),
                'style_counts' => $styleCounts
            ]];
        }
        
        // Handle DISTINCT queries for autocomplete
        if (strpos($sql, 'distinct') !== false) {
            $field = '';
            if (strpos($sql, 'artist_name') !== false) {
                $field = 'artist_name';
            } elseif (strpos($sql, 'album_name') !== false) {
                $field = 'album_name';
            }
            
            if ($field) {
                $values = [];
                foreach ($albums as $album) {
                    if (!in_array($album[$field], $values)) {
                        $values[] = $album[$field];
                    }
                }
                
                // Apply search filter if present (case-insensitive)
                if (!empty($params)) {
                    $searchTerm = str_replace('%', '', $params[0]);
                    $searchTerm = strtolower($searchTerm);
                    $values = array_filter($values, function($value) use ($searchTerm) {
                        return stripos(strtolower($value), $searchTerm) !== false;
                    });
                }
                
                return array_map(function($value) use ($field) {
                    return [$field => $value];
                }, array_values($values));
            }
        }
        
        // Handle specific ID queries
        if (strpos($sql, 'id = ?') !== false && !empty($params)) {
            $id = $params[0];
            foreach ($albums as $album) {
                if ($album['id'] == $id) {
                    return [$album];
                }
            }
            return [];
        }
        
        // Simple WHERE clause parsing
        if (strpos($sql, 'where') !== false) {
            $wherePart = substr($sql, strpos($sql, 'where') + 5);
            
            // Filter by owned status
            if (strpos($wherePart, 'is_owned = 1') !== false) {
                $albums = array_filter($albums, function($album) {
                    return $album['is_owned'] == 1;
                });
            }
            
            // Filter by wanted status
            if (strpos($wherePart, 'want_to_own = 1') !== false) {
                $albums = array_filter($albums, function($album) {
                    return $album['want_to_own'] == 1;
                });
            }
            
            // Duplicate checking (artist_name and album_name combination)
            if (strpos($wherePart, 'lower(artist_name) = lower(?)') !== false && strpos($wherePart, 'lower(album_name) = lower(?)') !== false) {
                $artistName = $params[0];
                $albumName = $params[1];
                $excludeId = null;
                
                // Check if there's an exclude ID parameter
                if (strpos($wherePart, 'id != ?') !== false && count($params) > 2) {
                    $excludeId = $params[2];
                }
                
                $albums = array_filter($albums, function($album) use ($artistName, $albumName, $excludeId) {
                    $match = (strtolower($album['artist_name']) === strtolower($artistName) && 
                             strtolower($album['album_name']) === strtolower($albumName));
                    
                    if ($excludeId) {
                        return $match && $album['id'] != $excludeId;
                    }
                    return $match;
                });
            }
            
            // Search functionality
            if (strpos($wherePart, 'like') !== false) {
                $searchTerm = '';
                foreach ($params as $param) {
                    if (strpos($param, '%') !== false) {
                        $searchTerm = str_replace('%', '', $param);
                        break;
                    }
                }
                
                if ($searchTerm) {
                    $searchTerm = strtolower($searchTerm);
                    $albums = array_filter($albums, function($album) use ($searchTerm) {
                        return stripos(strtolower($album['artist_name']), $searchTerm) !== false ||
                               stripos(strtolower($album['album_name']), $searchTerm) !== false;
                    });
                }
            }
        }
        
        // Simple ORDER BY parsing
        if (strpos($sql, 'order by') !== false) {
            // Sort by artist_name ASC, release_year ASC, album_name ASC
            if (strpos($sql, 'artist_name asc') !== false && strpos($sql, 'release_year asc') !== false && strpos($sql, 'album_name asc') !== false) {
                usort($albums, function($a, $b) {
                    // First sort by artist name (ignoring articles)
                    $artistSortKeyA = $this->getSortKey($a['artist_name']);
                    $artistSortKeyB = $this->getSortKey($b['artist_name']);
                    $artistCompare = strcmp($artistSortKeyA, $artistSortKeyB);
                    if ($artistCompare !== 0) {
                        return $artistCompare;
                    }
                    
                    // Then sort by release year (ascending)
                    $yearA = $a['release_year'] ?: 0;
                    $yearB = $b['release_year'] ?: 0;
                    $yearCompare = $yearA - $yearB;
                    if ($yearCompare !== 0) {
                        return $yearCompare;
                    }
                    
                    // Finally sort by album name
                    return strcmp($a['album_name'], $b['album_name']);
                });
            }
            // Fallback to just artist name sorting
            elseif (strpos($sql, 'artist_name asc') !== false) {
                usort($albums, function($a, $b) {
                    $artistSortKeyA = $this->getSortKey($a['artist_name']);
                    $artistSortKeyB = $this->getSortKey($b['artist_name']);
                    return strcmp($artistSortKeyA, $artistSortKeyB);
                });
            }
        }
        
        return array_values($albums);
    }
    
    private function handleInsert($sql, $params) {
        $album = [
            'id' => $this->data['next_id']++,
            'artist_name' => $params[0],
            'album_name' => $params[1],
            'release_year' => $params[2] ?: null,
            'is_owned' => $params[3] ?: 0,
            'want_to_own' => $params[4] ?: 0,
            'cover_url' => $params[5] ?? null,
            'discogs_release_id' => $params[6] ?? null,
            'style' => $params[7] ?? null,
            'created_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s')
        ];
        
        $this->data['albums'][] = $album;
        $this->saveData();
        
        return true;
    }
    
    private function handleUpdate($sql, $params) {
        $id = end($params); // Last parameter is the ID
        
        foreach ($this->data['albums'] as &$album) {
            if ($album['id'] == $id) {
                $album['artist_name'] = $params[0];
                $album['album_name'] = $params[1];
                $album['release_year'] = $params[2] ?: null;
                $album['is_owned'] = $params[3] ?: 0;
                $album['want_to_own'] = $params[4] ?: 0;
                $album['cover_url'] = $params[5] ?? null;
                $album['discogs_release_id'] = $params[6] ?? null;
                $album['style'] = $params[7] ?? null;
                $album['updated_date'] = date('Y-m-d H:i:s');
                break;
            }
        }
        
        $this->saveData();
        return true;
    }
    
    private function handleDelete($sql, $params) {
        $id = $params[0];
        
        $this->data['albums'] = array_filter($this->data['albums'], function($album) use ($id) {
            return $album['id'] != $id;
        });
        
        $this->saveData();
        return true;
    }
    
    public function exec($sql) {
        return $this->query($sql);
    }
    
    public function prepare($sql) {
        return new SimpleDBStatement($this, $sql);
    }
}

/**
 * Simple Statement Class
 */
class SimpleDBStatement {
    private $db;
    private $sql;
    
    public function __construct($db, $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }
    
    public function execute($params = []) {
        return $this->db->query($this->sql, $params);
    }
    
    public function fetchAll() {
        return $this->db->query($this->sql);
    }
    
    public function fetch() {
        $result = $this->db->query($this->sql);
        return !empty($result) ? $result[0] : false;
    }
}
?> 