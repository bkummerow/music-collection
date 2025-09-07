<?php
/**
 * Setup & Configuration Page
 * Standalone page for configuring Discogs API key, authentication, and theme settings
 */

// Include authentication (this will handle session management)
require_once __DIR__ . '/config/auth_config.php';

// Ensure session is started with proper configuration
ensureSessionStarted();

// Load theme colors server-side to prevent flash
$themeFile = __DIR__ . '/data/theme.json';
$defaultColors = [
    'gradient_color_1' => '#667eea',
    'gradient_color_2' => '#764ba2'
];

// Clear any file cache and load fresh theme data
if (file_exists($themeFile)) {
    clearstatcache(true, $themeFile); // Clear file cache
    $content = file_get_contents($themeFile);
    $theme = json_decode($content, true);
    
    if ($theme && is_array($theme) && isset($theme['gradient_color_1']) && isset($theme['gradient_color_2'])) {
        $themeColors = $theme; // Use the actual saved colors, not merged defaults
    } else {
        $themeColors = $defaultColors;
    }
} else {
    $themeColors = $defaultColors;
}

// Load display mode preference server-side to prevent flash
$displayModeFile = __DIR__ . '/data/display_mode.json';
$defaultDisplayMode = 'light';

if (file_exists($displayModeFile)) {
    clearstatcache(true, $displayModeFile); // Clear file cache
    $content = file_get_contents($displayModeFile);
    $displayModeData = json_decode($content, true);
    
    if ($displayModeData && is_array($displayModeData) && isset($displayModeData['theme'])) {
        $displayMode = $displayModeData['theme'];
    } else {
        $displayMode = $defaultDisplayMode;
    }
} else {
    $displayMode = $defaultDisplayMode;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($displayMode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Configuration - Music Collection</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        :root {
            --gradient-color-1: <?php echo htmlspecialchars($themeColors['gradient_color_1']); ?>;
            --gradient-color-2: <?php echo htmlspecialchars($themeColors['gradient_color_2']); ?>;
        }
    </style>
</head>
<body class="setup-page">
    <div class="setup-page">
        <div class="setup-container">
            <header class="setup-header">
                <div class="setup-header-content">
                    <h1>Setup & Configuration</h1>
                    <p>Configure your Discogs API key, authentication, and theme settings</p>
                    <div class="setup-nav">
                        <a href="index.php" class="btn-back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                            </svg>
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
                            <span class="tab-icon">⚙️</span>
                            <span class="tab-label">Settings</span>
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
                        </div>

                        <div class="setup-status" id="setupStatus">
                            <div class="status-item">
                                <span class="status-label">Discogs API Key:</span>
                                <span class="status-value" id="apiKeyStatus">Checking...</span>
                            </div>

                            <div class="status-item overall-status">
                                <span class="status-label">Overall Setup:</span>
                                <span class="status-value" id="overallStatus">Checking...</span>
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

                    <!-- Settings Tab -->
                    <div class="tab-panel" id="settings">
                        <div class="setup-section">
                            <h2>Settings</h2>
                            <p>
                                Configure additional display and behavior options for your music collection.
                            </p>
                            
                            <div class="settings-group">
                                <h3>Tracklist Display Options</h3>
                                <p class="settings-description">
                                    Control what information is shown in the tracklist modal.
                                </p>

                                <div class="toggle-group">
                                    <h4>Album Information</h4>
                                    <div class="toggle-option">
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="labelToggle" name="show_label" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Show Label</span>
                                    </div>
                                    <div class="toggle-option">
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="formatToggle" name="show_format" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Show Format</span>
                                    </div>
                                    <div class="toggle-option">
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="producerToggle" name="show_producer" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Show Producer</span>
                                    </div>
                                    <div class="toggle-option">
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="releasedToggle" name="show_released" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Show Released Date</span>
                                    </div>
                                    <div class="toggle-option">
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="ratingToggle" name="show_rating" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Show Rating</span>
                                    </div>
                                    <div class="toggle-option">
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="lyricsToggle" name="lyrics_display" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label">Show Links to Lyrics</span>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-group">
                                <h4>Artist Information Display</h4>
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
                </div>
            </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script>
        // Setup page initialization is handled by the main app.js file
        // which detects the setup-page class and calls initSetupPage()
    </script>
</body>
</html>
