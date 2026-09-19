# Style Search Docs Design

**Date:** 2026-09-19  
**Status:** Approved  
**Site:** personal_site (Music Collection Manager)

## Problem

Docs and audits imply free-text search matches **style**, or that server-side style search is missing. In reality:

- Free-text search matches **artist** and **album** only.
- Style is searched via `style:` / `genre:` / `type:` prefixes, or via style **facet** / stats chips.
- That behavior already lives in `CollectionListHelper` (collection `action=albums` path). The unused `MusicCollection::getAllAlbums($filter, $search)` SQL-style `LIKE` on artist/album is **not** the list search path.

## Goals

- Align `readme.md` and `INSTALL.md` with actual search UX and server behavior.
- Make it obvious how to search by style (`style: rock`, etc.) vs typing a bare style word.

## Non-goals

- Changing free-text to OR-match style.
- Changing `CollectionListHelper`, JS search, or `getAllAlbums()`.
- Implementing in-app lyrics fetch, PWA, CSRF (already done), or tests.

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Free-text | Artist + album substring only |
| Style in search box | Prefix only: `style:`, `genre:`, `type:` |
| Facets | Unchanged (exact style chip / stats filter) |
| Implementation | **Docs only** |

---

## Design

### 1. Documented behavior

| Input | Result |
|-------|--------|
| `fugazi` | Albums whose artist or album name contains `fugazi` |
| `style: rock` (also `genre:` / `type:`) | Albums whose comma-split `style` field contains `rock` (when style facet is empty) |
| Style facet / stats chip | Exact style facet filter (existing) |
| Bare `rock` | Artist/album match only — **not** style |

Prefix matching and facet precedence stay as implemented in `collection_list_filter()`.

### 2. File edits

**`readme.md`**

- Feature bullet “Search by artist, album, or style” → state free-text vs `style:` / facet.
- Features list line that says “Search by artist or album name” → same clarification if still present.
- **Searching and Filtering** section: document free-text, style prefixes with examples, and facet clicks.

**`INSTALL.md`**

- Under **Collection list paging**, add one sentence: style in the search box requires a `style:` / `genre:` / `type:` prefix; facet filters remain available without a prefix.

**Unchanged**

- Search input placeholder (already shows `style: rock`).
- All PHP/JS/CSS.

### 3. Verification

- Grep `readme.md` / `INSTALL.md` for leftover claims that bare search matches style.
- Manual spot-check: docs describe `style: rock` and facet paths; no claim that typing `rock` alone filters by style.

## Out of scope (explicit)

- Adding style to free-text OR logic.
- Hygiene comments on `getAllAlbums()`.
- Automated tests for `CollectionListHelper` search.
