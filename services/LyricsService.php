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

        // Build Genius direct URL: https://genius.com/{artist}-{song}-lyrics
        $artistSlug = $this->slugifyForGenius($artist);
        $sanitizedTitle = $this->sanitizeTitleForGenius($title);
        $titleSlug = $this->slugifyForGenius($sanitizedTitle);
        
        // Build AZLyrics direct URL: https://www.azlyrics.com/lyrics/{artist}/{song}.html
        $artistAz = $this->tokenizeForAzlyrics($artist);
        $titleAz = $this->tokenizeForAzlyrics($this->sanitizeTitleForGenius($title));

        $geniusDirect = null;
        if (!empty($artistSlug) && !empty($titleSlug)) {
            // Special-case: Wire – Map Ref. 41ºN 93ºW should point to album page on Genius
            $titleKey = strtolower($sanitizedTitle);
            if ($titleKey === 'map ref 41n 93w' && strtolower(trim($artist)) === 'wire') {
                $albumSegment = 'map-ref-41n-93w';
                $artistAlbum = trim($artist);
                $geniusDirect = "https://genius.com/albums/" . rawurlencode($artistAlbum) . "/{$albumSegment}";
            } else {
                $geniusDirect = "https://genius.com/{$artistSlug}-{$titleSlug}-lyrics";
            }
        } else {
            // Fallback to search if we failed to generate a slug
            $geniusDirect = "https://genius.com/search?q={$searchQuery}";
        }

        $azUrl = null;
        if (!empty($artistAz) && !empty($titleAz)) {
            $azUrl = "https://www.azlyrics.com/lyrics/{$artistAz}/{$titleAz}.html";
        }

        return [
            'genius' => $geniusDirect,
            'azlyrics' => $azUrl,
            'google' => "https://www.google.com/search?q={$searchQuery}+lyrics"
        ];
    }

    /**
     * Remove common noise from titles for better Genius URL matching
     */
    private function sanitizeTitleForGenius($title) {
        $clean = $title;
        // Keep content inside parentheses/brackets by removing only the braces
        // e.g., "(Called The Moon)" stays as "Called The Moon" without duplication
        $clean = str_replace(['(', ')', '[', ']'], ' ', $clean);
        // Remove trailing "- Remaster(ed) YYYY" or similar annotations
        $clean = preg_replace('/\s*-\s*remaster(?:ed)?(?:\s*\d{2,4})?/iu', ' ', $clean);
        // Remove featuring credits
        $clean = preg_replace('/\s+(feat\.|featuring)\s+.+$/iu', ' ', $clean);
        // Remove periods entirely in titles
        $clean = str_replace('.', '', $clean);
        // Normalize ampersand to "and"
        $clean = str_ireplace('&', 'and', $clean);
        // Collapse whitespace
        $clean = preg_replace('/\s+/u', ' ', $clean);
        return trim($clean);
    }

    /**
     * Convert a string to a Genius-style slug: lowercase, ASCII, hyphen-separated
     */
    private function slugifyForGenius($text) {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        // Remove degree symbols before transliteration so they don't become 'o'
        $text = str_replace(["º", "°"], '', $text);
        // Transliterate to ASCII where possible
        if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($translit !== false) {
                $text = $translit;
            }
        }
        // Explicitly normalize common diacritics used in artist/title names
        $diacriticsMap = [
            'ä' => 'a', 'Ä' => 'a', 'ö' => 'o', 'Ö' => 'o', 'ü' => 'u', 'Ü' => 'u',
            'ß' => 'ss', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ō' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ū' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ];
        $text = strtr($text, $diacriticsMap);
        // Replace ampersand with "and"
        $text = str_ireplace('&', 'and', $text);
        // Remove periods entirely (e.g., R.E.M. -> REM)
        $text = str_replace('.', '', $text);
        // Remove apostrophes (straight and curly) so they don't become hyphens
        $text = str_replace(["'", "’"], '', $text);
        // Replace non alphanumerics with hyphens
        $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);
        // Trim hyphens
        $text = trim($text, '-');
        // Lowercase
        $text = strtolower($text);
        return $text;
    }

    /**
     * Convert a string to AZLyrics token: lowercase alphanumerics only, spaces/punct removed
     * Example: "R.E.M." -> "rem", "Gardening at Night" -> "gardeningatnight"
     */
    private function tokenizeForAzlyrics($text) {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        // Remove degree symbols BEFORE transliteration so they don't become 'o'
        $text = str_replace(["º", "°"], '', $text);
        if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($translit !== false) {
                $text = $translit;
            }
        }
        // Replace ampersand with 'and' and remove periods
        $text = str_ireplace('&', 'and', $text);
        $text = str_replace('.', '', $text);
        // Handle leading articles per AZLyrics rules, with special-case 'The The'
        // Build a words array using letters+digits (keep numbers like 52 in B-52's)
        $lettersOnly = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $text));
        $words = array_values(array_filter(explode(' ', $lettersOnly), function($w){ return $w !== ''; }));
        if (!empty($words)) {
            if (count($words) === 2 && $words[0] === 'the' && $words[1] === 'the') {
                // Keep both words, will become "thethe" after stripping non-alphanumerics
                $text = 'the the';
            } else if ($words[0] === 'the') {
                // Drop the leading 'the'
                $text = implode(' ', array_slice($words, 1));
            } else if ($words[0] === 'a') {
                // Drop the leading 'a'
                $text = implode(' ', array_slice($words, 1));
            }
        }
        // Remove all non-alphanumeric characters but KEEP digits
        $text = preg_replace('/[^a-zA-Z0-9]/', '', $text);
        // Lowercase
        $text = strtolower($text);
        return $text;
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
