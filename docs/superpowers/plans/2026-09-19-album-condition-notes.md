# Album Condition & Notes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add local-only `media_condition`, `sleeve_condition`, and `notes` on each album, editable in the Add/Edit modal, with a compact Condition column in the collection table.

**Architecture:** Small PHP helper validates Discogs grade strings and notes length. Persist via `addAlbum` / `handleInsert` on create, and via `updateAlbumRaw` (allowed fields) after the existing `updateAlbum` call on edit so Discogs import’s fixed `updateAlbum` path never clears personal fields. UI: modal selects + textarea; table short labels.

**Tech Stack:** PHP 7.4+ JSON catalog (`config/database.php`), `MusicCollection`, `music_api.php`, vanilla JS `app.js` + `npm run build:js`, `index.php` markup.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-19-album-condition-notes-design.md`
- Fields: `media_condition`, `sleeve_condition`, `notes` only (no price/location)
- Grade allow-list: exact Discogs strings from the spec (or empty)
- Notes max 2000 chars; reject if longer
- Local-only: Discogs import/export must not write these keys
- Do not put personal fields on fixed `updateAlbum()` SET list (would wipe on import)
- No `tests/` directory; verify with `php -r` and manual/UI checks
- Do not commit unless the user asks; do not commit `*.json` catalog data
- PHP/JS: 2-space indent in touched code
- Work on current branch

## File map

| File | Responsibility |
|------|----------------|
| `services/AlbumPersonalFields.php` | Allow-list, normalize/validate, short labels |
| `config/database.php` | `handleInsert` optional personal fields |
| `models/MusicCollection.php` | `addAlbum` optional args; `updateAlbumRaw` allowed fields |
| `api/music_api.php` | Validate on add/update; apply personal fields on write |
| `index.php` | Modal fields + table Condition header |
| `assets/js/app.js` (+ min) | save/load modal; Condition column |
| `INSTALL.md` / `readme.md` | Brief mention |

---

### Task 1: AlbumPersonalFields helper + persistence

**Files:**
- Create: `services/AlbumPersonalFields.php`
- Modify: `config/database.php` (`handleInsert`)
- Modify: `models/MusicCollection.php` (`addAlbum`, `updateAlbumRaw`)

**Interfaces:**
- Produces:
  - `AlbumPersonalFields::ALLOWED_GRADES` (array of exact strings)
  - `AlbumPersonalFields::NOTES_MAX_LENGTH` = 2000
  - `AlbumPersonalFields::normalizeFromInput(array $input): array` → `['ok'=>bool,'error'=>?string,'media_condition'=>string,'sleeve_condition'=>string,'notes'=>string]`
  - `AlbumPersonalFields::shortLabel(string $grade): string` → e.g. `NM`, `VG+`, or `''`
  - `AlbumPersonalFields::formatTableCell(?string $media, ?string $sleeve): string` → `NM / VG+` or `''`

- [ ] **Step 1: Create helper**

```php
<?php
/**
 * Local album media/sleeve condition and notes (not synced with Discogs).
 */
class AlbumPersonalFields {
  const NOTES_MAX_LENGTH = 2000;

  public static $ALLOWED_GRADES = [
    'Mint (M)',
    'Near Mint (NM or M-)',
    'Very Good Plus (VG+)',
    'Very Good (VG)',
    'Good Plus (G+)',
    'Good (G)',
    'Fair (F)',
    'Poor (P)',
  ];

  /**
   * @param array $input
   * @return array{ok:bool,error:?string,media_condition:string,sleeve_condition:string,notes:string}
   */
  public static function normalizeFromInput($input) {
    $media = isset($input['media_condition']) ? trim((string) $input['media_condition']) : '';
    $sleeve = isset($input['sleeve_condition']) ? trim((string) $input['sleeve_condition']) : '';
    $notes = isset($input['notes']) ? trim((string) $input['notes']) : '';

    if ($media !== '' && !in_array($media, self::$ALLOWED_GRADES, true)) {
      return ['ok' => false, 'error' => 'Invalid media condition', 'media_condition' => '', 'sleeve_condition' => '', 'notes' => ''];
    }
    if ($sleeve !== '' && !in_array($sleeve, self::$ALLOWED_GRADES, true)) {
      return ['ok' => false, 'error' => 'Invalid sleeve condition', 'media_condition' => '', 'sleeve_condition' => '', 'notes' => ''];
    }
    if (strlen($notes) > self::NOTES_MAX_LENGTH) {
      return ['ok' => false, 'error' => 'Notes must be at most ' . self::NOTES_MAX_LENGTH . ' characters', 'media_condition' => '', 'sleeve_condition' => '', 'notes' => ''];
    }

    return [
      'ok' => true,
      'error' => null,
      'media_condition' => $media,
      'sleeve_condition' => $sleeve,
      'notes' => $notes,
    ];
  }

  public static function shortLabel($grade) {
    $grade = trim((string) $grade);
    $map = [
      'Mint (M)' => 'M',
      'Near Mint (NM or M-)' => 'NM',
      'Very Good Plus (VG+)' => 'VG+',
      'Very Good (VG)' => 'VG',
      'Good Plus (G+)' => 'G+',
      'Good (G)' => 'G',
      'Fair (F)' => 'F',
      'Poor (P)' => 'P',
    ];
    return isset($map[$grade]) ? $map[$grade] : '';
  }

  public static function formatTableCell($media, $sleeve) {
    $m = self::shortLabel($media);
    $s = self::shortLabel($sleeve);
    if ($m === '' && $s === '') {
      return '';
    }
    if ($m === '') {
      return '— / ' . $s;
    }
    if ($s === '') {
      return $m . ' / —';
    }
    return $m . ' / ' . $s;
  }
}
```

- [ ] **Step 2: Extend `addAlbum` + `handleInsert`**

Append optional `$mediaCondition = ''`, `$sleeveCondition = ''`, `$notes = ''` to `MusicCollection::addAlbum` and INSERT column list / params.

In `SimpleDB::handleInsert`, set:
```php
'media_condition' => $params[13] ?? '',
'sleeve_condition' => $params[14] ?? '',
'notes' => $params[15] ?? '',
```

- [ ] **Step 3: Extend `updateAlbumRaw` allowedFields**

Add `'media_condition', 'sleeve_condition', 'notes'` to `$allowedFields`. Do **not** change fixed `updateAlbum()` SQL.

- [ ] **Step 4: Verify with php -r**

```bash
php -r 'require "services/AlbumPersonalFields.php"; $r=AlbumPersonalFields::normalizeFromInput(["media_condition"=>"Near Mint (NM or M-)","sleeve_condition"=>"Very Good Plus (VG+)","notes"=>"ok"]); echo $r["ok"]?"OK":"FAIL"; echo " ".AlbumPersonalFields::formatTableCell($r["media_condition"],$r["sleeve_condition"])."\n"; $b=AlbumPersonalFields::normalizeFromInput(["media_condition"=>"Minty"]); echo $b["ok"]?"BAD":"reject-ok\n";'
```

Expected: `OK NM / VG+` then `reject-ok`

- [ ] **Step 5: Commit** only if user asked

---

### Task 2: music_api add/update wiring

**Files:**
- Modify: `api/music_api.php`

**Interfaces:**
- Consumes: `AlbumPersonalFields::normalizeFromInput`
- Produces: add/update persist personal fields; invalid → success false + message (HTTP 400 when practical)

- [ ] **Step 1: Require helper** near other service requires

- [ ] **Step 2: Helper apply function**

```php
function applyAlbumPersonalFields($musicCollection, $albumId, $personal) {
  return $musicCollection->updateAlbumRaw([
    'id' => $albumId,
    'artist_name' => '', // unused when only personal keys set — WRONG
  ]);
}
```

Do **not** use that stub. Correct approach:

After a successful `addAlbum` / `addAlbumToCollection`, load the new album id (or pass personal fields into `addAlbum` args). Prefer **passing into `addAlbum`** on create.

On **update** (and replace_existing update): after successful `updateAlbum(...)`, call:

```php
$personal = AlbumPersonalFields::normalizeFromInput($input);
if (!$personal['ok']) {
  $response['success'] = false;
  $response['message'] = $personal['error'];
  break;
}
$musicCollection->updateAlbumRaw([
  'id' => $input['id'], // or existing id
  'artist_name' => $input['artist_name'], // required by updateAlbumRaw duplicate check
  'album_name' => $input['album_name'],
  'media_condition' => $personal['media_condition'],
  'sleeve_condition' => $personal['sleeve_condition'],
  'notes' => $personal['notes'],
]);
```

`updateAlbumRaw` requires `artist_name`/`album_name` for duplicate check — always pass them from `$input` / `$existingAlbum`.

On **add** paths: validate personal fields first; pass the three strings into `addAlbum` / `addAlbumToCollection` (extend the wrapper in `music_api.php` similarly).

Validate **before** mutating when personal keys are present OR always normalize (empty defaults) on every add/update so clears work.

- [ ] **Step 3: Confirm Discogs import unchanged** — still calls `updateAlbum` without personal keys; existing keys remain on the album object (legacy handleUpdate does not unset unknown keys). Spot-check `DiscogsImportService.php` does not reference the new fields.

- [ ] **Step 4: php -r smoke** (optional load classes) + note unauth still 401

---

### Task 3: Setup UI — modal + table

**Files:**
- Modify: `index.php` (form + `<th>Condition</th>`)
- Modify: `assets/js/app.js`
- Run: `npm run build:js`

- [ ] **Step 1: Modal markup** after Album Status block, before `#modalMessage`:

Condition & notes: two `<select>`s (`mediaCondition`, `sleeveCondition`) with empty option + full grade strings; `<textarea id="albumNotes" name="albumNotes" maxlength="2000">`.

- [ ] **Step 2: Table header** — add `<th class="column-condition">Condition</th>` before Own (or after Year). Update empty-state `colspan` in `renderAlbums` (was 6 → 7).

- [ ] **Step 3: `showModal`** — set select/textarea from album or clear; grade options already in HTML.

- [ ] **Step 4: `saveAlbum`** — include `media_condition`, `sleeve_condition`, `notes` from form fields.

- [ ] **Step 5: `renderAlbums`** — new `<td class="condition-cell">` with short label formatter (mirror PHP map in JS helper `formatConditionCell(album)`). Optional `title` attribute with notes if non-empty.

- [ ] **Step 6: `npm run build:js`**

- [ ] **Step 7: Browser or curl** — unauthenticated update still fails auth; with login, edit one album and confirm JSON keys (manual OK).

---

### Task 4: Docs

**Files:**
- Modify: `INSTALL.md`, `readme.md` (short: personal media/sleeve/notes; local-only; not Discogs)

- [ ] Document under Setup/collection editing
- [ ] Commit only if user asked

## Spec coverage

| Spec item | Task |
|-----------|------|
| Data model + allow-list | 1 |
| Persistence without wiping on Discogs update | 1, 2 |
| API validate + 400/message | 2 |
| Modal UI | 3 |
| Table Condition column | 3 |
| Local-only Discogs | 2 (no import changes) |
| Docs | 4 |

## Placeholder scan

None intentional. `applyAlbumPersonalFields` stub in Task 2 is explicitly marked WRONG — implementers must use `updateAlbumRaw` with artist/album names as described.
