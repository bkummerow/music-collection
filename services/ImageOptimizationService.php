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
        
        // For Discogs images, use our local proxy to avoid rate limiting
        if (strpos($secureUrl, 'discogs.com') !== false) {
            // Use our local image proxy with size parameters
            $proxyUrl = 'api/image_proxy.php?url=' . urlencode($secureUrl);
            if ($width) {
                $proxyUrl .= '&w=' . $width;
            }
            if ($height) {
                $proxyUrl .= '&h=' . $height;
            }
            return $proxyUrl;
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
     * Get thumbnail URL for album covers - optimized for table display
     */
    public static function getThumbnailUrl($originalUrl) {
        return self::getOptimizedImageUrl($originalUrl, 60, 60);
    }
    
    /**
     * Get medium size URL for modal covers - optimized for tracklist modal
     */
    public static function getMediumUrl($originalUrl) {
        return self::getOptimizedImageUrl($originalUrl, 120, 120);
    }
    
    /**
     * Get large size URL for cover modal - optimized for cover modal
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