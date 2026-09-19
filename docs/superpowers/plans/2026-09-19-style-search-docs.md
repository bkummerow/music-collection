# Style Search Docs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align `readme.md` and `INSTALL.md` with actual collection search behavior: free-text matches artist/album only; style requires `style:` / `genre:` / `type:` or a style facet.

**Architecture:** Documentation-only. No PHP, JS, or CSS changes. Collection search already lives in `services/CollectionListHelper.php` (`collection_list_filter`).

**Tech Stack:** Markdown docs in the personal_site repo.

## Global Constraints

- Free-text search: artist + album substring only (do not claim bare terms match style).
- Style in search box: prefixes `style:`, `genre:`, `type:` only (example: `style: rock`).
- Style facets / stats chips: unchanged; document as an alternate path.
- Do not modify `CollectionListHelper`, `app.js`, `getAllAlbums()`, or the search placeholder.
- Do not stage or commit `*.json` catalog files.

---

### Task 1: Clarify search behavior in `readme.md`

**Files:**
- Modify: `readme.md` (feature bullets ~26, ~66; Searching and Filtering ~309–315)
- Spec: `docs/superpowers/specs/2026-09-19-style-search-docs-design.md`

**Interfaces:**
- Consumes: Locked decisions from the style-search-docs design spec
- Produces: Accurate user-facing search docs in `readme.md`

- [ ] **Step 1: Update the Advanced Search feature bullet**

In `readme.md`, replace:

```markdown
- Search by artist, album, or style
```

with:

```markdown
- Search by artist or album name; search by style with `style:` / `genre:` / `type:` (or use style filters)
```

- [ ] **Step 2: Update the Features list Search & Filter bullet**

Replace:

```markdown
- **Search & Filter**: Search by artist or album name, filter by owned/wanted status
```

with:

```markdown
- **Search & Filter**: Free-text search matches artist or album name; use `style:` / `genre:` / `type:` (or style facets) for style. Filter by owned/wanted status
```

- [ ] **Step 3: Expand Searching and Filtering**

Replace the **Searching and Filtering** block (the Search bullet and Filter list) with:

```markdown
### Searching and Filtering

- **Search (free-text)**: Use the search box to find albums by **artist** or **album** name (substring match). A bare word such as `rock` does **not** filter by style.
- **Search by style**: Type a prefix in the search box, for example `style: rock`, `genre: jazz`, or `type: punk`. Matching is against each album’s comma-separated style list.
- **Style facets**: Click a style in statistics / facet UI to filter by that exact style (no prefix needed).
- **Filter**: Use the filter buttons to show:
  - All Albums
  - Albums you own
  - Albums you want to own
```

Leave **Option B: Search by artist and album name** (Discogs add-album flow around line 272) unchanged — that is Discogs lookup, not collection list search.

- [ ] **Step 4: Verify no leftover false claims in readme**

Run from repo root:

```bash
rg -n -i 'search by artist, album, or style|search box to find albums by artist or album name' readme.md || true
rg -n 'Search by artist or album name, filter' readme.md || true
```

Expected: no matches for the old vague bullets. Confirm the new wording appears:

```bash
rg -n 'style:|free-text|does \*\*not\*\* filter by style' readme.md
```

Expected: matches in the updated sections.

- [ ] **Step 5: Commit**

```bash
git add readme.md
git commit -m "$(cat <<'EOF'
Clarify collection search vs style prefix in readme.

EOF
)"
```

Do not add `data/*.json`.

---

### Task 2: Clarify style search in `INSTALL.md` and final check

**Files:**
- Modify: `INSTALL.md` (Collection list paging ~157–159)
- Verify: `readme.md` (already updated in Task 1)

**Interfaces:**
- Consumes: Same documented behavior as Task 1
- Produces: Operator-facing note under collection paging

- [ ] **Step 1: Extend Collection list paging**

In `INSTALL.md`, under `### Collection list paging`, keep the existing paging paragraph and append this sentence (same paragraph or immediately after):

```markdown
Style search in the box requires a `style:`, `genre:`, or `type:` prefix (for example `style: rock`); clicking a style facet still filters without a prefix. Free-text search matches artist and album names only.
```

Full subsection after edit should read:

```markdown
### Collection list paging

On the main collection page, the album table loads **100 albums at a time** and fetches the next page as you scroll. Changing **search**, **Own/Want/Total filters**, **facet filters** (style, format, etc.), or **column sort** requests a fresh first page from the server (filter badge counts still come from collection stats, not from the loaded scroll window). Style search in the box requires a `style:`, `genre:`, or `type:` prefix (for example `style: rock`); clicking a style facet still filters without a prefix. Free-text search matches artist and album names only.
```

- [ ] **Step 2: Cross-doc grep verification**

```bash
rg -n -i 'bare|style:|free-text|artist or album' readme.md INSTALL.md
rg -n -i 'search by artist, album, or style' readme.md INSTALL.md || true
```

Expected: new clarifying lines present; the old “Search by artist, album, or style” bullet gone.

- [ ] **Step 3: Commit**

```bash
git add INSTALL.md
git commit -m "$(cat <<'EOF'
Document style search prefix under collection paging in INSTALL.

EOF
)"
```

Do not add `data/*.json`.

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Document free-text = artist/album only | Task 1 |
| Document `style:` / `genre:` / `type:` | Task 1, Task 2 |
| Document facet path | Task 1, Task 2 |
| Edit `readme.md` | Task 1 |
| Edit `INSTALL.md` | Task 2 |
| Leave placeholder / code unchanged | Global Constraints (no code tasks) |
| Grep verification | Task 1 Step 4, Task 2 Step 2 |
