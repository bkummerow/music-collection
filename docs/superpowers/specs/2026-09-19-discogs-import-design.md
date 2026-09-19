# Discogs Collection + Wantlist Import Design

**Date:** 2026-09-19  
**Status:** Approved (pending user review of this file)  
**Site:** personal_site (Music Collection Manager)

## Problem

Albums are added one-by-one via Discogs search/barcode. Users who already maintain a Discogs Collection and Wantlist must re-enter data manually. There is no bulk import or re-sync path.

## Goals

- Import **both** Discogs Collection (owned) and Wantlist into the local JSON catalog.
- Support **re-runs** that merge/update instead of creating duplicate rows.
- Remember Discogs **username** so import is one click after first setup.
- Run as an authenticated admin **one-shot** flow with paged progress (Approach 1).
- Reuse existing auth/CSRF gates and Discogs API key resolution.

## Non-goals (v1)

- Scheduled / background sync
- Deleting local albums missing from Discogs
- Discogs folder filters (use “All” collection folder `0`)
- Importing condition, notes, purchase price as first-class local fields
- Multi-user Discogs accounts

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Scope | **C** — Collection + Wantlist |
| Duplicates | **B** — Merge/update (flags + metadata); no duplicate rows for same release |
| UX | **B** — One-shot button + remember username |
| Architecture | Paged AJAX import in-browser (not CLI-only, not job queue) |

---

## Design

### 1. Configuration

**Discogs username**

- Store `discogs_username` under app settings in `data/settings.json` (same settings document as theme/display/app).
- Setup UI (and/or Settings): text field + save; pre-filled on load from settings.
- Import may accept an optional username override for the run; if “Save username” is checked (default on), persist it.

**API credentials**

- Use existing `DISCOGS_API_KEY` via `config/api_config.php` (env → `api_config.local.php` → empty).
- Import refuses to start if `DiscogsAPIService::isAvailable()` is false (clear message to configure API key).

### 2. Discogs API usage

Add methods on `DiscogsAPIService` (or a thin `DiscogsImportService` that uses it):

| Source | Endpoint (Discogs) | Local flags |
|--------|-------------------|-------------|
| Collection | `GET /users/{username}/collection/folders/0/releases` | `is_owned=1`, `want_to_own=0` |
| Wantlist | `GET /users/{username}/wants` | `is_owned=0`, `want_to_own=1` |

- Paginate with `page` + `per_page` (e.g. 50–100). Respect existing `enforceRateLimit()`.
- Each item exposes a nested `basic_information` (title, artists, year, cover, formats, labels, id as release id). Map into the same shape used when adding albums today (`artist_name`, `album_name`, `release_year`, `cover_url`, `cover_images` when available, `discogs_release_id`, `format`, `label`, `style` if present).
- Prefer release `id` from the collection/wantlist item as `discogs_release_id`. Optionally call `getReleaseInfo` / cover helpers only when cover or metadata is missing (keep v1 light: use basic_information first; enrich only if needed for parity with manual add).

**Conflict rule:** If the same `discogs_release_id` appears in both Collection and Wantlist during one import run, **Collection wins** → owned, not wanted.

**Order of phases:** Process Collection first, then Wantlist (so wantlist cannot overwrite owned with want-only flags for the same release in the same run). When applying Wantlist updates, do not clear `is_owned` if the album is already owned locally unless Discogs Collection phase already ran and did not include it — **v1 rule:** Wantlist updates only set `want_to_own=1` when `is_owned` is not already 1; if already owned, leave owned and leave `want_to_own=0` (owned beats want).

### 3. Match and merge

Match priority:

1. `discogs_release_id` equal to Discogs release id  
2. Else `MusicCollection::getAlbumByArtistAndName()` (case-insensitive)

**New album:** insert via existing add path / model with mapped fields and flags.

**Existing album:**

- Update ownership flags per phase rules above.
- Refresh `discogs_release_id` if missing locally.
- Refresh `cover_url` / `cover_images` when local cover is empty and Discogs has one.
- Refresh year/style/format/label/producer when local field empty and Discogs provides a value (do not wipe user-edited non-empty fields in v1).

**Skipped:** identical flags and no metadata gaps to fill → count as skipped.

**Never:** delete local albums absent from Discogs.

### 4. HTTP API (music_api or dedicated import endpoints)

All mutating import actions: `AuthHelper::requireAdminAction()` (auth + CSRF + must-change gate).

Suggested actions:

| Action | Method | Purpose |
|--------|--------|---------|
| `get_discogs_import_settings` | GET | Return saved `discogs_username` (and whether API key is set) |
| `save_discogs_import_settings` | POST | Persist `discogs_username` |
| `import_discogs_start` | POST | Validate username + API; initialize session import state (phases, counters); return first page plan |
| `import_discogs_page` | POST | Process one page: `{ phase: 'collection'\|'wantlist', page: N }`; return progress + cumulative counts |
| `import_discogs_cancel` | POST | Clear session import state |

Session state (server-side) holds running totals and phase cursor so the client only advances page-by-page.

Response sketch for a page:

```json
{
  "success": true,
  "data": {
    "phase": "collection",
    "page": 2,
    "pages": 5,
    "done": false,
    "next": { "phase": "collection", "page": 3 },
    "counts": {
      "added": 12,
      "updated": 3,
      "skipped": 40,
      "errors": 0
    },
    "errors_sample": []
  }
}
```

When finished: `done: true`, final counts, clear session state.

### 5. Frontend UX

- Location: Setup page tab **or** Settings dropdown entry “Import from Discogs” opening a modal / setup section (prefer Setup → new “Discogs Import” tab next to API Config for discoverability).
- Fields: username (required), checkbox “Save username” (default on).
- Button: Import — confirm dialog explaining merge behavior and that the tab must stay open.
- Progress UI: phase label, page X of Y, live counts.
- Completion: summary modal/toast; refresh album list/stats if on main page.
- Errors: show sample messages (rate limit, 404 username, network); allow retry from last page if state remains.

Demo mode: allow import only if API key + username configured; no special demo dataset dependency. Optionally hide or disable on public demo if shared Railway demo should not mutate toward a private Discogs user — **default:** feature available whenever authenticated; document that public demo users should not point at a private collection.

### 6. Rate limiting and timeouts

- Use existing Discogs rate limiter between page requests.
- One HTTP request from the browser ≈ one Discogs page fetch + local merges (bounded work) to avoid PHP max_execution_time kills.
- Client waits for each `import_discogs_page` before requesting the next (serial).

### 7. Files (expected touch list)

| File | Role |
|------|------|
| `services/DiscogsAPIService.php` | Collection + wantlist page fetchers |
| `services/DiscogsImportService.php` (new, optional) | Match/merge orchestration |
| `models/MusicCollection.php` | Helpers if needed (find by release id) |
| `api/music_api.php` | Import actions + settings |
| `api/theme_api.php` or settings path | Persist `discogs_username` in settings.json |
| `setup.php` + `assets/js/app.js` | UI + paged client loop |
| `INSTALL.md` / `readme.md` | Document import |

### 8. Testing / success criteria

1. With valid key + username: collection pages import owned albums; wantlist imports wanted.  
2. Re-run: no duplicate `discogs_release_id` rows; flags converge to Discogs.  
3. Release in both lists → owned, not wanted.  
4. Unauthenticated / missing CSRF → 401/403.  
5. Missing API key → clear failure before paging.  
6. Invalid username → Discogs error surfaced in UI.  
7. Progress completes with accurate added/updated/skipped counts (± small error samples).

## Rollback

Disable/remove Setup import UI and API actions; local data remains as last successful merge. Username setting can remain harmlessly in settings.json.

## Open notes

- Security hardening branch may still be uncommitted; import must call whatever auth helpers exist on the branch (`requireAdminAction`).  
- Large collections (1000+ releases) will take several minutes; UI copy should say so.  
- Rotate any previously exposed Discogs token if not already done.
