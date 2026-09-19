# Tracklist Caching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist Discogs tracklists on local albums (admin auto-save + Refresh), and serve cached tracks on later opens without a Discogs release call.

**Architecture:** Extend `updateAlbumRaw` allowed fields; add a small persist helper used by `tracklist_api.php`. On read with `album_id`, return cached `tracklist` unless `refresh=1`. After Discogs success, admin sessions write lean cache fields. Tracklist modal gains **Refresh from Discogs** for authenticated admins.

**Tech Stack:** PHP 7.4+ JSON catalog, `AuthHelper`, `tracklist_api.php`, vanilla JS `app.js` + `npm run build:js`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-19-tracklist-caching-design.md`
- Auto-save + Refresh; lean payload (tracks + `total_runtime`; empty-only format/label/producer)
- Admin write only; guests may read cache
- Do not cache rating / marketplace fields
- No bulk job, no TTL, no sidecar files
- Do not commit unless the user asks; do not commit `*.json` catalog data
- No `tests/` directory; verify with `php -r` / curl
- PHP/JS: 2-space indent in touched code
- Work on current branch

## File map

| File | Responsibility |
|------|----------------|
| `models/MusicCollection.php` | Allow `total_runtime`, `tracklist_cached_at`, `tracklist_source_release_id` in `updateAlbumRaw` |
| `api/tracklist_api.php` | Cache hit path; admin persist after Discogs; refresh gate |
| `index.php` | Refresh button markup |
| `assets/js/app.js` (+ min) | Show/hide Refresh; call refresh with CSRF; render from cache/discogs |
| `INSTALL.md` / `readme.md` | Brief docs |

---

### Task 1: Persist fields + helper

**Files:**
- Modify: `models/MusicCollection.php` (`updateAlbumRaw` `$allowedFields`)
- Modify: `api/tracklist_api.php` (add helpers at bottom / top after requires)

**Interfaces:**
- Produces:
  - `tracklistAlbumHasCache($album): bool`
  - `tracklistBuildCachePayload($album, $releaseInfo, $discogsReleaseId): array` → fields for `updateAlbumRaw` (always includes artist_name, album_name, id)
  - `tracklistPersistCache($musicCollection, $album, $releaseInfo, $discogsReleaseId): bool` — no-op / false if not authenticated or empty tracks
  - `tracklistStripLyricsForStorage($tracklist): array` — store only position/title/duration

- [ ] **Step 1: Extend `updateAlbumRaw` allowed fields**

Add to `$allowedFields`:
`'total_runtime', 'tracklist_cached_at', 'tracklist_source_release_id'`

(`tracklist` is already allowed.)

- [ ] **Step 2: Require auth in tracklist API**

Near top of `api/tracklist_api.php` after other requires:

```php
require_once __DIR__ . '/../config/auth_config.php';
```

- [ ] **Step 3: Add helpers**

```php
/**
 * @param array|null $album
 * @return bool
 */
function tracklistAlbumHasCache($album) {
  return is_array($album)
    && !empty($album['tracklist'])
    && is_array($album['tracklist']);
}

/**
 * Store only durable track fields (no lyrics URLs).
 *
 * @param array $tracklist
 * @return array
 */
function tracklistStripLyricsForStorage($tracklist) {
  $out = [];
  if (!is_array($tracklist)) {
    return $out;
  }
  foreach ($tracklist as $track) {
    if (!is_array($track)) {
      continue;
    }
    $out[] = [
      'position' => isset($track['position']) ? (string) $track['position'] : '',
      'title' => isset($track['title']) ? (string) $track['title'] : '',
      'duration' => isset($track['duration']) ? (string) $track['duration'] : '',
    ];
  }
  return $out;
}

/**
 * Build updateAlbumRaw payload for lean cache write.
 *
 * @param array $album Existing local album
 * @param array $releaseInfo Discogs-shaped payload (needs tracklist, optional total_runtime/format/label/producer)
 * @param string|int $discogsReleaseId
 * @return array
 */
function tracklistBuildCachePayload($album, $releaseInfo, $discogsReleaseId) {
  $payload = [
    'id' => $album['id'],
    'artist_name' => $album['artist_name'],
    'album_name' => $album['album_name'],
    'tracklist' => tracklistStripLyricsForStorage($releaseInfo['tracklist'] ?? []),
    'total_runtime' => isset($releaseInfo['total_runtime']) ? $releaseInfo['total_runtime'] : '',
    'tracklist_cached_at' => gmdate('c'),
    'tracklist_source_release_id' => $discogsReleaseId,
  ];

  foreach (['format', 'label', 'producer'] as $field) {
    $local = isset($album[$field]) ? trim((string) $album[$field]) : '';
    $incoming = isset($releaseInfo[$field]) ? trim((string) $releaseInfo[$field]) : '';
    if ($local === '' && $incoming !== '') {
      $payload[$field] = $incoming;
    }
  }

  return $payload;
}

/**
 * Persist cache when admin is logged in. Does not throw to callers.
 *
 * @return bool
 */
function tracklistPersistCache($musicCollection, $album, $releaseInfo, $discogsReleaseId) {
  if (!AuthHelper::isAuthenticated() || AuthHelper::mustChangePassword()) {
    return false;
  }
  if (!is_array($album) || empty($album['id'])) {
    return false;
  }
  $tracks = tracklistStripLyricsForStorage($releaseInfo['tracklist'] ?? []);
  if (count($tracks) === 0) {
    return false;
  }
  try {
    $payload = tracklistBuildCachePayload($album, $releaseInfo, $discogsReleaseId);
    $payload['tracklist'] = $tracks;
    return (bool) $musicCollection->updateAlbumRaw($payload);
  } catch (Exception $e) {
    return false;
  }
}
```

- [ ] **Step 4: Verify helpers with php -r**

```bash
php -r '
require "api/tracklist_api.php";
' 2>&1 | head -5
```

Prefer extracting helpers to `services/TracklistCacheService.php` if requiring `tracklist_api.php` exits. **If helpers are only functions in tracklist_api.php**, verify with:

```bash
php -r '
require "config/auth_config.php";
require "models/MusicCollection.php";
// paste or require a small include file
'
```

**Preferred structure:** put the four functions in `services/TracklistCacheHelper.php` and `require_once` from `tracklist_api.php`, then:

```bash
php -r 'require "services/TracklistCacheHelper.php"; echo tracklistAlbumHasCache(["tracklist"=>[["position"=>"A1","title"=>"X","duration"=>"1:00"]]]) ? "ok\n" : "fail\n";'
```

Expected: `ok`

- [ ] **Step 5: Commit** only if user asked

---

### Task 2: Cache read path + Discogs write + refresh gate

**Files:**
- Modify: `api/tracklist_api.php`

**Interfaces:**
- Consumes: helpers from Task 1; `AuthHelper::requireAdminAction` for refresh
- Produces: responses with `source: "cache"|"discogs"`

- [ ] **Step 1: Parse `refresh` flag** with other GET/POST inputs:

```php
$refresh = !empty($_GET['refresh']) || !empty($input['refresh']);
```

(For POST body, `$input` already decoded; for GET use `$_GET`.)

When `$refresh` is true:

```php
AuthHelper::requireAdminAction();
```

Non-admin refresh → existing JSON 401/403 exit from AuthHelper.

- [ ] **Step 2: Early cache hit** after `$album` is loaded (when `$albumId` set), **before** requiring Discogs for the main track path — but **after** the `$enrich` early exit (enrich stays Discogs-based).

Logic (main tracklist, not enrich):

```php
if ($albumId && !$album) {
  $album = $musicCollection->getAlbumById($albumId);
}
// existing discogs_release_id resolution from album...

if (!$refresh && tracklistAlbumHasCache($album)) {
  $artistForLyrics = !empty($album['artist_name']) ? $album['artist_name'] : $artistName;
  $enhanced = enhanceTracklistWithLyrics($album['tracklist'], $artistForLyrics);
  $releaseIdForLinks = $album['tracklist_source_release_id']
    ?? ($album['discogs_release_id'] ?? null);
  $response['success'] = true;
  $response['source'] = 'cache';
  $response['data'] = [
    'artist' => $album['artist_name'],
    'album' => $album['album_name'],
    'year' => $album['release_year'] ?? null,
    'cover_url' => $album['cover_url'] ?? null,
    'tracklist' => $enhanced,
    'format' => $album['format'] ?? '',
    'producer' => $album['producer'] ?? '',
    'label' => $album['label'] ?? '',
    'total_runtime' => $album['total_runtime'] ?? null,
    'discogs_release_id' => $album['discogs_release_id'] ?? $releaseIdForLinks,
    'discogs_url' => $releaseIdForLinks
      ? ('https://www.discogs.com/release/' . $releaseIdForLinks)
      : ('https://www.discogs.com/search/?q=' . urlencode($artistName . ' ' . $albumName) . '&type=release'),
    'shop_url' => $releaseIdForLinks
      ? ('https://www.discogs.com/sell/release/' . $releaseIdForLinks)
      : null,
    'rating' => null,
    'rating_count' => null,
    'has_reviews_with_content' => false,
    'num_for_sale' => null,
    'lowest_price' => null,
    'matched_reason' => 'local_cache',
    'tracklist_cached_at' => $album['tracklist_cached_at'] ?? null,
  ];
  $response['message'] = 'Tracklist served from local cache';
  tracklistJsonExit($response);
}
```

Note: Client may still call enrich afterward for rating/shop; that path unchanged.

- [ ] **Step 3: After successful Discogs responses** (both stored-release-id branch and search-match branch), before `tracklistJsonExit`:

```php
$response['source'] = 'discogs';
if ($albumId) {
  if (!$album) {
    $album = $musicCollection->getAlbumById($albumId);
  }
  if ($album) {
    tracklistPersistCache(
      $musicCollection,
      $album,
      $response['data'],
      $response['data']['discogs_release_id'] ?? $discogsReleaseId
    );
  }
}
```

Use the same `$response['data']` array that includes `tracklist` / `total_runtime` / format / label / producer.

- [ ] **Step 4: If Discogs unavailable and no cache** — keep existing error. If Discogs unavailable but cache exists, Step 2 already returned (unless refresh). On refresh with Discogs down, fail without wiping cache (never call persist with empty tracks).

- [ ] **Step 5: Smoke**

```bash
# Unauthenticated: if an album somehow has tracklist in JSON, GET returns source=cache
# Without cache: still needs Discogs
curl -sk "https://music.lndo.site/api/tracklist_api.php?artist=Test&album=Test&album_id=3" | head -c 200
```

Document results in the task report mentally; admin persist needs a logged-in session (manual or skip with note).

---

### Task 3: Tracklist modal Refresh UI

**Files:**
- Modify: `index.php` (button next to `#tracklistEditBtn`)
- Modify: `assets/js/app.js`
- Run: `npm run build:js`

- [ ] **Step 1: Markup** after Edit button:

```html
<button type="button" id="tracklistRefreshBtn" class="btn btn-secondary" style="display: none;">
  Refresh from Discogs
</button>
```

- [ ] **Step 2: Show/hide with Edit** in `showTracklist` where `tracklistEditBtn` visibility is set — same condition: `this.isAuthenticated && albumId`.

- [ ] **Step 3: Click handler** (bind once in init / existing tracklist edit init):

```javascript
async refreshTracklistFromDiscogs() {
  const modal = document.getElementById('tracklistModal');
  const albumId = modal && modal.dataset.albumId;
  if (!this.isAuthenticated || !albumId) {
    return;
  }
  const artistName = modal.dataset.artistName || '';
  const albumName = modal.dataset.albumName || '';
  const releaseYear = modal.dataset.releaseYear || '';
  const params = new URLSearchParams({
    artist: artistName,
    album: albumName,
    album_id: albumId,
    refresh: '1',
    currency: this.getSettings().currency_preference || 'USD'
  });
  if (releaseYear) {
    params.append('year', releaseYear);
  }
  const tracks = document.getElementById('tracklistModalTracks');
  if (tracks) {
    tracks.innerHTML = '<div class="tracklist-loading">Refreshing tracklist...</div>';
  }
  try {
    const response = await this.apiFetch(`api/tracklist_api.php?${params.toString()}`, {
      method: 'GET',
      cache: 'no-cache',
      headers: { 'Content-Type': 'application/json' }
    });
    // apiFetch must send CSRF on GET for refresh — if apiFetch only adds CSRF on mutating methods,
    // use method POST with JSON body { refresh: true, album_id, artist, album, year, currency }
    // matching tracklist_api POST input parsing.
    ...
  } catch (e) {
    if (tracks) {
      tracks.innerHTML = '<div class="tracklist-error">Could not refresh tracklist</div>';
    }
  }
}
```

**CSRF note:** Current `apiFetch` adds `X-CSRF-Token` for all methods that go through it — verify `apiFetch` always sets the header when `this.csrfToken` is set (it does for non-GET-only paths). If GET omits CSRF, implement Refresh as:

```javascript
await this.apiFetch('api/tracklist_api.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    artist: artistName,
    album: albumName,
    year: releaseYear || undefined,
    album_id: albumId,
    refresh: true,
    currency: this.getSettings().currency_preference || 'USD'
  })
});
```

Prefer **POST + refresh** so `requireAdminAction()` CSRF check passes.

- [ ] **Step 4: On successful refresh**, reuse the same rendering path as a normal tracklist success (extract a `renderTracklistModalData(data, …)` if needed, or re-call inner success block). Update `this.albums` in memory for that id’s `tracklist` if present so Condition/other local fields stay consistent.

- [ ] **Step 5: Ensure normal `showTracklist` GET** still works; cache hits should render faster without Discogs.

- [ ] **Step 6: `npm run build:js`**

---

### Task 4: Docs + verification notes

**Files:**
- Modify: `INSTALL.md`, `readme.md`

- [ ] Document: tracklists auto-cache for logged-in admin; Refresh in modal; guests read cache; included in catalog backup.
- [ ] Checklist from spec §4 — mark SKIP for authenticated items if no session.

## Spec coverage

| Spec item | Task |
|-----------|------|
| Data fields + empty-only metadata fill | 1, 2 |
| Cache read + `source` | 2 |
| Admin auto-persist | 2 |
| Refresh + CSRF/admin | 2, 3 |
| Modal Refresh button | 3 |
| No rating/shop in cache | 2 |
| Docs | 4 |

## Placeholder scan

None intentional. Refresh must use CSRF-capable request (POST preferred).
