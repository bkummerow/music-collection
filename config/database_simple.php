<?php
/**
 * Simple File-Based Database Configuration
 * Uses JSON files to store data since database extensions aren't available
 */

define('DB_FILE', __DIR__ . '/../data/music_collection.json');
define('DB_LOCK_FILE', __DIR__ . '/../data/music_collection.lock');

/**
 * Create simple file-based database connection
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
 * Get connection type
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
        $albums = $this->data['albums'];
        
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
                    $albums = array_filter($albums, function($album) use ($searchTerm) {
                        return stripos($album['artist_name'], $searchTerm) !== false ||
                               stripos($album['album_name'], $searchTerm) !== false;
                    });
                }
            }
        }
        
        // Simple ORDER BY parsing
        if (strpos($sql, 'order by') !== false) {
            if (strpos($sql, 'artist_name asc') !== false) {
                usort($albums, function($a, $b) {
                    return strcmp($a['artist_name'], $b['artist_name']);
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