<?php
/**
 * Tracklist cache helpers (lean persist payload).
 */

require_once __DIR__ . '/../config/auth_config.php';

/**
 * @param array|null $album
 * @return bool
 */
function tracklistAlbumHasCache($album) {
  return is_array($album)
    && !empty($album['tracklist'])
    && is_array($album['tracklist']);
}

/**
 * Store only durable track fields (no lyrics URLs).
 *
 * @param array $tracklist
 * @return array
 */
function tracklistStripLyricsForStorage($tracklist) {
  $out = [];
  if (!is_array($tracklist)) {
    return $out;
  }
  foreach ($tracklist as $track) {
    if (!is_array($track)) {
      continue;
    }
    $out[] = [
      'position' => isset($track['position']) ? (string) $track['position'] : '',
      'title' => isset($track['title']) ? (string) $track['title'] : '',
      'duration' => isset($track['duration']) ? (string) $track['duration'] : '',
    ];
  }
  return $out;
}

/**
 * Build updateAlbumRaw payload for lean cache write.
 *
 * @param array $album Existing local album
 * @param array $releaseInfo Discogs-shaped payload (needs tracklist, optional total_runtime/format/label/producer)
 * @param string|int $discogsReleaseId
 * @return array
 */
function tracklistBuildCachePayload($album, $releaseInfo, $discogsReleaseId) {
  $payload = [
    'id' => $album['id'],
    'artist_name' => $album['artist_name'],
    'album_name' => $album['album_name'],
    'tracklist' => tracklistStripLyricsForStorage($releaseInfo['tracklist'] ?? []),
    'total_runtime' => isset($releaseInfo['total_runtime']) ? $releaseInfo['total_runtime'] : '',
    'tracklist_cached_at' => gmdate('c'),
    'tracklist_source_release_id' => $discogsReleaseId,
  ];

  // Discogs release year (this pressing), distinct from collection release_year/master year
  if (isset($releaseInfo['year']) && $releaseInfo['year'] !== '' && $releaseInfo['year'] !== null) {
    $payload['pressing_year'] = (string) $releaseInfo['year'];
  }

  foreach (['format', 'label', 'producer'] as $field) {
    $local = isset($album[$field]) ? trim((string) $album[$field]) : '';
    $incoming = isset($releaseInfo[$field]) ? trim((string) $releaseInfo[$field]) : '';
    if ($local === '' && $incoming !== '') {
      $payload[$field] = $incoming;
    }
  }

  return $payload;
}

/**
 * Persist lean tracklist cache after a successful Discogs fetch.
 * Allowed for anonymous visitors (server writes Discogs data only; Refresh remains admin-only).
 * Does not throw to callers.
 *
 * @return bool
 */
function tracklistPersistCache($musicCollection, $album, $releaseInfo, $discogsReleaseId) {
  // Skip while an authenticated session must change password (same mutate gate as elsewhere).
  if (AuthHelper::isAuthenticated() && AuthHelper::mustChangePassword()) {
    return false;
  }
  // Require a real collection row (Discogs search hits use different field names).
  if (!is_array($album) || empty($album['id']) || empty($album['artist_name']) || empty($album['album_name'])) {
    return false;
  }
  $tracks = tracklistStripLyricsForStorage($releaseInfo['tracklist'] ?? []);
  if (count($tracks) === 0) {
    return false;
  }
  try {
    $payload = tracklistBuildCachePayload($album, $releaseInfo, $discogsReleaseId);
    $payload['tracklist'] = $tracks;
    return (bool) $musicCollection->updateAlbumRaw($payload);
  } catch (Exception $e) {
    return false;
  }
}

/**
 * Cache Discogs pressing year on the album when missing. Safe for anonymous enrich.
 *
 * @param object $musicCollection
 * @param array $album
 * @param string|int $pressingYear
 * @return bool
 */
function tracklistPersistPressingYear($musicCollection, $album, $pressingYear) {
  if (!is_array($album) || empty($album['id'])) {
    return false;
  }
  if ($pressingYear === null || $pressingYear === '') {
    return false;
  }
  $existing = isset($album['pressing_year']) ? trim((string) $album['pressing_year']) : '';
  $incoming = trim((string) $pressingYear);
  if ($existing !== '' || $incoming === '') {
    return false;
  }
  try {
    $payload = [
      'id' => $album['id'],
      'pressing_year' => $incoming,
    ];
    // Include names when present so updateAlbumRaw duplicate-check never warns.
    if (!empty($album['artist_name'])) {
      $payload['artist_name'] = $album['artist_name'];
    }
    if (!empty($album['album_name'])) {
      $payload['album_name'] = $album['album_name'];
    }
    return (bool) $musicCollection->updateAlbumRaw($payload);
  } catch (Exception $e) {
    return false;
  }
}

/**
 * @param array|null $album
 * @return bool
 */
function tracklistAlbumHasArtistWebsiteCache($album) {
  return is_array($album)
    && isset($album['artist_website'])
    && is_array($album['artist_website']);
}

/**
 * Lean artist website payload for album storage (no match_score).
 *
 * @param mixed $artistWebsite
 * @return array|null
 */
function tracklistStripArtistWebsiteForStorage($artistWebsite) {
  if (!is_array($artistWebsite)) {
    return null;
  }
  $websites = [];
  if (!empty($artistWebsite['websites']) && is_array($artistWebsite['websites'])) {
    foreach ($artistWebsite['websites'] as $website) {
      if (!is_array($website)) {
        continue;
      }
      $websites[] = [
        'url' => isset($website['url']) ? (string) $website['url'] : '',
        'type' => isset($website['type']) ? (string) $website['type'] : '',
      ];
    }
  }
  return [
    'name' => isset($artistWebsite['name']) ? (string) $artistWebsite['name'] : '',
    'websites' => $websites,
    'discogs_url' => isset($artistWebsite['discogs_url']) ? (string) $artistWebsite['discogs_url'] : '',
  ];
}

/**
 * Persist artist website cache after a successful Discogs fetch.
 * Same write gate as tracklistPersistCache (must-change-password skip; local album required).
 *
 * @param object $musicCollection
 * @param array $album
 * @param array $artistWebsite
 * @return bool
 */
function tracklistPersistArtistWebsite($musicCollection, $album, $artistWebsite) {
  if (AuthHelper::isAuthenticated() && AuthHelper::mustChangePassword()) {
    return false;
  }
  if (!is_array($album) || empty($album['id']) || empty($album['artist_name']) || empty($album['album_name'])) {
    return false;
  }
  $lean = tracklistStripArtistWebsiteForStorage($artistWebsite);
  if ($lean === null) {
    return false;
  }
  try {
    return (bool) $musicCollection->updateAlbumRaw([
      'id' => $album['id'],
      'artist_name' => $album['artist_name'],
      'album_name' => $album['album_name'],
      'artist_website' => $lean,
      'artist_website_cached_at' => gmdate('c'),
    ]);
  } catch (Exception $e) {
    return false;
  }
}

/**
 * Whether any artist-link display toggles are enabled (matches frontend hasAnyArtistLinksEnabled).
 *
 * @param array|null $albumDisplay From loadAlbumDisplaySettings(); loads settings when null
 * @return bool
 */
function albumDisplayHasArtistLinksEnabled($albumDisplay = null) {
  if ($albumDisplay === null) {
    if (!function_exists('loadAlbumDisplaySettings')) {
      if (!defined('MUSIC_COLLECTION_THEME_SETTINGS_LIB')) {
        define('MUSIC_COLLECTION_THEME_SETTINGS_LIB', true);
      }
      require_once __DIR__ . '/../api/theme_api.php';
    }
    $albumDisplay = loadAlbumDisplaySettings();
  }
  if (!is_array($albumDisplay)) {
    return false;
  }
  $keys = [
    'show_facebook', 'show_twitter', 'show_instagram', 'show_youtube',
    'show_bandcamp', 'show_soundcloud', 'show_wikipedia', 'show_lastfm',
    'show_imdb', 'show_bluesky', 'show_discogs', 'show_official_website',
  ];
  foreach ($keys as $key) {
    if (!empty($albumDisplay[$key])) {
      return true;
    }
  }
  return false;
}

/**
 * After add/update, fetch Discogs artist links and persist when display settings enable them.
 * Does not throw; skips when links already cached, settings off, or Discogs unavailable.
 *
 * @param object $musicCollection
 * @param object|null $discogsAPI
 * @param array|null $album Local album row
 * @return bool
 */
function tracklistMaybePersistArtistWebsiteOnAlbumSave($musicCollection, $discogsAPI, $album) {
  if (!albumDisplayHasArtistLinksEnabled()) {
    return false;
  }
  if (!is_array($album) || empty($album['artist_name'])) {
    return false;
  }
  if (tracklistAlbumHasArtistWebsiteCache($album)) {
    return false;
  }
  if (!$discogsAPI || !method_exists($discogsAPI, 'isAvailable') || !$discogsAPI->isAvailable()) {
    return false;
  }
  try {
    $artistWebsite = $discogsAPI->getArtistWebsite($album['artist_name']);
  } catch (Exception $e) {
    return false;
  }
  if (!is_array($artistWebsite)) {
    return false;
  }
  return tracklistPersistArtistWebsite($musicCollection, $album, $artistWebsite);
}

/**
 * Resolve the saved album row after add/replace and optionally persist artist website cache.
 *
 * @param object $musicCollection
 * @param object|null $discogsAPI
 * @param string $artistName
 * @param string $albumName
 * @param int|string|null $albumId Prefer this id when known (replace path)
 * @return void
 */
function tracklistMaybePersistArtistWebsiteAfterAlbumSave($musicCollection, $discogsAPI, $artistName, $albumName, $albumId = null) {
  $album = null;
  if ($albumId !== null && $albumId !== '' && method_exists($musicCollection, 'getAlbumById')) {
    $album = $musicCollection->getAlbumById($albumId);
  }
  if (!$album && method_exists($musicCollection, 'getNewestAlbumByArtistAndName')) {
    $album = $musicCollection->getNewestAlbumByArtistAndName($artistName, $albumName);
  } elseif (!$album && method_exists($musicCollection, 'getAlbumByArtistAndName')) {
    $album = $musicCollection->getAlbumByArtistAndName($artistName, $albumName);
  }
  if ($album) {
    tracklistMaybePersistArtistWebsiteOnAlbumSave($musicCollection, $discogsAPI, $album);
  }
}
