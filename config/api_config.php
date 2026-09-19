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
