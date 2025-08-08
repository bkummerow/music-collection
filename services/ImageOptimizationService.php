<?php
/**
 * Image Optimization Service
 * Handles image resizing and optimization for better performance
 */

class ImageOptimizationService {
    
    /**
     * Get optimized image URL with size parameters
     */
    public static function getOptimizedImageUrl($originalUrl, $width = 150, $height = 150) {
        if (empty($originalUrl)) {
            return null;
        }
        
        // Force HTTPS for all image URLs
        $secureUrl = self::forceHttps($originalUrl);
        
        // For Discogs images, we need to modify the URL path parameters
        if (strpos($secureUrl, 'discogs.com') !== false) {
            // Discogs uses a specific URL structure with size parameters in the path
            // Original format: /rs:fit/g:sm/q:90/h:598/w:600/
            // We need to replace the h and w parameters with our desired sizes
            
            // First, remove any existing query parameters
            $baseUrl = preg_replace('/\?.*/', '', $secureUrl);
            
            // Replace the size parameters in the URL path
            $pattern = '/\/rs:fit\/g:sm\/q:\d+\/h:\d+\/w:\d+\//';
            $replacement = "/rs:fit/g:sm/q:75/h:{$height}/w:{$width}/";
            
            $optimizedUrl = preg_replace($pattern, $replacement, $baseUrl);
            
            // If the pattern wasn't found, try a simpler approach
            if ($optimizedUrl === $baseUrl) {
                // Fallback: try to add parameters to the end
                return $baseUrl . "?w={$width}&h={$height}&fit=crop&q=75&fm=webp";
            }
            
            return $optimizedUrl;
        }
        
        // For other image sources, return original URL with HTTPS
        return $secureUrl;
    }
    
    /**
     * Force HTTPS for any HTTP URLs
     */
    public static function forceHttps($url) {
        if (empty($url)) {
            return $url;
        }
        
        // Replace http:// with https://
        $url = preg_replace('/^http:\/\//', 'https://', $url);
        
        // Also handle protocol-relative URLs
        $url = preg_replace('/^\/\//', 'https://', $url);
        
        return $url;
    }
    
    /**
     * Get thumbnail URL for album covers - use larger size
     */
    public static function getThumbnailUrl($originalUrl) {
        return self::getOptimizedImageUrl($originalUrl, 60, 60);
    }
    
    /**
     * Get medium size URL for modal covers - use larger size
     */
    public static function getMediumUrl($originalUrl) {
        return self::getOptimizedImageUrl($originalUrl, 120, 120);
    }
    
    /**
     * Get large size URL for cover modal - use larger size
     */
    public static function getLargeUrl($originalUrl) {
        return self::getOptimizedImageUrl($originalUrl, 300, 300);
    }
    
    /**
     * Check if image URL is valid and accessible
     */
    public static function isValidImageUrl($url) {
        if (empty($url)) {
            return false;
        }
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['User-Agent: MusicCollectionApp/1.0']
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
     * Get image dimensions from URL
     */
    public static function getImageDimensions($url) {
        if (empty($url)) {
            return null;
        }
        
        try {
            $imageInfo = getimagesize($url);
            if ($imageInfo) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'type' => $imageInfo[2]
                ];
            }
        } catch (Exception $e) {
            return null;
        }
        
        return null;
    }
}
?> 