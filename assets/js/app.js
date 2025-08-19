/**
 * Music Collection Application JavaScript
 * Handles all frontend functionality including CRUD operations and autocomplete
 */

class MusicCollectionApp {
  constructor() {
      this.currentFilter = 'all';
      this.currentSearch = '';
      this.editingAlbum = null;
      this.autocompleteTimeout = null;
      this.artistAutocompleteTimeout = null;
      this.albumAutocompleteTimeout = null;
      this.isAuthenticated = false;
      
      this.init();
  }
  
  // Helper function to ensure proper caching headers for all requests
  async fetchWithCache(url, options = {}) {
      const defaultOptions = {
          cache: 'default', // Use browser cache
          headers: {
              'Content-Type': 'application/json',
              ...options.headers
          }
      };
      
      return fetch(url, { ...defaultOptions, ...options });
  }
  
  init() {
      this.checkAuthStatus();
      this.loadStats();
      this.loadAlbums();
      this.bindEvents();
      this.setupAutocomplete();
  }
  
  async checkAuthStatus() {
      try {
          const response = await this.fetchWithCache('api/music_api.php?action=auth_check');
          const data = await response.json();
          
          if (data.success) {
              this.isAuthenticated = data.data.authenticated;
          }
      } catch (error) {
          // Auth status check failed silently, assume not authenticated
          this.isAuthenticated = false;
      }
      
      // Always update UI after checking auth status
      this.updateAuthUI();
  }
  
  updateAuthUI() {
      const addBtn = document.getElementById('addAlbumBtn');
      const loginBtn = document.getElementById('loginBtn');
      const logoutBtn = document.getElementById('logoutBtn');
      
      if (addBtn) {
          if (this.isAuthenticated) {
              addBtn.textContent = '+ Add Album';
              addBtn.style.opacity = '1';
              addBtn.style.cursor = 'pointer';
          } else {
              addBtn.textContent = '+ Add Album (Login Required)';
              addBtn.style.opacity = '0.7';
              addBtn.style.cursor = 'pointer';
          }
      }
      
      // Show login button when not authenticated, logout button when authenticated
      if (loginBtn) {
          loginBtn.style.display = this.isAuthenticated ? 'none' : 'flex';
      }
      
      if (logoutBtn) {
          logoutBtn.style.display = this.isAuthenticated ? 'flex' : 'none';
      }
  }
  
  bindEvents() {
      // Search functionality
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      
      searchInput.addEventListener('input', (e) => {
          this.currentSearch = e.target.value;
          this.debounceSearch();
          
          // Show/hide clear button based on input value
          if (e.target.value.length > 0) {
              clearSearchBtn.classList.add('visible');
          } else {
              clearSearchBtn.classList.remove('visible');
          }
      });
      
      // Clear search button functionality
      clearSearchBtn.addEventListener('click', () => {
          searchInput.value = '';
          this.currentSearch = '';
          this.debounceSearch();
          clearSearchBtn.classList.remove('visible');
          searchInput.focus();
      });
      
      // Password toggle functionality
      const togglePasswordBtn = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      const eyeIcon = togglePasswordBtn.querySelector('.eye-icon');
      const eyeSlashIcon = togglePasswordBtn.querySelector('.eye-slash-icon');
      
      togglePasswordBtn.addEventListener('click', () => {
          const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          passwordInput.setAttribute('type', type);
          
          // Toggle icon visibility
          if (type === 'text') {
              eyeIcon.style.display = 'none';
              eyeSlashIcon.style.display = 'block';
          } else {
              eyeIcon.style.display = 'block';
              eyeSlashIcon.style.display = 'none';
          }
      });
      
      // Reset Password modal password toggle functionality
      const resetPasswordFields = [
          { inputId: 'reset_current_password', buttonId: 'toggleResetCurrentPassword' },
          { inputId: 'reset_new_password', buttonId: 'toggleResetNewPassword' },
          { inputId: 'reset_confirm_password', buttonId: 'toggleResetConfirmPassword' }
      ];

      resetPasswordFields.forEach(field => {
          const toggleBtn = document.getElementById(field.buttonId);
          const passwordInput = document.getElementById(field.inputId);
          const eyeIcon = toggleBtn.querySelector('.eye-icon');
          const eyeSlashIcon = toggleBtn.querySelector('.eye-slash-icon');
          
          toggleBtn.addEventListener('click', () => {
              const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
              passwordInput.setAttribute('type', type);
              
              // Toggle icon visibility
              if (type === 'text') {
                  eyeIcon.style.display = 'none';
                  eyeSlashIcon.style.display = 'block';
              } else {
                  eyeIcon.style.display = 'block';
                  eyeSlashIcon.style.display = 'none';
              }
          });
      });
      
      // Filter buttons
      document.querySelectorAll('.filter-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
              this.setFilter(e.target.dataset.filter);
          });
      });
      
      // Add album button
      document.getElementById('addAlbumBtn').addEventListener('click', () => {
          this.showModal();
      });
      
      // Modal events
      document.getElementById('albumModal').addEventListener('click', (e) => {
          if (e.target.id === 'albumModal') {
              this.hideModal();
          }
      });
      
      // Cover modal events
      document.getElementById('coverModal').addEventListener('click', (e) => {
          if (e.target.id === 'coverModal') {
              this.hideCoverModal();
          }
      });
      
      // Tracklist modal events
      document.getElementById('tracklistModal').addEventListener('click', (e) => {
          if (e.target.id === 'tracklistModal') {
              this.hideTracklistModal();
          }
      });
      
                // Close button events for all modals
      document.querySelectorAll('.close').forEach(closeBtn => {
          closeBtn.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              // Determine which modal to close based on the close button's parent
              const modal = closeBtn.closest('.modal');
              if (modal) {
                  if (modal.id === 'albumModal') {
                      this.hideModal();
                  } else if (modal.id === 'coverModal') {
                      this.hideCoverModal();
                  } else if (modal.id === 'tracklistModal') {
                      this.hideTracklistModal();
                  } else if (modal.id === 'loginModal') {
                      this.hideLoginModal();
                  } else if (modal.id === 'statsModal') {
                      this.hideStatsModal();
                  } else if (modal.id === 'resetPasswordModal') {
                      this.hideResetPasswordModal();
                  } else if (modal.id === 'setupModal') {
                      this.hideSetupModal();
                  }
              }
          });
          
          // Add touch event support for mobile
          closeBtn.addEventListener('touchend', (e) => {
              e.preventDefault();
              e.stopPropagation();
              // Determine which modal to close based on the close button's parent
              const modal = closeBtn.closest('.modal');
              if (modal) {
                  if (modal.id === 'albumModal') {
                      this.hideModal();
                  } else if (modal.id === 'coverModal') {
                      this.hideCoverModal();
                  } else if (modal.id === 'tracklistModal') {
                      this.hideTracklistModal();
                  } else if (modal.id === 'loginModal') {
                      this.hideLoginModal();
                  } else if (modal.id === 'statsModal') {
                      this.hideStatsModal();
                  } else if (modal.id === 'resetPasswordModal') {
                      this.hideResetPasswordModal();
                  } else if (modal.id === 'setupModal') {
                      this.hideSetupModal();
                  }
              }
          });
      });
      
      // Login modal events
      document.getElementById('loginModal').addEventListener('click', (e) => {
          if (e.target.id === 'loginModal') {
              this.hideLoginModal();
          }
      });
      
      // Login form submission
      document.getElementById('loginForm').addEventListener('submit', (e) => {
          e.preventDefault();
          this.handleLogin(e);
      });
      
      // Login modal cancel button
      document.querySelector('#loginModal .btn-cancel').addEventListener('click', () => {
          this.hideLoginModal();
      });

      // Statistics modal events
      document.getElementById('statsModal').addEventListener('click', (e) => {
          if (e.target.id === 'statsModal') {
              this.hideStatsModal();
          }
      });

      // Statistics modal close button
      document.querySelector('#statsModal .btn-cancel').addEventListener('click', () => {
          this.hideStatsModal();
      });

      // Reset Password modal events
      document.getElementById('resetPasswordModal').addEventListener('click', (e) => {
          if (e.target.id === 'resetPasswordModal') {
              this.hideResetPasswordModal();
          }
      });

      // Reset Password form submission
      document.getElementById('resetPasswordForm').addEventListener('submit', (e) => {
          e.preventDefault();
          this.handleResetPassword(e);
      });

      // Reset Password modal cancel button
      document.querySelector('#resetPasswordModal .btn-cancel').addEventListener('click', () => {
          this.hideResetPasswordModal();
      });

      // Reset Password modal close button handling
      document.querySelector('#resetPasswordModal .close').addEventListener('click', () => {
          this.hideResetPasswordModal();
      });

      // Setup modal events
      document.getElementById('setupModal').addEventListener('click', (e) => {
          if (e.target.id === 'setupModal') {
              this.hideSetupModal();
          }
      });

      // Setup form submission
      document.getElementById('setupForm').addEventListener('submit', (e) => {
          e.preventDefault();
          this.handleSetupConfig(e);
      });

      // Setup modal cancel button
      document.querySelector('#setupModal .btn-cancel').addEventListener('click', () => {
          this.hideSetupModal();
      });

      // Setup modal close button handling
      document.querySelector('#setupModal .close').addEventListener('click', () => {
          this.hideSetupModal();
      });

      // Setup modal password button
      document.getElementById('setupPasswordBtn').addEventListener('click', () => {
          this.hideSetupModal();
          this.showResetPasswordModal();
      });
      
      // Event delegation for dynamically rendered album elements
      document.getElementById('albumsTable').addEventListener('click', (e) => {
          // Cover image clicks
          if (e.target.classList.contains('album-cover')) {
              const artist = e.target.dataset.artist;
              const album = e.target.dataset.album;
              const year = e.target.dataset.year;
              const cover = e.target.dataset.cover;
              this.showCoverModal(artist, album, year, cover);
          }
          
          // Album link clicks
          if (e.target.classList.contains('album-link')) {
              e.preventDefault();
              const artist = e.target.dataset.artist;
              const album = e.target.dataset.album;
              const year = e.target.dataset.year;
              const albumId = e.target.closest('tr').dataset.id;
              this.showTracklist(artist, album, year, albumId);
          }
          
          // Edit button clicks
          if (e.target.classList.contains('btn-edit')) {
              const id = e.target.dataset.id;
              if (id) {
                  this.editAlbum(parseInt(id));
              }
          }
          
          // Delete button clicks
          if (e.target.classList.contains('btn-delete')) {
              const id = e.target.dataset.id;
              if (id) {
                  this.deleteAlbum(parseInt(id));
              }
          }
      });
      
      // Form submission
      document.getElementById('albumForm').addEventListener('submit', (e) => {
          e.preventDefault();
          this.saveAlbum();
      });
      
      // Cancel button
      document.getElementById('cancelBtn').addEventListener('click', () => {
          this.hideModal();
      });
  }
  
  setupAutocomplete() {

      const artistInput = document.getElementById('artistName');
      const albumInput = document.getElementById('albumName');

      // Initially disable album input until artist is selected
      this.updateAlbumInputState();
      
      // Artist autocomplete with debouncing
      artistInput.addEventListener('input', (e) => {
          this.debounceArtistAutocomplete(e.target.value);
          this.updateAlbumInputState();
      });
      
      // Album autocomplete with debouncing (only when artist is selected)
      albumInput.addEventListener('input', (e) => {
          const artistValue = artistInput.value.trim();
          if (artistValue && artistValue.length > 0) {
              this.debounceAlbumAutocomplete(artistValue, e.target.value);
          } else {
              // Hide album autocomplete if no artist is selected
              this.hideAutocomplete('albumAutocomplete');
          }
      });
      
      // Clear album autocomplete when artist field is cleared
      artistInput.addEventListener('input', (e) => {
          if (!e.target.value.trim()) {
              this.hideAutocomplete('albumAutocomplete');
              albumInput.value = ''; // Clear album field when artist is cleared
              this.updateAlbumInputState();
          }
      });
      
      // Hide autocomplete on blur (with delay to allow for clicks)
      artistInput.addEventListener('blur', (e) => {
          setTimeout(() => {
              this.hideAutocomplete('artistAutocomplete');
          }, 500); // Increased from 200ms to 500ms
      });
      
      albumInput.addEventListener('blur', (e) => {
          setTimeout(() => {
              this.hideAutocomplete('albumAutocomplete');
          }, 500); // Increased from 200ms to 500ms
      });
      
      // Hide autocomplete when clicking outside
      document.addEventListener('click', (e) => {
          if (!e.target.closest('.autocomplete-container')) {
              this.hideAutocomplete('artistAutocomplete');
              this.hideAutocomplete('albumAutocomplete');
          }
      });
      
      // Dropdown handling
      this.setupDropdown();
  }
  
  setupDropdown() {
      const dropdown = document.querySelector('.dropdown');
      const dropdownMenu = document.querySelector('.dropdown-menu');
      
      if (dropdown && dropdownMenu) {
          // Prevent dropdown from closing when clicking inside it
          dropdownMenu.addEventListener('click', (e) => {
              e.stopPropagation();
          });
          
          // Close dropdown when clicking outside
          document.addEventListener('click', (e) => {
              if (!dropdown.contains(e.target)) {
                  dropdown.classList.remove('active');
              }
          });
          
          // Handle keyboard navigation
          dropdown.addEventListener('keydown', (e) => {
              if (e.key === 'Escape') {
                  dropdown.classList.remove('active');
                  dropdown.querySelector('.dropdown-toggle').blur();
              }
          });
      }
  }
  
  updateAlbumInputState() {
      const artistInput = document.getElementById('artistName');
      const albumInput = document.getElementById('albumName');
      
      if (artistInput && albumInput) {
          const artistValue = artistInput.value.trim();
          if (artistValue && artistValue.length > 0) {
              albumInput.disabled = false;
              albumInput.placeholder = 'Enter album name...';
          } else {
              albumInput.disabled = true;
              albumInput.placeholder = 'Select an artist first...';
          }
      }
  }
  
  debounceArtistAutocomplete(search) {
      clearTimeout(this.artistAutocompleteTimeout);
      this.artistAutocompleteTimeout = setTimeout(() => {
          this.handleArtistAutocomplete(search);
      }, 300);
  }
  
  debounceAlbumAutocomplete(artist, search) {
      clearTimeout(this.albumAutocompleteTimeout);
      this.albumAutocompleteTimeout = setTimeout(() => {
          this.handleAlbumAutocomplete(artist, search);
      }, 300);
  }
  
  async handleArtistAutocomplete(search) {
      if (search.length < 2) {
          this.hideAutocomplete('artistAutocomplete');
          return;
      }
      
      // Show loading state
      this.showAutocompleteLoading('artistAutocomplete');
      
      try {
          const url = `api/music_api.php?action=artists&search=${encodeURIComponent(search)}`;
          
          const response = await this.fetchWithCache(url);
          
          const data = await response.json();
          
          if (data.success) {
              this.showAutocomplete('artistAutocomplete', data.data, 'artist_name');
          } else {
              this.hideAutocomplete('artistAutocomplete');
          }
      } catch (error) {
          this.hideAutocomplete('artistAutocomplete');
      }
  }
  
  async handleAlbumAutocomplete(artist, search) {
      if (search.length < 2) {
          this.hideAutocomplete('albumAutocomplete');
          return;
      }
      
      // Show loading state
      this.showAutocompleteLoading('albumAutocomplete');
      
      try {
          const url = `api/music_api.php?action=albums_by_artist&artist=${encodeURIComponent(artist)}&search=${encodeURIComponent(search)}`;
          
          const response = await this.fetchWithCache(url);
          
          const data = await response.json();
          
          if (data.success) {
              this.showAutocomplete('albumAutocomplete', data.data, 'album_name');
          } else {
              this.hideAutocomplete('albumAutocomplete');
          }
      } catch (error) {
          this.hideAutocomplete('albumAutocomplete');
      }
  }
  
  showAutocomplete(containerId, items, field) {
      const container = document.getElementById(containerId);
      let list = container.querySelector('.autocomplete-list');
      
      // If not found in original container, check body
      if (!list) {
          list = document.querySelector(`[data-original-container="${containerId}"]`);
      }
      
      if (!list) {
          return;
      }
      
      // Clear existing items
      list.innerHTML = '';
      
      // Add new items
      items.forEach(item => {
          if (item && item[field] && item[field] !== 'undefined') {
              const div = document.createElement('div');
              div.className = 'autocomplete-item';
              
              // Create text span
              const textSpan = document.createElement('span');
              if (field === 'album_name' && item.year) {
                  textSpan.textContent = `${item[field]} (${item.year})`;
              } else {
                  textSpan.textContent = item[field];
              }
              div.appendChild(textSpan);
              
              // Add cover art if available
              if (item.cover_url) {
                  const coverImg = document.createElement('img');
                  coverImg.src = item.cover_url;
                  coverImg.className = 'autocomplete-cover';
                  coverImg.alt = 'Album cover';
                  coverImg.onerror = () => {
                      // Image failed to load (expected for Discogs on localhost), remove it gracefully
                      coverImg.remove();
                  };
                  coverImg.onload = () => {
                      // Cover art loaded successfully
                  };
                  div.appendChild(coverImg);
              }
              
              div.addEventListener('click', (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  this.selectAutocompleteItem(containerId, item[field], item);
              });
              list.appendChild(div);
          }
      });
      
      // Only show the list if we have valid items
      if (list.children.length > 0) {
          // Force visibility and proper styling
          list.style.display = 'block';
          list.style.visibility = 'visible';
          list.style.opacity = '1';
          list.style.zIndex = '10000';
          list.style.position = 'absolute';
          list.style.backgroundColor = 'white';
          list.style.border = '1px solid #ddd';
          list.style.borderRadius = '4px';
          list.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
          
          // Check if we're in a modal and adjust positioning
          this.adjustAutocompletePosition(container, list);
          
          // Double-check positioning after a brief delay to ensure proper rendering
          setTimeout(() => {
              this.adjustAutocompletePosition(container, list);
          }, 10);
      } else {
          list.style.display = 'none';
      }
  }
  
  showAutocompleteLoading(containerId) {
      const container = document.getElementById(containerId);
      let list = container.querySelector('.autocomplete-list');
      
      // If the list was moved to body, find it there instead
      if (!list) {
          list = document.querySelector(`[data-original-container="${containerId}"]`);
      }
      
      if (list) {
          list.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
          list.style.display = 'block';
      }
  }
  
  hideAutocomplete(containerId) {
      const container = document.getElementById(containerId);
      let list = container.querySelector('.autocomplete-list');
      
      // If the list was moved to body, find it there instead
      if (!list) {
          list = document.querySelector(`[data-original-container="${containerId}"]`);
      }
      
      if (list) {
          // Force hide the autocomplete list
          list.style.display = 'none';
          list.style.visibility = 'hidden';
          list.style.opacity = '0';
          
          // If the list was moved to body, move it back to the original container
          if (list.parentNode === document.body && list.dataset.originalContainer) {
              const originalContainer = document.getElementById(list.dataset.originalContainer);
              if (originalContainer) {
                  originalContainer.appendChild(list);
                  // Reset positioning
                  list.style.position = '';
                  list.style.top = '';
                  list.style.left = '';
                  list.style.width = '';
                  list.style.zIndex = '';
                  delete list.dataset.originalContainer;
              }
          }
      }
  }
  
  selectAutocompleteItem(containerId, value, item = null) {
      const container = document.getElementById(containerId);
      const input = container.querySelector('input');
      
      if (input) {
          // For albums, use just the album name (without year) for the input value
          if (containerId === 'albumAutocomplete') {
              input.value = value; // This is just the album name
          } else {
              input.value = value;
          }
          
          // Store additional data if available
          if (item) {
              if (containerId === 'artistAutocomplete') {
                  this.selectedArtist = item;
              } else if (containerId === 'albumAutocomplete') {
                  this.selectedAlbum = item;
                  this.selectedDiscogsReleaseId = item.id || null;
                  this.selectedCoverUrl = item.cover_url || null;
                  
                  // Populate release year if available
                  if (item.year) {
                      const yearInput = document.getElementById('releaseYear');
                      if (yearInput) {
                          yearInput.value = item.year;
                      }
                  }
              }
          }
      }
      
      this.hideAutocomplete(containerId);
  }
  
  debounceSearch() {
      clearTimeout(this.autocompleteTimeout);
      this.autocompleteTimeout = setTimeout(() => {
          this.loadAlbums();
      }, 300);
  }
  
  setFilter(filter) {
      this.currentFilter = filter;
      
      // Update active button
      document.querySelectorAll('.filter-btn').forEach(btn => {
          btn.classList.remove('active');
      });
      document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
      
      this.loadAlbums();
  }
  
  async loadStats() {
      try {
          const response = await this.fetchWithCache('api/music_api.php?action=stats');
          const data = await response.json();
          
          if (data.success) {
              this.updateStats(data.data);
          }
      } catch (error) {
          // Stats loading failed silently
      }
  }
  
  updateStats(stats) {
      // Update modal stats (these are the ones that will be used now)
      document.getElementById('modalTotalAlbums').textContent = stats.total_albums || 0;
      document.getElementById('modalOwnedAlbums').textContent = stats.owned_count || 0;
      document.getElementById('modalWantedAlbums').textContent = stats.wanted_count || 0;
      document.getElementById('modalUniqueArtists').textContent = stats.unique_artists || 0;
  }
  
  async loadAlbums() {
      this.showLoading();
      
      // Add loading class to table container to prevent layout shifts
      const tableContainer = document.querySelector('.table-container');
      if (tableContainer) {
          tableContainer.classList.add('loading');
      }
      
      try {
          const params = new URLSearchParams({
              action: 'albums',
              filter: this.currentFilter,
              search: this.currentSearch
          });
          
          const response = await this.fetchWithCache(`api/music_api.php?${params}`);
          const data = await response.json();
          
          if (data.success) {
              this.renderAlbums(data.data);
          } else {
              this.showMessage('Error loading albums: ' + data.message, 'error');
          }
      } catch (error) {
          this.showMessage('Error loading albums', 'error');
      } finally {
          this.hideLoading();
          // Remove loading class from table container
          if (tableContainer) {
              tableContainer.classList.remove('loading');
          }
      }
  }
  
  renderAlbums(albums) {
      const tbody = document.querySelector('#albumsTable tbody');
      
      if (albums.length === 0) {
          tbody.innerHTML = `
              <tr>
                  <td colspan="5" class="empty-state">
                      <h3>No albums found</h3>
                      <p>Try adjusting your search or filter criteria</p>
                  </td>
              </tr>
          `;
          return;
      }
      
      tbody.innerHTML = albums.map(album => `
          <tr data-id="${album.id}">
              <td class="cover-cell">
                  ${album.cover_url ? 
                      `<img data-src="${album.cover_url}" data-medium="${album.cover_url_medium || album.cover_url}" data-large="${album.cover_url_large || album.cover_url}" class="album-cover lazy" alt="Album cover" data-artist="${this.escapeHtml(album.artist_name)}" data-album="${this.escapeHtml(album.album_name)}" data-year="${album.release_year || ''}" data-cover="${album.cover_url_large || album.cover_url}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" onload="this.classList.add('loaded')">
                       <div class="no-cover" style="display: none;">No Cover</div>` : 
                      '<div class="no-cover">No Cover</div>'
                  }
                  <div class="mobile-status">
                      ${album.is_owned ? '<span class="status-owned">Own</span>' : ''}
                      ${album.want_to_own ? '<span class="status-wanted">Want</span>' : ''}
                      ${!album.is_owned && !album.want_to_own ? '<span class="status-none">-</span>' : ''}
                  </div>
              </td>
              <td>
                  <div class="album-info">
                      <div class="artist-name">${this.escapeHtml(album.artist_name)}</div>
                      <div class="album-name">
                          <a href="#" class="album-link" data-artist="${this.escapeHtml(album.artist_name)}" data-album="${this.escapeHtml(album.album_name)}" data-year="${album.release_year || ''}">
                              ${this.escapeHtml(album.album_name)}
                          </a>
                      </div>
                      <div class="mobile-year">${album.release_year ? `<span class="year-badge">${album.release_year}</span>` : '<span class="year-badge">-</span>'}</div>
                      <div class="mobile-actions">
                          <button class="btn-edit" data-id="${album.id}">Edit</button>
                          <button class="btn-delete" data-id="${album.id}">Delete</button>
                      </div>
                  </div>
              </td>
              <td>
                  ${album.release_year ? `<span class="year-badge">${album.release_year}</span>` : '<span class="year-badge">-</span>'}
              </td>
              <td>
                  ${album.is_owned ? '<span class="checkmark">✓</span>' : '<span class="checkmark">&nbsp;</span>'}
              </td>
              <td>
                  ${album.want_to_own ? '<span class="checkmark">✓</span>' : '<span class="checkmark">&nbsp;</span>'}
              </td>
              <td>
                  <div class="action-buttons">
                      <button class="btn-edit" data-id="${album.id}">Edit</button>
                      <button class="btn-delete" data-id="${album.id}">Delete</button>
                  </div>
              </td>
          </tr>
      `).join('');
      
      // Initialize lazy loading after rendering
      this.initLazyLoading();
  }
  
  async editAlbum(id) {
      try {
          const response = await this.fetchWithCache(`api/music_api.php?action=album&id=${id}`);
          const data = await response.json();
          
          if (data.success) {
              this.editingAlbum = data.data;
              this.showModal(data.data);
          } else {
              this.showMessage('Error loading album: ' + data.message, 'error');
          }
      } catch (error) {
          this.showMessage('Error loading album', 'error');
      }
  }
  
  async deleteAlbum(id) {
      if (!this.isAuthenticated) {
          this.showLoginModal();
          return;
      }
      
      if (!confirm('Are you sure you want to delete this album?')) {
          return;
      }
      
      try {
          const response = await fetch('api/music_api.php?action=delete', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({ id: id })
          });
          
          const data = await response.json();
          
          if (data.success) {
              this.showMessage('Album deleted successfully', 'success');
              this.loadAlbums();
              this.loadStats();
          } else {
              if (data.auth_required) {
                  this.showLoginModal();
              } else {
                  this.showMessage('Error deleting album: ' + data.message, 'error');
              }
          }
      } catch (error) {
          this.showMessage('Error deleting album', 'error');
      }
  }
  
  showModal(album = null) {
      const modal = document.getElementById('albumModal');
      const form = document.getElementById('albumForm');
      const title = document.querySelector('#albumModal h2');
      
      // Reset form
      form.reset();
      
      // Clear any previous modal messages
      this.hideModalMessage();
      
      if (album) {
          title.textContent = 'Edit Album';
          document.getElementById('artistName').value = album.artist_name;
          document.getElementById('albumName').value = album.album_name;
          document.getElementById('releaseYear').value = album.release_year || '';
          
          // Preserve existing cover art and Discogs data when editing
          this.selectedCoverUrl = album.cover_url || null;
          this.selectedDiscogsReleaseId = album.discogs_release_id || null;
          
          // Set radio button based on album status
          if (album.is_owned == 1) {
              document.getElementById('isOwned').checked = true;
          } else if (album.want_to_own == 1) {
              document.getElementById('wantToOwn').checked = true;
          }
          
          // Enable album input for editing
          const albumInput = document.getElementById('albumName');
          albumInput.disabled = false;
          albumInput.placeholder = 'Enter album name...';
      } else {
          title.textContent = 'Add New Album';
          this.editingAlbum = null;
          
          // Clear cover art data for new albums
          this.selectedCoverUrl = null;
          this.selectedDiscogsReleaseId = null;
          
          // Update album input state for new album
          this.updateAlbumInputState();
      }
      
      modal.style.display = 'block';
      
      // Focus on the artist input field
      document.getElementById('artistName').focus();
  }
  
  hideModal() {
      document.getElementById('albumModal').style.display = 'none';
      this.editingAlbum = null;
      this.selectedCoverUrl = null;
      this.selectedDiscogsReleaseId = null;
      this.hideModalMessage();
  }
  
  hideModalMessage() {
      const messageEl = document.getElementById('modalMessage');
      messageEl.style.display = 'none';
      messageEl.textContent = '';
      messageEl.className = 'modal-message';
  }
  
  async saveAlbum() {
      if (!this.isAuthenticated) {
          this.showLoginModal();
          return;
      }
      
      const form = document.getElementById('albumForm');
      const formData = new FormData(form);
      
      // Get the selected radio button value
      const albumStatus = formData.get('albumStatus');
      
      if (!albumStatus) {
          this.showModalMessage('Please select either "I own this album" or "I want to own this album"', 'error');
          return;
      }
      
      const albumData = {
          artist_name: formData.get('artistName'),
          album_name: formData.get('albumName'),
          release_year: formData.get('releaseYear'),
          is_owned: albumStatus === 'owned',
          want_to_own: albumStatus === 'wanted',
          cover_url: this.selectedCoverUrl || null,
          discogs_release_id: this.selectedDiscogsReleaseId || null
      };
      
      const action = this.editingAlbum ? 'update' : 'add';
      if (this.editingAlbum) {
          albumData.id = this.editingAlbum.id;
      }
      

      
      try {
          const response = await fetch(`api/music_api.php?action=${action}`, {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(albumData)
          });
          
          // Check if response is ok
          if (!response.ok) {
              throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          
          // Check if response has content
          const responseText = await response.text();
          
          if (!responseText.trim()) {
              throw new Error('Empty response from server');
          }
          
          // Try to parse JSON
          let data;
          try {
              data = JSON.parse(responseText);
          } catch (jsonError) {
              throw new Error(`Invalid JSON response: ${jsonError.message}`);
          }
          
          if (data.success) {
              this.hideModal();
              this.showMessage(data.message, 'success');
              this.loadAlbums();
              this.loadStats();
              this.editingAlbum = null;
          } else {
              if (data.auth_required) {
                  this.showLoginModal();
              } else {
                  // Show the specific API error message
                  this.showModalMessage(data.message || 'Unknown error occurred', 'error');
              }
          }
      } catch (error) {
          this.showModalMessage('Network error: ' + error.message, 'error');
      }
  }
  
  showLoading() {
      document.getElementById('loading').style.display = 'block';
  }
  
  hideLoading() {
      document.getElementById('loading').style.display = 'none';
  }
  
  showModalMessage(message, type) {
      const messageEl = document.getElementById('modalMessage');
      messageEl.textContent = message;
      messageEl.className = `modal-message ${type}`;
      messageEl.style.display = 'block';
      
      setTimeout(() => {
          messageEl.style.display = 'none';
      }, 5000);
  }
  
  showMessage(message, type) {
      const messageEl = document.getElementById('message');
      messageEl.textContent = message;
      messageEl.className = `message ${type}`;
      messageEl.style.display = 'block';
      
      setTimeout(() => {
          messageEl.style.display = 'none';
      }, 5000);
  }

  showCoverModal(artistName, albumName, releaseYear, coverUrl) {
      const modal = document.getElementById('coverModal');
      const image = document.getElementById('coverModalImage');
      const info = document.getElementById('coverModalInfo');
      
      image.src = coverUrl;
      image.alt = `${albumName} by ${artistName}`;
      
      info.innerHTML = `
          <div class="artist-name">${this.escapeHtml(artistName)}</div>
          <div class="album-name">${this.escapeHtml(albumName)}</div>
          ${releaseYear ? `<div class="album-year">${releaseYear}</div>` : ''}
      `;
      
      modal.style.display = 'block';
  }

  hideCoverModal() {
      document.getElementById('coverModal').style.display = 'none';
  }
  
  async showTracklist(artistName, albumName, releaseYear, albumId = null) {
      const modal = document.getElementById('tracklistModal');
      const title = document.getElementById('tracklistModalTitle');
      const info = document.getElementById('tracklistModalInfo');
      const tracks = document.getElementById('tracklistModalTracks');
      const discogsLink = document.getElementById('tracklistModalDiscogsLink');
      const coverImage = document.getElementById('tracklistModalCover');
      const noCover = document.getElementById('tracklistModalNoCover');
      
      // Set modal title and info
      title.textContent = `${albumName}`;
      info.innerHTML = `
          <div><strong>Artist:</strong> ${artistName}</div>
          ${releaseYear ? `<div><strong>Year:</strong> ${releaseYear}</div>` : ''}
      `;
      
      // Hide cover art initially - don't show loading state until we know if it's cached
      coverImage.style.display = 'none';
      noCover.style.display = 'none';
      noCover.textContent = ''; // Clear any existing text
      
      // Try to find already-loaded image from the table first
      let existingImage = null;
      if (albumId) {
          // Look for the table row with this album ID
          const tableRow = document.querySelector(`tr[data-id="${albumId}"]`);
          if (tableRow) {
              const tableImage = tableRow.querySelector('.album-cover');
              if (tableImage && tableImage.src && tableImage.src !== window.location.href) {
                  // Found an already-loaded image in the table
                  existingImage = tableImage.src;
                  console.log('Found existing image in table:', existingImage);
              }
          }
      }
      
      // If we found an existing image, use it immediately
      if (existingImage) {
          console.log('Using existing table image for instant loading');
          coverImage.src = existingImage;
          coverImage.style.display = 'block';
          noCover.style.display = 'none';
          coverImage.classList.add('loaded');
      }
      
      // Show loading state
      tracks.innerHTML = '<div class="tracklist-loading">Loading tracklist...</div>';
      modal.style.display = 'block';
      
      // Note: The tracklist API now handles cover art prioritization automatically
      // It will return existing cover art from our collection if available, otherwise Discogs API
      
      try {
          // Fetch tracklist from API with album ID for exact release matching
          const params = new URLSearchParams({
              artist: artistName,
              album: albumName
          });
          
          if (releaseYear) {
              params.append('year', releaseYear);
          }
          
          if (albumId) {
              params.append('album_id', albumId);
          }
          
          const response = await this.fetchWithCache(`api/tracklist_api.php?${params}`);
          const data = await response.json();
          
          if (data.success && data.data) {
              const albumData = data.data;
              
              // Format release date if available - hide if only year is known (Dec 31)
              let formattedReleased = '';
              if (albumData.released) {
                  try {
                      // Handle dates with day "00" (like 1979-10-00) by replacing with "01" for parsing
                      let dateString = albumData.released;
                      let hasDay00 = false;
                      if (dateString.match(/^\d{4}-\d{2}-00$/)) {
                          hasDay00 = true;
                          dateString = dateString.replace('-00', '-01');
                      }
                      
                      // Parse the date components to avoid timezone issues
                      const dateParts = dateString.split('-');
                      const year = parseInt(dateParts[0]);
                      const month = parseInt(dateParts[1]) - 1; // JavaScript months are 0-indexed
                      const day = parseInt(dateParts[2]);
                      
                      const date = new Date(year, month, day);
                      if (!isNaN(date.getTime())) {
                          const month = date.getMonth(); // 11 = December
                          const day = date.getDate();
                          
                          // Check if original date had day "00" or if it's December 31st (which indicates only year is known)
                          if (hasDay00 || (month === 11 && day === 31)) {
                              // Show only month and year for dates with day "00" or December 31st
                              formattedReleased = date.toLocaleDateString('en-US', {
                                  month: 'short',
                                  year: 'numeric'
                              });
                          } else {
                              // Show full date for complete dates
                              formattedReleased = date.toLocaleDateString('en-US', {
                                  month: 'short',
                                  day: 'numeric',
                                  year: 'numeric'
                              });
                          }
                      } else {
                          formattedReleased = albumData.released;
                      }
                  } catch (e) {
                      formattedReleased = albumData.released;
                  }
              }

              // Create reviews count display - make it a link if there are reviews with content
              let reviewsDisplay = '';
              if (albumData.rating_count) {
                  if (albumData.has_reviews_with_content) {
                      reviewsDisplay = `<div class="rating-count">(based on <a href="${albumData.discogs_url}#release-reviews" target="_blank" rel="noopener noreferrer" style="padding-left: .25em;">${albumData.rating_count} reviews</a>)</div>`;
                  } else {
                      reviewsDisplay = `<div class="rating-count">(based on ${albumData.rating_count} reviews)</div>`;
                  }
              }

              // Helper function to remove trailing numbers in parentheses
              const removeTrailingNumbers = (text) => {
                  if (!text) return text;
                  return text.replace(/\s*\(\d+\)\s*$/, '');
              };

              // Update info with additional details
              info.innerHTML = `
                  <div><strong>Artist:</strong> <span>${removeTrailingNumbers(albumData.artist)}</span></div>
                  ${albumData.year ? `<div><strong>Year:</strong> <span>${albumData.year}</span></div>` : ''}
                  ${albumData.label ? `<div><strong>Label:</strong> <span>${removeTrailingNumbers(albumData.label)}</span></div>` : ''}
                  ${formattedReleased && !/^\d{4}$/.test(formattedReleased) ? `<div><strong>Released:</strong> <span>${formattedReleased}</span></div>` : ''}
                  ${albumData.format ? `<div><strong>Format:</strong> <span>${albumData.format}</span></div>` : ''}
                  ${albumData.producer ? `<div><strong>Producer:</strong> <span>${removeTrailingNumbers(albumData.producer)}</span></div>` : ''}
                  ${albumData.rating ? `<div class="rating-container"><strong>Rating:</strong> <span class="rating-value">${albumData.rating}</span>${this.generateStarRating(albumData.rating)}${reviewsDisplay}</div>` : ''}
              `;
              
              // Display cover art from tracklist API response (only if we didn't find one in the table)
              if (!existingImage && albumData.cover_url) {
                  const coverUrl = albumData.cover_url_medium || albumData.cover_url;
                  console.log('Using cover URL from tracklist API:', coverUrl);
                  
                  // Check if this is a cached image (image proxy URL)
                  const isCachedImage = coverUrl.includes('api/image_proxy.php');
                  
                  if (isCachedImage) {
                      // For cached images, don't show loading state - image should load instantly
                      console.log('Cached image detected, skipping loading state');
                      coverImage.style.display = 'none';
                      noCover.style.display = 'none';
                      noCover.textContent = ''; // Clear any existing text
                  } else {
                      // For non-cached images, show loading state
                      console.log('Non-cached image, showing loading state');
                      noCover.textContent = 'Loading Cover...';
                      noCover.style.display = 'flex';
                      coverImage.style.display = 'none';
                  }
                  
                  // Set image source
                  coverImage.src = coverUrl;
                  
                  // Add a timeout to handle slow loading
                  const imageTimeout = setTimeout(() => {
                      console.log('Image loading timeout');
                      if (coverImage.style.display === 'none') {
                          coverImage.style.display = 'none';
                          noCover.textContent = 'No Cover';
                          noCover.style.display = 'flex';
                      }
                  }, 10000); // 10 second timeout
                  
                  // Handle image load success
                  coverImage.onload = function() {
                      console.log('Cover image loaded successfully');
                      clearTimeout(imageTimeout);
                      coverImage.style.display = 'block';
                      noCover.style.display = 'none';
                      coverImage.classList.add('loaded');
                  };
                  
                  // Handle image load errors
                  coverImage.onerror = function() {
                      console.log('Cover image failed to load');
                      clearTimeout(imageTimeout);
                      coverImage.style.display = 'none';
                      noCover.textContent = 'No Cover';
                      noCover.style.display = 'flex';
                  };
              } else if (!existingImage && !albumData.cover_url) {
                  // No cover art available and no existing image found
                  console.log('No cover art available');
                  coverImage.style.display = 'none';
                  noCover.textContent = 'No Cover';
                  noCover.style.display = 'flex';
              }
              
              // Set Discogs link
              discogsLink.href = albumData.discogs_url;
              
              // Add debugging information if available
              if (albumData.matched_reason) {
                  const matchInfo = document.createElement('div');
                  matchInfo.className = 'tracklist-match-info';
                  matchInfo.innerHTML = `<small>Matched: ${albumData.matched_reason}</small>`;
                  tracks.appendChild(matchInfo);
              }
              
              // Display tracklist
              if (albumData.tracklist && albumData.tracklist.length > 0) {
                  tracks.innerHTML = `
                      <div class="tracklist-modal-tracks">
                          ${albumData.tracklist.map(track => `
                              <div class="track-item">
                                  <span class="track-position">${track.position}</span>
                                  <span class="track-title">${this.escapeHtml(track.title)}</span>
                                  <span class="track-duration">${track.duration || ''}</span>
                              </div>
                          `).join('')}
                      </div>
                  `;
              } else {
                  tracks.innerHTML = '<div class="tracklist-error">No tracklist available for this album</div>';
              }
          } else {
              tracks.innerHTML = `<div class="tracklist-error">${data.message || 'Could not load tracklist'}</div>`;
              discogsLink.href = `https://www.discogs.com/search/?q=${encodeURIComponent(artistName + ' ' + albumName)}&type=release`;
          }
      } catch (error) {
          tracks.innerHTML = '<div class="tracklist-error">Error loading tracklist</div>';
          discogsLink.href = `https://www.discogs.com/search/?q=${encodeURIComponent(artistName + ' ' + albumName)}&type=release`;
      }
  }
  
  hideTracklistModal() {
      document.getElementById('tracklistModal').style.display = 'none';
  }
  
  showLoginModal() {
      document.getElementById('loginModal').style.display = 'block';
      document.getElementById('password').focus();
      document.getElementById('loginMessage').style.display = 'none';
  }

  showStatsModal() {
      // Load fresh stats before showing the modal
      this.loadStats();
      document.getElementById('statsModal').style.display = 'block';
  }

  hideStatsModal() {
      document.getElementById('statsModal').style.display = 'none';
  }
  
  showResetPasswordModal() {
      // Check if user is authenticated first
      if (!this.isAuthenticated) {
          this.showLoginModal();
          return;
      }
      
      // Check password status
      this.checkPasswordStatus();
      
      // Clear any previous messages
      document.getElementById('resetPasswordMessage').style.display = 'none';
      
      // Clear form fields
      document.getElementById('resetPasswordForm').reset();
      
      // Show the modal
      document.getElementById('resetPasswordModal').style.display = 'block';
      
      // Focus on first field
      document.getElementById('reset_current_password').focus();
  }
  
  hideResetPasswordModal() {
      document.getElementById('resetPasswordModal').style.display = 'none';
      document.getElementById('resetPasswordForm').reset();
      document.getElementById('resetPasswordMessage').style.display = 'none';
  }
  
  async checkPasswordStatus() {
      try {
          const response = await fetch('api/music_api.php?action=auth_check');
          const data = await response.json();
          
          if (data.success) {
              // For now, we'll assume password is set if we can check auth status
              // In a real implementation, you might want a separate endpoint to check password status
              const statusText = document.getElementById('passwordStatusText');
              const statusDiv = document.getElementById('passwordStatus');
              
              if (data.data.authenticated) {
                  statusText.textContent = 'Set';
                  statusDiv.className = 'password-status set';
              } else {
                  statusText.textContent = 'Not set';
                  statusDiv.className = 'password-status not-set';
              }
          }
      } catch (error) {
          console.error('Error checking password status:', error);
      }
  }
  
  async handleResetPassword(event) {
      event.preventDefault();
      
      const currentPassword = document.getElementById('reset_current_password').value;
      const newPassword = document.getElementById('reset_new_password').value;
      const confirmPassword = document.getElementById('reset_confirm_password').value;
      const messageDiv = document.getElementById('resetPasswordMessage');
      
      try {
          const response = await fetch('api/music_api.php?action=reset_password', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                  current_password: currentPassword,
                  new_password: newPassword,
                  confirm_password: confirmPassword
              })
          });
          
          const data = await response.json();
          
          if (data.success) {
              messageDiv.textContent = data.message;
              messageDiv.className = 'modal-message success';
              messageDiv.style.display = 'block';
              
              // Clear form on success
              document.getElementById('resetPasswordForm').reset();
              
              // Update password status
              this.checkPasswordStatus();
              
              // Auto-hide modal after 3 seconds
              setTimeout(() => {
                  this.hideResetPasswordModal();
              }, 3000);
          } else {
              messageDiv.textContent = data.message;
              messageDiv.className = 'modal-message error';
              messageDiv.style.display = 'block';
          }
      } catch (error) {
          messageDiv.textContent = 'Network error. Please try again.';
          messageDiv.className = 'modal-message error';
          messageDiv.style.display = 'block';
      }
  }
  
  showSetupModal() {
      // Check if user is authenticated first
      if (!this.isAuthenticated) {
          this.showLoginModal();
          return;
      }
      
      // Load setup status
      this.loadSetupStatus();
      
      // Clear any previous messages
      document.getElementById('setupMessage').style.display = 'none';
      
      // Clear form fields
      document.getElementById('setupForm').reset();
      
      // Show the modal
      document.getElementById('setupModal').style.display = 'block';
      
      // Focus on first field
      document.getElementById('setup_discogs_api_key').focus();
  }
  
  hideSetupModal() {
      document.getElementById('setupModal').style.display = 'none';
      document.getElementById('setupForm').reset();
      document.getElementById('setupMessage').style.display = 'none';
  }
  
  async loadSetupStatus() {
      try {
          const response = await fetch('api/music_api.php?action=get_setup_status');
          const data = await response.json();
          
          if (data.success) {
              const statusData = data.data;
              
              // Update API key status
              const apiKeyStatus = document.getElementById('apiKeyStatus');
              const currentApiKeyDisplay = document.getElementById('currentApiKeyDisplay');
              const currentApiKeyText = document.getElementById('currentApiKeyText');
              
              if (statusData.api_key_set) {
                  apiKeyStatus.textContent = '✅ Set';
                  apiKeyStatus.className = 'status-value set';
                  currentApiKeyDisplay.style.display = 'block';
                  currentApiKeyText.textContent = statusData.current_api_key;
              } else {
                  apiKeyStatus.textContent = '❌ Not set';
                  apiKeyStatus.className = 'status-value not-set';
                  currentApiKeyDisplay.style.display = 'none';
              }
              
              // Update password status
              const passwordStatus = document.getElementById('passwordStatus');
              const passwordActionText = document.getElementById('passwordActionText');
              
              if (statusData.password_set) {
                  passwordStatus.textContent = '✅ Set';
                  passwordStatus.className = 'status-value set';
                  passwordActionText.textContent = 'Change Password';
              } else {
                  passwordStatus.textContent = '❌ Not set';
                  passwordStatus.className = 'status-value not-set';
                  passwordActionText.textContent = 'Set Password';
              }
              
              // Update overall status
              const overallStatus = document.getElementById('overallStatus');
              if (statusData.setup_complete) {
                  overallStatus.textContent = '✅ Complete';
                  overallStatus.className = 'status-value set';
              } else {
                  overallStatus.textContent = '⚠️ Incomplete';
                  overallStatus.className = 'status-value not-set';
              }
          }
      } catch (error) {
          console.error('Error loading setup status:', error);
      }
  }
  
  async handleSetupConfig(event) {
      event.preventDefault();
      
      const discogsApiKey = document.getElementById('setup_discogs_api_key').value;
      const messageDiv = document.getElementById('setupMessage');
      
      try {
          const response = await fetch('api/music_api.php?action=setup_config', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                  discogs_api_key: discogsApiKey
              })
          });
          
          const data = await response.json();
          
          if (data.success) {
              messageDiv.textContent = data.message;
              messageDiv.className = 'modal-message success';
              messageDiv.style.display = 'block';
              
              // Clear form on success
              document.getElementById('setupForm').reset();
              
              // Reload setup status
              this.loadSetupStatus();
              
              // Auto-hide modal after 3 seconds
              setTimeout(() => {
                  this.hideSetupModal();
              }, 3000);
          } else {
              messageDiv.textContent = data.message;
              messageDiv.className = 'modal-message error';
              messageDiv.style.display = 'block';
          }
      } catch (error) {
          messageDiv.textContent = 'Network error. Please try again.';
          messageDiv.className = 'modal-message error';
          messageDiv.style.display = 'block';
      }
  }
  
  hideLoginModal() {
      document.getElementById('loginModal').style.display = 'none';
      document.getElementById('password').value = '';
  }
  
  async handleLogin(event) {
      event.preventDefault();
      
      const password = document.getElementById('password').value;
      const messageDiv = document.getElementById('loginMessage');
      
      try {
          const response = await fetch('api/music_api.php?action=login', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({ password: password })
          });
          
          const data = await response.json();
          
          if (data.success) {
              this.isAuthenticated = true;
              this.updateAuthUI();
              this.hideLoginModal();
              this.showMessage('Login successful', 'success');
          } else {
              messageDiv.textContent = data.message;
              messageDiv.className = 'modal-message error';
              messageDiv.style.display = 'block';
          }
      } catch (error) {
          messageDiv.textContent = 'Login failed. Please try again.';
          messageDiv.className = 'modal-message error';
          messageDiv.style.display = 'block';
      }
  }
  
  async handleLogout() {
      try {
          const response = await fetch('api/music_api.php?action=logout', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              }
          });
          
          const data = await response.json();
          
          if (data.success) {
              this.isAuthenticated = false;
              this.updateAuthUI();
              this.showMessage('Logged out successfully', 'success');
          }
      } catch (error) {
          // Logout error handled silently
      }
  }
  
  initLazyLoading() {
      // Use Intersection Observer for modern browsers
      if ('IntersectionObserver' in window) {
          const imageObserver = new IntersectionObserver((entries, observer) => {
              entries.forEach(entry => {
                  if (entry.isIntersecting) {
                      const img = entry.target;
                      // Use optimized image loading
                      this.loadOptimizedImage(img, img.dataset.src);
                      img.classList.remove('lazy');
                      img.classList.add('loaded');
                      observer.unobserve(img);
                  }
              });
          }, {
              rootMargin: '50px 0px', // Start loading 50px before image comes into view
              threshold: 0.01
          });
          
          // Observe all lazy images
          document.querySelectorAll('img.lazy').forEach(img => {
              imageObserver.observe(img);
          });
      } else {
          // Fallback for older browsers
          this.loadLazyImagesFallback();
      }
  }
  
  loadLazyImagesFallback() {
      const lazyImages = document.querySelectorAll('img.lazy');
      
      const loadImage = (img) => {
          if (img.dataset.src) {
              // Use optimized image loading
              this.loadOptimizedImage(img, img.dataset.src);
              img.classList.remove('lazy');
          }
      };
      
      // Load images that are in viewport
      lazyImages.forEach(img => {
          if (this.isElementInViewport(img)) {
              loadImage(img);
          }
      });
      
      // Add scroll listener for remaining images
      const scrollHandler = () => {
          lazyImages.forEach(img => {
              if (img.classList.contains('lazy') && this.isElementInViewport(img)) {
                  loadImage(img);
              }
          });
      };
      
      window.addEventListener('scroll', scrollHandler);
      window.addEventListener('resize', scrollHandler);
  }
  
  isElementInViewport(el) {
      const rect = el.getBoundingClientRect();
      return (
          rect.top >= 0 &&
          rect.left >= 0 &&
          rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
          rect.right <= (window.innerWidth || document.documentElement.clientWidth)
      );
  }
  
  escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
  }
  
  formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString();
  }

  // Helper function to load optimized images with fallback
  loadOptimizedImage(img, src, fallbackSrc = null) {
      // Check if WebP is supported
      const webpSupported = this.isWebPSupported();
      
      if (webpSupported && src.includes('discogs.com') && !src.includes('fm=webp')) {
          // Add WebP format to Discogs URLs for better compression
          const webpSrc = src.includes('?') ? src + '&fm=webp' : src + '?fm=webp';
          img.src = webpSrc;
          
          // Fallback to original if WebP fails (or if Discogs blocks the request)
          img.onerror = () => {
              img.src = src;
              
              // If the original also fails (expected on localhost), try fallback
              if (fallbackSrc) {
                  img.onerror = () => {
                      img.src = fallbackSrc;
                  };
              }
          };
      } else {
          img.src = src;
          
          // Handle final fallback
          if (fallbackSrc) {
              img.onerror = () => {
                  img.src = fallbackSrc;
              };
          }
      }
  }
  
  // Check if WebP is supported
  isWebPSupported() {
      const canvas = document.createElement('canvas');
      canvas.width = 1;
      canvas.height = 1;
      return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
  }
  
  // Generate star rating HTML with quarter, half, and three-quarter stars
  generateStarRating(rating) {
      const fullStars = Math.floor(rating);
      const decimal = rating % 1;
      let fractionalStar = '';
      
      // Determine fractional star type
      if (decimal >= 0.875) {
          fractionalStar = 'filled'; // Round up to full star
      } else if (decimal >= 0.625) {
          fractionalStar = 'three-quarter';
      } else if (decimal >= 0.375) {
          fractionalStar = 'half';
      } else if (decimal >= 0.125) {
          fractionalStar = 'quarter';
      }
      
      const totalStars = fullStars + (fractionalStar ? 1 : 0);
      const emptyStars = 5 - totalStars;
      
      let starsHTML = '<span class="stars">';
      
      // Add full stars
      for (let i = 0; i < fullStars; i++) {
          starsHTML += '<span class="star filled">★</span>';
      }
      
      // Add fractional star if needed
      if (fractionalStar && fractionalStar !== 'filled') {
          starsHTML += `<span class="star ${fractionalStar}">★</span>`;
      } else if (fractionalStar === 'filled') {
          starsHTML += '<span class="star filled">★</span>';
      }
      
      // Add empty stars
      for (let i = 0; i < emptyStars; i++) {
          starsHTML += '<span class="star empty">☆</span>';
      }
      
      starsHTML += '</span>';
      return starsHTML;
  }

  // Adjust autocomplete position to stay within modal bounds
  adjustAutocompletePosition(container, list) {
      // Check if the container is within a modal
      const modal = container.closest('.modal');
      if (!modal) return;
      
      const modalContent = modal.querySelector('.modal-content');
      const containerRect = container.getBoundingClientRect();
      const modalRect = modalContent.getBoundingClientRect();
      
      // Reset any previous positioning
      list.style.top = '100%';
      list.style.bottom = 'auto';
      list.style.maxHeight = '150px';
      
      // Calculate available space below the input
      const spaceBelow = modalRect.bottom - containerRect.bottom - 20; // Account for padding
      const spaceAbove = containerRect.top - modalRect.top - 20; // Account for padding
      
      // Calculate the actual height the list would need
      const listHeight = Math.min(list.scrollHeight, 150);
      
      // If there's not enough space below, try positioning above
      if (spaceBelow < listHeight && spaceAbove > listHeight) {
          list.style.top = 'auto';
          list.style.bottom = '100%';
          list.style.maxHeight = `${Math.min(spaceAbove, 150)}px`;
      } else if (spaceBelow < listHeight) {
          // If neither above nor below works, limit the height to available space
          list.style.maxHeight = `${Math.max(spaceBelow, 100)}px`; // Minimum 100px
      } else {
          // Position below the input with available space
          list.style.maxHeight = `${Math.min(spaceBelow, 150)}px`;
      }
      
      // Ensure the list doesn't extend horizontally beyond the modal
      const listWidth = list.offsetWidth;
      const containerWidth = container.offsetWidth;
      
      if (listWidth > containerWidth) {
          list.style.width = `${containerWidth}px`;
      }
  }

  // Move autocomplete to body to avoid stacking context issues
  moveAutocompleteToBody(container, list) {
      // Check if we're in a modal
      const modal = container.closest('.modal');
      if (!modal) return;

      // Move the list to body to avoid stacking context issues
      if (list.parentNode !== document.body) {
          document.body.appendChild(list);
          
          // Position the list relative to the container
          const containerRect = container.getBoundingClientRect();
          list.style.position = 'fixed';
          list.style.top = (containerRect.bottom + window.scrollY) + 'px';
          list.style.left = containerRect.left + 'px';
          list.style.width = containerRect.width + 'px';
          list.style.zIndex = '10000';
          list.style.backgroundColor = 'white';
          list.style.border = '1px solid #ddd';
          list.style.borderRadius = '4px';
          list.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
          list.style.display = 'block'; // Ensure it's visible
          list.style.visibility = 'visible';
          list.style.opacity = '1';
          
          // Store reference to original container
          list.dataset.originalContainer = container.id;
      }
  }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.app = new MusicCollectionApp();
}); 