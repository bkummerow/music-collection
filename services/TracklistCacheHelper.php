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
    return (bool) $musicCollection->updateAlbumRaw([
      'id' => $album['id'],
      'pressing_year' => $incoming,
    ]);
  } catch (Exception $e) {
    return false;
  }
}
