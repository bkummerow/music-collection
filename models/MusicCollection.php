<?php
/**
 * Music Collection Model
 * Handles all database operations for the music collection
 */

require_once __DIR__ . '/../config/database.php';

class MusicCollection {
    private $connection;
    private $connectionType;
    
    public function __construct() {
        $this->connection = getDBConnection();
        $this->connectionType = getConnectionType();
    }
    
    /**
     * Execute a query and return results
     */
    private function executeQuery($sql, $params = []) {
        if ($this->connectionType === 'SimpleDB') {
            return $this->connection->query($sql, $params);
        } else {
            throw new Exception("Unsupported database connection type: " . $this->connectionType);
        }
    }
    
    /**
     * Execute a query that doesn't return results (INSERT, UPDATE, DELETE)
     */
    private function executeNonQuery($sql, $params = []) {
        if ($this->connectionType === 'SimpleDB') {
            return $this->connection->query($sql, $params);
        } else {
            throw new Exception("Unsupported database connection type: " . $this->connectionType);
        }
    }
    
    /**
     * Get all albums with optional filtering
     */
    public function getAllAlbums($filter = null, $search = '') {
        $sql = "SELECT * FROM music_collection WHERE 1=1";
        $params = [];
        
        // Apply filter
        if ($filter === 'owned') {
            $sql .= " AND is_owned = 1";
        } elseif ($filter === 'wanted') {
            $sql .= " AND want_to_own = 1";
        }
        
        // Apply search
        if (!empty($search)) {
            $sql .= " AND (artist_name LIKE ? OR album_name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY artist_name ASC, release_year ASC, album_name ASC";
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get album by ID
     */
    public function getAlbumById($id) {
        $sql = "SELECT * FROM music_collection WHERE id = ?";
        $result = $this->executeQuery($sql, [$id]);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if album already exists
     */
    public function albumExists($artistName, $albumName, $excludeId = null) {
        $sql = "SELECT id FROM music_collection WHERE LOWER(artist_name) = LOWER(?) AND LOWER(album_name) = LOWER(?)";
        $params = [$artistName, $albumName];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->executeQuery($sql, $params);
        return !empty($result);
    }
    
    /**
     * Add new album
     */
    public function addAlbum($artistName, $albumName, $releaseYear, $isOwned, $wantToOwn, $coverUrl = null, $discogsReleaseId = null, $style = null, $format = null) {
        // Check for duplicates
        if ($this->albumExists($artistName, $albumName)) {
            throw new Exception("Album '$albumName' by '$artistName' already exists in your collection.");
        }
        
        $sql = "INSERT INTO music_collection (artist_name, album_name, release_year, is_owned, want_to_own, cover_url, discogs_release_id, style, format) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        return $this->executeNonQuery($sql, [$artistName, $albumName, $releaseYear, $isOwned, $wantToOwn, $coverUrl, $discogsReleaseId, $style, $format]);
    }
    
    /**
     * Update album
     */
    public function updateAlbum($id, $artistName, $albumName, $releaseYear, $isOwned, $wantToOwn, $coverUrl = null, $discogsReleaseId = null, $style = null, $format = null) {
        // Check for duplicates (excluding current album)
        if ($this->albumExists($artistName, $albumName, $id)) {
            throw new Exception("Album '$albumName' by '$artistName' already exists in your collection.");
        }
        
        $sql = "UPDATE music_collection 
                SET artist_name = ?, album_name = ?, release_year = ?, is_owned = ?, want_to_own = ?, cover_url = ?, discogs_release_id = ?, style = ?, format = ? 
                WHERE id = ?";
        return $this->executeNonQuery($sql, [$artistName, $albumName, $releaseYear, $isOwned, $wantToOwn, $coverUrl, $discogsReleaseId, $style, $format, $id]);
    }
    
    /**
     * Delete album
     */
    public function deleteAlbum($id) {
        $sql = "DELETE FROM music_collection WHERE id = ?";
        return $this->executeNonQuery($sql, [$id]);
    }
    
    /**
     * Get unique artists for autocomplete
     */
    public function getArtists($search = '') {
        $sql = "SELECT DISTINCT artist_name FROM music_collection";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " WHERE artist_name LIKE ?";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY artist_name ASC";
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get albums by artist for autocomplete
     */
    public function getAlbumsByArtist($artistName, $search = '') {
        $sql = "SELECT id, artist_name, album_name, release_year, is_owned, want_to_own, cover_url, discogs_release_id, style FROM music_collection WHERE artist_name = ?";
        $params = [$artistName];
        
        if (!empty($search)) {
            $sql .= " AND album_name LIKE ?";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY album_name ASC";
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get statistics
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total_albums,
                    SUM(is_owned) as owned_count,
                    SUM(want_to_own) as wanted_count,
                    COUNT(DISTINCT artist_name) as unique_artists
                FROM music_collection";
        
        $result = $this->executeQuery($sql);
        $stats = !empty($result) ? $result[0] : [
            'total_albums' => 0,
            'owned_count' => 0,
            'wanted_count' => 0,
            'unique_artists' => 0
        ];
        
        // The SimpleDB implementation already includes style_counts and format_counts
        // in the main stats query, so we just need to add year_counts
        if (!isset($stats['style_counts'])) {
            $stats['style_counts'] = [];
        }
        if (!isset($stats['format_counts'])) {
            $stats['format_counts'] = [];
        }
        
        // Get year counts manually since SimpleDB doesn't handle GROUP BY for years
        $albums = $this->getAllAlbums();
        $yearCounts = [];
        
        foreach ($albums as $album) {
            if (!empty($album['release_year'])) {
                $year = $album['release_year'];
                $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
            }
        }
        
        // Sort by count (descending) and then by year (descending)
        // Manual sorting approach
        $sortedYears = [];
        $sortedCounts = [];
        
        // First, sort by count (descending)
        arsort($yearCounts);
        

        
        // Now handle ties by year (descending)
        $currentCount = null;
        $yearsWithSameCount = [];
        
        foreach ($yearCounts as $year => $count) {
            if ($currentCount === null) {
                $currentCount = $count;
            }
            
            if ($count == $currentCount) {
                $yearsWithSameCount[] = (int)$year;
            } else {
                // Sort the years with the same count (descending)
                rsort($yearsWithSameCount);
                foreach ($yearsWithSameCount as $sortedYear) {
                    $sortedYears[] = $sortedYear;
                    $sortedCounts[] = $currentCount;
                }
                
                // Start new group
                $currentCount = $count;
                $yearsWithSameCount = [(int)$year];
            }
        }
        
        // Handle the last group
        if (!empty($yearsWithSameCount)) {
            rsort($yearsWithSameCount);
            foreach ($yearsWithSameCount as $sortedYear) {
                $sortedYears[] = $sortedYear;
                $sortedCounts[] = $currentCount;
            }
        }
        

        
        // Build the final associative array
        $sortedYearCounts = [];
        for ($i = 0; $i < count($sortedYears); $i++) {
            $sortedYearCounts[(string)$sortedYears[$i]] = $sortedCounts[$i];
        }
        

        
        $stats['year_counts'] = $sortedYearCounts;
        
        return $stats;
    }
}
?> 