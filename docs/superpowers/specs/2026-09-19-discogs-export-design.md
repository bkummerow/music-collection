# Discogs Collection + Wantlist Export Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

Local catalog albums (owned / want-to-own) that already have a Discogs release ID cannot be pushed to Discogs in bulk. Users who primarily maintain the local site have no one-way upload path into their Discogs Collection and Wantlist.

## Goals

- One-way push: local **owned** → Discogs Collection (folder `0` = All); local **wanted (not owned)** → Discogs Wantlist.
- Skip releases already present on the target Discogs list.
- Skip albums with no `discogs_release_id` and report a `missing_id` count.
- **Never** remove or alter existing Discogs collection/wantlist items.
- Setup tab **Discogs Export** (separate from Import); reuse saved username + API credentials.
- Paged AJAX one-shot flow (same architecture family as import).
- **Do not change** Discogs import behavior or APIs beyond shared helpers if needed.

## Non-goals (v1)

- Two-way sync or scheduled jobs
- Deleting or moving items on Discogs when missing/unowned locally
- Custom Discogs collection folders (only folder `0`)
- Auto-matching albums that lack `discogs_release_id` (artist/title search)
- Pushing metadata edits (notes, condition, ratings) to Discogs
- Changing the existing Import tab flow

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Direction | **A** — One-way push only |
| Missing release ID | **A** — Skip + count as `missing_id` |
| Discogs deletes | **A** — Never remove/change existing Discogs items |
| UX location | **B** — Separate Setup **Discogs Export** tab |
| Architecture | **1** — Mirror import: paged AJAX + session state |

---

## Design

### 1. Relationship to import

- Import remains Discogs → local (merge, no local deletes).
- Export is additive: new actions, service, and Setup tab.
- Shared: `discogs_username` settings, Discogs API key resolution, rate limiter, CSRF/`requireAdminAction`, username field conventions.
- Import and export must not share a single in-progress session key that would collide if both tabs were used at once (use distinct session keys, e.g. `discogs_import_*` vs `discogs_export_*`).

### 2. Configuration

**Username**

- Reuse `discogs_username` from settings (same as import).
- Export tab: username input + “Save username” checkbox (default on), pre-filled from settings.

**API credentials**

- Reuse `DISCOGS_API_KEY` via existing `config/api_config.php` resolution.
- Write endpoints require a Discogs **personal access token** (user token) with permission to modify that user’s collection/wantlist. Document in Setup copy + INSTALL/readme: consumer-key-only credentials will fail writes.
- Refuse to start if `DiscogsAPIService::isAvailable()` is false.

### 3. Discogs API usage

| Local flag | Discogs target | Typical endpoint |
|------------|----------------|------------------|
| `is_owned=1` | Collection folder `0` | `POST /users/{username}/collection/folders/0/releases/{release_id}` |
| `want_to_own=1` and not owned | Wantlist | `POST /users/{username}/wants/{release_id}` |

**Already present**

- At export start (or lazily), build sets of release IDs already in Collection folder `0` and in Wantlist (reuse existing collection/wantlist page fetchers).
- If release ID is in the target set → count `skipped` (do not POST).
- Treat Discogs “already in collection/wantlist” error responses as `skipped` when a race occurs.

**Order of phases**

1. **Collection:** all local albums with `is_owned=1` and a non-empty `discogs_release_id`.
2. **Wantlist:** local albums with `want_to_own=1`, `is_owned≠1`, and a non-empty `discogs_release_id`.

Owned albums are never pushed to Wantlist in the same run (owned beats want locally).

**Rate limiting**

- Use existing `enforceRateLimit()` between Discogs calls.
- One browser page request ≈ bounded batch of local albums (e.g. 10–25) and at most that many POSTs, to avoid PHP timeouts.

### 4. Local catalog rules

- Read-only against local data during export (no local row updates required for v1).
- Albums without `discogs_release_id` → `missing_id` (included in progress totals; not POSTed).
- No local deletes; no Discogs deletes.

### 5. HTTP API (`music_api`)

All mutating export actions: `AuthHelper::requireAdminAction()`.

| Action | Method | Purpose |
|--------|--------|---------|
| `get_discogs_export_settings` | GET | Saved username + whether API key is set (+ csrf_token if following import pattern) |
| `export_discogs_start` | POST | Validate username/API; build work queues; cache existing Discogs ID sets; return first page plan |
| `export_discogs_page` | POST | Process one page `{ phase, page }` (or cursor); return progress + counts |
| `export_discogs_cancel` | POST | Clear export session state |

Suggested counts: `added`, `skipped`, `missing_id`, `errors`, plus `errors_sample`.

When finished: `done: true`, clear export session.

### 6. Frontend UX

- New Setup tab **Discogs Export** after **Discogs Import**.
- Fields: username, save checkbox; button “Push to Discogs”.
- Confirm dialog: add-only; keep tab open; never deletes on Discogs; albums without release ID are skipped.
- Progress: phase (Collection / Wantlist), page X of Y, live counts.
- Completion summary; resume-from-last-page on failure (same pattern as import: do not auto-cancel session).
- Disable start while running; show API-key-missing notice when applicable.

### 7. Files (expected)

| File | Role |
|------|------|
| `services/DiscogsAPIService.php` | Add-to-collection / add-to-wantlist (+ helpers); reuse list page fetchers for ID sets |
| `services/DiscogsExportService.php` (new) | Queue build, phase rules, skip/add orchestration |
| `api/music_api.php` | Export actions |
| `setup.php` + `assets/js/app.js` | Export tab + paged client loop; rebuild min JS |
| `INSTALL.md` / `readme.md` | Document Export + user-token requirement |

### 8. Testing / success criteria

1. Unauthenticated export start → 401; missing CSRF → 403.
2. Missing API key → clear failure before paging.
3. Owned album with release ID not on Discogs → appears in Collection after push.
4. Re-run → same release counted `skipped`.
5. Wanted-only album → Wantlist; owned album not also added to Wantlist.
6. Album without release ID → `missing_id`, no Discogs write.
7. Import still works unchanged after export is added.
8. No Discogs items removed by export.

## Rollback

Remove Export tab and export API actions; import and local data unchanged. Discogs side only ever received additive POSTs.

## Open notes

- Confirm personal access token vs consumer key before live smoke.
- Large catalogs: keep-tab-open copy; serial paging mandatory.
- Import and export session keys must remain distinct.
