# Tracklist Caching Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

Opening an album’s tracklist always fetches from Discogs (aside from a short browser HTTP cache). That is slower offline, burns API rate limit on every reopen, and there is no first-class way to persist tracks—even though `updateAlbumRaw` already allows a `tracklist` field. None of the catalog albums currently store a tracklist.

## Goals

- Persist tracklists on the local album after a successful Discogs fetch (admin only).
- Serve cached tracks on later opens without a Discogs release call.
- Let an authenticated admin **Refresh from Discogs** to overwrite the cache.
- Cache lean stable extras (`total_runtime`; fill empty format/label/producer only).
- Keep rating / marketplace data live when Discogs is available.

## Non-goals (v1)

- Bulk “cache all tracklists” job
- Editing individual track rows locally
- TTL / automatic expiry (Refresh is the staleness control)
- Caching rating, shop counts, or marketplace prices
- Sidecar cache files (use album fields so backup already covers them)
- Writing cache for guests or for opens without a local `album_id`

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Save behavior | **B** — Auto-save on successful fetch + Refresh control |
| Payload | Lean **B** — tracks + stable extras; not full live marketplace/rating |
| Who writes | **A** — Logged-in admin only; guests may read cache |
| Architecture | **1** — Server-side in `tracklist_api.php` + album JSON fields |

---

## Design

### 1. Data model

On each album in `music_collection.json` when cached:

| Field | Type | Purpose |
|-------|------|---------|
| `tracklist` | array of `{ position, title, duration }` | Cached tracks (Discogs shape already used by the API) |
| `total_runtime` | string (optional) | Stable runtime string from Discogs calc |
| `tracklist_cached_at` | string (ISO8601) | Last successful cache write |
| `tracklist_source_release_id` | string/int | Discogs release id used for that cache |

On cache write, also set **empty** local `format` / `label` / `producer` from Discogs when those local fields are missing or empty. Never overwrite non-empty local values.

Do **not** persist rating, `num_for_sale`, or `lowest_price`.

Missing or empty `tracklist` means uncached. Catalog backup/restore already includes these fields via `music_collection.json`.

### 2. API read / write

**Read** (`api/tracklist_api.php`, when `album_id` is present)

1. Load the local album.
2. If `tracklist` is a non-empty array **and** request does not ask to refresh → return cached tracks (and stored `total_runtime` if present). Response includes `source: "cache"`.
3. Optionally still run live enrich for rating/shop when Discogs is up; enrich failure must not block showing cached tracks.
4. Otherwise fetch from Discogs as today. Response includes `source: "discogs"`.

**Write** (after a successful Discogs tracklist fetch)

- Only when: admin session, valid local `album_id`, and non-empty tracklist.
- Persist via `MusicCollection::updateAlbumRaw` (or a thin helper wrapping it): `tracklist`, `total_runtime`, `tracklist_cached_at`, `tracklist_source_release_id`, plus empty-only format/label/producer fills.
- Guests never write. Opens without `album_id` never write.

**Refresh**

- Admin request with `refresh=1` (and CSRF consistent with other admin mutations).
- Bypass cache, fetch Discogs, overwrite cache fields, return `source: "discogs"`.

`updateAlbumRaw` already allows `tracklist`; extend allowed fields for `total_runtime`, `tracklist_cached_at`, and `tracklist_source_release_id` as needed.

### 3. UI

**Tracklist modal**

- When logged in as admin and `album_id` is present: show **Refresh from Discogs** near Edit.
- Clicking Refresh calls the tracklist API with `refresh=1` + CSRF and re-renders tracks from the response.
- Guests / logged-out: no Refresh button; they still see cached tracks when present.
- Auto-save is silent. Refresh may show a brief success/error message.
- Optional “Cached tracklist” hint when `source === "cache"` is nice-to-have, not required for v1.

### 4. Verification

1. Admin opens tracklist for an uncached album → Discogs fetch → album gains `tracklist` (+ lean fields).
2. Reopen same album (admin or guest) → tracks from cache; no Discogs release call required for tracks.
3. Guest open never writes/updates catalog JSON.
4. Admin **Refresh from Discogs** overwrites cache; tracks update if Discogs data changed.
5. Rating/shop still attempt live enrich when Discogs is available; tracks still show if enrich fails.
6. Catalog backup ZIP includes cached tracklists inside `music_collection.json`.

### 5. Error handling

| Case | Behavior |
|------|----------|
| Cache miss + Discogs down | Existing error path; no partial cache write |
| Admin refresh + Discogs fail | Keep existing cache; show error |
| Non-admin refresh attempt | Reject (401/403); do not bypass cache for write |
| Persist failure after Discogs success | Still return Discogs tracks to the UI; log/message optional |

---

## Open notes

- Lyrics links can continue to be computed at response time from track titles (as today) whether source is cache or Discogs; do not require storing lyrics URLs in the cache.
- Browser HTTP `Cache-Control` on successful responses may remain; local album cache is the durable layer.
