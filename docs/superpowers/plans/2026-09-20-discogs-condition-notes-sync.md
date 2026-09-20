# Discogs Condition & Notes Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sync `media_condition`, `sleeve_condition`, and `notes` with Discogs on import/export using the approved merge rules (non-empty Discogs overwrites on import; local always wins on export including clears).

**Architecture:** Extend `mapCollectionOrWantItem` and import merge for inbound fields; extend export to keep a release→instance map and POST instance/wantlist field updates after add-or-skip. Reuse `AlbumPersonalFields` allow-list. Prefer `updateAlbumRaw` when personal fields change on import so they are not dropped by `updateAlbum`.

**Tech Stack:** PHP services (`DiscogsAPIService`, `DiscogsImportService`, `DiscogsExportService`, `AlbumPersonalFields`), `api/music_api.php` export session, existing Setup import/export UI, CLI PHP smoke tests under `tests/`.

## Global Constraints

- Import: non-empty Discogs value overwrites local; empty Discogs leaves local alone.
- Export: local values always pushed (empty string clears Discogs).
- Wantlist: **notes only** (never media/sleeve).
- Invalid Discogs grades → treat as empty on import; export only allow-list or `""`.
- Do not sync rating; do not change fill-empty-only rules for other metadata.
- Do not stage/commit `data/*.json` catalog dumps.
- After `app.js` edits: `npm run build:js`.

## File map

| File | Role |
|------|------|
| `services/AlbumPersonalFields.php` | Sanitize Discogs grades; merge helper |
| `services/DiscogsAPIService.php` | Map instance fields; instance map; write field updates |
| `services/DiscogsImportService.php` | Apply personal-field merge; persist |
| `services/DiscogsExportService.php` | Field updates after add/skip; counts |
| `api/music_api.php` | Export session instance map + `fields_updated` |
| `index.php` | Modal hint copy |
| `readme.md`, `INSTALL.md` | Docs |
| `tests/discogs_condition_notes_sync_test.php` | CLI unit tests |

---

### Task 1: Personal-field helpers + CLI tests

**Files:**
- Modify: `services/AlbumPersonalFields.php`
- Create: `tests/discogs_condition_notes_sync_test.php`

**Interfaces:**
- Produces: `AlbumPersonalFields::sanitizeGradeFromDiscogs($value): string`
- Produces: `AlbumPersonalFields::sanitizeNotesFromDiscogs($value): string`
- Produces: `AlbumPersonalFields::mergeFromDiscogsDraft($existing, $draft, $phase): array`  
  Returns `{ media_condition, sleeve_condition, notes, changed: bool }` after import rules.

- [ ] **Step 1: Write failing CLI test**

Create `tests/discogs_condition_notes_sync_test.php`:

```php
<?php
require_once __DIR__ . '/../services/AlbumPersonalFields.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

assert_true(AlbumPersonalFields::sanitizeGradeFromDiscogs('Near Mint (NM or M-)') === 'Near Mint (NM or M-)', 'valid grade');
assert_true(AlbumPersonalFields::sanitizeGradeFromDiscogs('Bogus') === '', 'invalid grade empty');
assert_true(AlbumPersonalFields::sanitizeGradeFromDiscogs('  ') === '', 'blank empty');

$existing = [
    'media_condition' => 'Very Good (VG)',
    'sleeve_condition' => '',
    'notes' => 'local note',
];
$draft = [
    'media_condition' => 'Mint (M)',
    'sleeve_condition' => '',
    'notes' => '',
];
$merged = AlbumPersonalFields::mergeFromDiscogsDraft($existing, $draft, 'collection');
assert_true($merged['media_condition'] === 'Mint (M)', 'non-empty Discogs overwrites media');
assert_true($merged['sleeve_condition'] === '', 'empty Discogs sleeve leaves local empty');
assert_true($merged['notes'] === 'local note', 'empty Discogs notes leave local');
assert_true($merged['changed'] === true, 'changed when media updated');

$want = AlbumPersonalFields::mergeFromDiscogsDraft(
    ['media_condition' => 'Mint (M)', 'sleeve_condition' => 'Mint (M)', 'notes' => ''],
    ['media_condition' => 'Poor (P)', 'sleeve_condition' => 'Poor (P)', 'notes' => 'want note'],
    'wantlist'
);
assert_true($want['media_condition'] === 'Mint (M)', 'wantlist ignores Discogs media');
assert_true($want['sleeve_condition'] === 'Mint (M)', 'wantlist ignores Discogs sleeve');
assert_true($want['notes'] === 'want note', 'wantlist applies notes');

echo "discogs_condition_notes_sync_test: OK\n";
```

- [ ] **Step 2: Run — expect FAIL**

```bash
php tests/discogs_condition_notes_sync_test.php
```

Expected: fatal/undefined method.

- [ ] **Step 3: Implement helpers on `AlbumPersonalFields`**

Add (and update the file header comment — remove “not synced with Discogs”):

```php
  /**
   * Allow-list a Discogs grade string; invalid or blank → "".
   *
   * @param mixed $value
   * @return string
   */
  public static function sanitizeGradeFromDiscogs($value) {
    $grade = trim((string) $value);
    if ($grade === '' || !in_array($grade, self::$ALLOWED_GRADES, true)) {
      return '';
    }
    return $grade;
  }

  /**
   * Trim Discogs notes; over-length truncated to NOTES_MAX_LENGTH for import.
   *
   * @param mixed $value
   * @return string
   */
  public static function sanitizeNotesFromDiscogs($value) {
    $notes = trim((string) $value);
    if (strlen($notes) > self::NOTES_MAX_LENGTH) {
      $notes = substr($notes, 0, self::NOTES_MAX_LENGTH);
    }
    return $notes;
  }

  /**
   * Import merge: non-empty Discogs overwrites; empty leaves local.
   * Wantlist phase never applies media/sleeve from draft.
   *
   * @param array $existing Album row (or empty defaults)
   * @param array $draft Mapped draft
   * @param string $phase collection|wantlist
   * @return array{media_condition:string,sleeve_condition:string,notes:string,changed:bool}
   */
  public static function mergeFromDiscogsDraft($existing, $draft, $phase) {
    $media = isset($existing['media_condition']) ? trim((string) $existing['media_condition']) : '';
    $sleeve = isset($existing['sleeve_condition']) ? trim((string) $existing['sleeve_condition']) : '';
    $notes = isset($existing['notes']) ? trim((string) $existing['notes']) : '';

    $dMedia = self::sanitizeGradeFromDiscogs(isset($draft['media_condition']) ? $draft['media_condition'] : '');
    $dSleeve = self::sanitizeGradeFromDiscogs(isset($draft['sleeve_condition']) ? $draft['sleeve_condition'] : '');
    $dNotes = self::sanitizeNotesFromDiscogs(isset($draft['notes']) ? $draft['notes'] : '');

    if ($phase === 'collection') {
      if ($dMedia !== '') {
        $media = $dMedia;
      }
      if ($dSleeve !== '') {
        $sleeve = $dSleeve;
      }
    }
    if ($dNotes !== '') {
      $notes = $dNotes;
    }

    $origMedia = isset($existing['media_condition']) ? trim((string) $existing['media_condition']) : '';
    $origSleeve = isset($existing['sleeve_condition']) ? trim((string) $existing['sleeve_condition']) : '';
    $origNotes = isset($existing['notes']) ? trim((string) $existing['notes']) : '';
    $changed = ($media !== $origMedia) || ($sleeve !== $origSleeve) || ($notes !== $origNotes);

    return [
      'media_condition' => $media,
      'sleeve_condition' => $sleeve,
      'notes' => $notes,
      'changed' => $changed,
    ];
  }
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/discogs_condition_notes_sync_test.php
```

Expected: `discogs_condition_notes_sync_test: OK`

- [ ] **Step 5: Commit**

```bash
git add services/AlbumPersonalFields.php tests/discogs_condition_notes_sync_test.php
git commit -m "$(cat <<'EOF'
Add Discogs condition/notes merge helpers and CLI tests.

EOF
)"
```

---

### Task 2: Map instance fields + collection instance map

**Files:**
- Modify: `services/DiscogsAPIService.php` (`mapCollectionOrWantItem`, `collectReleaseIdSet` → richer collector, or new `collectCollectionInstanceMap`)

**Interfaces:**
- Produces drafts with `media_condition`, `sleeve_condition`, `notes` (and optional `discogs_instance_id`, `discogs_folder_id` for export maps)
- Produces: `collectCollectionInstanceMap($username): array<int, array{folder_id:int,instance_id:int}>` keyed by release id (first instance wins)

- [ ] **Step 1: Extend `mapCollectionOrWantItem`**

After `mapBasicInformationItem($basic)`, merge personal fields from `$item`:

```php
        $draft = $this->mapBasicInformationItem($basic);
        $draft['media_condition'] = isset($item['media_condition']) ? trim((string) $item['media_condition']) : '';
        $draft['sleeve_condition'] = isset($item['sleeve_condition']) ? trim((string) $item['sleeve_condition']) : '';
        $draft['notes'] = isset($item['notes']) ? trim((string) $item['notes']) : '';
        if (isset($item['id'])) {
            $draft['discogs_instance_id'] = (int) $item['id'];
        }
        if (isset($item['folder_id'])) {
            $draft['discogs_folder_id'] = (int) $item['folder_id'];
        }
        return $draft;
```

(Wantlist items may lack `folder_id` / media fields — that is fine.)

- [ ] **Step 2: Add `collectCollectionInstanceMap`**

Paginate `getCollectionPage` (or raw page loop). For each mapped release with `discogs_release_id`, `discogs_instance_id`, and `discogs_folder_id`: if release id not yet in map, store `{ folder_id, instance_id }`. First instance wins.

Also add `collectWantlistReleaseIdSet` stay as today OR keep `collectReleaseIdSet` for membership and call the new map separately at export start.

- [ ] **Step 3: Smoke**

```bash
php -r 'require "services/DiscogsAPIService.php"; $m=["id"=>9,"folder_id"=>1,"notes"=>"n","media_condition"=>"Mint (M)","sleeve_condition"=>"","basic_information"=>["id"=>1,"title"=>"T","artists"=>[["name"=>"A"]]]]; $s=new DiscogsAPIService(); $d=$s->mapCollectionOrWantItem($m); echo $d["notes"]."|".$d["media_condition"]."|".$d["discogs_instance_id"]."\n";'
```

Expected: `n|Mint (M)|9` (adjust constructor if API key required — use reflection or ensure empty key still allows pure map).

If constructor requires config, call `mapCollectionOrWantItem` via a minimal bootstrap that already works in other scripts.

- [ ] **Step 4: Commit**

```bash
git add services/DiscogsAPIService.php
git commit -m "$(cat <<'EOF'
Map Discogs instance condition/notes and collect instance ids.

EOF
)"
```

---

### Task 3: Import merge + persist personal fields

**Files:**
- Modify: `services/DiscogsImportService.php`
- Require: `services/AlbumPersonalFields.php` at top of import service (or via music_api bootstrap — ensure loaded)

**Interfaces:**
- Consumes: `AlbumPersonalFields::mergeFromDiscogsDraft`
- `processMappedItem` returns `added|updated|skipped` including personal-field-only updates

- [ ] **Step 1: On insert (`existing === null`)**

After computing `$flags`, merge personal fields from draft against empty existing:

```php
            $personal = AlbumPersonalFields::mergeFromDiscogsDraft([], $draft, $phase);
            $this->collection->addAlbum(
                // ...existing args...
                isset($draft['producer']) ? $draft['producer'] : null,
                false, // skipDuplicateCheck if that arg exists — match current signature
                $personal['media_condition'],
                $personal['sleeve_condition'],
                $personal['notes']
            );
```

Verify current `addAlbum` signature argument order in `MusicCollection.php` and match it exactly (including `$skipDuplicateCheck`).

- [ ] **Step 2: On update**

```php
        $personal = AlbumPersonalFields::mergeFromDiscogsDraft($existing, $draft, $phase);
        $metadataUpdates = self::shouldUpdateMetadata($existing, $draft);
        // flagsChanged as today
        if (!$flagsChanged && count($metadataUpdates) === 0 && !$personal['changed']) {
            return 'skipped';
        }

        $merged = $this->buildMergedAlbumRow($existing, $draft, $flags, $metadataUpdates);
        $merged['media_condition'] = $personal['media_condition'];
        $merged['sleeve_condition'] = $personal['sleeve_condition'];
        $merged['notes'] = $personal['notes'];

        $this->collection->updateAlbumRaw($merged);
        return 'updated';
```

Remove the old `updateAlbum(...)` call for this path so personal fields persist.

- [ ] **Step 3: Extend CLI test** (optional assert document in test file comments) — run existing Task 1 tests still pass:

```bash
php tests/discogs_condition_notes_sync_test.php
```

- [ ] **Step 4: Commit**

```bash
git add services/DiscogsImportService.php
git commit -m "$(cat <<'EOF'
Import Discogs condition and notes with non-empty overwrite rules.

EOF
)"
```

---

### Task 4: Discogs write helpers for instance / wantlist fields

**Files:**
- Modify: `services/DiscogsAPIService.php` (`makeWriteRequest` body support + public update methods)

**Interfaces:**
- Produces: `updateCollectionInstanceFields($username, $folderId, $releaseId, $instanceId, $fields): array{status,message,http_code}`
- Produces: `updateWantlistNotes($username, $releaseId, $notes): array{status,message,http_code}`
- `makeWriteRequest` accepts optional JSON body string/array (default `{}`)

- [ ] **Step 1: Extend `makeWriteRequest`**

Add optional `$body = null` parameter. If `$body` is an array, `json_encode` it for `CURLOPT_POSTFIELDS`; if null, keep `'{}'`.

- [ ] **Step 2: Implement collection instance update**

```php
    public function updateCollectionInstanceFields($username, $folderId, $releaseId, $instanceId, array $fields) {
        // validate ids; build URL:
        // /users/{u}/collection/folders/{folder}/releases/{release}/instances/{instance}
        // POST with token query param and JSON body:
        // media_condition, sleeve_condition, notes (sanitize grades via AlbumPersonalFields)
        // Map 200/201 → status updated; 4xx skip/error similar to addReleaseWrite
    }
```

Require `AlbumPersonalFields.php` if not already loaded.

Sanitize before send:

```php
$body = [
    'media_condition' => AlbumPersonalFields::sanitizeGradeFromDiscogs(isset($fields['media_condition']) ? $fields['media_condition'] : ''),
    'sleeve_condition' => AlbumPersonalFields::sanitizeGradeFromDiscogs(isset($fields['sleeve_condition']) ? $fields['sleeve_condition'] : ''),
    'notes' => isset($fields['notes']) ? trim((string) $fields['notes']) : '',
];
```

Empty strings are intentional (clear Discogs).

- [ ] **Step 3: Implement wantlist notes update**

```php
    public function updateWantlistNotes($username, $releaseId, $notes) {
        // POST /users/{u}/wants/{release_id} with {"notes": "..."}
    }
```

- [ ] **Step 4: Commit**

```bash
git add services/DiscogsAPIService.php
git commit -m "$(cat <<'EOF'
Add Discogs API helpers to update collection instance and wantlist notes.

EOF
)"
```

---

### Task 5: Export batch field updates + session map

**Files:**
- Modify: `services/DiscogsExportService.php`
- Modify: `api/music_api.php` (`discogsExportEmptyCounts`, export start/page)

**Interfaces:**
- `processBatch` accepts `$instanceMap` (collection) keyed by release id
- Counts include `fields_updated` and field errors roll into `errors` or separate `field_errors` (prefer `fields_updated` + keep `errors` for hard failures)

- [ ] **Step 1: Extend empty counts**

```php
function discogsExportEmptyCounts() {
    return [
        'added' => 0,
        'skipped' => 0,
        'missing_id' => 0,
        'errors' => 0,
        'fields_updated' => 0,
    ];
}
```

Update any JS progress UI that lists count keys if it hard-codes the old set (search `export` progress in `assets/js/app.js` / setup).

- [ ] **Step 2: Export start stores instance map**

In `export_discogs_start`, after collecting release id sets:

```php
$instanceMap = $discogsAPI->collectCollectionInstanceMap($username);
// store in $_SESSION['discogs_export']['instances'] = $instanceMap;
```

Keep `existing` membership sets as today.

- [ ] **Step 3: Rewrite `DiscogsExportService::processBatch`**

For each album:

1. Resolve `$rid` as today.
2. Membership: if not in `$existingIds`, call add; handle added/skipped/error as today; if added and response includes instance id, merge into `$instanceMap`.
3. **Always attempt field update** when possible:
   - **collection:** look up `$instanceMap[$rid]`; if missing, increment error sample “missing instance”; else call `updateCollectionInstanceFields` with local media/sleeve/notes; on success `fields_updated++`.
   - **wantlist:** call `updateWantlistNotes` with local notes; on success `fields_updated++`.
4. Return updated `$instanceMap` / `$existingIds` as needed.

If `addReleaseWrite` decoded body contains `instance_id` / `id`, use it (inspect real Discogs add response during implementation; fall back to map).

- [ ] **Step 4: Wire `export_discogs_page`** to pass session instance map and save it back.

- [ ] **Step 5: Commit**

```bash
git add services/DiscogsExportService.php api/music_api.php assets/js/app.js assets/js/app.min.js
# (only include JS if progress UI changed)
git commit -m "$(cat <<'EOF'
Push local condition and notes on Discogs export field updates.

EOF
)"
```

---

### Task 6: UI copy + docs

**Files:**
- Modify: `index.php` (condition-notes hint)
- Modify: `readme.md`, `INSTALL.md`

- [ ] **Step 1: Modal hint**

Replace:

```html
<p class="condition-notes-hint">Local only — not synced with Discogs.</p>
```

with:

```html
<p class="condition-notes-hint">Synced on Discogs import (non-empty Discogs values) and export (local values, including clears). Wantlist syncs notes only.</p>
```

- [ ] **Step 2: Docs**

Update album condition sections in `readme.md` / `INSTALL.md` to describe the sync rules; remove “local only / never overwrite” language.

- [ ] **Step 3: Commit**

```bash
git add index.php readme.md INSTALL.md
git commit -m "$(cat <<'EOF'
Document Discogs sync for album condition and notes.

EOF
)"
```

Also commit the plan if untracked:

```bash
git add docs/superpowers/plans/2026-09-20-discogs-condition-notes-sync.md
git commit -m "$(cat <<'EOF'
Add implementation plan for Discogs condition and notes sync.

EOF
)"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Map instance fields on import | Task 2 |
| Import non-empty overwrite / empty leave | Task 1 + 3 |
| Wantlist notes only | Task 1 + 3 + 5 |
| Invalid grade → empty | Task 1 |
| Export push including clears | Task 4 + 5 |
| Update already-on-Discogs instances | Task 5 |
| Instance map first-wins | Task 2 + 5 |
| UI/docs | Task 6 |
| Rate limit / admin CSRF | Existing export/import paths unchanged |
| No rating sync | Global Constraints |
