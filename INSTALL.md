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

### 3. Configure API Keys (Optional)

For full functionality, you'll need a Discogs API key:

1. **Get credentials** from [Discogs Developers](https://www.discogs.com/settings/developers):
   - **Read-only features** (search, import, cover art): a consumer application token is enough.
   - **Discogs Export (writes)**: use a **personal access token** with permission to modify your collection and wantlist. A consumer key alone cannot add releases to Collection or Wantlist.
2. **Configure credentials** (choose one):
   - **Environment variables** (recommended for production):
     ```bash
     export DISCOGS_API_KEY="your_api_key_here"
     export DISCOGS_USER_AGENT="YourAppName/1.0"
     ```
   - **Local config file** (for development): copy `config/api_config.local.php.example` to `config/api_config.local.php` (gitignored) and set your key there. Environment variables override the local file when both are set.
3. **If you ever committed a Discogs token to git**, revoke or rotate it in [Discogs developer settings](https://www.discogs.com/settings/developers) and use a new key only in env or `api_config.local.php`—never commit secrets.

### 4. Access the Application

1. Navigate to your web server URL
2. **Login with default credentials:**
   - Password: `admin123`
3. **Change the default password on first login** (required for your own deployment; the public demo intentionally keeps `admin123`).
4. Click the settings gear icon and select "Setup & Configuration"
5. Configure your application:
   - **API Config**: Add your Discogs API Key (if not set via environment variables or `api_config.local.php`)
   - **Discogs Import**: Bulk-import your Discogs Collection and Wantlist (see below)
   - **Discogs Export**: Push local owned/wanted albums to Discogs (see below)
   - **Password**: Confirm your password is no longer the default
   - **Display Mode**: Choose between Light and Dark mode
   - **Album Display**: Customize album information and artist links
   - **Stats Display**: Control collection statistics and charts
6. Start adding your music collection!

#### Discogs Import (Setup → Discogs Import tab)

After your Discogs API key is configured, open **Setup & Configuration** → **Discogs Import**:

1. Enter your **Discogs username** (the public username on discogs.com, not your email).
2. Leave **Save username for next time** checked to store the username in app settings for future imports.
3. Click **Import from Discogs**. The import runs in the browser, one Discogs API page at a time—**keep this browser tab open** until it finishes (large collections can take several minutes).

**Requirements:** A valid Discogs API key (API Config tab or environment) and your Discogs username.

**Merge behavior:**

- Imports **Collection** (owned) and **Wantlist** (wanted). Re-imports **merge** into your local catalog; they do not create duplicate rows for the same release.
- **Nothing is deleted** locally—albums that are not on Discogs anymore stay in your collection.
- If the same release appears in both Collection and Wantlist, **owned wins** (want-only flags are not applied on top of owned).

#### Discogs Export (Setup → Discogs Export tab)

After your Discogs credentials are configured, open **Setup & Configuration** → **Discogs Export**:

1. Enter your **Discogs username** — it **must match** the account that owns your personal access token (the Export tab shows which account the token belongs to).
2. Click **Push to Discogs**. The export runs in the browser in pages—**keep this tab open** until it finishes.

**Requirements:** A valid **personal access token** in `DISCOGS_API_KEY` (or API Config / `api_config.local.php`) and the **same** Discogs username as that token.

**Push behavior (add-only):**

- **Owned** local albums → Discogs **Collection**, folder **1** (Uncategorized). Folder 0 is Discogs’ virtual “All” folder and is not used for writes.
- **Want to own** (and not owned) → Discogs **Wantlist**.
- Albums **already on Discogs** in the target list are **skipped** (safe to re-run).
- Albums **without a `discogs_release_id`** are **skipped** and counted as missing ID.
- **Nothing is removed or changed** on Discogs; export never deletes collection or wantlist items.
- **Discogs Import is unchanged**—import still pulls from Discogs into your local catalog only.
- A username that does not match the token account returns a clear error (Discogs would otherwise respond with HTTP 403).

### Security notes

- **HTTPS**: On HTTPS deployments, session cookies use the `Secure` flag automatically.
- **CSRF**: State-changing POST requests require a session synchronizer token sent as the `X-CSRF-Token` header (the app sets this for you in the browser UI). With `DEMO_MODE=true`, demo `reset_demo` and logout POSTs use the same requirement.

## Deployment Options

The application can be deployed on any platform that supports PHP:

- **Cloud platforms** with PHP support
- **Shared hosting** providers
- **VPS/dedicated servers**
- **Container platforms** (Docker, etc.)

Simply upload the files and set the proper permissions (755 for directories, 644 for files).

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

**Discogs Import:**
- Confirm API key is set (Setup → API Config or `DISCOGS_API_KEY`)
- Use your Discogs **username**, not email
- Keep the Setup tab open until progress completes
- Re-run import anytime to merge updates; local albums are never removed because they are missing from Discogs

**Discogs Export:**
- Use a **personal access token**, not a consumer-only key, for write access
- Confirm username matches the account that owns the token
- Albums need a Discogs release ID on the local row; otherwise they are skipped
- Re-run export to add new local albums only; items already on Discogs are skipped

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
