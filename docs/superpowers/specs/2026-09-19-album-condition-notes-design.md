# Album Condition & Notes Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

Albums store Discogs-oriented catalog fields and owned/wanted flags, but there is no place for collector-specific **media/sleeve condition** or freeform **notes**. Graded copies and private remarks live only in the user’s head or on Discogs (and Discogs import deliberately does not bring those into the local catalog).

## Goals

- Let an authenticated admin set **media condition**, **sleeve condition**, and **notes** on each album.
- Persist them in `data/music_collection.json` with the album object.
- Show grades in the **Add/Edit album modal** and a compact **Condition** column in the collection table.
- Keep these fields **local-only**: Discogs import/export must not set or clear them.

## Non-goals (v1)

- Purchase price or storage location
- Condition filters, notes-in-search, or sort-by-grade
- Importing or exporting condition/notes to/from Discogs
- Bulk edit of personal fields
- Sleeve extras beyond the Discogs grade list (e.g. Generic / No Cover) — defer unless requested later
- Migration job rewriting existing JSON (missing keys = empty)

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Fields | Condition + notes only (no location/price) |
| Condition model | **B** — Media + sleeve separately |
| Discogs sync | **A** — Local-only; import/export never overwrite |
| UI surface | **B** — Modal editors + compact table Condition column |
| Architecture | **1** — First-class keys on each album object |

---

## Design

### 1. Data model

On each album in `music_collection.json`:

| Field | Type | Allowed values | Default |
|-------|------|----------------|---------|
| `media_condition` | string | Empty string, or one of the Discogs media grades below | `""` / omitted |
| `sleeve_condition` | string | Same allow-list as media | `""` / omitted |
| `notes` | string | Free text, trimmed, max **2000** characters | `""` / omitted |

**Discogs grade allow-list** (exact strings):

- `Mint (M)`
- `Near Mint (NM or M-)`
- `Very Good Plus (VG+)`
- `Very Good (VG)`
- `Good Plus (G+)`
- `Good (G)`
- `Fair (F)`
- `Poor (P)`

No schema migration: albums without these keys are treated as empty in UI and API. Catalog backup/restore already includes whatever is in `music_collection.json`.

### 2. UI

**Add/Edit album modal**

- Section **Condition & notes** (after ownership status):
  - Media condition — `<select>` (blank + allow-list)
  - Sleeve condition — `<select>` (blank + allow-list)
  - Notes — `<textarea>`
- Populate on edit; blank on add.
- Saved with the existing album save flow (auth + CSRF unchanged).

**Collection table**

- One **Condition** column.
- Display format: `media / sleeve` using short labels where practical (e.g. `NM / VG+`), or the stored string if shortening is awkward.
- Both empty → blank or em dash.
- Notes are **not** a table column. Optional tooltip/title when notes exist is nice-to-have, not required for v1.

### 3. API & persistence

**Write**

- Add/update album payloads may include `media_condition`, `sleeve_condition`, `notes`.
- Server validates each condition against the allow-list or empty; unknown values → **400** with a clear message.
- Notes: trim; if longer than 2000 chars → **400** or truncate consistently (prefer reject with message).
- Persist through `MusicCollection` add/update paths (including `updateAlbumRaw` when that path is used for edits) so values round-trip in JSON.

**Read**

- List/get responses already return album objects; new keys appear when present. Clients treat missing as empty.

**Discogs**

- Import merge must **not** write or clear `media_condition`, `sleeve_condition`, or `notes`.
- Export must **not** push these fields to Discogs.

**Auth**

- Same gates as existing album mutate actions (`requireAdminAction` / session + CSRF).

### 4. Verification

1. Add or edit an album with both grades + notes → values persist and reload in the modal.
2. Collection table shows a compact Condition cell (e.g. `NM / VG+`) or blank when empty.
3. API rejects an invalid grade string.
4. Discogs import re-run leaves existing personal fields unchanged.
5. After save, a catalog backup ZIP still contains the fields inside `music_collection.json`.

### 5. Error handling

| Case | Behavior |
|------|----------|
| Invalid grade | 400 + message; album not updated |
| Notes too long | 400 + message (prefer over silent truncate) |
| Unauthenticated mutate | Existing 401 / auth redirect behavior |

---

## Open notes

- Short display labels in the table (`NM`, `VG+`) may map from the full Discogs strings in the UI only; stored values remain the full allow-list strings.
- If `updateAlbum`’s fixed parameter list is awkward, prefer extending that API and `updateAlbumRaw` together so both edit paths stay consistent.
