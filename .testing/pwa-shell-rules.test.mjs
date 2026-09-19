import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const code = fs.readFileSync(path.join(root, 'assets/js/pwa-shell-rules.js'), 'utf8');
const sandbox = { self: {}, console };
sandbox.self = sandbox;
vm.runInNewContext(code, sandbox);
const { shouldNetworkOnly, isShellAsset } = sandbox.PwaShellRules;

const origin = 'https://music.lndo.site';

function u(pathname) {
  return new URL(pathname, origin);
}

assert.equal(shouldNetworkOnly(u('/index.php')), true);
assert.equal(shouldNetworkOnly(u('/api/music_api.php?action=albums')), true);
assert.equal(shouldNetworkOnly(u('/data/music_collection.json')), true);
assert.equal(shouldNetworkOnly(u('/setup.php')), true);
assert.equal(shouldNetworkOnly(u('/assets/css/main.css')), false);

assert.equal(isShellAsset(u('/assets/css/main.css')), true);
assert.equal(isShellAsset(u('/assets/js/app.min.js')), true);
assert.equal(isShellAsset(u('/assets/js/app.min.js?v=123')), true);
assert.equal(isShellAsset(u('/favicon.ico')), true);
assert.equal(isShellAsset(u('/android-chrome-192x192.png')), true);
assert.equal(isShellAsset(u('/api/music_api.php')), false);
assert.equal(isShellAsset(u('/index.php')), false);

console.log('pwa-shell-rules: all assertions passed');
