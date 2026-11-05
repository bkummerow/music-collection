# Build Process

This project uses npm and build tools to compile native CSS and minify JavaScript for production.

## Prerequisites

Node.js and npm.

## Installation

```bash
npm install
```

## Available Scripts

### Build Commands

- **`npm run build`** — Build CSS and JS (one-time)
- **`npm run watch`** — Watch CSS and JS and rebuild automatically

### Individual Commands

- **`npm run build:css`** — Bundle and minify CSS from `assets/css/src/` into `assets/css/`
- **`npm run build:js`** — Minify JavaScript only
- **`npm run watch:css`** — Watch native CSS sources under `assets/css/src/`
- **`npm run watch:js`** — Watch `assets/js/app.js` only

### Manual CSS build

```bash
node .scripts/build-css.mjs
```

## Development Workflow

1. Edit styles under **`assets/css/src/`** (layered entry points and partials).
2. Edit application logic in **`assets/js/app.js`**.
3. Run **`npm run build`** before deploy, or **`npm run watch`** during development.

## CSS Architecture

Source lives in **`assets/css/src/`**:

- **`tokens.css`** — Design tokens (`:root` and `[data-theme="dark"]` custom properties)
- **`main.entry.css`**, **`critical.entry.css`**, **`critical-dark.entry.css`** — Bundle entry points
- **`base/`**, **`components/`**, **`layouts/`**, **`pages/`**, **`themes/`**, **`utilities/`** — Partials (mirrors former Sass layout)
- **`critical/`** — Critical-path styles for first paint

Built artifacts (linked from PHP and the service worker):

- **`assets/css/main.css`**
- **`assets/css/critical.css`**
- **`assets/css/critical-dark.css`**

**`main.css` is intentionally unlayered** so its rules override **`critical.css`** / **`critical-dark.css`** (also unlayered) in document order. Do not wrap the main bundle in `@layer` without also layering critical.

Class names referenced from PHP and JavaScript are listed in **`.projects/css-class-contract.txt`** (regenerate with `node .scripts/generate-class-contract.mjs`).

Dark mode palette: **`--dm-*`** tokens in **`tokens.css`** — see **`.projects/dm-color-tokens.md`**. After editing dark theme rules, run **`node .scripts/apply-dm-tokens.mjs`** only if you introduced new raw hex values.

## JavaScript

- **`assets/js/theme-palette.js`** — WCAG palette (`MusicThemePalette` global)
- **`assets/js/app.js`** — Application source (requires `theme-palette.js` loaded first)
- **`assets/js/app.min.js`** — Production bundle (`theme-palette.js` + `app.js`, minified)

`index.php` loads **`theme-palette.js` then `app.js`** on `*.lndo.site`, and **`app.min.js` only** elsewhere. Do not load `app.js` alone.

## Dependencies

- **lightningcss** — CSS bundling and minification
- **terser** — JavaScript minification
- **nodemon** — File watching for development

## Notes

- After CSS changes, bump **`CACHE_NAME`** in **`sw.js`** so PWA clients pick up new styles.
- **GoDaddy / cPanel uploads:** Browser upload of `app.min.js` often appends `------WebKitFormBoundary…` (check with `curl -s …/app.min.js | grep WebKitFormBoundary` — must be empty). **Fix:** run **`.scripts/package-godaddy-deploy.sh`**, upload **`godaddy-deploy.zip`** to the `music/` folder, **Extract** in File Manager (avoids corrupting JS). Or use **FTP/SFTP binary**. Do not paste minified JS into Code Editor.
- Migration baseline (pre-native CSS hashes) is documented in **`.projects/css-migration-baseline.md`**.
