<?php
/**
 * Music Collection Manager
 * Main application page with authentication
 */

// Start session for authentication
session_start();

// Set proper caching headers to allow back/forward cache
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));

// Include authentication
require_once __DIR__ . '/config/auth_config.php';
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
                <button id="logoutBtn" onclick="app.handleLogout()" class="btn-logout" style="display: none;">Logout</button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number" id="totalAlbums">0</div>
                <div class="stat-label">Total Albums</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="ownedAlbums">0</div>
                <div class="stat-label">Albums Owned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="wantedAlbums">0</div>
                <div class="stat-label">Want to Own</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="uniqueArtists">0</div>
                <div class="stat-label">Unique Artists</div>
            </div>
        </div>

        <!-- Message Display -->
        <div id="message" class="message"></div>

        <!-- Controls -->
        <div class="controls">
            <div class="controls-row">
                <div class="search-box">
                  <label for="searchInput" class="sr-only">
                      Search albums or artists
                      <input type="text" id="searchInput" placeholder="Search albums or artists...">
                  </label>
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
                            <div id="tracklistModalNoCover" class="tracklist-no-cover">No Cover</div>
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
                        <a id="tracklistModalDiscogsLink" href="" target="discogs" class="btn btn-primary">
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
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div id="loginMessage" class="modal-message" style="display: none;"></div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn-save">Login</button>
                        <button type="button" class="btn-cancel">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

    <script src="assets/js/app.min.js"></script>
</body>
</html> 