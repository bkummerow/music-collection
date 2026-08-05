# Cover Modal Slideshow Design

**Date:** 2026-08-05  
**Status:** Approved — implementation plan written  
**Site:** music.lndo.site (personal_site)

## Goal

When Discogs provides multiple images for a release, the cover modal (`#coverModal`) shows a manual slideshow of **all** full-size images. Thumbnails in the collection table remain a single small image.

## Decisions

| Topic | Choice |
|--------|--------|
| Which Discogs images | All (`images[]`: primary + secondary — covers, backs, labels, inserts, etc.) |
| Data source | Stored in collection JSON (no live Discogs fetch on modal open) |
| Navigation | Manual only: prev/next + keyboard ←/→ + counter `n / total` |
| Data shape | Keep `cover_url`; add `cover_images`; drop `cover_url_large` |

## Data model

### Per album fields

```json
{
  "cover_url": "https://i.discogs.com/.../uri150-or-150px...",
  "cover_images": [
    "https://i.discogs.com/.../full-primary...",
    "https://i.discogs.com/.../full-secondary-1...",
    "https://i.discogs.com/.../full-secondary-2..."
  ]
}
```

- **`cover_url`**: Discogs thumbnail (`uri150` / search thumb). Used only for list/table/autocomplete. Unchanged role.
- **`cover_images`**: Ordered array of full-size Discogs `uri` values. Index 0 is the primary image (same role formerly held by `cover_url_large`). Remaining entries are additional release images, in Discogs order after primary.
- **`cover_url_large`**: Removed. Do not write on create/update. Readers may temporarily fall back during migration (see Compatibility).

### Derivation from Discogs release payload

From `GET /releases/{id}` → `images[]`:

1. Prefer the entry with `"type": "primary"` as first element; if none, use `images[0]`.
2. Append every other image’s `uri` (HTTPS), preserving Discogs order, skipping entries with empty `uri`.
3. Set `cover_url` from the primary image’s `uri150` (fallback: first available thumb field / existing helpers).
4. Deduplicate URLs if Discogs ever repeats the same `uri`.

Empty / missing images:

- If Discogs returns no images: always write `cover_images` as `[]`; UI falls back to `cover_url` if present, else no cover.
- If only one image: `cover_images` has length 1; modal shows single image with no slideshow chrome.

### Compatibility (transition)

Until backfill completes, resolve the “large primary” URL as:

```text
cover_images[0] || cover_url_large || cover_url
```

Resolve the slideshow list as:

```text
cover_images (if non-empty array)
else if cover_url_large → [cover_url_large]
else if cover_url → [cover_url]
else → []
```

After backfill of the active collection file(s), `cover_url_large` can be ignored entirely. New writes never set it.

## Backfill

Extend `.scripts/upgrade-cover-art.php` (same CLI style: `--file=`, `--dry-run` / `--apply`, rate-limit sleep):

1. For each album with `discogs_release_id`, fetch release.
2. Write `cover_url` (thumb) and `cover_images` (all full `uri`s).
3. Remove `cover_url_large` from the album object when applying.
4. Log SKIP / SAME / UPDATE / FAIL as today; include image count on UPDATE.

Target files: whatever collection JSON is active for local/prod (e.g. `data/music_collection.json`); operator chooses `--file=`.

## Cover modal UI

**Scope:** `#coverModal` only (not tracklist modal cover).

**Single image** (`cover_images.length <= 1` after resolution): current behavior — one `<img>`, no arrows, no counter.

**Multiple images:**

- Prev / next controls (accessible buttons).
- Counter text: `currentIndex + 1` / `total` (e.g. `2 / 5`).
- Keyboard: Left = previous, Right = next, only while cover modal is open / focused.
- Wrap around (last → first, first → last).
- Opening the modal always starts at index `0`.
- No autoplay, no thumbnail strip.

**Markup:** Extend existing `#coverModal` structure in `index.php` with controls that are hidden when `total <= 1`. CSS in `assets/css/main.css` to match existing modal styling.

**JS:** Update `showCoverModal` in `assets/js/app.js` to accept the image list (or resolve it from album data), keep current index in instance state, and wire button + key handlers. Clear/unbind key handlers when the modal closes.

## API and persistence

- Create / update album paths (`music_api.php`, Discogs helpers, add-album flow): persist `cover_url` + `cover_images`; stop writing `cover_url_large`.
- `DiscogsAPIService`: add a helper that returns `{ cover_url, cover_images }` from a release payload (reuse / extend current `getCoverArtForSize` logic rather than only picking primary).
- Responses that expose cover art to the client include `cover_images` so the modal does not need a second round-trip.
- Table rendering: continue using `cover_url` for lazy-loaded thumbs; click-to-open cover modal resolves `cover_images` from in-memory album data by `albumId` (preferred over stuffing long URL lists into `data-*` attributes).

## Out of scope

- Slideshow / gallery in the tracklist modal
- Autoplay
- Thumbnail strip under the large image
- Filtering by Discogs image `type`
- Live Discogs fetch when opening the modal
- Rewriting Discogs CDN size params in `ImageOptimizationService` (keep official `uri` / `uri150`)

## Success criteria

1. Albums with multiple Discogs images show prev/next + counter in the cover modal; all images are reachable.
2. Albums with zero or one image behave as today (no slideshow chrome).
3. Collection table still loads thumbs from `cover_url` only.
4. New albums written after the change have `cover_images` and no `cover_url_large`.
5. Upgrade script can dry-run and apply a full collection backfill under Discogs rate limits.
6. Keyboard ←/→ navigate only while the cover modal is open; closing the modal removes those handlers.

## Implementation notes (non-binding)

Likely touch points: `services/DiscogsAPIService.php`, `.scripts/upgrade-cover-art.php`, `api/music_api.php`, `models/MusicCollection.php` (if field lists exist), `index.php` (modal markup), `assets/css/main.css`, `assets/js/app.js` (`showCoverModal` and cover click / data attributes).
