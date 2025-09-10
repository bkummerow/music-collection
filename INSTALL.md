# Installation Guide

## Quick Start

### Prerequisites
- PHP 7.4 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- Node.js 10.12.0+ (for building assets)
- Discogs API key (free at [Discogs Developers](https://www.discogs.com/settings/developers))

### 1. Download and Setup

```bash
# Clone the repository
git clone https://github.com/bkummerow/music-collection.git
cd music-collection

# Install dependencies
npm install

# Build assets
npm run build
```

### 2. Set Permissions

```bash
# Set proper permissions
chmod 755 data/
chmod 644 data/*.json
```

### 3. Web Server Setup

#### Apache
Ensure mod_rewrite is enabled and create `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

#### PHP Built-in Server (Development)
```bash
php -S localhost:8000
```

### 4. Access the Application

1. Navigate to your web server URL
2. **Login with default credentials:**
   - Password: `admin123`
3. Click the settings gear icon and select "Setup & Configuration"
4. Configure your application:
   - **API Config**: Add your Discogs API Key
   - **Password**: Change your password from the default
   - **Display Mode**: Choose between Light and Dark mode
   - **Album Display**: Customize album information and artist links
   - **Stats Display**: Control collection statistics and charts
5. Start adding your music collection!

## Docker Installation (Optional)

```dockerfile
FROM php:8.1-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    nodejs npm \
    && rm -rf /var/lib/apt/lists/*

# Copy application
COPY . /var/www/html/

# Install Node dependencies and build
WORKDIR /var/www/html
RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/data

EXPOSE 80
```

## Troubleshooting

### Common Issues

**Permission Errors:**
```bash
chmod 755 data/
chmod 644 data/*.json
```

**API Errors:**
- Login with default password `admin123`
- Go to Setup & Configuration to add your Discogs API key
- Check rate limiting settings
- Ensure your server can make external HTTP requests

**Database Errors:**
- Ensure the `data/` directory is writable
- Check file permissions (755 for directories, 644 for files)

**Asset Loading Issues:**
```bash
npm run build
```

### Support

If you encounter issues:
1. Check the [Common Issues](readme.md#common-issues) section
2. Search existing [GitHub Issues](https://github.com/yourusername/music-collection-manager/issues)
3. Create a new issue with detailed information
