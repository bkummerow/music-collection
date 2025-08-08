<?php
/**
 * SQLite Database Configuration
 * Uses SQLite which is available on your hosting
 */

define('DB_FILE', __DIR__ . '/../data/music_collection.db');
define('DB_CHARSET', 'utf8');

/**
 * Create SQLite database connection
 */
function getDBConnection() {
    try {
        // Create data directory if it doesn't exist
        $dataDir = dirname(DB_FILE);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON');
        
        return $pdo;
    } catch (PDOException $e) {
        die("SQLite Connection failed: " . $e->getMessage());
    }
}

/**
 * Get connection type
 */
function getConnectionType() {
    return 'SQLite';
}

/**
 * Check if SQLite is available
 */
function isSQLiteAvailable() {
    return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers());
}
?> 