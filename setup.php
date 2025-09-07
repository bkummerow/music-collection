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

    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">

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

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
      <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Reset Admin Password</h2>

        <div class="warning">
          <h3>⚠️ Security Warning</h3>
          <p>Make sure to remember your new password - there's no password recovery option.</p>
        </div>

        <div class="password-requirements">
          <h3>Password Requirements:</h3>
          <ul>
            <li>At least 6 characters long</li>
            <li>Use a strong, unique password</li>
            <li>Consider using a password manager</li>
          </ul>
        </div>

        <form id="resetPasswordForm">
          <div class="form-group">
            <label for="reset_current_password">Current Password</label>
            <div class="password-input-wrapper">
              <input 
                type="password" 
                id="reset_current_password" 
                name="current_password" 
                placeholder="Enter current password"
                required
              >
              <button type="button" id="toggleResetCurrentPassword" class="toggle-password-btn" title="Show/hide password">
                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                </svg>
                <svg class="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="display: none;">
                  <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                  <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                  <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="reset_new_password">New Password</label>
            <div class="password-input-wrapper">
              <input 
                type="password" 
                id="reset_new_password" 
                name="new_password" 
                placeholder="Enter new password"
                required
              >
              <button type="button" id="toggleResetNewPassword" class="toggle-password-btn" title="Show/hide password">
                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                </svg>
                <svg class="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="display: none;">
                  <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                  <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                  <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="reset_confirm_password">Confirm New Password</label>
            <div class="password-input-wrapper">
              <input 
                type="password" 
                id="reset_confirm_password" 
                name="confirm_password" 
                placeholder="Confirm new password"
                required
              >
              <button type="button" id="toggleResetConfirmPassword" class="toggle-password-btn" title="Show/hide password">
                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                </svg>
                <svg class="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="display: none;">
                  <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                  <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                  <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                </svg>
              </button>
            </div>
          </div>

          <div id="resetPasswordMessage" class="modal-message" style="display: none;"></div>

          <div class="form-buttons">
            <button type="button" class="btn-cancel">Cancel</button>
            <button type="submit" class="btn-save">Update Password</button>
          </div>
        </form>
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
