# Discogs Collection + Wantlist Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an authenticated admin push local owned albums to Discogs Collection (folder 0) and wanted-only albums to Wantlist via a paged one-shot Setup tab, skipping existing Discogs items and albums without a release ID, without changing import.

**Architecture:** At export start, build release-ID sets from Discogs collection/wantlist pages and local work queues from `MusicCollection::getAllAlbums()`. `DiscogsExportService` processes bounded batches (add or skip). `DiscogsAPIService` gains POST/PUT helpers for collection/wantlist writes. Distinct `$_SESSION['discogs_export']` from import. Setup **Discogs Export** tab mirrors import UX.

**Tech Stack:** PHP JSON SimpleDB, Discogs personal access token auth, vanilla JS on setup.php / app.js, CSRF via `AuthHelper::requireAdminAction()`.

## Global Constraints

- Follow approved spec: `docs/superpowers/specs/2026-09-19-discogs-export-design.md`.
- Do **not** change Discogs import behavior, session key `discogs_import`, or import UI beyond inserting the new Export tab in nav.
- Never delete or alter existing Discogs items; add/skip only.
- Skip albums with no `discogs_release_id` → count `missing_id`.
- Owned → Collection folder `0` only; want-not-owned → Wantlist only.
- All mutating export endpoints: `AuthHelper::requireAdminAction()`.
- Session key: `discogs_export` (never `discogs_import`).
- PHP 7.4+ compatible; 2-space indent in touched PHP/JS per project.
- Do not commit unless the user explicitly asks.
- Never commit Discogs secrets; do not commit unrelated `data/music_collection.json` diffs.

## File map

| File | Responsibility |
|------|----------------|
| `services/DiscogsAPIService.php` | `addReleaseToCollection`, `addReleaseToWantlist`, optional `collectReleaseIdSet`; extend HTTP for POST/PUT |
| `services/DiscogsExportService.php` | Build queues; process batch; counts |
| `api/music_api.php` | Export settings + start/page/cancel |
| `setup.php` | Discogs Export tab |
| `assets/js/app.js` (+ min) | Export tab + paged loop |
| `tests/discogs_export_service_test.php` | CLI unit tests (no live Discogs) |
| `INSTALL.md` / `readme.md` | Document Export + user-token requirement |

---

### Task 1: DiscogsAPIService — write helpers + ID set collector

**Files:**
- Modify: `services/DiscogsAPIService.php`
- Test: `tests/discogs_export_service_test.php` (stub methods tested via service in Task 2; here add a tiny HTTP-shape smoke if needed)

**Interfaces:**
- Produces:
  - `addReleaseToCollection($username, $releaseId): array` → `['status' => 'added'|'skipped'|'error', 'message' => string|null, 'http_code' => int]`
  - `addReleaseToWantlist($username, $releaseId): array` → same shape
  - `collectReleaseIdSet($username, $source): array` where `$source` is `'collection'|'wantlist'` → list/map of int release IDs (fetch all pages via existing `getCollectionPage` / `getWantlistPage`)

Discogs endpoints:

```text
POST /users/{username}/collection/folders/0/releases/{release_id}?token=...
PUT  /users/{username}/wants/{release_id}?token=...
```

- [ ] **Step 1: Add private `makeWriteRequest`**

Extend beyond GET-only `makeRequest`. New private method:

```php
/**
 * POST or PUT to Discogs; returns decoded JSON body (or null) and HTTP code via reference.
 *
 * @param string $method POST|PUT
 * @param string $url Absolute API URL without query
 * @param array $queryParams Including token
 * @param int $httpCode Out: HTTP status
 * @param int $retryCount
 * @return array|null
 */
private function makeWriteRequest($method, $url, $params, &$httpCode, $retryCount = 0) {
    if (self::$lastRequestTime > 0) {
        $this->enforceRateLimit();
    }
    $headers = [
        'User-Agent: ' . $this->userAgent,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    $fullUrl = $url;
    if (!empty($params)) {
        $fullUrl .= '?' . http_build_query($params);
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => '{}',
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    self::$lastRequestTime = microtime(true) * 1000000;
    if ($httpCode === 429 && $retryCount < 3) {
        sleep([1, 3, 6][$retryCount]);
        return $this->makeWriteRequest($method, $url, $params, $httpCode, $retryCount + 1);
    }
    if ($response === false || $response === '') {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}
```

Map success: HTTP 201/200 → `added`. HTTP 400/409/422 (already exists) → `skipped`. Other → `error` with message including HTTP code. Do **not** throw on already-exists.

- [ ] **Step 2: Implement public add methods**

```php
public function addReleaseToCollection($username, $releaseId) {
    return $this->addReleaseWrite('collection', $username, $releaseId);
}

public function addReleaseToWantlist($username, $releaseId) {
    return $this->addReleaseWrite('wantlist', $username, $releaseId);
}
```

Internal URL builders:

```php
// collection:
$url = $this->baseUrl . '/users/' . rawurlencode(trim($username))
     . '/collection/folders/0/releases/' . (int) $releaseId;
// method POST

// wantlist:
$url = $this->baseUrl . '/users/' . rawurlencode(trim($username))
     . '/wants/' . (int) $releaseId;
// method PUT
```

Always require `isAvailable()`; throw only if API unavailable or empty username/id.

- [ ] **Step 3: Implement `collectReleaseIdSet`**

```php
public function collectReleaseIdSet($username, $source) {
    $ids = [];
    $page = 1;
    $pages = 1;
    do {
        if ($source === 'wantlist') {
            $result = $this->getWantlistPage($username, $page, 100);
        } else {
            $result = $this->getCollectionPage($username, $page, 100);
        }
        foreach ($result['releases'] as $row) {
            if (!empty($row['discogs_release_id'])) {
                $ids[(int) $row['discogs_release_id']] = true;
            }
        }
        $pages = max(1, (int) $result['pagination']['pages']);
        $page++;
    } while ($page <= $pages);
    return $ids; // associative set
}
```

- [ ] **Step 4: Syntax check**

```bash
php -l services/DiscogsAPIService.php
```

Expected: no syntax errors.

- [ ] **Step 5: Commit** (only if user asked)

---

### Task 2: DiscogsExportService + CLI tests

**Files:**
- Create: `services/DiscogsExportService.php`
- Create: `tests/discogs_export_service_test.php`

**Interfaces:**
- Consumes: `DiscogsAPIService::addReleaseToCollection`, `addReleaseToWantlist`; ID sets as `array<int,true>`
- Produces:
  - `DiscogsExportService::buildQueues(array $albums): array` →
    ```php
    [
      'collection' => [ /* albums with is_owned and release id */ ],
      'wantlist' => [ /* want_to_own and not owned and release id */ ],
      'missing_id' => int,
    ]
    ```
  - `processBatch($phase, array $albums, array $existingIds, DiscogsAPIService $api, $username): array` →
    ```php
    [
      'counts' => ['added'=>0,'skipped'=>0,'missing_id'=>0,'errors'=>0],
      'errors_sample' => [],
    ]
    ```

- [ ] **Step 1: Write failing CLI tests**

```php
<?php
require_once __DIR__ . '/../services/DiscogsExportService.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL $msg\n");
        exit(1);
    }
    echo "PASS $msg\n";
}

$albums = [
    ['id' => 1, 'is_owned' => 1, 'want_to_own' => 0, 'discogs_release_id' => 10],
    ['id' => 2, 'is_owned' => 0, 'want_to_own' => 1, 'discogs_release_id' => 20],
    ['id' => 3, 'is_owned' => 1, 'want_to_own' => 1, 'discogs_release_id' => 30], // owned only → collection
    ['id' => 4, 'is_owned' => 0, 'want_to_own' => 1, 'discogs_release_id' => null], // missing
    ['id' => 5, 'is_owned' => 0, 'want_to_own' => 0, 'discogs_release_id' => 50], // neither
];

$q = DiscogsExportService::buildQueues($albums);
assert_true(count($q['collection']) === 2, 'owned with ids go to collection queue');
assert_true(count($q['wantlist']) === 1, 'want-not-owned with id goes to wantlist');
assert_true($q['missing_id'] === 1, 'missing_id counted');
$wantIds = array_map(function ($a) { return (int) $a['discogs_release_id']; }, $q['wantlist']);
assert_true($wantIds === [20], 'owned+want not duplicated onto wantlist queue');

echo "OK\n";
```

- [ ] **Step 2: Run — expect fail (class missing)**

```bash
php tests/discogs_export_service_test.php
```

Expected: fatal class not found (or FAIL).

- [ ] **Step 3: Implement `DiscogsExportService`**

```php
<?php
class DiscogsExportService {
    /**
     * Partition local albums into export queues.
     *
     * @param array $albums Rows from MusicCollection::getAllAlbums()
     * @return array{collection: array, wantlist: array, missing_id: int}
     */
    public static function buildQueues(array $albums) {
        $collection = [];
        $wantlist = [];
        $missing = 0;
        foreach ($albums as $album) {
            if (!is_array($album)) {
                continue;
            }
            $owned = !empty($album['is_owned']);
            $want = !empty($album['want_to_own']);
            $rid = isset($album['discogs_release_id']) ? $album['discogs_release_id'] : null;
            $hasId = ($rid !== null && $rid !== '' && (int) $rid > 0);

            if ($owned || $want) {
                if (!$hasId) {
                    $missing++;
                    continue;
                }
            }
            if ($owned && $hasId) {
                $collection[] = $album;
                continue;
            }
            if ($want && !$owned && $hasId) {
                $wantlist[] = $album;
            }
        }
        return [
            'collection' => $collection,
            'wantlist' => $wantlist,
            'missing_id' => $missing,
        ];
    }

    /**
     * Process one batch of albums for a phase.
     *
     * @param string $phase collection|wantlist
     * @param array $albums Batch slice
     * @param array $existingIds Associative set of release ids already on Discogs for this phase
     * @param object $api DiscogsAPIService
     * @param string $username
     * @return array{counts: array, errors_sample: array}
     */
    public static function processBatch($phase, array $albums, array $existingIds, $api, $username) {
        $counts = ['added' => 0, 'skipped' => 0, 'missing_id' => 0, 'errors' => 0];
        $errorsSample = [];
        foreach ($albums as $album) {
            $rid = isset($album['discogs_release_id']) ? (int) $album['discogs_release_id'] : 0;
            if ($rid < 1) {
                $counts['missing_id']++;
                continue;
            }
            if (!empty($existingIds[$rid])) {
                $counts['skipped']++;
                continue;
            }
            if ($phase === 'wantlist') {
                $result = $api->addReleaseToWantlist($username, $rid);
            } else {
                $result = $api->addReleaseToCollection($username, $rid);
            }
            $status = isset($result['status']) ? $result['status'] : 'error';
            if ($status === 'added') {
                $counts['added']++;
                $existingIds[$rid] = true; // avoid duplicate POSTs in same run
            } elseif ($status === 'skipped') {
                $counts['skipped']++;
                $existingIds[$rid] = true;
            } else {
                $counts['errors']++;
                if (count($errorsSample) < 10) {
                    $errorsSample[] = isset($result['message']) ? $result['message'] : 'Write failed';
                }
            }
        }
        return [
            'counts' => $counts,
            'errors_sample' => $errorsSample,
            'existing_ids' => $existingIds,
        ];
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php tests/discogs_export_service_test.php
```

Expected: all PASS, OK.

- [ ] **Step 5: Commit** (only if user asked)

---

### Task 3: music_api export actions

**Files:**
- Modify: `api/music_api.php`

**Interfaces:**
- Consumes: `DiscogsExportService::buildQueues`, `processBatch`; `DiscogsAPIService::collectReleaseIdSet`
- Produces actions:
  - `GET get_discogs_export_settings` — username, api_key_set, csrf_token (reuse import username helpers)
  - `POST export_discogs_start` — body `{ username, save_username }`
  - `POST export_discogs_page` — body `{ phase, page }` (1-based page into that phase queue)
  - `POST export_discogs_cancel`

Batch size constant: **15** albums per page.

Session shape:

```php
$_SESSION['discogs_export'] = [
    'username' => string,
    'counts' => ['added'=>0,'skipped'=>0,'missing_id'=>0,'errors'=>0],
    'queues' => ['collection' => [...albums], 'wantlist' => [...]],
    'existing' => ['collection' => [id=>true], 'wantlist' => [id=>true]],
    'started' => time(),
];
```

On start: load all local albums; `buildQueues`; set `counts.missing_id` from queues; fetch both ID sets via `collectReleaseIdSet` (may take time — acceptable for start); store queues + existing; return `next: { phase: 'collection', page: 1 }` or jump to wantlist/done if collection empty.

On page: slice queue for phase: `$offset = ($page-1)*15`, `$batch = array_slice(...)`. Call `processBatch`; merge counts; update `existing` in session from returned set; compute next page or switch phase or done.

Reuse username save helpers from import (`discogsImportLoadSavedUsername`, `discogsImportSaveUsernameToSettings`, `validateDiscogsUsernameSetting`) — do not invent parallel settings keys.

- [ ] **Step 1: Add empty-counts helper**

```php
function discogsExportEmptyCounts() {
    return ['added' => 0, 'skipped' => 0, 'missing_id' => 0, 'errors' => 0];
}
```

- [ ] **Step 2: Wire GET/POST cases** (mirror import_discogs_* structure; session key `discogs_export` only).

- [ ] **Step 3: Auth smoke**

```bash
curl -sk -X POST 'https://music.lndo.site/api/music_api.php?action=export_discogs_start' \
  -H 'Content-Type: application/json' -d '{"username":"x"}'
```

Expected: HTTP 401 Authentication required.

- [ ] **Step 4: Commit** (only if user asked)

---

### Task 4: Setup UI — Discogs Export tab + JS loop

**Files:**
- Modify: `setup.php` — tab button after Discogs Import; panel `#discogs-export`
- Modify: `assets/js/app.js` — load settings, start/page loop, resume, CSRF via existing `checkAuthStatus`
- Rebuild: `npm run build:js`

**UI IDs:**

- `#discogsExportUsername`
- `#discogsExportSaveUsername` (checkbox-group / checkbox-option pattern — **not** bare form-group checkbox)
- `#discogsExportStartBtn` — “Push to Discogs”
- `#discogsExportProgress` — phase, page, counts (`added`, `skipped`, `missing_id`, `errors`)
- Note: “Keep this tab open. Push is add-only; nothing is removed from Discogs. Albums without a Discogs release ID are skipped.”

Confirm copy: add-only; keep tab open; no Discogs deletes.

JS loop (mirror import):

```javascript
async runDiscogsExport(next) {
  while (next) {
    const pageNext = next;
    const response = await this.apiFetch('api/music_api.php?action=export_discogs_page', {
      method: 'POST',
      body: JSON.stringify(pageNext),
    });
    const data = await response.json();
    if (!data.success) {
      this.discogsExportResumeNext = pageNext;
      throw new Error(data.message || 'Export page failed');
    }
    this.renderDiscogsExportProgress(data.data);
    if (data.data && data.data.done) {
      this.discogsExportResumeNext = null;
      this.showDiscogsExportComplete(data.data);
      break;
    }
    next = data.data ? data.data.next : null;
  }
}
```

On failure: do **not** auto-cancel; allow resume. Wire `onSetupTabShown('discogs-export')` to load settings.

- [ ] **Step 1: Markup in setup.php**
- [ ] **Step 2: JS methods + tab hook**
- [ ] **Step 3: `npm run build:js`** — exit 0
- [ ] **Step 4: Browser** — open Export tab; checkbox left-aligned; settings load (auth required)
- [ ] **Step 5: Commit** (only if user asked)

---

### Task 5: Docs + verification checklist

**Files:**
- Modify: `INSTALL.md`, `readme.md`

- [ ] **Step 1: Document** Setup → Discogs Export; personal access token needed for writes; add-only; folder 0; skip missing IDs; import unchanged.

- [ ] **Step 2: Checklist**

1. `php tests/discogs_export_service_test.php` PASS  
2. Unauthenticated `export_discogs_start` → 401  
3. Import CLI tests still PASS (`discogs_import_merge_test.php`)  
4. (Optional live) Push one owned release → appears in Discogs Collection; re-run → skipped  
5. (Optional live) Want-not-owned → Wantlist; owned not also wantlisted  

- [ ] **Step 3: Commit** (only if user asked)

---

## Spec coverage self-check

| Spec item | Task |
|-----------|------|
| Owned → Collection folder 0 | 1, 2, 3 |
| Want-not-owned → Wantlist | 2, 3 |
| Skip if already on Discogs | 1, 2, 3 |
| Skip missing release ID + count | 2, 3, 4 |
| Never delete on Discogs | 1–5 (no delete APIs) |
| Separate Export tab | 4 |
| Paged AJAX + session `discogs_export` | 3, 4 |
| Import unchanged | Global + Task 5 regression |
| User token docs | 5 |
| CSRF / requireAdminAction | 3 |

## Placeholder scan

No TBD steps; live Discogs smoke left optional/operator-driven.
