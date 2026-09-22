# Artist Links Caching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist Discogs artist links on local albums and serve them on later enrich opens without a Discogs artist fetch; admin Refresh also re-fetches and overwrites those links.

**Architecture:** Store lean `artist_website` (+ `artist_website_cached_at`) on album rows via `updateAlbumRaw`, same pattern as tracklist cache. Enrich reads the album cache unless `refresh=1`. After a live Discogs artist-website fetch, persist silently. Refresh UI passes `refresh` through to enrich with CSRF (POST) so `requireAdminAction` succeeds.

**Tech Stack:** PHP (SimpleDB JSON catalog), `AuthHelper`, `tracklist_api.php`, `TracklistCacheHelper.php`, `DiscogsAPIService.php`, vanilla JS `app.js` + `npm run build:js`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-22-artist-links-caching-design.md`
- Album-row storage only; no TTL; no bulk job; no shared artist cache file
- Refresh updates tracks and artist links together
- Empty `websites` array is a valid cache hit; missing/non-object `artist_website` is a miss
- Do not cache rating / marketplace fields
- Do not commit unless the user asks; do not commit `data/*.json` catalog data
- PHP/JS: 2-space indent in touched code
- Work on current branch (`main` unless user says otherwise)
- Verify with `php -r` / small `tests/` script + manual enrich; no full PHPUnit suite required

## File map

| File | Responsibility |
|------|----------------|
| `models/MusicCollection.php` | Allow `artist_website`, `artist_website_cached_at` in `updateAlbumRaw` |
| `services/TracklistCacheHelper.php` | Has-cache / strip / persist helpers for artist website |
| `services/DiscogsAPIService.php` | `getTracklistExtras` accepts optional cached artist website |
| `api/tracklist_api.php` | Enrich: cache hit vs live fetch + persist; honor `refresh` |
| `assets/js/app.js` (+ `app.min.js`) | Pass refresh into enrich; CSRF POST; replace links DOM on refresh |
| `readme.md` / `INSTALL.md` | One short mention of artist-link cache (fold into docs task) |

---

### Task 1: Allow fields + persist helpers

**Files:**
- Modify: `models/MusicCollection.php` (`$allowedFields` in `updateAlbumRaw`)
- Modify: `services/TracklistCacheHelper.php`
- Create: `tests/artist_website_cache_test.php`

**Interfaces:**
- Produces:
  - `tracklistAlbumHasArtistWebsiteCache($album): bool` — true when `$album['artist_website']` is an array (object in JSON)
  - `tracklistStripArtistWebsiteForStorage($artistWebsite): array|null` — `{ name, websites:[{url,type}], discogs_url }` or null
  - `tracklistPersistArtistWebsite($musicCollection, $album, $artistWebsite): bool` — writes lean payload + `artist_website_cached_at`; false on gate fail / null strip

- [ ] **Step 1: Write the failing test**

Create `tests/artist_website_cache_test.php`:

```php
<?php
require_once __DIR__ . '/../services/TracklistCacheHelper.php';

function assert_true($cond, $msg) {
  if (!$cond) {
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
  }
}

assert_true(tracklistAlbumHasArtistWebsiteCache([
  'artist_website' => ['name' => 'X', 'websites' => [], 'discogs_url' => 'https://www.discogs.com/artist/1'],
]) === true, 'empty websites is cache hit');

assert_true(tracklistAlbumHasArtistWebsiteCache(['artist_website' => null]) === false, 'null is miss');
assert_true(tracklistAlbumHasArtistWebsiteCache([]) === false, 'missing is miss');

$lean = tracklistStripArtistWebsiteForStorage([
  'name' => 'Artist',
  'websites' => [
    ['url' => 'https://example.com', 'type' => 'Official Website', 'extra' => 'drop'],
  ],
  'discogs_url' => 'https://www.discogs.com/artist/9',
  'match_score' => 0.99,
]);

assert_true(is_array($lean) && $lean['name'] === 'Artist', 'name kept');
assert_true(!isset($lean['match_score']), 'match_score stripped');
assert_true(count($lean['websites']) === 1 && $lean['websites'][0]['url'] === 'https://example.com', 'url kept');
assert_true(!isset($lean['websites'][0]['extra']), 'extra stripped');
assert_true(tracklistStripArtistWebsiteForStorage(null) === null, 'null strip');

echo "artist_website_cache_test: OK\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/artist_website_cache_test.php`  
Expected: FAIL (undefined function `tracklistAlbumHasArtistWebsiteCache` or similar)

- [ ] **Step 3: Extend `updateAlbumRaw` allowed fields**

In `models/MusicCollection.php`, add to `$allowedFields`:

```php
'artist_website', 'artist_website_cached_at',
```

- [ ] **Step 4: Add helpers to `TracklistCacheHelper.php`**

Append (keep existing tracklist helpers unchanged):

```php
/**
 * @param array|null $album
 * @return bool
 */
function tracklistAlbumHasArtistWebsiteCache($album) {
  return is_array($album)
    && isset($album['artist_website'])
    && is_array($album['artist_website']);
}

/**
 * Lean artist website payload for album storage (no match_score).
 *
 * @param mixed $artistWebsite
 * @return array|null
 */
function tracklistStripArtistWebsiteForStorage($artistWebsite) {
  if (!is_array($artistWebsite)) {
    return null;
  }
  $websites = [];
  if (!empty($artistWebsite['websites']) && is_array($artistWebsite['websites'])) {
    foreach ($artistWebsite['websites'] as $website) {
      if (!is_array($website)) {
        continue;
      }
      $websites[] = [
        'url' => isset($website['url']) ? (string) $website['url'] : '',
        'type' => isset($website['type']) ? (string) $website['type'] : '',
      ];
    }
  }
  return [
    'name' => isset($artistWebsite['name']) ? (string) $artistWebsite['name'] : '',
    'websites' => $websites,
    'discogs_url' => isset($artistWebsite['discogs_url']) ? (string) $artistWebsite['discogs_url'] : '',
  ];
}

/**
 * Persist artist website cache after a successful Discogs fetch.
 * Same write gate as tracklistPersistCache (must-change-password skip; local album required).
 *
 * @param object $musicCollection
 * @param array $album
 * @param array $artistWebsite
 * @return bool
 */
function tracklistPersistArtistWebsite($musicCollection, $album, $artistWebsite) {
  if (AuthHelper::isAuthenticated() && AuthHelper::mustChangePassword()) {
    return false;
  }
  if (!is_array($album) || empty($album['id']) || empty($album['artist_name']) || empty($album['album_name'])) {
    return false;
  }
  $lean = tracklistStripArtistWebsiteForStorage($artistWebsite);
  if ($lean === null) {
    return false;
  }
  try {
    return (bool) $musicCollection->updateAlbumRaw([
      'id' => $album['id'],
      'artist_name' => $album['artist_name'],
      'album_name' => $album['album_name'],
      'artist_website' => $lean,
      'artist_website_cached_at' => gmdate('c'),
    ]);
  } catch (Exception $e) {
    return false;
  }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/artist_website_cache_test.php`  
Expected: `artist_website_cache_test: OK`

- [ ] **Step 6: Commit** (only if user asks)

```bash
git add models/MusicCollection.php services/TracklistCacheHelper.php tests/artist_website_cache_test.php
git commit -m "$(cat <<'EOF'
Add album artist-website cache helpers and allow-list fields.

EOF
)"
```

---

### Task 2: Enrich API — read cache, live fetch, persist

**Files:**
- Modify: `services/DiscogsAPIService.php` (`getTracklistExtras`)
- Modify: `api/tracklist_api.php` (enrich block ~lines 113–143)

**Interfaces:**
- Consumes: helpers from Task 1
- Produces: `getTracklistExtras($releaseId, $artistName = '', $masterId = null, $cachedPressingYear = null, $cachedArtistWebsite = null)` — when `$cachedArtistWebsite` is an array, use it and skip `getArtistWebsite`

- [ ] **Step 1: Extend `getTracklistExtras` signature**

Replace the artist-website block inside `getTracklistExtras` with:

```php
$artistWebsite = null;
if (is_array($cachedArtistWebsite)) {
  $artistWebsite = $cachedArtistWebsite;
} elseif ($artistName !== '') {
  $artistWebsite = $this->getArtistWebsite($artistName);
}
```

Add parameter after `$cachedPressingYear`:

```php
public function getTracklistExtras($releaseId, $artistName = '', $masterId = null, $cachedPressingYear = null, $cachedArtistWebsite = null) {
```

Update the method docblock to document `$cachedArtistWebsite`.

- [ ] **Step 2: Wire enrich path in `tracklist_api.php`**

In the `$enrich` block, after `$album` / `$cachedPressingYear` are resolved, before `getTracklistExtras`:

```php
$cachedArtistWebsite = null;
if (!$refresh && tracklistAlbumHasArtistWebsiteCache($album)) {
  $cachedArtistWebsite = $album['artist_website'];
}
$extras = $discogsAPI->getTracklistExtras(
  $discogsReleaseId,
  $artistForExtras,
  $masterId,
  $cachedPressingYear,
  $cachedArtistWebsite
);
```

After pressing-year persist (keep existing), add artist-website persist when this request did a live fetch and Discogs returned an array (including empty `websites`):

```php
if (
  $albumId
  && is_array($album)
  && $cachedArtistWebsite === null
  && isset($extras['artist_website'])
  && is_array($extras['artist_website'])
) {
  tracklistPersistArtistWebsite($musicCollection, $album, $extras['artist_website']);
}
```

Notes:
- On `$refresh === true`, `$cachedArtistWebsite` stays null → live fetch → overwrite on success.
- If live fetch returns `null`, do **not** call persist (keep prior cache).
- `$refresh` already triggers `AuthHelper::requireAdminAction()` earlier in the file.

- [ ] **Step 3: Smoke-check with php -r (no Discogs)**

Run:

```bash
php -r '
require "services/TracklistCacheHelper.php";
$album = ["id"=>1,"artist_name"=>"A","album_name"=>"B","artist_website"=>["name"=>"A","websites"=>[],"discogs_url"=>""]];
var_export(tracklistAlbumHasArtistWebsiteCache($album));
echo "\n";
'
```

Expected: `true`

- [ ] **Step 4: Manual API check (local site)**

1. Pick an album with `album_id` and no `artist_website` in `data/music_collection.json`.
2. Open tracklist modal (or GET enrich) once as any user → album gains `artist_website` + `artist_website_cached_at`.
3. Enrich again → response still has links; Discogs artist search should not be required for links (cache path).
4. As admin, POST tracklist refresh then enrich with `refresh=1` → cache timestamps update when Discogs succeeds.

- [ ] **Step 5: Commit** (only if user asks)

```bash
git add services/DiscogsAPIService.php api/tracklist_api.php
git commit -m "$(cat <<'EOF'
Serve and persist cached artist links on tracklist enrich.

EOF
)"
```

---

### Task 3: Refresh UI passes refresh into enrich

**Files:**
- Modify: `assets/js/app.js` (`applyTracklistModalApiData`, `enrichTracklistModal`, `refreshTracklistFromDiscogs`)
- Modify: `assets/js/app.min.js` via `npm run build:js`

**Interfaces:**
- Consumes: enrich API with `refresh` from Task 2
- Produces: `enrichTracklistModal(params, albumData, tracklistRequestId, options = {})` where `options.refresh === true` forces CSRF-backed refresh enrich

- [ ] **Step 1: Pass `refreshExtras` from Refresh success**

In `refreshTracklistFromDiscogs`, when calling `applyTracklistModalApiData`, add:

```javascript
this.applyTracklistModalApiData(data.data, {
  artistName,
  albumName,
  releaseYear,
  albumId,
  existingImage,
  tracklistRequestId,
  params,
  refreshExtras: true
});
```

- [ ] **Step 2: Thread option through `applyTracklistModalApiData`**

Destructure `refreshExtras` from `context` (default false). Change the enrich call at the end of that method to:

```javascript
this.enrichTracklistModal(params, albumData, tracklistRequestId, {
  refresh: !!refreshExtras
});
```

- [ ] **Step 3: Update `enrichTracklistModal` for refresh + CSRF + DOM replace**

`fetchWithCache` / `apiFetch` only attach `X-CSRF-Token` on non-GET. Because `$refresh` calls `requireAdminAction()` (CSRF required), refresh enrich must use **POST** via `apiFetch`.

Replace the enrich request section with:

```javascript
async enrichTracklistModal(params, albumData, tracklistRequestId, options = {}) {
  const modal = document.getElementById('tracklistModal');
  const tracks = document.getElementById('tracklistModalTracks');
  const info = document.getElementById('tracklistModalInfo');
  const shopLink = document.getElementById('tracklistModalShopLink');
  const shopText = document.getElementById('tracklistModalShopText');
  const discogsReleaseId = albumData.discogs_release_id;
  const forceRefresh = !!(options && options.refresh);
  if (!discogsReleaseId || !modal || !tracks) {
    return;
  }

  let data;
  try {
    if (forceRefresh) {
      const body = {
        enrich: true,
        refresh: true,
        release_id: discogsReleaseId,
        artist: params.get('artist') || modal.dataset.artistName || '',
        album: params.get('album') || modal.dataset.albumName || '',
        currency: params.get('currency') || (this.getSettings().currency_preference || 'USD')
      };
      const albumId = params.get('album_id') || modal.dataset.albumId || null;
      if (albumId) {
        body.album_id = albumId;
      }
      if (albumData.master_id) {
        body.master_id = albumData.master_id;
      }
      if (params.get('year')) {
        body.year = params.get('year');
      }
      const response = await this.apiFetch('api/tracklist_api.php', {
        method: 'POST',
        body: JSON.stringify(body)
      });
      data = await response.json();
    } else {
      const enrichParams = new URLSearchParams(params);
      enrichParams.set('enrich', '1');
      enrichParams.set('release_id', discogsReleaseId);
      if (albumData.master_id) {
        enrichParams.set('master_id', albumData.master_id);
      }
      const response = await this.fetchWithCache(`api/tracklist_api.php?${enrichParams}`, { cache: 'no-cache' });
      data = await response.json();
    }

    if (modal.dataset.tracklistRequestId !== tracklistRequestId) {
      return;
    }
    if (!data.success || !data.data) {
      return;
    }

    const extras = data.data;

    if (extras.artist_website) {
      const existingSection = tracks.querySelector('.artist-website-section');
      if (existingSection) {
        existingSection.remove();
      }
      const websiteHtml = this.renderArtistWebsite(extras.artist_website, albumData.discogs_url);
      if (websiteHtml) {
        tracks.insertAdjacentHTML('beforeend', websiteHtml);
        this.syncTracklistDiscogsAlbumLinkVisibility();
      }
    }

    // ... keep the rest of enrichTracklistModal (master_year, pressing_year, rating, shop) unchanged ...
  } catch (e) {
    // optional enrich failure is silent today — keep that behavior
  }
}
```

Important: when editing, preserve the existing master_year / pressing_year / rating / marketplace update logic after the artist-website block; only change how the request is made and how artist links are inserted (always replace section when `extras.artist_website` is present, so Refresh can update links).

- [ ] **Step 4: Build minified JS**

Run: `npm run build:js`  
Expected: exit 0; `assets/js/app.min.js` updated

- [ ] **Step 5: Manual UI verification**

1. Open tracklist as guest/admin for album without cache → links appear; JSON gains fields.
2. Reopen → links still appear (from cache).
3. Admin **Refresh from Discogs** → tracks refresh; links reappear (new fetch); `artist_website_cached_at` updates on success.
4. Confirm console has no CSRF 403 on refresh enrich.

- [ ] **Step 6: Commit** (only if user asks)

```bash
git add assets/js/app.js assets/js/app.min.js
git commit -m "$(cat <<'EOF'
Pass Refresh through to tracklist enrich for artist links.

EOF
)"
```

---

### Task 4: Docs

**Files:**
- Modify: `readme.md` (tracklist caching / artist links sections)
- Modify: `INSTALL.md` only if it already documents tracklist cache fields (add the two new album fields next to them)

- [ ] **Step 1: Document briefly**

In `readme.md` near tracklist caching bullets, add:

- Artist links (`artist_website`) are cached on the album after enrich; later opens skip Discogs artist fetch unless admin **Refresh from Discogs** (which also refreshes links).

In `INSTALL.md` field list (if present), add `artist_website` and `artist_website_cached_at`.

- [ ] **Step 2: Commit** (only if user asks)

```bash
git add readme.md INSTALL.md
git commit -m "$(cat <<'EOF'
Document album-level artist links caching.

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Album fields `artist_website` + `artist_website_cached_at` | 1 |
| Lean payload without `match_score` | 1 |
| Empty websites = cache hit | 1 |
| Enrich read from cache unless refresh | 2 |
| Persist after live Discogs success | 2 |
| Failed refresh keeps old links | 2 (no persist on null) |
| Refresh updates links via enrich `refresh` | 3 |
| CSRF for refresh enrich | 3 (POST + apiFetch) |
| No new UI controls | 3 |
| Docs | 4 |

## Plan self-review

- No placeholders / TBD.
- `getTracklistExtras` signature in Task 2 matches Task 3’s refresh behavior.
- CSRF pitfall for GET+refresh is explicitly handled with POST.
- Catalog JSON not committed.
