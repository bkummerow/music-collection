# Light theme — single primary → WCAG palette

## User flow

1. In **Setup → Theme**, pick one **Primary color** (light mode only).
2. On change/preview/save, `MusicThemePalette.buildLightThemePalette()` derives:
   - `--primary-color` (≥ **3:1** vs `#fff` for UI/borders)
   - `--primary-dark` (≥ **4.5:1** vs `#fff` for buttons with white text)
   - `--link-color` (≥ **4.5:1** vs `#fff` and vs `--collection-surface` / hover variants)
   - `--gradient-color-1` / `--gradient-color-2` (decorative body gradient; content stays on opaque surfaces)
   - `--collection-surface` / hover variants (album table `#albumsTable`; ~12% primary tint when possible; `#333` / `#495057` on surface; `--collection-text-muted` darkened for AA on tinted panels)
3. Generated palette preview (setup panel): **background gradient start** (same as chosen primary), **background gradient end**, links, buttons, **collection surface**, **panels & row hover** (`--collection-surface-hover` / `--panel-surface`).
4. If the chosen primary is below WCAG contrast on white (e.g. `#e0e0e0`), a **warning alert** appears in Setup before save.

## Code

| Piece | Path |
|-------|------|
| Contrast math + apply | `assets/js/theme-palette.js` |
| UI + save/load | `assets/js/app.js` |
| Settings | `data/settings.json` → `theme.primary_color`, `gradient_color_1/2` |

## Build

`theme-palette.js` is concatenated into `app.min.js` via `npm run build:js`.

## Service worker

Bump `CACHE_NAME` in `sw.js` when shipping JS changes.
