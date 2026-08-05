# Cover Modal Slideshow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Store all Discogs release images as `cover_images`, drop `cover_url_large`, and add a manual prev/next slideshow in `#coverModal` when more than one image exists.

**Architecture:** Extract full image URIs from Discogs `images[]` in `DiscogsAPIService`, persist `cover_url` (thumb) + `cover_images` (full URIs, primary first) through the JSON-backed collection layer, backfill with the existing upgrade script, and drive the cover modal from in-memory album data (`this.albums`) with keyboard and button navigation.

**Tech Stack:** PHP (Discogs service + JSON collection), vanilla JS (`assets/js/app.js`), Sass (`assets/scss/components/_modals.scss`), npm `build:sass` / `build:js`.

**Spec:** `docs/superpowers/specs/2026-08-05-cover-modal-slideshow-design.md`

## Global Constraints

- Keep `cover_url` as list thumbnail (`uri150`).
- `cover_images` is always an array of full-size HTTPS Discogs `uri` strings (possibly empty); index 0 is primary.
- Never write `cover_url_large` on create/update after this work; readers may fall back during migration: `cover_images[0] || cover_url_large || cover_url`.
- Slideshow only in `#coverModal` (not tracklist modal); manual only; wrap-around; start at index 0.
- Resolve slideshow images from `this.albums` by `albumId` — do not stuff long URL lists into `data-*`.
- No PHPUnit in this repo: use a small CLI assertion script under `.testing/php/`.
- Do not commit unless the user explicitly authorizes a commit in this session.

## File map

| File | Responsibility |
|------|----------------|
| `services/DiscogsAPIService.php` | Extract `{ cover_url, cover_images }` from release payloads; stop returning `cover_url_large` as the primary storage field |
| `.testing/fixtures/discogs-release-images.json` | Fixture `images[]` payload |
| `.testing/php/test_cover_images_extract.php` | CLI assertions for extraction |
| `.scripts/upgrade-cover-art.php` | Backfill `cover_images`, remove `cover_url_large` |
| `models/MusicCollection.php` | Persist `cover_images` instead of `cover_url_large` |
| `config/database.php` | JSON insert/update map param 6 → `cover_images` |
| `api/music_api.php` | Accept/return `cover_images`; stop writing `cover_url_large` |
| `index.php` | Cover modal prev/next + counter markup |
| `assets/scss/components/_modals.scss` | Slideshow control styles |
| `assets/js/app.js` | Resolve images, slideshow state, keyboard handlers |
| `assets/css/main.css` / `assets/js/app.min.js` | Built artifacts via npm |

---

### Task 1: Discogs cover image extraction helper

**Files:**
- Create: `.testing/fixtures/discogs-release-images.json`
- Create: `.testing/php/test_cover_images_extract.php`
- Modify: `services/DiscogsAPIService.php` (add public extract method; update `getCoverUrlsByReleaseId`)

**Interfaces:**
- Produces: `DiscogsAPIService::extractCoverArtFromRelease(array $release): array` returning `['cover_url' => ?string, 'cover_images' => string[]]`
- Produces: `getCoverUrlsByReleaseId($releaseId): ?array` returning `['thumb' => ?string, 'cover_images' => string[]]` (no `large` key; callers use `cover_images[0]`)

- [ ] **Step 1: Write the fixture**

Create `.testing/fixtures/discogs-release-images.json`:

```json
{
  "images": [
    {
      "type": "secondary",
      "uri": "https://i.discogs.com/secondary-a/full.jpeg",
      "uri150": "https://i.discogs.com/secondary-a/150.jpeg"
    },
    {
      "type": "primary",
      "uri": "https://i.discogs.com/primary/full.jpeg",
      "uri150": "https://i.discogs.com/primary/150.jpeg"
    },
    {
      "type": "secondary",
      "uri": "https://i.discogs.com/secondary-b/full.jpeg",
      "uri150": "https://i.discogs.com/secondary-b/150.jpeg"
    },
    {
      "type": "secondary",
      "uri": "https://i.discogs.com/primary/full.jpeg",
      "uri150": "https://i.discogs.com/primary/150.jpeg"
    },
    {
      "type": "secondary",
      "uri": "",
      "uri150": "https://i.discogs.com/empty-uri/150.jpeg"
    }
  ]
}
```

- [ ] **Step 2: Write the failing CLI test**

Create `.testing/php/test_cover_images_extract.php`:

```php
<?php
/**
 * CLI assertions for DiscogsAPIService::extractCoverArtFromRelease.
 * Run: php .testing/php/test_cover_images_extract.php
 */

require_once __DIR__ . '/../../services/DiscogsAPIService.php';

$failures = 0;

/**
 * Fail the run with a message if condition is false.
 *
 * @param bool $condition
 * @param string $message
 * @return void
 */
function assert_true($condition, $message) {
    global $failures;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        $failures++;
    } else {
        fwrite(STDOUT, "PASS: {$message}\n");
    }
}

$fixturePath = __DIR__ . '/../fixtures/discogs-release-images.json';
$release = json_decode(file_get_contents($fixturePath), true);

$service = new DiscogsAPIService();
$result = $service->extractCoverArtFromRelease($release);

assert_true(is_array($result), 'result is array');
assert_true(($result['cover_url'] ?? null) === 'https://i.discogs.com/primary/150.jpeg', 'cover_url is primary uri150');
assert_true(isset($result['cover_images']) && is_array($result['cover_images']), 'cover_images is array');
assert_true(count($result['cover_images']) === 3, 'deduped to 3 full URIs (empty skipped, duplicate skipped)');
assert_true($result['cover_images'][0] === 'https://i.discogs.com/primary/full.jpeg', 'primary full URI is first');
assert_true($result['cover_images'][1] === 'https://i.discogs.com/secondary-a/full.jpeg', 'secondary-a follows Discogs order after primary-first reorder');
assert_true($result['cover_images'][2] === 'https://i.discogs.com/secondary-b/full.jpeg', 'secondary-b included');

$empty = $service->extractCoverArtFromRelease(['images' => []]);
assert_true($empty['cover_url'] === null, 'empty images → null cover_url');
assert_true($empty['cover_images'] === [], 'empty images → [] cover_images');

exit($failures > 0 ? 1 : 0);
```

Note on expected order after primary-first: primary, then remaining images in original Discogs order excluding the primary entry and empties/dupes → `primary`, `secondary-a`, `secondary-b`.

- [ ] **Step 3: Run test to verify it fails**

Run: `php .testing/php/test_cover_images_extract.php`

Expected: FAIL (method `extractCoverArtFromRelease` undefined) or fatal error.

- [ ] **Step 4: Implement `extractCoverArtFromRelease`**

In `services/DiscogsAPIService.php`, add a public method (near `getCoverArtForSize`):

```php
/**
 * Build cover_url (thumb) + cover_images (all full URIs) from a Discogs release payload.
 * Primary image is first in cover_images; remaining images keep Discogs order.
 *
 * @param array $release Discogs release (or search-like) array
 * @return array{cover_url:?string,cover_images:array}
 */
public function extractCoverArtFromRelease($release) {
    $coverUrl = null;
    $coverImages = [];
    $seen = [];

    if (empty($release['images']) || !is_array($release['images'])) {
        $fallbackThumb = $this->getCoverArtForSize($release, 'thumbnail');
        $fallbackLarge = $this->getCoverArtForSize($release, 'large');
        if (!empty($fallbackLarge)) {
            $coverImages[] = ImageOptimizationService::forceHttps($fallbackLarge);
        }
        return [
            'cover_url' => $fallbackThumb ? ImageOptimizationService::forceHttps($fallbackThumb) : null,
            'cover_images' => $coverImages,
        ];
    }

    $images = $release['images'];
    $primaryIndex = null;
    foreach ($images as $i => $image) {
        if (isset($image['type']) && $image['type'] === 'primary') {
            $primaryIndex = $i;
            break;
        }
    }
    if ($primaryIndex === null) {
        $primaryIndex = 0;
    }

    $ordered = [];
    $ordered[] = $images[$primaryIndex];
    foreach ($images as $i => $image) {
        if ($i === $primaryIndex) {
            continue;
        }
        $ordered[] = $image;
    }

    foreach ($ordered as $index => $image) {
        $uri = isset($image['uri']) ? trim($image['uri']) : '';
        if ($uri === '') {
            continue;
        }
        $uri = ImageOptimizationService::forceHttps($uri);
        if (isset($seen[$uri])) {
            continue;
        }
        $seen[$uri] = true;
        $coverImages[] = $uri;

        if ($index === 0) {
            if (!empty($image['uri150'])) {
                $coverUrl = ImageOptimizationService::forceHttps($image['uri150']);
            }
        }
    }

    if ($coverUrl === null && !empty($coverImages)) {
        $coverUrl = $this->getCoverArtForSize($release, 'thumbnail') ?: $coverImages[0];
    }

    return [
        'cover_url' => $coverUrl,
        'cover_images' => $coverImages,
    ];
}
```

- [ ] **Step 5: Update `getCoverUrlsByReleaseId` to use the helper**

Replace the body return in `getCoverUrlsByReleaseId` so that on successful `$response`:

```php
$art = $this->extractCoverArtFromRelease($response);
if (empty($art['cover_url']) && empty($art['cover_images'])) {
    return null;
}
return [
    'thumb' => $art['cover_url'] ?: (isset($art['cover_images'][0]) ? $art['cover_images'][0] : null),
    'cover_images' => $art['cover_images'],
];
```

Update `getLargeCoverUrlByReleaseId`:

```php
public function getLargeCoverUrlByReleaseId($releaseId) {
    $urls = $this->getCoverUrlsByReleaseId($releaseId);
    if (!$urls || empty($urls['cover_images'][0])) {
        return null;
    }
    return $urls['cover_images'][0];
}
```

Update PHPDoc on `getCoverUrlsByReleaseId` return type to `array{thumb:?string,cover_images:string[]}|null`.

- [ ] **Step 6: Run CLI test to verify it passes**

Run: `php .testing/php/test_cover_images_extract.php`

Expected: all PASS lines, exit code `0`.

- [ ] **Step 7: Propose commit (do not commit unless user authorizes)**

Proposed message:

```
Add Discogs extractCoverArtFromRelease for cover_images arrays
```

---

### Task 2: Persist `cover_images` in collection storage and API

**Files:**
- Modify: `models/MusicCollection.php`
- Modify: `config/database.php` (`handleInsert` / `handleUpdate`)
- Modify: `api/music_api.php`
- Modify: `services/DiscogsAPIService.php` (`getReleaseInfo` and any search result maps that emit `cover_url_large`)

**Interfaces:**
- Consumes: `extractCoverArtFromRelease` / `cover_images` arrays from Task 1
- Produces: Albums in JSON with `cover_url` + `cover_images`; API create/update accept `cover_images`; list responses include `cover_images`

- [ ] **Step 1: Change `MusicCollection::addAlbum` / `updateAlbum` signatures**

Replace `$coverUrlLarge = null` with `$coverImages = null` (array or null). Normalize:

```php
/**
 * Normalize cover_images to a list of non-empty HTTPS URL strings.
 *
 * @param mixed $coverImages
 * @return array
 */
private function normalizeCoverImages($coverImages) {
    if (!is_array($coverImages)) {
        return [];
    }
    $out = [];
    foreach ($coverImages as $url) {
        if (!is_string($url)) {
            continue;
        }
        $url = trim($url);
        if ($url === '') {
            continue;
        }
        $out[] = $url;
    }
    return array_values($out);
}
```

In `addAlbum` / `updateAlbum`:

```php
$coverImages = $this->normalizeCoverImages($coverImages);
if (empty($coverUrl) && !empty($coverImages[0])) {
    $coverUrl = $coverImages[0];
}
```

Change SQL column name from `cover_url_large` to `cover_images` in INSERT/UPDATE (JSON shim treats this as a field name). Pass `$coverImages` as the 7th bound value (index 6).

Update `updateAlbumRaw` `$allowedFields`: replace `cover_url_large` with `cover_images`; keep `cover_url_medium` if still used.

- [ ] **Step 2: Update JSON DB insert/update**

In `config/database.php` `handleInsert` / `handleUpdate`, replace:

```php
'cover_url_large' => $params[6] ?? null,
```

with:

```php
'cover_images' => is_array($params[6] ?? null) ? $params[6] : [],
```

Do not write `cover_url_large` on insert/update.

- [ ] **Step 3: Update `api/music_api.php` create/update paths**

Wherever `$coverUrlLarge` is gathered from input / Discogs:

```php
$coverImages = [];
if (!empty($input['cover_images']) && is_array($input['cover_images'])) {
    $coverImages = $input['cover_images'];
} elseif (!empty($input['cover_url_large'])) {
    // Migration compat for older clients
    $coverImages = [$input['cover_url_large']];
}
```

When Discogs `getReleaseInfo` / search returns art, prefer:

```php
if (!empty($releaseInfo['cover_images']) && is_array($releaseInfo['cover_images'])) {
    $coverImages = $releaseInfo['cover_images'];
} elseif (!empty($releaseInfo['cover_url_large'])) {
    $coverImages = [$releaseInfo['cover_url_large']];
}
```

Pass `$coverImages` into `addAlbum` / `updateAlbum` instead of `$coverUrlLarge`.

In list/autocomplete payloads that currently set `cover_url_large`, also set:

```php
'cover_images' => $album['cover_images'] ?? (
    !empty($album['cover_url_large']) ? [$album['cover_url_large']] : []
),
```

Stop requiring `cover_url_large` in new writes; optional compat read is fine.

- [ ] **Step 4: Update Discogs `getReleaseInfo` (and search maps) to emit `cover_images`**

In `getReleaseInfo` where thumb/large are set (~1108), use:

```php
$art = $this->extractCoverArtFromRelease($response);
// ...
'cover_url' => $art['cover_url'],
'cover_images' => $art['cover_images'],
```

Remove writing `cover_url_large` from that return (or leave as deprecated alias `cover_url_large => $art['cover_images'][0] ?? null` only if something still breaks — prefer remove and fix callers).

Update search result maps that set `cover_url_large` similarly: add `cover_images` via `extractCoverArtFromRelease` when full `images[]` exists; for search stubs without `images[]`, set `cover_images` to a one-element array from the large cover field when available.

- [ ] **Step 5: Smoke-check PHP syntax**

Run:

```bash
php -l services/DiscogsAPIService.php
php -l models/MusicCollection.php
php -l config/database.php
php -l api/music_api.php
php .testing/php/test_cover_images_extract.php
```

Expected: no syntax errors; CLI test exit `0`.

- [ ] **Step 6: Propose commit (ask user first)**

```
Persist cover_images instead of cover_url_large in collection API
```

---

### Task 3: Backfill script writes `cover_images` and drops `cover_url_large`

**Files:**
- Modify: `.scripts/upgrade-cover-art.php`

**Interfaces:**
- Consumes: `getCoverUrlsByReleaseId` → `thumb` + `cover_images`
- Produces: Updated collection JSON albums with `cover_url`, `cover_images`; no `cover_url_large`

- [ ] **Step 1: Update script header comment**

Document:

```
 *   cover_url     — Discogs thumbnail (list)
 *   cover_images  — all full-size Discogs image URIs (primary first)
 * Removes cover_url_large when applying.
```

- [ ] **Step 2: Replace SAME/UPDATE logic**

After `$urls = $discogs->getCoverUrlsByReleaseId(...)`:

```php
$newThumb = upgrade_cover_art_normalize_url($urls['thumb'] ?? (isset($urls['cover_images'][0]) ? $urls['cover_images'][0] : ''));
$newImages = [];
if (!empty($urls['cover_images']) && is_array($urls['cover_images'])) {
    foreach ($urls['cover_images'] as $imageUrl) {
        $normalized = upgrade_cover_art_normalize_url($imageUrl);
        if ($normalized !== '') {
            $newImages[] = $normalized;
        }
    }
}
$newImages = array_values(array_unique($newImages));

$currentThumb = upgrade_cover_art_normalize_url($album['cover_url'] ?? '');
$currentImages = [];
if (!empty($album['cover_images']) && is_array($album['cover_images'])) {
    foreach ($album['cover_images'] as $imageUrl) {
        $normalized = upgrade_cover_art_normalize_url($imageUrl);
        if ($normalized !== '') {
            $currentImages[] = $normalized;
        }
    }
} elseif (!empty($album['cover_url_large'])) {
    $currentImages[] = upgrade_cover_art_normalize_url($album['cover_url_large']);
}

$storedIsProxy = strpos((string) ($album['cover_url'] ?? ''), 'image_proxy.php') !== false
    || strpos((string) ($album['cover_url_large'] ?? ''), 'image_proxy.php') !== false
    || (!empty($album['cover_images'][0]) && strpos((string) $album['cover_images'][0], 'image_proxy.php') !== false);

$needsUpdate = $storedIsProxy
    || $currentThumb !== $newThumb
    || $currentImages !== $newImages
    || array_key_exists('cover_url_large', $album)
    || !isset($album['cover_images']);
```

On apply:

```php
$album['cover_url'] = $newThumb;
$album['cover_images'] = $newImages;
unset($album['cover_url_large']);
$album['updated_date'] = date('Y-m-d H:i:s');
```

Log image count: `images: {count($newImages)}`.

- [ ] **Step 3: Dry-run against local collection (no write)**

Run:

```bash
php .scripts/upgrade-cover-art.php --file=data/music_collection.json --dry-run
```

Expected: progress lines; some UPDATE with `images: N` where N > 1 for multi-image releases; no file write.

- [ ] **Step 4: Propose commit (ask user first)**

```
Upgrade cover-art script to backfill cover_images arrays
```

---

### Task 4: Cover modal markup and styles

**Files:**
- Modify: `index.php` (`#coverModal` block ~457–465)
- Modify: `assets/scss/components/_modals.scss` (`#coverModal` section ~612+)
- Run: `npm run build:sass` (updates `assets/css/main.css`)

**Interfaces:**
- Produces: DOM ids `coverModalPrev`, `coverModalNext`, `coverModalCounter`, wrapper `coverModalSlideshowControls` (hidden by default / via class)

- [ ] **Step 1: Extend `#coverModal` markup in `index.php`**

Replace the cover modal body with:

```html
    <div id="coverModal" class="modal">
      <div class="modal-content cover-modal-content">
        <span class="close">&times;</span>
        <div class="cover-modal-body">
          <div class="cover-modal-stage">
            <button type="button" id="coverModalPrev" class="cover-modal-nav cover-modal-prev" aria-label="Previous image" hidden>&lsaquo;</button>
            <img id="coverModalImage" src="" alt="Album cover" class="cover-modal-image">
            <button type="button" id="coverModalNext" class="cover-modal-nav cover-modal-next" aria-label="Next image" hidden>&rsaquo;</button>
          </div>
          <div id="coverModalCounter" class="cover-modal-counter" hidden aria-live="polite"></div>
          <div id="coverModalInfo" class="cover-modal-info"></div>
        </div>
      </div>
    </div>
```

- [ ] **Step 2: Add SCSS under `#coverModal`**

In `assets/scss/components/_modals.scss` inside `#coverModal .cover-modal-body`, add:

```scss
      .cover-modal-stage {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        max-width: 100%;
      }

      .cover-modal-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
        border: none;
        background: rgba(0, 0, 0, 0.45);
        color: #fff;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        font-size: 1.75rem;
        line-height: 1;
        cursor: pointer;

        &:hover,
        &:focus {
          background: rgba(0, 0, 0, 0.7);
        }

        &[hidden] {
          display: none !important;
        }
      }

      .cover-modal-prev {
        left: 0.5rem;
      }

      .cover-modal-next {
        right: 0.5rem;
      }

      .cover-modal-counter {
        margin-bottom: $spacing-sm;
        font-size: $font-size-base;
        color: #555;

        &[hidden] {
          display: none !important;
        }
      }
```

Keep existing `.cover-modal-image` rules; ensure stage does not break max-height behavior.

- [ ] **Step 3: Build CSS**

Run: `npm run build:sass`

Expected: exit `0`; `assets/css/main.css` updated.

- [ ] **Step 4: Propose commit (ask user first)**

```
Add cover modal slideshow controls markup and styles
```

---

### Task 5: Cover modal slideshow behavior in JS

**Files:**
- Modify: `assets/js/app.js`
- Run: `npm run build:js`

**Interfaces:**
- Consumes: album `cover_images` / compat fields from `this.albums`
- Produces: `resolveCoverImages(album)`, updated `showCoverModal(...)`, keyboard handler bound only while cover modal open

- [ ] **Step 1: Add helpers on the app class**

Near other utility methods, add:

```javascript
  /**
   * Resolve full-size cover URLs for the cover modal (spec compat order).
   * @param {Object|null} album
   * @returns {string[]}
   */
  resolveCoverImages(album) {
      if (!album) {
          return [];
      }
      if (Array.isArray(album.cover_images) && album.cover_images.length > 0) {
          return album.cover_images.filter((url) => typeof url === 'string' && url.trim() !== '');
      }
      if (album.cover_url_large) {
          return [album.cover_url_large];
      }
      if (album.cover_url) {
          return [album.cover_url];
      }
      return [];
  }

  /**
   * Find album in the in-memory collection by id.
   * @param {string|number|null} albumId
   * @returns {Object|null}
   */
  findAlbumById(albumId) {
      if (albumId === null || albumId === undefined || albumId === '') {
          return null;
      }
      if (!this.albums || !Array.isArray(this.albums)) {
          return null;
      }
      return this.albums.find((album) => String(album.id) === String(albumId)) || null;
  }
```

- [ ] **Step 2: Replace `showCoverModal` to drive slideshow state**

Change signature to keep existing positional args, resolve images inside:

```javascript
  async showCoverModal(artistName, albumName, releaseYear, coverUrl, albumId = null) {
      const modal = document.getElementById('coverModal');
      const image = document.getElementById('coverModalImage');
      const info = document.getElementById('coverModalInfo');
      const prevBtn = document.getElementById('coverModalPrev');
      const nextBtn = document.getElementById('coverModalNext');
      const counter = document.getElementById('coverModalCounter');

      const album = this.findAlbumById(albumId);
      let images = this.resolveCoverImages(album);
      if (images.length === 0 && coverUrl) {
          images = [coverUrl];
      }

      this.coverModalImages = images;
      this.coverModalIndex = 0;

      const showChrome = images.length > 1;
      if (prevBtn) {
          prevBtn.hidden = !showChrome;
      }
      if (nextBtn) {
          nextBtn.hidden = !showChrome;
      }
      if (counter) {
          counter.hidden = !showChrome;
      }

      const renderSlide = () => {
          const url = this.coverModalImages[this.coverModalIndex] || '';
          image.src = url;
          image.alt = `${albumName} by ${artistName}`;
          if (counter && showChrome) {
              counter.textContent = `${this.coverModalIndex + 1} / ${this.coverModalImages.length}`;
          }
      };

      this.coverModalGo = (delta) => {
          if (!this.coverModalImages || this.coverModalImages.length <= 1) {
              return;
          }
          const total = this.coverModalImages.length;
          this.coverModalIndex = (this.coverModalIndex + delta + total) % total;
          renderSlide();
      };

      if (prevBtn) {
          prevBtn.onclick = (e) => {
              e.preventDefault();
              e.stopPropagation();
              this.coverModalGo(-1);
          };
      }
      if (nextBtn) {
          nextBtn.onclick = (e) => {
              e.preventDefault();
              e.stopPropagation();
              this.coverModalGo(1);
          };
      }

      // Remove previous key handler if any
      if (this.coverModalKeyHandler) {
          document.removeEventListener('keydown', this.coverModalKeyHandler);
      }
      this.coverModalKeyHandler = (e) => {
          const open = modal && modal.style.display === 'block';
          if (!open) {
              return;
          }
          if (e.key === 'ArrowLeft') {
              e.preventDefault();
              this.coverModalGo(-1);
          } else if (e.key === 'ArrowRight') {
              e.preventDefault();
              this.coverModalGo(1);
          }
      };
      document.addEventListener('keydown', this.coverModalKeyHandler);

      renderSlide();

      // ... keep existing info HTML + artist/album/year link listeners ...
      // ... then show modal as today (display block / modal-open class) ...
  }
```

Preserve the existing info markup and link listeners from the current `showCoverModal` body after `renderSlide()` setup.

- [ ] **Step 3: Clear slideshow handlers when hiding cover modal**

In whatever path hides `#coverModal` (`hideModalById('coverModal')` or dedicated hide), add:

```javascript
      if (modalId === 'coverModal' || /* when covering cover modal */) {
          if (this.coverModalKeyHandler) {
              document.removeEventListener('keydown', this.coverModalKeyHandler);
              this.coverModalKeyHandler = null;
          }
          this.coverModalImages = [];
          this.coverModalIndex = 0;
          this.coverModalGo = null;
      }
```

Inspect `hideModalById` and wire the cleanup there when `id === 'coverModal'`.

- [ ] **Step 4: Stop relying on `data-large` for the modal primary**

In `renderAlbums` cover `<img>`, keep `data-cover` as primary large for fallback:

```javascript
data-cover="${(Array.isArray(album.cover_images) && album.cover_images[0]) || album.cover_url_large || album.cover_url || ''}"
```

Prefer resolving via `albumId` in `handleAlbumsTableClick` (already passes `albumData.albumId`). Ensure `extractAlbumData` still returns `albumId`.

- [ ] **Step 5: Update add/edit album client fields**

Replace `selectedCoverUrlLarge` usage when saving with:

```javascript
cover_images: Array.isArray(this.selectedCoverImages) && this.selectedCoverImages.length
    ? this.selectedCoverImages
    : (this.selectedCoverUrlLarge ? [this.selectedCoverUrlLarge] : (this.selectedCoverUrl ? [this.selectedCoverUrl] : [])),
```

When selecting Discogs autocomplete items, set:

```javascript
this.selectedCoverImages = Array.isArray(item.cover_images) ? item.cover_images.slice() : (
    item.cover_url_large ? [item.cover_url_large] : (item.cover_url ? [item.cover_url] : [])
);
this.selectedCoverUrl = item.cover_url || (this.selectedCoverImages[0] || null);
```

Gradually remove dependence on `selectedCoverUrlLarge` (compat assignment OK during transition).

- [ ] **Step 6: Build minified JS**

Run: `npm run build:js`

Expected: exit `0`.

- [ ] **Step 7: Manual browser check on music.lndo.site**

1. Open an album known to have multiple Discogs images (after a dry-run/apply backfill, or temporarily inject `cover_images` on one album in JSON for local test).
2. Click the table thumb → cover modal shows image 1, counter `1 / N`, arrows visible.
3. Click next / press → cycles; wraps; ← goes back.
4. Close modal; ←/→ no longer change anything.
5. Album with a single image: no arrows/counter.

- [ ] **Step 8: Propose commit (ask user first)**

```
Add manual cover modal slideshow from cover_images
```

---

### Task 6: Apply backfill to local collection (operator step)

**Files:**
- Modify: `data/music_collection.json` (via script apply)

- [ ] **Step 1: Dry-run once more**

```bash
php .scripts/upgrade-cover-art.php --file=data/music_collection.json --dry-run
```

- [ ] **Step 2: Apply (only with user approval — Discogs rate limit + backup)**

```bash
php .scripts/upgrade-cover-art.php --file=data/music_collection.json --apply
```

Expected: backup `data/music_collection.json.bak.*` written; albums gain `cover_images`; `cover_url_large` removed.

- [ ] **Step 3: Spot-check JSON**

```bash
php -r '$d=json_decode(file_get_contents("data/music_collection.json"),true); $n=0;$m=0; foreach($d["albums"] as $a){ if(!empty($a["cover_images"])) $n++; if(count($a["cover_images"]??[])>1) $m++; if(isset($a["cover_url_large"])) { echo "still has cover_url_large id=".$a["id"]."\n"; } } echo "with cover_images=$n multi=$m\n";'
```

Expected: no `cover_url_large`; `multi` > 0.

- [ ] **Step 4: Re-verify slideshow in browser** against a multi-image album.

- [ ] **Step 5: Propose commit of data only if user wants collection data in git**

```
Backfill cover_images for music collection
```

(Skip if `data/music_collection.json` is not meant to be committed.)

---

## Plan self-review

| Spec requirement | Task |
|------------------|------|
| All Discogs images in slideshow | 1, 3, 5 |
| Store in JSON (`cover_images`) | 2, 3, 6 |
| Keep `cover_url`, drop `cover_url_large` | 2, 3 |
| Manual prev/next + counter + keyboard | 4, 5 |
| Compat fallback while migrating | 2, 5 (`resolveCoverImages`) |
| No tracklist slideshow / no autoplay | Out of scope honored |
| Resolve by album id | 5 |
| Upgrade script dry-run/apply | 3, 6 |

No TBD placeholders. Method names consistent: `extractCoverArtFromRelease`, `resolveCoverImages`, `cover_images`.
