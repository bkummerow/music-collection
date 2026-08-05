#!/usr/bin/env php
<?php
/**
 * One-shot cover art upgrade for a music_collection JSON file.
 *
 * Writes both:
 *   cover_url     — Discogs thumbnail (list)
 *   cover_images  — all full-size Discogs image URIs (primary first)
 * Removes cover_url_large when applying.
 *
 * Usage:
 *   php .scripts/upgrade-cover-art.php --file=data/music_collection2.json --dry-run
 *   php .scripts/upgrade-cover-art.php --file=data/music_collection2.json --apply
 */

require_once __DIR__ . '/../services/DiscogsAPIService.php';

/**
 * Parse CLI flags into a simple options map.
 *
 * @param array $argv
 * @return array
 */
function upgrade_cover_art_parse_args($argv) {
    $options = [
        'file' => null,
        'dry_run' => false,
        'apply' => false,
        'sleep_ms' => 1100,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($arg === '--apply') {
            $options['apply'] = true;
        } elseif (strpos($arg, '--file=') === 0) {
            $options['file'] = substr($arg, 7);
        } elseif (strpos($arg, '--sleep-ms=') === 0) {
            $options['sleep_ms'] = max(0, intval(substr($arg, 11)));
        }
    }

    return $options;
}

/**
 * Normalize a stored cover URL for comparison (unwrap proxy, force https).
 *
 * @param string|null $url
 * @return string
 */
function upgrade_cover_art_normalize_url($url) {
    if (empty($url)) {
        return '';
    }

    // Unwrap local image proxy URLs back to the Discogs source
    if (strpos($url, 'image_proxy.php') !== false) {
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['url'])) {
                $url = $query['url'];
            }
        }
    }

    return ImageOptimizationService::forceHttps($url);
}

/**
 * Print a progress line to STDERR.
 *
 * @param string $message
 * @return void
 */
function upgrade_cover_art_log($message) {
    fwrite(STDERR, $message . PHP_EOL);
}

$options = upgrade_cover_art_parse_args($argv);

if (empty($options['file'])) {
    upgrade_cover_art_log('Error: --file=path/to/music_collection.json is required');
    exit(1);
}

if (!$options['dry_run'] && !$options['apply']) {
    upgrade_cover_art_log('Error: pass --dry-run or --apply');
    exit(1);
}

if ($options['dry_run'] && $options['apply']) {
    upgrade_cover_art_log('Error: use either --dry-run or --apply, not both');
    exit(1);
}

$filePath = $options['file'];
if ($filePath[0] !== '/') {
    $filePath = dirname(__DIR__) . '/' . ltrim($filePath, '/');
}

if (!is_file($filePath)) {
    upgrade_cover_art_log('Error: file not found: ' . $filePath);
    exit(1);
}

$raw = file_get_contents($filePath);
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['albums']) || !is_array($data['albums'])) {
    upgrade_cover_art_log('Error: invalid collection JSON (expected albums object/array)');
    exit(1);
}

$discogs = new DiscogsAPIService();
if (!$discogs->isAvailable()) {
    upgrade_cover_art_log('Error: Discogs API key is not configured');
    exit(1);
}

$total = count($data['albums']);
$updated = 0;
$unchanged = 0;
$skipped = 0;
$failed = 0;
$index = 0;

$modeLabel = $options['dry_run'] ? 'DRY-RUN' : 'APPLY';
upgrade_cover_art_log("{$modeLabel}: {$total} albums in {$filePath}");

foreach ($data['albums'] as $key => &$album) {
    $index++;
    $albumId = $album['id'] ?? $key;
    $artist = $album['artist_name'] ?? '';
    $title = $album['album_name'] ?? '';
    $releaseId = $album['discogs_release_id'] ?? null;

    if (empty($releaseId)) {
        $skipped++;
        upgrade_cover_art_log("[{$index}/{$total}] SKIP id={$albumId} (no discogs_release_id) {$artist} - {$title}");
        continue;
    }

    $urls = $discogs->getCoverUrlsByReleaseId($releaseId);
    if (empty($urls) || (empty($urls['thumb']) && empty($urls['cover_images']))) {
        $failed++;
        upgrade_cover_art_log("[{$index}/{$total}] FAIL id={$albumId} release={$releaseId} {$artist} - {$title}");
        if ($options['sleep_ms'] > 0) {
            usleep($options['sleep_ms'] * 1000);
        }
        continue;
    }

    $newThumb = upgrade_cover_art_normalize_url($urls['thumb'] ?? (isset($urls['cover_images'][0]) ? $urls['cover_images'][0] : ''));
    $newImages = [];
    if (!empty($urls['cover_images']) && is_array($urls['cover_images'])) {
        foreach ($urls['cover_images'] as $imageUrl) {
            $normalized = upgrade_cover_art_normalize_url($imageUrl);
            if ($normalized !== '') {
                $newImages[] = $normalized;
            }
        }
    }
    $newImages = array_values(array_unique($newImages));

    $currentThumb = upgrade_cover_art_normalize_url($album['cover_url'] ?? '');
    $currentImages = [];
    if (!empty($album['cover_images']) && is_array($album['cover_images'])) {
        foreach ($album['cover_images'] as $imageUrl) {
            $normalized = upgrade_cover_art_normalize_url($imageUrl);
            if ($normalized !== '') {
                $currentImages[] = $normalized;
            }
        }
    } elseif (!empty($album['cover_url_large'])) {
        $currentImages[] = upgrade_cover_art_normalize_url($album['cover_url_large']);
    }

    $storedIsProxy = strpos((string) ($album['cover_url'] ?? ''), 'image_proxy.php') !== false
        || strpos((string) ($album['cover_url_large'] ?? ''), 'image_proxy.php') !== false
        || (!empty($album['cover_images'][0]) && strpos((string) $album['cover_images'][0], 'image_proxy.php') !== false);

    $needsUpdate = $storedIsProxy
        || $currentThumb !== $newThumb
        || $currentImages !== $newImages
        || array_key_exists('cover_url_large', $album)
        || !isset($album['cover_images']);

    if (!$needsUpdate) {
        $unchanged++;
        upgrade_cover_art_log("[{$index}/{$total}] SAME id={$albumId} {$artist} - {$title}");
    } else {
        $updated++;
        upgrade_cover_art_log("[{$index}/{$total}] UPDATE id={$albumId} {$artist} - {$title}");
        upgrade_cover_art_log("  thumb: {$newThumb}");
        upgrade_cover_art_log('  images: ' . count($newImages));

        if ($options['apply']) {
            $album['cover_url'] = $newThumb;
            $album['cover_images'] = $newImages;
            unset($album['cover_url_large']);
            $album['updated_date'] = date('Y-m-d H:i:s');
        }
    }

    if ($options['sleep_ms'] > 0) {
        usleep($options['sleep_ms'] * 1000);
    }
}
unset($album);

upgrade_cover_art_log('');
upgrade_cover_art_log("Summary ({$modeLabel}):");
upgrade_cover_art_log("  total:     {$total}");
upgrade_cover_art_log("  updated:   {$updated}");
upgrade_cover_art_log("  unchanged: {$unchanged}");
upgrade_cover_art_log("  skipped:   {$skipped}");
upgrade_cover_art_log("  failed:    {$failed}");

if ($options['apply']) {
    $backupPath = $filePath . '.bak.' . date('Ymd-His');
    if (!copy($filePath, $backupPath)) {
        upgrade_cover_art_log('Error: could not write backup to ' . $backupPath);
        exit(1);
    }
    upgrade_cover_art_log("Backup: {$backupPath}");

    $encoded = json_encode($data, JSON_PRETTY_PRINT);
    if ($encoded === false) {
        upgrade_cover_art_log('Error: failed to encode updated JSON');
        exit(1);
    }

    if (file_put_contents($filePath, $encoded) === false) {
        upgrade_cover_art_log('Error: failed to write ' . $filePath);
        exit(1);
    }

    upgrade_cover_art_log("Wrote: {$filePath}");
}

exit($failed > 0 && $updated === 0 ? 2 : 0);
