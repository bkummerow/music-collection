# Dark mode color tokens (`--dm-*`)

Defined in [`assets/css/src/tokens.css`](../assets/css/src/tokens.css) under `[data-theme='dark']`.

## Roles

| Token | Role |
|-------|------|
| `--dm-canvas` | Page / table shell (black) |
| `--dm-surface` | Modals, sidebar, menus |
| `--dm-surface-raised` | Chips, secondary buttons, badges |
| `--dm-hover` | Row / header hover (`color-mix`) |
| `--dm-surface-hover` | Raised control hover |
| `--dm-text` | Primary text |
| `--dm-text-muted` | Secondary labels |
| `--dm-text-soft` | De-emphasized (75% mix) |
| `--dm-border` / `--dm-border-strong` | Dividers and emphasis borders |
| `--dm-accent` | Active filters, brand accent |
| `--dm-callout-bg` / `--dm-callout-border` | Info/warning/setup callouts |
| `--dm-input-*` | Light inputs on dark chrome |
| `--dm-message-error-*` / success via `--success-message*` | Flash messages |

## Maintenance

- Prefer editing `--dm-*` in `tokens.css`; rebuild with `npm run build:css`.
- Optional: `node .scripts/apply-dm-tokens.mjs` when adding new dark rules with hex literals (then extend the script map).
- Bump `CACHE_NAME` in `sw.js` when shipping CSS.
