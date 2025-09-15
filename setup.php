<?php
/**
 * Setup & Configuration Page
 * Standalone page for configuring Discogs API key, authentication, and theme settings
 */

// Include authentication (this will handle session management)
require_once __DIR__ . '/config/auth_config.php';

// Ensure session is started with proper configuration
ensureSessionStarted();

// Check if user is authenticated
if (!AuthHelper::isAuthenticated()) {
    // Redirect to main page with error message
    header('Location: index.php?error=setup_requires_auth');
    exit();
}

// Load theme and display mode from unified settings to prevent flash
$settingsFile = __DIR__ . '/data/settings.json';
$defaultSettings = [
    'theme' => [
        'gradient_color_1' => '#667eea',
        'gradient_color_2' => '#764ba2'
    ],
    'display_mode' => [
        'theme' => 'light'
    ]
];

$settings = $defaultSettings;
if (file_exists($settingsFile)) {
    clearstatcache(true, $settingsFile);
    $content = file_get_contents($settingsFile);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        if (isset($decoded['theme']) && is_array($decoded['theme'])) {
            $settings['theme'] = array_merge($settings['theme'], $decoded['theme']);
        }
        if (isset($decoded['display_mode']) && is_array($decoded['display_mode'])) {
            $settings['display_mode'] = array_merge($settings['display_mode'], $decoded['display_mode']);
        }
    }
}

$themeColors = $settings['theme'];
$displayMode = $settings['display_mode']['theme'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($displayMode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Configuration - Music Collection</title>

    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">

    <!-- Preconnect to external domains for faster loading -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="assets/css/main.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="https://fonts.gstatic.com/s/inter/v19/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0Q5n-wU.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="https://fonts.gstatic.com/s/jetbrainsmono/v23/tDbv2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKwBNntkaToggR7BYRbKPxTcwgknk-6nFg.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/js/app.min.js" as="script">
    
    <!-- Using system fonts only to eliminate layout shifts -->
    <!-- No external font loading to prevent CLS issues -->
    
    <!-- Fallback for browsers that don't support preload -->
    <noscript>
        <link rel="stylesheet" href="assets/css/main.css">
    </noscript>
    <style>
        :root {
            --gradient-color-1: <?php echo htmlspecialchars($themeColors['gradient_color_1']); ?>;
            --gradient-color-2: <?php echo htmlspecialchars($themeColors['gradient_color_2']); ?>;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 400;
            font-size-adjust: 0.5; /* Match Inter's aspect ratio to prevent layout shifts */
            min-height: 100vh; /* Reserve space to prevent body shifts */
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-weight: 600;
            line-height: 1.2;
            font-size-adjust: 0.5;
        }
        code, pre, .mono {
            font-family: 'JetBrains Mono', 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.4;
            font-size-adjust: 0.5;
        }
        /* Prevent layout shifts during font loading */
        * {
            font-display: swap;
        }
        /* Reserve space for setup page content */
        .setup-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .setup-main {
            flex: 1;
            min-height: 400px; /* Reserve minimum space */
        }
    </style>
</head>
<body class="setup-page">
    <div class="setup-page">
        <div class="setup-container">
            <header class="setup-header">
                <div class="setup-header-content">
                    <div class="header-text">
                        <h1>Setup & Configuration</h1>
                    </div>
                    <div class="setup-nav">
                        <a href="index.php" class="btn-back">
                            Back to Collection
                        </a>
                    </div>
                </div>
            </header>

            <main class="setup-main">
                <!-- Message display area -->
                <div id="message" class="message"></div>
                
                <!-- Tab Navigation -->
                <div class="setup-tabs">
                    <nav class="tab-navigation">
                        <button class="tab-button active" data-tab="api-config">
                            <span class="tab-icon">🔑</span>
                            <span class="tab-label">API Config</span>
                        </button>
                        <button class="tab-button" data-tab="password">
                            <span class="tab-icon">🔒</span>
                            <span class="tab-label">Password</span>
                        </button>
                        <button class="tab-button" data-tab="display-mode">
                            <span class="tab-icon">🎨</span>
                            <span class="tab-label">Display Mode</span>
                        </button>
                        <button class="tab-button" data-tab="settings">
                            <span class="tab-icon">💿</span>
                            <span class="tab-label">Album Display</span>
                        </button>
                        <button class="tab-button" data-tab="stats">
                            <span class="tab-icon">📊</span>
                            <span class="tab-label">Stats Display</span>
                        </button>
                    </nav>
                    
                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- API Config Tab -->
                        <div class="tab-panel active" id="api-config">
                            <div class="setup-section">
                        <h2>Discogs API Configuration</h2>
                        <p>Configure your Discogs API key to enable album lookup and metadata retrieval</p>

                        <div class="setup-instructions">
                            <h3>How to get your Discogs API key:</h3>
                            <ol>
                                <li>Go to <a href="https://www.discogs.com/settings/developers" target="_blank" rel="noopener noreferrer">Discogs Developer Settings</a></li>
                                <li>Create a new application</li>
                                <li>Copy your Consumer Key (this is your API key)</li>
                                <li>Paste it in the field below</li>
                            </ol>
                            
                            <div class="priority-note">
                                <h4>Configuration Priority:</h4>
                                <p><strong>Environment variables take priority</strong> over config file settings. If you're using a hosting platform or have set environment variables, use the <code>DISCOGS_API_KEY</code> environment variable instead of this form.</p>
                            </div>
                        </div>

                        <div class="setup-status" id="setupStatus">
                            <div class="status-item">
                                <span class="status-label">Discogs API Key:</span>
                                <span class="status-details" id="apiKeyDetails" style="display: none;"></span>
                                <span class="status-value" id="apiKeyStatus">Checking...</span>
                            </div>
                        </div>

                        <form id="setupForm">
                            <div class="form-group">
                                <label for="setup_discogs_api_key">Discogs API Key</label>
                                <div class="current-value" id="currentApiKeyDisplay" style="display: none;">
                                    <strong>Current:</strong> <span id="currentApiKeyText"></span>
                                </div>
                                <input 
                                    type="text" 
                                    id="setup_discogs_api_key" 
                                    name="discogs_api_key" 
                                    placeholder="Enter your Discogs API key"
                                    required
                                >
                            </div>

                            <div id="setupMessage" class="setup-message" style="display: none;"></div>

                            <div class="form-buttons">
                                <button type="submit" class="btn-save">Save Configuration</button>
                            </div>
                            </form>
                            </div>
                        </div>

                        <!-- Password Tab -->
                        <div class="tab-panel" id="password">
                          <div class="setup-section">
                            <h2>Authentication Setup</h2>
                            <p>
                                Set up a password to protect your music collection. This password will be required to add, edit, or delete albums.
                            </p>

                            <div class="setup-auth-actions">
                                <button type="button" id="setupPasswordBtn" class="btn-save">
                                    <span id="passwordActionText">Set Password</span>
                                </button>
                            </div>
                          </div>
                        </div>

                        <!-- Display Mode Tab -->
                        <div class="tab-panel" id="display-mode">
                            <div class="setup-section">
                        <h2>Display Mode</h2>
                        <p>
                            Choose your preferred display mode for the interface.
                        </p>
                        
                        <div class="display-mode-group">
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" id="lightMode" name="displayMode" value="light" <?php echo $displayMode === 'light' ? 'checked' : ''; ?>>
                                    <span class="radio-label">
                                        <span class="radio-icon">☀️</span>
                                        Light Mode
                                    </span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" id="darkMode" name="displayMode" value="dark" <?php echo $displayMode === 'dark' ? 'checked' : ''; ?>>
                                    <span class="radio-label">
                                        <span class="radio-icon">🌙</span>
                                        Dark Mode
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="theme-actions">
                            <button type="button" id="saveDisplayModeBtn" class="btn-save">
                                Save Display Mode
                            </button>
                        </div>
                    </div>

                    <div class="setup-section">
                        <h2>Theme Customization</h2>
                        <p>
                            Customize the background gradient colors of your music collection interface.
                        </p>
                        <p class="theme-note">
                            💡 Theme colors are saved locally and synced across devices. Changes will persist on this device and be available on other browsers/devices.
                        </p>
                        <p class="theme-note">
                            ⚠️ Background gradient colors are for light mode only. Dark mode uses a fixed dark theme.
                        </p>
                        <div id="themeMessage" class="setup-message" style="display: none;"></div>

                        <div class="color-picker-group">
                            <div class="color-picker-item">
                                <label for="gradientColor1">Gradient Color 1:</label>
                                <div class="color-input-group">
                                    <input 
                                        type="color" 
                                        id="gradientColor1" 
                                        name="gradient_color_1" 
                                        value="<?php echo htmlspecialchars($themeColors['gradient_color_1']); ?>"
                                        title="Choose the first gradient color"
                                    >
                                    <input 
                                        type="text" 
                                        id="gradientColor1Hex" 
                                        name="gradient_color_1_hex" 
                                        placeholder="<?php echo htmlspecialchars($themeColors['gradient_color_1']); ?>"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        title="Enter hex color code (e.g., #667eea)"
                                    >
                                </div>
                            </div>

                            <div class="color-picker-item">
                                <label for="gradientColor2">Gradient Color 2:</label>
                                <div class="color-input-group">
                                    <input 
                                        type="color" 
                                        id="gradientColor2" 
                                        name="gradient_color_2" 
                                        value="<?php echo htmlspecialchars($themeColors['gradient_color_2']); ?>"
                                        title="Choose the second gradient color"
                                    >
                                    <input 
                                        type="text" 
                                        id="gradientColor2Hex" 
                                        name="gradient_color_2_hex" 
                                        placeholder="<?php echo htmlspecialchars($themeColors['gradient_color_2']); ?>"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        title="Enter hex color code (e.g., #764ba2)"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="theme-actions">
                            <button type="button" id="resetThemeBtn" class="btn-secondary">
                                Reset to Default
                            </button>
                            <button type="button" id="saveThemeBtn" class="btn-save">
                                Save Theme
                            </button>
                            </div>
                        </div>
                        </div>

                        <!-- Album Display Tab -->
                        <div class="tab-panel" id="settings">
                            <div class="setup-section">
                                <h2>Album Display Settings</h2>
                                <p>
                                  Control what information is shown in the album modal.
                                </p>
                                
                                <div class="settings-group">
                                    <h3>Album Information</h3>

                                    <div class="album-info-controls">
                                        <button type="button" id="selectAllAlbumInfo" class="btn-select-all">Select All</button>
                                        <button type="button" id="selectNoneAlbumInfo" class="btn-select-none">Select None</button>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="labelToggle" name="show_label" checked>
                                            <span class="checkbox-label">Show Label</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="formatToggle" name="show_format" checked>
                                            <span class="checkbox-label">Show Format</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="producerToggle" name="show_producer" checked>
                                            <span class="checkbox-label">Show Producer</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="releasedToggle" name="show_released" checked>
                                            <span class="checkbox-label">Show Released Date</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="ratingToggle" name="show_rating" checked>
                                            <span class="checkbox-label">Show Rating</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="lyricsToggle" name="lyrics_display" checked>
                                            <span class="checkbox-label">Show Links to Lyrics</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="settings-group">
                                    <h3>Artist Information Display</h3>
                                    <p class="settings-description">
                                        Choose which artist links to display.
                                    </p>

                                    <div class="artist-links-controls">
                                        <button type="button" id="selectAllArtistLinks" class="btn-select-all">Select All</button>
                                        <button type="button" id="selectNoneArtistLinks" class="btn-select-none">Select None</button>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showFacebook" name="show_facebook" checked>
                                            <span class="checkbox-label">Show Facebook Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showTwitter" name="show_twitter" checked>
                                            <span class="checkbox-label">Show Twitter Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showInstagram" name="show_instagram" checked>
                                            <span class="checkbox-label">Show Instagram Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showYouTube" name="show_youtube" checked>
                                            <span class="checkbox-label">Show YouTube Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showBandcamp" name="show_bandcamp" checked>
                                            <span class="checkbox-label">Show Bandcamp Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showSoundCloud" name="show_soundcloud" checked>
                                            <span class="checkbox-label">Show SoundCloud Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showWikipedia" name="show_wikipedia" checked>
                                            <span class="checkbox-label">Show Wikipedia Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showLastfm" name="show_lastfm" checked>
                                            <span class="checkbox-label">Show Last.fm Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showImdb" name="show_imdb" checked>
                                            <span class="checkbox-label">Show IMDb Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showBluesky" name="show_bluesky" checked>
                                            <span class="checkbox-label">Show Bluesky Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showDiscogs" name="show_discogs" checked>
                                            <span class="checkbox-label">Show Discogs Artist Links</span>
                                        </label>
                                        
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showOfficialWebsite" name="show_official_website" checked>
                                            <span class="checkbox-label">Show Official Website Links</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="theme-actions">
                                    <button type="button" id="saveSettingsBtn" class="btn-save">
                                        Save Settings
                                    </button>
                                    <button type="button" id="resetSettingsBtn" class="btn-secondary">
                                        Reset to Defaults
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Display Tab -->
                        <div class="tab-panel" id="stats">
                            <div class="setup-section">
                                <h2>Statistics Display Settings</h2>
                                <p>
                                    Control what statistics are shown in your music collection.
                                </p>

                                <div class="settings-group">
                                    <h3>Collection Statistics</h3>
                                    <p class="settings-description">
                                        Choose which statistics to display in the collection overview.
                                    </p>
                                    
                                    <div class="stats-controls">
                                        <button type="button" id="selectAllCollectionStats" class="btn-select-all">Select All</button>
                                        <button type="button" id="selectNoneCollectionStats" class="btn-select-none">Select None</button>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showTotalAlbums" name="show_total_albums" checked>
                                            <span class="checkbox-label">Show Total Albums Count</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showOwnedAlbums" name="show_owned_albums" checked>
                                            <span class="checkbox-label">Show Owned Albums Count</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showWantedAlbums" name="show_wanted_albums" checked>
                                            <span class="checkbox-label">Show Wanted Albums Count</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="settings-group">
                                    <h3>Chart Display Options</h3>
                                    <p class="settings-description">
                                        Control which charts are displayed in the Collection Statistics sidebar (only visible on desktop).
                                    </p>
                                    
                                    <div class="stats-controls">
                                        <button type="button" id="selectAllChartStats" class="btn-select-all">Select All</button>
                                        <button type="button" id="selectNoneChartStats" class="btn-select-none">Select None</button>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showYearChart" name="show_year_chart" checked>
                                            <span class="checkbox-label">Show Top 10 Years Chart</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showStyleChart" name="show_style_chart" checked>
                                            <span class="checkbox-label">Show Top 10 Styles Chart</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showFormatChart" name="show_format_chart" checked>
                                            <span class="checkbox-label">Show Top 10 Formats Chart</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showLabelChart" name="show_label_chart" checked>
                                            <span class="checkbox-label">Show Top 10 Labels Chart</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="settings-group">
                                    <h3>Collection Statistics Modal</h3>
                                    <p class="settings-description">
                                      Choose which statistics are displayed in the collection statistics modal.
                                    </p>
                                    
                                    <div class="stats-controls">
                                        <button type="button" id="selectAllModalStats" class="btn-select-all">Select All</button>
                                        <button type="button" id="selectNoneModalStats" class="btn-select-none">Select None</button>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showModalStyles" name="show_modal_styles" checked>
                                            <span class="checkbox-label">Show Top Music Styles in Modal</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showModalYears" name="show_modal_years" checked>
                                            <span class="checkbox-label">Show Top Music Years in Modal</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showModalFormats" name="show_modal_formats" checked>
                                            <span class="checkbox-label">Show Top Music Formats in Modal</span>
                                        </label>
                                        <label class="checkbox-option">
                                            <input type="checkbox" id="showModalLabels" name="show_modal_labels" checked>
                                            <span class="checkbox-label">Show Top Music Labels in Modal</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="theme-actions">
                                    <button type="button" id="saveStatsBtn" class="btn-save">
                                        Save Stats Settings
                                    </button>
                                    <button type="button" id="resetStatsBtn" class="btn-secondary">
                                        Reset to Defaults
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'components/reset_password_modal.php'; ?>

    <script src="assets/js/app.min.js"></script>
    <script>
        // Setup page initialization is handled by the main app.js file
        // which detects the setup-page class and calls initSetupPage()
    </script>
</body>
</html>
