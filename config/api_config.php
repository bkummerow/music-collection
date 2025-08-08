<?php
/**
 * API Configuration
 * Store API keys and settings for external services
 */

// Discogs API Configuration
define('DISCOGS_API_KEY', 'qALwSqZPayzoIbBDeqgdoZOrWjspHpWBQsRCyDUm'); // Replace with your actual API key
define('DISCOGS_USER_AGENT', 'MusicCollectionApp/1.0'); // Required by Discogs API

// Last.fm API Configuration (if you want to use their full API)
define('LASTFM_API_KEY', 'YOUR_LASTFM_API_KEY_HERE'); // Optional

// Spotify API Configuration (future use)
define('SPOTIFY_CLIENT_ID', 'YOUR_SPOTIFY_CLIENT_ID_HERE'); // Future use
define('SPOTIFY_CLIENT_SECRET', 'YOUR_SPOTIFY_CLIENT_SECRET_HERE'); // Future use

// API Timeout Settings
define('API_TIMEOUT', 15); // seconds
define('API_MAX_RETRIES', 3);

// Cover Art Settings
define('COVER_ART_MAX_SIZE', 500); // pixels
define('COVER_ART_FALLBACK_SIZE', 250); // pixels
?> 