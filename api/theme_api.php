<?php
/**
 * Theme API - Handle theme color saving and loading
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// Safe string length helper (works without mbstring)
function str_length($text) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return strlen($text);
}


// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$settingsFile = __DIR__ . '/../data/settings.json';
$defaultColors = [
    'gradient_color_1' => '#667eea',
    'gradient_color_2' => '#764ba2'
];
$defaultDisplayMode = 'light';
$defaultAppSettings = [
    'title' => 'Music Collection',
    'description' => '',
    'meta_description' => '',
    'start_url' => ''
];
$defaultAlbumDisplaySettings = [
    'show_facebook' => true,
    'show_twitter' => true,
    'show_instagram' => true,
    'show_youtube' => true,
    'show_bandcamp' => true,
    'show_soundcloud' => true,
    'show_wikipedia' => true,
    'show_lastfm' => true,
    'show_imdb' => true,
    'show_bluesky' => true,
    'show_discogs' => true,
    'show_official_website' => true,
    'show_view_album_on_discogs' => true,
    'show_for_sale_on_discogs' => true,
    'show_album_count' => true,
    'show_year_range' => true,
    'enable_animations' => true,
    'show_lyrics' => true,
    'show_genius_lyrics' => true,
    'show_azlyrics_lyrics' => true,
    'show_google_lyrics' => true,
    'show_producer' => true,
    'show_label' => true,
    'show_released' => true,
    'show_runtime' => true,
    'show_rating' => true,
    'show_format' => true,
    'currency_preference' => 'USD'
];
$defaultStatsDisplaySettings = [
    'show_total_albums' => true,
    'show_owned_albums' => true,
    'show_wanted_albums' => true,
    'show_year_chart' => true,
    'show_style_chart' => true,
    'show_format_chart' => true,
    'show_label_chart' => true,
    'show_modal_styles' => true,
    'show_modal_years' => true,
    'show_modal_formats' => true,
    'show_modal_labels' => true
];

function loadAllSettings() {
    global $settingsFile;
    
    $defaultSettings = [
        'theme' => [
            'gradient_color_1' => '#667eea',
            'gradient_color_2' => '#764ba2'
        ],
        'display_mode' => [
            'theme' => 'light'
        ],
        'app' => [
            'title' => 'Music Collection',
            'description' => '',
            'meta_description' => '',
            'start_url' => ''
        ],
        'album_display' => [
            'show_facebook' => true,
            'show_twitter' => true,
            'show_instagram' => true,
            'show_youtube' => true,
            'show_bandcamp' => true,
            'show_soundcloud' => true,
            'show_wikipedia' => true,
            'show_lastfm' => true,
            'show_imdb' => true,
            'show_bluesky' => true,
            'show_discogs' => true,
            'show_official_website' => true,
            'show_view_album_on_discogs' => true,
            'show_for_sale_on_discogs' => true,
            'show_album_count' => true,
            'show_year_range' => true,
            'enable_animations' => true,
            'show_lyrics' => true,
            'show_genius_lyrics' => true,
            'show_azlyrics_lyrics' => true,
            'show_google_lyrics' => true,
            'show_producer' => true,
            'show_label' => true,
            'show_released' => true,
            'show_runtime' => true,
            'show_rating' => true,
            'show_format' => true,
            'currency_preference' => 'USD'
        ],
        'stats_display' => [
            'show_total_albums' => true,
            'show_owned_albums' => true,
            'show_wanted_albums' => true,
            'show_year_chart' => true,
            'show_style_chart' => true,
            'show_format_chart' => true,
            'show_label_chart' => true,
            'show_modal_styles' => true,
            'show_modal_years' => true,
            'show_modal_formats' => true,
            'show_modal_labels' => true
        ]
    ];
    
    if (file_exists($settingsFile)) {
        $content = file_get_contents($settingsFile);
        $settings = json_decode($content, true);
        
        if ($settings && is_array($settings)) {
            // Merge with defaults, ensuring all sections exist
            foreach ($defaultSettings as $section => $defaultSection) {
                if (!isset($settings[$section])) {
                    $settings[$section] = $defaultSection;
                } else {
                    $settings[$section] = array_merge($defaultSection, $settings[$section]);
                }
            }
            return $settings;
        }
    }
    
    return $defaultSettings;
}

function saveAllSettings($newSettings) {
    global $settingsFile;
    
    // Load current settings first
    $currentSettings = loadAllSettings();
    
    // Merge new settings with current settings
    foreach ($newSettings as $section => $sectionData) {
        if (isset($currentSettings[$section]) && is_array($sectionData)) {
            $currentSettings[$section] = array_merge($currentSettings[$section], $sectionData);
        } else {
            $currentSettings[$section] = $sectionData;
        }
    }
    
    // Clear file cache before writing
    clearstatcache(true, $settingsFile);
    
    // Save to file
    $result = file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to save settings'];
    }
    
    // Verify the file was written correctly
    $writtenContent = file_get_contents($settingsFile);
    $writtenData = json_decode($writtenContent, true);
    
    if ($writtenData !== $currentSettings) {
        return ['success' => false, 'message' => 'Settings were not saved correctly'];
    }
    
    return ['success' => true, 'message' => 'Settings saved successfully'];
}

function loadThemeColors() {
    $settings = loadAllSettings();
    return $settings['theme'];
}

function saveThemeColors($colors) {
    // Validate colors
    if (!isset($colors['gradient_color_1']) || !isset($colors['gradient_color_2'])) {
        return ['success' => false, 'message' => 'Missing color parameters'];
    }
    
    // Validate hex format
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colors['gradient_color_1']) ||
        !preg_match('/^#[0-9A-Fa-f]{6}$/', $colors['gradient_color_2'])) {
        return ['success' => false, 'message' => 'Invalid color format'];
    }
    
    return saveAllSettings(['theme' => $colors]);
}

function loadDisplayMode() {
    $settings = loadAllSettings();
    return $settings['display_mode']['theme'];
}

function saveDisplayMode($theme) {
    // Validate theme
    if (!in_array($theme, ['light', 'dark'])) {
        return ['success' => false, 'message' => 'Invalid display mode'];
    }
    
    return saveAllSettings(['display_mode' => ['theme' => $theme]]);
}

function loadAppSettings() {
    $settings = loadAllSettings();
    return $settings['app'];
}

function saveAppSettings($appSettings) {
    // Validate title
    if (!isset($appSettings['title'])) {
        return ['success' => false, 'message' => 'Missing title'];
    }
    $title = trim((string)$appSettings['title']);
    if ($title === '') {
        return ['success' => false, 'message' => 'Title cannot be empty'];
    }
    if (str_length($title) > 120) {
        return ['success' => false, 'message' => 'Title too long'];
    }
    // Optional description
    $description = '';
    if (isset($appSettings['description'])) {
        $description = trim((string)$appSettings['description']);
        if (str_length($description) > 1000) {
            return ['success' => false, 'message' => 'Description too long'];
        }
    }
    // Optional meta description
    $metaDescription = '';
    if (isset($appSettings['meta_description'])) {
        $metaDescription = trim((string)$appSettings['meta_description']);
        if (str_length($metaDescription) > 300) {
            return ['success' => false, 'message' => 'Meta description too long'];
        }
    }
    // Optional start_url
    $startUrl = '';
    if (isset($appSettings['start_url'])) {
        $startUrl = trim((string)$appSettings['start_url']);
        // Normalize: empty allowed; else must start and end with '/'
        if ($startUrl !== '') {
            // Ensure leading '/'
            if ($startUrl[0] !== '/') {
                $startUrl = '/' . $startUrl;
            }
            // Ensure trailing '/'
            if (substr($startUrl, -1) !== '/') {
                $startUrl .= '/';
            }
            // Prevent dangerous characters
            if (preg_match('/[^A-Za-z0-9_\-\/]/', $startUrl)) {
                return ['success' => false, 'message' => 'Invalid characters in Start URL'];
            }
        }
    }
    return saveAllSettings(['app' => [
        'title' => $title,
        'description' => $description,
        'meta_description' => $metaDescription,
        'start_url' => $startUrl
    ]]);
}

function loadAlbumDisplaySettings() {
    $settings = loadAllSettings();
    return $settings['album_display'];
}

function saveAlbumDisplaySettings($settings) {
    // Validate settings
    $validKeys = ['show_facebook', 'show_twitter', 'show_instagram', 'show_youtube', 'show_bandcamp', 'show_soundcloud', 'show_wikipedia', 'show_lastfm', 'show_imdb', 'show_bluesky', 'show_discogs', 'show_official_website', 'show_view_album_on_discogs', 'show_for_sale_on_discogs', 'show_album_count', 'show_year_range', 'enable_animations', 'show_lyrics', 'show_genius_lyrics', 'show_azlyrics_lyrics', 'show_google_lyrics', 'show_producer', 'show_label', 'show_released', 'show_runtime', 'show_rating', 'show_format', 'currency_preference'];
    
    foreach ($settings as $key => $value) {
        if (!in_array($key, $validKeys)) {
            return ['success' => false, 'message' => "Invalid setting key: $key"];
        }
        
        // currency_preference is a string ISO code; others are booleans
        if ($key === 'currency_preference') {
            $value = strtoupper(trim((string)$value));
            if ($value !== '' && !in_array($value, ['USD','GBP','EUR','CAD','AUD','JPY','CHF','MXN','BRL','NZD','SEK','ZAR'])) {
                return ['success' => false, 'message' => 'Invalid currency code'];
            }
        } else if (!is_bool($value)) {
            return ['success' => false, 'message' => "Invalid boolean value for $key"];
        }
    }
    
    return saveAllSettings(['album_display' => $settings]);
}

function loadStatsDisplaySettings() {
    $settings = loadAllSettings();
    return $settings['stats_display'];
}

function saveStatsDisplaySettings($settings) {
    // Validate settings
    $validKeys = ['show_total_albums', 'show_owned_albums', 'show_wanted_albums', 'show_year_chart', 'show_style_chart', 'show_format_chart', 'show_label_chart', 'show_modal_styles', 'show_modal_years', 'show_modal_formats', 'show_modal_labels'];
    
    foreach ($settings as $key => $value) {
        if (!in_array($key, $validKeys)) {
            return ['success' => false, 'message' => "Invalid setting key: $key"];
        }
        
        if (!is_bool($value)) {
            return ['success' => false, 'message' => "Invalid boolean value for $key"];
        }
    }
    
    return saveAllSettings(['stats_display' => $settings]);
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

// Check request type
$isDisplayModeRequest = isset($_GET['type']) && $_GET['type'] === 'display_mode';
$isAlbumDisplaySettingsRequest = isset($_GET['type']) && $_GET['type'] === 'album_display_settings';
$isStatsDisplaySettingsRequest = isset($_GET['type']) && $_GET['type'] === 'stats_display_settings';
$isAppSettingsRequest = isset($_GET['type']) && $_GET['type'] === 'app_settings';

switch ($method) {
    case 'GET':
        if ($isDisplayModeRequest) {
            $displayMode = loadDisplayMode();
            echo json_encode([
                'success' => true,
                'data' => ['theme' => $displayMode]
            ]);
        } elseif ($isAppSettingsRequest) {
            $app = loadAppSettings();
            echo json_encode([
                'success' => true,
                'data' => $app
            ]);
        } elseif ($isAlbumDisplaySettingsRequest) {
            $settings = loadAlbumDisplaySettings();
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
        } elseif ($isStatsDisplaySettingsRequest) {
            $settings = loadStatsDisplaySettings();
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
        } else {
            $colors = loadThemeColors();
            echo json_encode([
                'success' => true,
                'data' => $colors
            ]);
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data'
            ]);
            break;
        }
        
        if ($isDisplayModeRequest) {
            $result = saveDisplayMode($input['theme'] ?? '');
            echo json_encode($result);
        } elseif ($isAppSettingsRequest) {
            $result = saveAppSettings($input);
            echo json_encode($result);
        } elseif ($isAlbumDisplaySettingsRequest) {
            $result = saveAlbumDisplaySettings($input);
            echo json_encode($result);
        } elseif ($isStatsDisplaySettingsRequest) {
            $result = saveStatsDisplaySettings($input);
            echo json_encode($result);
        } else {
            $result = saveThemeColors($input);
            echo json_encode($result);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
}
?>
