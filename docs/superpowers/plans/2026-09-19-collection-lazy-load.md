# Collection Lazy Load Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Load the collection table in pages of 100 with infinite scroll, refetching from the server on filter/search/sort so results stay complete.

**Architecture:** Add a PHP list helper that filters, sorts, and slices the catalog; extend `action=albums` with `page`/`limit`/`meta` and facet/sort query params. Frontend replaces page 1 on query changes and appends further pages via an IntersectionObserver sentinel. Own/Want/Total badges stay on `action=stats`.

**Tech Stack:** PHP 7.4+ JSON/`SimpleDB` catalog, `api/music_api.php`, vanilla JS `app.js` + `npm run build:js` / `npm run build:sass` as needed.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-19-collection-lazy-load-design.md`
- Chunk size default **100**, max **200**
- Infinite scroll only (no numbered pages)
- Server refetch on Own/Want/Total, search, facets, and column sort (reset to page 1)
- Badge counts from stats, not “rows loaded”
- SimpleDB may still read the full JSON file per request; that is acceptable
- PHP/JS: 2-space indent in touched code
- Do not commit unless the user asks; never commit `*.json` catalog data
- Work on current branch
- Verify with `php -r` and curl against Lando (`https://music.lndo.site`)

## File map

| File | Responsibility |
|------|----------------|
| `services/CollectionListHelper.php` | Filter, sort, paginate album arrays; build `meta` |
| `api/music_api.php` | `action=albums` accepts page/limit/facets/sort; returns `data` + `meta` |
| `assets/js/app.js` (+ `app.min.js`) | Paged `loadAlbums`, append on scroll, sentinel, request fingerprint |
| `index.php` | Sentinel + “Loading more…” markup under the table |
| `assets/scss/components/_tables.scss` or `_modals.scss` / collection styles | Minimal styles for loading-more row if needed |
| `INSTALL.md` / `readme.md` | One-line note that the list is lazily loaded |

---

### Task 1: CollectionListHelper (filter / sort / page)

**Files:**
- Create: `services/CollectionListHelper.php`
- Verify: `php -r` script (no permanent test file required unless you add `tests/collection_list_helper_test.php`)

**Interfaces:**
- Produces:
  - `collection_list_normalize_params(array $get): array` — returns keys: `page` (int ≥1), `limit` (int 1–200, default 100), `filter` (`owned`|`wanted`|`all`|null), `search` (string), `style`, `format`, `format_types` (array|null), `year`, `artist`, `label`, `producer`, `sort` (`artist`|`album`|`year`), `direction` (`asc`|`desc`)
  - `collection_list_apply(array $albums, array $params): array` — returns `['albums' => array, 'meta' => ['page'=>int,'limit'=>int,'total'=>int,'has_more'=>bool]]`
  - `collection_list_sortable_artist_name(string $artistName, $artistType = null): string` — mirror JS `getSortableArtistName` (articles, Person last-name, Elvis Costello special case)
  - `collection_list_sortable_album_name(string $albumName): string` — mirror JS `getSortableAlbumName` if it strips leading articles; otherwise trim + lowercase compare key
  - `collection_list_clean_discogs_numbering(string $label): string` — strip trailing ` (N)` Discogs label numbering like JS `cleanDiscogsNumbering`

- [ ] **Step 1: Create helper with normalize + paginate core**

```php
<?php
/**
 * Server-side collection list filter, sort, and pagination.
 */

/**
 * @param array $get Typically $_GET
 * @return array Normalized list query params
 */
function collection_list_normalize_params($get) {
  $page = isset($get['page']) ? (int) $get['page'] : 1;
  if ($page < 1) {
    $page = 1;
  }
  $limit = isset($get['limit']) ? (int) $get['limit'] : 100;
  if ($limit < 1) {
    $limit = 100;
  }
  if ($limit > 200) {
    $limit = 200;
  }
  $filter = isset($get['filter']) ? $get['filter'] : 'all';
  if (!in_array($filter, ['owned', 'wanted', 'all'], true)) {
    $filter = 'all';
  }
  $sort = isset($get['sort']) ? $get['sort'] : 'artist';
  if (!in_array($sort, ['artist', 'album', 'year'], true)) {
    $sort = 'artist';
  }
  $direction = isset($get['direction']) ? strtolower($get['direction']) : 'asc';
  if (!in_array($direction, ['asc', 'desc'], true)) {
    $direction = 'asc';
  }
  $formatTypes = null;
  if (!empty($get['format_types'])) {
    if (is_array($get['format_types'])) {
      $formatTypes = array_values(array_filter(array_map('strval', $get['format_types'])));
    } else {
      $formatTypes = array_values(array_filter(array_map('trim', explode(',', (string) $get['format_types']))));
    }
    if (count($formatTypes) === 0) {
      $formatTypes = null;
    }
  }
  return [
    'page' => $page,
    'limit' => $limit,
    'filter' => $filter,
    'search' => isset($get['search']) ? trim((string) $get['search']) : '',
    'style' => isset($get['style']) ? trim((string) $get['style']) : '',
    'format' => isset($get['format']) ? trim((string) $get['format']) : '',
    'format_types' => $formatTypes,
    'year' => isset($get['year']) ? trim((string) $get['year']) : '',
    'artist' => isset($get['artist']) ? trim((string) $get['artist']) : '',
    'label' => isset($get['label']) ? trim((string) $get['label']) : '',
    'producer' => isset($get['producer']) ? trim((string) $get['producer']) : '',
    'sort' => $sort,
    'direction' => $direction,
  ];
}

/**
 * @param array $albums Full catalog rows
 * @param array $params From collection_list_normalize_params
 * @return array{albums: array, meta: array}
 */
function collection_list_apply($albums, $params) {
  $filtered = collection_list_filter($albums, $params);
  $sorted = collection_list_sort($filtered, $params['sort'], $params['direction']);
  $total = count($sorted);
  $offset = ($params['page'] - 1) * $params['limit'];
  $pageAlbums = array_slice($sorted, $offset, $params['limit']);
  return [
    'albums' => array_values($pageAlbums),
    'meta' => [
      'page' => $params['page'],
      'limit' => $params['limit'],
      'total' => $total,
      'has_more' => ($params['page'] * $params['limit']) < $total,
    ],
  ];
}
```

Implement `collection_list_filter` and `collection_list_sort` in the same file:

**Filter order (match current JS):**
1. `style:` / `genre:` / `type:` search prefix → style contains (comma-split), and clear normal search
2. Else normal `search` → case-insensitive substring on `artist_name` or `album_name`
3. `style` facet → exact token match in comma-split styles
4. `format` / `format_types` → same as JS (consolidated list OR exact unescaped format)
5. `year` → `release_year == year`
6. `artist` → case-insensitive exact `artist_name`
7. `label` → clean Discogs numbering, case-insensitive exact
8. `producer` → case-insensitive `includes` on `producer`
9. `filter` owned/wanted on `is_owned` / `want_to_own`

**Sort:** Port `sortAlbums` logic using `collection_list_sortable_artist_name` / album helper (year primary with artist tiebreak; album field = artist then year then album; default artist).

- [ ] **Step 2: Verify with php -r**

```bash
cd /Users/billkummerow/personal_site && php -r '
require "services/CollectionListHelper.php";
$albums = [
  ["id"=>1,"artist_name"=>"Wire","album_name"=>"Pink Flag","release_year"=>"1977","is_owned"=>1,"want_to_own"=>0,"style"=>"Punk","format"=>"Vinyl, LP","label"=>"Harvest","producer"=>"","artist_type"=>"Group"],
  ["id"=>2,"artist_name"=>"The Beatles","album_name"=>"Abbey Road","release_year"=>"1969","is_owned"=>1,"want_to_own"=>0,"style"=>"Pop","format"=>"Vinyl, LP","label"=>"Apple","producer"=>"George Martin","artist_type"=>"Group"],
  ["id"=>3,"artist_name"=>"Wire","album_name"=>"Chairs Missing","release_year"=>"1978","is_owned"=>0,"want_to_own"=>1,"style"=>"Punk","format"=>"CD","label"=>"Harvest","producer"=>"","artist_type"=>"Group"],
];
$p = collection_list_normalize_params(["page"=>1,"limit"=>1,"filter"=>"all","search"=>"Wire","sort"=>"year","direction"=>"asc"]);
$r = collection_list_apply($albums, $p);
assert(count($r["albums"])===1);
assert($r["meta"]["total"]===2);
assert($r["meta"]["has_more"]===true);
assert($r["albums"][0]["album_name"]==="Pink Flag");
$p2 = collection_list_normalize_params(["page"=>2,"limit"=>1,"search"=>"Wire","sort"=>"year","direction"=>"asc"]);
$r2 = collection_list_apply($albums, $p2);
assert($r2["albums"][0]["album_name"]==="Chairs Missing");
assert($r2["meta"]["has_more"]===false);
echo "ok\n";
'
```

Expected: `ok`

- [ ] **Step 3: Commit** (only if user asked)

```bash
git add services/CollectionListHelper.php
git commit -m "$(cat <<'EOF'
Add collection list helper for filter, sort, and pagination.

EOF
)"
```

---

### Task 2: Wire `action=albums` API

**Files:**
- Modify: `api/music_api.php` (`case 'albums':` ~551–564)
- Consumes: `CollectionListHelper.php` functions from Task 1

**Interfaces:**
- Produces: JSON `{ success, data, meta }` for paged albums

- [ ] **Step 1: Require helper and replace albums case**

Near other requires at top of `music_api.php`:

```php
require_once __DIR__ . '/../services/CollectionListHelper.php';
```

Replace the `albums` case body with:

```php
case 'albums':
  $params = collection_list_normalize_params($_GET);
  // Keep existing owned/wanted SQL prefilter when no text/facet filters need full scan.
  // Simplest correct path: always getAllAlbums then PHP filter (matches spec; OK for JSON store).
  $all = $musicCollection->getAllAlbums(null, '');
  $result = collection_list_apply($all, $params);
  foreach ($result['albums'] as &$album) {
    $album['master_year'] = $album['release_year'];
  }
  unset($album);
  $response['data'] = $result['albums'];
  $response['meta'] = $result['meta'];
  $response['success'] = true;
  break;
```

Do **not** leave an unpaged full dump as the default when `page` is omitted — normalize defaults to page 1 / limit 100.

- [ ] **Step 2: Verify with curl (Lando)**

```bash
curl -sk "https://music.lndo.site/api/music_api.php?action=albums&page=1&limit=100" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
echo "success=".($j["success"]?"1":"0")." n=".count($j["data"]??[])." total=".($j["meta"]["total"]??"?")." more=".(($j["meta"]["has_more"]??false)?"1":"0")."\n";
'
curl -sk "https://music.lndo.site/api/music_api.php?action=albums&page=1&limit=100&search=Wire" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
echo "wire total=".($j["meta"]["total"]??"?")." n=".count($j["data"]??[])."\n";
'
```

Expected: first call `n<=100`, `total` ≈ full catalog size, `more=1` if total>100; Wire search `total` equals matching albums.

- [ ] **Step 3: Commit** (only if user asked)

```bash
git add api/music_api.php
git commit -m "$(cat <<'EOF'
Page albums API responses with filter and sort meta.

EOF
)"
```

---

### Task 3: Frontend page-1 load (replace client filter pipeline)

**Files:**
- Modify: `assets/js/app.js` — constructor state, `loadAlbums`, `handleSort`
- Modify: rebuild `assets/js/app.min.js` via `npm run build:js`

**Interfaces:**
- Consumes: `meta` from Task 2
- Produces: `this.listPage`, `this.listHasMore`, `this.listLoadingMore`, `this.listRequestId`, `buildAlbumsQueryParams()`, `loadAlbums({ append: false })`

- [ ] **Step 1: Add list state on the class**

In constructor (near other `this.current*` fields):

```javascript
this.listPage = 1;
this.listHasMore = false;
this.listLoadingMore = false;
this.listRequestId = 0;
this.listLimit = 100;
```

- [ ] **Step 2: Add `buildAlbumsQueryParams(page)`**

```javascript
/**
 * Build query string for paged albums API.
 * @param {number} page
 * @returns {URLSearchParams}
 */
buildAlbumsQueryParams(page) {
  const searchLower = (this.currentSearch || '').toLowerCase();
  const styleKeywords = ['style:', 'genre:', 'type:'];
  const isStyleSearch = styleKeywords.some((keyword) => searchLower.startsWith(keyword));
  const params = new URLSearchParams({
    action: 'albums',
    filter: this.currentFilter || 'all',
    search: isStyleSearch ? this.currentSearch : (this.currentSearch || ''),
    page: String(page),
    limit: String(this.listLimit),
    sort: this.currentSort.field || 'artist',
    direction: this.currentSort.direction || 'asc',
  });
  if (this.currentStyleFilter) {
    params.set('style', this.currentStyleFilter);
  }
  if (this.currentFormatFilter) {
    params.set('format', this.currentFormatFilter);
  }
  if (this.consolidatedFormatTypes && this.consolidatedFormatTypes.length) {
    params.set('format_types', this.consolidatedFormatTypes.join(','));
  }
  if (this.currentYearFilter) {
    params.set('year', String(this.currentYearFilter));
  }
  if (this.currentArtistFilter) {
    params.set('artist', this.currentArtistFilter);
  }
  if (this.currentLabelFilter) {
    params.set('label', this.currentLabelFilter);
  }
  if (this.currentProducerFilter) {
    params.set('producer', this.currentProducerFilter);
  }
  return params;
}
```

- [ ] **Step 3: Rewrite `loadAlbums` to fetch page 1 only**

Remove the client-side style/format/year/artist/label/producer/search filtering block and the `sortAlbums` call inside `loadAlbums`. Replace with:

```javascript
async loadAlbums(options = {}) {
  const append = !!options.append;
  if (append) {
    if (this.listLoadingMore || !this.listHasMore) {
      return;
    }
    this.listLoadingMore = true;
    this.setListLoadingMoreUi(true);
  } else {
    this.toggleLoading(true);
    const tableContainer = document.querySelector('.table-container');
    if (tableContainer) {
      tableContainer.classList.add('loading');
    }
    this.listPage = 1;
  }

  const requestId = ++this.listRequestId;
  const page = append ? (this.listPage + 1) : 1;
  const params = this.buildAlbumsQueryParams(page);

  try {
    const response = await this.fetchWithCache(`api/music_api.php?${params}`, { cache: 'no-cache' });
    const data = await response.json();
    if (requestId !== this.listRequestId) {
      return;
    }
    if (!data.success) {
      if (!append) {
        this.showMessage(data.message || 'Could not load albums', 'error');
        this.renderAlbums([]);
      } else {
        this.showListLoadMoreError(data.message || 'Could not load more albums');
      }
      return;
    }
    const pageAlbums = Array.isArray(data.data) ? data.data : [];
    const meta = data.meta || {};
    this.listHasMore = !!meta.has_more;
    this.listPage = meta.page || page;
    if (append) {
      this.albums = (this.albums || []).concat(pageAlbums);
      this.appendAlbums(pageAlbums);
    } else {
      this.albums = pageAlbums;
      this.renderAlbums(this.albums);
    }
    // Badges: do not call updateFilterButtonsWithFilteredCount on partial pages.
    // Stats path continues to own Own/Want/Total numbers.
  } catch (error) {
    console.error('Error loading albums:', error);
    if (!append) {
      this.showMessage('Error loading albums', 'error');
    } else {
      this.showListLoadMoreError('Could not load more albums');
    }
  } finally {
    if (requestId === this.listRequestId) {
      if (append) {
        this.listLoadingMore = false;
        this.setListLoadingMoreUi(false);
      } else {
        this.toggleLoading(false);
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
          tableContainer.classList.remove('loading');
        }
      }
    }
  }
}
```

Add stubs used above (minimal):

```javascript
setListLoadingMoreUi(isLoading) {
  const el = document.getElementById('albumsLoadMoreStatus');
  if (el) {
    el.hidden = !isLoading;
    el.textContent = isLoading ? 'Loading more…' : '';
  }
}

showListLoadMoreError(message) {
  const el = document.getElementById('albumsLoadMoreStatus');
  if (el) {
    el.hidden = false;
    el.textContent = message;
  }
}

/**
 * Append rows without full tbody replace.
 * @param {Array} albums
 */
appendAlbums(albums) {
  // Prefer extracting row HTML builder from renderAlbums if one exists;
  // otherwise call renderAlbums(this.albums) for v1 correctness (full re-render of loaded set).
  this.renderAlbums(this.albums);
}
```

- [ ] **Step 4: Change `handleSort` to reload from server**

```javascript
handleSort(sortField) {
  if (this.currentSort.field === sortField) {
    this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    this.currentSort.field = sortField;
    this.currentSort.direction = 'asc';
  }
  this.updateSortIndicators();
  this.loadAlbums({ append: false });
}
```

Leave `sortAlbums` / `renderAlbumsWithSort` in the file unused for now (or delete only if nothing else calls them).

- [ ] **Step 5: Build min JS**

```bash
cd /Users/billkummerow/personal_site && npm run build:js
```

Expected: exit 0

- [ ] **Step 6: Manual check** — open collection, confirm ≤100 rows; Own/Want badges still show full stats.

- [ ] **Step 7: Commit** (only if user asked)

```bash
git add assets/js/app.js assets/js/app.min.js
git commit -m "$(cat <<'EOF'
Load collection page one from the paged albums API.

EOF
)"
```

---

### Task 4: Infinite scroll sentinel

**Files:**
- Modify: `index.php` (after `</table>` inside `.table-container` or just below it)
- Modify: `assets/js/app.js` — init observer; `append` path
- Rebuild: `npm run build:js`

**Interfaces:**
- Consumes: `loadAlbums({ append: true })`, `listHasMore`
- Produces: IntersectionObserver on `#albumsScrollSentinel`

- [ ] **Step 1: Markup**

In `index.php` after the albums table (still inside the list section):

```html
<div id="albumsLoadMoreStatus" class="albums-load-more-status" hidden></div>
<div id="albumsScrollSentinel" class="albums-scroll-sentinel" aria-hidden="true"></div>
```

Optional SCSS (minimal):

```scss
.albums-load-more-status {
  text-align: center;
  padding: $spacing-md;
  color: $text-secondary;
  font-size: $font-size-sm;
}
.albums-scroll-sentinel {
  height: 1px;
  width: 100%;
}
```

Run `npm run build:sass` if SCSS added.

- [ ] **Step 2: Init observer once after DOM ready**

```javascript
initAlbumsInfiniteScroll() {
  const sentinel = document.getElementById('albumsScrollSentinel');
  if (!sentinel || typeof IntersectionObserver === 'undefined') {
    return;
  }
  this.albumsScrollObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        this.loadAlbums({ append: true });
      }
    });
  }, { root: null, rootMargin: '200px', threshold: 0 });
  this.albumsScrollObserver.observe(sentinel);
}
```

Call `this.initAlbumsInfiniteScroll()` from the same place other UI inits run (e.g. after `loadAlbums` first bind / `init`).

- [ ] **Step 3: After mutations that change membership**

Wherever save/delete/ownership already calls `loadAlbums()`, keep that — it resets to page 1 via non-append path.

- [ ] **Step 4: Verify in browser**

1. Load site → ≤100 rows.  
2. Scroll to bottom → next page appends; status flashes briefly.  
3. Search Wire → table resets; scroll only within Wire results.  
4. Sort by year → resets; years ordered across pages.  
5. Own/Want numbers unchanged by scrolling.

- [ ] **Step 5: Commit** (only if user asked)

```bash
git add index.php assets/js/app.js assets/js/app.min.js assets/scss/**/*.scss assets/css/main.css assets/css/main.css.map
git commit -m "$(cat <<'EOF'
Add infinite scroll for paged collection rows.

EOF
)"
```

---

### Task 5: Docs

**Files:**
- Modify: `readme.md` and/or `INSTALL.md` — one short note under collection UI / performance

- [ ] **Step 1: Document behavior**

Add something like:

```markdown
The collection table loads 100 albums at a time and fetches more as you scroll. Search, filters, and column sort request a fresh first page from the server.
```

- [ ] **Step 2: Commit** (only if user asked)

```bash
git add readme.md INSTALL.md
git commit -m "$(cat <<'EOF'
Document collection lazy loading behavior.

EOF
)"
```

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| page/limit default 100, max 200 | 1, 2 |
| meta total / has_more | 1, 2 |
| Infinite scroll | 4 |
| Server refetch on filter/search/sort | 3 |
| Facet filters server-side | 1, 3 |
| Badges via stats not scroll | 3 |
| Error handling first vs load-more | 3 |
| Stale request ignore | 3 (`listRequestId`) |
| Docs | 5 |
| Verification steps | 2, 3, 4 |

## Plan self-review

- No TBD placeholders.
- Helper interfaces named consistently (`collection_list_*`).
- Frontend always sends `filter` as current Own/Want/Total (not the old “force all when facets” hack); helper applies facets then owned/wanted.
- `appendAlbums` may full-re-render loaded set in v1 — acceptable; optimize later if needed.
