# Catalog Backup / Restore Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an authenticated admin download a dated ZIP backup of `music_collection.json` (optionally with `settings.json`) and restore from ZIP or catalog JSON, replacing the catalog always and settings only when opted in.

**Architecture:** New `CatalogBackupService` builds/extracts ZIPs via PHP `ZipArchive`, validates JSON, writes timestamped `.bak` copies, then atomically replaces files under `data/`. `music_api.php` exposes `backup_download` (POST → ZIP stream) and `backup_restore` (multipart). Setup gains a **Backup** tab; `app.js` handles blob download and FormData upload with CSRF.

**Tech Stack:** PHP 7.4+ with ZipArchive, vanilla JS on setup.php / app.js, `AuthHelper::requireAdminAction()`.

## Global Constraints

- Follow approved spec: `docs/superpowers/specs/2026-09-19-catalog-backup-design.md`.
- Catalog restore always full-replaces `data/music_collection.json`.
- Settings restore only when client sends `restore_settings=1` **and** backup contains `settings.json`.
- Never put secrets (`api_config.local.php`, `auth_config.php`) in the ZIP.
- All mutating backup endpoints: `AuthHelper::requireAdminAction()`.
- If `ZipArchive` is missing: fail with a clear message (no silent JSON-only fallback).
- PHP 7.4+; 2-space indent in touched PHP/JS.
- Do not commit unless the user explicitly asks.
- Do not commit `data/music_collection.json` / secrets; do not reintroduce a `tests/` directory unless the user asks (verify with `php -r` and curl).
- Work on current branch `security/hardening-20260919` (or whatever branch is checked out).

## File map

| File | Responsibility |
|------|----------------|
| `services/CatalogBackupService.php` | Validate catalog/settings JSON; build ZIP bytes; parse upload; bak + atomic write |
| `api/music_api.php` | `backup_download`, `backup_restore` |
| `setup.php` | Backup tab after Discogs Export |
| `assets/js/app.js` (+ min) | Download blob + restore FormData |
| `INSTALL.md` / `readme.md` | Document Backup tab |
| `.gitignore` | Ignore `*.bak.*` / `data/*.bak*` patterns beyond existing `*.bak` |

---

### Task 1: CatalogBackupService

**Files:**
- Create: `services/CatalogBackupService.php`
- Modify: `.gitignore` (bak patterns)

**Interfaces:**
- Produces:
  - `CatalogBackupService::dataDir(): string` → absolute path to `data/`
  - `validateCatalogJson($jsonString): array` → `['ok'=>bool,'error'=>?string,'data'=>?array,'album_count'=>int]`
  - `validateSettingsJson($jsonString): array` → `['ok'=>bool,'error'=>?string,'data'=>?array]`
  - `buildZip($includeSettings): array` → `['ok'=>bool,'error'=>?string,'filename'=>string,'bytes'=>string]`
  - `restoreFromUpload($tmpPath, $originalName, $restoreSettings): array` → summary with `album_count`, `settings_restored`, `bak_files`

- [ ] **Step 1: Implement service**

```php
<?php
/**
 * Build and restore local catalog backups (ZIP / JSON).
 */
class CatalogBackupService {
    const MAX_UPLOAD_BYTES = 52428800; // 50 MB

    public static function dataDir() {
        return realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data');
    }

    public static function catalogPath() {
        return self::dataDir() . '/music_collection.json';
    }

    public static function settingsPath() {
        return self::dataDir() . '/settings.json';
    }

    /**
     * @param string $jsonString
     * @return array{ok:bool,error:?string,data:?array,album_count:int}
     */
    public static function validateCatalogJson($jsonString) {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Catalog JSON is invalid', 'data' => null, 'album_count' => 0];
        }
        if (!array_key_exists('albums', $data) || (!is_array($data['albums']))) {
            return ['ok' => false, 'error' => 'Catalog JSON must contain an albums object or array', 'data' => null, 'album_count' => 0];
        }
        return [
            'ok' => true,
            'error' => null,
            'data' => $data,
            'album_count' => count($data['albums']),
        ];
    }

    /**
     * @param string $jsonString
     * @return array{ok:bool,error:?string,data:?array}
     */
    public static function validateSettingsJson($jsonString) {
        $data = json_decode($jsonString, true);
        if (!is_array($data) || $data === null) {
            return ['ok' => false, 'error' => 'Settings JSON is invalid', 'data' => null];
        }
        // json_decode of "null" yields null; empty object {} is array in PHP with json_decode true? {} → []
        // Accept any array (object or list-shaped); reject scalars.
        return ['ok' => true, 'error' => null, 'data' => $data];
    }

    /**
     * @param bool $includeSettings
     * @return array{ok:bool,error:?string,filename:string,bytes:string}
     */
    public static function buildZip($includeSettings) {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'PHP ZipArchive extension is required for backups', 'filename' => '', 'bytes' => ''];
        }
        $catalogFile = self::catalogPath();
        if (!is_readable($catalogFile)) {
            return ['ok' => false, 'error' => 'music_collection.json is not readable', 'filename' => '', 'bytes' => ''];
        }
        $stamp = date('Ymd-His');
        $filename = 'music-backup-' . $stamp . '.zip';
        $tmpZip = tempnam(sys_get_temp_dir(), 'mcbackup');
        if ($tmpZip === false) {
            return ['ok' => false, 'error' => 'Could not create temp file', 'filename' => '', 'bytes' => ''];
        }
        @unlink($tmpZip);
        $tmpZip .= '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
            return ['ok' => false, 'error' => 'Could not create ZIP', 'filename' => '', 'bytes' => ''];
        }
        $zip->addFile($catalogFile, 'music_collection.json');
        $includesSettings = false;
        if ($includeSettings) {
            $settingsFile = self::settingsPath();
            if (is_readable($settingsFile)) {
                $zip->addFile($settingsFile, 'settings.json');
                $includesSettings = true;
            }
        }
        $meta = json_encode([
            'created_at' => date('c'),
            'app' => 'MusicCollection',
            'includes_settings' => $includesSettings,
        ]);
        $zip->addFromString('backup-meta.json', $meta);
        $zip->close();
        $bytes = file_get_contents($tmpZip);
        @unlink($tmpZip);
        if ($bytes === false || $bytes === '') {
            return ['ok' => false, 'error' => 'Failed to read ZIP bytes', 'filename' => '', 'bytes' => ''];
        }
        return ['ok' => true, 'error' => null, 'filename' => $filename, 'bytes' => $bytes];
    }

    /**
     * @param string $tmpPath Uploaded temp path
     * @param string $originalName Client filename
     * @param bool $restoreSettings
     * @return array{ok:bool,error:?string,album_count:int,settings_restored:bool,bak_files:array}
     */
    public static function restoreFromUpload($tmpPath, $originalName, $restoreSettings) {
        // Implement: size check; detect zip vs json by extension/MIME;
        // extract catalog (+ optional settings); validate; bak current files;
        // write temp then rename to catalogPath/settingsPath; return summary.
        // On validation failure before write: ok=false, leave files unchanged.
    }
}
```

Fill in `restoreFromUpload` completely (no stub). Use `copy($path, $path . '.bak.' . date('YmdHis'))` before overwrite. Write via `file_put_contents($path . '.tmp', $json)` then `rename`.

For ZIP extract: open upload with ZipArchive; `getFromName('music_collection.json')` required; `getFromName('settings.json')` optional.

- [ ] **Step 2: Update `.gitignore`**

Add:

```
*.bak.*
data/*.bak*
```

(Keep existing `*.bak`.)

- [ ] **Step 3: Verify with php -r**

```bash
php -r '
require "services/CatalogBackupService.php";
$bad = CatalogBackupService::validateCatalogJson("{not json");
assert($bad["ok"]===false);
$good = CatalogBackupService::validateCatalogJson(file_get_contents("data/music_collection.json"));
assert($good["ok"]===true && $good["album_count"]>0);
$z = CatalogBackupService::buildZip(true);
assert($z["ok"]===true && strlen($z["bytes"])>100);
echo "OK album_count=".$good["album_count"]." zip_bytes=".strlen($z["bytes"])."\n";
'
```

Expected: `OK album_count=… zip_bytes=…`

- [ ] **Step 4: Commit** (only if user asked)

---

### Task 2: music_api backup actions

**Files:**
- Modify: `api/music_api.php`

**Interfaces:**
- Consumes: `CatalogBackupService::*`
- Produces:
  - `POST action=backup_download` body `{ include_settings: bool }` → ZIP binary (`Content-Type: application/zip`, `Content-Disposition: attachment; filename="…"`)
  - `POST action=backup_restore` multipart `backup_file` + `restore_settings` → JSON success summary

- [ ] **Step 1: Require service**

Near other service requires:

```php
require_once __DIR__ . '/../services/CatalogBackupService.php';
```

- [ ] **Step 2: `backup_download`**

Inside POST switch:

```php
case 'backup_download':
    AuthHelper::requireAdminAction();
    $includeSettings = !empty($input['include_settings']);
    $built = CatalogBackupService::buildZip($includeSettings);
    if (empty($built['ok'])) {
        $response['message'] = $built['error'] ?: 'Could not build backup';
        break;
    }
    // Exit with raw ZIP (do not wrap in JSON)
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $built['filename'] . '"');
    header('Content-Length: ' . strlen($built['bytes']));
    header('Cache-Control: no-store');
    echo $built['bytes'];
    exit;
```

Note: `$input` is already parsed from JSON body for other POSTs; ensure this case still works when Content-Type is application/json.

- [ ] **Step 3: `backup_restore`**

```php
case 'backup_restore':
    AuthHelper::requireAdminAction();
    if (empty($_FILES['backup_file']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
        $response['message'] = 'Backup file is required';
        break;
    }
    if (!empty($_FILES['backup_file']['error'])) {
        $response['message'] = 'Upload failed';
        break;
    }
    $restoreSettings = false;
    if (isset($_POST['restore_settings'])) {
        $restoreSettings = $_POST['restore_settings'] === '1' || $_POST['restore_settings'] === 'true';
    } elseif (isset($input['restore_settings'])) {
        $restoreSettings = !empty($input['restore_settings']);
    }
    $result = CatalogBackupService::restoreFromUpload(
        $_FILES['backup_file']['tmp_name'],
        isset($_FILES['backup_file']['name']) ? $_FILES['backup_file']['name'] : '',
        $restoreSettings
    );
    if (empty($result['ok'])) {
        $response['message'] = $result['error'] ?: 'Restore failed';
        break;
    }
    $response['success'] = true;
    $response['message'] = 'Backup restored';
    $response['data'] = [
        'album_count' => $result['album_count'],
        'settings_restored' => !empty($result['settings_restored']),
        'bak_files' => $result['bak_files'],
    ];
    break;
```

Ensure multipart POSTs still populate CSRF: header `X-CSRF-Token` from `apiFetch` FormData path (do not require CSRF only inside JSON body). Existing `AuthHelper` already reads the header.

- [ ] **Step 4: Auth smoke**

```bash
curl -sk -o /tmp/b401.txt -w "%{http_code}" -X POST \
  'https://music.lndo.site/api/music_api.php?action=backup_download' \
  -H 'Content-Type: application/json' -d '{"include_settings":true}'
echo; cat /tmp/b401.txt
```

Expected: `401` and Authentication required.

- [ ] **Step 5: Commit** (only if user asked)

---

### Task 3: Setup Backup tab + JS

**Files:**
- Modify: `setup.php` — tab after Discogs Export
- Modify: `assets/js/app.js`
- Rebuild: `npm run build:js`

**UI IDs (exact):**

- Tab `data-tab="backup"` / panel `#backup`
- `#backupIncludeSettings` (checked)
- `#backupDownloadBtn` — “Download backup”
- `#backupRestoreFile` accept `.zip,.json`
- `#backupRestoreSettings` (unchecked)
- `#backupRestoreBtn` — “Restore backup”
- `#backupMessage`

Use `checkbox-group` / `checkbox-option` / `span.checkbox-label` (not bare form-group checkbox).

- [ ] **Step 1: Markup in setup.php**
- [ ] **Step 2: JS**

Wire in `setupPageEventListeners` / setup init:

```javascript
setupBackupFunctionality() {
  const downloadBtn = document.getElementById('backupDownloadBtn');
  if (downloadBtn) {
    downloadBtn.addEventListener('click', () => this.handleBackupDownloadClick());
  }
  const restoreBtn = document.getElementById('backupRestoreBtn');
  if (restoreBtn) {
    restoreBtn.addEventListener('click', () => this.handleBackupRestoreClick());
  }
}

async handleBackupDownloadClick() {
  const include = document.getElementById('backupIncludeSettings');
  const includeSettings = include ? include.checked : true;
  const response = await this.apiFetch('api/music_api.php?action=backup_download', {
    method: 'POST',
    body: JSON.stringify({ include_settings: includeSettings }),
  });
  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(data.message || 'Download failed');
  }
  const blob = await response.blob();
  const cd = response.headers.get('Content-Disposition') || '';
  const match = /filename="([^"]+)"/.exec(cd);
  const name = match ? match[1] : 'music-backup.zip';
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

async handleBackupRestoreClick() {
  const fileInput = document.getElementById('backupRestoreFile');
  const settingsCb = document.getElementById('backupRestoreSettings');
  if (!fileInput || !fileInput.files || !fileInput.files[0]) {
    this.showBackupMessage('Choose a backup file first.', 'error');
    return;
  }
  const confirmed = window.confirm(
    'This will replace your local music catalog with the backup. Settings are restored only if you checked that option and the backup contains settings. Continue?'
  );
  if (!confirmed) return;
  const form = new FormData();
  form.append('backup_file', fileInput.files[0]);
  form.append('restore_settings', settingsCb && settingsCb.checked ? '1' : '0');
  const response = await this.apiFetch('api/music_api.php?action=backup_restore', {
    method: 'POST',
    body: form,
    headers: {}, // let browser set multipart boundary; apiFetch must not force application/json for FormData
  });
  // Ensure apiFetch skips Content-Type: application/json when body is FormData
  ...
}
```

**Important:** Adjust `apiFetch` so when `body instanceof FormData`, it does **not** set `Content-Type: application/json` (browser sets multipart boundary). Still attach `X-CSRF-Token`.

On restore success: show summary; if `document.getElementById('albumGrid')` call `loadAlbums()`; if settings restored and setup theme loaders exist, reload them.

- [ ] **Step 3: `npm run build:js`** — exit 0
- [ ] **Step 4: Browser smoke** — open Backup tab; download ZIP; confirm it opens and contains `music_collection.json`
- [ ] **Step 5: Commit** (only if user asked)

---

### Task 4: Docs + verification checklist

**Files:**
- Modify: `INSTALL.md`, `readme.md`

- [ ] **Step 1: Document** Setup → Backup; ZIP contents; restore replace rules; ZipArchive requirement; does not touch Discogs or API secrets.

- [ ] **Step 2: Checklist**

1. Unauthenticated `backup_download` → 401  
2. `php -r` validate + buildZip OK  
3. Download ZIP (authenticated) contains catalog (± settings)  
4. Restore without settings checkbox leaves settings mtime/content unchanged  
5. Invalid upload rejected; catalog unchanged  

- [ ] **Step 3: Commit** (only if user asked)

---

## Spec coverage self-check

| Spec item | Task |
|-----------|------|
| Setup Backup tab | 3 |
| ZIP download + optional settings | 1, 2, 3 |
| Restore ZIP/JSON; catalog replace | 1, 2 |
| Settings only if checked + present | 1, 2, 3 |
| `.bak` before overwrite | 1 |
| requireAdminAction / CSRF | 2, 3 |
| No secrets in ZIP | 1 |
| ZipArchive hard fail | 1 |
| Docs | 4 |

## Placeholder scan

No TBD; `restoreFromUpload` must be fully implemented in Task 1 (plan shows signature + behavior, implementer writes full body).
