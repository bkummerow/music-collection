# Catalog Backup / Restore Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

The local catalog lives in `data/music_collection.json` (and display/app preferences in `data/settings.json`). Discogs import/export syncs with Discogs but does not snapshot local files. There is no first-class download/restore path; the only related tool is the authenticated raw JSON editor (`update_raw`), which is easy to misuse and does not package settings.

## Goals

- Let an authenticated admin **download** a dated backup ZIP of the catalog, optionally including settings.
- Let an authenticated admin **restore** from a ZIP or raw catalog JSON.
- On restore: **always replace** the catalog; restore settings **only** when explicitly checked (and present in the backup).
- Protect against bad uploads with JSON validation and pre-write `.bak` copies of files about to be overwritten.
- Reuse existing auth/CSRF gates (`AuthHelper::requireAdminAction()`).

## Non-goals (v1)

- Merge/restore-by-id or partial album merges
- Scheduled or cloud backups
- Restoring Discogs credentials or password hashes (those stay in env / `api_config.local.php` / `auth_config.php`)
- Changing Discogs import/export behavior
- Public (unauthenticated) download

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Scope | **C** — Download + restore; ZIP may include `settings.json` |
| Restore semantics | **B** — Catalog always replaced; settings only if checkbox + present |
| UX location | **A** — New Setup tab **Backup** |
| Architecture | **1** — Server-built ZIP from `data/` files; authenticated download + upload restore |

---

## Design

### 1. Setup UI — Backup tab

Place a **Backup** tab in Setup (after Discogs Export is fine).

**Download section**

- Checkbox `#backupIncludeSettings` — “Include settings.json” (default checked)
- Button `#backupDownloadBtn` — “Download backup”
- Short note: file is for local safekeeping; does not change Discogs

**Restore section**

- File input `#backupRestoreFile` — accept `.zip,.json`
- Checkbox `#backupRestoreSettings` — “Also restore settings if present in backup” (default unchecked)
- Button `#backupRestoreBtn` — “Restore backup”
- Confirm dialog explaining catalog will be fully replaced; settings only if checked
- Status message region for success/errors

### 2. Download API

| Action | Method | Purpose |
|--------|--------|---------|
| `backup_download` | GET or POST | Stream a ZIP of current data files |

Prefer **POST** with CSRF (consistent with other admin mutations), or GET with auth session + CSRF header if the client uses `apiFetch` blob download. Implementation may use POST that returns the ZIP body with `Content-Disposition: attachment`.

**ZIP contents**

- Always: `music_collection.json` (exact file bytes from `data/music_collection.json`)
- If include_settings: `settings.json` (exact file bytes from `data/settings.json` when readable)
- Optional manifest `backup-meta.json`: `{ "created_at": ISO8601, "app": "MusicCollection", "includes_settings": bool }` for clarity (not required for restore)

**Filename:** `music-backup-YYYYMMDD-HHMMSS.zip`

**Auth:** `requireAdminAction()` (or authenticated + CSRF equivalent for the chosen method).

### 3. Restore API

| Action | Method | Purpose |
|--------|--------|---------|
| `backup_restore` | POST multipart | Upload ZIP or JSON; replace catalog; optionally settings |

**Inputs**

- File field `backup_file`
- Flag `restore_settings` (0/1)

**Processing**

1. Require admin + CSRF.
2. Reject empty/oversized uploads (reasonable cap, e.g. 20–50 MB).
3. If `.json`: treat as catalog only; ignore settings flag unless ZIP.
4. If `.zip`: extract `music_collection.json` (required); `settings.json` optional.
5. Validate catalog JSON: decode succeeds; top-level has `albums` key that is an array or object.
6. If restoring settings: validate settings JSON is a non-null object.
7. Before overwrite: copy current `data/music_collection.json` → `data/music_collection.json.bak.YYYYMMDDHHMMSS` (and same for settings if restoring).
8. Write new files atomically when practical (write temp + rename).
9. Return counts/summary: album count after restore, whether settings were restored, bak filenames.

**Failure:** leave existing files intact if validation fails before write; if write fails mid-way, prefer rolling back from the `.bak` just written.

### 4. Client behavior

- Download: `apiFetch` → blob → trigger browser save.
- Restore: `FormData` upload with CSRF header; on success show message and call `loadAlbums()` / reload setup theme if settings restored.
- Disable buttons while in flight.

### 5. Security / privacy

- Admin-only; no public backup URL.
- Do not include `api_config.local.php`, `auth_config.php`, or other secrets in the ZIP.
- Settings restore must not silently grant Discogs tokens (tokens are not in settings.json today).
- Demo mode: allow if authenticated (same as other setup tools); document that demo hosts should not treat backups as multi-tenant.

### 6. Files (expected)

| File | Role |
|------|------|
| `api/music_api.php` | `backup_download`, `backup_restore` |
| Optional thin helper | ZIP build/extract + validation |
| `setup.php` | Backup tab markup |
| `assets/js/app.js` (+ min) | Download/restore UX |
| `INSTALL.md` / `readme.md` | Document Backup tab |
| `.gitignore` | Keep ignoring `*.bak*` / bak patterns if not already |

### 7. Testing / success criteria

1. Unauthenticated download/restore → 401; missing CSRF → 403.
2. Download ZIP contains valid `music_collection.json`; with checkbox, also `settings.json`.
3. Restore JSON catalog replaces albums; album count matches backup.
4. Restore without settings checkbox leaves `settings.json` unchanged.
5. Restore with checkbox + settings in ZIP updates settings.
6. Invalid JSON rejected; previous catalog remains.
7. Pre-restore `.bak` files exist after a successful restore.

## Rollback

Remove Backup tab and API actions. Existing `.bak` files on disk may remain harmlessly.

## Open notes

- PHP ZipArchive (or equivalent) required on the host for ZIP download/restore; if missing, fail with a clear message (or fall back to catalog-only JSON download — document choice in implementation: prefer hard fail with install note).
- Large collections: stream ZIP when possible; keep restore size limits explicit.
