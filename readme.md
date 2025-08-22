# Music Collection Manager

A modern PHP CRUD application for managing your music collection with database integration, search functionality, autocomplete features, and external API integration.

## Features

- **Complete CRUD Operations**: Add, edit, delete, and view albums
- **Search & Filter**: Search by artist or album name, filter by owned/wanted status
- **Smart Autocomplete**: Enhanced autocomplete with Discogs API integration
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
4. Optionally enter the release year
5. Select either "I own this album" or "I want to own this album" (radio buttons)
6. Click "Save Album"

### Enhanced Features

- **Cover Art**: Automatically retrieved and displayed for albums with local image proxy
- **Tracklist View**: Click on album titles to view detailed tracklists with producer and rating information
- **Cover Art Modal**: Click on cover images to view larger versions
- **Duplicate Prevention**: System prevents adding duplicate albums
- **Smart Sorting**: Artists are sorted intelligently (ignoring articles, sorting individuals by last name)
- **Star Ratings**: Visual star ratings with quarter, half, and three-quarter precision
- **Reviews Integration**: Clickable review counts that link directly to Discogs reviews section
- **Settings Dropdown**: Gear icon menu with login/logout, reset password, and configuration options
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

### Deleting Albums

1. Click the "Delete" button next to any album
2. Confirm the deletion (requires authentication)

## API Endpoints

The application provides RESTful API endpoints for all operations:

### GET Requests

- `api/music_api.php?action=albums` - Get all albums
- `api/music_api.php?action=albums&filter=owned` - Get owned albums
- `api/music_api.php?action=albums&search=search_term` - Search albums
- `api/music_api.php?action=album&id=1` - Get specific album
- `api/music_api.php?action=artists&search=search_term` - Get artists for autocomplete
- `api/music_api.php?action=albums_by_artist&artist=artist_name` - Get albums by artist
- `api/music_api.php?action=stats` - Get collection statistics
- `api/music_api.php?action=auth_status` - Check authentication status

### POST Requests

- `api/music_api.php?action=add` - Add new album (requires authentication)
- `api/music_api.php?action=update` - Update existing album (requires authentication)
- `api/music_api.php?action=delete` - Delete album (requires authentication)
- `api/music_api.php?action=login` - Authenticate user
- `api/music_api.php?action=logout` - Logout user

### Tracklist API

- `api/tracklist_api.php?artist=artist_name&album=album_name` - Get detailed tracklist

## Features in Detail

### Enhanced Autocomplete System

The application provides intelligent autocomplete with Discogs API integration:
- **Artist Names**: Suggests artists from Discogs database
- **Album Names**: Suggests albums by the selected artist with cover art
- **Release Years**: Automatically populated from API data
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