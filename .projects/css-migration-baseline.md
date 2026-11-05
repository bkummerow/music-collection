# CSS migration baseline (SCSS → native CSS)

Captured before native CSS pipeline replaced Dart Sass (2026-11-05).

## Compiled artifact hashes (SHA-256)

| File | Bytes | SHA-256 |
|------|------:|---------|
| `assets/css/main.css` | 227597 | `3ee059f286cd333b60afab30869523ab93f7a11ef0a2f2b008c01d0a9ccfeaa8` |
| `assets/css/critical.css` | 7670 | `c8e9f6e1e4744489bdf232916c944feff0e6eb8a299c375fbfa71eebe0d7a890` |
| `assets/css/critical-dark.css` | 3233 | `8028ecb305e386455271ae42a5d899b5a48b2ee1fba3d096fd2d1de70631bddd` |

## Browser floor

- **Parity testing:** latest Chrome, Safari, Firefox; iOS Safari (PWA + camera barcode flows).
- **`@scope` (optional Phase 4):** progressive enhancement only; requires Safari ≥ 17.4, Firefox ≥ 128, Chrome ≥ 118. Unscoped rules retained until floor is documented as mandatory.

## Post-migration compiled hashes (native CSS + Lightning CSS, 2026-11-05)

| File | Bytes | SHA-256 |
|------|------:|---------|
| `assets/css/main.css` | 225343 | `81842ef26879aa659b722d6e2b608898aa6e383a783f1dee05808f12057cc954` |
| `assets/css/critical.css` | 7457 | `311ad5b8d0b8c117edae29f21a6ef1655e12705894c5399e21a157dcc13279ef` |
| `assets/css/critical-dark.css` | 3091 | `6b35f85a5558e8c86bfbde709284d62ea5106db14db1ed1782d46fab5dfe1554` |

## Visual checklist (manual on music.lndo.site)

- [ ] Collection table light + dark
- [ ] Album / tracklist / cover modals
- [ ] Setup panel, login / passkey
- [ ] Mobile width (~375px)
