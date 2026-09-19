# Collection Lazy Load Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

The collection table loads every album in one `action=albums` response and keeps the full set in the browser (`this.albums`). At ~430 albums that is acceptable; as the catalog grows, first paint, memory, and DOM cost grow with it.

## Goals

- Load the table in chunks (default **100** albums per request).
- Use **infinite scroll** to fetch the next chunk when the user nears the bottom of the list.
- On Own/Want/Total, search, facet filters, or column sort: **reset to page 1** and refetch from the server so results stay complete and correct.
- Keep Own/Want/Total **badge counts** on the existing stats path (full-collection counts), not on how many rows are currently loaded.
- Reduce JSON payload size and DOM row count on first paint and while browsing.

## Non-goals (v1)

- Numbered page UI (“page 1 of N”)
- Client-only virtualization while still downloading the full catalog
- A separate slim list API with different album shapes
- Changing how `action=stats` computes Own/Want/Total
- Skipping the JSON file read inside SimpleDB (still reads the catalog file; win is wire + DOM)

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| UX | **A** — Infinite scroll (IntersectionObserver + sentinel) |
| Filters / search | **A** — Server refetch; reset to page 1 |
| Chunk size | **B** — 100 per page (cap max, e.g. 200) |
| Architecture | **1** — Extend `action=albums` with `page` / `limit` + `meta` |

---

## Design

### 1. API

`GET api/music_api.php?action=albums`

| Param | Meaning |
|-------|---------|
| `page` | 1-based page index (default `1`) |
| `limit` | Page size (default `100`, max capped e.g. `200`) |
| `filter` | `owned` / `wanted` / `all` (existing) |
| `search` | Artist/album search (existing) |
| Facet filters | Style, format, year, artist, label, producer — query params matching current UI filters |
| `sort` / `direction` | Column sort (e.g. `artist`/`album`/`year`, `asc`/`desc`) so order is consistent across pages |

Server flow:

1. Load catalog via existing MusicCollection / SimpleDB path.
2. Apply filter + search + facet filters in PHP (same semantics as today’s client filters).
3. Sort with the same rules as today’s `sortAlbums` (including artist sort-key behavior where applicable).
4. Compute `total`, then `array_slice` for the requested page.
5. Return albums for that page only.

Response:

```json
{
  "success": true,
  "data": [ /* up to limit albums */ ],
  "meta": {
    "page": 1,
    "limit": 100,
    "total": 430,
    "has_more": true
  }
}
```

`has_more` is true when `page * limit < total`.

### 2. Frontend

- State: current `page`, `has_more`, `loadingMore`, and a request fingerprint (filter/search/sort) so stale responses are ignored.
- Initial load / filter / sort change: show existing table loading overlay; replace `this.albums` with page 1; render; set `has_more` from `meta`.
- Sentinel element below the table body. When it intersects the viewport and `has_more` and not `loadingMore`, request `page + 1` and **append** rows.
- While loading more: show a subtle “Loading more…” under the list; do not clear existing rows.
- When `has_more` is false: hide the sentinel / loading hint.
- Own/Want/Total buttons continue to use `action=stats` (or equivalent full counts). Scrolling does not change badge numbers.
- After add / delete / ownership edit that changes list membership: reload from page 1 with current filters (simple and correct).

### 3. Error handling

| Case | Behavior |
|------|----------|
| First page fails | Existing error messaging; empty table |
| Load-more fails | Keep rows already shown; short error under list; allow retry on next scroll |
| Response fingerprint mismatch | Ignore (user changed filters mid-flight) |

### 4. Verification

1. Fresh load shows ≤100 rows; Own/Want/Total badges match today’s full counts.
2. Scroll near bottom appends the next page without wiping the list.
3. Search or Own/Want filter resets to a correct page 1; further scroll stays within that result set.
4. Sort by year (or album) resets and stays consistent across pages.
5. Network: page-1 payload is much smaller than today’s full-catalog response.

---

## Open notes

- SimpleDB still reads `music_collection.json` per request; pagination does not avoid that. If the file becomes very large later, a real DB or indexed store would be a separate project.
- Consolidated format filtering and `style:` search prefixes must keep current UX semantics when moved server-side.
