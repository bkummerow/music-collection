<?php
// Dynamic Web App Manifest
header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=3600');

$settingsFile = __DIR__ . '/data/settings.json';
$title = 'Music Collection';
$metaDescription = 'Track your vinyl music collection with album covers, tracklists, and Discogs integration. Organize your music library by artist, album, release year, and ownership status.';
$startUrl = '/';

if (file_exists($settingsFile)) {
    $content = file_get_contents($settingsFile);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        if (isset($decoded['app']['title']) && is_string($decoded['app']['title'])) {
            $t = trim($decoded['app']['title']);
            if ($t !== '') {
                $title = $t;
            }
        }
        if (isset($decoded['app']['meta_description']) && is_string($decoded['app']['meta_description'])) {
            $md = trim($decoded['app']['meta_description']);
            if ($md !== '') {
                $metaDescription = $md;
            }
        }
        if (isset($decoded['app']['start_url']) && is_string($decoded['app']['start_url'])) {
            $su = trim($decoded['app']['start_url']);
            if ($su !== '') {
                // Normalize leading/trailing slashes
                if ($su[0] !== '/') { $su = '/' . $su; }
                if (substr($su, -1) !== '/') { $su .= '/'; }
                $startUrl = $su;
            }
        }
    }
}

$manifest = [
    'name' => $title,
    'short_name' => $title,
    'description' => $metaDescription,
    'icons' => [
        [ 'src' => 'android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png' ],
        [ 'src' => 'android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png' ],
    ],
    'theme_color' => '#1a1a1a',
    'background_color' => '#ffffff',
    'display' => 'standalone',
    'start_url' => $startUrl,
    'scope' => $startUrl,
    'version' => '2.0'
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>

