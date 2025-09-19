/**
 * Music Collection Application JavaScript
 * Handles all frontend functionality including CRUD operations and autocomplete
 */

class MusicCollectionApp {
      constructor() {
        this.currentFilter = 'owned';
        this.currentSearch = '';
        this.currentStyleFilter = '';
        this.currentFormatFilter = '';
        this.currentYearFilter = '';
        this.currentArtistFilter = '';
        this.currentLabelFilter = '';
        this.currentProducerFilter = '';
        this.consolidatedFormatTypes = null; // For handling consolidated format filtering
        this.editingAlbum = null;
        this.autocompleteTimeout = null;
        this.artistAutocompleteTimeout = null;
        this.albumAutocompleteTimeout = null;
        this.isAuthenticated = false;
        this.currentSort = { field: 'artist', direction: 'asc' }; // Default sort: artist ascending
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
  
  async init() {
      this.checkAuthStatus();
      this.loadStats();
      this.loadAlbums();
      this.bindEvents();
      this.setupAutocomplete();
      await this.loadThemeColors(); // Load saved theme colors on page load
      
      // Load display mode after a short delay to ensure DOM is ready
      setTimeout(async () => {
          await this.loadDisplayMode(); // Load saved display mode preference
          await this.loadStatsSettings(); // Load saved stats settings
          await this.loadSettings(); // Load saved album display settings
      }, 100);
      
      // Start notification polling
      this.startNotificationPolling();
      
      // Initialize demo manager if on demo site
      this.initDemoManager();
      
      // Initialize sort indicators
      this.updateSortIndicators();
      
      // Initialize back to top button
      this.initBackToTop();
      
      // Check if we should show a cache clear message (from URL params)
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('cache_cleared') === 'true') {
          this.showMessage('All caches cleared successfully. Fresh data loaded.', 'success');
          // Remove both cache-related parameters from URL without reloading
          urlParams.delete('cache_cleared');
          urlParams.delete('_cache_clear');
          const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
          window.history.replaceState({}, '', newUrl);
      }
      
      // Check if we should show login modal (from URL params)
      if (urlParams.get('login') === 'required') {
          this.showMessage('Please log in to access this feature.', 'info');
          setTimeout(() => {
              this.showLoginModal();
          }, 1000);
          // Remove login parameter from URL without reloading
          urlParams.delete('login');
          const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
          window.history.replaceState({}, '', newUrl);
      }
  }
  
  async checkAuthStatus() {
      try {
          // Don't cache authentication status - always check fresh
          const response = await fetch('api/music_api.php?action=auth_status', {
              cache: 'no-cache',
              headers: {
                  'Content-Type': 'application/json'
              }
          });
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
      const setupConfigBtn = document.getElementById('setupConfigBtn');
      
      // Handle add button
      if (addBtn) {
          if (this.isAuthenticated) {
              addBtn.textContent = '+ Add Album';
              addBtn.style.display = 'block';
              addBtn.style.opacity = '1';
              addBtn.style.cursor = 'pointer';
          } else {
              addBtn.style.display = 'none';
          }
      }
      
      // Show login button when not authenticated, logout button when authenticated
      if (loginBtn) {
          loginBtn.style.display = this.isAuthenticated ? 'none' : 'flex';
      }
      
      if (logoutBtn) {
          logoutBtn.style.display = this.isAuthenticated ? 'flex' : 'none';
      }
      
      // Show setup & configuration button only when authenticated
      if (setupConfigBtn) {
          setupConfigBtn.style.display = this.isAuthenticated ? 'flex' : 'none';
      }
      
          // Hide/show edit and delete buttons based on authentication
    const editButtons = document.querySelectorAll('.btn-edit');
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    editButtons.forEach(btn => {
        btn.style.display = this.isAuthenticated ? 'inline-block' : 'none';
    });
    
    deleteButtons.forEach(btn => {
        btn.style.display = this.isAuthenticated ? 'inline-block' : 'none';
    });
    
    // Hide/show Actions column header and action cells based on authentication
    const actionsHeader = document.querySelector('th:last-child');
    const actionCells = document.querySelectorAll('td:last-child');
    
    if (actionsHeader) {
        actionsHeader.style.display = this.isAuthenticated ? 'table-cell' : 'none';
    }
    
    actionCells.forEach(cell => {
        cell.style.display = this.isAuthenticated ? 'table-cell' : 'none';
    });
    
    // Hide/show Clear Caches button based on authentication
    const clearCacheBtn = document.getElementById('clearCacheBtn');
    if (clearCacheBtn) {
        clearCacheBtn.style.display = this.isAuthenticated ? 'flex' : 'none';
    }
    
    // Hide/show Setup & Configuration button based on authentication
    const setupBtn = document.getElementById('setupBtn');
    if (setupBtn) {
        setupBtn.style.display = this.isAuthenticated ? 'flex' : 'none';
    }
    
    // Hide/show Reset Password button based on authentication
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');
    if (resetPasswordBtn) {
        resetPasswordBtn.style.display = this.isAuthenticated ? 'flex' : 'none';
    }
  }
  
  bindEvents() {
      // Search functionality
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      const searchSubmitBtn = document.getElementById('searchSubmit');
      
      if (searchInput) {
          searchInput.addEventListener('input', (e) => {
          this.currentSearch = e.target.value;
          this.debounceSearch();
          
          // Show/hide clear button based on input value
          if (clearSearchBtn) {
              if (e.target.value.length > 0) {
                  clearSearchBtn.classList.add('visible');
              } else {
                  clearSearchBtn.classList.remove('visible');
              }
          }
          
          // Show style suggestions if user types "style:"
          this.handleStyleSearchSuggestions(e.target.value);
          });
      }
      
      // Search submit button functionality
      if (searchSubmitBtn) {
          searchSubmitBtn.addEventListener('click', () => {
              this.currentSearch = searchInput.value;
              this.debounceSearch();
          });
      }
      
      // Clear search button functionality
      if (clearSearchBtn) {
          clearSearchBtn.addEventListener('click', () => {
          searchInput.value = '';
          this.currentSearch = '';
          this.currentStyleFilter = ''; // Clear style filter when clearing search
          this.currentFormatFilter = ''; // Clear format filter when clearing search
          this.consolidatedFormatTypes = null; // Clear consolidated format types
          this.currentYearFilter = ''; // Clear year filter when clearing search
          this.currentArtistFilter = ''; // Clear artist filter when clearing search
          this.currentLabelFilter = ''; // Clear label filter when clearing search
          this.currentProducerFilter = ''; // Clear producer filter when clearing search
          searchInput.disabled = false; // Re-enable search input
          this.debounceSearch();
          clearSearchBtn.classList.remove('visible');
          searchInput.focus();
          
          // Clear any info messages
          const messageEl = document.getElementById('message');
          if (messageEl && messageEl.classList.contains('info')) {
              messageEl.classList.remove('show');
          }
          
          // Refresh stats to update filter buttons with overall collection totals
          this.loadStats();
          });
      }
      
      // Password toggle functionality
      const togglePasswordBtn = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      
      if (togglePasswordBtn && passwordInput) {
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
      }
      
      // Reset Password modal password toggle functionality
      const resetPasswordFields = [
          { inputId: 'reset_current_password', buttonId: 'toggleResetCurrentPassword' },
          { inputId: 'reset_new_password', buttonId: 'toggleResetNewPassword' },
          { inputId: 'reset_confirm_password', buttonId: 'toggleResetConfirmPassword' }
      ];

      resetPasswordFields.forEach(field => {
          const toggleBtn = document.getElementById(field.buttonId);
          const passwordInput = document.getElementById(field.inputId);
          
          if (toggleBtn && passwordInput) {
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
          }
      });
      
      // Filter buttons
      document.querySelectorAll('.filter-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
              this.setFilter(e.target.dataset.filter);
          });
      });
      
      // Add album button
      const addAlbumBtn = document.getElementById('addAlbumBtn');
      if (addAlbumBtn) {
          addAlbumBtn.addEventListener('click', () => {
              this.showModal();
          });
      }
      
      // View record button
      const viewRecordBtn = document.getElementById('viewRecordBtn');
      if (viewRecordBtn) {
          viewRecordBtn.addEventListener('click', () => {
              this.showViewRecordModal();
          });
      }
      
      // View record modal close button
      const viewRecordModalClose = document.querySelector('#viewRecordModal .close');
      if (viewRecordModalClose) {
          viewRecordModalClose.addEventListener('click', () => {
              this.hideViewRecordModal();
          });
      }
      
      // View record modal cancel button
      const viewRecordModalCancel = document.querySelector('#viewRecordModal .btn-cancel');
      if (viewRecordModalCancel) {
          viewRecordModalCancel.addEventListener('click', () => {
              this.hideViewRecordModal();
          });
      }
      
      // View record modal close button (bottom)
      const viewRecordCloseBtn = document.getElementById('viewRecordCloseBtn');
      if (viewRecordCloseBtn) {
          viewRecordCloseBtn.addEventListener('click', () => {
              this.hideViewRecordModal();
          });
      }
      
      // Edit record button
      const editRecordBtn = document.getElementById('editRecordBtn');
      if (editRecordBtn) {
          editRecordBtn.addEventListener('click', () => {
              this.enableRecordEditing();
          });
      }
      
      // Save record button
      const saveRecordBtn = document.getElementById('saveRecordBtn');
      if (saveRecordBtn) {
          saveRecordBtn.addEventListener('click', () => {
              this.saveRecordChanges();
          });
      }
      
      // Cancel edit button
      const cancelEditBtn = document.getElementById('cancelEditBtn');
      if (cancelEditBtn) {
          cancelEditBtn.addEventListener('click', () => {
              this.cancelRecordEditing();
          });
      }
      
      // Clear cache button
      const clearCacheBtn = document.getElementById('clearCacheBtn');
      if (clearCacheBtn) {
          clearCacheBtn.addEventListener('click', () => {
              this.clearAllCaches();
          });
      }
      
      // Modal events
      const albumModal = document.getElementById('albumModal');
      if (albumModal) {
          albumModal.addEventListener('click', (e) => {
              if (e.target.id === 'albumModal') {
                  this.hideModal();
              }
          });
      }
      
      // Cover modal events
      const coverModal = document.getElementById('coverModal');
      if (coverModal) {
          coverModal.addEventListener('click', (e) => {
              if (e.target.id === 'coverModal') {
                  this.hideCoverModal();
              }
          });
      }
      
      // Tracklist modal events
      const tracklistModal = document.getElementById('tracklistModal');
      if (tracklistModal) {
          tracklistModal.addEventListener('click', (e) => {
              if (e.target.id === 'tracklistModal') {
                  this.hideTracklistModal();
              }
          });
      }
      
      // Tracklist modal cover image click event
      const tracklistModalCover = document.getElementById('tracklistModalCover');
      if (tracklistModalCover) {
          tracklistModalCover.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          
          // Get the current album data from the tracklist modal
          const modal = document.getElementById('tracklistModal');
          const coverImage = document.getElementById('tracklistModalCover');
          
          if (modal && coverImage && coverImage.src && coverImage.src !== window.location.href) {
              // Get album data from the modal's dataset (this contains the original values)
              const artist = modal.dataset.artistName || '';
              const album = modal.dataset.albumName || '';
              const year = modal.dataset.releaseYear || '';
              const coverUrl = coverImage.src;
              const albumId = modal.dataset.albumId || null;
              
              // Close tracklist modal and open cover modal
              this.hideTracklistModal();
              this.showCoverModal(artist, album, year, coverUrl, albumId);
          }
          });
      }
      
      // Tracklist edit button event
      const tracklistEditBtn = document.getElementById('tracklistEditBtn');
      if (tracklistEditBtn) {
          tracklistEditBtn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.handleTracklistEdit();
          });
      }
      
      // Lyrics dropdown functionality - use event delegation
      document.addEventListener('click', (e) => {
          // Handle lyrics button clicks
          if (e.target.classList.contains('lyrics-btn')) {
              e.preventDefault();
              e.stopPropagation();
              
              const dropdown = e.target.closest('.lyrics-dropdown');
              const menu = dropdown.querySelector('.lyrics-menu');
              
              // Close all other lyrics menus
              document.querySelectorAll('.lyrics-menu.show').forEach(openMenu => {
                  if (openMenu !== menu) {
                      openMenu.classList.remove('show');
                  }
              });
              
              // Toggle current menu
              menu.classList.toggle('show');
          }
          // Handle clicks outside lyrics dropdowns to close them
          else if (!e.target.closest('.lyrics-dropdown')) {
              document.querySelectorAll('.lyrics-menu.show').forEach(menu => {
                  menu.classList.remove('show');
              });
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
                  } else if (modal.id === 'viewRecordModal') {
                      this.hideViewRecordModal();
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
                  } else if (modal.id === 'viewRecordModal') {
                      this.hideViewRecordModal();
                  }
              }
          });
      });
      
      // Login modal events
      const loginModal = document.getElementById('loginModal');
      if (loginModal) {
          loginModal.addEventListener('click', (e) => {
              if (e.target.id === 'loginModal') {
                  this.hideLoginModal();
              }
          });
      }
      
      // Login form submission
      const loginForm = document.getElementById('loginForm');
      if (loginForm) {
          loginForm.addEventListener('submit', (e) => {
              e.preventDefault();
              this.handleLogin(e);
          });
      }
      
      // Login modal cancel button
      const loginModalCancel = document.querySelector('#loginModal .btn-cancel');
      if (loginModalCancel) {
          loginModalCancel.addEventListener('click', () => {
              this.hideLoginModal();
          });
      }

      // Statistics modal events
      const statsModal = document.getElementById('statsModal');
      if (statsModal) {
          statsModal.addEventListener('click', (e) => {
              if (e.target.id === 'statsModal') {
                  this.hideStatsModal();
              }
          });
      }

      // Statistics modal close button
      const statsModalCancel = document.querySelector('#statsModal .btn-cancel');
      if (statsModalCancel) {
          statsModalCancel.addEventListener('click', () => {
              this.hideStatsModal();
          });
      }

      // Reset Password modal events
      const resetPasswordModal = document.getElementById('resetPasswordModal');
      if (resetPasswordModal) {
          resetPasswordModal.addEventListener('click', (e) => {
              if (e.target.id === 'resetPasswordModal') {
                  this.hideResetPasswordModal();
              }
          });
      }

      // Reset Password form submission
      const resetPasswordForm = document.getElementById('resetPasswordForm');
      if (resetPasswordForm) {
          resetPasswordForm.addEventListener('submit', (e) => {
              e.preventDefault();
              this.handleResetPassword(e);
          });
      }

      // Reset Password modal cancel button
      const resetPasswordModalCancel = document.querySelector('#resetPasswordModal .btn-cancel');
      if (resetPasswordModalCancel) {
          resetPasswordModalCancel.addEventListener('click', () => {
              this.hideResetPasswordModal();
          });
      }

      // Reset Password modal close button handling
      const resetPasswordModalClose = document.querySelector('#resetPasswordModal .close');
      if (resetPasswordModalClose) {
          resetPasswordModalClose.addEventListener('click', () => {
              this.hideResetPasswordModal();
          });
      }

      // Setup modal events
      const setupModal = document.getElementById('setupModal');
      if (setupModal) {
          setupModal.addEventListener('click', (e) => {
              if (e.target.id === 'setupModal') {
                  this.hideSetupModal();
              }
          });
      }

      // Setup form submission
      const setupForm = document.getElementById('setupForm');
      if (setupForm) {
          setupForm.addEventListener('submit', (e) => {
              e.preventDefault();
              this.handleSetupConfig(e);
          });
      }

      // Setup modal cancel button
      const setupModalCancel = document.querySelector('#setupModal .btn-cancel');
      if (setupModalCancel) {
          setupModalCancel.addEventListener('click', () => {
              this.hideSetupModal();
          });
      }

      // Setup modal close button handling
      const setupModalClose = document.querySelector('#setupModal .close');
      if (setupModalClose) {
          setupModalClose.addEventListener('click', () => {
              this.hideSetupModal();
          });
      }

      // Setup modal password button
      const setupPasswordBtn = document.getElementById('setupPasswordBtn');
      if (setupPasswordBtn) {
          setupPasswordBtn.addEventListener('click', () => {
              this.hideSetupModal();
              this.showResetPasswordModal();
          });
      }
      
      // Event delegation for dynamically rendered album elements
      const albumsTable = document.getElementById('albumsTable');
      if (albumsTable) {
          albumsTable.addEventListener('click', (e) => {
          // Cover image clicks
          if (e.target.classList.contains('album-cover')) {
              const artist = e.target.dataset.artist;
              const album = e.target.dataset.album;
              const year = e.target.dataset.year;
              const cover = e.target.dataset.cover;
              const albumId = e.target.closest('tr').dataset.id;
              this.showCoverModal(artist, album, year, cover, albumId);
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
          
          // Artist link clicks
          if (e.target.classList.contains('artist-link')) {
              e.preventDefault();
              const artist = e.target.dataset.artist;
              if (artist) {
                  this.filterByArtist(artist);
              }
          }
          
          // Year link clicks
          if (e.target.classList.contains('year-link') || e.target.closest('.year-link')) {
              e.preventDefault();
              const yearLink = e.target.classList.contains('year-link') ? e.target : e.target.closest('.year-link');
              const year = yearLink.dataset.year;
              if (year) {
                  this.filterByYear(year);
              }
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
      }
      
      // Form submission
      const albumForm = document.getElementById('albumForm');
      if (albumForm) {
          albumForm.addEventListener('submit', (e) => {
          e.preventDefault();
          this.saveAlbum();
          });
      }
      
      // Add event listeners for hidden fields to enable/disable Save button
      const releaseYearInput = document.getElementById('releaseYear');
      const albumFormatInput = document.getElementById('albumFormat');
      
      if (releaseYearInput) {
          releaseYearInput.addEventListener('input', () => {
              this.updateSaveButtonState();
          });
          releaseYearInput.addEventListener('change', () => {
              this.updateSaveButtonState();
          });
      }
      
      if (albumFormatInput) {
          albumFormatInput.addEventListener('input', () => {
              this.updateSaveButtonState();
          });
          albumFormatInput.addEventListener('change', () => {
              this.updateSaveButtonState();
          });
      }
      
      // Cancel button
      document.getElementById('cancelBtn').addEventListener('click', () => {
          this.hideModal();
      });
      
      // Sortable column headers
      document.querySelectorAll('.sortable-header').forEach(header => {
          header.addEventListener('click', (e) => {
              const sortField = e.currentTarget.dataset.sort;
              this.handleSort(sortField);
          });
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
      
      // Format filter change handler
      const formatFilter = document.getElementById('formatFilter');
      if (formatFilter) {
          formatFilter.addEventListener('change', (e) => {
              const artistValue = artistInput.value.trim();
              const albumValue = albumInput.value.trim();
              if (artistValue && albumValue && albumValue.length >= 2) {
                  this.debounceAlbumAutocomplete(artistValue, albumValue);
              }
          });
      }
      
      // Dropdown handling
      this.setupDropdown();
  }
  
  setupDropdown() {
      const dropdown = document.querySelector('.dropdown');
      const dropdownMenu = document.querySelector('.dropdown-menu');
      const dropdownToggle = document.querySelector('.dropdown-toggle');
      
      if (dropdown && dropdownMenu && dropdownToggle) {
          // Toggle dropdown on click
          dropdownToggle.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              dropdown.classList.toggle('active');
          });
          
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
      
      // Check if Save Album button should be enabled
      this.updateSaveButtonState();
  }
  
  updateSaveButtonState() {
      const saveButton = document.querySelector('#albumForm .btn-save');
      const releaseYearInput = document.getElementById('releaseYear');
      const albumFormatInput = document.getElementById('albumFormat');
      
      if (saveButton && releaseYearInput && albumFormatInput) {
          const hasReleaseYear = releaseYearInput.value.trim() !== '';
          const hasAlbumFormat = albumFormatInput.value.trim() !== '';
          
          if (hasReleaseYear && hasAlbumFormat) {
              saveButton.disabled = false;
              saveButton.style.opacity = '1';
              saveButton.style.cursor = 'pointer';
          } else {
              saveButton.disabled = true;
              saveButton.style.opacity = '0.5';
              saveButton.style.cursor = 'not-allowed';
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
          // Get format filter value
          const formatFilter = document.getElementById('formatFilter')?.value || '';
          
          let url = `api/music_api.php?action=albums_by_artist&artist=${encodeURIComponent(artist)}&search=${encodeURIComponent(search)}`;
          
          // Add format filter to URL if selected
          if (formatFilter) {
              url += `&format=${encodeURIComponent(formatFilter)}`;
          }
          
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
              
              // Create text span for album name and format
              const textSpan = document.createElement('span');
              if (field === 'album_name' && item.year) {
                  textSpan.textContent = `${item[field]} (${item.year})`;
              } else {
                  textSpan.textContent = item[field];
              }
              
              // Add format information if available
              if (field === 'album_name' && item.format) {
                  textSpan.innerHTML = textSpan.textContent + '<br><span class="format-text">' + item.format + '</span>';
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
          list.style.zIndex = '10020';
          list.style.position = 'absolute';
          // Don't set backgroundColor - let CSS handle it for dark mode
          list.style.border = '1px solid #ddd';
          list.style.borderRadius = '4px';
          list.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
          list.style.maxHeight = '350px';
          list.style.overflowY = 'auto';
          
          // Check if we're in a modal and move to body to avoid stacking context issues
          this.moveAutocompleteToBody(container, list);
          
          // Double-check positioning after a brief delay to ensure proper rendering
          setTimeout(() => {
              this.moveAutocompleteToBody(container, list);
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
                  
                  // Set year input - prioritize master year, fallback to specific release year
                  const yearInput = document.getElementById('releaseYear');
                  if (yearInput) {
                      if (item.master_year) {
                          // Use master year if available from search results (local albums)
                          yearInput.value = item.master_year;
                      } else if (item.id) {
                          // For external albums, fetch master year if not available
                          yearInput.value = ''; // Clear initially
                          this.fetchMasterYearForSelection(item.id, yearInput, item.year);
                      } else {
                          // Fallback to specific release year if no release ID available
                          yearInput.value = item.year || '';
                      }
                  }
                  
                  // Set format input from Discogs data
                  const formatInput = document.getElementById('albumFormat');
                  if (formatInput && item.format) {
                      formatInput.value = item.format;
                      formatInput.readOnly = false; // Allow editing after selection
                  }
                  
                  // Update Save button state after populating hidden fields
                  this.updateSaveButtonState();
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
  
  initBackToTop() {
      const backToTopBtn = document.getElementById('backToTopBtn');
      if (!backToTopBtn) return;
      
      // Show button when scrolled down
      const showBackToTop = () => {
          if (window.pageYOffset > 300) {
              backToTopBtn.classList.add('visible');
          } else {
              backToTopBtn.classList.remove('visible');
          }
      };
      
      // Scroll to top when button is clicked
      const scrollToTop = () => {
          // Use different approaches for mobile vs desktop
          if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
              // Mobile devices - use multiple scroll methods for maximum compatibility
              try {
                  // Method 1: Window scroll
                  window.scrollTo(0, 0);
                  
                  // Method 2: Document body scroll
                  if (document.body && document.body.scrollTop > 0) {
                      document.body.scrollTop = 0;
                  }
                  
                  // Method 3: Document element scroll
                  if (document.documentElement && document.documentElement.scrollTop > 0) {
                      document.documentElement.scrollTop = 0;
                  }
                  
                  // Method 4: Scroll the main container if it exists
                  const mainContainer = document.querySelector('.content-with-sidebar');
                  if (mainContainer && mainContainer.scrollTop > 0) {
                      mainContainer.scrollTop = 0;
                  }
                  
                  // Method 5: Force scroll on any scrollable parent
                  let currentElement = document.activeElement;
                  while (currentElement && currentElement !== document.body) {
                      if (currentElement.scrollTop > 0) {
                          currentElement.scrollTop = 0;
                      }
                      currentElement = currentElement.parentElement;
                  }
              } catch (error) {
                  // Fallback: try the most basic scroll method
                  window.scrollTo(0, 0);
              }
          } else {
              // Desktop - use smooth scrolling
              window.scrollTo({
                  top: 0,
                  behavior: 'smooth'
              });
          }
      };
      
      // Add event listeners
      window.addEventListener('scroll', showBackToTop);
      backToTopBtn.addEventListener('click', scrollToTop);
      
      // Show button on page load if already scrolled down
      showBackToTop();
  }
  
  filterByStyle(style) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting style filter
      this.currentArtistFilter = '';
      this.currentYearFilter = '';
      this.currentFormatFilter = '';
      this.consolidatedFormatTypes = null;
      this.currentLabelFilter = '';
      this.currentProducerFilter = '';
      this.currentSearch = '';
      
      // Set the style filter
      this.currentStyleFilter = style;
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when style filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by style: ${style}`, 'info');
      
      // Load albums with the style filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }
  
  // Helper function to build combined filter text
  buildFilterText() {
      const filters = [];
      
      if (this.currentArtistFilter) {
          filters.push(`Artist: ${this.currentArtistFilter}`);
      }
      
      if (this.currentYearFilter) {
          filters.push(`Year: ${this.currentYearFilter}`);
      }
      
      if (this.currentStyleFilter) {
          filters.push(`Style: ${this.currentStyleFilter}`);
      }
      
      if (this.currentFormatFilter) {
          filters.push(`Format: ${this.currentFormatFilter}`);
      }
      
      if (this.currentLabelFilter) {
          filters.push(`Label: ${this.currentLabelFilter}`);
      }
      
      if (this.currentProducerFilter) {
          filters.push(`Producer: ${this.currentProducerFilter}`);
      }
      
      return filters.join('; ');
  }

  filterByArtist(artist) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting artist filter
      this.currentYearFilter = '';
      this.currentStyleFilter = '';
      this.currentFormatFilter = '';
      this.consolidatedFormatTypes = null;
      this.currentLabelFilter = '';
      this.currentProducerFilter = '';
      this.currentSearch = '';
      
      // Set the artist filter
      this.currentArtistFilter = artist;
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when artist filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by artist: ${artist}`, 'info');
      
      // Load albums with the artist filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }

  filterByFormat(format) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting format filter
      this.currentArtistFilter = '';
      this.currentYearFilter = '';
      this.currentStyleFilter = '';
      this.consolidatedFormatTypes = null;
      this.currentLabelFilter = '';
      this.currentProducerFilter = '';
      this.currentSearch = '';
      
      // Set the format filter
      this.currentFormatFilter = format;
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when format filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by format: ${format}`, 'info');
      
      // Load albums with the format filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }

  filterByLabel(label) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting label filter
      this.currentArtistFilter = '';
      this.currentYearFilter = '';
      this.currentStyleFilter = '';
      this.currentFormatFilter = '';
      this.consolidatedFormatTypes = null;
      this.currentProducerFilter = '';
      
      // Clear search filter so label filter shows all albums by that label
      this.currentSearch = '';
      
      // Set the label filter
      this.currentLabelFilter = label;
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when label filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by label: ${label}`, 'info');
      
      // Load albums with the label filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }

  filterByProducer(producer) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting producer filter
      this.currentArtistFilter = '';
      this.currentYearFilter = '';
      this.currentStyleFilter = '';
      this.currentFormatFilter = '';
      this.consolidatedFormatTypes = null;
      this.currentLabelFilter = '';
      
      // Clear search filter so producer filter shows all albums by that producer
      this.currentSearch = '';
      
      // Set the producer filter
      this.currentProducerFilter = producer;
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when producer filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by producer: ${producer}`, 'info');
      
      // Load albums with the producer filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }

  filterByConsolidatedFormat(formatTypes, displayName) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting consolidated format filter
      this.currentArtistFilter = '';
      this.currentYearFilter = '';
      this.currentStyleFilter = '';
      this.currentLabelFilter = '';
      this.currentProducerFilter = '';
      this.currentSearch = '';
      
      // Set a special consolidated format filter
      this.currentFormatFilter = displayName;
      this.consolidatedFormatTypes = formatTypes; // Store the actual format types to filter by
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when format filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by format: ${displayName}`, 'info');
      
      // Load albums with the consolidated format filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }
  
  clearStyleFilter() {
      this.currentStyleFilter = '';
      this.currentFormatFilter = ''; // Also clear format filter
      this.consolidatedFormatTypes = null; // Also clear consolidated format types
      this.currentYearFilter = ''; // Also clear year filter
      this.currentArtistFilter = ''; // Also clear artist filter
      this.currentLabelFilter = ''; // Also clear label filter
      this.currentProducerFilter = ''; // Also clear producer filter
      
      // Re-enable search input
      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
          searchInput.value = '';
          searchInput.disabled = false;
      }
      
      this.loadAlbums();
  }
  
  clearFormatFilter() {
      this.currentFormatFilter = '';
      this.consolidatedFormatTypes = null; // Clear consolidated format types
      this.currentStyleFilter = ''; // Also clear style filter
      this.currentYearFilter = ''; // Also clear year filter
      this.currentArtistFilter = ''; // Also clear artist filter
      this.currentLabelFilter = ''; // Also clear label filter
      this.currentProducerFilter = ''; // Also clear producer filter
      
      // Re-enable search input
      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
          searchInput.value = '';
          searchInput.disabled = false;
      }
      
      this.loadAlbums();
  }
  
  filterByYear(year) {
      // Close the stats modal
      this.hideStatsModal();
      
      // Clear other filters before setting year filter
      this.currentArtistFilter = '';
      this.currentStyleFilter = '';
      this.currentFormatFilter = '';
      this.consolidatedFormatTypes = null;
      this.currentLabelFilter = '';
      this.currentProducerFilter = '';
      this.currentSearch = '';
      
      // Set the year filter
      this.currentYearFilter = year;
      
      // Temporarily set filter to "all" to get all albums for accurate counting
      const originalFilter = this.currentFilter;
      this.currentFilter = 'all';
      
      // Update the search input to show the current filter
      const searchInput = document.getElementById('searchInput');
      const clearSearchBtn = document.getElementById('clearSearch');
      if (searchInput) {
          searchInput.value = this.buildFilterText();
          searchInput.disabled = true; // Disable search input when year filter is active
          clearSearchBtn.classList.add('visible'); // Show clear button
      }
      
      // Show a message about the current filter
      this.showMessage(`Filtering by year: ${year}`, 'info');
      
      // Load albums with the year filter
      this.loadAlbums();
      
      // Restore the original filter for display purposes
      this.currentFilter = originalFilter;
  }
  
  clearYearFilter() {
      this.currentYearFilter = '';
      this.currentStyleFilter = ''; // Also clear style filter
      this.currentFormatFilter = ''; // Also clear format filter
      this.consolidatedFormatTypes = null; // Also clear consolidated format types
      this.currentArtistFilter = ''; // Also clear artist filter
      this.currentLabelFilter = ''; // Also clear label filter
      this.currentProducerFilter = ''; // Also clear producer filter
      
      // Re-enable search input
      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
          searchInput.value = '';
          searchInput.disabled = false;
      }
      
      this.loadAlbums();
  }
  
  handleStyleSearchSuggestions(searchValue) {
      const searchLower = searchValue.toLowerCase();
      
      // Check if user is typing a style search
      if (searchLower.startsWith('style:') || searchLower.startsWith('genre:') || searchLower.startsWith('type:')) {
          const styleTerm = searchValue.replace(/^(style|genre|type):\s*/i, '').trim();
          
          // If they've typed "style:" but nothing after, show a helpful message
          if (styleTerm === '') {
              this.showMessage('Type a style name after "style:" (e.g., style: rock, style: jazz)', 'info');
          } else if (styleTerm.length > 0) {
              // Clear the info message if they start typing a style name
              const messageEl = document.getElementById('message');
              if (messageEl && messageEl.classList.contains('info')) {
                  messageEl.classList.remove('show');
              }
          }
      }
  }
  
  async loadStats() {
      try {
          const response = await fetch('api/music_api.php?action=stats');
          const data = await response.json();
          
          if (data.success) {
              this.updateStats(data.data);
          }
      } catch (error) {
          // Stats loading failed silently
      }
  }
  
  updateStats(stats) {
      // Update the filter buttons with counts
      const ownButton = document.querySelector('[data-filter="owned"]');
      const wantButton = document.querySelector('[data-filter="wanted"]');
      const allButton = document.querySelector('[data-filter="all"]');
      
      // Get current stats settings
      const statsSettings = this.getStatsSettings();
      
      if (ownButton) {
          const ownedCount = stats.owned_count || 0;
          if (statsSettings.show_owned_albums) {
              ownButton.textContent = `${ownedCount} Owned`;
          } else {
              ownButton.textContent = 'Owned';
          }
      }
      
      if (wantButton) {
          const wantedCount = stats.wanted_count || 0;
          if (statsSettings.show_wanted_albums) {
              wantButton.textContent = `${wantedCount} Want`;
          } else {
              wantButton.textContent = 'Want';
          }
      }
      
      if (allButton) {
          const totalCount = stats.total_albums || 0;
          if (statsSettings.show_total_albums) {
              allButton.textContent = `${totalCount} Total`;
          } else {
              allButton.textContent = 'Total';
          }
      }
      
      // Update footer stats
      this.updateFooterStats(stats);
      
      // Update style statistics
      const styleStatsList = document.getElementById('styleStatsList');
      if (styleStatsList && stats.style_counts) {
          const styleEntries = Object.entries(stats.style_counts);
          if (styleEntries.length > 0) {
              const allStyles = styleEntries; // Show all styles
              styleStatsList.innerHTML = allStyles.map(([style, count]) => `
                  <div class="style-stat-item" data-style="${this.escapeHtml(style)}">
                      <span class="style-name">${this.escapeHtml(style)}</span>
                      <span class="style-count">${count}</span>
                  </div>
              `).join('');
              
              // Add click event listeners to style items
              styleStatsList.querySelectorAll('.style-stat-item').forEach(item => {
                  item.addEventListener('click', (e) => {
                      e.preventDefault();
                      const style = item.dataset.style;
                      this.filterByStyle(style);
                  });
              });
          } else {
              styleStatsList.innerHTML = '<p class="no-styles">No style information available</p>';
          }
      }
      
      // Update format statistics
      const formatStatsList = document.getElementById('formatStatsList');
      if (formatStatsList && stats.format_counts) {
          const formatEntries = Object.entries(stats.format_counts);
          if (formatEntries.length > 0) {
              const allFormats = formatEntries; // Show all formats
              formatStatsList.innerHTML = allFormats.map(([format, count]) => `
                  <div class="format-stat-item" data-format="${encodeURIComponent(format)}">
                      <span class="format-name">${this.escapeHtml(format)}</span>
                      <span class="format-count">${count}</span>
                  </div>
              `).join('');
              
              // Add click event listeners to format items
              formatStatsList.querySelectorAll('.format-stat-item').forEach(item => {
                  item.addEventListener('click', (e) => {
                      e.preventDefault();
                      const format = decodeURIComponent(item.dataset.format);
                      this.filterByFormat(format);
                  });
              });
          } else {
              formatStatsList.innerHTML = '<p class="no-formats">No format information available</p>';
          }
      }
      
      // Update label statistics
      const labelStatsList = document.getElementById('labelStatsList');
      if (labelStatsList && stats.label_counts) {
          const labelEntries = Object.entries(stats.label_counts);
          if (labelEntries.length > 0) {
              // Sort by count (descending) then by label name (ascending)
              labelEntries.sort((a, b) => {
                  const countDiff = b[1] - a[1];
                  if (countDiff !== 0) return countDiff;
                  return a[0].localeCompare(b[0]);
              });
              
              // Show top 10 labels
              const topLabels = labelEntries.slice(0, 10);
              labelStatsList.innerHTML = topLabels.map(([label, count]) => {
                  const cleanLabel = this.cleanDiscogsNumbering(label);
                  return `
                      <div class="label-stat-item" data-label="${encodeURIComponent(label)}">
                          <span class="label-name">${this.escapeHtml(cleanLabel)}</span>
                          <span class="label-count">${count}</span>
                      </div>
                  `;
              }).join('');
              
              // Add click event listeners to label items
              labelStatsList.querySelectorAll('.label-stat-item').forEach(item => {
                  item.addEventListener('click', (e) => {
                      e.preventDefault();
                      const label = decodeURIComponent(item.dataset.label);
                      this.filterByLabel(label);
                  });
              });
          } else {
              labelStatsList.innerHTML = '<p class="no-labels">No label information available</p>';
          }
      }
      
      // Update year statistics
      const yearStatsList = document.getElementById('yearStatsList');
      if (yearStatsList && stats.year_counts) {
          // Convert to array and sort by count (descending) then by year (descending)
          const yearEntries = Object.entries(stats.year_counts);
          
          // Sort by count (descending) then by year (descending)
          yearEntries.sort((a, b) => {
              const countDiff = b[1] - a[1]; // Sort by count descending
              if (countDiff !== 0) return countDiff;
              return b[0] - a[0]; // If counts are equal, sort by year descending
          });
          
          if (yearEntries.length > 0) {
              yearStatsList.innerHTML = yearEntries.map(([year, count]) => `
                  <div class="year-stat-item" data-year="${year}">
                      <span class="year-name">${this.escapeHtml(year)}</span>
                      <span class="year-count">${count}</span>
                  </div>
              `).join('');
              
              // Add click event listeners to year items
              yearStatsList.querySelectorAll('.year-stat-item').forEach(item => {
                  item.addEventListener('click', (e) => {
                      e.preventDefault();
                      const year = item.dataset.year;
                      this.filterByYear(year);
                  });
              });
          } else {
              yearStatsList.innerHTML = '<p class="no-years">No year information available</p>';
          }
      }
      
      // Update chart display after updating stats
      this.updateChartDisplay();
      
      // Update modal display after updating stats
      this.updateModalDisplay();
  }
  
  updateFooterStats(stats) {
      // Check if Chart.js is loaded
      if (typeof Chart === 'undefined') {
          setTimeout(() => this.updateFooterStats(stats), 100);
          return;
      }
      
      // Update footer stats (mobile only)
      const footerStats = document.getElementById('footerStats');
      if (footerStats) {
          this.createFooterYearChart(stats.year_counts, stats.total_albums);
          this.createFooterStyleChart(stats.style_counts, stats.total_albums);
          this.createFooterFormatChart(stats.format_counts, stats.total_albums);
          this.createFooterLabelChart(stats.label_counts, stats.total_albums);
      }
      
      // Update sidebar stats (desktop only)
      const sidebarStats = document.getElementById('sidebarStats');
      if (sidebarStats) {
          this.createSidebarYearChart(stats.year_counts, stats.total_albums);
          this.createSidebarStyleChart(stats.style_counts, stats.total_albums);
          this.createSidebarFormatChart(stats.format_counts, stats.total_albums);
          this.createSidebarLabelChart(stats.label_counts, stats.total_albums);
      }
  }
  
  createFooterYearChart(yearCounts, totalAlbums) {
      const container = document.getElementById('footerYearChart');
      if (!container || !yearCounts) return;
      
      // Get top 10 years
      const yearEntries = Object.entries(yearCounts);
      yearEntries.sort((a, b) => {
          const countDiff = b[1] - a[1];
          if (countDiff !== 0) return countDiff;
          return b[0] - a[0];
      });
      const top10Years = yearEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllAlbums = totalAlbums || yearEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Years.length === 0) {
          container.innerHTML = '<p class="no-data">No year data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="yearBarChart" width="200" height="500"></canvas>';
      
      const ctx = document.getElementById('yearBarChart').getContext('2d');
      const maxCount = Math.max(...top10Years.map(([, count]) => count));
      
      const yearChart = new Chart(ctx, {
          type: 'bar',
          data: {
              labels: top10Years.map(([year]) => String(year)),
              datasets: [{
                  data: top10Years.map(([, count]) => count),
                  backgroundColor: '#38BA6A', // Single green color for all bars
                  borderWidth: 0, // Remove borders
                  borderRadius: 4 // Add rounded corners to soften the bars
              }]
          },
          options: {
              indexAxis: 'y', // This makes the bars horizontal
              responsive: true,
              maintainAspectRatio: false,
              layout: {
                  padding: {
                      top: 10,
                      bottom: 10
                  }
              },
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      callbacks: {
                          label: function(context) {
                              const percentage = ((context.parsed.x / totalAllAlbums) * 100).toFixed(1);
                              return `${context.label}: ${context.parsed.x} (${percentage}%)`;
                          }
                      }
                  }
              },
              scales: {
                  x: {
                      beginAtZero: true,
                      max: maxCount,
                      ticks: {
                          stepSize: 1,
                          color: 'rgba(255, 255, 255, 0.8)'
                      },
                      grid: {
                          color: 'rgba(255, 255, 255, 0.1)'
                      }
                  },
                  y: {
                      ticks: {
                          color: 'rgba(255, 255, 255, 0.8)',
                          maxTicksLimit: 10
                      },
                      grid: {
                          display: false
                      },
                      categoryPercentage: 0.6,
                      barPercentage: 0.8
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const year = top10Years[elementIndex][0];
                      this.filterByYear(year);
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  createFooterStyleChart(styleCounts, totalAlbums) {
      const container = document.getElementById('footerStyleChart');
      if (!container || !styleCounts) return;
      
      // Get top 10 styles
      const styleEntries = Object.entries(styleCounts);
      styleEntries.sort((a, b) => b[1] - a[1]);
      const top10Styles = styleEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllStyles = totalAlbums || styleEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Styles.length === 0) {
          container.innerHTML = '<p class="no-data">No style data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="stylePieChart" width="300" height="300"></canvas>';
      
      const ctx = document.getElementById('stylePieChart').getContext('2d');
      
      const styleChart = new Chart(ctx, {
          type: 'pie',
          data: {
              labels: top10Styles.map(([style]) => style),
              datasets: [{
                  data: top10Styles.map(([, count]) => count),
                  backgroundColor: [
                      '#38BA6A', // Green
                      '#E9C46A', // Yellow
                      '#EB8244', // Orange
                      '#309BF1', // Blue
                      '#E83DB4', // Pink
                      '#2BBBAD', // Teal
                      '#F94144', // Red
                      '#90BE6D', // Light Green
                      '#F896D8', // Light Pink
                      '#577590'  // Dark Blue
                  ],
                  borderWidth: 0 // Remove borders
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      position: 'average',
                      intersect: false,
                      callbacks: {
                          label: function(context) {
                              const percentage = ((context.parsed / totalAllStyles) * 100).toFixed(1);
                              return `${context.label}: ${context.parsed} (${percentage}%)`;
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 20,
                      bottom: 20,
                      left: 20,
                      right: 20
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const style = top10Styles[elementIndex][0];
                      this.filterByStyle(style);
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  createFooterFormatChart(formatCounts, totalAlbums) {
      const container = document.getElementById('footerFormatChart');
      if (!container || !formatCounts) return;
      
      // Consolidate formats before processing
      const consolidatedFormats = {};
      
      Object.entries(formatCounts).forEach(([format, count]) => {
          // Skip "Stereo" and "Vinyl" formats (too generic)
          if (['stereo', 'vinyl'].includes(format.toLowerCase())) {
              return;
          }
          
          // Combine LP, Album, Reissue, Remastered, and Repress into "LP"
          if (['lp', 'album', 'reissue', 'remastered', 'repress'].includes(format.toLowerCase())) {
              if (consolidatedFormats['LP']) {
                  consolidatedFormats['LP'] += count;
              } else {
                  consolidatedFormats['LP'] = count;
              }
              return;
          }
          
          // Combine Single and Maxi-Single into "Single"
          if (['single', 'maxi-single'].includes(format.toLowerCase())) {
              if (consolidatedFormats['Single']) {
                  consolidatedFormats['Single'] += count;
              } else {
                  consolidatedFormats['Single'] = count;
              }
              return;
          }
          
          // Keep other formats as-is
          consolidatedFormats[format] = count;
      });
      
      // Get top 10 formats from consolidated data
      const formatEntries = Object.entries(consolidatedFormats);
      formatEntries.sort((a, b) => b[1] - a[1]);
      const top10Formats = formatEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllAlbums = totalAlbums || formatEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Formats.length === 0) {
          container.innerHTML = '<p class="no-data">No format data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="formatPieChart" width="300" height="300"></canvas>';
      
      const ctx = document.getElementById('formatPieChart').getContext('2d');
      
      const formatChart = new Chart(ctx, {
          type: 'pie',
          data: {
              labels: top10Formats.map(([format]) => format),
              datasets: [{
                  data: top10Formats.map(([, count]) => count),
                  backgroundColor: [
                      '#38BA6A', // Green
                      '#38BA6A', // Green
                      '#E9C46A', // Yellow
                      '#EB8244', // Orange
                      '#309BF1', // Blue
                      '#E83DB4', // Pink
                      '#2BBBAD', // Teal
                      '#F94144', // Red
                      '#90BE6D', // Light Green
                      '#F896D8', // Light Pink
                      '#577590'  // Dark Blue
                  ],
                  borderWidth: 0 // Remove borders
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      position: 'average',
                      intersect: false,
                      callbacks: {
                          label: function(context) {
                              // Calculate percentage based on the consolidated format totals
                              // This gives us the visual proportion each format represents
                              const totalPieData = top10Formats.reduce((sum, [, count]) => sum + count, 0);
                              const percentage = ((context.parsed / totalPieData) * 100).toFixed(1);
                              return `${context.label}: ${percentage}%`;
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 20,
                      bottom: 20,
                      left: 20,
                      right: 20
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const format = top10Formats[elementIndex][0];
                      
                      // Handle consolidated format filtering
                      if (format === 'LP') {
                          // For LP, we need to handle "LP", "Album", "Reissue", "Remastered", and "Repress" formats
                          // We'll use a special approach to show all albums that match any of these formats
                          this.filterByConsolidatedFormat(['LP', 'Album', 'Reissue', 'Remastered', 'Repress'], 'LP');
                      } else if (format === 'Single') {
                          // For Single, we need to handle both "Single" and "Maxi-Single" formats
                          this.filterByConsolidatedFormat(['Single', 'Maxi-Single'], 'Single');
                      } else {
                          // For other formats, use the standard filtering
                          this.filterByFormat(format);
                      }
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  // Sidebar Chart Functions
  createSidebarYearChart(yearCounts, totalAlbums) {
      const container = document.getElementById('sidebarYearChart');
      if (!container || !yearCounts) return;
      
      // Get top 10 years
      const yearEntries = Object.entries(yearCounts);
      yearEntries.sort((a, b) => {
          const countDiff = b[1] - a[1];
          if (countDiff !== 0) return countDiff;
          return b[0] - a[0];
      });
      const top10Years = yearEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllAlbums = totalAlbums || yearEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Years.length === 0) {
          container.innerHTML = '<p class="no-data">No year data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="sidebarYearBarChart" width="200" height="500"></canvas>';
      
      const ctx = document.getElementById('sidebarYearBarChart').getContext('2d');
      const maxCount = Math.max(...top10Years.map(([, count]) => count));
      
      const yearChart = new Chart(ctx, {
          type: 'bar',
          data: {
              labels: top10Years.map(([year]) => String(year)),
              datasets: [{
                  data: top10Years.map(([, count]) => count),
                  backgroundColor: '#38BA6A',
                  borderWidth: 0,
                  borderRadius: 4
              }]
          },
          options: {
              indexAxis: 'y',
              responsive: true,
              maintainAspectRatio: false,
              layout: {
                  padding: {
                      top: 10,
                      bottom: 10
                  }
              },
              scales: {
                  x: {
                      beginAtZero: true,
                      max: maxCount,
                      ticks: {
                          stepSize: 1,
                          color: 'rgba(255, 255, 255, 0.8)'
                      },
                      grid: {
                          color: 'rgba(255, 255, 255, 0.1)'
                      }
                  },
                  y: {
                      ticks: {
                          color: 'rgba(255, 255, 255, 0.8)',
                          maxTicksLimit: 10
                      },
                      grid: {
                          display: false
                      }
                  }
              },
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      callbacks: {
                          label: function(context) {
                              const percentage = ((context.parsed.x / totalAllAlbums) * 100).toFixed(1);
                              return `${context.label}: ${context.parsed.x} (${percentage}%)`;
                          }
                      }
                  }
              },
              scales: {
                  x: {
                      beginAtZero: true,
                      max: maxCount,
                      ticks: {
                          stepSize: 1,
                          color: 'rgba(255, 255, 255, 0.8)'
                      },
                      grid: {
                          color: 'rgba(255, 255, 255, 0.1)'
                      }
                  },
                  y: {
                      ticks: {
                          color: 'rgba(255, 255, 255, 0.8)',
                          maxTicksLimit: 10
                      },
                      grid: {
                          display: false
                      },
                      categoryPercentage: 0.6,
                      barPercentage: 0.8
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const year = top10Years[elementIndex][0];
                      this.filterByYear(year);
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  createSidebarStyleChart(styleCounts, totalAlbums) {
      const container = document.getElementById('sidebarStyleChart');
      if (!container || !styleCounts) return;
      
      // Get top 10 styles
      const styleEntries = Object.entries(styleCounts);
      styleEntries.sort((a, b) => b[1] - a[1]);
      const top10Styles = styleEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllStyles = totalAlbums || styleEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Styles.length === 0) {
          container.innerHTML = '<p class="no-data">No style data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="sidebarStylePieChart" width="300" height="300"></canvas>';
      
      const ctx = document.getElementById('sidebarStylePieChart').getContext('2d');
      
      const styleChart = new Chart(ctx, {
          type: 'pie',
          data: {
              labels: top10Styles.map(([style]) => style),
              datasets: [{
                  data: top10Styles.map(([, count]) => count),
                  backgroundColor: [
                      '#38BA6A', '#E9C46A', '#EB8244', '#309BF1', '#E83DB4',
                      '#2BBBAD', '#F94144', '#90BE6D', '#F896D8', '#577590'
                  ],
                  borderWidth: 0
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      position: 'average',
                      intersect: false,
                      callbacks: {
                          label: function(context) {
                              const percentage = ((context.parsed / totalAllStyles) * 100).toFixed(1);
                              return `${context.label}: ${context.parsed} (${percentage}%)`;
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 20, bottom: 20, left: 20, right: 20
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const style = top10Styles[elementIndex][0];
                      this.filterByStyle(style);
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  createSidebarFormatChart(formatCounts, totalAlbums) {
      const container = document.getElementById('sidebarFormatChart');
      if (!container || !formatCounts) return;
      
      // Consolidate formats before processing
      const consolidatedFormats = {};
      
      Object.entries(formatCounts).forEach(([format, count]) => {
          // Skip "Stereo" and "Vinyl" formats (too generic)
          if (['stereo', 'vinyl'].includes(format.toLowerCase())) {
              return;
          }
          
          // Combine LP, Album, Reissue, Remastered, and Repress into "LP"
          if (['lp', 'album', 'reissue', 'remastered', 'repress'].includes(format.toLowerCase())) {
              if (consolidatedFormats['LP']) {
                  consolidatedFormats['LP'] += count;
              } else {
                  consolidatedFormats['LP'] = count;
              }
              return;
          }
          
          // Combine Single and Maxi-Single into "Single"
          if (['single', 'maxi-single'].includes(format.toLowerCase())) {
              if (consolidatedFormats['Single']) {
                  consolidatedFormats['Single'] += count;
              } else {
                  consolidatedFormats['Single'] = count;
              }
              return;
          }
          
          // Keep other formats as-is
          consolidatedFormats[format] = count;
      });
      
      // Get top 10 formats from consolidated data
      const formatEntries = Object.entries(consolidatedFormats);
      formatEntries.sort((a, b) => b[1] - a[1]);
      const top10Formats = formatEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllAlbums = totalAlbums || formatEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Formats.length === 0) {
          container.innerHTML = '<p class="no-data">No format data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="sidebarFormatPieChart" width="300" height="300"></canvas>';
      
      const ctx = document.getElementById('sidebarFormatPieChart').getContext('2d');
      
      const formatChart = new Chart(ctx, {
          type: 'pie',
          data: {
              labels: top10Formats.map(([format]) => format),
              datasets: [{
                  data: top10Formats.map(([, count]) => count),
                  backgroundColor: [
                      '#38BA6A', '#E9C46A', '#EB8244', '#309BF1', '#E83DB4',
                      '#2BBBAD', '#F94144', '#90BE6D', '#F896D8', '#577590'
                  ],
                  borderWidth: 0
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      position: 'average',
                      intersect: false,
                      callbacks: {
                          label: function(context) {
                              // Calculate percentage based on the consolidated format totals
                              // This gives us the visual proportion each format represents
                              const totalPieData = top10Formats.reduce((sum, [, count]) => sum + count, 0);
                              const percentage = ((context.parsed / totalPieData) * 100).toFixed(1);
                              return `${context.label}: ${percentage}%`;
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 20, bottom: 20, left: 20, right: 20
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const format = top10Formats[elementIndex][0];
                      
                      // Handle consolidated format filtering
                      if (format === 'LP') {
                          this.filterByConsolidatedFormat(['LP', 'Album', 'Reissue', 'Remastered', 'Repress'], 'LP');
                      } else if (format === 'Single') {
                          this.filterByConsolidatedFormat(['Single', 'Maxi-Single'], 'Single');
                      } else {
                          this.filterByFormat(format);
                      }
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  createFooterLabelChart(labelCounts, totalAlbums) {
      const container = document.getElementById('footerLabelChart');
      if (!container || !labelCounts) return;
      
      // Get top 10 labels
      const labelEntries = Object.entries(labelCounts);
      labelEntries.sort((a, b) => b[1] - a[1]);
      const top10Labels = labelEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllLabels = totalAlbums || labelEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Labels.length === 0) {
          container.innerHTML = '<p class="no-data">No label data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="labelPieChart" width="300" height="300"></canvas>';
      
      const ctx = document.getElementById('labelPieChart').getContext('2d');
      
      const labelChart = new Chart(ctx, {
          type: 'pie',
          data: {
              labels: top10Labels.map(([label]) => this.cleanDiscogsNumbering(label)),
              datasets: [{
                  data: top10Labels.map(([, count]) => count),
                  backgroundColor: [
                      '#38BA6A', '#E9C46A', '#EB8244', '#309BF1', '#E83DB4',
                      '#2BBBAD', '#F94144', '#90BE6D', '#F896D8', '#577590'
                  ],
                  borderWidth: 0
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      position: 'average',
                      intersect: false,
                      callbacks: {
                          label: function(context) {
                              const percentage = ((context.parsed / totalAllLabels) * 100).toFixed(1);
                              return `${context.label}: ${context.parsed} (${percentage}%)`;
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 20, bottom: 20, left: 20, right: 20
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const label = top10Labels[elementIndex][0];
                      this.filterByLabel(label);
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  createSidebarLabelChart(labelCounts, totalAlbums) {
      const container = document.getElementById('sidebarLabelChart');
      if (!container || !labelCounts) return;
      
      // Get top 10 labels
      const labelEntries = Object.entries(labelCounts);
      labelEntries.sort((a, b) => b[1] - a[1]);
      const top10Labels = labelEntries.slice(0, 10);
      
      // Use the total album count for accurate percentages
      const totalAllLabels = totalAlbums || labelEntries.reduce((sum, [, count]) => sum + count, 0);
      
      if (top10Labels.length === 0) {
          container.innerHTML = '<p class="no-data">No label data available</p>';
          return;
      }
      
      // Clear existing content
      container.innerHTML = '<canvas id="sidebarLabelPieChart" width="300" height="300"></canvas>';
      
      const ctx = document.getElementById('sidebarLabelPieChart').getContext('2d');
      
      const labelChart = new Chart(ctx, {
          type: 'pie',
          data: {
              labels: top10Labels.map(([label]) => this.cleanDiscogsNumbering(label)),
              datasets: [{
                  data: top10Labels.map(([, count]) => count),
                  backgroundColor: [
                      '#38BA6A', '#E9C46A', '#EB8244', '#309BF1', '#E83DB4',
                      '#2BBBAD', '#F94144', '#90BE6D', '#F896D8', '#577590'
                  ],
                  borderWidth: 0
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      display: false
                  },
                  tooltip: {
                      position: 'average',
                      intersect: false,
                      callbacks: {
                          label: function(context) {
                              const percentage = ((context.parsed / totalAllLabels) * 100).toFixed(1);
                              return `${context.label}: ${context.parsed} (${percentage}%)`;
                          }
                      }
                  }
              },
              layout: {
                  padding: {
                      top: 20, bottom: 20, left: 20, right: 20
                  }
              },
              onClick: (event, elements) => {
                  if (elements.length > 0) {
                      const elementIndex = elements[0].index;
                      const label = top10Labels[elementIndex][0];
                      this.filterByLabel(label);
                  }
              },
              onHover: (event, elements) => {
                  event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
              }
          }
      });
  }
  
  updateFilterButtonsWithFilteredCount(filteredAlbums) {
      // Check if there are active filters
                const hasActiveFilters = this.currentSearch || this.currentStyleFilter || this.currentFormatFilter || this.currentYearFilter || this.currentArtistFilter || this.currentLabelFilter || this.currentProducerFilter;
      
      if (!hasActiveFilters) {
          // No active filters, don't update the filter buttons
          // Let the stats function handle the overall totals
          return;
      }
      
      // Count albums by status in the filtered results
      const totalCount = filteredAlbums.length;
      const ownedCount = filteredAlbums.filter(album => album.is_owned == 1).length;
      const wantedCount = filteredAlbums.filter(album => album.want_to_own == 1).length;
      
      // Get current stats settings
      const statsSettings = this.getStatsSettings();
      
      // Update the filter buttons with filtered counts
      const ownButton = document.querySelector('[data-filter="owned"]');
      const wantButton = document.querySelector('[data-filter="wanted"]');
      const allButton = document.querySelector('[data-filter="all"]');
      
      if (ownButton) {
          if (statsSettings.show_owned_albums) {
              ownButton.textContent = `${ownedCount} Owned`;
          } else {
              ownButton.textContent = 'Owned';
          }
      }
      
      if (wantButton) {
          if (statsSettings.show_wanted_albums) {
              wantButton.textContent = `${wantedCount} Want`;
          } else {
              wantButton.textContent = 'Want';
          }
      }
      
      if (allButton) {
          if (statsSettings.show_total_albums) {
              allButton.textContent = `${totalCount} Total`;
          } else {
              allButton.textContent = 'Total';
          }
      }
  }
  
  async loadAlbums() {
      this.showLoading();
      
      // Add loading class to table container for overlay effect
      const tableContainer = document.querySelector('.table-container');
      if (tableContainer) {
          tableContainer.classList.add('loading');
      }
      
      try {
          // Check if this is a style search
          const searchLower = this.currentSearch.toLowerCase();
          const styleKeywords = ['style:', 'genre:', 'type:'];
          const isStyleSearch = styleKeywords.some(keyword => searchLower.startsWith(keyword));
          
          // For style searches, don't send the search term to the server
          const searchParam = isStyleSearch ? '' : this.currentSearch;
          
          // When there are active filters, always fetch all albums to get accurate counts
          const hasActiveFilters = this.currentSearch || this.currentStyleFilter || this.currentFormatFilter || this.currentYearFilter || this.currentArtistFilter || this.currentLabelFilter || this.currentProducerFilter;
          const filterToUse = hasActiveFilters ? 'all' : this.currentFilter;
          
          const params = new URLSearchParams({
              action: 'albums',
              filter: filterToUse,
              search: searchParam
          });

          const response = await this.fetchWithCache(`api/music_api.php?${params}`);
          const data = await response.json();
          
          if (data.success) {
              let albums = data.data;
              
              // Apply style filter if set
              if (this.currentStyleFilter) {
                  albums = albums.filter(album => {
                      if (!album.style) return false;
                      const styles = album.style.split(',').map(s => s.trim());
                      return styles.includes(this.currentStyleFilter);
                  });
              }
              
              // Apply format filter if set
              if (this.currentFormatFilter) {
                  albums = albums.filter(album => {
                      if (!album.format) return false;
                      const formats = album.format.split(',').map(f => f.trim());
                      
                      // Handle consolidated format filtering
                      if (this.consolidatedFormatTypes) {
                          // Check if any of the album's formats match any of the consolidated format types
                          return formats.some(format => {
                              const unescapedFormat = format.replace(/\\"/g, '"').toLowerCase().trim();
                              return this.consolidatedFormatTypes.some(consolidatedType => 
                                  consolidatedType.toLowerCase() === unescapedFormat
                              );
                          });
                      } else {
                          // Standard format filtering
                          return formats.some(format => {
                              // Unescape quotes for comparison
                              const unescapedFormat = format.replace(/\\"/g, '"');
                              // Use exact match for more specific format filtering
                              return unescapedFormat.toLowerCase() === this.currentFormatFilter.toLowerCase();
                          });
                      }
                  });
              }
              
              // Apply year filter if set
              if (this.currentYearFilter) {
                  albums = albums.filter(album => {
                      return album.release_year == this.currentYearFilter;
                  });
              }
              
              // Apply artist filter if set
              if (this.currentArtistFilter) {
                  albums = albums.filter(album => {
                      return album.artist_name.toLowerCase() === this.currentArtistFilter.toLowerCase();
                  });
              }
              
              // Apply label filter if set
              if (this.currentLabelFilter) {
                  albums = albums.filter(album => {
                      if (!album.label) return false;
                      return album.label.toLowerCase() === this.currentLabelFilter.toLowerCase();
                  });
              }
              
              // Apply producer filter if set
              if (this.currentProducerFilter) {
                  albums = albums.filter(album => {
                      if (!album.producer) return false;
                      return album.producer.toLowerCase().includes(this.currentProducerFilter.toLowerCase());
                  });
              }
              
              // Apply client-side style search if search term contains style keywords
              if (this.currentSearch && !this.currentStyleFilter) {
                  const searchLower = this.currentSearch.toLowerCase();
                  const styleKeywords = ['style:', 'genre:', 'type:'];
                  const hasStyleKeyword = styleKeywords.some(keyword => searchLower.startsWith(keyword));

                  if (hasStyleKeyword) {
                      // Extract style search term
                      const styleSearchTerm = this.currentSearch.replace(/^(style|genre|type):\s*/i, '').trim();
                      
                      if (styleSearchTerm) {
                          albums = albums.filter(album => {
                              if (!album.style) return false;
                              
                              const styles = album.style.toLowerCase();
                              const searchTerm = styleSearchTerm.toLowerCase();
                              
                              // Split the styles by comma and check each one
                              const styleArray = styles.split(',').map(s => s.trim().toLowerCase());
                              return styleArray.some(style => style.includes(searchTerm));
                          });
                      }
                  }
              }
              
              // Update filter buttons with filtered results count (before applying main filter)
              this.updateFilterButtonsWithFilteredCount(albums);
              
              // Apply main filter (Owned/Want/All) to the filtered results
              if (this.currentFilter !== 'all') {
                  albums = albums.filter(album => {
                      if (this.currentFilter === 'owned') {
                          return album.is_owned == 1;
                      } else if (this.currentFilter === 'wanted') {
                          return album.want_to_own == 1;
                      }
                      return true;
                  });
              }
              
              // Apply current sort to albums
              try {
                  const sortedAlbums = this.sortAlbums(albums);
                  this.renderAlbums(sortedAlbums);
              } catch (error) {
                  console.error('Sorting error:', error);
                  // Fallback to unsorted albums if sorting fails
                  this.renderAlbums(albums);
              }
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
                  <td colspan="6" class="empty-state">
                      <h3>No albums found</h3>
                      <p>Try adjusting your search or filter criteria</p>
                  </td>
              </tr>
          `;
          return;
      }
      
      tbody.innerHTML = albums.map(album => `
          <tr data-id="${album.id}" data-artist-type="${album.artist_type || ''}" data-label="${this.escapeHtml(album.label || '')}" data-format="${encodeURIComponent(album.format || '')}" data-producer="${this.escapeHtml(album.producer || '')}" data-year="${album.year || ''}">
              <td class="cover-cell">
                  ${album.cover_url ? 
                      `<img data-src="${album.cover_url}" data-medium="${album.cover_url_medium || album.cover_url}" data-large="${album.cover_url_large || album.cover_url}" class="album-cover lazy" alt="Album cover" data-artist="${this.escapeHtml(album.artist_name)}" data-album="${this.escapeHtml(album.album_name)}" data-year="${album.release_year || ''}" data-cover="${album.cover_url_large || album.cover_url}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" onload="this.classList.add('loaded')" width="60" height="60">
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
                      <div class="artist-name">
                          <a href="#" class="artist-link" data-artist="${this.escapeHtml(album.artist_name)}">
                              ${this.escapeHtml(album.artist_name)}
                          </a>
                      </div>
                      <div class="album-name">
                          <a href="#" class="album-link" data-artist="${this.escapeHtml(album.artist_name)}" data-album="${this.escapeHtml(album.album_name)}" data-year="${album.release_year || ''}">
                              ${this.escapeHtml(album.album_name)}
                          </a>
                      </div>
                      <div class="mobile-year">${album.release_year ? `<button type="button" class="year-link" data-year="${album.release_year}"><span class="year-badge">${album.release_year}</span></button>` : '<span class="year-badge">-</span>'}</div>
                      <div class="mobile-actions">
                          <button class="btn-edit" data-id="${album.id}">
                              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Edit" style="vertical-align: text-top;">
                                <title>edit</title>
                                <path d="M4 20h4l10.5-10.5a2 2 0 0 0 0-2.8l-1.2-1.2a2 2 0 0 0-2.8 0L4 16v4z" />
                                <path d="M13.5 6.5l4 4" />
                              </svg>
                              Edit
                          </button>
                          <button class="btn-delete" data-id="${album.id}">
                              &times; 
                              Delete
                          </button>
                      </div>
                  </div>
              </td>
              <td>
                  ${album.release_year ? `<button type="button" class="year-link" data-year="${album.release_year}"><span class="year-badge">${album.release_year}</span></button>` : '<span class="year-badge">-</span>'}
              </td>
              <td>
                  ${album.is_owned ? '<span class="checkmark">✓</span>' : '<span class="checkmark">&nbsp;</span>'}
              </td>
              <td>
                  ${album.want_to_own ? '<span class="checkmark">✓</span>' : '<span class="checkmark">&nbsp;</span>'}
              </td>
              <td>
                  <div class="action-buttons">
                      <button class="btn-edit" data-id="${album.id}">
                          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="Edit" style="vertical-align: text-top;">
                            <title>edit</title>
                            <path d="M4 20h4l10.5-10.5a2 2 0 0 0 0-2.8l-1.2-1.2a2 2 0 0 0-2.8 0L4 16v4z" />
                            <path d="M13.5 6.5l4 4" />
                          </svg>
                          Edit
                      </button>
                      <button class="btn-delete" data-id="${album.id}">
                          &times; 
                          Delete
                      </button>
                  </div>
              </td>
          </tr>
      `).join('');
      
      // Initialize lazy loading after rendering
      this.initLazyLoading();
      
      // Update UI to show/hide edit/delete buttons based on authentication
      this.updateAuthUI();
      
      // Master years are now fetched only when adding/editing albums, not for table display
  }
  

  
  async fetchMasterYearForSelection(releaseId, yearInput, fallbackYear = null) {
      try {
          // Fetch master year using lightweight endpoint
          const response = await this.fetchWithCache(`api/music_api.php?action=master_year&release_id=${releaseId}`);
          const data = await response.json();
          
          if (data.success && data.data && data.data.master_year) {
              // Update the year input with master year
              if (yearInput) {
                  yearInput.value = data.data.master_year;
              }
          } else if (fallbackYear) {
              // If no master year available, use the fallback year (specific release year)
              if (yearInput) {
                  yearInput.value = fallbackYear;
              }
          }
      } catch (error) {
          // If there's an error, use the fallback year if available
          if (fallbackYear && yearInput) {
              yearInput.value = fallbackYear;
          }
      }
      
      // Update Save button state after setting the year value
      this.updateSaveButtonState();
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
      
      // Find the album data from the DOM
      const row = document.querySelector(`tr[data-id="${id}"]`);
      if (!row) {
          this.showMessage('Album not found', 'error');
          return;
      }
      
      // Extract album data from the row content
      const artistNameDiv = row.querySelector('.artist-name');
      const albumNameDiv = row.querySelector('.album-name');
      const yearBadge = row.querySelector('.year-badge');
      const coverImg = row.querySelector('.album-cover');
      
      const album = {
          id: parseInt(id),
          artist_name: artistNameDiv ? artistNameDiv.textContent.trim() : '',
          album_name: albumNameDiv ? albumNameDiv.textContent.trim() : '',
          release_year: row.dataset.year || (yearBadge ? yearBadge.textContent.trim() : ''),
          artist_type: row.dataset.artistType || '',
          label: row.dataset.label || '',
          format: row.dataset.format || '',
          producer: row.dataset.producer || '',
          cover_url: coverImg ? coverImg.dataset.src || coverImg.src : ''
      };
      
      this.showDeleteConfirmationModal(album);
  }
  
  showDeleteConfirmationModal(album) {
      // Create modal if it doesn't exist
      let modal = document.getElementById('deleteConfirmationModal');
      if (!modal) {
          modal = document.createElement('div');
          modal.id = 'deleteConfirmationModal';
          modal.className = 'modal';
          modal.innerHTML = `
              <div class="modal-content">
                  <span class="close" id="deleteModalClose">&times;</span>
                  <h2>Delete Album</h2>
                  <p>Are you sure you want to delete this album?</p>
                  <div class="album-info">
                      <div class="album-details">
                          ${album.cover_url ? `<img src="${album.cover_url}" alt="Album cover" class="delete-modal-cover">` : ''}
                          <div class="album-text">
                              <div class="artist-name">${this.escapeHtml(album.artist_name)}</div>
                              <div class="album-name">${this.escapeHtml(album.album_name)}</div>
                              ${album.release_year ? `<div class="year">${album.release_year}</div>` : ''}
                          </div>
                      </div>
                  </div>
                  <p class="warning">This action cannot be undone.</p>
                  <div class="form-buttons">
                      <button type="button" class="btn-cancel" id="deleteCancelBtn">Cancel</button>
                      <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Delete</button>
                  </div>
              </div>
          `;
          document.body.appendChild(modal);
          
          // Add event listeners
          document.getElementById('deleteModalClose').addEventListener('click', () => {
              modal.style.display = 'none';
          });
          
          document.getElementById('deleteCancelBtn').addEventListener('click', () => {
              modal.style.display = 'none';
          });
          
          document.getElementById('deleteConfirmBtn').addEventListener('click', () => {
              this.confirmDeleteAlbum(album.id);
              modal.style.display = 'none';
          });
          
          // Close modal when clicking outside
          modal.addEventListener('click', (e) => {
              if (e.target === modal) {
                  modal.style.display = 'none';
              }
          });
      }
      
      // Update album info
      const albumInfo = modal.querySelector('.album-info');
      albumInfo.innerHTML = `
          <div class="album-details">
              ${album.cover_url ? `<img src="${album.cover_url}" alt="Album cover" class="delete-modal-cover">` : ''}
              <div class="album-text">
                  <div class="artist-name">${this.escapeHtml(album.artist_name)}</div>
                  <div class="album-name">${this.escapeHtml(album.album_name)}</div>
                  ${album.release_year ? `<div class="year">${album.release_year}</div>` : ''}
              </div>
          </div>
      `;
      
      // Show modal
      modal.style.display = 'block';
  }
  
  async confirmDeleteAlbum(id) {
      try {
          const controller = new AbortController();
          const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
          
          const response = await fetch('api/music_api.php?action=delete', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({ id: id }),
              signal: controller.signal
          });
          
          clearTimeout(timeoutId);
          
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
          if (error.name === 'AbortError') {
              this.showMessage('Delete request timed out. Please try again.', 'error');
          } else {
              this.showMessage('Error deleting album', 'error');
          }
      }
  }

  showModal(album = null) {
      const modal = document.getElementById('albumModal');
      const form = document.getElementById('albumForm');
      const title = document.querySelector('#albumModal h2');
      const viewRecordBtn = document.getElementById('viewRecordBtn');
      
      // Reset form
      form.reset();
      
      // Clear any previous modal messages
      this.hideModalMessage();
      
      // Set modal class based on whether we're adding or editing
      modal.classList.remove('add-album', 'edit-album');
      if (album) {
          modal.classList.add('edit-album');
      } else {
          modal.classList.add('add-album');
      }
      
      if (album) {
          title.textContent = 'Edit Album';
          document.getElementById('artistName').value = album.artist_name;
          document.getElementById('albumName').value = album.album_name;
          document.getElementById('releaseYear').value = album.release_year || '';
          document.getElementById('label').value = album.label || '';
          document.getElementById('producer').value = album.producer || '';
          const formatInput = document.getElementById('albumFormat');
          formatInput.value = album.format || '';
          formatInput.readOnly = false; // Allow editing when editing an album
          
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
          
          // Show View Record button for editing
          viewRecordBtn.style.display = 'block';
          this.editingAlbum = album;
          
          // Update Save button state for existing album data
          this.updateSaveButtonState();
      } else {
          title.textContent = 'Add New Album';
          this.editingAlbum = null;
          
          // Hide View Record button for new albums
          viewRecordBtn.style.display = 'none';
          
          // Clear cover art data for new albums
          this.selectedCoverUrl = null;
          this.selectedDiscogsReleaseId = null;
          
          // Set format field to readonly for new albums
          const formatInput = document.getElementById('albumFormat');
          formatInput.value = '';
          formatInput.readOnly = true;
          
          // Update album input state for new album
          this.updateAlbumInputState();
      }
      
      modal.style.display = 'block';
      
      // Initially disable the Save Album button until hidden fields are populated
      this.updateSaveButtonState();
      
      // Focus on the artist input field
      document.getElementById('artistName').focus();
  }
  
  hideModal() {
      const modal = document.getElementById('albumModal');
      modal.style.display = 'none';
      // Clear modal classes
      modal.classList.remove('add-album', 'edit-album');
      this.editingAlbum = null;
      this.selectedCoverUrl = null;
      this.selectedDiscogsReleaseId = null;
      this.hideModalMessage();
  }
  
  async showViewRecordModal() {
      if (!this.editingAlbum) {
          return;
      }
      
      const modal = document.getElementById('viewRecordModal');
      const recordData = document.getElementById('recordData');
      
      try {
          // Fetch the complete album data from the API
          const response = await this.fetchWithCache(`api/music_api.php?action=album&id=${this.editingAlbum.id}`);
          const data = await response.json();
          
          if (data.success && data.data) {
              // Store the original data for editing
              this.originalAlbumData = data.data;
              
              // Format the complete album data as JSON
              const formattedData = JSON.stringify(data.data, null, 2);
              recordData.textContent = formattedData;
          } else {
              recordData.textContent = 'Error loading album data: ' + (data.message || 'Unknown error');
          }
      } catch (error) {
          recordData.textContent = 'Error loading album data: ' + error.message;
      }
      
      modal.style.display = 'block';
  }
  
  hideViewRecordModal() {
      const modal = document.getElementById('viewRecordModal');
      modal.style.display = 'none';
      this.cancelRecordEditing(); // Reset edit state when closing
      
      // Also close the main album modal
      this.hideModal();
  }
  
  enableRecordEditing() {
      const recordData = document.getElementById('recordData');
      const editBtn = document.getElementById('editRecordBtn');
      const saveBtn = document.getElementById('saveRecordBtn');
      const cancelBtn = document.getElementById('cancelEditBtn');
      const editWarning = document.getElementById('editWarning');
      const editError = document.getElementById('editError');
      
      // Store original data for cancel functionality
      this.originalRecordData = recordData.textContent;
      
      // Create protected JSON editor
      this.createProtectedJsonEditor();
      
      // Show/hide buttons
      editBtn.style.display = 'none';
      saveBtn.style.display = 'block';
      cancelBtn.style.display = 'block';
      
      // Show warning and hide any previous errors
      editWarning.style.display = 'block';
      editError.style.display = 'none';
  }
  
  cancelRecordEditing() {
      const recordData = document.getElementById('recordData');
      const editBtn = document.getElementById('editRecordBtn');
      const saveBtn = document.getElementById('saveRecordBtn');
      const cancelBtn = document.getElementById('cancelEditBtn');
      const editWarning = document.getElementById('editWarning');
      const editError = document.getElementById('editError');
      
      // Restore original data
      if (this.originalRecordData) {
          recordData.textContent = this.originalRecordData;
      }
      
      // Disable editing
      recordData.contentEditable = false;
      
      // Show/hide buttons
      editBtn.style.display = 'block';
      saveBtn.style.display = 'none';
      cancelBtn.style.display = 'none';
      
      // Hide warning and error
      editWarning.style.display = 'none';
      editError.style.display = 'none';
      
      // Clear stored data
      this.originalRecordData = null;
      this.originalAlbumData = null;
  }
  
  createProtectedJsonEditor() {
      const recordData = document.getElementById('recordData');
      const albumData = this.originalAlbumData;
      
      if (!albumData) return;
      
      // Clear the container
      recordData.innerHTML = '';
      recordData.contentEditable = false;
      
             // Create the protected editor structure
       const editorContainer = document.createElement('div');
       editorContainer.className = 'protected-json-editor';
       editorContainer.style.whiteSpace = 'pre';
       editorContainer.style.lineHeight = '1.4';
       
       let indentLevel = 1;
      
      Object.entries(albumData).forEach(([key, value], index) => {
          const indent = '  '.repeat(indentLevel);
          const isLast = index === Object.keys(albumData).length - 1;
          const comma = isLast ? '' : ',';
          
                     // Create protected key (read-only)
           const keySpan = document.createElement('span');
           keySpan.textContent = `"${key}":`;
           keySpan.style.color = '#0066cc';
           keySpan.style.fontWeight = 'bold';
           keySpan.contentEditable = false;
          
                     // Create editable value (or read-only for ID)
           const valueInput = document.createElement('span');
           valueInput.textContent = typeof value === 'string' ? `"${value}"` : value;
           
           // Make ID field completely read-only
           if (key === 'id') {
               valueInput.contentEditable = false;
               valueInput.style.color = '#6c757d';
               valueInput.style.fontStyle = 'italic';
               valueInput.style.backgroundColor = '#f8f9fa';
               valueInput.style.padding = '2px 4px';
               valueInput.style.borderRadius = '3px';
               valueInput.style.border = '1px solid #dee2e6';
           } else {
               valueInput.contentEditable = true;
               valueInput.style.outline = 'none';
               valueInput.style.border = '1px solid transparent';
               valueInput.style.borderRadius = '2px';
               valueInput.style.padding = '2px 4px';
               valueInput.style.backgroundColor = '#ffffff';
               valueInput.style.color = '#212529';
               valueInput.style.fontWeight = '500';
               valueInput.style.display = 'inline';
           }
           
           valueInput.dataset.key = key;
           valueInput.dataset.originalValue = value;
          
                     // Add hover and focus effects only for editable fields
           if (key !== 'id') {
               valueInput.addEventListener('mouseenter', () => {
                   valueInput.style.backgroundColor = '#e3f2fd';
                   valueInput.style.borderColor = '#007bff';
               });
               
               valueInput.addEventListener('mouseleave', () => {
                   valueInput.style.backgroundColor = '#ffffff';
                   valueInput.style.borderColor = 'transparent';
               });
               
               // Add focus effect
               valueInput.addEventListener('focus', () => {
                   valueInput.style.backgroundColor = '#e3f2fd';
                   valueInput.style.borderColor = '#007bff';
                   valueInput.style.boxShadow = '0 0 0 2px rgba(0, 123, 255, 0.25)';
               });
               
               valueInput.addEventListener('blur', () => {
                   valueInput.style.backgroundColor = '#ffffff';
                   valueInput.style.borderColor = 'transparent';
                   valueInput.style.boxShadow = 'none';
               });
           }
          
          // Create line container
          const lineDiv = document.createElement('div');
          lineDiv.style.marginLeft = `${indentLevel * 20}px`;
          lineDiv.appendChild(keySpan);
          lineDiv.appendChild(valueInput);
          
          // Add comma if not last
          if (!isLast) {
              const commaSpan = document.createElement('span');
              commaSpan.textContent = ',';
              lineDiv.appendChild(commaSpan);
          }
          
                 editorContainer.appendChild(lineDiv);
       });
       
       recordData.appendChild(editorContainer);
      
      // Focus on first editable field
      const firstValueInput = recordData.querySelector('[contenteditable="true"]');
      if (firstValueInput) {
          firstValueInput.focus();
      }
  }
  
  collectDataFromProtectedEditor() {
      const recordData = document.getElementById('recordData');
      const albumData = { ...this.originalAlbumData };
      
      // Collect values from all editable fields
      const editableFields = recordData.querySelectorAll('[contenteditable="true"]');
      
      editableFields.forEach(field => {
          const key = field.dataset.key;
          const originalValue = field.dataset.originalValue;
          let newValue = field.textContent.trim();
          
          // Handle different data types
          if (typeof originalValue === 'string') {
              // Remove quotes if present
              if (newValue.startsWith('"') && newValue.endsWith('"')) {
                  newValue = newValue.slice(1, -1);
              }
              albumData[key] = newValue;
          } else if (typeof originalValue === 'number') {
              albumData[key] = parseFloat(newValue) || originalValue;
          } else if (typeof originalValue === 'boolean') {
              albumData[key] = newValue.toLowerCase() === 'true';
          } else if (originalValue === null) {
              albumData[key] = newValue === 'null' ? null : newValue;
          } else {
              albumData[key] = newValue;
          }
      });
      
      return albumData;
  }
  
  async saveRecordChanges() {
      const recordData = document.getElementById('recordData');
      const editBtn = document.getElementById('editRecordBtn');
      const saveBtn = document.getElementById('saveRecordBtn');
      const cancelBtn = document.getElementById('cancelEditBtn');
      
      try {
          // Collect data from protected editor
          const editedData = this.collectDataFromProtectedEditor();
          
          // Validate required fields
          if (!editedData.artist_name || !editedData.album_name) {
              throw new Error('Artist name and album name are required');
          }
          
          // Prevent ID changes to avoid conflicts
          if (editedData.id !== this.editingAlbum.id) {
              throw new Error('Cannot change the album ID. This field is protected to prevent data conflicts.');
          }
          
          // Send update to API
          const response = await fetch('api/music_api.php?action=update_raw', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(editedData)
          });
          
          const data = await response.json();
          
          if (data.success) {
              // Update the editing album data
              this.editingAlbum = editedData;
              
              // Restore normal view
              recordData.textContent = JSON.stringify(editedData, null, 2);
              recordData.contentEditable = false;
              
              // Show/hide buttons
              editBtn.style.display = 'block';
              saveBtn.style.display = 'none';
              cancelBtn.style.display = 'none';
              
              // Hide warning
              const editWarning = document.getElementById('editWarning');
              editWarning.style.display = 'none';
              
              // Clear stored data
              this.originalRecordData = null;
              
              // Show success message
              this.showMessage('Record updated successfully', 'success');
              
              // Refresh the album list to show changes
              this.loadAlbums();
              
              // Close both modals
              this.hideViewRecordModal();
          } else {
              throw new Error(data.message || 'Failed to update record');
          }
      } catch (error) {
          // Show error message in the modal
          const editError = document.getElementById('editError');
          editError.innerHTML = `<strong>❌ Error:</strong> ${error.message}`;
          editError.style.display = 'block';
          
          // Restore original data on error
          if (this.originalRecordData) {
              recordData.textContent = this.originalRecordData;
          }
      }
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
          format: formData.get('albumFormat'),
          label: formData.get('label'),
          producer: formData.get('producer'),
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
              // Log debug information if available
              
              
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
      messageEl.classList.add('show');
      
      setTimeout(() => {
          messageEl.classList.remove('show');
      }, 5000);
  }

  async showCoverModal(artistName, albumName, releaseYear, coverUrl, albumId = null) {
      const modal = document.getElementById('coverModal');
      const image = document.getElementById('coverModalImage');
      const info = document.getElementById('coverModalInfo');
      
      image.src = coverUrl;
      image.alt = `${albumName} by ${artistName}`;
      
      // Show initial info with release year
      info.innerHTML = `
          <div class="album-info">
            <a href="javascript:void(0)" class="artist-link" data-artist="${this.escapeHtml(artistName)}">${this.escapeHtml(artistName)}</a> • 
            <a href="javascript:void(0)" class="album-link" data-artist="${this.escapeHtml(artistName)}" data-album="${this.escapeHtml(albumName)}" data-year="${releaseYear || ''}" data-album-id="${albumId || ''}">${this.escapeHtml(albumName)}</a> • 
          ${releaseYear ? `<a href="javascript:void(0)" class="year-link" data-year="${releaseYear}">${releaseYear}</a>` : ''}
      `;
      
      // Add event listener for the album link
      const albumLink = info.querySelector('.album-link');
      if (albumLink) {
          albumLink.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const artist = albumLink.dataset.artist;
              const album = albumLink.dataset.album;
              const year = albumLink.dataset.year;
              const albumId = albumLink.dataset.albumId;
              this.showTracklist(artist, album, year, albumId);
              this.hideCoverModal();
          });
      }
      
      // Add event listener for the artist link
      const artistLink = info.querySelector('.artist-link');
      if (artistLink) {
          artistLink.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const artist = artistLink.dataset.artist;
              this.filterByArtist(artist);
              this.hideCoverModal();
          });
      }
      
      // Add event listener for the year link
      const yearLink = info.querySelector('.year-link');
      if (yearLink) {
          yearLink.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const year = yearLink.dataset.year;
              this.filterByYear(year);
              this.hideCoverModal();
          });
      }
      
      modal.style.display = 'block';
      
      // Fetch master release year if we have an album ID
      if (albumId) {
          try {
              // Fetch detailed album info to get master release year
              const params = new URLSearchParams({
                  artist: artistName,
                  album: albumName
              });
              
              if (releaseYear) {
                  params.append('year', releaseYear);
              }
              
              params.append('album_id', albumId);
              
              const response = await this.fetchWithCache(`api/tracklist_api.php?${params}`);
              const data = await response.json();
              
              if (data.success && data.data && data.data.master_year) {
                  // Update the year to show master release year
                  const yearElement = info.querySelector('.year-link');
                  if (yearElement) {
                      yearElement.textContent = data.data.master_year;
                      yearElement.dataset.year = data.data.master_year;
                  }
              }
          } catch (error) {
              // If there's an error fetching master year, keep the original year
          }
      }
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
      
      // Extract album data from table row if available
      let labelData = '';
      let formatData = '';
      let producerData = '';
      let yearData = '';
      
      if (albumId) {
          const albumRow = document.querySelector(`tr[data-id="${albumId}"]`);
          if (albumRow) {
              const label = albumRow.dataset.label;
              const format = decodeURIComponent(albumRow.dataset.format);
              const producer = albumRow.dataset.producer;
              const year = albumRow.dataset.year;
              
              // Helper function to format comma-separated values with Unicode decoding
              const formatCommaSeparated = (text) => {
                  if (!text) return text;
                  // Fix Unicode escape sequences like \u2153 to ⅓
                  const decodedText = text.replace(/\\u([0-9a-fA-F]{4})/g, (match, code) => {
                      return String.fromCharCode(parseInt(code, 16));
                  });
                  return decodedText.split(',').map(item => item.trim()).join(', ');
              };
              
              if (label) {
                  labelData = `<span><a href="javascript:void(0)" class="tracklist-label-link" data-label="${this.escapeHtml(label)}">${this.cleanDiscogsNumbering(label)}</a></span>`;
              } else {
                  labelData = '<span class="loading-placeholder">Loading...</span>';
              }
              
              if (format) {
                  formatData = `<a href="javascript:void(0)" class="tracklist-format-link" data-format-encoded="${btoa(encodeURIComponent(format))}">${formatCommaSeparated(format)}</a>`;
              } else {
                  formatData = '<span class="loading-placeholder">Loading...</span>';
              }
              
              if (producer) {
                  producerData = `<a href="javascript:void(0)" class="tracklist-producer-link" data-producer-encoded="${btoa(encodeURIComponent(producer))}">${formatCommaSeparated(this.cleanDiscogsNumbering(producer))}</a>`;
              } else {
                  producerData = null; // Don't show producer field if no local data
              }
              
              if (year) {
                  yearData = `<span>${year}</span>`;
              } else {
                  yearData = '<span class="loading-placeholder">Loading...</span>';
              }
          }
      } else {
          // Fallback to loading placeholders if no albumId
          labelData = '<span class="loading-placeholder">Loading...</span>';
          formatData = '<span class="loading-placeholder">Loading...</span>';
          producerData = null; // Don't show producer field if no albumId
          yearData = '<span class="loading-placeholder">Loading...</span>';
      }
      
      // Show album info with local data where available
      let infoHtml = `
          <div><strong>Artist:</strong> <span><a href="javascript:void(0)" class="tracklist-artist-link" data-artist="${this.escapeHtml(artistName)}">${this.escapeHtml(artistName)}</a></span></div>
          ${releaseYear ? `<div><strong>Year:</strong> <span><a href="javascript:void(0)" class="tracklist-year-link" data-year="${releaseYear}">${releaseYear}</a></span></div>` : ''}
      `;
      
      // Add elements based on toggle settings
      if (this.shouldShowLabel()) {
          infoHtml += `<div><strong>Label:</strong> ${labelData}</div>`;
      }
      if (this.shouldShowFormat()) {
          infoHtml += `<div><strong>Format:</strong> ${formatData}</div>`;
      }
      if (this.shouldShowProducer() && producerData) {
          infoHtml += `<div><strong>Producer:</strong> ${producerData}</div>`;
      }
      if (this.shouldShowReleased()) {
          infoHtml += `<div><strong>Released:</strong> ${yearData}</div>`;
      }
      if (this.shouldShowRating()) {
          infoHtml += `<div><strong>Rating:</strong> <span class="loading-placeholder">Loading...</span></div>`;
      }
      
      info.innerHTML = infoHtml;
      
      // Add event listeners for the basic info links
      this.addTracklistFilterEventListeners(info);
      
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
              }
          }
      }
      
      // If we found an existing image, use it immediately
      if (existingImage) {
          coverImage.src = existingImage;
          coverImage.style.display = 'block';
          noCover.style.display = 'none';
          coverImage.classList.add('loaded');
      }
      
      // Show loading state
      tracks.innerHTML = '<div class="tracklist-loading">Loading tracklist...</div>';
      modal.style.display = 'block';
      
      // Store album data on the modal for cover image click functionality
      modal.dataset.artistName = artistName;
      modal.dataset.albumName = albumName;
      modal.dataset.releaseYear = releaseYear || '';
      modal.dataset.albumId = albumId || '';
      
      // Show/hide edit button based on authentication status
      const editBtn = document.getElementById('tracklistEditBtn');
      if (editBtn) {
          if (this.isAuthenticated && albumId) {
              editBtn.style.display = 'flex';
              // Store album data for editing
              editBtn.dataset.albumId = albumId;
              editBtn.dataset.artistName = artistName;
              editBtn.dataset.albumName = albumName;
              editBtn.dataset.releaseYear = releaseYear || '';
          } else {
              editBtn.style.display = 'none';
          }
      }
      
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
          
          // Use cache: 'no-cache' to ensure fresh data, especially after edits
          const response = await fetch(`api/tracklist_api.php?${params}`, {
              cache: 'no-cache',
              headers: {
                  'Content-Type': 'application/json'
              }
          });
          const data = await response.json();
          
          if (data.success && data.data) {
                  const albumData = data.data;
                  

              
              // Format master release date if available
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
                              // Show only year for dates with day "00" or December 31st
                              formattedReleased = year.toString();
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
                  const reviewText = albumData.rating_count === 1 ? 'review' : 'reviews';
                  if (albumData.has_reviews_with_content) {
                      reviewsDisplay = `<span class="rating-count">(based on <a href="${albumData.discogs_url}#release-reviews" target="_blank" rel="noopener noreferrer" style="padding-left: .25em;">${albumData.rating_count} ${reviewText}</a>)</span>`;
                  } else {
                      reviewsDisplay = `<span class="rating-count">(based on ${albumData.rating_count} ${reviewText})</span>`;
                  }
              }

              // Helper function to remove trailing numbers in parentheses
              const removeTrailingNumbers = (text) => {
                  if (!text) return text;
                  return text.replace(/\s*\(\d+\)\s*$/, '');
              };
              
              // Helper function to format comma-separated values with spaces
              const formatCommaSeparated = (text) => {
                  if (!text) return text;
                  // Fix Unicode escape sequences like \u2153 to ⅓
                  const decodedText = text.replace(/\\u([0-9a-fA-F]{4})/g, (match, code) => {
                      return String.fromCharCode(parseInt(code, 16));
                  });
                  return decodedText.split(',').map(item => item.trim()).join(', ');
              };


              
              // Update info with additional details
              let infoHtml = `
                  <div><strong>Artist:</strong> <span><a href="javascript:void(0)" class="tracklist-artist-link" data-artist="${this.escapeHtml(artistName)}">${this.escapeHtml(artistName)}</a></span></div>
                  ${formattedReleased ? `<div><strong>Year:</strong> <span><a href="javascript:void(0)" class="tracklist-year-link" data-year="${formattedReleased}">${formattedReleased}</a></span></div>` : ''}
              `;
              
              // Add elements based on toggle settings
              if (this.shouldShowLabel() && albumData.label) {
                  infoHtml += `<div><strong>Label:</strong> <span><a href="javascript:void(0)" class="tracklist-label-link" data-label="${this.escapeHtml(albumData.label)}">${this.cleanDiscogsNumbering(albumData.label)}</a></span></div>`;
              }
              if (this.shouldShowFormat() && albumData.format) {
                  infoHtml += `<div><strong>Format:</strong> <a href="javascript:void(0)" class="tracklist-format-link" data-format-encoded="${btoa(encodeURIComponent(albumData.format))}">${formatCommaSeparated(albumData.format)}</a></div>`;
              }
              if (this.shouldShowProducer() && albumData.producer) {
                  infoHtml += `<div><strong>Producer:</strong> <a href="javascript:void(0)" class="tracklist-producer-link" data-producer-encoded="${btoa(encodeURIComponent(albumData.producer))}">${formatCommaSeparated(this.cleanDiscogsNumbering(albumData.producer))}</a></div>`;
              }
              if (this.shouldShowReleased() && albumData.year) {
                  infoHtml += `<div><strong>Released:</strong> <span>${albumData.year}</span></div>`;
              }
              if (this.shouldShowRating() && albumData.rating) {
                  infoHtml += `<div><strong>Rating:</strong> <span class="rating-content">${albumData.rating}${this.generateStarRating(albumData.rating)}<br>${reviewsDisplay}</span></div>`;
              }
              
              info.innerHTML = infoHtml;
              

              
              // Add event listeners for the filter links
              this.addTracklistFilterEventListeners(info);
              
              // Display cover art from tracklist API response (only if we didn't find one in the table)
              if (!existingImage && albumData.cover_url) {
                  const coverUrl = albumData.cover_url_medium || albumData.cover_url;
                  
                  // Check if this is a cached image (image proxy URL)
                  const isCachedImage = coverUrl.includes('api/image_proxy.php');
                  
                  if (isCachedImage) {
                      // For cached images, don't show loading state - image should load instantly
                      coverImage.style.display = 'none';
                      noCover.style.display = 'none';
                      noCover.textContent = ''; // Clear any existing text
                  } else {
                      // For non-cached images, show loading state
                      noCover.textContent = 'Loading Cover...';
                      noCover.style.display = 'flex';
                      coverImage.style.display = 'none';
                  }
                  
                  // Set image source
                  coverImage.src = coverUrl;
                  
                  // Add a timeout to handle slow loading
                  const imageTimeout = setTimeout(() => {
                      if (coverImage.style.display === 'none') {
                          coverImage.style.display = 'none';
                          noCover.textContent = 'No Cover';
                          noCover.style.display = 'flex';
                      }
                  }, 10000); // 10 second timeout
                  
                  // Handle image load success
                  coverImage.onload = function() {
                      clearTimeout(imageTimeout);
                      coverImage.style.display = 'block';
                      noCover.style.display = 'none';
                      coverImage.classList.add('loaded');
                  };
                  
                  // Handle image load errors
                  coverImage.onerror = function() {
                      clearTimeout(imageTimeout);
                      coverImage.style.display = 'none';
                      noCover.textContent = 'No Cover';
                      noCover.style.display = 'flex';
                  };
              } else if (!existingImage && !albumData.cover_url) {
                  // No cover art available and no existing image found
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
                          ${albumData.tracklist.map(track => {
                              // Check if this is a side header (no position, no duration, no lyrics)
                              const isSideHeader = !track.position && !track.duration && !track.has_lyrics;
                              const trackItemClass = isSideHeader ? 'track-item track-item-side-header' : 'track-item';
                              
                              return `
                              <div class="${trackItemClass}">
                                  <span class="track-position">${track.position}</span>
                                  <span class="track-title">${this.escapeHtml(track.title)}</span>
                                  <span class="track-duration">${track.duration || ''}</span>
                                  ${this.renderLyricsLinks(track)}
                              </div>
                              `;
                          }).join('')}
                      </div>
                      ${this.renderArtistWebsite(albumData.artist_website)}
                  `;
              } else {
                  tracks.innerHTML = '<div class="tracklist-error">No tracklist available for this album</div>';
              }
          } else {
              // Handle API errors gracefully - don't show technical error messages to users
              let errorMessage = 'Could not load tracklist';
              if (data.message && !data.message.includes('Discogs API request failed')) {
                  // Only show user-friendly messages, not technical API errors
                  errorMessage = data.message;
              }
              tracks.innerHTML = `<div class="tracklist-error">${errorMessage}</div>`;
              discogsLink.href = `https://www.discogs.com/search/?q=${encodeURIComponent(artistName + ' ' + albumName)}&type=release`;
          }
      } catch (error) {
          tracks.innerHTML = '<div class="tracklist-error">Could not load tracklist. Please try again later.</div>';
          discogsLink.href = `https://www.discogs.com/search/?q=${encodeURIComponent(artistName + ' ' + albumName)}&type=release`;
      }
  }
  
  addTracklistFilterEventListeners(info) {
      // Add event listener for artist link
      const artistLink = info.querySelector('.tracklist-artist-link');
      if (artistLink) {
          artistLink.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const artist = artistLink.dataset.artist;
              this.filterByArtist(artist);
              this.hideTracklistModal();
          });
      }
      
      // Add event listener for year links
      const yearLinks = info.querySelectorAll('.tracklist-year-link');
      yearLinks.forEach(yearLink => {
          yearLink.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const year = yearLink.dataset.year;
              this.filterByYear(year);
              this.hideTracklistModal();
          });
      });
      
      // Add event listener for label link
      const labelLink = info.querySelector('.tracklist-label-link');
      if (labelLink) {
          labelLink.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              const label = labelLink.dataset.label;
              this.filterByLabel(label);
              this.hideTracklistModal();
          });
      }
      
      // Add event listener for format link - split by comma and create individual format links
      const formatLink = info.querySelector('.tracklist-format-link');
      if (formatLink) {
          const formatTextEncoded = formatLink.dataset.formatEncoded;
          const formatText = decodeURIComponent(atob(formatTextEncoded)); // Decode from base64 and URI component
          
          // Handle escaped quotes and clean up format types
          const formatTypes = formatText.split(',').map(f => {
              let cleaned = f.trim();
              // Fix escaped quotes like 7\" to 7"
              cleaned = cleaned.replace(/\\"/g, '"');
              // Fix Unicode escape sequences like \u2153 to ⅓
              cleaned = cleaned.replace(/\\u([0-9a-fA-F]{4})/g, (match, code) => {
                  return String.fromCharCode(parseInt(code, 16));
              });
              return cleaned;
            });
          
          // Replace the single format link with individual format type links wrapped in a span
          formatLink.outerHTML = `<span class="format-links-container">${formatTypes.map(formatType => 
              `<a href="javascript:void(0)" class="tracklist-format-type-link" data-format-encoded="${btoa(encodeURIComponent(formatType))}">${this.escapeHtml(formatType)}</a>`
          ).join(', ')}</span>`;
          
          // Add event listeners for each individual format type link
          const formatTypeLinks = info.querySelectorAll('.tracklist-format-type-link');
          formatTypeLinks.forEach(formatTypeLink => {
              formatTypeLink.addEventListener('click', (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  const formatTypeEncoded = formatTypeLink.dataset.formatEncoded;
                  const formatType = decodeURIComponent(atob(formatTypeEncoded)); // Decode from base64 and URI component
                  this.filterByFormat(formatType);
                  this.hideTracklistModal();
              });
          });
      }
      
      // Add event listener for producer link - split by comma and create individual producer links
      const producerLink = info.querySelector('.tracklist-producer-link');
      if (producerLink) {
          const producerTextEncoded = producerLink.dataset.producerEncoded;
          const producerText = decodeURIComponent(atob(producerTextEncoded)); // Decode from base64 and URI component
          
          // Handle comma-separated producers
          const producers = producerText.split(',').map(p => p.trim());
          
          // Replace the single producer link with individual producer links wrapped in a span
          producerLink.outerHTML = `<span class="producer-links-container">${producers.map(producer => {
              const cleanProducer = this.cleanDiscogsNumbering(producer);
              return `<a href="javascript:void(0)" class="tracklist-producer-type-link" data-producer="${this.escapeHtml(producer)}">${this.escapeHtml(cleanProducer)}</a>`;
          }).join(', ')}</span>`;
          
          // Add event listeners for each individual producer link
          const producerTypeLinks = info.querySelectorAll('.tracklist-producer-type-link');
          producerTypeLinks.forEach(producerTypeLink => {
              producerTypeLink.addEventListener('click', (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  const producer = producerTypeLink.dataset.producer;
                  this.filterByProducer(producer);
                  this.hideTracklistModal();
              });
          });
      }
  }
  
  hideTracklistModal() {
      document.getElementById('tracklistModal').style.display = 'none';
  }
  
  handleTracklistEdit() {
      const editBtn = document.getElementById('tracklistEditBtn');
      if (!editBtn || !editBtn.dataset.albumId) {
          return;
      }
      
      // Get album ID from the button's dataset
      const albumId = editBtn.dataset.albumId;
      
      // Close tracklist modal
      this.hideTracklistModal();
      
      // Open edit modal with the album ID
      this.editAlbum(parseInt(albumId));
  }
  
  showLoginModal() {
      document.getElementById('loginModal').style.display = 'block';
      document.getElementById('password').focus();
      document.getElementById('loginMessage').style.display = 'none';
  }

  showStatsModal() {
      // Only reload stats if we don't have recent data or if albums have changed
      // The stats are already loaded on page load, so we can just show the modal
      // Stats will be refreshed when albums are added/deleted via loadStats() calls
      document.getElementById('statsModal').style.display = 'block';
  }

  hideStatsModal() {
      document.getElementById('statsModal').style.display = 'none';
  }
  
  showResetPasswordModal() {
      // Check if user is authenticated first (skip check on setup page)
      if (!this.isAuthenticated && !document.body.classList.contains('setup-page')) {
          this.showLoginModal();
          return;
      }
      

      
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
  
  // Initialize demo manager if on demo site
  initDemoManager() {
      // Check if this is a demo site
      const isDemoSite = window.location.hostname.includes('railway.app') || 
                        window.location.hostname.includes('herokuapp.com') ||
                        window.location.hostname.includes('netlify.app') ||
                        window.location.hostname.includes('vercel.app');
      
      if (isDemoSite && window.DemoManager) {
          this.demoManager = new window.DemoManager(this);
      }
  }

  // Handle demo reset (delegates to demo manager)
  async handleDemoReset() {
      if (this.demoManager) {
          this.demoManager.handleDemoReset();
      }
  }
  
  // Notification system
  startNotificationPolling() {
      // Generate unique browser identifier for localStorage key
      if (!localStorage.getItem('browserId')) {
          localStorage.setItem('browserId', 'browser_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));
      }
      this.browserId = localStorage.getItem('browserId');
      
      // Poll for notifications every 10 seconds
      setInterval(() => {
          this.checkForNotifications();
      }, 10000);
      
      // Check immediately on page load
      setTimeout(() => {
          this.checkForNotifications();
      }, 2000);
  }
  
  async checkForNotifications() {
      try {
          const response = await fetch('api/music_api.php?action=get_notifications');
          if (response.ok) {
              const result = await response.json();
              if (result.success && result.notifications && result.notifications.length > 0) {
                  this.showNotifications(result.notifications);
              }
          }
      } catch (error) {
          // Silently fail - notifications are not critical
      }
  }
  
  showNotifications(notifications) {
      // Get shown notifications from localStorage (per-browser storage)
      const notificationKey = 'shownNotifications_' + this.browserId;
      const shownNotifications = JSON.parse(localStorage.getItem(notificationKey) || '[]');
      
      // Clean up old notification IDs (keep only last 50)
      if (shownNotifications.length > 50) {
          shownNotifications.splice(0, shownNotifications.length - 50);
          localStorage.setItem(notificationKey, JSON.stringify(shownNotifications));
      }
      
      // Get current notification IDs from the server
      const currentNotificationIds = notifications.map(n => n.id);
      
      // Remove any existing notifications that are no longer on the server
      const existingNotifications = document.querySelectorAll('.notification-toast');
      existingNotifications.forEach(notificationElement => {
          const notificationId = parseInt(notificationElement.dataset.notificationId);
          if (!currentNotificationIds.includes(notificationId)) {
              notificationElement.remove();
          }
      });
      
      // Show each notification that hasn't been shown yet
      notifications.forEach(notification => {
          if (!shownNotifications.includes(notification.id)) {
              // Mark as shown in localStorage
              shownNotifications.push(notification.id);
              localStorage.setItem(notificationKey, JSON.stringify(shownNotifications));
              
              this.showNotificationToast(notification);
          }
      });
  }
  
  showNotificationToast(notification) {
      // All notifications are demo_reset type - delegate to demo manager
      if (this.demoManager) {
          this.demoManager.handleDemoResetNotification(notification);
      }
  }
  
  
  async showSetupModal() {
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
      
      // Set up theme customization
      await this.setupThemeCustomization();
      
      // Focus on first field
      document.getElementById('setup_discogs_api_key').focus();
  }
  
  hideSetupModal() {
      document.getElementById('setupModal').style.display = 'none';
      document.getElementById('setupForm').reset();
      document.getElementById('setupMessage').style.display = 'none';
  }
  
  // Initialize setup page functionality
  async initSetupPage() {
      // Load setup status
      this.loadSetupStatus();
      
      // Load display mode
      await this.loadDisplayMode();
      
      // Set up theme customization
      await this.setupThemeCustomization();
      
      // Set up event listeners for setup page
      this.setupPageEventListeners();
  }
  
  // Set up event listeners for the setup page
  setupPageEventListeners() {
      // Setup form submission
      const setupForm = document.getElementById('setupForm');
      if (setupForm) {
          setupForm.addEventListener('submit', (e) => {
              e.preventDefault();
              this.handleSetupConfig(e);
          });
      }
      
      // Setup password button
      const setupPasswordBtn = document.getElementById('setupPasswordBtn');
      if (setupPasswordBtn) {
          setupPasswordBtn.addEventListener('click', () => {
              this.handlePasswordSetup();
          });
      }
      
      // Reset Password modal password toggle functionality
      const resetPasswordFields = [
          { inputId: 'reset_current_password', buttonId: 'toggleResetCurrentPassword' },
          { inputId: 'reset_new_password', buttonId: 'toggleResetNewPassword' },
          { inputId: 'reset_confirm_password', buttonId: 'toggleResetConfirmPassword' }
      ];

      resetPasswordFields.forEach(field => {
          const toggleBtn = document.getElementById(field.buttonId);
          const passwordInput = document.getElementById(field.inputId);
          
          if (toggleBtn && passwordInput) {
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
          }
      });
      
      // Reset Password form submission
      const resetPasswordForm = document.getElementById('resetPasswordForm');
      if (resetPasswordForm) {
          resetPasswordForm.addEventListener('submit', (e) => {
              e.preventDefault();
              this.handleResetPassword(e);
          });
      }
      
      // Reset Password modal cancel button
      const resetPasswordModalCancel = document.querySelector('#resetPasswordModal .btn-cancel');
      if (resetPasswordModalCancel) {
          resetPasswordModalCancel.addEventListener('click', () => {
              this.hideResetPasswordModal();
          });
      }
      
      // Reset Password modal close button handling
      const resetPasswordModalClose = document.querySelector('#resetPasswordModal .close');
      if (resetPasswordModalClose) {
          resetPasswordModalClose.addEventListener('click', () => {
              this.hideResetPasswordModal();
          });
      }
      
      // Display mode buttons
      const saveDisplayModeBtn = document.getElementById('saveDisplayModeBtn');
      if (saveDisplayModeBtn) {
          saveDisplayModeBtn.addEventListener('click', () => {
              this.saveDisplayMode();
          });
      }
      
      // Theme buttons
      const saveThemeBtn = document.getElementById('saveThemeBtn');
      if (saveThemeBtn) {
          saveThemeBtn.addEventListener('click', () => {
              this.saveThemeColors();
          });
      }
      
      const resetThemeBtn = document.getElementById('resetThemeBtn');
      if (resetThemeBtn) {
          resetThemeBtn.addEventListener('click', () => {
              this.resetThemeColors();
          });
      }
      
      // Color picker synchronization
      this.setupColorPickerSync();
      
      // Tab functionality
      this.setupTabFunctionality();
      
      // Settings functionality
      this.setupSettingsFunctionality();
      
      // Stats display functionality
      this.setupStatsFunctionality();
  }
  
  // Handle password setup for setup page
  handlePasswordSetup() {
      // If we're on the setup page, always show reset password modal
      // (setup page is for initial configuration, so authentication not required)
      if (document.body.classList.contains('setup-page')) {
          this.showResetPasswordModal();
          return;
      }
      
      // For other pages, check authentication first
      if (!this.isAuthenticated) {
          window.location.href = 'index.php?login=required';
          return;
      }
      
      // Show reset password modal
      this.showResetPasswordModal();
  }
  
  // Setup tab functionality
  setupTabFunctionality() {
      const tabButtons = document.querySelectorAll('.tab-button');
      const tabPanels = document.querySelectorAll('.tab-panel');
      
      tabButtons.forEach(button => {
          button.addEventListener('click', () => {
              const targetTab = button.getAttribute('data-tab');
              
              // Remove active class from all buttons and panels
              tabButtons.forEach(btn => btn.classList.remove('active'));
              tabPanels.forEach(panel => panel.classList.remove('active'));
              
              // Add active class to clicked button and corresponding panel
              button.classList.add('active');
              const targetPanel = document.getElementById(targetTab);
              if (targetPanel) {
                  targetPanel.classList.add('active');
              }
          });
      });
  }
  
  // Setup color picker synchronization for setup page
  setupColorPickerSync() {
      const colorInputs = document.querySelectorAll('input[type="color"]');
      const hexInputs = document.querySelectorAll('input[type="text"][pattern*="hex"]');
      
      // Sync color inputs with hex inputs
      colorInputs.forEach(colorInput => {
          const hexInput = document.getElementById(colorInput.id + 'Hex');
          if (hexInput) {
              colorInput.addEventListener('input', () => {
                  hexInput.value = colorInput.value;
              });
              
              hexInput.addEventListener('input', () => {
                  if (/^#[0-9A-Fa-f]{6}$/.test(hexInput.value)) {
                      colorInput.value = hexInput.value;
                  }
              });
          }
      });
  }
  
  // Setup settings functionality
  setupSettingsFunctionality() {
      // Load saved settings
      this.loadSettings();
      
      // Setup lyrics toggle functionality
      this.setupLyricsToggle();
      
      // Save settings button
      const saveSettingsBtn = document.getElementById('saveSettingsBtn');
      if (saveSettingsBtn) {
          saveSettingsBtn.addEventListener('click', () => {
              this.saveSettings();
          });
      }
      
      // Reset settings button
      const resetSettingsBtn = document.getElementById('resetSettingsBtn');
      if (resetSettingsBtn) {
          resetSettingsBtn.addEventListener('click', () => {
              this.resetSettings();
          });
      }
      
      // Select All Artist Links button
      const selectAllArtistLinksBtn = document.getElementById('selectAllArtistLinks');
      if (selectAllArtistLinksBtn) {
          selectAllArtistLinksBtn.addEventListener('click', () => {
              this.selectAllArtistLinks();
          });
      }
      
      // Select None Artist Links button
      const selectNoneArtistLinksBtn = document.getElementById('selectNoneArtistLinks');
      if (selectNoneArtistLinksBtn) {
          selectNoneArtistLinksBtn.addEventListener('click', () => {
              this.selectNoneArtistLinks();
          });
      }
      
      // Select All Album Info button
      const selectAllAlbumInfoBtn = document.getElementById('selectAllAlbumInfo');
      if (selectAllAlbumInfoBtn) {
          selectAllAlbumInfoBtn.addEventListener('click', () => {
              this.selectAllAlbumInfo();
          });
      }
      
      // Select None Album Info button
      const selectNoneAlbumInfoBtn = document.getElementById('selectNoneAlbumInfo');
      if (selectNoneAlbumInfoBtn) {
          selectNoneAlbumInfoBtn.addEventListener('click', () => {
              this.selectNoneAlbumInfo();
          });
      }
      // App settings: load current title
      this.loadAppSettings();
      const saveAppSettingsBtn = document.getElementById('saveAppSettingsBtn');
      if (saveAppSettingsBtn) {
          saveAppSettingsBtn.addEventListener('click', () => {
              this.saveAppSettings();
          });
      }
  }

  async loadAppSettings() {
      try {
          const res = await fetch('api/theme_api.php?type=app_settings');
          const data = await res.json();
      if (data.success && data.data) {
              const input = document.getElementById('appTitleInput');
              const desc = document.getElementById('appDescriptionInput');
              const meta = document.getElementById('appMetaDescriptionInput');
              const startUrl = document.getElementById('appStartUrlInput');
              if (input) {
                  input.value = data.data.title || 'Music Collection';
              }
              if (desc) {
                  desc.value = data.data.description || '';
              }
              if (meta) {
              meta.value = (data.data.meta_description && data.data.meta_description.trim() !== '')
                ? data.data.meta_description
                : 'Track your vinyl music collection with album covers, tracklists, and Discogs integration. Organize your music library by artist, album, release year, and ownership status.';
              }
              if (startUrl) {
                  startUrl.value = data.data.start_url || '/';
              }
          }
      } catch (_) {}
  }

  async saveAppSettings() {
      const input = document.getElementById('appTitleInput');
      const desc = document.getElementById('appDescriptionInput');
      const meta = document.getElementById('appMetaDescriptionInput');
      const startUrl = document.getElementById('appStartUrlInput');
      const title = (input?.value || '').trim();
      const description = (desc?.value || '').trim();
      const meta_description = (meta?.value || '').trim();
      let start_url = (startUrl?.value || '').trim();
      if (start_url !== '') {
          if (!start_url.startsWith('/')) start_url = '/' + start_url;
          if (!start_url.endsWith('/')) start_url += '/';
      }
      if (!title) {
          this.showMessage('Title cannot be empty', 'error');
          return;
      }
      try {
          const res = await fetch('api/theme_api.php?type=app_settings', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ title, description, meta_description, start_url })
          });
          const data = await res.json();
          if (data.success) {
              this.showMessage('App settings saved!', 'success');
          } else {
              this.showMessage('Server rejected app settings', 'error');
          }
      } catch (_) {
          this.showMessage('Failed to save app settings', 'error');
      }
  }
  
  // Setup lyrics toggle functionality
  setupLyricsToggle() {
      const lyricsToggle = document.getElementById('lyricsToggle');
      const lyricsServicesGroup = document.getElementById('lyricsServicesGroup');
      
      if (lyricsToggle && lyricsServicesGroup) {
          // Function to toggle visibility of lyrics services
          const toggleLyricsServices = () => {
              if (lyricsToggle.checked) {
                  lyricsServicesGroup.style.display = 'block';
              } else {
                  lyricsServicesGroup.style.display = 'none';
              }
          };
          
          // Set initial state
          toggleLyricsServices();
          
          // Add event listener for changes
          lyricsToggle.addEventListener('change', toggleLyricsServices);
      }
  }
  
  // Setup stats display functionality
  setupStatsFunctionality() {
      // Load saved stats settings
      this.loadStatsSettings();
      
      // Add event listeners to count display checkboxes
      const showTotalAlbumsCheckbox = document.getElementById('showTotalAlbums');
      const showOwnedAlbumsCheckbox = document.getElementById('showOwnedAlbums');
      const showWantedAlbumsCheckbox = document.getElementById('showWantedAlbums');
      
      if (showTotalAlbumsCheckbox) {
          showTotalAlbumsCheckbox.addEventListener('change', () => {
              this.updateButtonDisplay();
          });
      }
      
      if (showOwnedAlbumsCheckbox) {
          showOwnedAlbumsCheckbox.addEventListener('change', () => {
              this.updateButtonDisplay();
          });
      }
      
      if (showWantedAlbumsCheckbox) {
          showWantedAlbumsCheckbox.addEventListener('change', () => {
              this.updateButtonDisplay();
          });
      }
      
      // Add event listeners to chart display checkboxes
      const showYearChartCheckbox = document.getElementById('showYearChart');
      const showStyleChartCheckbox = document.getElementById('showStyleChart');
      const showFormatChartCheckbox = document.getElementById('showFormatChart');
      const showLabelChartCheckbox = document.getElementById('showLabelChart');
      
      if (showYearChartCheckbox) {
          showYearChartCheckbox.addEventListener('change', () => {
              this.updateChartDisplay();
          });
      }
      
      if (showStyleChartCheckbox) {
          showStyleChartCheckbox.addEventListener('change', () => {
              this.updateChartDisplay();
          });
      }
      
      if (showFormatChartCheckbox) {
          showFormatChartCheckbox.addEventListener('change', () => {
              this.updateChartDisplay();
          });
      }
      
      if (showLabelChartCheckbox) {
          showLabelChartCheckbox.addEventListener('change', () => {
              this.updateChartDisplay();
          });
      }
      
      // Add event listeners to modal statistics checkboxes
      const showModalStylesCheckbox = document.getElementById('showModalStyles');
      const showModalYearsCheckbox = document.getElementById('showModalYears');
      const showModalFormatsCheckbox = document.getElementById('showModalFormats');
      const showModalLabelsCheckbox = document.getElementById('showModalLabels');
      
      if (showModalStylesCheckbox) {
          showModalStylesCheckbox.addEventListener('change', () => {
              this.updateModalDisplay();
          });
      }
      
      if (showModalYearsCheckbox) {
          showModalYearsCheckbox.addEventListener('change', () => {
              this.updateModalDisplay();
          });
      }
      
      if (showModalFormatsCheckbox) {
          showModalFormatsCheckbox.addEventListener('change', () => {
              this.updateModalDisplay();
          });
      }
      
      if (showModalLabelsCheckbox) {
          showModalLabelsCheckbox.addEventListener('change', () => {
              this.updateModalDisplay();
          });
      }
      
      // Add event listeners to Select All/None buttons
      const selectAllCollectionStats = document.getElementById('selectAllCollectionStats');
      const selectNoneCollectionStats = document.getElementById('selectNoneCollectionStats');
      const selectAllChartStats = document.getElementById('selectAllChartStats');
      const selectNoneChartStats = document.getElementById('selectNoneChartStats');
      const selectAllModalStats = document.getElementById('selectAllModalStats');
      const selectNoneModalStats = document.getElementById('selectNoneModalStats');
      
      if (selectAllCollectionStats) {
          selectAllCollectionStats.addEventListener('click', () => {
              document.getElementById('showTotalAlbums').checked = true;
              document.getElementById('showOwnedAlbums').checked = true;
              document.getElementById('showWantedAlbums').checked = true;
              this.updateButtonDisplay();
          });
      }
      
      if (selectNoneCollectionStats) {
          selectNoneCollectionStats.addEventListener('click', () => {
              document.getElementById('showTotalAlbums').checked = false;
              document.getElementById('showOwnedAlbums').checked = false;
              document.getElementById('showWantedAlbums').checked = false;
              this.updateButtonDisplay();
          });
      }
      
      if (selectAllChartStats) {
          selectAllChartStats.addEventListener('click', () => {
              document.getElementById('showYearChart').checked = true;
              document.getElementById('showStyleChart').checked = true;
              document.getElementById('showFormatChart').checked = true;
              document.getElementById('showLabelChart').checked = true;
              this.updateChartDisplay();
          });
      }
      
      if (selectNoneChartStats) {
          selectNoneChartStats.addEventListener('click', () => {
              document.getElementById('showYearChart').checked = false;
              document.getElementById('showStyleChart').checked = false;
              document.getElementById('showFormatChart').checked = false;
              document.getElementById('showLabelChart').checked = false;
              this.updateChartDisplay();
          });
      }
      
      if (selectAllModalStats) {
          selectAllModalStats.addEventListener('click', () => {
              document.getElementById('showModalStyles').checked = true;
              document.getElementById('showModalYears').checked = true;
              document.getElementById('showModalFormats').checked = true;
              document.getElementById('showModalLabels').checked = true;
              this.updateModalDisplay();
          });
      }
      
      if (selectNoneModalStats) {
          selectNoneModalStats.addEventListener('click', () => {
              document.getElementById('showModalStyles').checked = false;
              document.getElementById('showModalYears').checked = false;
              document.getElementById('showModalFormats').checked = false;
              document.getElementById('showModalLabels').checked = false;
              this.updateModalDisplay();
          });
      }
      
      // Save stats settings button
      const saveStatsBtn = document.getElementById('saveStatsBtn');
      if (saveStatsBtn) {
          saveStatsBtn.addEventListener('click', () => {
              this.saveStatsSettings();
          });
      }
      
      // Reset stats settings button
      const resetStatsBtn = document.getElementById('resetStatsBtn');
      if (resetStatsBtn) {
          resetStatsBtn.addEventListener('click', () => {
              this.resetStatsSettings();
          });
      }
  }
  
  // Update button display based on current checkbox states
  updateButtonDisplay() {
      // Get current stats settings
      const statsSettings = this.getStatsSettings();
      
      // Get the filter buttons
      const ownButton = document.querySelector('[data-filter="owned"]');
      const wantButton = document.querySelector('[data-filter="wanted"]');
      const allButton = document.querySelector('[data-filter="all"]');
      
      // Update button text based on current settings and stored counts
      if (ownButton) {
          const currentText = ownButton.textContent;
          const count = currentText.match(/\d+/)?.[0] || '0';
          if (statsSettings.show_owned_albums) {
              ownButton.textContent = `${count} Owned`;
          } else {
              ownButton.textContent = 'Owned';
          }
      }
      
      if (wantButton) {
          const currentText = wantButton.textContent;
          const count = currentText.match(/\d+/)?.[0] || '0';
          if (statsSettings.show_wanted_albums) {
              wantButton.textContent = `${count} Want`;
          } else {
              wantButton.textContent = 'Want';
          }
      }
      
      if (allButton) {
          const currentText = allButton.textContent;
          const count = currentText.match(/\d+/)?.[0] || '0';
          if (statsSettings.show_total_albums) {
              allButton.textContent = `${count} Total`;
          } else {
              allButton.textContent = 'Total';
          }
      }
  }
  
  // Update chart display based on current checkbox states
  updateChartDisplay() {
      // Get current stats settings
      const statsSettings = this.getStatsSettings();
      
      // Get chart containers - only proceed if we're on the main collection page
      const yearChart = document.querySelector('#sidebarYearChart');
      const styleChart = document.querySelector('#sidebarStyleChart');
      const formatChart = document.querySelector('#sidebarFormatChart');
      const labelChart = document.querySelector('#sidebarLabelChart');
      
      // If we're not on the main collection page (setup page), exit early
      if (!yearChart || !styleChart || !formatChart || !labelChart) {
          return;
      }
      
      const yearChartSection = yearChart.closest('.sidebar-stat-section');
      const styleChartSection = styleChart.closest('.sidebar-stat-section');
      const formatChartSection = formatChart.closest('.sidebar-stat-section');
      const labelChartSection = labelChart.closest('.sidebar-stat-section');
      
      // Show/hide individual charts
      if (yearChartSection) {
          yearChartSection.style.display = statsSettings.show_year_chart ? 'block' : 'none';
      }
      
      if (styleChartSection) {
          styleChartSection.style.display = statsSettings.show_style_chart ? 'block' : 'none';
      }
      
      if (formatChartSection) {
          formatChartSection.style.display = statsSettings.show_format_chart ? 'block' : 'none';
      }
      
      if (labelChartSection) {
          labelChartSection.style.display = statsSettings.show_label_chart ? 'block' : 'none';
      }
      
      // Check if any charts are visible
      const anyChartsVisible = statsSettings.show_year_chart || statsSettings.show_style_chart || 
                              statsSettings.show_format_chart || statsSettings.show_label_chart;
      
      // Show/hide the entire sidebar and "Collection Statistics" header
      const sidebarStats = document.getElementById('sidebarStats');
      const sidebar = document.querySelector('.sidebar');
      const mainContent = document.querySelector('.main-content');
      const contentWithSidebar = document.querySelector('.content-with-sidebar');
      
      if (sidebarStats && sidebar && mainContent && contentWithSidebar) {
          if (anyChartsVisible) {
              // Show sidebar and header
              sidebarStats.style.display = 'block';
              sidebar.style.display = 'block';
              mainContent.classList.remove('full-width');
              contentWithSidebar.classList.remove('no-sidebar');
          } else {
              // Hide sidebar and header, extend main content
              sidebarStats.style.display = 'none';
              sidebar.style.display = 'none';
              mainContent.classList.add('full-width');
              contentWithSidebar.classList.add('no-sidebar');
          }
      }
  }
  
  // Update modal display based on current checkbox states
  updateModalDisplay() {
      // Get current stats settings
      const statsSettings = this.getStatsSettings();
      
      // Get modal section containers - only proceed if we're on the main collection page
      const styleStatsContainer = document.querySelector('.style-stats-container');
      const yearStatsContainer = document.querySelector('.year-stats-container');
      const formatStatsContainer = document.querySelector('.format-stats-container');
      const labelStatsContainer = document.querySelector('.label-stats-container');
      
      // If we're not on the main collection page (setup page), exit early
      if (!styleStatsContainer || !yearStatsContainer || !formatStatsContainer || !labelStatsContainer) {
          return;
      }
      
      // Show/hide individual modal sections
      if (styleStatsContainer) {
          styleStatsContainer.style.display = statsSettings.show_modal_styles ? 'block' : 'none';
      }
      
      if (yearStatsContainer) {
          yearStatsContainer.style.display = statsSettings.show_modal_years ? 'block' : 'none';
      }
      
      if (formatStatsContainer) {
          formatStatsContainer.style.display = statsSettings.show_modal_formats ? 'block' : 'none';
      }
      
      if (labelStatsContainer) {
          labelStatsContainer.style.display = statsSettings.show_modal_labels ? 'block' : 'none';
      }
      
      // Check if any modal sections are visible
      const anyModalSectionsVisible = statsSettings.show_modal_styles || statsSettings.show_modal_years || 
                                     statsSettings.show_modal_formats || statsSettings.show_modal_labels;
      
      // Show/hide the Collection Statistics dropdown option
      const statsBtn = document.getElementById('statsBtn');
      if (statsBtn) {
          if (anyModalSectionsVisible) {
              statsBtn.style.display = 'flex';
          } else {
              statsBtn.style.display = 'none';
          }
      }
  }
  
  // Load stats settings from server
  async loadStatsSettings() {
      try {
          const response = await fetch('api/theme_api.php?type=stats_display_settings');
          const data = await response.json();
          
          if (data.success) {
              const statsSettings = data.data;
              this.serverStatsSettings = statsSettings; // Cache server settings
              this.applyStatsSettingsToUI(statsSettings);
              
              // removed localStorage backup for stats settings
              
              // Update displays after loading settings
              this.updateButtonDisplay();
              this.updateChartDisplay();
              this.updateModalDisplay();
          } else {
              // No localStorage fallback; keep current UI/defaults
          }
      } catch (error) {
          // No localStorage fallback; keep current UI/defaults
      }
  }
  
  // Removed localStorage fallback for stats settings
  
  // Apply stats settings to UI elements
  applyStatsSettingsToUI(statsSettings) {
      Object.keys(statsSettings).forEach(key => {
          let checkboxId = key.replace(/_([a-z])/g, (match, letter) => letter.toUpperCase());
          const checkbox = document.getElementById(checkboxId);
          if (checkbox) {
              checkbox.checked = statsSettings[key];
          }
      });
  }
  
  // Save stats settings to server
  async saveStatsSettings() {
      const statsSettings = {
          show_total_albums: document.getElementById('showTotalAlbums')?.checked || false,
          show_owned_albums: document.getElementById('showOwnedAlbums')?.checked || false,
          show_wanted_albums: document.getElementById('showWantedAlbums')?.checked || false,
          show_year_chart: document.getElementById('showYearChart')?.checked || false,
          show_style_chart: document.getElementById('showStyleChart')?.checked || false,
          show_format_chart: document.getElementById('showFormatChart')?.checked || false,
          show_label_chart: document.getElementById('showLabelChart')?.checked || false,
          show_modal_styles: document.getElementById('showModalStyles')?.checked || false,
          show_modal_years: document.getElementById('showModalYears')?.checked || false,
          show_modal_formats: document.getElementById('showModalFormats')?.checked || false,
          show_modal_labels: document.getElementById('showModalLabels')?.checked || false
      };
      
      try {
          // Save to server
          const response = await fetch('api/theme_api.php?type=stats_display_settings', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(statsSettings)
          });
          
          const data = await response.json();
          
          if (data.success) {
              // Show success message
              this.showMessage('Stats settings saved successfully!', 'success');
          } else {
              this.showMessage('Stats settings save failed on server', 'error');
          }
      } catch (error) {
          this.showMessage('Failed to save stats settings to server', 'error');
      }
  }
  
  // Reset stats settings to defaults
  resetStatsSettings() {
      const defaultStatsSettings = {
          show_total_albums: true,
          show_owned_albums: true,
          show_wanted_albums: true,
          show_year_chart: true,
          show_style_chart: true,
          show_format_chart: true,
          show_label_chart: true,
          show_modal_styles: true,
          show_modal_years: true,
          show_modal_formats: true,
          show_modal_labels: true
      };
      
      // Apply default settings to UI
      this.applyStatsSettingsToUI(defaultStatsSettings);
      
      // Save default settings to server
      this.saveDefaultStatsSettingsToServer(defaultStatsSettings);
  }
  
  // Save default stats settings to server
  async saveDefaultStatsSettingsToServer(defaultStatsSettings) {
      try {
          // Save to server
          const response = await fetch('api/theme_api.php?type=stats_display_settings', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(defaultStatsSettings)
          });
          
          const data = await response.json();
          
          if (data.success) {
              // Show success message
              this.showMessage('Stats settings reset to defaults!', 'success');
              
              // Update displays after reset
              this.updateChartDisplay();
              this.updateModalDisplay();
          } else {
              this.showMessage('Stats settings reset failed on server', 'error');
          }
      } catch (error) {
          this.showMessage('Stats settings reset failed on server', 'error');
      }
  }
  
  // Get stats settings
  getStatsSettings() {
      const defaultStatsSettings = {
          show_total_albums: true,
          show_owned_albums: true,
          show_wanted_albums: true,
          show_year_chart: true,
          show_style_chart: true,
          show_format_chart: true,
          show_label_chart: true,
          show_modal_styles: true,
          show_modal_years: true,
          show_modal_formats: true,
          show_modal_labels: true
      };
      
      if (this.serverStatsSettings) {
          return { ...defaultStatsSettings, ...this.serverStatsSettings };
      }
      return defaultStatsSettings;
  }
  
  // Load settings from server
  async loadSettings() {
      try {
          const response = await fetch('api/theme_api.php?type=album_display_settings');
          const data = await response.json();
          
          if (data.success) {
              const settings = data.data;
              this.serverSettings = settings; // Cache server settings
              this.applySettingsToUI(settings);
              
              // Refresh album display if we're on the main collection page
              if (!document.body.classList.contains('setup-page') && this.albums && this.albums.length > 0) {
                  this.renderAlbums(this.albums);
              }
          } else {
              // No localStorage fallback; keep current UI/defaults
          }
      } catch (error) {
          // No localStorage fallback; keep current UI/defaults
      }
  }
  
  // Removed localStorage fallback for album settings
  
  // Apply settings to UI elements
  applySettingsToUI(settings) {
      Object.keys(settings).forEach(key => {
          if (key === 'show_lyrics') {
              // Handle toggle switch for lyrics display
              const lyricsToggle = document.getElementById('lyricsToggle');
              const lyricsServicesGroup = document.getElementById('lyricsServicesGroup');
              if (lyricsToggle) {
                  lyricsToggle.checked = settings[key];
                  // Also update visibility of lyrics services group
                  if (lyricsServicesGroup) {
                      lyricsServicesGroup.style.display = settings[key] ? 'block' : 'none';
                  }
              }
          }
          if (key === 'show_genius_lyrics') {
              // Handle toggle switch for Genius lyrics
              const geniusToggle = document.getElementById('geniusToggle');
              if (geniusToggle) {
                  geniusToggle.checked = settings[key];
              }
          }
          if (key === 'show_azlyrics_lyrics') {
              // Handle toggle switch for AZLyrics lyrics
              const azlyricsToggle = document.getElementById('azlyricsToggle');
              if (azlyricsToggle) {
                  azlyricsToggle.checked = settings[key];
              }
          }
          if (key === 'show_google_lyrics') {
              // Handle toggle switch for Google Search lyrics
              const googleToggle = document.getElementById('googleToggle');
              if (googleToggle) {
                  googleToggle.checked = settings[key];
              }
          } else if (key.startsWith('show_') && (key === 'show_producer' || key === 'show_label' || key === 'show_released' || key === 'show_rating' || key === 'show_format')) {
              // Handle tracklist toggle switches
              const toggleId = key.replace('show_', '') + 'Toggle';
              const toggle = document.getElementById(toggleId);
              if (toggle) {
                  toggle.checked = settings[key];
              }
          } else {
              // Handle checkboxes
              let checkboxId = key.replace(/_([a-z])/g, (match, letter) => letter.toUpperCase());
              
              // Handle special cases where the ID doesn't match the standard conversion
              if (key === 'show_youtube') {
                  checkboxId = 'showYouTube';
              } else if (key === 'show_soundcloud') {
                  checkboxId = 'showSoundCloud';
              }
              
              const checkbox = document.getElementById(checkboxId);
              if (checkbox) {
                  checkbox.checked = settings[key];
              }
          }
      });
  }
  
  // Save settings to server
  async saveSettings() {
      console.log('saveSettings() called');
      const settings = {
          show_facebook: document.getElementById('showFacebook')?.checked || false,
          show_twitter: document.getElementById('showTwitter')?.checked || false,
          show_instagram: document.getElementById('showInstagram')?.checked || false,
          show_youtube: document.getElementById('showYouTube')?.checked || false,
          show_bandcamp: document.getElementById('showBandcamp')?.checked || false,
          show_soundcloud: document.getElementById('showSoundCloud')?.checked || false,
          show_wikipedia: document.getElementById('showWikipedia')?.checked || false,
          show_lastfm: document.getElementById('showLastfm')?.checked || false,
          show_imdb: document.getElementById('showImdb')?.checked || false,
          show_bluesky: document.getElementById('showBluesky')?.checked || false,
          show_discogs: document.getElementById('showDiscogs')?.checked || false,
          show_official_website: document.getElementById('showOfficialWebsite')?.checked || false,
          show_album_count: document.getElementById('showAlbumCount')?.checked || false,
          show_year_range: document.getElementById('showYearRange')?.checked || false,
          enable_animations: document.getElementById('enableAnimations')?.checked || false,
          show_lyrics: document.getElementById('lyricsToggle')?.checked || false,
          show_genius_lyrics: document.getElementById('geniusToggle')?.checked || false,
          show_azlyrics_lyrics: document.getElementById('azlyricsToggle')?.checked || false,
          show_google_lyrics: document.getElementById('googleToggle')?.checked || false,
          show_producer: document.getElementById('producerToggle')?.checked || false,
          show_label: document.getElementById('labelToggle')?.checked || false,
          show_released: document.getElementById('releasedToggle')?.checked || false,
          show_rating: document.getElementById('ratingToggle')?.checked || false,
          show_format: document.getElementById('formatToggle')?.checked || false
      };
      
      console.log('Settings to save:', settings);
      
      try {
          // Save to server
          const response = await fetch('api/theme_api.php?type=album_display_settings', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(settings)
          });
          
          const data = await response.json();
          
          if (data.success) {
              // removed localStorage backup for album settings
              
              // Show success message
              this.showMessage('Settings saved successfully!', 'success');
              
              // Refresh artist links if we're on the main collection page
              if (!document.body.classList.contains('setup-page')) {
                  this.refreshArtistLinks();
              }
          } else {
              this.showMessage('Server rejected settings', 'error');
          }
      } catch (error) {
          this.showMessage('Failed to save settings to server', 'error');
      }
  }
  
  // Reset settings to defaults
  resetSettings() {
      const defaultSettings = {
          show_facebook: true,
          show_twitter: true,
          show_instagram: true,
          show_youtube: true,
          show_bandcamp: true,
          show_soundcloud: true,
          show_wikipedia: true,
          show_lastfm: true,
          show_imdb: true,
          show_bluesky: true,
          show_discogs: true,
          show_official_website: true,
          show_album_count: true,
          show_year_range: true,
          enable_animations: true,
          show_lyrics: true,
          show_genius_lyrics: true,
          show_azlyrics_lyrics: true,
          show_google_lyrics: true,
          show_producer: true,
          show_label: true,
          show_released: true,
          show_rating: true,
          show_format: true
      };
      
      // Apply default settings to UI
      this.applySettingsToUI(defaultSettings);
      
      // Save default settings to server
      this.saveDefaultSettingsToServer(defaultSettings);
  }
  
  // Save default settings to server
  async saveDefaultSettingsToServer(defaultSettings) {
      try {
          // Save to server
          const response = await fetch('api/theme_api.php?type=album_display_settings', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(defaultSettings)
          });
          
          const data = await response.json();
          
          if (data.success) {
              // Clear saved settings from localStorage
              // removed localStorage cleanup for album settings
              
              // Show success message
              this.showMessage('Settings reset to defaults!', 'success');
          } else {
              this.showMessage('Settings reset failed on server', 'error');
          }
      } catch (error) {
          this.showMessage('Settings reset failed on server', 'error');
      }
  }
  
  // Show message helper
  showMessage(message, type = 'info') {
      const messageDiv = document.getElementById('message');
      if (messageDiv) {
          messageDiv.textContent = message;
          messageDiv.className = `message ${type}`;
          messageDiv.classList.add('show');
          
          setTimeout(() => {
              messageDiv.classList.remove('show');
          }, 3000);
      }
  }
  
  // Select all artist links
  selectAllArtistLinks() {
      const artistLinkCheckboxes = [
          'showFacebook', 'showTwitter', 'showInstagram', 'showYouTube', 
          'showBandcamp', 'showSoundCloud', 'showWikipedia', 'showLastfm', 
          'showImdb', 'showBluesky', 'showDiscogs', 'showOfficialWebsite'
      ];
      
      artistLinkCheckboxes.forEach(checkboxId => {
          const checkbox = document.getElementById(checkboxId);
          if (checkbox) {
              checkbox.checked = true;
          }
      });
  }
  
  // Select none of the artist links
  selectNoneArtistLinks() {
      const artistLinkCheckboxes = [
          'showFacebook', 'showTwitter', 'showInstagram', 'showYouTube', 
          'showBandcamp', 'showSoundCloud', 'showWikipedia', 'showLastfm', 
          'showImdb', 'showBluesky', 'showDiscogs', 'showOfficialWebsite'
      ];
      
      artistLinkCheckboxes.forEach(checkboxId => {
          const checkbox = document.getElementById(checkboxId);
          if (checkbox) {
              checkbox.checked = false;
          }
      });
  }
  
  // Select all album info
  selectAllAlbumInfo() {
      const albumInfoCheckboxes = [
          'labelToggle', 'formatToggle', 'producerToggle', 
          'releasedToggle', 'ratingToggle', 'lyricsToggle',
          'geniusToggle', 'azlyricsToggle', 'googleToggle'
      ];
      
      albumInfoCheckboxes.forEach(checkboxId => {
          const checkbox = document.getElementById(checkboxId);
          if (checkbox) {
              checkbox.checked = true;
          }
      });
      
      // Show lyrics services group when lyrics toggle is checked
      const lyricsServicesGroup = document.getElementById('lyricsServicesGroup');
      if (lyricsServicesGroup) {
          lyricsServicesGroup.style.display = 'block';
      }
  }
  
  // Select none of the album info
  selectNoneAlbumInfo() {
      const albumInfoCheckboxes = [
          'labelToggle', 'formatToggle', 'producerToggle', 
          'releasedToggle', 'ratingToggle', 'lyricsToggle',
          'geniusToggle', 'azlyricsToggle', 'googleToggle'
      ];
      
      albumInfoCheckboxes.forEach(checkboxId => {
          const checkbox = document.getElementById(checkboxId);
          if (checkbox) {
              checkbox.checked = false;
          }
      });
      
      // Hide lyrics services group when lyrics toggle is unchecked
      const lyricsServicesGroup = document.getElementById('lyricsServicesGroup');
      if (lyricsServicesGroup) {
          lyricsServicesGroup.style.display = 'none';
      }
  }
  
  // Get current settings from localStorage
  getSettings() {
      const defaultSettings = {
          show_facebook: true,
          show_twitter: true,
          show_instagram: true,
          show_youtube: true,
          show_bandcamp: true,
          show_soundcloud: true,
          show_wikipedia: true,
          show_lastfm: true,
          show_imdb: true,
          show_bluesky: true,
          show_discogs: true,
          show_official_website: true,
          show_album_count: true,
          show_year_range: true,
          enable_animations: true,
          show_lyrics: true,
          show_genius_lyrics: true,
          show_azlyrics_lyrics: true,
          show_google_lyrics: true,
          show_producer: true,
          show_label: true,
          show_released: true,
          show_rating: true,
          show_format: true
      };
      
      // Use cached server settings if available
      if (this.serverSettings) {
          return { ...defaultSettings, ...this.serverSettings };
      }
      return defaultSettings;
  }
  
  // Check if a specific link type should be shown
  shouldShowLink(linkType) {
      const settings = this.getSettings();
      const settingKey = `show_${linkType.toLowerCase().replace(' ', '_')}`;
      return settings[settingKey] === true;
  }
  
  // Check if any artist links are enabled
  hasAnyArtistLinksEnabled() {
      const settings = this.getSettings();
      const artistLinkSettings = [
          'show_facebook', 'show_twitter', 'show_instagram', 'show_youtube',
          'show_bandcamp', 'show_soundcloud', 'show_wikipedia', 'show_lastfm',
          'show_imdb', 'show_bluesky', 'show_discogs', 'show_official_website'
      ];
      
      return artistLinkSettings.some(setting => settings[setting] === true);
  }
  
  // Check if lyrics should be shown in tracklist
  shouldShowLyrics() {
      const settings = this.getSettings();
      return settings.show_lyrics === true;
  }
  
  // Check if tracklist elements should be shown
  shouldShowProducer() {
      const settings = this.getSettings();
      return settings.show_producer === true;
  }
  
  shouldShowLabel() {
      const settings = this.getSettings();
      return settings.show_label === true;
  }
  
  shouldShowReleased() {
      const settings = this.getSettings();
      return settings.show_released === true;
  }
  
  shouldShowRating() {
      const settings = this.getSettings();
      return settings.show_rating === true;
  }
  
  shouldShowFormat() {
      const settings = this.getSettings();
      return settings.show_format === true;
  }
  
  // Refresh artist links based on current settings
  refreshArtistLinks() {
      // Find all artist website sections and re-render them
      const artistWebsiteSections = document.querySelectorAll('.artist-website-section');
      artistWebsiteSections.forEach(section => {
          // Get the artist name from the section's parent
          const artistName = section.closest('.artist-info')?.querySelector('.artist-name')?.textContent;
          if (artistName) {
              // Re-fetch and re-render the artist websites
              this.loadArtistWebsites(artistName.trim()).then(artistWebsiteInfo => {
                  if (artistWebsiteInfo) {
                      const newSection = this.renderArtistWebsites(artistWebsiteInfo);
                      section.outerHTML = newSection;
                  }
              });
          }
      });
  }
  
  async loadSetupStatus() {
      try {
          const response = await fetch('api/music_api.php?action=get_setup_status');
          const data = await response.json();
          
          if (data.success) {
              const statusData = data.data;
              
              // Update API key status
              const apiKeyStatus = document.getElementById('apiKeyStatus');
              const apiKeyDetails = document.getElementById('apiKeyDetails');
              const currentApiKeyDisplay = document.getElementById('currentApiKeyDisplay');
              const currentApiKeyText = document.getElementById('currentApiKeyText');
              
              if (statusData.api_key_set) {
                  // Show source information
                  let sourceText = '';
                  if (statusData.api_key_source === 'environment') {
                      sourceText = ' (from environment variable)';
                  } else if (statusData.api_key_source === 'config_file') {
                      sourceText = ' (from config file)';
                  }
                  
                  apiKeyDetails.textContent = statusData.current_api_key + sourceText;
                  apiKeyDetails.style.display = 'inline';
                  apiKeyStatus.textContent = '✅ Set';
                  apiKeyStatus.className = 'status-value set';
                  currentApiKeyDisplay.style.display = 'none';
              } else {
                  apiKeyDetails.style.display = 'none';
                  apiKeyStatus.textContent = '❌ Not set';
                  apiKeyStatus.className = 'status-value not-set';
                  currentApiKeyDisplay.style.display = 'none';
              }
              
              // Update password action text (password is always set)
              const passwordActionText = document.getElementById('passwordActionText');
              passwordActionText.textContent = 'Change Password';
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
              messageDiv.className = 'setup-message success';
              messageDiv.style.display = 'block';
              
              // Clear form on success
              document.getElementById('setupForm').reset();
              
              // Reload setup status
              this.loadSetupStatus();
              
              // Auto-hide message after 3 seconds
              setTimeout(() => {
                  messageDiv.style.display = 'none';
              }, 3000);
          } else {
              messageDiv.textContent = data.message;
              messageDiv.className = 'setup-message error';
              messageDiv.style.display = 'block';
          }
      } catch (error) {
          messageDiv.textContent = 'Network error. Please try again.';
          messageDiv.className = 'setup-message error';
          messageDiv.style.display = 'block';
      }
  }
  
  hideLoginModal() {
      const modal = document.getElementById('loginModal');
      if (modal) {
          modal.style.display = 'none';
      }
      document.getElementById('password').value = '';
      const messageDiv = document.getElementById('loginMessage');
      if (messageDiv) {
          messageDiv.style.display = 'none';
      }
  }
  
  async handleLogin(event) {
      event.preventDefault();
      
      const password = document.getElementById('password').value;
      const messageDiv = document.getElementById('loginMessage');
      
      if (!password) {
          messageDiv.textContent = 'Please enter a password.';
          messageDiv.className = 'modal-message error';
          messageDiv.style.display = 'block';
          return;
      }
      
      try {
          const controller = new AbortController();
          const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
          
          const response = await fetch('api/music_api.php?action=login', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({ password: password }),
              signal: controller.signal
          });
          
          clearTimeout(timeoutId);

          const data = await response.json();
          
          if (data.success) {
              // Re-check authentication status from server to ensure session is set
              await this.checkAuthStatus();
              this.hideLoginModal();
              this.showMessage('Login successful', 'success');
          } else {
              messageDiv.textContent = data.message;
              messageDiv.className = 'modal-message error';
              messageDiv.style.display = 'block';
          }
      } catch (error) {
          if (error.name === 'AbortError') {
              messageDiv.textContent = 'Login request timed out. Please try again.';
          } else {
              messageDiv.textContent = 'Login failed. Please try again.';
          }
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
              // Re-check authentication status from server to ensure session is cleared
              await this.checkAuthStatus();
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
  
  renderLyricsLinks(track) {
      // Check if lyrics should be shown based on user settings
      if (!this.shouldShowLyrics()) {
          return '';
      }
      
      if (!track.lyrics_urls) {
          return '';
      }
      
      const lyricsUrls = track.lyrics_urls;
      const hasLyrics = track.has_lyrics;
      const settings = this.getSettings();
      
      // Determine which services are enabled and available
      const enabledServices = [];
      if (lyricsUrls.genius && settings.show_genius_lyrics) {
          enabledServices.push({ name: 'Genius', url: lyricsUrls.genius });
      }
      if (lyricsUrls.azlyrics && settings.show_azlyrics_lyrics) {
          enabledServices.push({ name: 'AZLyrics', url: lyricsUrls.azlyrics });
      }
      if (lyricsUrls.google && settings.show_google_lyrics) {
          enabledServices.push({ name: 'Google Search', url: lyricsUrls.google });
      }
      
      // If no services are enabled, don't show anything
      if (enabledServices.length === 0) {
          return '';
      }
      
      let lyricsHtml = '<div class="lyrics-links">';
      
      if (hasLyrics) {
          lyricsHtml += '<span class="lyrics-indicator">🎵</span>';
      }
      
      // If only one service is enabled, create a direct link button
      if (enabledServices.length === 1) {
          const service = enabledServices[0];
          lyricsHtml += `<a href="${service.url}" target="_blank" rel="noopener noreferrer" class="lyrics-btn-direct">Lyrics</a>`;
      } else {
          // Multiple services enabled, show dropdown
          lyricsHtml += '<div class="lyrics-dropdown">';
          lyricsHtml += '<button class="lyrics-btn">Lyrics</button>';
          lyricsHtml += '<div class="lyrics-menu">';
          
          enabledServices.forEach(service => {
              lyricsHtml += `<a href="${service.url}" target="_blank" rel="noopener noreferrer">${service.name}</a>`;
          });
          
          lyricsHtml += '</div></div>';
      }
      
      lyricsHtml += '</div>';
      
      return lyricsHtml;
  }

  renderArtistWebsite(artistWebsiteInfo) {
      if (!artistWebsiteInfo || !artistWebsiteInfo.websites || artistWebsiteInfo.websites.length === 0) {
          return '';
      }

      const websites = artistWebsiteInfo.websites;
      const discogsUrl = artistWebsiteInfo.discogs_url;

      // Filter websites based on settings
      const filteredWebsites = websites.filter(website => {
          return this.shouldShowLink(website.type);
      });
      
      // Check if Discogs link should be shown
      const showDiscogsLink = discogsUrl && this.shouldShowLink('Discogs');
      
      // Don't render the section if no links should be shown or no artist links are enabled
      if (filteredWebsites.length === 0 && !showDiscogsLink || !this.hasAnyArtistLinksEnabled()) {
          return '';
      }

      return `
          <div class="artist-website-section">
              <div class="artist-website-header">
                  <span class="artist-website-icon">🌐</span>
                  <strong>Artist Links</strong>
              </div>
              <div class="artist-website-links">
                  ${filteredWebsites.map(website => `
                      <a href="${website.url}" target="_blank" rel="noopener noreferrer" class="artist-website-link">
                          ${website.type}
                      </a>
                  `).join('')}
                  ${showDiscogsLink ? `<a href="${discogsUrl}" target="_blank" rel="noopener noreferrer" class="artist-website-link discogs-link">Artist Profile</a>` : ''}
              </div>
          </div>
      `;
  }

  // Helper function to clean Discogs numbering (e.g., "Label Name (5)" -> "Label Name")
  cleanDiscogsNumbering(text) {
      if (!text) return text;
      return text.replace(/\s*\(\d+\)\s*$/, '');
  }
  
  decodeHtmlEntities(text) {
      const div = document.createElement('div');
      div.innerHTML = text;
      return div.textContent;
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
      list.style.maxHeight = '1200px';
      
      // Calculate available space below and above the input
      const spaceBelow = modalRect.bottom - containerRect.bottom - 20; // Account for padding
      const spaceAbove = containerRect.top - modalRect.top - 20; // Account for padding
      
      // Calculate the actual height the list would need
      const listHeight = Math.min(list.scrollHeight, 1200);
      
      // Always prefer positioning below the input for better UX
      // Only position above if there's significantly more space above and very little below
      if (spaceBelow < 200 && spaceAbove > spaceBelow + 100) {
          // Position above only if there's much more space above
          list.style.top = 'auto';
          list.style.bottom = '100%';
          list.style.maxHeight = `${Math.min(spaceAbove - 20, 1200)}px`;
          
          // Ensure the list is still scrollable and visible
          list.style.overflowY = 'auto';
          list.style.overflowX = 'hidden';
      } else {
          // Position below the input (preferred)
          list.style.top = '100%';
          list.style.bottom = 'auto';
          
          // If there's limited space below, make it scrollable with a reasonable height
          if (spaceBelow < listHeight) {
              list.style.maxHeight = `${Math.max(spaceBelow - 20, 300)}px`; // Minimum 300px for usability
          } else {
              list.style.maxHeight = `${Math.min(spaceBelow - 20, 1200)}px`;
          }
          
          // Ensure the list is scrollable
          list.style.overflowY = 'auto';
          list.style.overflowX = 'hidden';
      }
      
      // Ensure the list doesn't extend horizontally beyond the modal
      const listWidth = list.offsetWidth;
      const containerWidth = container.offsetWidth;
      
      if (listWidth > containerWidth) {
          list.style.width = `${containerWidth}px`;
      }
      
      // Ensure proper z-index and visibility
      list.style.zIndex = '10020';
      list.style.visibility = 'visible';
      list.style.opacity = '1';
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
          list.style.zIndex = '10020';
          list.style.backgroundColor = 'white';
          list.style.border = '1px solid #ddd';
          list.style.borderRadius = '4px';
          list.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
          list.style.display = 'block'; // Ensure it's visible
          list.style.visibility = 'visible';
          list.style.opacity = '1';
          list.style.maxHeight = '350px';
          list.style.overflowY = 'auto';
          list.style.overflowX = 'hidden';
          
          // Store reference to original container
          list.dataset.originalContainer = container.id;
      }
  }

  // ==========================================================================
  // THEME CUSTOMIZATION METHODS
  // ==========================================================================

  async setupThemeCustomization() {
      // Load saved theme colors on initialization
      await this.loadThemeColors();
      
      // Setup display mode functionality
      await this.setupDisplayMode();

      // Color picker change events
      const color1Picker = document.getElementById('gradientColor1');
      const color2Picker = document.getElementById('gradientColor2');
      const color1Hex = document.getElementById('gradientColor1Hex');
      const color2Hex = document.getElementById('gradientColor2Hex');
      const resetBtn = document.getElementById('resetThemeBtn');
      const saveBtn = document.getElementById('saveThemeBtn');

      if (color1Picker) {
          color1Picker.addEventListener('change', (e) => {
              color1Hex.value = e.target.value;
              this.previewTheme();
          });
      }

      if (color2Picker) {
          color2Picker.addEventListener('change', (e) => {
              color2Hex.value = e.target.value;
              this.previewTheme();
          });
      }

      // Hex input change events
      if (color1Hex) {
          color1Hex.addEventListener('input', (e) => {
              const color = e.target.value;
              if (this.isValidHexColor(color)) {
                  color1Picker.value = color;
                  this.previewTheme();
              }
          });
      }

      if (color2Hex) {
          color2Hex.addEventListener('input', (e) => {
              const color = e.target.value;
              if (this.isValidHexColor(color)) {
                  color2Picker.value = color;
                  this.previewTheme();
              }
          });
      }

      // Theme action buttons
      if (resetBtn) {
          resetBtn.addEventListener('click', () => {
              this.resetTheme();
          });
      }

      if (saveBtn) {
          saveBtn.addEventListener('click', () => {
              this.saveThemeColors();
              this.showThemeMessage('Theme colors saved successfully!', 'success');
          });
      }
  }

  async loadThemeColors() {
      // Always load from server first to get the latest colors
      try {
          const response = await fetch('api/theme_api.php');
          const data = await response.json();
          
          if (data.success) {
              const serverColor1 = data.data.gradient_color_1;
              const serverColor2 = data.data.gradient_color_2;

              // Check if localStorage has different colors (indicating they're outdated)
              const localColor1 = localStorage.getItem('gradientColor1');
              const localColor2 = localStorage.getItem('gradientColor2');
              
              if (localColor1 && localColor2 && 
                  (localColor1 !== serverColor1 || localColor2 !== serverColor2)) {
                  console.log('localStorage colors differ from server, updating localStorage');
                  // Update localStorage with server colors
                  localStorage.setItem('gradientColor1', serverColor1);
                  localStorage.setItem('gradientColor2', serverColor2);
              }
              
              // Update color picker inputs with server colors
              this.updateColorPickerInputs(serverColor1, serverColor2);
              
              // Apply server colors (background already set by server-side CSS)
              this.applyThemeColors(serverColor1, serverColor2);
          } else {
              console.warn('Server theme load failed:', data.message);
              this.fallbackToLocalStorage();
          }
      } catch (error) {
          console.warn('Server theme load error:', error);
          this.fallbackToLocalStorage();
      }
  }

  fallbackToLocalStorage() {
      // Fallback to localStorage if server fails
      const localColor1 = localStorage.getItem('gradientColor1') || '#667eea';
      const localColor2 = localStorage.getItem('gradientColor2') || '#764ba2';
      
      console.log('Falling back to localStorage:', localColor1, localColor2);
      this.updateColorPickerInputs(localColor1, localColor2);
      this.applyThemeColors(localColor1, localColor2);
  }

  updateColorPickerInputs(color1, color2) {
      const color1Picker = document.getElementById('gradientColor1');
      const color1Hex = document.getElementById('gradientColor1Hex');
      const color2Picker = document.getElementById('gradientColor2');
      const color2Hex = document.getElementById('gradientColor2Hex');

      if (color1Picker && color1Hex && color2Picker && color2Hex) {
          color1Picker.value = color1;
          color1Hex.value = color1;
          color2Picker.value = color2;
          color2Hex.value = color2;
      }
  }

  async saveThemeColors() {
      const color1 = document.getElementById('gradientColor1').value;
      const color2 = document.getElementById('gradientColor2').value;

      // Save to localStorage (fast, same device)
      localStorage.setItem('gradientColor1', color1);
      localStorage.setItem('gradientColor2', color2);

      // Apply the colors to the body
      this.applyThemeColors(color1, color2);

      // Save to server (cross-device persistence)
      try {
          const response = await fetch('api/theme_api.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                  gradient_color_1: color1,
                  gradient_color_2: color2
              })
          });

          const data = await response.json();
          if (!data.success) {
              console.warn('Failed to save theme to server:', data.message);
          }
      } catch (error) {
          console.warn('Failed to save theme to server:', error);
      }
  }

  applyThemeColors(color1, color2) {
      // Apply colors directly to body element to override server-side CSS
      document.body.style.background = `linear-gradient(135deg, ${color1} 0%, ${color2} 100%)`;
  }

  previewTheme() {
      const color1 = document.getElementById('gradientColor1').value;
      const color2 = document.getElementById('gradientColor2').value;
      this.applyThemeColors(color1, color2);
  }

  resetTheme() {
      const defaultColor1 = '#667eea';
      const defaultColor2 = '#764ba2';

      const color1Picker = document.getElementById('gradientColor1');
      const color1Hex = document.getElementById('gradientColor1Hex');
      const color2Picker = document.getElementById('gradientColor2');
      const color2Hex = document.getElementById('gradientColor2Hex');

      if (color1Picker && color1Hex && color2Picker && color2Hex) {
          color1Picker.value = defaultColor1;
          color1Hex.value = defaultColor1;
          color2Picker.value = defaultColor2;
          color2Hex.value = defaultColor2;

          this.applyThemeColors(defaultColor1, defaultColor2);
          this.saveThemeColors();
      }
  }

  async setupDisplayMode() {
      // Load saved display mode preference
      await this.loadDisplayMode();

      // Add event listeners to radio buttons for visual feedback
      const lightModeRadio = document.getElementById('lightMode');
      const darkModeRadio = document.getElementById('darkMode');
      
      if (lightModeRadio && darkModeRadio) {
          lightModeRadio.addEventListener('change', () => {
              this.updateRadioButtonStyles();
          });
          
          darkModeRadio.addEventListener('change', () => {
              this.updateRadioButtonStyles();
          });
      }

      // Save display mode button
      const saveDisplayModeBtn = document.getElementById('saveDisplayModeBtn');
      if (saveDisplayModeBtn) {
          saveDisplayModeBtn.addEventListener('click', () => {
              this.saveDisplayMode();
          });
      }
  }

  async loadDisplayMode() {
      // Always load from server first to get the latest display mode
      try {
          const response = await fetch('api/theme_api.php?type=display_mode');
          const data = await response.json();
          
          if (data.success) {
              const serverMode = data.data.theme;
              
              // Check if localStorage has different mode (indicating it's outdated)
              const localMode = localStorage.getItem('theme');
              
              if (localMode && localMode !== serverMode) {
                  // Update localStorage with server mode
                  localStorage.setItem('theme', serverMode);
              }
              
              // Apply the server mode
              this.applyDisplayMode(serverMode);
              
              // Update radio buttons if they exist (they might not exist if modal isn't open yet)
              this.updateDisplayModeRadioButtons(serverMode);
          } else {
              // Fallback to localStorage if server fails
              this.loadDisplayModeFromLocalStorage();
          }
      } catch (error) {
          // Fallback to localStorage if server is unavailable
          this.loadDisplayModeFromLocalStorage();
      }
  }

  loadDisplayModeFromLocalStorage() {
      // Load from localStorage - use the same key as the existing dark mode system
      const savedMode = localStorage.getItem('theme') || 'light';
      
      // Apply the mode immediately
      this.applyDisplayMode(savedMode);
      
      // Update radio buttons if they exist
      this.updateDisplayModeRadioButtons(savedMode);
  }

  updateDisplayModeRadioButtons(mode) {
      const lightModeRadio = document.getElementById('lightMode');
      const darkModeRadio = document.getElementById('darkMode');

      if (lightModeRadio && darkModeRadio) {
          if (mode === 'dark') {
              darkModeRadio.checked = true;
          } else {
              lightModeRadio.checked = true;
          }
          
          // Update visual styles
          this.updateRadioButtonStyles();
      }
  }

  async saveDisplayMode() {
      const lightModeRadio = document.getElementById('lightMode');
      const darkModeRadio = document.getElementById('darkMode');
      
      if (!lightModeRadio || !darkModeRadio) {
          return;
      }

      const selectedMode = lightModeRadio.checked ? 'light' : 'dark';
      
      try {
          // Save to server
          const response = await fetch('api/theme_api.php?type=display_mode', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify({ theme: selectedMode })
          });
          
          const data = await response.json();
          
          if (data.success) {
              // Save to localStorage as backup
              localStorage.setItem('theme', selectedMode);
              
              // Apply the mode immediately
              this.applyDisplayMode(selectedMode);
              
              // Show success message
              this.showMessage('Display mode saved successfully!', 'success');
          } else {
              this.showMessage('Failed to save display mode: ' + data.message, 'error');
          }
      } catch (error) {
          // Fallback to localStorage if server fails
          localStorage.setItem('theme', selectedMode);
          this.applyDisplayMode(selectedMode);
          this.showMessage('Display mode saved locally (server unavailable)', 'warning');
      }
  }

  applyDisplayMode(mode) {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      
      // Only apply if the theme is different from current to avoid unnecessary DOM updates
      if (currentTheme !== mode) {
          if (mode === 'dark') {
              document.documentElement.setAttribute('data-theme', 'dark');
          } else {
              document.documentElement.setAttribute('data-theme', 'light');
          }
      }
  }

  updateRadioButtonStyles() {
      const lightModeRadio = document.getElementById('lightMode');
      const darkModeRadio = document.getElementById('darkMode');
      
      if (lightModeRadio && darkModeRadio) {
          // Remove checked class from both options
          lightModeRadio.closest('.radio-option')?.classList.remove('checked');
          darkModeRadio.closest('.radio-option')?.classList.remove('checked');
          
          // Add checked class to the selected option
          if (lightModeRadio.checked) {
              lightModeRadio.closest('.radio-option')?.classList.add('checked');
          } else if (darkModeRadio.checked) {
              darkModeRadio.closest('.radio-option')?.classList.add('checked');
          }
      }
  }

  isValidHexColor(color) {
      return /^#[0-9A-Fa-f]{6}$/.test(color);
  }

  showSetupMessage(message, type) {
      const messageDiv = document.getElementById('setupMessage');
      if (messageDiv) {
          messageDiv.textContent = message;
          messageDiv.className = `modal-message ${type}`;
          messageDiv.style.display = 'block';
          
          setTimeout(() => {
              messageDiv.style.display = 'none';
          }, 3000);
      }
  }

  showThemeMessage(message, type) {
      const messageDiv = document.getElementById('themeMessage');
      if (messageDiv) {
          messageDiv.textContent = message;
          messageDiv.className = `modal-message ${type}`;
          messageDiv.style.display = 'block';
          
          setTimeout(() => {
              messageDiv.style.display = 'none';
          }, 3000);
      }
  }
  
  async clearAllCaches() {
      try {
          // Clear Cache API caches (service workers, PWA caches)
          if ('caches' in window) {
              const cacheNames = await caches.keys();
              await Promise.all(
                  cacheNames.map(cacheName => caches.delete(cacheName))
              );
          }
          
          // Skip IndexedDB clearing due to browser extension conflicts
          
          // Clear localStorage and sessionStorage, but preserve notification tracking
          const notificationKey = 'shownNotifications_' + this.browserId;
          const shownNotifications = localStorage.getItem(notificationKey);
          const browserId = localStorage.getItem('browserId');
          
          localStorage.clear();
          sessionStorage.clear();
          
          // Restore browser ID and notification tracking to prevent notifications from reappearing
          if (browserId) {
              localStorage.setItem('browserId', browserId);
          }
          if (shownNotifications) {
              localStorage.setItem(notificationKey, shownNotifications);
          }
          
          // Clear any in-memory caches or cached data
          this.selectedArtist = null;
          this.selectedAlbum = null;
          this.selectedCoverUrl = null;
          this.selectedDiscogsReleaseId = null;
          
          // Force reload with cache-busting parameters and success message
          const currentUrl = new URL(window.location.href);
          currentUrl.searchParams.set('_cache_clear', Date.now());
          currentUrl.searchParams.set('cache_cleared', 'true');
          
          // Reload the page to ensure all resources are fresh
          window.location.href = currentUrl.toString();
          
      } catch (error) {
          console.error('Error clearing caches:', error);
          this.showMessage('Error clearing caches. Please try again.', 'error');
      }
  }
  
  handleSort(sortField) {
      // Determine sort direction
      if (this.currentSort.field === sortField) {
          // Toggle direction if same field
          this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
      } else {
          // New field, set default direction
          this.currentSort.field = sortField;
          this.currentSort.direction = 'asc';
      }
      
      // Update sort indicators
      this.updateSortIndicators();
      
      // Re-render albums with new sort
      this.renderAlbumsWithSort();
  }
  
  updateSortIndicators() {
      // Clear all sort indicators
      document.querySelectorAll('.sortable-header').forEach(header => {
          header.classList.remove('sort-asc', 'sort-desc');
      });
      
      // Add indicator to current sort field
      const currentHeader = document.querySelector(`[data-sort="${this.currentSort.field}"]`);
      if (currentHeader) {
          currentHeader.classList.add(`sort-${this.currentSort.direction}`);
      }
  }
  
  renderAlbumsWithSort() {
      // Get current albums from the table
      const tbody = document.querySelector('#albumsTable tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      // Extract album data from rows
      const albums = rows.map(row => {
          if (row.classList.contains('empty-state')) return null;
          
          const artistName = row.querySelector('.artist-name')?.textContent || '';
          const albumName = row.querySelector('.album-name a')?.textContent || '';
          const yearElement = row.querySelector('.year-link');
          const releaseYear = yearElement ? yearElement.dataset.year : '';
          const isOwned = row.querySelector('td:nth-child(4) .checkmark')?.textContent === '✓';
          const wantToOwn = row.querySelector('td:nth-child(5) .checkmark')?.textContent === '✓';
          
                                  return {
              id: row.dataset.id,
              artist_name: artistName,
              album_name: albumName,
              release_year: releaseYear,
              is_owned: isOwned ? 1 : 0,
              want_to_own: wantToOwn ? 1 : 0,
              cover_url: row.querySelector('.album-cover')?.dataset.src || '',
              cover_url_medium: row.querySelector('.album-cover')?.dataset.medium || '',
              cover_url_large: row.querySelector('.album-cover')?.dataset.large || '',
              artist_type: row.dataset.artistType || null // Extract artist_type from data attribute
          };
      }).filter(album => album !== null);
      
      // Sort albums
      const sortedAlbums = this.sortAlbums(albums);
      
      // Re-render with sorted data
      this.renderAlbums(sortedAlbums);
  }
  
  sortAlbums(albums) {
      return albums.sort((a, b) => {
          // Ensure artist_type exists for backward compatibility
          const artistTypeA = a.artist_type || null;
          const artistTypeB = b.artist_type || null;
          
          if (this.currentSort.field === 'year') {
              // Sort by year first, then by artist
              const yearA = parseInt(a.release_year) || 0;
              const yearB = parseInt(b.release_year) || 0;
              
              if (this.currentSort.direction === 'desc') {
                  // Year descending, then artist ascending
                  if (yearA !== yearB) {
                      return yearB - yearA;
                  }
                  return this.getSortableArtistName(a.artist_name, artistTypeA).localeCompare(this.getSortableArtistName(b.artist_name, artistTypeB));
              } else {
                  // Year ascending, then artist ascending
                  if (yearA !== yearB) {
                      return yearA - yearB;
                  }
                  return this.getSortableArtistName(a.artist_name, artistTypeA).localeCompare(this.getSortableArtistName(b.artist_name, artistTypeB));
              }
          } else if (this.currentSort.field === 'album') {
              // Sort by artist first, then by year, then by album name
              const artistComparison = this.getSortableArtistName(a.artist_name, artistTypeA).localeCompare(this.getSortableArtistName(b.artist_name, artistTypeB));
              
              if (this.currentSort.direction === 'desc') {
                  // Artist descending, then year ascending, then album ascending
                  if (artistComparison !== 0) {
                      return -artistComparison; // Reverse for descending
                  }
                  // Same artist, sort by year ascending
                  const yearA = parseInt(a.release_year) || 0;
                  const yearB = parseInt(b.release_year) || 0;
                  if (yearA !== yearB) {
                      return yearA - yearB; // Year ascending
                  }
                  // Same year, sort by album name ascending
                  return this.getSortableAlbumName(a.album_name).localeCompare(this.getSortableAlbumName(b.album_name));
              } else {
                  // Artist ascending, then year ascending, then album ascending
                  if (artistComparison !== 0) {
                      return artistComparison;
                  }
                  // Same artist, sort by year ascending
                  const yearA = parseInt(a.release_year) || 0;
                  const yearB = parseInt(b.release_year) || 0;
                  if (yearA !== yearB) {
                      return yearA - yearB; // Year ascending
                  }
                  // Same year, sort by album name ascending
                  return this.getSortableAlbumName(a.album_name).localeCompare(this.getSortableAlbumName(b.album_name));
              }
          } else {
              // Default sort by artist
              return this.getSortableArtistName(a.artist_name, artistTypeA).localeCompare(this.getSortableArtistName(b.artist_name, artistTypeB));
          }
      });
  }
  
  getSortableArtistName(artistName, artistType = null) {
      if (!artistName) return '';
      
      // Remove common prefixes and trim
      const name = artistName.trim();
      const lowerName = name.toLowerCase();
      
      // Special case for Elvis Costello & The Attractions
      if (lowerName.includes('elvis costello') && lowerName.includes('attractions')) {
          return 'Costello, Elvis & The Attractions';
      }
      
      // List of common prefixes to ignore
      const prefixes = ['the ', 'a ', 'an '];
      
      for (const prefix of prefixes) {
          if (lowerName.startsWith(prefix)) {
              return name.substring(prefix.length).trim();
          }
      }
      
      // Use Discogs artist type if available
      if (artistType) {
          if (artistType.toLowerCase() === 'group') {
              // It's a band, sort by first word (after removing prefixes)
              return name;
          } else if (artistType.toLowerCase() === 'person') {
              // It's a person, try to sort by last name
              const parts = name.split(' ');
              if (parts.length === 2) {
                  const firstName = parts[0];
                  const lastName = parts[1];
                  
                  // Check if the last word is a common suffix
                  const commonSuffixes = ['jr', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
                  if (commonSuffixes.includes(lastName.toLowerCase()) && parts.length > 2) {
                      // If last word is a suffix, use the second-to-last word as surname
                      return parts[parts.length - 2] + ', ' + parts.slice(0, -2).join(' ') + ' ' + parts[parts.length - 1];
                  } else {
                      // Use the last word as surname
                      return lastName + ', ' + firstName;
                  }
              }
              // For single names or complex names, sort as-is
              return name;
          }
      }
      
      // Fallback to heuristic detection if no artist type is available
      const parts = name.split(' ');
      
      // Indicators that this is likely a band name, not a person
      const bandIndicators = ['&', 'and', 'featuring', 'feat', 'ft', 'with', 'vs', 'versus'];
      const hasBandIndicator = bandIndicators.some(indicator => 
          lowerName.includes(indicator)
      );
      
      // Check if the last word is a number (like "500" in "Galaxie 500")
      const lastWord = parts[parts.length - 1];
      const hasNumberSuffix = /^\d+$/.test(lastWord);
      
      // Check if it looks like a band name (multiple words, no clear first/last name pattern)
      const isLikelyBand = hasBandIndicator || hasNumberSuffix || parts.length > 3;
      
      if (!isLikelyBand && parts.length === 2) {
          // Likely a person with first and last name
          const firstName = parts[0];
          const lastName = parts[1];
          
          // Check if the last word is a common suffix
          const commonSuffixes = ['jr', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
          if (commonSuffixes.includes(lastName.toLowerCase()) && parts.length > 2) {
              // If last word is a suffix, use the second-to-last word as surname
              return parts[parts.length - 2] + ', ' + parts.slice(0, -2).join(' ') + ' ' + parts[parts.length - 1];
          } else {
              // Use the last word as surname
              return lastName + ', ' + firstName;
          }
      }
      
      // For band names or unclear cases, just return the name as-is (after removing prefixes)
      return name;
  }
  
  getSortableAlbumName(albumName) {
      if (!albumName) return '';
      
      // Remove common prefixes and trim
      const name = albumName.trim();
      const lowerName = name.toLowerCase();
      
      // List of common prefixes to ignore
      const prefixes = ['the ', 'a ', 'an '];
      
      for (const prefix of prefixes) {
          if (lowerName.startsWith(prefix)) {
              return name.substring(prefix.length).trim();
          }
      }
      
      return name;
  }


}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
  window.app = new MusicCollectionApp();
  
  // Check if we're on the setup page
  if (document.body.classList.contains('setup-page')) {
    // Only initialize setup page functionality
    await window.app.initSetupPage();
  } else {
    // Initialize main app functionality
    await window.app.init();
  }
}); 