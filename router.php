<?php
/**
 * Router for PHP built-in server (Railway / local php -S).
 * Serves .wasm with the correct MIME type for the barcode scanner polyfill.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    return false;
}

$file = __DIR__ . $path;
if (!is_file($file)) {
    return false;
}

if (preg_match('/\.wasm$/i', $path)) {
    header('Content-Type: application/wasm');
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);
    return true;
}

return false;
