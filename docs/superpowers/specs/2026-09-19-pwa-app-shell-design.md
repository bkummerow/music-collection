# PWA App-Shell Service Worker Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

The site has a dynamic web manifest and icons, and **Clear Caches** claims to clear “service workers, PWA caches,” but there is **no service worker**. `caches.keys()` is usually empty, so Clear Caches is mostly storage wipe + hard reload. Installability and documented PWA/cache behavior are incomplete.

## Goals

- Add a **hand-rolled** service worker that caches **app-shell static assets** only.
- Keep **HTML/PHP, APIs, and catalog data on the network** (no SW caching).
- Make **Clear Caches** real: delete Cache API entries, **unregister** the service worker, then hard-reload (existing storage wipe preserved).
- Keep the app **installable** via existing `site.webmanifest.php` + new SW registration.
- Document accurate behavior in `readme.md` / `INSTALL.md`.

## Non-goals

- Offline browsing of the album list or API responses
- Workbox or build-time GenerateSW
- Caching HTML/PHP documents
- Push notifications / background sync
- Caching Discogs or other cross-origin responses

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Scope | **A** — App-shell static assets only |
| HTML / PHP | **A** — Always network; never cache navigations or `.php` |
| Implementation | **1** — Hand-rolled `sw.js` (no Workbox) |
| Clear Caches | Delete all caches + unregister SW + existing storage wipe + hard reload |

---

## Design

### 1. Service worker file

**Create:** `sw.js` at the site root (same directory as `index.php`), so registration scope covers the app (including configured `start_url` / subdirectory installs when the SW URL is under that path).

**Cache name:** versioned string, e.g. `music-shell-v1`. Bump the version when the precache list or shell strategy changes so Activate can drop old caches.

**Install (precache):** Open the shell cache and `addAll` (or equivalent) a fixed list of same-origin URLs, including at least:

- `assets/css/critical.css`
- `assets/css/critical-dark.css`
- `assets/css/main.css`
- `assets/js/app.min.js`
- `assets/js/demo.min.js` (if present / used on demo)
- Favicons and PWA icons referenced by the app (`favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png`, `android-chrome-192x192.png`, `android-chrome-512x512.png`)

Paths are relative to the SW scope. Query-string versioning on script tags (`app.min.js?v=…`) is handled at fetch time (see below); precache may use bare paths.

**Activate:** Claim clients if needed; delete Cache Storage keys that match the shell prefix but are not the current cache name.

**Fetch rules:**

| Request | Strategy |
|---------|----------|
| Navigation requests (`mode === 'navigate'`) | Network only |
| Same-origin paths under `api/`, `data/`, or ending in `.php` | Network only |
| Same-origin shell assets (`assets/…`, icons, `.css` / `.js` matching shell) | Cache-first; on miss, network then put into shell cache |
| Cross-origin (Discogs, Google Fonts, etc.) | Network only — do not put in Cache Storage |

Do not cache opaque or error responses into the shell cache.

### 2. Registration

Register from the main app JS (`assets/js/app.js` → rebuild `app.min.js`) when `'serviceWorker' in navigator`, after the app boots (e.g. init), with:

```js
navigator.serviceWorker.register('sw.js')
```

(or a path derived from the app base if subdirectory `start_url` requires it — prefer a single relative `sw.js` next to `index.php`).

Also register from `setup.php` if that page does not load `app.js` (small inline script or shared snippet).

Registration is best-effort: failures are logged; app must work without a SW (HTTP without SW support, private modes, etc.).

### 3. Clear Caches

Extend `clearAllCaches()` in `app.js`:

1. If `caches` exists: `caches.keys()` then `caches.delete` each.
2. If `navigator.serviceWorker` exists: `getRegistrations()` then `unregister()` each.
3. Existing behavior: clear `localStorage` / `sessionStorage`, restore `browserId` and notification tracking keys.
4. Clear in-memory selection fields (existing).
5. Hard reload with `_cache_clear` / `cache_cleared` query params (existing).

Optional: show a short success path message that SW + caches were cleared (existing reload success UX if any).

### 4. Docs

**`readme.md` — Cache Management:** State that a service worker caches app-shell static assets (CSS/JS/icons); Clear Caches deletes those caches and unregisters the SW; album/API data is not cached by the SW.

**`INSTALL.md`:** One short note under an appropriate ops section that SW lives at `sw.js` and Clear Caches unregisters it.

### 5. Verification

- With SW registered: Application → Cache Storage shows `music-shell-v1` (or current name) with shell assets after visit.
- Clear Caches: caches empty (or gone), no active SW registration (or pending removal), page reloads.
- Network tab: `api/music_api.php` and navigations are not served from SW cache.
- Lando (`music.lndo.site`) HTTPS/local SW registration works.

## Out of scope (explicit)

- Offline collection / API cache  
- Workbox / npm GenerateSW  
- Caching `index.php` / `setup.php` HTML  
- Changing manifest icons or theme colors beyond what exists  

## Error handling

- Precache `addAll` failure: install can fail; leave previous SW until a successful install (standard SW behavior). Prefer resilient install (cache what succeeds) only if `addAll` proves too brittle in practice — default is atomic `addAll` of the fixed list.
- Unregister / cache delete errors: surface existing Clear Caches error message; do not leave the user without a reload attempt if partial success is possible.
