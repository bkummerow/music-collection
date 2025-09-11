<?php
/**
 * API Configuration
 * Store API keys and settings for external services
 */

// Discogs API Configuration
define('DISCOGS_API_KEY', $_ENV['DISCOGS_API_KEY'] ?? 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Use environment variable or fallback
define('DISCOGS_USER_AGENT', $_ENV['DISCOGS_USER_AGENT'] ?? 'MusicCollectionApp/1.0'); // Use environment variable or fallback

// API Timeout Settings
define('API_TIMEOUT', $_ENV['API_TIMEOUT'] ?? 15); // seconds 