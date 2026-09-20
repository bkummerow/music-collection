/* Music Collection — app-shell service worker */
importScripts('assets/js/pwa-shell-rules.js');

var CACHE_PREFIX = 'music-shell-';
var CACHE_NAME = 'music-shell-v6';

var PRECACHE_URLS = [
  'assets/css/critical.css',
  'assets/css/critical-dark.css',
  'assets/css/main.css',
  'assets/js/app.min.js',
  'assets/js/demo.min.js',
  'favicon.ico',
  'favicon-16x16.png',
  'favicon-32x32.png',
  'apple-touch-icon.png',
  'android-chrome-192x192.png',
  'android-chrome-512x512.png'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(PRECACHE_URLS);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.map(function (key) {
          if (key.indexOf(CACHE_PREFIX) === 0 && key !== CACHE_NAME) {
            return caches.delete(key);
          }
          return undefined;
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  var url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }

  // Cross-origin: network only
  if (url.origin !== self.location.origin) {
    return;
  }

  // Navigations and network-only paths: do not intercept with cache
  if (request.mode === 'navigate' || PwaShellRules.shouldNetworkOnly(url)) {
    return;
  }

  if (!PwaShellRules.isShellAsset(url)) {
    return;
  }

  event.respondWith(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.match(request).then(function (cached) {
        if (cached) {
          return cached;
        }
        return fetch(request).then(function (response) {
          if (response && response.ok && response.type === 'basic') {
            cache.put(request, response.clone());
          }
          return response;
        });
      });
    })
  );
});
