# Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close high-priority security gaps (theme API auth, CSRF, Discogs secrets, Secure cookies, CORS, forced default-password change) per `docs/superpowers/specs/2026-09-19-security-hardening-design.md`.

**Architecture:** Centralize CSRF, HTTPS cookie flags, and must-change-password in `AuthHelper` (`config/auth_config.php`). Gate mutating API POSTs through small require helpers. Frontend stores CSRF from `auth_status` and sends `X-CSRF-Token` on all POSTs. Discogs key loads from env, then gitignored `api_config.local.php`.

**Tech Stack:** PHP 7.4+ sessions, vanilla JS (`assets/js/app.js` / `demo.js`), JSON APIs under `api/`, npm terser build for minified JS.

## Global Constraints

- Follow approved spec: `docs/superpowers/specs/2026-09-19-security-hardening-design.md`.
- PHP 7.4+ compatible (no typed properties required; match existing style).
- Do not commit unless the user explicitly asks (skip plan “Commit” steps unless requested).
- Demo mode = `$_ENV['DEMO_MODE'] === 'true'` only.
- Detect default password with `password_verify('admin123', ADMIN_PASSWORD_HASH)` — **not** a fixed hash constant (demo reset regenerates bcrypt).
- Never reintroduce a Discogs key into committed `api_config.php`.

## File map

| File | Responsibility |
|------|----------------|
| `config/auth_config.php` | Secure cookie, CSRF, must-change helpers, JSON error exits |
| `config/api_config.php` | Env + local file key resolution |
| `config/api_config.local.php.example` | Example local secrets file |
| `.gitignore` | Ignore `api_config.local.php` |
| `api/music_api.php` | Expose CSRF; gate POSTs; write local API key file |
| `api/theme_api.php` | Auth+CSRF on POST; drop CORS |
| `api/tracklist_api.php` | Drop CORS |
| `api/image_proxy.php` | Drop CORS |
| `assets/js/app.js` | CSRF header helper; must-change UX |
| `assets/js/demo.js` | CSRF on reset_demo / logout |
| `assets/js/*.min.js` | Rebuild via npm |
| `INSTALL.md`, `readme.md` | Document secrets, CSRF, password policy |
| `tests/security_helpers_test.php` | CLI PHP unit checks for AuthHelper (no PHPUnit) |

---

### Task 1: AuthHelper — secure cookie, CSRF, must-change-password

**Files:**
- Modify: `config/auth_config.php`
- Create: `tests/security_helpers_test.php`

**Interfaces:**
- Produces:
  - `AuthHelper::isHttpsRequest(): bool`
  - `AuthHelper::isDemoMode(): bool`
  - `AuthHelper::ensureCsrfToken(): string`
  - `AuthHelper::getCsrfToken(): string`
  - `AuthHelper::validateCsrfToken($token): bool`
  - `AuthHelper::getRequestCsrfToken(): string` (header `HTTP_X_CSRF_TOKEN` then body)
  - `AuthHelper::requireCsrf(): void` (403 JSON exit)
  - `AuthHelper::requireAuthenticated(): void` (401 JSON exit)
  - `AuthHelper::mustChangePassword(): bool`
  - `AuthHelper::isDefaultPasswordInUse(): bool` (`password_verify('admin123', ADMIN_PASSWORD_HASH)`)
  - `AuthHelper::markMustChangePasswordIfNeeded(): void`
  - `AuthHelper::clearMustChangePassword(): void`
  - `AuthHelper::requireAdminAction(): void` (auth + CSRF + !mustChange, 403 if must change)
  - `AuthHelper::jsonExit($httpCode, $message, $extra = []): void`

- [ ] **Step 1: Write CLI test file (expects failures until helpers exist)**

Create `tests/security_helpers_test.php`:

```php
<?php
require_once __DIR__ . '/../config/auth_config.php';

$failures = 0;
function assert_true($cond, $msg) {
    global $failures;
    if (!$cond) {
        echo "FAIL: $msg\n";
        $failures++;
    } else {
        echo "PASS: $msg\n";
    }
}

ensureSessionStarted();
AuthHelper::logout();
unset($_SESSION['csrf_token'], $_SESSION['must_change_password']);

$token = AuthHelper::getCsrfToken();
assert_true(is_string($token) && strlen($token) === 64, 'csrf token is 64 hex chars');
assert_true(AuthHelper::validateCsrfToken($token), 'valid token accepted');
assert_true(!AuthHelper::validateCsrfToken('nope'), 'invalid token rejected');
assert_true(!AuthHelper::validateCsrfToken(''), 'empty token rejected');

assert_true(AuthHelper::isDemoMode() === (isset($_ENV['DEMO_MODE']) && $_ENV['DEMO_MODE'] === 'true'), 'demo mode reads env');

echo $failures === 0 ? "OK\n" : "FAILED $failures\n";
exit($failures === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test (expect fail on missing methods)**

Run: `php tests/security_helpers_test.php`  
Expected: fatal/error until methods exist, or FAIL assertions.

- [ ] **Step 3: Implement helpers in `config/auth_config.php`**

Replace `ensureSessionStarted()` cookie block with HTTPS detection:

```php
function ensureSessionStarted() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = AuthHelper::isHttpsRequest();
        session_set_cookie_params([
            'lifetime' => 10800,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
        AuthHelper::ensureCsrfToken();
    }
}
```

Add to `AuthHelper` (full methods — keep existing authenticate/logout/establishSession):

```php
public static function isHttpsRequest() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

public static function isDemoMode() {
    return isset($_ENV['DEMO_MODE']) && $_ENV['DEMO_MODE'] === 'true';
}

public static function jsonExit($httpCode, $message, $extra = []) {
    http_response_code((int)$httpCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => false,
        'message' => $message
    ], $extra));
    exit;
}

public static function ensureCsrfToken() {
    ensureSessionStarted();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

public static function getCsrfToken() {
    return self::ensureCsrfToken();
}

public static function validateCsrfToken($token) {
    ensureSessionStarted();
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

public static function getRequestCsrfToken() {
    $header = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    $raw = file_get_contents('php://input');
    // Note: callers that already consumed php://input must pass token via header.
    // Prefer header in JS. Fallback POST field:
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    return '';
}

public static function requireCsrf() {
    // Prefer header; also accept token already parsed into $GLOBALS['__request_csrf'] by API
    $token = '';
    if (!empty($GLOBALS['__request_csrf'])) {
        $token = $GLOBALS['__request_csrf'];
    } else {
        $token = self::getRequestCsrfToken();
    }
    if (!self::validateCsrfToken($token)) {
        self::jsonExit(403, 'Invalid CSRF token');
    }
}

public static function requireAuthenticated() {
    if (!self::isAuthenticated()) {
        self::jsonExit(401, 'Authentication required', ['auth_required' => true]);
    }
}

public static function isDefaultPasswordInUse() {
    return password_verify('admin123', ADMIN_PASSWORD_HASH);
}

public static function markMustChangePasswordIfNeeded() {
    ensureSessionStarted();
    if (!self::isDemoMode() && self::isDefaultPasswordInUse()) {
        $_SESSION['must_change_password'] = true;
    } else {
        unset($_SESSION['must_change_password']);
    }
}

public static function mustChangePassword() {
    ensureSessionStarted();
    return !empty($_SESSION['must_change_password']);
}

public static function clearMustChangePassword() {
    ensureSessionStarted();
    unset($_SESSION['must_change_password']);
}

public static function requireAdminAction() {
    self::requireAuthenticated();
    self::requireCsrf();
    if (self::mustChangePassword()) {
        self::jsonExit(403, 'Password change required', ['must_change_password' => true]);
    }
}
```

**Important:** `php://input` can only be read once. In APIs that `json_decode(file_get_contents('php://input'))`, after decoding set:

```php
$GLOBALS['__request_csrf'] = isset($input['csrf_token']) ? $input['csrf_token'] : '';
if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $GLOBALS['__request_csrf'] = $_SERVER['HTTP_X_CSRF_TOKEN'];
}
```

Update `authenticate()` and `establishSession()` to call `markMustChangePasswordIfNeeded()` after successful session establish.

- [ ] **Step 4: Re-run CLI test**

Run: `php tests/security_helpers_test.php`  
Expected: `OK` exit 0.

- [ ] **Step 5: Commit** (only if user asked)

---

### Task 2: Discogs secrets — env + local file

**Files:**
- Modify: `config/api_config.php`
- Create: `config/api_config.local.php.example`
- Modify: `.gitignore`
- Modify: `api/music_api.php` (`setup_config`, `get_setup_status`)

**Interfaces:**
- Consumes: none from Task 1
- Produces: `DISCOGS_API_KEY` from env → local array file → `''`

- [ ] **Step 1: Rewrite `config/api_config.php`**

```php
<?php
/**
 * API Configuration
 * Secrets: environment variables win; otherwise optional gitignored local file.
 */

$localConfig = [];
$localFile = __DIR__ . '/api_config.local.php';
if (is_readable($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$envKey = getenv('DISCOGS_API_KEY');
if ($envKey === false || $envKey === '') {
    $envKey = isset($_ENV['DISCOGS_API_KEY']) ? $_ENV['DISCOGS_API_KEY'] : '';
}
$discogsApiKey = ($envKey !== '')
    ? $envKey
    : (isset($localConfig['DISCOGS_API_KEY']) ? (string)$localConfig['DISCOGS_API_KEY'] : '');

define('DISCOGS_API_KEY', $discogsApiKey);

$userAgent = getenv('DISCOGS_USER_AGENT');
if ($userAgent === false || $userAgent === '') {
    $userAgent = isset($_ENV['DISCOGS_USER_AGENT']) ? $_ENV['DISCOGS_USER_AGENT'] : '';
}
if ($userAgent === '' && !empty($localConfig['DISCOGS_USER_AGENT'])) {
    $userAgent = $localConfig['DISCOGS_USER_AGENT'];
}
if ($userAgent === '') {
    $userAgent = 'MusicCollectionApp/1.0';
}
define('DISCOGS_USER_AGENT', $userAgent);

$currency = getenv('DISCOGS_CURRENCY');
if ($currency === false || $currency === '') {
    $currency = isset($_ENV['DISCOGS_CURRENCY']) ? $_ENV['DISCOGS_CURRENCY'] : '';
}
if ($currency === '' && !empty($localConfig['DISCOGS_CURRENCY'])) {
    $currency = $localConfig['DISCOGS_CURRENCY'];
}
if ($currency === '') {
    $currency = 'USD';
}
define('DISCOGS_CURRENCY', $currency);

$timeout = getenv('API_TIMEOUT');
if ($timeout === false || $timeout === '') {
    $timeout = isset($_ENV['API_TIMEOUT']) ? $_ENV['API_TIMEOUT'] : '';
}
if ($timeout === '' && isset($localConfig['API_TIMEOUT'])) {
    $timeout = $localConfig['API_TIMEOUT'];
}
if ($timeout === '') {
    $timeout = 15;
}
define('API_TIMEOUT', (int)$timeout);
```

- [ ] **Step 2: Add example + gitignore**

`config/api_config.local.php.example`:

```php
<?php
/**
 * Copy to api_config.local.php (gitignored) for local Discogs credentials.
 * Environment variables override these values.
 */
return [
    'DISCOGS_API_KEY' => 'your_discogs_api_key_here',
    // 'DISCOGS_USER_AGENT' => 'MusicCollectionApp/1.0',
    // 'DISCOGS_CURRENCY' => 'USD',
    // 'API_TIMEOUT' => 15,
];
```

Add to `.gitignore`:

```
config/api_config.local.php
```

- [ ] **Step 3: Change `setup_config` to write local file**

Replace preg_replace on `api_config.php` with writing:

```php
$localFile = __DIR__ . '/../config/api_config.local.php';
$payload = "<?php\nreturn [\n    'DISCOGS_API_KEY' => '" . addslashes($discogsApiKey) . "',\n];\n";
if (file_put_contents($localFile, $payload) !== false) {
    $response['success'] = true;
    $response['message'] = 'Discogs API key saved to config/api_config.local.php';
} else {
    $response['message'] = 'Could not write api_config.local.php. Check permissions.';
}
```

Update `get_setup_status` masking logic to treat non-empty `DISCOGS_API_KEY` constant (from env or local) as configured — keep existing env-vs-file messaging, but say local file instead of “config file” when not env.

- [ ] **Step 4: Verify no key in git**

Run: `rg -n "LDAuzCKsNbMEIJuFYifaEEpKvAUESueijgAhhvLO" config/ || true`  
Expected: no matches in committed config.

- [ ] **Step 5: Commit** (only if user asked)

---

### Task 3: music_api.php — CSRF exposure + POST gates + drop CORS

**Files:**
- Modify: `api/music_api.php`

**Interfaces:**
- Consumes: AuthHelper methods from Task 1; local key write from Task 2
- Produces: `auth_status` / `auth_check` include `csrf_token`, `must_change_password`

- [ ] **Step 1: Drop CORS headers** at top of file (remove `Access-Control-Allow-Origin: *` and related Allow-Methods/Headers).

- [ ] **Step 2: After parsing `$input` from JSON**, set CSRF global:

```php
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$GLOBALS['__request_csrf'] = '';
if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $GLOBALS['__request_csrf'] = $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (!empty($input['csrf_token'])) {
    $GLOBALS['__request_csrf'] = $input['csrf_token'];
}
```

(Place this where POST body is currently read; ensure GET paths still work.)

- [ ] **Step 3: Extend auth_status and auth_check**

```php
$response['data'] = [
    'authenticated' => AuthHelper::isAuthenticated(),
    'lockout_remaining' => AuthHelper::getLockoutTimeRemaining(),
    'csrf_token' => AuthHelper::getCsrfToken(),
    'must_change_password' => AuthHelper::mustChangePassword(),
];
```

(Same fields on `auth_check`.)

- [ ] **Step 4: Gate POST actions**

At start of each case (or a switch preamble):

| Action | Gate |
|--------|------|
| `login` | `requireCsrf()` only; on success AuthHelper already marks must-change |
| `webauthn_login_options`, `webauthn_login` | `requireCsrf()`; login success → `markMustChangePasswordIfNeeded()` via `establishSession` |
| `logout` | `requireCsrf()` |
| `reset_password` | `requireAuthenticated()` + `requireCsrf()`; on success `clearMustChangePassword()` (and if still default password after change, re-mark — only clear when new password is not `admin123`) |
| `reset_demo` | demo check then `requireCsrf()` |
| `add`, `update`, `update_raw`, `delete`, `setup_config`, `webauthn_register_options`, `webauthn_register`, `webauthn_delete` | `requireAdminAction()` |
| `webauthn_status` | `requireCsrf()`; if it reveals auth-only info keep auth as today |

Replace existing hand-rolled `if (!AuthHelper::isAuthenticated())` blocks for those admin actions with `requireAdminAction()` to avoid double logic.

For `reset_password` success path after writing new hash: if `password_verify('admin123', $newPasswordHash)` refuse or re-set must-change; otherwise `clearMustChangePassword()`. Prefer reject setting password to `admin123` outside demo:

```php
if ($newPassword === 'admin123' && !AuthHelper::isDemoMode()) {
    $response['message'] = 'Choose a password other than the default.';
    break;
}
```

- [ ] **Step 5: Manual curl smoke (Lando or php built-in)**

```bash
# Unauthenticated theme POST covered in Task 4
CSRF=$(curl -s -c /tmp/mcjar -b /tmp/mcjar 'http://music.lndo.site/api/music_api.php?action=auth_status' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->csrf_token;')
curl -s -c /tmp/mcjar -b /tmp/mcjar -X POST 'http://music.lndo.site/api/music_api.php?action=login' \
  -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d '{"password":"admin123"}'
```

Expected: login works with CSRF; without header → 403.

- [ ] **Step 6: Commit** (only if user asked)

---

### Task 4: theme_api.php + other APIs CORS

**Files:**
- Modify: `api/theme_api.php`
- Modify: `api/tracklist_api.php`
- Modify: `api/image_proxy.php`

- [ ] **Step 1: theme_api.php**

- `require_once` auth_config.
- Remove CORS `*` headers and OPTIONS early exit that exists only for CORS.
- Read JSON body once; set `$GLOBALS['__request_csrf']` like music_api.
- If `REQUEST_METHOD === 'POST'`: `AuthHelper::requireAdminAction();` before any save.
- GETs unchanged.

- [ ] **Step 2: tracklist_api.php + image_proxy.php**

- Remove `Access-Control-Allow-Origin: *` and related Allow-* / OPTIONS if only for CORS.
- Tracklist remains GET-only for mutations; no CSRF required for GET.

- [ ] **Step 3: Verify**

```bash
curl -s -o /dev /null -w '%{http_code}' -X POST 'http://music.lndo.site/api/theme_api.php' \
  -H 'Content-Type: application/json' -d '{"gradient_color_1":"#111111","gradient_color_2":"#222222"}'
```

Expected: `401`  
Also: response headers must not include `Access-Control-Allow-Origin: *`.

- [ ] **Step 4: Commit** (only if user asked)

---

### Task 5: Frontend CSRF + must-change UX

**Files:**
- Modify: `assets/js/app.js`
- Modify: `assets/js/demo.js`
- Rebuild: `assets/js/app.min.js`, `assets/js/demo.min.js`

**Interfaces:**
- Consumes: `csrf_token`, `must_change_password` from auth_status
- Produces: all POSTs send `X-CSRF-Token`

- [ ] **Step 1: Constructor state**

In `MusicCollectionApp` constructor defaults:

```javascript
this.csrfToken = '';
this.mustChangePassword = false;
```

- [ ] **Step 2: Add `apiFetch` helper**

```javascript
async apiFetch(url, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const headers = Object.assign({
    'Content-Type': 'application/json'
  }, options.headers || {});
  if (method !== 'GET' && method !== 'HEAD' && this.csrfToken) {
    headers['X-CSRF-Token'] = this.csrfToken;
  }
  const response = await fetch(url, Object.assign({}, options, { headers }));
  // Optionally refresh CSRF from JSON body if present
  try {
    const clone = response.clone();
    const data = await clone.json();
    if (data && data.data && data.data.csrf_token) {
      this.csrfToken = data.data.csrf_token;
    }
    if (data && data.must_change_password) {
      this.mustChangePassword = true;
    }
    if (data && data.data && typeof data.data.must_change_password === 'boolean') {
      this.mustChangePassword = data.data.must_change_password;
    }
  } catch (e) {
    // non-JSON
  }
  return response;
}
```

Replace mutating `fetch(...)` calls to music_api / theme_api with `this.apiFetch(...)`. Keep GETs as fetch or apiFetch without CSRF requirement.

Also update `fetchWithCache` to attach CSRF when method is POST (if ever used that way).

- [ ] **Step 3: `checkAuthStatus` stores token + must-change**

```javascript
if (data.success) {
  this.isAuthenticated = data.data.authenticated;
  this.csrfToken = data.data.csrf_token || '';
  this.mustChangePassword = !!data.data.must_change_password;
}
// after updateAuthUI:
if (this.mustChangePassword) {
  this.showResetPasswordModal(); // existing modal opener — use real method name in file
}
```

Find the existing reset-password modal show method and call it. Disable add/edit entry points while `mustChangePassword` (server already enforces).

- [ ] **Step 4: `demo.js`**

Pass CSRF: either set `window.app` token on fetch headers for `reset_demo` and `logout`, reading `window.app.csrfToken` if available, or fetch auth_status first.

```javascript
headers: {
  'Content-Type': 'application/json',
  'X-CSRF-Token': (window.app && window.app.csrfToken) ? window.app.csrfToken : ''
}
```

Ensure demo init waits until app has loaded auth_status (or fetch csrf in demo reset handler).

- [ ] **Step 5: Rebuild minified assets**

Run from project root:

```bash
npm run build:js
```

Expected: `app.min.js` / `demo.min.js` updated.

- [ ] **Step 6: Browser smoke**

- Load site → Network: `auth_status` returns `csrf_token`.
- Login with CSRF header present.
- Save theme while logged in → 200.
- Logged out theme POST → 401.

- [ ] **Step 7: Commit** (only if user asked)

---

### Task 6: Docs

**Files:**
- Modify: `INSTALL.md`
- Modify: `readme.md` (CSRF claim ~line 838; secrets / password sections)

- [ ] **Step 1: INSTALL.md** — document:

1. Copy `config/api_config.local.php.example` → `api_config.local.php` or set `DISCOGS_API_KEY`.
2. Default password `admin123` must be changed on first login (non-demo).
3. Rotate any previously committed Discogs token in Discogs developer settings.

- [ ] **Step 2: readme.md** — replace vague CSRF bullet with: session synchronizer token via `X-CSRF-Token` on POSTs; note Secure cookies on HTTPS; note local API config file.

- [ ] **Step 3: Commit** (only if user asked)

---

### Task 7: End-to-end verification checklist

- [ ] **Step 1: Run CLI tests** — `php tests/security_helpers_test.php` → OK
- [ ] **Step 2: Unauthenticated theme POST → 401**
- [ ] **Step 3: Auth POST without CSRF → 403**
- [ ] **Step 4: Full login + theme save + album add with CSRF → success**
- [ ] **Step 5: Non-demo default password → must_change blocks add until reset**
- [ ] **Step 6: Confirm no `Access-Control-Allow-Origin: *` on api responses**
- [ ] **Step 7: Confirm hardcoded Discogs key absent from `config/api_config.php`**
- [ ] **Step 8: Mark plan tasks complete in this file’s checkboxes**

---

## Spec coverage self-check

| Spec item | Task |
|-----------|------|
| Theme POST auth | Task 4 |
| CSRF synchronizer | Tasks 1, 3, 5 |
| Discogs local/env secrets | Task 2 |
| Secure cookie HTTPS/XFP | Task 1 |
| Drop CORS `*` | Tasks 3–4 |
| Forced password change non-demo | Tasks 1, 3, 5 |
| Docs | Task 6 |
| Success criteria | Task 7 |

## Placeholder scan

No TBD/TODO steps; default-password detection explicitly uses `password_verify('admin123', …)` (spec’s fixed-hash approach corrected for demo reset).
