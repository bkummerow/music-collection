/**
 * PWA shell URL rules — used by sw.js (importScripts) and Node tests.
 * Attach to self/globalThis as PwaShellRules.
 */
(function (root) {
  /**
   * @param {URL} url
   * @return {boolean}
   */
  function shouldNetworkOnly(url) {
    var path = url.pathname || '';
    if (/\.php$/i.test(path)) {
      return true;
    }
    if (path.indexOf('/api/') !== -1 || path.indexOf('/data/') !== -1) {
      return true;
    }
    // Path segment starts with api/ or data/ at root
    if (/^\/api(\/|$)/i.test(path) || /^\/data(\/|$)/i.test(path)) {
      return true;
    }
    return false;
  }

  /**
   * Same-origin shell static assets eligible for cache-first.
   * @param {URL} url
   * @return {boolean}
   */
  function isShellAsset(url) {
    if (shouldNetworkOnly(url)) {
      return false;
    }
    var path = url.pathname || '';
    if (path.indexOf('/assets/') !== -1 || /^\/assets(\/|$)/i.test(path)) {
      return true;
    }
    var shellFiles = [
      '/favicon.ico',
      '/favicon-16x16.png',
      '/favicon-32x32.png',
      '/apple-touch-icon.png',
      '/android-chrome-192x192.png',
      '/android-chrome-512x512.png'
    ];
    for (var i = 0; i < shellFiles.length; i++) {
      if (path === shellFiles[i] || path.endsWith(shellFiles[i])) {
        return true;
      }
    }
    return false;
  }

  root.PwaShellRules = {
    shouldNetworkOnly: shouldNetworkOnly,
    isShellAsset: isShellAsset
  };
})(typeof self !== 'undefined' ? self : globalThis);
