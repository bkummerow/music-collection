# Discogs Condition & Notes Sync Design

**Date:** 2026-09-20  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

Local albums store `media_condition`, `sleeve_condition`, and `notes`, but Discogs import/export deliberately ignore them. Collectors who grade copies on Discogs (or locally) cannot keep those fields in sync.

## Goals

- **Import:** Pull Discogs collection instance `media_condition`, `sleeve_condition`, and `notes` into local albums (wantlist: **notes only**).
- **Export:** Push local values to Discogs collection instances (wantlist: notes only), including clearing Discogs when local is empty.
- Replace “local-only” UI/docs copy with the sync rules below.
- Keep grade allow-list validation (`AlbumPersonalFields`) as the single source of truth for valid strings.

## Non-goals

- Syncing Discogs **rating**
- Custom collection folders beyond current export folder usage
- Changing fill-empty-only merge for other metadata (cover, style, format, etc.)
- Two-way live sync outside import/export runs

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Conflict model | **B** — Discogs wins on import; local wins on export |
| Empty Discogs on import | **B** — Empty Discogs leaves local alone |
| Empty local on export | **A** — Empty local clears Discogs |
| Architecture | **1** — Extend existing import + export (not a separate Setup job) |

---

## Design

### 1. Field mapping

| Local key | Discogs collection instance | Discogs wantlist |
|-----------|----------------------------|------------------|
| `media_condition` | `media_condition` | — (not synced) |
| `sleeve_condition` | `sleeve_condition` | — (not synced) |
| `notes` | `notes` | `notes` |

Discogs grade strings must match the existing allow-list (or empty). Values that fail validation are treated as **empty** on import (do not write invalid strings). Export only sends allow-list grades or `""`.

### 2. Import (Discogs → local)

**Mapping**

- Extend `DiscogsAPIService::mapCollectionOrWantItem` to copy instance-level fields from the raw item (siblings of `basic_information`), not only `basic_information`.
- Collection drafts include `media_condition`, `sleeve_condition`, `notes` when present.
- Wantlist drafts include `notes` only; media/sleeve omitted or forced empty and **not** applied to local media/sleeve on wantlist phase.

**Merge**

- New merge path (not `metadataFieldNames` fill-empty-only):
  - For each applicable field: if Discogs/draft value is **non-empty** (after trim + validation), overwrite local; if empty, leave local unchanged.
- Apply on insert (new row gets draft values) and update (existing row).
- Persist via existing `MusicCollection` add/update paths that already support these keys (`updateAlbumRaw` / personal fields as needed so updates are not dropped).

### 3. Export (local → Discogs)

**Behavior change**

- Export remains additive for membership (never delete Discogs items).
- After a release is **added** or already present (**skipped**), still run a **field update** for that release when instance/folder identity is known.
- Collection: `POST /users/{username}/collection/folders/{folder_id}/releases/{release_id}/instances/{instance_id}` with `media_condition`, `sleeve_condition`, `notes` from local (empty string clears Discogs).
- Wantlist: edit wantlist entry notes from local (empty clears); no media/sleeve.
- Progress counts: introduce or extend counters (e.g. `fields_updated` / include in existing progress) so “skipped add” is distinct from “fields pushed”.

**Instance identity**

- Collection page responses include `id` (instance id), `folder_id`, and release id under `basic_information.id`.
- At export start (or when building existing-ID sets), retain a map `release_id → { folder_id, instance_id }` (if multiple instances per release, use the first instance found in folder 0 / All listing, document that choice).
- If add returns a new instance payload, use that for the immediate field update.
- If instance id cannot be resolved, count as field-update error/skip sample; do not fail the whole batch.

### 4. UI and docs

- Add/Edit modal: remove “Local only — not synced with Discogs.” Replace with a short hint that import applies non-empty Discogs values and export pushes local values (including clears).
- Update `INSTALL.md` / `readme.md` condition sections and reverse the “never overwrite” language from the original album-condition-notes design (new spec supersedes that Discogs non-goal).

### 5. Auth / rate limits

- Same admin + CSRF gates as existing import/export.
- Field-update POSTs use existing Discogs rate limiter (`enforceRateLimit`).

### 6. Verification

1. Import a collection item with Discogs grades/notes → local matches; re-import with empty Discogs grades → local grades unchanged.
2. Export owned album with local grades → Discogs instance updated; clear local grades/notes, re-export → Discogs cleared.
3. Wantlist: notes sync both ways; media/sleeve never written from/to wantlist.
4. Invalid Discogs grade string does not land in local JSON.
5. Export of release already on Discogs still updates fields (not only newly added).

### 7. Error handling

| Case | Behavior |
|------|----------|
| Invalid Discogs grade on import | Treat as empty for that field |
| Missing instance id on export field update | Skip field update; sample error; continue batch |
| Discogs 4xx/5xx on field update | Count error; continue batch |
| Wantlist phase media/sleeve | Ignored |

---

## Supersedes

- `docs/superpowers/specs/2026-09-19-album-condition-notes-design.md` — Discogs sync **non-goal** and “local-only” decision are superseded for these three fields.
- `docs/superpowers/specs/2026-09-19-discogs-export-design.md` — “Pushing metadata edits (notes, condition…)” non-goal is superseded for condition/notes only.
