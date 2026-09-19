# Discogs Collection + Wantlist Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an authenticated admin import Discogs Collection (owned) and Wantlist into the local JSON catalog via a paged one-shot UI, merging on re-run and remembering the Discogs username.

**Architecture:** `DiscogsAPIService` fetches paginated collection/wantlist pages; new `DiscogsImportService` maps items, matches by release id then artist+album, and merges into `MusicCollection`. `music_api.php` exposes start/page/cancel + settings under `AuthHelper::requireAdminAction()`. Setup gains a “Discogs Import” tab; JS runs pages serially with progress UI.

**Tech Stack:** PHP 7.4+ JSON DB, existing Discogs token auth, vanilla JS on setup.php / app.js, CSRF session tokens from security hardening.

## Global Constraints

- Follow approved spec: `docs/superpowers/specs/2026-09-19-discogs-import-design.md`.
- Collection first, then Wantlist; Collection wins over Wantlist for the same release; owned beats want on wantlist updates.
- Do not delete local albums missing from Discogs.
- Do not wipe non-empty local metadata fields on update.
- All mutating import endpoints: `AuthHelper::requireAdminAction()`.
- PHP 7.4+ compatible; match existing 2-space / project style in touched files.
- Do not commit unless the user explicitly asks.
- Work on branch `security/hardening-20260919` (or current feature branch); do not touch unrelated `data/music_collection.json` diffs unless importing for a real test with user approval.
- Never commit Discogs secrets; username is non-secret and may live in `settings.json`.

## File map

| File | Responsibility |
|------|----------------|
| `models/MusicCollection.php` | `getAlbumByDiscogsReleaseId($id)` |
| `services/DiscogsAPIService.php` | `getCollectionPage`, `getWantlistPage`, map `basic_information` |
| `services/DiscogsImportService.php` | Match/merge one page; counts |
| `api/music_api.php` | Import + settings API actions |
| `api/theme_api.php` | Allow `discogs_username` in app settings load/save |
| `setup.php` | Discogs Import tab UI |
| `assets/js/app.js` (+ min) | Paged import client + tab wiring |
| `tests/discogs_import_merge_test.php` | CLI tests for merge rules (no live Discogs) |
| `INSTALL.md` / `readme.md` | Document import |

---

### Task 1: Model — find by Discogs release ID

**Files:**
- Modify: `models/MusicCollection.php`
- Test: `tests/discogs_import_merge_test.php` (stub then expand in Task 3)

**Interfaces:**
- Produces: `MusicCollection::getAlbumByDiscogsReleaseId($releaseId): ?array`

- [ ] **Step 1: Add method**

```php
/**
 * Find album by Discogs release id.
 *
 * @param int|string $releaseId
 * @return array|null
 */
public function getAlbumByDiscogsReleaseId($releaseId) {
    if ($releaseId === null || $releaseId === '') {
        return null;
    }
    $sql = "SELECT * FROM music_collection WHERE discogs_release_id = ?";
    $result = $this->executeQuery($sql, [$releaseId]);
    return !empty($result) ? $result[0] : null;
}
```

(If SimpleDB compares loosely, cast id consistently as used elsewhere for `discogs_release_id`.)

- [ ] **Step 2: Smoke**

```bash
php -r "require 'models/MusicCollection.php'; \$m=new MusicCollection(); var_export(\$m->getAlbumByDiscogsReleaseId(417282)['album_name']??null);"
```

Expected: `Strength` (or null if that id absent in local data — then use any id from `data/music_collection.json`).

- [ ] **Step 3: Commit** (only if user asked)

---

### Task 2: DiscogsAPIService — collection / wantlist pages

**Files:**
- Modify: `services/DiscogsAPIService.php`

**Interfaces:**
- Produces:
  - `getCollectionPage($username, $page = 1, $perPage = 50): array` → `['releases' => [...normalized], 'pagination' => ['page'=>,'pages'=>,'items'=>]]`
  - `getWantlistPage($username, $page = 1, $perPage = 50): array` → same shape with `releases` list
  - `mapCollectionOrWantItem($item): array` normalized album draft

Normalized draft fields:

```php
[
  'artist_name' => string,
  'album_name' => string,
  'release_year' => int|null,
  'cover_url' => string|null,
  'cover_images' => [], // empty in v1 unless thumb available as single URL list
  'discogs_release_id' => int,
  'format' => string|null,
  'label' => string|null,
  'style' => string|null, // often absent on basic_information
  'producer' => null,
  'artist_type' => null,
]
```

- [ ] **Step 1: Implement fetchers**

```php
public function getCollectionPage($username, $page = 1, $perPage = 50) {
    if (!$this->isAvailable()) {
        throw new Exception('Discogs API is not available');
    }
    $username = rawurlencode(trim($username));
    $url = $this->baseUrl . "/users/{$username}/collection/folders/0/releases";
    $params = [
        'page' => (int)$page,
        'per_page' => (int)$perPage,
        'token' => $this->apiKey,
    ];
    $response = $this->makeRequest($url, $params);
    // Map $response['releases'] via mapBasicInformationItem
    // Return pagination from $response['pagination']
}

public function getWantlistPage($username, $page = 1, $perPage = 50) {
    // GET /users/{username}/wants — items in $response['wants']
}
```

Mapping notes:

- Artist: first of `basic_information.artists[].name`, strip trailing ` (n)` Discogs disambiguator if present (reuse clean helpers if any).
- Title: `basic_information.title`.
- Year: `basic_information.year`.
- Cover: `basic_information.cover_image` or `thumb` → `cover_url`.
- Format: join format names from `basic_information.formats` similar to `extractFormatDetails` if feasible; else first format name.
- Label: first of `basic_information.labels[].name`.
- Release id: `basic_information.id` (or item `id` for wants — use basic_information.id).

- [ ] **Step 2: Live smoke (Lando)**

```bash
lando php -r '
require "services/DiscogsAPIService.php";
\$d=new DiscogsAPIService();
\$r=\$d->getCollectionPage("YOUR_DISCOGS_USERNAME", 1, 5);
echo "pages=".$r["pagination"]["pages"]." n=".count(\$r["releases"])."\n";
print_r(\$r["releases"][0]??[]);
'
```

Replace username with a real one (or skip if user has none — then unit-test mapper with fixture JSON only).

- [ ] **Step 3: Commit** (only if user asked)

---

### Task 3: DiscogsImportService — merge rules + CLI tests

**Files:**
- Create: `services/DiscogsImportService.php`
- Create: `tests/discogs_import_merge_test.php`

**Interfaces:**
- Consumes: `MusicCollection`, `DiscogsAPIService` mappers
- Produces:
  - `processMappedItem(array $draft, string $phase): string` → `'added'|'updated'|'skipped'`
  - `processPage(string $phase, array $releases): array` counts for the page
  - Flag rules: phase `collection` → owned=1 want=0; phase `wantlist` → if existing owned, skip flag change (count skipped or updated only for metadata); else want=1 owned=0

- [ ] **Step 1: Write failing CLI tests** for pure merge decisions using a temp JSON DB or mocked MusicCollection if easier.

Minimal approach without full DB mock: test a package-private-style pure function `DiscogsImportService::resolveFlags($phase, $existing): array` and `shouldUpdateMetadata($existing, $draft): array` of fields to set.

```php
// tests/discogs_import_merge_test.php
require_once __DIR__ . '/../services/DiscogsImportService.php';
$failures = 0;
function assert_eq($a, $b, $msg) {
    global $failures;
    if ($a !== $b) { echo "FAIL $msg\n"; $failures++; } else { echo "PASS $msg\n"; }
}
$f = DiscogsImportService::resolveFlags('collection', null);
assert_eq($f['is_owned'], 1, 'collection new is owned');
assert_eq($f['want_to_own'], 0, 'collection new not wanted');
$f = DiscogsImportService::resolveFlags('wantlist', ['is_owned' => 1, 'want_to_own' => 0]);
assert_eq($f['is_owned'], 1, 'wantlist does not clear owned');
assert_eq($f['want_to_own'], 0, 'wantlist leaves want 0 when owned');
$f = DiscogsImportService::resolveFlags('wantlist', null);
assert_eq($f['is_owned'], 0, 'wantlist new not owned');
assert_eq($f['want_to_own'], 1, 'wantlist new wanted');
exit($failures ? 1 : 0);
```

- [ ] **Step 2: Implement `DiscogsImportService`**

```php
class DiscogsImportService {
    private $collection;
    public function __construct(MusicCollection $collection = null) {
        $this->collection = $collection ?: new MusicCollection();
    }
    public static function resolveFlags($phase, $existing) { /* per spec */ }
    public function findExisting($draft) {
        if (!empty($draft['discogs_release_id'])) {
            $hit = $this->collection->getAlbumByDiscogsReleaseId($draft['discogs_release_id']);
            if ($hit) return $hit;
        }
        return $this->collection->getAlbumByArtistAndName($draft['artist_name'], $draft['album_name']);
    }
    public function processMappedItem(array $draft, $phase) {
        // add or updateAlbum with merge rules; return status string
    }
    public function processPage($phase, array $releases) {
        $counts = ['added'=>0,'updated'=>0,'skipped'=>0,'errors'=>0];
        $errors_sample = [];
        foreach ($releases as $draft) {
            try {
                $status = $this->processMappedItem($draft, $phase);
                $counts[$status]++;
            } catch (Exception $e) {
                $counts['errors']++;
                if (count($errors_sample) < 5) {
                    $errors_sample[] = $e->getMessage();
                }
            }
        }
        return ['counts' => $counts, 'errors_sample' => $errors_sample];
    }
}
```

Metadata fill: only set empty local fields from draft.

- [ ] **Step 3: Run tests**

```bash
php tests/discogs_import_merge_test.php
```

Expected: exit 0.

- [ ] **Step 4: Commit** (only if user asked)

---

### Task 4: API — settings + paged import actions

**Files:**
- Modify: `api/music_api.php`
- Modify: `api/theme_api.php` (app settings `discogs_username`)

**Interfaces:**
- Consumes: `DiscogsImportService`, `DiscogsAPIService`, `AuthHelper::requireAdminAction`
- Produces: actions listed in spec

- [ ] **Step 1: Persist username in app settings**

In `theme_api.php` `loadAppSettings` / `saveAppSettings` / defaults:

- Add optional `discogs_username` string (max 100 chars, alphanumeric/`_-` Discogs-safe). Validate on save.

- [ ] **Step 2: music_api actions**

GET `get_discogs_import_settings`:

```php
[
  'discogs_username' => from app settings,
  'api_key_set' => DiscogsAPIService available,
]
```

POST `save_discogs_import_settings`: `requireAdminAction`; save username via same settings writer theme uses (or shared helper writing `data/settings.json` app section).

POST `import_discogs_start`:

- `requireAdminAction`
- Body: `{ username, save_username?: bool }`
- Validate API available + username non-empty
- Optionally save username
- Init `$_SESSION['discogs_import'] = ['counts'=>zeros, 'username'=>..., 'started'=>time()]`
- Return `{ phase:'collection', page:1, ... hint }`

POST `import_discogs_page`:

- `requireAdminAction`
- Body: `{ phase, page, per_page? }`
- Fetch page from DiscogsAPIService
- `DiscogsImportService::processPage`
- Merge counts into session
- Compute `next` / `done` from pagination (`page < pages` → next page same phase; else if collection → wantlist page 1; else done and unset session)

POST `import_discogs_cancel`: clear session key.

- [ ] **Step 3: Curl smoke**

```bash
# After login with CSRF (same jar pattern as security tests):
curl -sk -b /tmp/mcjar -c /tmp/mcjar -X POST 'https://music.lndo.site/api/music_api.php?action=import_discogs_start' \
  -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"username":"YOUR_USER","save_username":true}'
```

- [ ] **Step 4: Commit** (only if user asked)

---

### Task 5: Setup UI + frontend paged loop

**Files:**
- Modify: `setup.php` — new tab `discogs-import` after API Config
- Modify: `assets/js/app.js` — load/save settings, run import loop
- Rebuild: `npm run build:js`

**Interfaces:**
- Consumes: import API actions + `apiFetch` / CSRF from security work

- [ ] **Step 1: setup.php markup**

Tab button + panel:

- Username input `#discogsImportUsername`
- Checkbox `#discogsImportSaveUsername` (checked)
- Button `#discogsImportStartBtn` “Import from Discogs”
- Progress region `#discogsImportProgress` (hidden until run): phase, page, counts
- Note: “Keep this tab open. Large collections may take several minutes. Re-import merges; does not delete local albums.”

Wire tab switching the same way other setup tabs work.

- [ ] **Step 2: JS**

On setup init / tab show: GET `get_discogs_import_settings`, fill username.

Start click → confirm → POST start → loop:

```javascript
async runDiscogsImport() {
  let next = { phase: 'collection', page: 1 };
  while (next) {
    const res = await this.apiFetch('api/music_api.php?action=import_discogs_page', {
      method: 'POST',
      body: JSON.stringify(next)
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    this.renderDiscogsImportProgress(data.data);
    if (data.data.done) break;
    next = data.data.next;
  }
  // show summary; optionally reload albums if present
}
```

Disable start button while running; re-enable on done/error.

- [ ] **Step 3: Rebuild min JS**

```bash
npm run build:js
```

- [ ] **Step 4: Browser smoke on setup.php** — start import for a small Discogs user or first page only; confirm progress updates and no console errors.

- [ ] **Step 5: Commit** (only if user asked)

---

### Task 6: Docs + verification checklist

**Files:**
- Modify: `INSTALL.md`, `readme.md`

- [ ] **Step 1: Document** Setup → Discogs Import; requires API key + username; merge behavior; keep tab open.

- [ ] **Step 2: Checklist**

1. CLI merge tests pass  
2. Unauthenticated import_start → 401  
3. Import without CSRF → 403  
4. One collection page adds/updates rows  
5. Re-run same page → mostly skipped  
6. Wantlist does not demote owned  
7. Username persists in settings  

- [ ] **Step 3: Commit** (only if user asked)

---

## Spec coverage self-check

| Spec item | Task |
|-----------|------|
| Collection + wantlist | 2, 4, 5 |
| Merge / no dupes | 1, 3 |
| Remember username | 4, 5 |
| Paged AJAX | 4, 5 |
| requireAdminAction | 4 |
| Owned beats want | 3 |
| No delete missing | 3 (implicit) |
| Docs | 6 |

## Placeholder scan

No TBD steps; live Discogs username left as operator input in smoke steps.
