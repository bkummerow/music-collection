<?php
/**
 * Music Collection Manager
 * Main application page with authentication
 */

// Set proper caching headers to allow back/forward cache - do this before session_start()
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));

// Include authentication (this will handle session management)
require_once __DIR__ . '/config/auth_config.php';

// Ensure session is started with proper configuration
ensureSessionStarted();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Personal Music Collection  - Track your vinyl music collection with album covers, tracklists, and Discogs integration. Organize your music library by artist, album, release year, and ownership status.">
    <meta name="keywords" content="music collection, vinyl records, album database, Discogs, music library, album covers, tracklists">
    <meta name="author" content="Music Collection App">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Personal Music Collection">
    <meta property="og:description" content="Track your vinyl music collection with album covers, tracklists, and Discogs integration.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="">
    <meta property="og:image" content="">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Personal Music Collection">
    <meta name="twitter:description" content="Track your vinyl music collection with album covers, tracklists, and Discogs integration.">
    <title>Music Collection</title>
    <link rel="stylesheet" href="assets/css/style.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Music Collection</h1>
            <div class="auth-controls">
                <div class="dropdown">
                    <button class="btn-settings dropdown-toggle" title="Settings Menu" aria-label="Settings Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu">
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
                        <button id="statsBtn" class="dropdown-item stats-item" onclick="app.showStatsModal()">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M1 15h2v-6H1v6zm3.5 0h2v-8h-2v8zm3.5 0h2V5h-2v10zm3.5 0h2V2h-2v13zm3.5 0h2V7h-2v8z"/>
                          </svg>
                            Collection Statistics
                        </button>
                        <button id="setupBtn" class="dropdown-item" onclick="app.showSetupModal()">
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



        <!-- Message Display -->
        <div id="message" class="message"></div>

        <!-- Controls -->
        <div class="controls">
            <div class="controls-row">
                <div class="search-box">
                  <label for="searchInput" class="sr-only">
                      <span>Search albums or artists</span>
                  </label>
                  <div class="search-input-wrapper">
                      <input type="text" id="searchInput" placeholder="Search albums or artists...">
                      <button type="button" id="clearSearch" class="clear-search-btn" title="Clear search">×</button>
                  </div>
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All Albums</button>
                    <button class="filter-btn" data-filter="owned">Own</button>
                    <button class="filter-btn" data-filter="wanted">Want to Own</button>
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
                        <th colspan="2">Album</th>
                        <th>Year</th>
                        <th>Own</th>
                        <th>Want</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Albums will be loaded here -->
                </tbody>
            </table>
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
                    <label for="albumName">Album Name <span class="required" title="Required field">*</span></label>
                    <div id="albumAutocomplete" class="autocomplete-container">
                        <input type="text" id="albumName" name="albumName" required>
                        <div class="autocomplete-list"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="releaseYear">Release Year</label>
                    <input type="number" id="releaseYear" name="releaseYear" min="1900" max="2030">
                </div>
                
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
                            <img id="tracklistModalCover" src="" alt="Album cover" class="tracklist-cover-image">
                            <div id="tracklistModalNoCover" class="tracklist-no-cover">Loading Cover...</div>
                        </div>
                        <div class="tracklist-modal-info-container">
                            <h3 id="tracklistModalTitle"></h3>
                            <div id="tracklistModalInfo"></div>
                        </div>
                    </div>
                </div>
                <div class="tracklist-modal-body">
                    <div id="tracklistModalTracks"></div>
                    <div class="tracklist-modal-actions">
                        <a id="tracklistModalDiscogsLink" href="" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                          View on Discogs 
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                                <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                </svg>
                                <svg class="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="display: none;">
                                    <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                                    <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                                    <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                                </svg>
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

        <!-- Statistics Modal -->
        <div id="statsModal" class="modal">
            <div class="modal-content stats-modal-content">
                <span class="close">&times;</span>
                <h2>Collection Statistics</h2>
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-number" id="modalTotalAlbums">0</div>
                        <div class="stat-label">Total Albums</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="modalOwnedAlbums">0</div>
                        <div class="stat-label">Albums Owned</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="modalWantedAlbums">0</div>
                        <div class="stat-label">Want to Own</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="modalUniqueArtists">0</div>
                        <div class="stat-label">Unique Artists</div>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="button" class="btn-cancel">Close</button>
                </div>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div id="resetPasswordModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Reset Admin Password</h2>
                
                <div class="password-status" id="resetPasswordStatus">
                    <strong>Password Status:</strong> 
                    <span id="passwordStatusText">Checking...</span>
                </div>
                
                <div class="warning">
                    <h3>⚠️ Security Warning</h3>
                    <p>This will change the admin password for your Music Collection app.</p>
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
                                    <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
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
                                    <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
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
                                    <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
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

        <!-- Setup Modal -->
        <div id="setupModal" class="modal">
            <div class="modal-content setup-modal-content">
                <span class="close">&times;</span>
                <h2>Setup & Configuration</h2>
                <p>Configure your Discogs API key and set up authentication</p>
                
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
                    <div class="status-item">
                        <span class="status-label">Password:</span>
                        <span class="status-value" id="passwordStatus">Checking...</span>
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
                    
                    <div id="setupMessage" class="modal-message" style="display: none;"></div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-save">Save Configuration</button>
                    </div>
                </form>
                
                <div class="setup-auth-section">
                    <h3>Authentication Setup</h3>
                    <p>
                        Set up a password to protect your music collection. This password will be required to add, edit, or delete albums.
                    </p>
                    
                    <div class="setup-auth-actions">
                        <button type="button" id="setupPasswordBtn" class="btn-secondary">
                            <span id="passwordActionText">Set Password</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <script src="assets/js/app.min.js"></script>
</body>
</html> 