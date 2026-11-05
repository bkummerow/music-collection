/**
 * Bundle @import graph and minify native CSS into assets/css/*.css
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { transform } from 'lightningcss';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const srcRoot = path.join(root, 'assets/css/src');

const IMPORT_RE =
  /@import url\(['"]?\.\/([^'"]+)['"]?\)\s*(layer\(([^)]+)\))?\s*;/g;

/**
 * Flatten relative @import url() directives into one stylesheet string.
 *
 * @param {string} entryPath Absolute path to entry CSS.
 * @param {Set<string>} seen Circular-import guard.
 * @return {string}
 */
function bundleImports(entryPath, seen) {
  if (seen.has(entryPath)) {
    return '';
  }
  seen.add(entryPath);

  const dir = path.dirname(entryPath);
  let css = fs.readFileSync(entryPath, 'utf8');

  css = css.replace(IMPORT_RE, (match, rel, layerClause, layerName) => {
    const importedPath = path.resolve(dir, rel);
    const importedCss = bundleImports(importedPath, seen);
    if (layerClause && layerName) {
      return `@layer ${layerName} {\n${importedCss}\n}\n`;
    }
    return `${importedCss}\n`;
  });

  return css;
}

const BUILDS = [
  ['critical.entry.css', 'assets/css/critical.css'],
  ['critical-dark.entry.css', 'assets/css/critical-dark.css'],
  ['main.entry.css', 'assets/css/main.css'],
];

for (const [entryName, outRelative] of BUILDS) {
  const entryPath = path.join(srcRoot, entryName);
  const bundled = bundleImports(entryPath, new Set());
  const result = transform({
    filename: entryPath,
    code: Buffer.from(bundled),
    minify: true,
    sourceMap: true,
    projectRoot: root,
    targets: {
      chrome: 100 << 16,
      safari: (15 << 16) | (4 << 8),
      firefox: 100 << 16,
    },
  });

  const outPath = path.join(root, outRelative);
  fs.writeFileSync(outPath, result.code);
  if (result.map) {
    fs.writeFileSync(`${outPath}.map`, result.map);
  }
  console.info('Built', outRelative, `(${result.code.length} bytes)`);
}
