# Music Collection Manager

A modern PHP CRUD application for managing your music collection with database integration, search functionality, autocomplete features, and external API integration.

## Features

- **Complete CRUD Operations**: Add, edit, delete, and view albums
- **Search & Filter**: Search by artist or album name, filter by owned/wanted status
- **Smart Autocomplete**: Enhanced autocomplete with Discogs API integration
- **Format Filtering**: Filter album search results by format (Vinyl, CD, Cassette, Digital, etc.)
- **Cover Art Display**: Automatic cover art retrieval and display with local image proxy
- **Tracklist Information**: View detailed tracklists for albums with producer and rating data
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
- **Cache Management**: Clear all caches to refresh data and resolve stale information issues

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

2. Go to setup.php (or click on gear icon on index.php).

3. Add your Discogs API Key and set your password (initial password is admin123)

### File Structure

```
personal_site/
├── config/
│   ├── api_config.php               # API keys and settings
│   ├── auth_config.php              # Authentication settings
│   └── database.php                 # Database configuration (SimpleDB)
├── models/
│   └── MusicCollection.php          # Database operations
├── services/
│   ├── DiscogsAPIService.php        # Discogs API integration
│   └── ImageOptimizationService.php # Image optimization
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
│   └──  music_collection.json       # JSON database filess
├── index.php                        # Main application page
├── setup.php                        # Initial setup page for Discogs API Key & password
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
- **Cover Art Modal**: Click on cover images to view larger versions
- **Duplicate Prevention**: System prevents adding duplicate albums
- **Smart Sorting**: Artists are sorted intelligently (ignoring articles, sorting individuals by last name)
- **Star Ratings**: Visual star ratings with quarter, half, and three-quarter precision
- **Reviews Integration**: Clickable review counts that link directly to Discogs reviews section
- **Settings Dropdown**: Gear icon menu with login/logout, reset password, and configuration options
- **Theme Customization**: Customize background gradient colors with dual input methods (visual picker and hex input)
- **Cross-Device Sync**: Theme colors persist across all browsers and devices
- **Back/Forward Cache**: Optimized for smooth browser navigation

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

### Customizing Theme Colors

1. Click the settings gear icon in the top-right corner
2. Select "Setup & Configuration"
3. Scroll down to the "Theme Customization" section
4. Use either:
   - **Visual Color Picker**: Click the color box to open the browser's color picker
   - **Hex Input**: Manually type hex color codes (e.g., `#ff6b6b`)
5. Colors update in real-time as you change them
6. Click "Save Theme" to persist your changes
7. Click "Reset to Default" to return to the original colors

**Note**: Theme colors are automatically synced across all devices and browsers. Changes made on one device will appear on all others.  Please use colors with proper color contrast by testing it out first in a tool such as https://webaim.org/resources/contrastchecker/

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

11. **Theme Colors Not Syncing**
    - Verify `data/theme.json` file is writable (644 permissions)
    - Check browser console for theme API errors
    - Ensure `api/theme_api.php` is accessible
    - Clear browser cache if colors are stuck on old values
    - Verify server-side theme loading in `index.php` is working

12. **Theme Colors Flash on Page Load**
    - Server-side CSS should prevent flash - check inline styles in `index.php`
    - Verify `data/theme.json` exists and contains valid JSON
    - Check that theme colors are being loaded server-side before HTML output

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

## License

This application is provided as-is for personal use. Feel free to modify and extend as needed.