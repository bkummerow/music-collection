<?php
/**
 * Server-side collection list filter, sort, and pagination.
 */

/**
 * Strip trailing Discogs label numbering, e.g. "Label Name (5)" → "Label Name".
 *
 * @param string $label
 * @return string
 */
function collection_list_clean_discogs_numbering($label) {
  if (!is_string($label)) {
    return $label;
  }
  if ($label === '') {
    return $label;
  }
  return preg_replace('/\s*\(\d+\)\s*$/', '', $label);
}

/**
 * Mirror JS getSortableArtistName (articles, Person last-name, Elvis Costello).
 *
 * @param string $artistName
 * @param string|null $artistType
 * @return string
 */
function collection_list_sortable_artist_name($artistName, $artistType = null) {
  if (!$artistName) {
    return '';
  }

  $name = trim($artistName);
  $lowerName = strtolower($name);

  if (strpos($lowerName, 'elvis costello') !== false && strpos($lowerName, 'attractions') !== false) {
    return 'Costello, Elvis & The Attractions';
  }

  $prefixes = array('the ', 'a ', 'an ');
  foreach ($prefixes as $prefix) {
    if (strpos($lowerName, $prefix) === 0) {
      return trim(substr($name, strlen($prefix)));
    }
  }

  if ($artistType) {
    $typeLower = strtolower($artistType);
    if ($typeLower === 'group') {
      return $name;
    }
    if ($typeLower === 'person') {
      $parts = preg_split('/\s+/', $name);
      if (count($parts) === 2) {
        $firstName = $parts[0];
        $lastName = $parts[1];
        $commonSuffixes = array('jr', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x');
        if (in_array(strtolower($lastName), $commonSuffixes, true) && count($parts) > 2) {
          return $parts[count($parts) - 2] . ', ' . implode(' ', array_slice($parts, 0, -2)) . ' ' . $parts[count($parts) - 1];
        }
        return $lastName . ', ' . $firstName;
      }
      return $name;
    }
  }

  $parts = preg_split('/\s+/', $name);
  $bandIndicators = array('&', 'and', 'featuring', 'feat', 'ft', 'with', 'vs', 'versus');
  $hasBandIndicator = false;
  foreach ($bandIndicators as $indicator) {
    if (strpos($lowerName, $indicator) !== false) {
      $hasBandIndicator = true;
      break;
    }
  }

  $lastWord = $parts[count($parts) - 1];
  $hasNumberSuffix = preg_match('/^\d+$/', $lastWord);
  $isLikelyBand = $hasBandIndicator || $hasNumberSuffix || count($parts) > 3;

  if (!$isLikelyBand && count($parts) === 2) {
    $firstName = $parts[0];
    $lastName = $parts[1];
    $commonSuffixes = array('jr', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x');
    if (in_array(strtolower($lastName), $commonSuffixes, true) && count($parts) > 2) {
      return $parts[count($parts) - 2] . ', ' . implode(' ', array_slice($parts, 0, -2)) . ' ' . $parts[count($parts) - 1];
    }
    return $lastName . ', ' . $firstName;
  }

  return $name;
}

/**
 * Mirror JS getSortableAlbumName (leading articles stripped).
 *
 * @param string $albumName
 * @return string
 */
function collection_list_sortable_album_name($albumName) {
  if (!$albumName) {
    return '';
  }

  $name = trim($albumName);
  $lowerName = strtolower($name);
  $prefixes = array('the ', 'a ', 'an ');

  foreach ($prefixes as $prefix) {
    if (strpos($lowerName, $prefix) === 0) {
      return trim(substr($name, strlen($prefix)));
    }
  }

  return $name;
}

/**
 * @param array $get Typically $_GET
 * @return array Normalized list query params
 */
function collection_list_normalize_params($get) {
  $page = isset($get['page']) ? (int) $get['page'] : 1;
  if ($page < 1) {
    $page = 1;
  }
  $limit = isset($get['limit']) ? (int) $get['limit'] : 100;
  if ($limit < 1) {
    $limit = 100;
  }
  if ($limit > 200) {
    $limit = 200;
  }
  $filter = isset($get['filter']) ? $get['filter'] : 'all';
  if (!in_array($filter, array('owned', 'wanted', 'all'), true)) {
    $filter = 'all';
  }
  $sort = isset($get['sort']) ? $get['sort'] : 'artist';
  if (!in_array($sort, array('artist', 'album', 'year'), true)) {
    $sort = 'artist';
  }
  $direction = isset($get['direction']) ? strtolower($get['direction']) : 'asc';
  if (!in_array($direction, array('asc', 'desc'), true)) {
    $direction = 'asc';
  }
  $formatTypes = null;
  if (!empty($get['format_types'])) {
    if (is_array($get['format_types'])) {
      $formatTypes = array_values(array_filter(array_map('strval', $get['format_types'])));
    } else {
      $formatTypes = array_values(array_filter(array_map('trim', explode(',', (string) $get['format_types']))));
    }
    if (count($formatTypes) === 0) {
      $formatTypes = null;
    }
  }
  return array(
    'page' => $page,
    'limit' => $limit,
    'filter' => $filter,
    'search' => isset($get['search']) ? trim((string) $get['search']) : '',
    'style' => isset($get['style']) ? trim((string) $get['style']) : '',
    'format' => isset($get['format']) ? trim((string) $get['format']) : '',
    'format_types' => $formatTypes,
    'year' => isset($get['year']) ? trim((string) $get['year']) : '',
    'artist' => isset($get['artist']) ? trim((string) $get['artist']) : '',
    'label' => isset($get['label']) ? trim((string) $get['label']) : '',
    'producer' => isset($get['producer']) ? trim((string) $get['producer']) : '',
    'sort' => $sort,
    'direction' => $direction,
  );
}

/**
 * Apply filters in the same order as the lazy-load server spec (mirrors client semantics).
 *
 * @param array $albums
 * @param array $params
 * @return array
 */
function collection_list_filter($albums, $params) {
  $search = $params['search'];
  $styleFacet = $params['style'];

  // 1. Style facet (matches JS loadAlbums: facet before style-prefix search).
  if ($styleFacet !== '') {
    $albums = array_filter($albums, function ($album) use ($styleFacet) {
      if (empty($album['style'])) {
        return false;
      }
      $styles = array_map('trim', explode(',', $album['style']));
      return in_array($styleFacet, $styles, true);
    });
  }

  // 2–3. Search: style-prefix only when facet empty; otherwise artist/album substring.
  if ($search !== '') {
    $searchLower = strtolower($search);
    $styleKeywords = array('style:', 'genre:', 'type:');
    $isStyleSearch = false;
    foreach ($styleKeywords as $keyword) {
      if (strpos($searchLower, $keyword) === 0) {
        $isStyleSearch = true;
        break;
      }
    }

    if ($isStyleSearch && $styleFacet === '') {
      $styleSearchTerm = trim(preg_replace('/^(style|genre|type):\s*/i', '', $search));
      if ($styleSearchTerm !== '') {
        $termLower = strtolower($styleSearchTerm);
        $albums = array_filter($albums, function ($album) use ($termLower) {
          if (empty($album['style'])) {
            return false;
          }
          $styleArray = array_map('trim', explode(',', strtolower($album['style'])));
          foreach ($styleArray as $style) {
            if (strpos($style, $termLower) !== false) {
              return true;
            }
          }
          return false;
        });
      }
    } elseif (!$isStyleSearch) {
      $termLower = strtolower($search);
      $albums = array_filter($albums, function ($album) use ($termLower) {
        $artist = isset($album['artist_name']) ? strtolower($album['artist_name']) : '';
        $albumName = isset($album['album_name']) ? strtolower($album['album_name']) : '';
        return strpos($artist, $termLower) !== false || strpos($albumName, $termLower) !== false;
      });
    }
  }

  if ($params['format'] !== '' || $params['format_types'] !== null) {
    $formatFilter = $params['format'];
    $formatTypes = $params['format_types'];
    $albums = array_filter($albums, function ($album) use ($formatFilter, $formatTypes) {
      if (empty($album['format'])) {
        return false;
      }
      $formats = array_map('trim', explode(',', $album['format']));

      if ($formatTypes !== null && count($formatTypes) > 0) {
        foreach ($formats as $format) {
          $unescapedFormat = strtolower(trim(str_replace('\\"', '"', $format)));
          foreach ($formatTypes as $consolidatedType) {
            if (strtolower(trim((string) $consolidatedType)) === $unescapedFormat) {
              return true;
            }
          }
        }
        return false;
      }

      if ($formatFilter === '') {
        return true;
      }

      foreach ($formats as $format) {
        $unescapedFormat = str_replace('\\"', '"', $format);
        if (strtolower($unescapedFormat) === strtolower($formatFilter)) {
          return true;
        }
      }
      return false;
    });
  }

  if ($params['year'] !== '') {
    $yearFilter = $params['year'];
    $albums = array_filter($albums, function ($album) use ($yearFilter) {
      return isset($album['release_year']) && $album['release_year'] == $yearFilter;
    });
  }

  if ($params['artist'] !== '') {
    $artistFilter = strtolower($params['artist']);
    $albums = array_filter($albums, function ($album) use ($artistFilter) {
      return isset($album['artist_name'])
        && strtolower($album['artist_name']) === $artistFilter;
    });
  }

  if ($params['label'] !== '') {
    $labelFilter = strtolower(trim(collection_list_clean_discogs_numbering($params['label'])));
    $albums = array_filter($albums, function ($album) use ($labelFilter) {
      if (empty($album['label']) || !is_string($album['label'])) {
        return false;
      }
      $cleanAlbumLabel = strtolower(trim(collection_list_clean_discogs_numbering($album['label'])));
      return $cleanAlbumLabel === $labelFilter;
    });
  }

  if ($params['producer'] !== '') {
    $producerFilter = strtolower($params['producer']);
    $albums = array_filter($albums, function ($album) use ($producerFilter) {
      if (empty($album['producer'])) {
        return false;
      }
      return strpos(strtolower($album['producer']), $producerFilter) !== false;
    });
  }

  if ($params['filter'] !== 'all') {
    $albums = array_filter($albums, function ($album) use ($params) {
      if ($params['filter'] === 'owned') {
        return isset($album['is_owned']) && $album['is_owned'] == 1;
      }
      if ($params['filter'] === 'wanted') {
        return isset($album['want_to_own']) && $album['want_to_own'] == 1;
      }
      return true;
    });
  }

  return array_values($albums);
}

/**
 * Port of client sortAlbums (year / album / default artist).
 *
 * @param array $albums
 * @param string $sort
 * @param string $direction
 * @return array
 */
function collection_list_sort($albums, $sort, $direction) {
  usort($albums, function ($a, $b) use ($sort, $direction) {
    $artistTypeA = isset($a['artist_type']) ? $a['artist_type'] : null;
    $artistTypeB = isset($b['artist_type']) ? $b['artist_type'] : null;

    if ($sort === 'year') {
      $yearA = (int) (isset($a['release_year']) ? $a['release_year'] : 0);
      $yearB = (int) (isset($b['release_year']) ? $b['release_year'] : 0);

      if ($direction === 'desc') {
        if ($yearA !== $yearB) {
          return $yearB - $yearA;
        }
      } else {
        if ($yearA !== $yearB) {
          return $yearA - $yearB;
        }
      }
      $keyA = collection_list_sortable_artist_name($a['artist_name'], $artistTypeA);
      $keyB = collection_list_sortable_artist_name($b['artist_name'], $artistTypeB);
      return strcmp($keyA, $keyB);
    }

    if ($sort === 'album') {
      $keyA = collection_list_sortable_artist_name($a['artist_name'], $artistTypeA);
      $keyB = collection_list_sortable_artist_name($b['artist_name'], $artistTypeB);
      $artistComparison = strcmp($keyA, $keyB);

      if ($direction === 'desc') {
        if ($artistComparison !== 0) {
          return -$artistComparison;
        }
      } else {
        if ($artistComparison !== 0) {
          return $artistComparison;
        }
      }

      $yearA = (int) (isset($a['release_year']) ? $a['release_year'] : 0);
      $yearB = (int) (isset($b['release_year']) ? $b['release_year'] : 0);
      if ($yearA !== $yearB) {
        return $yearA - $yearB;
      }

      $albumA = collection_list_sortable_album_name($a['album_name']);
      $albumB = collection_list_sortable_album_name($b['album_name']);
      return strcmp($albumA, $albumB);
    }

    $keyA = collection_list_sortable_artist_name($a['artist_name'], $artistTypeA);
    $keyB = collection_list_sortable_artist_name($b['artist_name'], $artistTypeB);
    return strcmp($keyA, $keyB);
  });

  return array_values($albums);
}

/**
 * @param array $albums Full catalog rows
 * @param array $params From collection_list_normalize_params
 * @return array{albums: array, meta: array}
 */
function collection_list_apply($albums, $params) {
  $filtered = collection_list_filter($albums, $params);
  $sorted = collection_list_sort($filtered, $params['sort'], $params['direction']);
  $total = count($sorted);
  $offset = ($params['page'] - 1) * $params['limit'];
  $pageAlbums = array_slice($sorted, $offset, $params['limit']);
  return array(
    'albums' => array_values($pageAlbums),
    'meta' => array(
      'page' => $params['page'],
      'limit' => $params['limit'],
      'total' => $total,
      'has_more' => ($params['page'] * $params['limit']) < $total,
    ),
  );
}
