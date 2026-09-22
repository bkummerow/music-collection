<?php
require_once __DIR__ . '/../services/TracklistCacheHelper.php';

function assert_true($cond, $msg) {
  if (!$cond) {
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
  }
}

assert_true(tracklistAlbumHasArtistWebsiteCache([
  'artist_website' => ['name' => 'X', 'websites' => [], 'discogs_url' => 'https://www.discogs.com/artist/1'],
]) === true, 'empty websites is cache hit');

assert_true(tracklistAlbumHasArtistWebsiteCache(['artist_website' => null]) === false, 'null is miss');
assert_true(tracklistAlbumHasArtistWebsiteCache([]) === false, 'missing is miss');

$lean = tracklistStripArtistWebsiteForStorage([
  'name' => 'Artist',
  'websites' => [
    ['url' => 'https://example.com', 'type' => 'Official Website', 'extra' => 'drop'],
  ],
  'discogs_url' => 'https://www.discogs.com/artist/9',
  'match_score' => 0.99,
]);

assert_true(is_array($lean) && $lean['name'] === 'Artist', 'name kept');
assert_true(!isset($lean['match_score']), 'match_score stripped');
assert_true(count($lean['websites']) === 1 && $lean['websites'][0]['url'] === 'https://example.com', 'url kept');
assert_true(!isset($lean['websites'][0]['extra']), 'extra stripped');
assert_true(tracklistStripArtistWebsiteForStorage(null) === null, 'null strip');

echo "artist_website_cache_test: OK\n";
