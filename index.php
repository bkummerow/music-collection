<?php
/**
 * Music Collection Manager
 * Main application page with authentication
 */

// Include authentication (this will handle session management)
require_once __DIR__ . '/config/auth_config.php';

// Include reusable components
require_once __DIR__ . '/components/components.php';

// Ensure session is started with proper configuration
ensureSessionStarted();

// Load theme and display mode from unified settings to prevent flash
$settingsFile = __DIR__ . '/data/settings.json';
$defaultSettings = [
    'theme' => [
        'gradient_color_1' => '#667eea',
        'gradient_color_2' => '#764ba2'
    ],
    'display_mode' => [
        'theme' => 'light'
    ],
    'app' => [
        'title' => 'Music Collection'
    ]
];

$settings = $defaultSettings;
if (file_exists($settingsFile)) {
    clearstatcache(true, $settingsFile);
    $content = file_get_contents($settingsFile);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        // Merge shallowly for the sections we need
        if (isset($decoded['theme']) && is_array($decoded['theme'])) {
            $settings['theme'] = array_merge($settings['theme'], $decoded['theme']);
        }
        if (isset($decoded['display_mode']) && is_array($decoded['display_mode'])) {
            $settings['display_mode'] = array_merge($settings['display_mode'], $decoded['display_mode']);
        }
        if (isset($decoded['app']) && is_array($decoded['app'])) {
            $settings['app'] = array_merge($settings['app'], $decoded['app']);
        }
    }
}

$themeColors = $settings['theme'];
$displayMode = $settings['display_mode']['theme'];
$appTitle = $settings['app']['title'];
$appDescription = isset($settings['app']['description']) ? (string)$settings['app']['description'] : '';
$appMetaDescription = isset($settings['app']['meta_description']) && $settings['app']['meta_description'] !== ''
    ? (string)$settings['app']['meta_description']
    : 'Track your vinyl music collection with album covers, tracklists, and Discogs integration. Organize your music library by artist, album, release year, and ownership status.';

// Handle error messages from redirects
$errorMessage = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'setup_requires_auth':
            $errorMessage = 'You must be logged in to access the setup page.';
            break;
        default:
            $errorMessage = 'An error occurred.';
            break;
    }
}

// Override session cache headers to allow back/forward cache
// This is safe because we're not caching sensitive data, just the page structure
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $displayMode ?>"<?php if ($displayMode === 'dark'): ?> style="background: #000000; color: #ffffff;"<?php endif; ?>>
<head>
  <!-- Apply theme immediately to prevent flash -->
  <script>
    // Apply theme immediately to prevent flash of light mode
    (function() {
      const savedMode = '<?= $displayMode ?>';
      if (savedMode === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        // Also apply inline styles immediately
        document.documentElement.style.setProperty('--bg-primary', '#1a1a1a');
        document.documentElement.style.setProperty('--bg-secondary', '#2d2d2d');
        document.documentElement.style.setProperty('--text-primary', '#e0e0e0');
        document.documentElement.style.setProperty('--text-light', '#ffffff');
        // Only apply body styles if body exists
        if (document.body) {
          document.body.style.backgroundColor = '#000000';
          document.body.style.color = '#ffffff';
        }
      } else {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
  </script>
  
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Immediate styling to prevent unstyled elements flash -->
  <!-- Critical CSS -->
  <link rel="stylesheet" href="assets/css/critical.css">
  <?php if ($displayMode === 'dark'): ?>
  <link rel="stylesheet" href="assets/css/critical-dark.css">
  <?php endif; ?>
  <meta name="description" content="<?= htmlspecialchars($appMetaDescription) ?>">
  <meta name="browsermode" content="application">
  <meta name="keywords" content="music collection, vinyl records, album database, Discogs, music library, album covers, tracklists">
  <meta name="author" content="Music Collection App">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="<?= htmlspecialchars($appTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($appMetaDescription) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="">
  <meta property="og:image" content="">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= htmlspecialchars($appTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($appMetaDescription) ?>">
  <title><?= htmlspecialchars($appTitle) ?></title>
  
  <!-- Favicon and App Icons -->
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest.php">
  
  <!-- Preconnect to external domains for faster loading -->
  <link rel="preconnect" href="https://api.discogs.com">
  <link rel="preconnect" href="https://i.discogs.com">
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link rel="dns-prefetch" href="https://api.discogs.com">
  <link rel="dns-prefetch" href="https://i.discogs.com">
  
  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/main.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <link rel="preload" href="https://fonts.gstatic.com/s/inter/v19/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0Q5n-wU.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="https://fonts.gstatic.com/s/jetbrainsmono/v23/tDbv2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKwBNntkaToggR7BYRbKPxTcwgknk-6nFg.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="assets/js/app.min.js" as="script">
  
  <!-- Preload Inter font weights to prevent layout shifts -->
  <link rel="preload" href="https://fonts.gstatic.com/s/inter/v19/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0Q5n-wU.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="https://fonts.gstatic.com/s/inter/v19/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa2JL7W0Q5n-wU.woff2" as="font" type="font/woff2" crossorigin>
  
  <!-- Using system fonts only to eliminate layout shifts -->
  <!-- No external font loading to prevent CLS issues -->
  
  <!-- Fallback for browsers that don't support preload -->
  <noscript>
    <link rel="stylesheet" href="assets/css/main.css">
  </noscript>
  
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <h1><?= htmlspecialchars($appTitle) ?></h1>
      <div class="auth-controls">
        <div class="dropdown">
          <button class="btn-settings dropdown-toggle" title="Click to Open Settings Menu" aria-label="Settings Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
              <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
            </svg>
          </button>
          <div id="settingsDropdown" class="dropdown-menu">
            <button id="loginBtn" class="dropdown-item login-item" onclick="app.showLoginModal()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M6 12.5a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-8a.5.5 0 0 0-.5.5v2a.5.5 0 0 1-1 0v-2A1.5 1.5 0 0 1 6.5 2h8A1.5 1.5 0 0 1 16 3.5v9a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 5 12.5v-2a.5.5 0 0 1 1 0v2z"/>
                <path fill-rule="evenodd" d="M.146 8.354a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L1.707 7.5H10.5a.5.5 0 0 1 0 1H1.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3z"/>
              </svg>
              Log In
            </button>
            <button id="logoutBtn" class="dropdown-item logout-item" onclick="app.handleLogout()" style="display: none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
              </svg>
              Log Out
            </button>
            <button id="resetPasswordBtn" class="dropdown-item reset-password-item" onclick="app.showResetPasswordModal()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
              </svg>
              Reset Password
            </button>
            <?php if (isset($_ENV['DEMO_MODE']) && $_ENV['DEMO_MODE'] === 'true'): ?>
            <button id="resetDemoBtn" class="dropdown-item demo-reset-item" onclick="app.handleDemoReset()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
              </svg>
              Reset Demo
            </button>
            <?php endif; ?>
            <button id="statsBtn" class="dropdown-item stats-item" onclick="app.showModalById('statsModal')">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M1 15h2v-6H1v6zm3.5 0h2v-8h-2v8zm3.5 0h2V5h-2v10zm3.5 0h2V2h-2v13zm3.5 0h2V7h-2v8z"/>
              </svg>
              Collection Statistics
            </button>
            <button id="clearCacheBtn" class="dropdown-item">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
              </svg>
              Clear Caches
            </button>
            <button id="setupConfigBtn" class="dropdown-item setup-config-item" onclick="window.location.href='setup.php'" style="display: none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
              </svg>
              Setup & Configuration
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Optional Description -->
    <div class="description">
      <?php if ($appDescription !== ''): ?>
        <p><?= nl2br(htmlspecialchars($appDescription)) ?></p>
      <?php endif; ?>
    </div>

    <!-- Message Display -->
    <div id="message" class="message<?php echo !empty($errorMessage) ? ' show error' : ''; ?>">
        <?php if (!empty($errorMessage)): ?>
            <strong>Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
        <?php endif; ?>
    </div>

    <!-- Main Content Area with Sidebar -->
    <div class="content-with-sidebar">
      <!-- Main Content -->
      <div class="main-content">
        <!-- Controls -->
        <div class="controls">
          <div class="search-box">
            <label for="searchInput" class="sr-only">
              <span>Search albums, artists, or styles</span>
            </label>
            <div class="search-input-wrapper">
              <input type="text" id="searchInput" placeholder="Search albums, artists, or styles (e.g., style: rock)...">
              <button type="button" id="clearSearch" class="clear-search-btn" title="Clear search">×</button>
            </div>
          </div>
          <div class="controls-row">
            <div class="filter-buttons">
              <button class="filter-btn active" data-filter="owned">Own</button>
              <button class="filter-btn" data-filter="wanted">Want</button>
              <button class="filter-btn" data-filter="all">Total</button>
            </div>
            <button id="addAlbumBtn" class="add-btn">+ Add Album</button>
          </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loading" class="loading">
          <div class="spinner"></div>
        </div>

        <!-- Albums Table -->
        <div class="table-container">
          <table id="albumsTable" class="music-table">
            <thead>
              <tr>
                <th colspan="2" class="sortable-header" data-sort="album">Album <span class="sort-indicator"></span></th>
                <th class="sortable-header" data-sort="year">Year <span class="sort-indicator"></span></th>
                <th class="column-own">Own</th>
                <th class="column-want">Want</th>
                <th class="column-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Albums will be loaded here -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right Sidebar for Desktop Stats -->
      <div class="sidebar">
        <div class="sidebar-stats" id="sidebarStats">
          <div class="sidebar-stats-title"><h2>Collection Statistics</h2></div>
          <!-- Top 10 Years Bar Chart -->
          <div class="sidebar-stat-section">
            <h3>Top 10 Years</h3>
            <div class="bar-chart" id="sidebarYearChart">
              <!-- Bar chart will be populated here -->
            </div>
          </div>
          
          <!-- Top 10 Styles Pie Chart -->
          <div class="sidebar-stat-section">
            <h3>Top 10 Styles</h3>
            <div class="pie-chart" id="sidebarStyleChart">
              <!-- Pie chart will be populated here -->
            </div>
          </div>
          
          <!-- Top 10 Formats Pie Chart -->
          <div class="sidebar-stat-section">
            <h3>Top 10 Formats</h3>
            <div class="pie-chart" id="sidebarFormatChart">
              <!-- Pie chart will be populated here -->
            </div>
          </div>
          
          <!-- Top 10 Labels Pie Chart -->
          <div class="sidebar-stat-section">
            <h3>Top 10 Labels</h3>
            <div class="pie-chart" id="sidebarLabelChart">
              <!-- Pie chart will be populated here -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Album Modal -->
  <div id="albumModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Add New Album</h2>
      
      <form id="albumForm">
        <div class="form-group">
          <label for="artistName">Artist Name <span class="required" title="Required field">*</span></label>
          <div id="artistAutocomplete" class="autocomplete-container">
            <input type="text" id="artistName" name="artistName" required>
            <div class="autocomplete-list"></div>
          </div>
        </div>
        
        <div class="form-group">
          <label for="formatFilter">Format Filter <span class="required" title="Required field">*</span></label>
          <select id="formatFilter" name="formatFilter">
            <option value="">All Formats</option>
            <option value="Vinyl">Vinyl</option>
            <option value="CD">CD</option>
            <option value="Cassette">Cassette</option>
            <option value="Digital">Digital</option>
            <option value="7"">7"</option>
            <option value="10"">10"</option>
            <option value="12"">12"</option>
            <option value="LP">LP</option>
            <option value="EP">EP</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="albumName">Album Name <span class="required" title="Required field">*</span></label>
          <div id="albumAutocomplete" class="autocomplete-container">
            <input type="text" id="albumName" name="albumName" required>
            <div class="autocomplete-list"></div>
          </div>
        </div>
        
        <input type="hidden" id="releaseYear" name="releaseYear">
        <input type="hidden" id="albumFormat" name="albumFormat">
        <input type="hidden" id="label" name="label">
        <input type="hidden" id="producer" name="producer">
        
        <div class="form-group">
          <strong>
            Album Status <span class="required" title="Required field">*</span>
          </strong>
          <div class="radio-group">
            <label for="isOwned">
              <input type="radio" id="isOwned" name="albumStatus" value="owned">
              I own this album
            </label>
            <label for="wantToOwn">
              <input type="radio" id="wantToOwn" name="albumStatus" value="wanted">
              I want to own this album
            </label>
          </div>
        </div>
        
        <!-- Modal Error Message -->
        <div id="modalMessage" class="modal-message"></div>
        
        <div class="form-buttons">
          <button type="button" id="viewRecordBtn" class="btn-view-record" style="display: none;">View Record</button>
          <button type="button" id="cancelBtn" class="btn-cancel">Cancel</button>
          <button type="submit" class="btn-save">Save Album</button>
        </div>
      </form>
    </div>
  </div>

      <!-- Cover Art Modal -->
    <div id="coverModal" class="modal">
      <div class="modal-content cover-modal-content">
        <span class="close">&times;</span>
        <div class="cover-modal-body">
          <img id="coverModalImage" src="" alt="Album cover" class="cover-modal-image">
          <div id="coverModalInfo" class="cover-modal-info"></div>
        </div>
      </div>
    </div>

    <!-- Tracklist Modal -->
    <div id="tracklistModal" class="modal">
      <div class="modal-content tracklist-modal-content">
        <span class="close">&times;</span>
        <div class="tracklist-modal-header">
          <div class="tracklist-modal-header-content">
            <div class="tracklist-modal-cover">
              <img id="tracklistModalCover" src="" alt="Album cover" class="tracklist-cover-image" width="120" height="120">
              <div id="tracklistModalNoCover" class="tracklist-no-cover">Loading Cover...</div>
            </div>
            <div class="tracklist-modal-info-container">
              <h3 id="tracklistModalTitle"></h3>
              <div id="tracklistModalInfo"></div>
            </div>
          </div>
          <button id="tracklistEditBtn" class="btn btn-edit" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Edit" style="vertical-align: text-top;">
              <title>edit</title>
              <path d="M4 20h4l10.5-10.5a2 2 0 0 0 0-2.8l-1.2-1.2a2 2 0 0 0-2.8 0L4 16v4z" />
              <path d="M13.5 6.5l4 4" />
            </svg>
            Edit
          </button>
        </div>
        <div class="tracklist-modal-body">
          <div id="tracklistModalTracks"></div>
          <div class="tracklist-modal-actions">
            <div class="tracklist-modal-actions-left">
            <a id="tracklistModalShopLink" href="" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="display:none;">
              <svg xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                role="img"
                aria-label="Shopping cart">
                <title>Shopping cart</title>
                <g fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 3h2l2.6 11.59A2 2 0 0 0 9.52 17h7.96a2 2 0 0 0 1.94-1.41L21 7H6"/>
                  <path d="M16 21a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5z"/>
                  <path d="M8 21a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5z"/>
                </g>
              </svg>
              <span id="tracklistModalShopText">Shop on Discogs</span>
            </a>
            <a id="tracklistModalEbayLink" href="" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" role="img" aria-label="Search eBay">
                <title>Search eBay</title>
                <g fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="11" cy="11" r="7"></circle>
                  <path d="M20 20l-3.5-3.5"></path>
                </g>
              </svg>
              <span>Search on eBay</span>
            </a>
            </div>
            <a id="tracklistModalDiscogsLink" href="" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
              View Album on Discogs 
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="margin-left:.35rem;">
                <path d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3z"/>
                <path d="M5 5h5V3H3v7h2V5z"/>
                <path d="M5 19h14V10h2v11H3V10h2v9z"/>
              </svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
      <div class="modal-content">
        <span class="close">&times;</span>
        <h2>🔐 Authentication Required</h2>
        <p>Please enter the password to add or edit albums.</p>

        <form id="loginForm">
          <div class="form-group">
            <label for="password">Password:</label>
            <div class="password-input-wrapper">
              <input type="password" id="password" name="password" required>
              <button type="button" id="togglePassword" class="toggle-password-btn" title="Show/hide password">
                <?php echo $eye_icon; ?>
                <?php echo $eye_slash_icon; ?>
              </button>
            </div>
          </div>

          <div id="loginMessage" class="modal-message" style="display: none;"></div>

          <div class="form-buttons">
            <button type="submit" class="btn-save">Login</button>
            <button type="button" class="btn-cancel">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- View Record Modal -->
    <div id="viewRecordModal" class="modal">
      <div class="modal-content view-record-modal-content">
        <span class="close">&times;</span>
        <h2>Album Record Data</h2>
        <div class="view-record-body">
          <div class="record-controls">
            <button type="button" id="editRecordBtn" class="btn-edit-record">Edit JSON</button>
          </div>
          <div id="editWarning" class="edit-warning" style="display: none;">
            <p>You are editing raw JSON data. Make sure to maintain valid JSON format and required fields (artist_name, album_name). Invalid JSON will not save.</p>
            <h3>Note:</h3>
            <div>If artist is not sorting the way you'd expect, such as by last name, change the "artist_type" to "Person" rather than "Group".</div>
          </div>
          <div id="editError" class="edit-error" style="display: none;"></div>
          <pre id="recordData" class="record-data" contenteditable="false"></pre>
        </div>
        <div class="form-buttons">
          <button type="button" id="cancelEditBtn" class="btn-cancel" style="display: none;">Cancel Edit</button>
          <button type="button" id="viewRecordCloseBtn" class="btn-cancel">Close</button>
          <button type="button" id="saveRecordBtn" class="btn-save" style="display: none;">Save Changes</button>
        </div>
      </div>
    </div>

    <!-- Statistics Modal -->
    <div id="statsModal" class="modal">
      <div class="modal-content stats-modal-content">
        <span class="close">&times;</span>
        <h2>Collection Statistics</h2>
        
        <div class="stats-grid">
          <div class="style-stats-container">
            <h3>Top Music Styles</h3>
            <div id="styleStatsList" class="style-stats-list">
              <!-- Style statistics will be populated here -->
            </div>
          </div>
          
          <div class="year-stats-container">
            <h3>Top Years</h3>
            <div id="yearStatsList" class="year-stats-list">
              <!-- Year statistics will be populated here -->
            </div>
          </div>
          
          <div class="format-stats-container">
            <h3>Top Formats</h3>
            <div id="formatStatsList" class="format-stats-list">
              <!-- Format statistics will be populated here -->
            </div>
          </div>
          
          <div class="label-stats-container">
            <h3>Top Labels</h3>
            <div id="labelStatsList" class="label-stats-list">
              <!-- Label statistics will be populated here -->
            </div>
          </div>
        </div>
        <div class="form-buttons">
          <button type="button" class="btn-cancel">Close</button>
        </div>
      </div>
    </div>

    <?php echo renderResetPasswordModal(); ?>


  <!-- Load Chart.js conditionally only on desktop screens -->
  <script>
    // Only load Chart.js on desktop screens (charts don't display on mobile)
    if (window.innerWidth >= 769) {
      const script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js';
      script.async = true;
      document.head.appendChild(script);
    }
  </script>
  
  <script src="assets/js/app.min.js"></script>
  
  <?php 
  // Load demo.js only on demo sites
  $isDemoSite = strpos($_SERVER['HTTP_HOST'], 'railway.app') !== false || 
                strpos($_SERVER['HTTP_HOST'], 'herokuapp.com') !== false ||
                strpos($_SERVER['HTTP_HOST'], 'netlify.app') !== false ||
                strpos($_SERVER['HTTP_HOST'], 'vercel.app') !== false;
  
  if ($isDemoSite): ?>
  <script src="assets/js/demo.min.js"></script>
  <?php endif; ?>
  
  <?php if (!empty($errorMessage) && $_GET['error'] === 'setup_requires_auth'): ?>
  <script>
    // Auto-open login modal when authentication is required for setup
    document.addEventListener('DOMContentLoaded', function() {
      // Wait a moment for the app to initialize, then open the login modal
      setTimeout(function() {
        const loginModal = document.getElementById('loginModal');
        if (loginModal) {
          loginModal.style.display = 'block';
          document.body.classList.add('modal-open');
        }
      }, 500);
    });
  </script>
  <?php endif; ?>
  
  <footer class="site-footer">
    <div class="footer-content">
      <p>&copy; <?php echo date('Y'); ?> Design & Development by <a href="mailto:bkummerow@gmail.com">Bill Kummerow</a>.</p>
    </div>
  </footer>
  
  <!-- Back to Top Button -->
  <button id="backToTopBtn" class="back-to-top-btn" title="Back to Top" aria-label="Back to Top">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
      <path fill-rule="evenodd" d="M8 12a.5.5 0 0 0 .5-.5V5.707l2.146 2.147a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 0 0 .708.708L7.5 5.707V11.5a.5.5 0 0 0 .5.5z"/>
    </svg>
  </button>
</body>
</html>