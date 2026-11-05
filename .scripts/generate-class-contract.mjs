/**
 * Collect CSS class names referenced from PHP templates and app.js.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const outFile = path.join(root, '.projects/css-class-contract.txt');

const classSet = new Set();

function scanFile(filePath) {
  const text = fs.readFileSync(filePath, 'utf8');
  const classAttr = /class=["']([^"']+)["']/g;
  let match;
  while ((match = classAttr.exec(text)) !== null) {
    match[1].split(/\s+/).forEach((c) => {
      if (c) {
        classSet.add(c);
      }
    });
  }

  const jsPatterns = [
    /classList\.(?:add|remove|toggle|contains)\(['"]([^'"]+)['"]\)/g,
    /className\s*=\s*['"]([^'"]+)['"]/g,
    /classList\.toggle\(['"]([^'"]+)['"]/g,
    /\.querySelector(?:All)?\(['"]\.([^'"\s.#[]+)['"]\)/g,
  ];
  jsPatterns.forEach((re) => {
    while ((match = re.exec(text)) !== null) {
      classSet.add(match[1]);
    }
  });
}

function walk(dir, extRe) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(full, extRe);
    } else if (extRe.test(entry.name)) {
      scanFile(full);
    }
  }
}

walk(path.join(root, 'components'), /\.php$/);
walk(root, /^index\.php$/);
scanFile(path.join(root, 'assets/js/app.js'));
scanFile(path.join(root, 'assets/js/demo.js'));

const sorted = [...classSet].sort();
fs.writeFileSync(outFile, sorted.join('\n') + '\n', 'utf8');
console.info(`Wrote ${sorted.length} class names to ${outFile}`);
