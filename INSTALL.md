# Installation Guide

## Quick Start

### Prerequisites
- PHP 7.4 or higher with the **ZipArchive** extension enabled (required for Setup → Backup ZIP download and restore)
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
   - **Backup**: Download or restore your local catalog ZIP (see below)
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

#### Catalog backup (Setup → Backup tab)

Open **Setup & Configuration** → **Backup** to snapshot or restore your **local** catalog files only.

**Download**

1. Optionally leave **Include settings.json** checked (default) to add display/app preferences from `data/settings.json`.
2. Click **Download backup**. The browser saves `music-backup-YYYYMMDD-HHMMSS.zip`.

**ZIP contents**

- Always: `music_collection.json` (your catalog, including cached `tracklist` fields on albums when present)
- If included and readable: `settings.json`
- Optional manifest: `backup-meta.json` (timestamp and flags; not required for restore)

**Not included:** Discogs API keys, personal access tokens, password hashes, `api_config.local.php`, or `auth_config.php`. Backups do **not** call Discogs and do **not** change import/export behavior.

**Restore**

1. Choose a `.zip` or raw catalog `.json` file.
2. Leave **Also restore settings if present in backup** unchecked unless you intend to replace settings (default unchecked).
3. Click **Restore backup** and confirm. **Restore always replaces** the entire local catalog with the backup catalog.
4. Settings are restored **only** when you check the settings option **and** the backup contains `settings.json` (ZIP only).

Before overwriting, the app copies existing files to timestamped `.bak` files under `data/` (for example `music_collection.json.bak.YYYYMMDDHHMMSS`).

**Requirements:** Logged-in admin session, CSRF token (handled by the Setup UI), and PHP **ZipArchive** for ZIP download/restore. If ZipArchive is missing, backup fails with a clear error—enable the `zip` extension in `php.ini`.

### Album condition & notes

On the main collection page, Add/Edit album includes **media condition**, **sleeve condition** (Discogs grade list), and optional **notes**. These fields are stored on each album in `music_collection.json`, included in catalog backups, and are **local only** (Discogs import/export never overwrite them).

### Collection list paging

On the main collection page, the album table loads **100 albums at a time** and fetches the next page as you scroll. Changing **search**, **Own/Want/Total filters**, **facet filters** (style, format, etc.), or **column sort** requests a fresh first page from the server (filter badge counts still come from collection stats, not from the loaded scroll window).

### Tracklist caching

When you open an album tracklist with a local **album id**, the app can store a lean copy of the Discogs tracklist on that album in `music_collection.json`:

- **`tracklist`** — array of `{ position, title, duration }` (no lyrics URLs stored)
- **`total_runtime`**, **`tracklist_cached_at`**, **`tracklist_source_release_id`** — cache metadata
- Empty local **format**, **label**, or **producer** may be filled from Discogs on cache write; existing local values are never overwritten.

**Who writes:** Only a **logged-in admin** (after a successful Discogs tracklist fetch). Auto-save is silent—no extra button on first open. **Guests and logged-out users never write** the catalog; they may still **read** cached tracks when present.

**Who reads:** Anyone opening the tracklist for an album that already has a non-empty `tracklist` gets tracks from the local cache (`source: "cache"` in the API) without a Discogs release fetch for the track rows. Rating, marketplace counts, and shop pricing are **not** cached; the app still tries live Discogs enrich for those when the API is available.

**Refresh from Discogs:** When logged in as admin, the tracklist modal shows **Refresh from Discogs** next to Edit. It POSTs to the tracklist API with `refresh=1` and CSRF, bypasses the cache, fetches Discogs again, overwrites cache fields, and re-renders the modal. Failed refresh keeps the existing cache.

**Backup:** Cached tracklists live on album rows inside `music_collection.json`, so **Setup → Backup** ZIP downloads already include them (same file as the rest of the catalog). Restore replaces the catalog as a whole; no separate tracklist export is required.

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

**Catalog backup:**
- Enable PHP **ZipArchive** (`php -m | grep -i zip`) for ZIP download/restore
- Must be logged in; unauthenticated backup requests return HTTP 401
- Restore replaces the whole catalog—keep a downloaded ZIP before testing restore on production data
- Invalid JSON uploads are rejected; existing files stay unchanged until validation passes
- Settings are not restored unless you opt in and the ZIP includes `settings.json`

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
