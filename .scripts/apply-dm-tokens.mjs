/**
 * Replace common dark-mode hex literals with var(--dm-*) / shared :root tokens.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

/** Longest hex first to avoid partial replacements. */
const REPLACEMENTS = [
  ['#ffffff', 'var(--dm-text)'],
  ['#FFFFFF', 'var(--dm-text)'],
  ['#000000', 'var(--dm-canvas)'],
  ['#333333', 'var(--dm-border)'],
  ['#404040', 'var(--dm-border-strong)'],
  ['#141414', 'var(--dm-hover)'],
  ['#161816', 'var(--dm-surface)'],
  ['#2d3748', 'var(--dm-callout-bg)'],
  ['#4a5568', 'var(--dm-callout-border)'],
  ['#2d2d2d', 'var(--dm-surface-raised)'],
  ['#2a2a2a', 'var(--dm-surface-raised)'],
  ['#3d3d3d', 'var(--dm-surface-raised)'],
  ['#3a3a3a', 'var(--dm-surface-hover)'],
  ['#1a1a1a', 'var(--dm-surface)'],
  ['#4a4a4a', 'var(--dm-border-strong)'],
  ['#555555', 'var(--dm-border-strong)'],
  ['#D2199A', 'var(--dm-accent)'],
  ['#d2199a', 'var(--dm-accent)'],
  ['#667eea', 'var(--primary-color)'],
  ['#5a67d8', 'var(--primary-dark)'],
  ['#f8f700', 'var(--dm-empty-state)'],
  ['#e0e0e0', 'var(--dm-text-muted)'],
  ['#e2e8f0', 'var(--dm-text-muted)'],
  ['#b0b0b0', 'var(--dm-text-muted)'],
  ['#a0aec0', 'var(--dm-text-muted)'],
  ['#888888', 'var(--dm-text-muted)'],
  ['#6c757d', 'var(--text-muted)'],
  ['#2c3e50', 'var(--dm-input-text)'],
  ['#fb4f60', 'var(--danger-message)'],
  ['#dc3545', 'var(--danger-color)'],
  ['#28a745', 'var(--success-color)'],
  ['#218838', 'var(--success-dark)'],
  ['#1a6b2c', 'var(--success-button-hover)'],
  ['#2F855A', 'var(--dm-owned-btn-bg)'],
  ['#2f855a', 'var(--dm-owned-btn-bg)'],
  ['#246645', 'var(--dm-owned-btn-border)'],
  ['#e83db4', 'var(--demo-accent)'],
  ['#d42a9f', 'var(--demo-accent-hover)'],
  ['#c0258a', 'var(--demo-accent-border)'],
  ['#ff6b35', 'var(--demo-warning)'],
  ['#f8f9fa', 'var(--bg-secondary)'],
  ['#333', 'var(--dm-border)'],
  ['#666', 'var(--text-muted)'],
  ['rgba(255, 255, 255, 0.75)', 'var(--dm-text-soft)'],
  ['#721c24', 'var(--dm-message-error-text)'],
  ['#f8d7da', 'var(--dm-message-error-bg)'],
  ['#f5c6cb', 'var(--dm-message-error-border)'],
  ['#d4edda', 'var(--dm-message-success-bg)'],
  ['#c3e6cb', 'var(--dm-message-success-border)'],
  ['#8ec5ff', 'var(--dm-code-accent)'],
  ['#155724', 'var(--status-owned)'],
  ['#0f6674', 'var(--info-button-bg)'],
  ['#0c5460', 'var(--info-button-hover)'],
  ['#fff', 'var(--dm-text)'],
  ['#dee2e6', 'var(--border-color)'],
  ['#e1e5e9', 'var(--border-light)'],
  ['#d1ecf1', 'var(--info-color)'],
  ['#bee5eb', 'var(--info-color)'],
  ['#6a3d8a', 'var(--dm-discogs-accent)'],
  ['#5a6268', 'var(--text-muted)'],
];

function applyFile(relativePath) {
  const filePath = path.join(root, relativePath);
  let css = fs.readFileSync(filePath, 'utf8');
  REPLACEMENTS.forEach(([from, to]) => {
    css = css.split(from).join(to);
  });
  css = css.replace(
    /--menu-surface: var\(--dm-surface\);/g,
    '--menu-surface: var(--dm-surface);'
  );
  fs.writeFileSync(filePath, css, 'utf8');
  console.info('Tokenized', relativePath);
}

applyFile('assets/css/src/themes/dark-mode.css');
applyFile('assets/css/src/critical/critical-dark-main.css');
