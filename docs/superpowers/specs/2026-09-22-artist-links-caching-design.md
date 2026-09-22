# Artist Links Caching Design

**Date:** 2026-09-22  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

Artist links (`artist_website`) in the tracklist enrich path are fetched from Discogs on every miss of a short in-memory (1 hour) PHP cache. Links change rarely and do not need to be evergreen. Tracklists already persist on album rows; artist links do not, so reopen/enrich still burns Discogs artist search + artist detail calls.

## Goals

- Persist artist links on the local album after a successful Discogs fetch.
- Serve cached artist links on later enrich opens without a Discogs artist fetch.
- Let admin **Refresh from Discogs** overwrite both tracklist cache and artist-link cache together.
- Keep rating / marketplace data live when Discogs is available.

## Non-goals (v1)

- TTL / automatic expiry (Refresh is the staleness control)
- Bulk “cache all artist links” job
- Shared artist-level cache file (accept per-album duplication)
- Separate UI control for refreshing links only
- Changing which link *types* are shown (settings remain render-time filters)

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Storage | **Album row** in `music_collection.json` (same pattern as tracklist cache) |
| Refresh | **A** — Refresh from Discogs updates tracks and artist links together |
| Architecture | Server-side in enrich / `getTracklistExtras` + album JSON fields + thin persist helper |

---

## Design

### 1. Data model

On each album in `music_collection.json` when artist links are cached:

| Field | Type | Purpose |
|-------|------|---------|
| `artist_website` | object | Lean payload: `{ name, websites: [{ url, type }], discogs_url }` (same shape the UI already expects; omit `match_score` from storage) |
| `artist_website_cached_at` | string (ISO8601) | Last successful cache write |

Absence of `artist_website`, or a non-object value, means uncached. A stored object with an empty `websites` array **is** a valid cache hit (avoids re-hitting Discogs for artists with no allowed link types). Catalog backup/restore already includes these fields via `music_collection.json`.

### 2. API read / write

**Read** (enrich path in `api/tracklist_api.php` / `DiscogsAPIService::getTracklistExtras`)

1. Load the local album when `album_id` is present.
2. If `artist_website` is a non-null object **and** the request does not ask to refresh → return it from album cache (no Discogs artist search/fetch).
3. Otherwise call Discogs `getArtistWebsite` as today.

Rating / marketplace / other live extras stay unchanged. Pressing-year album cache behavior stays as-is.

**Write** (after a successful Discogs artist-website fetch)

- Persist lean `artist_website` + `artist_website_cached_at` via the same helper style as tracklist persist (`MusicCollection::updateAlbumRaw`, allow-list those fields).
- Same write gate as tracklist cache: require a real local album row; skip if authenticated session must change password; server may write Discogs data for guests when a local album id is present.
- Opens without `album_id` never write.
- **Add album** (`music_api` add / replace / keep-both): when any artist-link display toggles are enabled in Album Display settings, fetch and persist artist links on the new/updated row at save time (so the first tracklist open can use cache without waiting on enrich).

**Refresh**

- Existing admin **Refresh from Discogs** POSTs `refresh: true` for the tracklist (bypasses tracklist cache), then the UI calls enrich for extras.
- Today enrich does **not** receive `refresh`. To honor “Refresh updates artist links too”, the Refresh UI path must pass `refresh=1` on the subsequent enrich request (admin + CSRF already required when `refresh` is set). Enrich then bypasses album `artist_website` and overwrites it on successful Discogs fetch.
- Failed artist-link refresh keeps the existing artist-link cache (same as tracklist failure behavior).

### 3. UI

- No new controls. Auto-save remains silent.
- Existing **Refresh from Discogs** remains the only admin override; it now also refreshes artist links by passing `refresh` through to enrich after a successful tracklist refresh.
- Artist link visibility settings stay preference-only (filter at render time; do not change what is stored).

### 4. Verification

1. Enrich an album with no `artist_website` → Discogs fetch → album gains `artist_website` (+ `artist_website_cached_at`).
2. Re-enrich same album → links from cache; no Discogs artist search required for links.
3. Admin **Refresh from Discogs** → tracklist refresh then enrich with `refresh=1` → Discogs artist fetch again; artist-link cache overwritten on success.
4. Refresh with Discogs artist fetch failing → previous `artist_website` kept.
5. Catalog backup ZIP includes cached artist links inside `music_collection.json`.

### 5. Error handling

| Case | Behavior |
|------|----------|
| Cache miss + Discogs down | Links missing/null; tracks/other extras unchanged |
| Admin refresh + artist fetch fails | Keep existing `artist_website` |
| Persist failure after Discogs success | Still return Discogs links to the UI; do not throw to callers |
| No local `album_id` | No persist (same as tracklist) |

---

## Open notes

- Per-album duplication of the same artist’s links is accepted; payloads are small.
- Browser HTTP `Cache-Control` on successful enrich responses may remain; local album cache is the durable layer.
- In-memory Discogs service cache can remain as a short-lived layer in front of live fetches; album fields are the durable cache of record.
