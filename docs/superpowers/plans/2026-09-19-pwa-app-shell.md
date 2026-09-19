# PWA App-Shell Service Worker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a hand-rolled app-shell service worker and make Clear Caches delete Cache Storage plus unregister the SW, without caching HTML/PHP or APIs.

**Architecture:** Root `sw.js` uses Cache Storage `music-shell-v1` for same-origin static shell assets (cache-first). Shared URL rules live in `assets/js/pwa-shell-rules.js` (importScripts from SW; Node tests the same file). `app.js` registers the SW on init and extends `clearAllCaches()`. Docs describe real behavior. `setup.php` already loads `app.min.js` — no separate setup registration.

**Tech Stack:** Vanilla service worker, Cache API, existing `app.js` / `npm run build:js`, Lando at `https://music.lndo.site`.

## Global Constraints

- App-shell static assets only — never cache navigations, `.php`, `api/*`, or `data/*`.
- Cross-origin (Discogs, fonts) — network only; do not put in Cache Storage.
- Cache name prefix: `music-shell-`; current name: `music-shell-v1`.
- Hand-rolled SW only — no Workbox, no GenerateSW.
- Clear Caches: delete all caches → unregister all SWs → existing storage wipe (preserve browserId / notification keys) → hard reload.
- Do not stage or commit `*.json` catalog files.
- After `app.js` changes: `npm run build:js` before commit so `app.min.js` matches.

## File map

| File | Role |
|------|------|
| `assets/js/pwa-shell-rules.js` | Pure URL classifiers (`shouldNetworkOnly`, `isShellAsset`) for SW + tests |
| `sw.js` | Service worker: install/activate/fetch |
| `.testing/pwa-shell-rules.test.mjs` | Node assertions for URL rules |
| `assets/js/app.js` (+ `app.min.js`) | Register SW; extend `clearAllCaches` |
| `readme.md`, `INSTALL.md` | Document SW + Clear Caches |

---

### Task 1: URL rules module + Node tests

**Files:**
- Create: `assets/js/pwa-shell-rules.js`
- Create: `.testing/pwa-shell-rules.test.mjs`
- Test: `.testing/pwa-shell-rules.test.mjs`

**Interfaces:**
- Produces: `globalThis.PwaShellRules.shouldNetworkOnly(url: URL): boolean`
- Produces: `globalThis.PwaShellRules.isShellAsset(url: URL): boolean`
- Consumes: none

- [ ] **Step 1: Write the failing Node test**

Create `.testing/pwa-shell-rules.test.mjs`:

```js
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const code = fs.readFileSync(path.join(root, 'assets/js/pwa-shell-rules.js'), 'utf8');
const sandbox = { self: {}, console };
sandbox.self = sandbox;
vm.runInNewContext(code, sandbox);
const { shouldNetworkOnly, isShellAsset } = sandbox.PwaShellRules;

const origin = 'https://music.lndo.site';

function u(pathname) {
  return new URL(pathname, origin);
}

assert.equal(shouldNetworkOnly(u('/index.php')), true);
assert.equal(shouldNetworkOnly(u('/api/music_api.php?action=albums')), true);
assert.equal(shouldNetworkOnly(u('/data/music_collection.json')), true);
assert.equal(shouldNetworkOnly(u('/setup.php')), true);
assert.equal(shouldNetworkOnly(u('/assets/css/main.css')), false);

assert.equal(isShellAsset(u('/assets/css/main.css')), true);
assert.equal(isShellAsset(u('/assets/js/app.min.js')), true);
assert.equal(isShellAsset(u('/assets/js/app.min.js?v=123')), true);
assert.equal(isShellAsset(u('/favicon.ico')), true);
assert.equal(isShellAsset(u('/android-chrome-192x192.png')), true);
assert.equal(isShellAsset(u('/api/music_api.php')), false);
assert.equal(isShellAsset(u('/index.php')), false);

console.log('pwa-shell-rules: all assertions passed');
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
cd /Users/billkummerow/personal_site && node .testing/pwa-shell-rules.test.mjs
```

Expected: FAIL (missing file or `PwaShellRules` undefined).

- [ ] **Step 3: Implement `assets/js/pwa-shell-rules.js`**

```js
/**
 * PWA shell URL rules — used by sw.js (importScripts) and Node tests.
 * Attach to self/globalThis as PwaShellRules.
 */
(function (root) {
  /**
   * @param {URL} url
   * @return {boolean}
   */
  function shouldNetworkOnly(url) {
    var path = url.pathname || '';
    if (/\.php$/i.test(path)) {
      return true;
    }
    if (path.indexOf('/api/') !== -1 || path.indexOf('/data/') !== -1) {
      return true;
    }
    // Path segment starts with api/ or data/ at root
    if (/^\/api(\/|$)/i.test(path) || /^\/data(\/|$)/i.test(path)) {
      return true;
    }
    return false;
  }

  /**
   * Same-origin shell static assets eligible for cache-first.
   * @param {URL} url
   * @return {boolean}
   */
  function isShellAsset(url) {
    if (shouldNetworkOnly(url)) {
      return false;
    }
    var path = url.pathname || '';
    if (path.indexOf('/assets/') !== -1 || /^\/assets(\/|$)/i.test(path)) {
      return true;
    }
    var shellFiles = [
      '/favicon.ico',
      '/favicon-16x16.png',
      '/favicon-32x32.png',
      '/apple-touch-icon.png',
      '/android-chrome-192x192.png',
      '/android-chrome-512x512.png'
    ];
    for (var i = 0; i < shellFiles.length; i++) {
      if (path === shellFiles[i] || path.endsWith(shellFiles[i])) {
        return true;
      }
    }
    return false;
  }

  root.PwaShellRules = {
    shouldNetworkOnly: shouldNetworkOnly,
    isShellAsset: isShellAsset
  };
})(typeof self !== 'undefined' ? self : globalThis);
```

- [ ] **Step 4: Run test — expect PASS**

```bash
node .testing/pwa-shell-rules.test.mjs
```

Expected: `pwa-shell-rules: all assertions passed`

- [ ] **Step 5: Commit**

```bash
git add assets/js/pwa-shell-rules.js .testing/pwa-shell-rules.test.mjs
git commit -m "$(cat <<'EOF'
Add PWA shell URL rules with Node tests.

EOF
)"
```

Do not add `data/*.json`.

---

### Task 2: Create `sw.js` (install / activate / fetch)

**Files:**
- Create: `sw.js`
- Consumes: `assets/js/pwa-shell-rules.js` via `importScripts`

**Interfaces:**
- Consumes: `PwaShellRules.shouldNetworkOnly`, `PwaShellRules.isShellAsset`
- Produces: Service worker controlling the origin scope; cache `music-shell-v1`

- [ ] **Step 1: Create `sw.js`**

```js
/* Music Collection — app-shell service worker */
importScripts('assets/js/pwa-shell-rules.js');

var CACHE_PREFIX = 'music-shell-';
var CACHE_NAME = 'music-shell-v1';

var PRECACHE_URLS = [
  'assets/css/critical.css',
  'assets/css/critical-dark.css',
  'assets/css/main.css',
  'assets/js/app.min.js',
  'assets/js/demo.min.js',
  'favicon.ico',
  'favicon-16x16.png',
  'favicon-32x32.png',
  'apple-touch-icon.png',
  'android-chrome-192x192.png',
  'android-chrome-512x512.png'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(PRECACHE_URLS);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.map(function (key) {
          if (key.indexOf(CACHE_PREFIX) === 0 && key !== CACHE_NAME) {
            return caches.delete(key);
          }
          return undefined;
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  var url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }

  // Cross-origin: network only
  if (url.origin !== self.location.origin) {
    return;
  }

  // Navigations and network-only paths: do not intercept with cache
  if (request.mode === 'navigate' || PwaShellRules.shouldNetworkOnly(url)) {
    return;
  }

  if (!PwaShellRules.isShellAsset(url)) {
    return;
  }

  event.respondWith(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.match(request).then(function (cached) {
        if (cached) {
          return cached;
        }
        return fetch(request).then(function (response) {
          if (response && response.ok && response.type === 'basic') {
            cache.put(request, response.clone());
          }
          return response;
        });
      });
    })
  );
});
```

Note: `cache.match(request)` with query strings may miss precached bare URLs; runtime `put` after first network hit covers `?v=` assets. That is acceptable.

- [ ] **Step 2: Verify `sw.js` is served**

With Lando running:

```bash
curl -sS -o /dev/null -w "%{http_code}" https://music.lndo.site/sw.js
curl -sS https://music.lndo.site/sw.js | head -n 5
```

Expected: HTTP `200`; first lines show the comment / `importScripts`.

- [ ] **Step 3: Commit**

```bash
git add sw.js
git commit -m "$(cat <<'EOF'
Add app-shell service worker with versioned Cache Storage.

EOF
)"
```

---

### Task 3: Register SW + Clear Caches unregister

**Files:**
- Modify: `assets/js/app.js` (init + `clearAllCaches`)
- Modify: `assets/js/app.min.js` (via build)
- Test: browser / DevTools checklist below

**Interfaces:**
- Consumes: `navigator.serviceWorker.register('sw.js')`
- Produces: Active SW after load; Clear Caches unregisters SW

- [ ] **Step 1: Add `registerServiceWorker` method**

Near other init helpers in `app.js`, add:

```js
  /**
   * Register the app-shell service worker when supported.
   * Failures are non-fatal — the app works without a SW.
   */
  registerServiceWorker() {
      if (!('serviceWorker' in navigator)) {
          return;
      }
      navigator.serviceWorker.register('sw.js').catch((error) => {
          console.error('Service worker registration failed:', error);
      });
  }
```

- [ ] **Step 2: Call it from `init()`**

At the end of `async init()` (after existing setup), add:

```js
      this.registerServiceWorker();
```

- [ ] **Step 3: Extend `clearAllCaches()`**

In `clearAllCaches()`, after deleting Cache API entries (existing block), **before** localStorage wipe, add:

```js
          // Unregister service workers so a fresh SW can install after reload
          if ('serviceWorker' in navigator) {
              const registrations = await navigator.serviceWorker.getRegistrations();
              await Promise.all(
                  registrations.map((registration) => registration.unregister())
              );
          }
```

Keep the rest of the method unchanged (storage wipe with preserved keys, in-memory clears, hard reload).

- [ ] **Step 4: Rebuild minified JS**

```bash
npm run build:js
```

Expected: exit 0; `app.min.js` updated.

- [ ] **Step 5: Browser verification (Lando)**

1. Open `https://music.lndo.site/` (hard refresh once).
2. DevTools → Application → Service Workers: `sw.js` registered.
3. Cache Storage: `music-shell-v1` present with shell assets (may fill after install).
4. Network: reload `api/music_api.php?action=albums&…` — not served from SW cache (document/xhr from network).
5. As admin: Settings → Clear Caches → after reload, Service Workers empty (or unregistered) and shell cache gone/empty until SW reinstalls on next load.

- [ ] **Step 6: Commit**

```bash
git add assets/js/app.js assets/js/app.min.js
git commit -m "$(cat <<'EOF'
Register app-shell service worker and unregister it from Clear Caches.

EOF
)"
```

---

### Task 4: Documentation

**Files:**
- Modify: `readme.md` (Cache Management ~569–588)
- Modify: `INSTALL.md` (short note near collection / ops)

**Interfaces:**
- Consumes: Behavior from Tasks 2–3
- Produces: Accurate Clear Caches / SW docs

- [ ] **Step 1: Update `readme.md` Cache Management**

Replace the bullet list under “The system will:” so it states:

- A service worker (`sw.js`) caches **app-shell** static assets (CSS, JS, icons) in Cache Storage (`music-shell-v1`)
- Clear Caches **deletes** Cache Storage entries and **unregisters** service workers
- Clears localStorage/sessionStorage (theme prefs, etc.), preserving browserId / notification tracking as implemented
- Resets in-memory selection state and hard-reloads with cache-bust params
- Album list / Discogs / API responses are **not** cached by the service worker

Keep “When to use Clear Caches” guidance; adjust any line that implies SW caches already existed without describing shell-only scope.

- [ ] **Step 2: Update `INSTALL.md`**

Add a short subsection after Collection list paging (or under a PWA/cache heading):

```markdown
### PWA app shell

The site registers `sw.js` to cache static shell assets (CSS/JS/icons). HTML, PHP, and `api/` responses are always fetched from the network. **Clear Caches** (admin menu) deletes Cache Storage and unregisters the service worker, then reloads.
```

- [ ] **Step 3: Grep verification**

```bash
rg -n 'sw\.js|music-shell|unregister' readme.md INSTALL.md
rg -n 'service workers, PWA caches' readme.md || true
```

Expected: new wording present; vague-only claim without shell explanation gone or replaced.

- [ ] **Step 4: Commit**

```bash
git add readme.md INSTALL.md
git commit -m "$(cat <<'EOF'
Document app-shell service worker and Clear Caches behavior.

EOF
)"
```

Also add the plan file if not yet committed:

```bash
git add docs/superpowers/plans/2026-09-19-pwa-app-shell.md
git commit -m "$(cat <<'EOF'
Add implementation plan for PWA app-shell service worker.

EOF
)"
```

(Only if this plan file is still untracked at the end.)

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Hand-rolled `sw.js` at root | Task 2 |
| Cache `music-shell-v1` / prefix cleanup | Task 2 |
| Precache CSS/JS/icons list | Task 2 |
| Network-only navigations / php / api / data | Task 1 + 2 |
| Cache-first shell assets | Task 2 |
| No cross-origin cache | Task 2 |
| Register from app.js | Task 3 (`setup.php` already loads app.min.js) |
| Clear Caches unregister + delete caches | Task 3 |
| Docs readme + INSTALL | Task 4 |
| Verification Lando / DevTools | Task 2–3 steps |
| No Workbox / no API offline | Global Constraints |
