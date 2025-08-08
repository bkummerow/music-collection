# Music Collection Manager

A modern PHP CRUD application for managing your music collection with database integration, search functionality, autocomplete features, and external API integration.

## Features

- **Complete CRUD Operations**: Add, edit, delete, and view albums
- **Search & Filter**: Search by artist or album name, filter by owned/wanted status
- **Smart Autocomplete**: Enhanced autocomplete with Discogs API integration
- **Cover Art Display**: Automatic cover art retrieval and display
- **Tracklist Information**: View detailed tracklists for albums
- **Password Protection**: Secure authentication for add/edit/delete operations
- **Statistics Dashboard**: View collection statistics at a glance
- **Responsive Design**: Works on desktop and mobile devices
- **Modern UI**: Clean, intuitive interface with smooth animations
- **Smart Sorting**: Intelligent artist sorting (ignores articles, sorts individuals by last name)
- **Lazy Loading**: Optimized image loading for better performance
- **Accessibility**: WCAG compliant with proper contrast ratios
- **SEO Optimized**: Meta tags and structured data

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
│   ├── api_config.php        # API keys and settings
│   ├── auth_config.php       # Authentication settings
│   └── database.php          # Database configuration (SimpleDB)
├── models/
│   └── MusicCollection.php   # Database operations
├── services/
│   ├── DiscogsAPIService.php # Discogs API integration
│   └── ImageOptimizationService.php # Image optimization
├── api/
│   ├── music_api.php         # Main API endpoints
│   └── tracklist_api.php     # Tracklist API
├── assets/
│   ├── css/
│   │   ├── style.css         # Application styles
│   │   └── style.min.css     # Application styles (minified)
│   └── js/
│       ├── app.js            # Frontend functionality
│       └── app.min.js        # Frontend functionality (minified)
├── data/                     # JSON database files
├── index.php                 # Main application page
├── setup.php                 # Initial setup page for Discogs API Key & password
├── reset_password.php        # Reset password page
└── README.md                 # This file
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

- **Cover Art**: Automatically retrieved and displayed for albums
- **Tracklist View**: Click on album titles to view detailed tracklists
- **Cover Art Modal**: Click on cover images to view larger versions
- **Duplicate Prevention**: System prevents adding duplicate albums
- **Smart Sorting**: Artists are sorted intelligently (ignoring articles, sorting individuals by last name)

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
- **Album Metadata**: Release year, format, genre information
- **Discogs Integration**: Direct links to Discogs pages
- **Modal Display**: Clean modal interface for tracklist viewing

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
- **Efficient Database**: JSON-based storage optimized for shared hosting

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

## Browser Compatibility

- Chrome (recommended)
- Firefox
- Safari
- Edge
- Mobile browsers (iOS Safari, Chrome Mobile)

## Troubleshooting

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

5. **Mobile Close Buttons Not Working**
   - Verify touch event handling is enabled
   - Check for JavaScript errors in mobile browser
   - Ensure proper CSS touch targets

### Performance Tips

- The JSON database is optimized for read/write operations
- Search queries are optimized with intelligent filtering
- Images are lazy-loaded for better performance
- API calls are debounced to reduce server load

## Customization

### Styling

Modify `assets/css/style.css` to customize the appearance:
- Color scheme and contrast ratios
- Typography and spacing
- Mobile responsiveness
- Animation effects

### API Integration

Extend the application by modifying:
- `services/DiscogsAPIService.php` for additional API features
- `services/ImageOptimizationService.php` for image handling
- `api/music_api.php` for custom endpoints

### Functionality

Extend the application by modifying:
- `models/MusicCollection.php` for database operations
- `assets/js/app.js` for frontend functionality
- `config/database.php` for custom database logic

## Recent Updates

### Version 2.0 Features
- **Discogs API Integration**: Enhanced autocomplete with real music data
- **Cover Art Display**: Automatic cover art retrieval and display
- **Tracklist Information**: Detailed tracklist viewing with modal interface
- **Password Protection**: Secure authentication for sensitive operations
- **Smart Artist Sorting**: Intelligent sorting for bands and individual artists
- **Mobile Optimization**: Improved touch targets and mobile experience
- **Lazy Loading**: Optimized image loading for better performance
- **Accessibility Improvements**: WCAG compliant design with proper contrast
- **Duplicate Prevention**: Prevents adding duplicate albums
- **Enhanced UI**: Radio buttons for album status, improved modals

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review browser console for error messages
3. Verify API configuration and connectivity
4. Check file permissions and server configuration
5. Test on different browsers and devices

## License

This application is provided as-is for personal use. Feel free to modify and extend as needed.