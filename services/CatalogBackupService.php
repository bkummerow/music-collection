<?php
/**
 * Build and restore local catalog backups (ZIP / JSON).
 */
class CatalogBackupService {
  const MAX_UPLOAD_BYTES = 52428800; // 50 MB

  /**
   * Absolute path to the data directory.
   *
   * @return string
   */
  public static function dataDir() {
    return realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data');
  }

  /**
   * @return string
   */
  public static function catalogPath() {
    return self::dataDir() . '/music_collection.json';
  }

  /**
   * @return string
   */
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
    $fail = function ($error) {
      return [
        'ok' => false,
        'error' => $error,
        'album_count' => 0,
        'settings_restored' => false,
        'bak_files' => [],
      ];
    };

    if (!is_readable($tmpPath)) {
      return $fail('Upload file is not readable');
    }

    $size = filesize($tmpPath);
    if ($size === false || $size < 1) {
      return $fail('Upload file is empty');
    }
    if ($size > self::MAX_UPLOAD_BYTES) {
      return $fail('Upload exceeds maximum size (50 MB)');
    }

    $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = self::detectMimeType($tmpPath);
    $isZip = ($ext === 'zip')
      || ($mime === 'application/zip')
      || ($mime === 'application/x-zip-compressed');
    $isJson = ($ext === 'json')
      || ($mime === 'application/json')
      || ($mime === 'text/plain');

    $catalogJson = null;
    $settingsJson = null;
    $fromZip = false;

    if ($isZip && !$isJson) {
      if (!class_exists('ZipArchive')) {
        return $fail('PHP ZipArchive extension is required for ZIP restore');
      }
      $zip = new ZipArchive();
      if ($zip->open($tmpPath) !== true) {
        return $fail('Could not open ZIP file');
      }
      $catalogJson = $zip->getFromName('music_collection.json');
      $settingsRaw = $zip->getFromName('settings.json');
      $zip->close();
      if ($catalogJson === false) {
        return $fail('ZIP must contain music_collection.json');
      }
      $fromZip = true;
      if ($settingsRaw !== false) {
        $settingsJson = $settingsRaw;
      }
    } elseif ($isJson && !$isZip) {
      $catalogJson = file_get_contents($tmpPath);
      if ($catalogJson === false) {
        return $fail('Could not read catalog JSON');
      }
    } elseif ($isZip) {
      if (!class_exists('ZipArchive')) {
        return $fail('PHP ZipArchive extension is required for ZIP restore');
      }
      $zip = new ZipArchive();
      if ($zip->open($tmpPath) !== true) {
        return $fail('Could not open ZIP file');
      }
      $catalogJson = $zip->getFromName('music_collection.json');
      $settingsRaw = $zip->getFromName('settings.json');
      $zip->close();
      if ($catalogJson === false) {
        return $fail('ZIP must contain music_collection.json');
      }
      $fromZip = true;
      if ($settingsRaw !== false) {
        $settingsJson = $settingsRaw;
      }
    } else {
      return $fail('Unsupported backup file type; use .zip or .json');
    }

    $catalogVal = self::validateCatalogJson($catalogJson);
    if (empty($catalogVal['ok'])) {
      return $fail($catalogVal['error']);
    }

    $settingsToWrite = null;
    if ($fromZip && $restoreSettings && $settingsJson !== null) {
      $settingsVal = self::validateSettingsJson($settingsJson);
      if (empty($settingsVal['ok'])) {
        return $fail($settingsVal['error']);
      }
      $settingsToWrite = $settingsJson;
    }

    $bakFiles = [];
    $catalogWrite = self::writeFileWithBackup(self::catalogPath(), $catalogJson);
    if (empty($catalogWrite['ok'])) {
      return [
        'ok' => false,
        'error' => $catalogWrite['error'],
        'album_count' => 0,
        'settings_restored' => false,
        'bak_files' => $catalogWrite['bak_files'],
      ];
    }
    $bakFiles = array_merge($bakFiles, $catalogWrite['bak_files']);

    $settingsRestored = false;
    if ($settingsToWrite !== null) {
      $settingsWrite = self::writeFileWithBackup(self::settingsPath(), $settingsToWrite);
      if (empty($settingsWrite['ok'])) {
        if (!empty($bakFiles)) {
          @copy($bakFiles[0], self::catalogPath());
        }
        return [
          'ok' => false,
          'error' => $settingsWrite['error'],
          'album_count' => 0,
          'settings_restored' => false,
          'bak_files' => array_merge($bakFiles, $settingsWrite['bak_files']),
        ];
      }
      $bakFiles = array_merge($bakFiles, $settingsWrite['bak_files']);
      $settingsRestored = true;
    }

    return [
      'ok' => true,
      'error' => null,
      'album_count' => $catalogVal['album_count'],
      'settings_restored' => $settingsRestored,
      'bak_files' => $bakFiles,
    ];
  }

  /**
   * @param string $path
   * @return string
   */
  private static function detectMimeType($path) {
    if (!function_exists('finfo_open')) {
      return '';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
      return '';
    }
    $mime = finfo_file($finfo, $path);
    return is_string($mime) ? $mime : '';
  }

  /**
   * Back up an existing file, then write content via temp + rename.
   *
   * @param string $targetPath
   * @param string $content
   * @return array{ok:bool,error:?string,bak_files:array}
   */
  private static function writeFileWithBackup($targetPath, $content) {
    $bakFiles = [];
    $stamp = date('YmdHis');

    if (is_file($targetPath)) {
      $bakPath = $targetPath . '.bak.' . $stamp;
      if (!copy($targetPath, $bakPath)) {
        return [
          'ok' => false,
          'error' => 'Could not create backup of ' . basename($targetPath),
          'bak_files' => [],
        ];
      }
      $bakFiles[] = $bakPath;
    }

    $tmpWrite = $targetPath . '.tmp';
    if (file_put_contents($tmpWrite, $content) === false) {
      return [
        'ok' => false,
        'error' => 'Could not write ' . basename($targetPath),
        'bak_files' => $bakFiles,
      ];
    }

    if (!rename($tmpWrite, $targetPath)) {
      @unlink($tmpWrite);
      if (!empty($bakFiles)) {
        @copy($bakFiles[0], $targetPath);
      }
      return [
        'ok' => false,
        'error' => 'Could not finalize ' . basename($targetPath),
        'bak_files' => $bakFiles,
      ];
    }

    return ['ok' => true, 'error' => null, 'bak_files' => $bakFiles];
  }
}
