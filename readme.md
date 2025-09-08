# Music Collection Manager

A modern PHP CRUD application for managing your music collection with database integration, search functionality, autocomplete features, and external API integration.

## Features

- **Complete CRUD Operations**: Add, edit, delete, and view albums
- **Search & Filter**: Search by artist or album name, filter by owned/wanted status
- **Smart Autocomplete**: Enhanced autocomplete with Discogs API integration
- **Format Filtering**: Filter album search results by format (Vinyl, CD, Cassette, Digital, etc.)
- **Cover Art Display**: Automatic cover art retrieval and display with local image proxy
- **Tracklist Information**: View detailed tracklists for albums with producer and rating data
- **Lyrics Integration**: Search for lyrics with direct links to Genius and Google Search
- **Artist Website Links**: Direct links to artist's official website and social media profiles
- **Password Protection**: Secure authentication for add/edit/delete operations
- **Statistics Dashboard**: View collection statistics at a glance
- **Responsive Design**: Works on desktop and mobile devices
- **Modern UI**: Clean, intuitive interface with smooth animations and dropdown menus
- **Smart Sorting**: Intelligent artist sorting (ignores articles, sorts individuals by last name)
- **Lazy Loading**: Optimized image loading for better performance
- **Accessibility**: WCAG compliant with proper contrast ratios
- **SEO Optimized**: Meta tags and structured data
- **Star Rating System**: Visual star ratings with quarter, half, and three-quarter precision
- **Reviews Integration**: Clickable review counts linking to Discogs reviews section
- **Image Proxy**: Local image serving to avoid rate limiting issues
- **Back/Forward Cache**: Optimized for browser navigation performance
- **Enhanced Dropdown Menu**: Settings menu with login/logout, reset password, and configuration options
- **Theme Customization**: Customizable background gradient colors with cross-device persistence
- **Display Mode Preference**: Light/Dark mode toggle with server-side persistence across browsers
- **Cache Management**: Clear all caches to refresh data and resolve stale information issues
- **Dedicated Setup Page**: Comprehensive setup and configuration page with tabbed interface
- **Advanced Settings**: Granular control over artist links and tracklist display options
- **Checkbox Interface**: Modern checkbox interface with Select All/None buttons for intuitive settings management
- **Tracklist Customization**: Show/hide specific tracklist elements (producer, label, released date, rating, format, lyrics)
- **Artist Link Management**: Selectively show/hide artist social media and website links
- **Statistics Display Control**: Granular control over collection statistics display
- **Chart Display Management**: Show/hide individual charts in sidebar and modal
- **Button Count Display**: Control whether album counts appear in filter buttons
- **Modal Section Control**: Show/hide individual sections in Collection Statistics modal
- **Dropdown Option Management**: Automatically hide Collection Statistics option when no sections are visible
- **Settings Persistence**: All preferences saved to localStorage with cross-session persistence

## Database Schema

The application stores the following information for each album:

- **Artist Name** (required)
- **Album Name** (required)
- **Release Year** (optional)
- **Owned Status** (boolean)
- **Want to Own Status** (boolean)
- **Cover URL** (optional - automatically retrieved)
- **Created Date** (auto-generated)
- **Updated Date** (auto-generated)

## Installation

### Database

The application uses a JSON file-based database system (`SimpleDB`) for broadest compatibility with shared hosting environments. No MySQL setup required.

### Configuration

1. Install repo and make sure that file permissions are set correctly (755 for directories, 644 for files)

2. Go to setup.php (or click on gear icon on index.php) to access the comprehensive setup page.

3. Configure your application using the tabbed interface:
   - **API Config**: Add your Discogs API Key
   - **Password**: Set your password (initial password is admin123)
   - **Display Mode**: Choose between Light and Dark mode
   - **Album Display**: Customize album information and artist links display options
   - **Stats Display**: Control collection statistics, charts, and modal display options

### File Structure

```
personal_site/
├── components/
│   └── reset_password_modal.php     # Reusable reset password modal component
├── config/
│   ├── api_config.php               # API keys and settings
│   ├── auth_config.php              # Authentication settings
│   └── database.php                 # Database configuration (SimpleDB)
├── models/
│   └── MusicCollection.php          # Database operations
├── services/
│   ├── DiscogsAPIService.php        # Discogs API integration
│   ├── ImageOptimizationService.php # Image optimization
│   └── LyricsService.php            # Lyrics search integration
├── api/
│   ├── music_api.php                # Main API endpoints
│   ├── tracklist_api.php            # Tracklist API
│   └── image_proxy.php              # Image proxy for external images
├── assets/
│   ├── css/
│   │   ├── style.css                # Application styles
│   │   └── style.min.css            # Application styles (minified)
│   └── js/
│       ├── app.js                   # Frontend functionality
│       └── app.min.js               # Frontend functionality (minified)
├── data/
│   ├── music_collection.json        # JSON database file
│   ├── theme.json                   # Theme color preferences
│   └── display_mode.json            # Display mode preferences
├── index.php                        # Main application page
├── setup.php                        # Comprehensive setup and configuration page
├── reset_password.php               # Reset password page
└── README.md                        # This file
```

## Usage

### Adding Albums

1. Click the "+ Add Album" button
2. Enter the artist name (autocomplete will suggest artists from Discogs)
3. Enter the album name (autocomplete will suggest albums by the selected artist)
4. Optionally select a format filter to limit search results (Vinyl, CD, Cassette, Digital, 7", 12", LP, EP, or All Formats)
5. Optionally enter the release year (will be automatically populated when selecting an album but can be edited)
6. Select either "I own this album" or "I want to own this album" (radio buttons)
7. Click "Save Album"

### Enhanced Features

- **Format Filtering**: Filter album search results by format to find specific releases (Vinyl, CD, Cassette, Digital, 7", 12", LP, EP, or All Formats)
- **Cover Art**: Automatically retrieved and displayed for albums with local image proxy
- **Tracklist View**: Click on album titles to view detailed tracklists with producer and rating information
- **Lyrics Search**: Click "Lyrics" buttons next to tracks to search for lyrics on Genius or Google
- **Cover Art Modal**: Click on cover images to view larger versions
- **Duplicate Prevention**: System prevents adding duplicate albums
- **Smart Sorting**: Artists are sorted intelligently (ignoring articles, sorting individuals by last name)
- **Star Ratings**: Visual star ratings with quarter, half, and three-quarter precision
- **Reviews Integration**: Clickable review counts that link directly to Discogs reviews section
- **Settings Dropdown**: Gear icon menu with login/logout, reset password, and configuration options
- **Theme Customization**: Customize background gradient colors with dual input methods (visual picker and hex input)
- **Display Mode Preference**: Choose between light and dark mode with server-side persistence
- **Cross-Device Sync**: Theme colors and display mode persist across all browsers and devices
- **Back/Forward Cache**: Optimized for smooth browser navigation
- **Dedicated Setup Page**: Comprehensive setup page with tabbed interface for all configuration options
- **Advanced Settings**: Granular control over what information is displayed in your collection
- **Checkbox Interface**: Modern checkbox interface with Select All/None buttons for intuitive settings management
- **Tracklist Customization**: Show/hide specific tracklist elements (producer, label, released date, rating, format, lyrics)
- **Artist Link Management**: Selectively show/hide artist social media and website links
- **Statistics Display Control**: Control collection statistics, charts, and modal display options
- **Settings Persistence**: All preferences automatically saved and restored across sessions

### Searching and Filtering

- **Search**: Use the search box to find albums by artist or album name
- **Filter**: Use the filter buttons to show:
  - All Albums
  - Albums you own
  - Albums you want to own

### Editing Albums

1. Click the "Edit" button next to any album
2. Modify the information as needed
3. Click "Save Album"

### Advanced JSON Editing ⚠️

**⚠️ WARNING: This is an advanced feature for experienced users only. Incorrect JSON editing can corrupt your data.**

The application includes a powerful JSON editor that allows you to directly edit the raw album data. This feature is useful for:
- Fixing data inconsistencies
- Adding missing metadata
- Correcting format information
- Updating artist types
- Modifying cover URLs

#### How to Use JSON Editing:

1. **Edit an album** by clicking the "Edit" button
2. **Click "View Record"** to open the JSON data modal
3. **Click "Edit JSON"** to enable editing mode
4. **Make your changes** directly in the JSON
5. **Click "Save Changes"** to update the database

#### Protected Fields:
- **`id`**: Cannot be changed (prevents data conflicts)

#### Editable Fields:
- `artist_name`, `album_name`, `release_year`
- `is_owned`, `want_to_own`
- `cover_url`, `cover_url_medium`, `cover_url_large`
- `discogs_release_id`, `style`, `format`, `artist_type`
- `tracklist`

#### Important Safety Notes:

⚠️ **CRITICAL WARNINGS:**
- **Backup your data** before using JSON editing
- **Maintain valid JSON format** - invalid JSON will not save
- **Required fields** (`artist_name`, `album_name`) must be present
- **ID field is protected** - changing it will cause an error
- **Test your changes** on a single album first
- **Errors appear in the modal** - read them carefully before proceeding

#### Common JSON Editing Tasks:

**Fix Artist Name:**
```json
{
  "artist_name": "Corrected Artist Name",
  "album_name": "Album Name"
}
```

**Update Style Information:**
```json
{
  "style": "Alternative Rock, Indie Rock"
}
```

**Add Missing Cover URL:**
```json
{
  "cover_url": "https://example.com/cover.jpg"
}
```

**Correct Format Information:**
```json
{
  "format": "Vinyl, LP, Album"
}
```

#### Error Handling:
- **Invalid JSON**: Shows syntax error in modal
- **Missing Required Fields**: Shows validation error
- **ID Changes**: Shows protection error
- **API Errors**: Shows database error

**If you encounter errors:**
1. Check the error message in the modal
2. Verify JSON syntax is valid
3. Ensure required fields are present
4. Do not change the `id` field
5. Click "Cancel Edit" to revert changes

**Recommendation**: Only use JSON editing if you're comfortable with JSON syntax and understand the data structure. For regular editing, use the standard edit form instead.

### Deleting Albums

1. Click the "Delete" button next to any album
2. Confirm the deletion (requires authentication)

### Setup and Configuration

The application includes a comprehensive setup page (`setup.php`) with a modern tabbed interface for all configuration options:

#### Accessing Setup
- **Direct Access**: Navigate to `setup.php` in your browser
- **From Main App**: Click the settings gear icon and select "Setup & Configuration"

#### Setup Page Tabs

**API Config Tab:**
- Configure your Discogs API Key
- Test API connectivity
- View API usage statistics

**Password Tab:**
- Change your application password
- Generate secure password hashes
- Reset password functionality

**Display Mode Tab:**
- Choose between Light and Dark mode
- Preview theme changes in real-time
- Server-side persistence across devices

**Album Display Tab:**
- **Album Information**: Control which album information is displayed
  - Show/Hide Label information
  - Show/Hide Format information
  - Show/Hide Producer information
  - Show/Hide Released date
  - Show/Hide Rating and reviews
  - Show/Hide Lyrics links
  - Select All/Select None buttons for quick management
- **Artist Information Display**: Control which artist links are shown
  - Facebook, Twitter, Instagram, YouTube, Bandcamp, SoundCloud
  - Wikipedia, Last.fm, IMDb, Bluesky, Discogs, Official Website
  - Select All/Select None buttons for quick management

**Stats Display Tab:**
- **Collection Statistics**: Control button count display
  - Show/Hide Total Albums Count in filter buttons
  - Show/Hide Owned Albums Count in filter buttons
  - Show/Hide Wanted Albums Count in filter buttons
  - Select All/Select None buttons for quick management
- **Chart Display Options**: Control sidebar charts (desktop only)
  - Show/Hide Top 10 Years Chart
  - Show/Hide Top 10 Styles Chart
  - Show/Hide Top 10 Formats Chart
  - Show/Hide Top 10 Labels Chart
  - Select All/Select None buttons for quick management
- **Collection Statistics Modal**: Control modal sections
  - Show/Hide Top Music Styles in modal
  - Show/Hide Top Music Years in modal
  - Show/Hide Top Music Formats in modal
  - Show/Hide Top Music Labels in modal
  - Select All/Select None buttons for quick management
  - Collection Statistics dropdown option automatically hidden when all sections are disabled

#### Settings Features
- **Checkbox Interface**: Modern checkbox interface with Select All/None buttons for intuitive control
- **Real-time Preview**: Changes apply immediately when saved
- **Cross-session Persistence**: All settings saved to localStorage
- **Reset to Defaults**: One-click reset for all settings
- **Success Messages**: Confirmation when settings are saved
- **Smart Layout Management**: Sidebar automatically hides when all charts are disabled, extending main content to full width
- **Dynamic Dropdown Options**: Collection Statistics option automatically appears/disappears based on modal section settings

### Customizing Theme Colors

1. Click the settings gear icon in the top-right corner
2. Select "Setup & Configuration"
3. Navigate to the "Display Mode" tab
4. Scroll down to the "Theme Customization" section
5. Use either:
   - **Visual Color Picker**: Click the color box to open the browser's color picker
   - **Hex Input**: Manually type hex color codes (e.g., `#ff6b6b`)
6. Colors update in real-time as you change them
7. Click "Save Theme" to persist your changes
8. Click "Reset to Default" to return to the original colors

**Note**: Theme colors are automatically synced across all devices and browsers. Changes made on one device will appear on all others.  Please use colors with proper color contrast by testing it out first in a tool such as https://webaim.org/resources/contrastchecker/

### Setting Display Mode Preference

1. Click the settings gear icon in the top-right corner
2. Select "Setup & Configuration"
3. Navigate to the "Display Mode" tab
4. Choose between:
   - **Light Mode**: Traditional light theme with customizable gradient background
   - **Dark Mode**: Dark theme optimized for low-light viewing
5. Click "Save Display Mode" to apply and persist your preference
6. Your display mode preference will be saved server-side and persist across all browsers and devices

**Note**: The gradient background colors only apply in light mode. Dark mode uses a fixed dark color scheme for optimal readability.

### Cache Management

The application uses caching to improve performance and reduce API calls. If you experience stale data or unexpected behavior:

1. Click the settings gear icon in the top-right corner
2. Select "Clear Caches" from the dropdown menu
3. The system will:
   - Clear Cache API caches (service workers, PWA caches)
   - Clear localStorage and sessionStorage (theme preferences, user settings)
   - Reset in-memory cached data (selected artists, albums, cover URLs)
   - Force a page reload with cache-busting parameters
   - Display a success message when complete

**When to use Clear Caches**:
- After editing albums to ensure fresh Discogs data
- When album information seems outdated
- To troubleshoot unexpected behavior
- For performance maintenance
- When theme changes aren't applying correctly
- To force refresh of all cached resources

## API Endpoints

The application provides RESTful API endpoints for all operations:

### GET Requests

- `api/music_api.php?action=albums` - Get total
- `api/music_api.php?action=albums&filter=owned` - Get owned albums
- `api/music_api.php?action=albums&search=search_term` - Search albums
- `api/music_api.php?action=album&id=1` - Get specific album
- `api/music_api.php?action=artists&search=search_term` - Get artists for autocomplete
- `api/music_api.php?action=albums_by_artist&artist=artist_name&format=format` - Get albums by artist with format filter
- `api/music_api.php?action=stats` - Get collection statistics
- `api/music_api.php?action=auth_status` - Check authentication status
- `api/theme_api.php` - Get theme colors

### POST Requests

- `api/music_api.php?action=add` - Add new album (requires authentication)
- `api/music_api.php?action=update` - Update existing album (requires authentication)
- `api/music_api.php?action=delete` - Delete album (requires authentication)
- `api/music_api.php?action=login` - Authenticate user
- `api/music_api.php?action=logout` - Logout user
- `api/theme_api.php` - Save theme colors (requires authentication)

### Tracklist API

- `api/tracklist_api.php?artist=artist_name&album=album_name` - Get detailed tracklist

## Features in Detail

### Enhanced Autocomplete System

The application provides intelligent autocomplete with Discogs API integration:
- **Artist Names**: Suggests artists from Discogs database
- **Album Names**: Suggests albums by the selected artist with cover art
- **Format Tracking**: Automatically retrieves and stores format information from Discogs
- **Release Years**: Automatically populated from API data (can be edited if needed)
- **Cover Art**: Automatically retrieved and displayed

#### Format Consolidation in Pie Chart vs. Collection Statistics

The application provides two different views of format data with different consolidation strategies:

**Collection Statistics Modal (Format List):**
- Shows all individual formats as they appear in your collection
- Each format is displayed separately with its exact count
- Useful for detailed analysis of your collection's format breakdown
- Clicking on any format filters your collection to show only albums with that specific format

**Top 10 Formats Pie Chart (Footer):**
- Consolidates similar formats into meaningful groups for better visualization
- **"LP"** combines "LP", "Album", "Reissue", "Remastered", and "Repress" (since they all represent vinyl LP records, including re-releases)
- **"Single"** combines "Single" and "Maxi-Single" (both are single releases)
- **"Stereo"** and **"Vinyl"** are excluded entirely (not meaningful format distinctions - Stereo is standard for modern releases, Vinyl is the material that includes all vinyl formats and is assumed is the bulk of your collection)
- **Note**: Tooltips show only percentage (not album count) to avoid confusion from double-counting albums that have multiple format labels. Percentages represent the visual proportion of each pie slice, not the percentage of total albums in your collection.
- Clicking on consolidated pie pieces filters your collection to show albums with any of the underlying formats

**Why This Difference?**
- The Collection Statistics modal provides granular, detailed information for precise filtering
- The pie chart consolidates formats to create a cleaner, more meaningful visual representation
- Some formats like "Stereo" are excluded from the chart because they don't represent meaningful musical distinctions (most modern releases are stereo)
- Consolidated formats represent the same musical concept but may be labeled differently in Discogs data
- **Important**: Some albums have multiple format labels (e.g., "LP, Album, Stereo"), so consolidating formats can result in double-counting. For this reason, pie chart tooltips show only percentages, not absolute counts. The percentages represent the visual proportion of each pie slice relative to the total pie chart, not the percentage of albums in your collection.

**Example:**
- If you have 3 albums labeled "LP", 2 labeled "Album", and 1 labeled "Reissue", the pie chart will show one "LP" slice representing all 6 vinyl LP records
- The Collection Statistics modal will show separate entries: "LP (3)", "Album (2)", and "Reissue (1)"
- Clicking on the "LP" pie piece will show all 6 albums, while clicking on individual formats in the modal will show only albums with those specific labels

### Cover Art Integration

- **Automatic Retrieval**: Cover art is automatically fetched from Discogs
- **Multiple Sizes**: Thumbnail, medium, and large image sizes
- **Lazy Loading**: Images load only when visible for better performance
- **Fallback Handling**: Graceful handling when cover art is unavailable
- **HTTPS Enforcement**: All images use secure HTTPS URLs

### Tracklist Information

- **Detailed Tracklists**: View complete track information including durations
- **Album Metadata**: Release year, format, producer information, and community ratings
- **Star Rating Display**: Visual star ratings with quarter, half, and three-quarter precision
- **Reviews Integration**: Clickable review counts linking to Discogs reviews section
- **Discogs Integration**: Direct links to Discogs pages
- **Modal Display**: Clean modal interface for tracklist viewing
- **Release Date Handling**: Smart display that hides dates when only year is known
- **Lyrics Integration**: Search for lyrics with dropdown buttons linking to Genius and Google Search
- **Smart Track Detection**: Lyrics buttons only appear on actual tracks, not section headers
- **Customizable Display**: Show/hide specific tracklist elements based on your preferences
- **Toggle Controls**: Modern toggle switches to control what information is displayed
- **Settings Persistence**: Tracklist display preferences saved across sessions

### Artist Website Links

The application automatically fetches and displays relevant artist website links from the Discogs database:

- **Official Website**: Artist's primary website (supports international domains like .co.uk, .de, .fr, etc.)
- **Social Media**: Facebook, Twitter, Instagram, YouTube, Bluesky
- **Music Platforms**: Bandcamp, SoundCloud
- **Reference Sites**: Wikipedia, Last.fm, IMDb
- **Artist Profile**: Direct link to artist's Discogs page

**Features:**
- **Smart Filtering**: Only shows the most useful and relevant website types
- **International Support**: Recognizes official websites with country-specific domains
- **Clean Interface**: No generic "Website" labels - every link is specifically categorized
- **Automatic Detection**: Uses Discogs API to find and categorize artist links
- **Modal Integration**: Artist links appear at the bottom of tracklist modals
- **Customizable Display**: Show/hide specific artist links based on your preferences
- **Toggle Controls**: Modern toggle switches to control which links are displayed
- **Select All/None**: Quick buttons to enable or disable all artist links at once
- **Settings Persistence**: Artist link preferences saved across sessions

### Smart Artist Sorting

The application intelligently sorts artists:
- **Band Names**: Ignores articles ("The Beatles" → "Beatles")
- **Individual Artists**: Sorts by last name ("Aaron Dilloway" → "Dilloway, Aaron")
- **Edge Cases**: Handles single names, complex names, and special characters

### Authentication System

- **Password Protection**: Secure authentication for sensitive operations
- **Session Management**: Proper session handling and timeout
- **Brute Force Protection**: Rate limiting for login attempts
- **Secure Storage**: Password hashes stored securely

### Theme Customization System

- **Dual Input Methods**: Visual color picker and manual hex input for maximum flexibility
- **Real-Time Preview**: Colors update immediately when changed
- **Cross-Device Persistence**: Colors sync across all browsers and devices
- **Hybrid Storage**: localStorage for fast access + server storage for cross-device sync
- **No Flash Loading**: Server-side CSS prevents flash of default colors
- **Smart Sync Logic**: Server colors take priority over outdated localStorage
- **Graceful Fallback**: Works even if server is unavailable
- **Validation**: Hex color format validation with visual feedback

### Display Mode System

- **Server-Side Persistence**: Display mode preference stored server-side for cross-browser consistency
- **No Flash Loading**: Theme applied server-side to prevent flash of wrong theme
- **Automatic Sync**: Display mode preference syncs across all browsers and devices
- **Hybrid Storage**: Server storage with localStorage fallback for reliability
- **Smart Loading**: JavaScript loads server preference first, then updates UI accordingly
- **Graceful Fallback**: Works even if server is unavailable
- **Unified Management**: Display mode managed through setup modal for consistency

### Advanced Settings System

The application includes a comprehensive settings system with granular control over display options:

#### Settings Categories

**Album Information Display:**
- Control which album information is displayed in tracklist modals
- Show/Hide Label information
- Show/Hide Format information
- Show/Hide Producer information
- Show/Hide Released date
- Show/Hide Rating and reviews
- Show/Hide Lyrics links
- Select All/Select None buttons for quick management

**Artist Information Display:**
- Control which artist links are shown in tracklist modals
- Toggle individual social media platforms (Facebook, Twitter, Instagram, YouTube, etc.)
- Toggle music platforms (Bandcamp, SoundCloud)
- Toggle reference sites (Wikipedia, Last.fm, IMDb, Bluesky)
- Toggle official website and Discogs artist profile links
- Select All/Select None buttons for quick management

**Collection Statistics Display:**
- Control whether album counts appear in filter buttons (Owned, Want, Total)
- Show/Hide Total Albums Count in filter buttons
- Show/Hide Owned Albums Count in filter buttons
- Show/Hide Wanted Albums Count in filter buttons
- Select All/Select None buttons for quick management

**Chart Display Options:**
- Control which charts appear in the desktop sidebar
- Show/Hide Top 10 Years Chart
- Show/Hide Top 10 Styles Chart
- Show/Hide Top 10 Formats Chart
- Show/Hide Top 10 Labels Chart
- Select All/Select None buttons for quick management
- Sidebar automatically hides when all charts are disabled

**Collection Statistics Modal:**
- Control which sections appear in the Collection Statistics modal
- Show/Hide Top Music Styles in modal
- Show/Hide Top Music Years in modal
- Show/Hide Top Music Formats in modal
- Show/Hide Top Music Labels in modal
- Select All/Select None buttons for quick management
- Collection Statistics dropdown option automatically hidden when all sections are disabled

#### Settings Features

- **Checkbox Interface**: Modern checkbox interface with Select All/None buttons for all settings
- **Real-time Application**: Changes apply immediately when settings are saved
- **Cross-session Persistence**: All settings saved to localStorage
- **Reset to Defaults**: One-click reset for all settings
- **Success Feedback**: Confirmation messages when settings are saved
- **Smart Defaults**: Sensible default values for all settings
- **Conditional Display**: Settings only affect relevant parts of the interface
- **Smart Layout Management**: Sidebar automatically hides when all charts are disabled, extending main content to full width
- **Dynamic Dropdown Options**: Collection Statistics option automatically appears/disappears based on modal section settings

### Mobile Optimization

- **Touch-Friendly**: Large touch targets for mobile devices
- **Responsive Design**: Optimized layout for all screen sizes
- **Touch Events**: Proper touch event handling for close buttons
- **Mobile Actions**: Dedicated mobile action buttons

### Performance Features

- **Lazy Loading**: Images load only when visible
- **Debounced Search**: Reduced API calls with intelligent debouncing
- **Optimized Images**: Multiple image sizes for different contexts
- **Image Proxy**: Local image serving to avoid rate limiting and improve reliability
- **Back/Forward Cache**: Optimized browser navigation with proper session handling
- **Efficient Database**: JSON-based storage optimized for shared hosting

### Rate Limiting & Caching System

- **Smart Rate Limiting**: 1-second base delay between API requests with automatic retry logic
- **Progressive Retry Strategy**: Automatic retries at 1, 3, and 6-second intervals when rate limited
- **In-Memory Caching**: 1-hour cache for release and master release data to minimize API calls
- **Graceful Degradation**: User-friendly error messages without exposing technical API details
- **Background Enhancement**: Master year fetching works asynchronously without blocking the UI
- **Automatic Recovery**: System automatically recovers from temporary rate limit issues

### Accessibility Features

- **WCAG Compliance**: Proper contrast ratios and color schemes
- **Keyboard Navigation**: Full keyboard accessibility
- **Screen Reader Support**: Proper ARIA labels and semantic HTML
- **Focus Management**: Proper focus handling in modals

## Security Features

- **SQL Injection Protection**: All database queries use prepared statements
- **XSS Protection**: Output is properly escaped
- **Input Validation**: Server-side validation for all inputs
- **CSRF Protection**: Form tokens and proper request handling
- **Authentication**: Password-protected sensitive operations
- **HTTPS Enforcement**: All external resources use HTTPS
- **Setup Page Protection**: Setup and configuration page requires authentication
- **Session Management**: Proper session handling and timeout
- **File Access Protection**: `.htaccess` rules deny direct access to sensitive files:
  - JSON configuration files (`*.json`)
  - Markdown documentation files (`*.md`)
  - README files (`README*`)
- **Unified Settings Security**: All application settings consolidated into protected `data/settings.json`

**Note**: File access protection via `.htaccess` only works on Apache servers. The PHP development server (`php -S`) doesn't process `.htaccess` files, so JSON files may be accessible during local development. This is normal and expected behavior.

### Common Issues

1. **Database Connection Error**
   - Verify the `data/` directory is writable
   - Check file permissions (755 for directories, 644 for files)

2. **API Errors**
   - Verify Discogs API key in `config/api_config.php`
   - Check browser console for JavaScript errors
   - Ensure API endpoints are accessible

3. **Authentication Issues**
   - Verify password hash in `config/auth_config.php`
   - Use `setup_password.php` to generate new hash
   - Check session configuration

4. **Cover Art Not Loading**
   - Verify Discogs API key is valid
   - Check network connectivity to Discogs
   - Ensure HTTPS is enforced for images
   - Check image proxy functionality in `api/image_proxy.php`

5. **Rate Limiting Issues**
   - The system now includes advanced rate limiting with automatic retry logic
   - API calls are automatically spaced 1 second apart with progressive retry delays
   - In-memory caching reduces repeated API calls for the same data
   - Check server logs for rate limit retry attempts
   - Verify Discogs API key has appropriate rate limits

6. **Back/Forward Cache Issues**
   - Ensure proper `rel="noopener noreferrer"` attributes on external links
   - Check session handling in `config/auth_config.php`
   - Verify no `window.open()` calls without proper attributes

7. **Mobile Close Buttons Not Working**
   - Verify touch event handling is enabled
   - Check for JavaScript errors in mobile browser
   - Ensure proper CSS touch targets

8. **Reviews Not Showing as Links**
   - Check Discogs API reviews endpoint functionality
   - Verify `has_reviews_with_content` field is being set correctly
   - Check browser console for debugging information

9. **Master Year Not Updating**
   - Master year fetching works asynchronously in the background
   - Check browser console for any API errors
   - Verify the album has a valid Discogs release ID stored
   - Rate limiting may delay master year updates - check server logs

10. **Tracklist Modal Errors**
    - The system now shows user-friendly error messages instead of technical API errors
    - Check server logs for detailed error information
    - Verify Discogs API key is valid and has appropriate permissions

11. **Lyrics Buttons Not Appearing**
    - Lyrics buttons only appear on actual tracks, not section headers
    - Verify track has a valid position and title
    - Check that LyricsService.php is properly included
    - Ensure tracklist API is returning enhanced tracklist data

12. **Theme Colors Not Syncing**
    - Verify `data/theme.json` file is writable (644 permissions)
    - Check browser console for theme API errors
    - Ensure `api/theme_api.php` is accessible
    - Clear browser cache if colors are stuck on old values
    - Verify server-side theme loading in `index.php` is working

13. **Theme Colors Flash on Page Load**
    - Server-side CSS should prevent flash - check inline styles in `index.php`
    - Verify `data/theme.json` exists and contains valid JSON
    - Check that theme colors are being loaded server-side before HTML output

14. **Display Mode Not Persisting Across Browsers**
    - Verify `data/display_mode.json` file is writable (644 permissions)
    - Check browser console for display mode API errors
    - Ensure `api/theme_api.php?type=display_mode` is accessible
    - Verify server-side display mode loading in `index.php` is working
    - Check that `data-theme` attribute is being set on `<html>` element server-side

15. **Artist Website Links Not Appearing**
    - Verify Discogs API key is valid and has sufficient rate limits
    - Check that `DiscogsAPIService.php` is properly included in `tracklist_api.php`
    - Ensure artist has website information in Discogs database
    - Check browser console for API errors when opening tracklist modal
    - Verify `getArtistWebsite()` method is being called in tracklist API
    - Clear Discogs API cache if links are not updating

16. **Artist Website Links Showing Generic "Website" Labels**
    - This has been resolved - the system now only shows specifically categorized links
    - If you see generic labels, clear the Discogs API cache
    - Verify the artist's official website matches common domain patterns

17. **Display Mode Radio Buttons Show Wrong State**
    - Display mode should be loaded from server on page load
    - Check that `loadDisplayMode()` function is being called during initialization
    - Verify `updateDisplayModeRadioButtons()` is updating the correct radio button
    - Ensure setup modal is properly loading display mode preference

18. **Settings Not Persisting**
    - Verify localStorage is enabled in your browser
    - Check browser console for JavaScript errors when saving settings
    - Ensure "Save Settings" button is being clicked after making changes
    - Clear browser cache if settings seem stuck on old values

19. **Toggle Switches Not Working**
    - Check that JavaScript is enabled in your browser
    - Verify the setup page is loading correctly
    - Check browser console for JavaScript errors
    - Ensure you're on the correct tab (Settings tab) in the setup page

20. **Tracklist Elements Not Hiding**
    - Verify settings are saved by checking the success message
    - Refresh the page after saving settings
    - Check that the correct toggle switches are turned off
    - Clear browser cache if changes aren't applying

21. **Artist Links Not Respecting Settings**
    - Verify artist link settings are saved in the Settings tab
    - Check that the artist has the specific link types enabled
    - Clear Discogs API cache if links aren't updating
    - Ensure you're viewing a tracklist modal (not the main collection page)

### Performance Tips

- The JSON database is optimized for read/write operations
- Search queries are optimized with intelligent filtering
- Images are lazy-loaded for better performance
- API calls are debounced to reduce server load
- Image proxy provides 24-hour caching for external images
- Back/forward cache optimization improves navigation performance
- Advanced rate limiting with 1-second delays and automatic retry logic prevents API throttling
- In-memory caching reduces repeated API calls for release and master release data
- Graceful error handling ensures smooth user experience even when API calls fail

## Recent Updates

### Version 2.0 - Enhanced Settings & Statistics Control

**New Features:**
- **Comprehensive Statistics Display Control**: Granular control over all collection statistics and charts
- **Smart Layout Management**: Sidebar automatically hides when all charts are disabled, extending main content to full width
- **Dynamic Dropdown Options**: Collection Statistics option automatically appears/disappears based on modal section settings
- **Enhanced Setup Interface**: Converted from toggle switches to checkbox interface with Select All/None buttons
- **Button Count Display Control**: Control whether album counts appear in filter buttons (Owned, Want, Total)
- **Chart Display Management**: Individual control over each chart in sidebar (Years, Styles, Formats, Labels)
- **Modal Section Control**: Individual control over each section in Collection Statistics modal
- **Consistent UI Design**: Unified checkbox interface across all settings sections

**Setup Page Improvements:**
- **Album Display Tab**: Control album information and artist links with Select All/None buttons
- **Stats Display Tab**: Control collection statistics, charts, and modal display with Select All/None buttons
- **Improved Navigation**: Direct link to index.php from setup page
- **Enhanced Styling**: Consistent button styling across all sections

**Technical Improvements:**
- **Real-time Updates**: All settings changes apply immediately without page refresh
- **Smart Error Handling**: Graceful handling when settings are accessed from setup page vs. main collection page
- **Enhanced CSS**: Full-width layout support when sidebar is hidden
- **Optimized JavaScript**: Efficient event handling and settings management
- **Component Architecture**: Reusable modal components to eliminate code duplication
- **Enhanced Security**: Setup page now requires authentication to prevent unauthorized access

## License

This application is provided as-is for personal use. Feel free to modify and extend as needed.