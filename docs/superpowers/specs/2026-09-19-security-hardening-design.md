# Security Hardening Design

**Date:** 2026-09-19  
**Status:** Approved (pending implementation)  
**Scope:** High-priority security fixes for Music Collection Manager (`personal_site`)

## Problem

Several security gaps weaken the “secure & private” story:

1. `api/theme_api.php` POSTs are unauthenticated — anyone can change theme, display, and app settings.
2. CSRF protection is claimed in docs but not implemented.
3. A Discogs API key is hardcoded in committed `config/api_config.php`.
4. Session cookies use `secure => false` even on HTTPS.
5. APIs send `Access-Control-Allow-Origin: *`.
6. Default password `admin123` remains usable on non-demo installs with no forced change.

## Goals

- Close the gaps above without changing core collection UX.
- Keep public browse and public theme/settings GETs working.
- Keep Railway demo usable (`DEMO_MODE`), including reset to `admin123`.
- Prefer small, central helpers over scattered one-off checks.

## Non-goals

- Multi-user accounts / roles
- Moving password storage off PHP config files (reset may still rewrite `auth_config.php`)
- Inline lyrics, Discogs sync, CSRF on GET
- Full CSP / XSS audit

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Discogs secrets | **B** — env first, then gitignored `config/api_config.local.php`; no key in committed config |
| CSRF | **A** — synchronizer token in session; send on mutating requests |
| Default password | **B** — force change after login with default password when not `DEMO_MODE` |
| Secure cookie | Auto when HTTPS or `X-Forwarded-Proto: https` |
| CORS | Remove wildcard CORS headers; same-origin only |

---

## Design

### 1. Shared auth / CSRF helpers (`config/auth_config.php`)

Extend existing `AuthHelper` / session helpers:

#### Secure session cookie

In `ensureSessionStarted()`:

- Detect HTTPS: `(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')` **or** `X-Forwarded-Proto === 'https'`.
- Set `session_set_cookie_params([..., 'secure' => $isHttps, ...])` before `session_start()`.

#### CSRF synchronizer token

- `AuthHelper::ensureCsrfToken()`: if `$_SESSION['csrf_token']` missing, set to `bin2hex(random_bytes(32))`.
- `AuthHelper::getCsrfToken()`: ensure + return token.
- `AuthHelper::validateCsrfToken($token)`: `hash_equals` against session token; empty/missing fails.
- `AuthHelper::requireCsrf()`: read token from `HTTP_X_CSRF_TOKEN` header first, else JSON/body/`$_POST['csrf_token']`; on failure respond `403` JSON `{ success: false, message: 'Invalid CSRF token' }` and exit.

Call `ensureCsrfToken()` whenever a session starts so anonymous page loads get a token before login.

#### Must-change-password

- Define a constant for the known default password hash currently shipping in repo (same value as today’s `ADMIN_PASSWORD_HASH` for `admin123`), e.g. `DEFAULT_ADMIN_PASSWORD_HASH`.
- After successful password auth: if `ADMIN_PASSWORD_HASH === DEFAULT_ADMIN_PASSWORD_HASH` (or `hash_equals`) **and** `DEMO_MODE` is not true, set `$_SESSION['must_change_password'] = true`.
- WebAuthn login: if current hash is still default and not demo, also set the flag (passkey alone must not bypass forced change).
- `AuthHelper::mustChangePassword()`: true when flag set.
- `AuthHelper::clearMustChangePassword()`: after successful `reset_password`.
- `AuthHelper::requireAuthenticated()`: 401 if not authenticated.
- `AuthHelper::requireAdminAction()`: require authenticated + CSRF + not `must_change_password` (except the `reset_password` action itself).

Demo: when `DEMO_MODE=true` (env or defined), never set / never enforce `must_change_password`.

### 2. Theme API (`api/theme_api.php`)

- Require `auth_config.php`.
- **GET:** unchanged (public read of settings).
- **POST:** `AuthHelper::requireAdminAction()` (auth + CSRF + password-change gate).
- Remove `Access-Control-Allow-Origin: *` and related CORS / OPTIONS short-circuit (or keep OPTIONS only if needed with no `*`).

### 3. Music API (`api/music_api.php`)

- Remove wildcard CORS headers.
- Include CSRF token in `auth_status` / `auth_check` JSON payloads: `csrf_token`, and `must_change_password` boolean.
- **POST actions that establish or probe auth without full admin gate:**
  - `login`, `webauthn_login_options`, `webauthn_login`: require CSRF only (session may be anonymous).
  - `logout`: require CSRF; auth optional.
  - `reset_password`: require authenticated + CSRF; **allow** when `must_change_password` is true; clear flag on success.
  - `reset_demo`: keep demo-only behavior; require CSRF when `DEMO_MODE` (still unauthenticated otherwise as today for shared demo — intentional).
- **All other mutating POSTs** (`add`, `update`, `update_raw`, `delete`, `setup_config`, WebAuthn register/delete, etc.): `requireAdminAction()`.

GETs remain public where they are today (`albums`, Discogs search, etc.).

### 4. Other APIs

- `api/tracklist_api.php`, `api/image_proxy.php`: remove `Access-Control-Allow-Origin: *`. Tracklist POST (if any) should use same CSRF + auth rules if it mutates; if tracklist is read-only GET, no CSRF needed.

### 5. Discogs secrets (option B)

**Committed `config/api_config.php`:** resolve the key into a variable, then `define` once (PHP constants cannot be redefined):

```php
$discogsApiKey = getenv('DISCOGS_API_KEY') ?: ($_ENV['DISCOGS_API_KEY'] ?? '');
$local = __DIR__ . '/api_config.local.php';
if ($discogsApiKey === '' && is_readable($local)) {
    $localConfig = require $local; // returns ['DISCOGS_API_KEY' => '...', ...]
    if (is_array($localConfig) && !empty($localConfig['DISCOGS_API_KEY'])) {
        $discogsApiKey = $localConfig['DISCOGS_API_KEY'];
    }
}
define('DISCOGS_API_KEY', $discogsApiKey);
// Same pattern for optional non-secret overrides (user agent, currency, timeout) if present in local file.
```

Load order:

1. Non-empty env `DISCOGS_API_KEY` wins.
2. Else non-empty value from `api_config.local.php` return array.
3. Else empty string (Discogs features degrade gracefully as today when unavailable).

Do **not** leave the previous hardcoded key in git.

**Add:**

- `config/api_config.local.php.example` — returns a PHP array with `DISCOGS_API_KEY` (and optional overrides); copy to `api_config.local.php`.
- `.gitignore` entry: `config/api_config.local.php`.

**Setup UI:** when saving API key from setup (non-env), write/update `api_config.local.php` as a returned array file instead of embedding the key in committed `api_config.php`. If env already supplies the key, keep current “managed by environment” behavior.

**Docs:** `INSTALL.md` / relevant `readme.md` sections — env vs local file; note that any previously committed key should be rotated in Discogs.

### 6. Frontend (`assets/js/app.js`)

- After `checkAuthStatus()`, store `this.csrfToken` and `this.mustChangePassword` from response.
- Centralize mutating fetches (extend `fetchWithCache` or add `fetchApi`) to attach header `X-CSRF-Token: this.csrfToken` on POST/PUT/DELETE.
- Refresh CSRF from auth responses when present.
- If `mustChangePassword`, force open reset-password modal and block add/edit/delete/setup navigation until cleared (mirror server gate).
- Rebuild minified JS via existing `npm run build:js` as part of delivery.

### 7. Documentation

- Update CSRF claim in `readme.md` to match real behavior (token + header).
- Note Secure cookie behavior and local Discogs config file.
- Do not claim CSRF if any exempt path remains undocumented — document `reset_demo` CSRF requirement explicitly.

---

## Request / response sketches

### Auth status (GET)

```json
{
  "success": true,
  "data": {
    "authenticated": false,
    "csrf_token": "…",
    "must_change_password": false
  }
}
```

### Rejected theme POST (unauthenticated)

`401` `{ "success": false, "message": "Authentication required" }`

### Rejected POST (bad CSRF)

`403` `{ "success": false, "message": "Invalid CSRF token" }`

### Rejected admin action (must change password)

`403` `{ "success": false, "message": "Password change required" }`

---

## File touch list

| File | Change |
|------|--------|
| `config/auth_config.php` | Secure cookie, CSRF helpers, must-change-password |
| `config/api_config.php` | Remove hardcoded key; load local override |
| `config/api_config.local.php.example` | New |
| `.gitignore` | Ignore `api_config.local.php` |
| `api/theme_api.php` | Auth + CSRF on POST; drop CORS |
| `api/music_api.php` | CSRF/auth gates; expose tokens; drop CORS; setup writes local file |
| `api/tracklist_api.php` | Drop CORS; CSRF if POST mutates |
| `api/image_proxy.php` | Drop CORS |
| `assets/js/app.js` | CSRF header; must-change UX |
| `assets/js/app.min.js` | Rebuild |
| `INSTALL.md` / `readme.md` | Secrets + CSRF + password policy |
| `setup.php` | Only if API-key save path needs UI copy tweaks |

---

## Testing / success criteria

1. Unauthenticated POST to `theme_api.php` → 401.
2. Authenticated POST without CSRF → 403.
3. Authenticated POST with valid CSRF → settings save works.
4. Login POST without CSRF → 403; with token from prior `auth_status` → success.
5. Non-demo install with default password: after login, mutations fail until password reset; then succeed.
6. `DEMO_MODE=true`: no forced password change; demo reset still works (with CSRF).
7. No Discogs key string in committed tree; local override / env still enables Discogs.
8. On HTTPS (or Railway forwarded HTTPS), session cookie has `Secure`.
9. Response headers from APIs no longer include `Access-Control-Allow-Origin: *`.

## Rollback

Revert the branch / commits for this change set. Local `api_config.local.php` remains on disk if created and is gitignored.

## Open notes

- Treat the previously committed Discogs token as compromised; rotate in Discogs dashboard after deploy.
- Password hash may still live in `auth_config.php`; forced change reduces default-credential risk on fresh clones that keep the shipped hash.
