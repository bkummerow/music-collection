<?php
/**
 * Lyrics Service
 * Fetches lyrics for tracks using multiple APIs as fallbacks
 */

class LyricsService {
    private $cache = [];
    private $cacheExpiry = 86400; // 24 hours cache
    
    /**
     * Get lyrics for a track
     * For now, we'll skip actual lyrics fetching to avoid blocking the tracklist API
     */
    public function getLyrics($artist, $title) {
        // Skip actual lyrics fetching to avoid blocking the tracklist API
        // We'll just provide the search URLs instead
        return null;
    }
    
    /**
     * Try Lyrics.ovh API
     */
    private function tryLyricsOvh($artist, $title) {
        try {
            $url = 'https://api.lyrics.ovh/v1/' . urlencode($artist) . '/' . urlencode($title);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2, // Reduced timeout to 2 seconds
                    'user_agent' => 'MusicCollection/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['lyrics']) && !empty(trim($data['lyrics']))) {
                return [
                    'lyrics' => trim($data['lyrics']),
                    'source' => 'lyrics.ovh',
                    'url' => "https://www.lyrics.ovh/lyrics/{$artist}/{$title}"
                ];
            }
        } catch (Exception $e) {
            // API failed, continue to next option
        }
        
        return null;
    }
    
    /**
     * Try Genius API (requires API key)
     */
    private function tryGenius($artist, $title) {
        // This would require a Genius API key
        // For now, return null to use other methods
        return null;
    }
    
    /**
     * Try alternative lyrics service
     */
    private function tryLyricsGenius($artist, $title) {
        try {
            // This is a placeholder for another lyrics service
            // Could be implemented with other free APIs
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Generate lyrics search URLs for manual lookup
     */
    public function getLyricsSearchUrls($artist, $title) {
        $searchQuery = urlencode($artist . ' ' . $title);
        
        return [
            'genius' => "https://genius.com/search?q={$searchQuery}",
            'google' => "https://www.google.com/search?q={$searchQuery}+lyrics"
        ];
    }
    
    /**
     * Check if lyrics are available for a track
     * For now, we'll just return false to avoid blocking API calls
     */
    public function hasLyrics($artist, $title) {
        // Skip actual lyrics checking to avoid blocking the tracklist API
        // We'll just provide the search URLs instead
        return false;
    }
}
?>
