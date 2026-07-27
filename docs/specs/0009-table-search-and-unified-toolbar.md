# Spec 0009 — Global quick-search + unified table toolbar

> Status: FROZEN CONTRACT. Full-stack. Builds on the generic domain-driven table
> (0002) and its filter/preferences/saved-views layers (0004/0005/0007). Backend
> and frontend implement against the shapes below without changing them.

## Goal

Two things, one block:

1. **Global quick-search** — a single search field that filters the table across
   a server-side allow-list of real columns (users → `name`, `email`; roles →
   `name`), server-side over ALL rows (SSRM), not just the loaded page.
2. **Unified toolbar** — the search, a live row counter, and the table controls
   (reset filters, saved views, an options menu, fullscreen) are fused into ONE
   bordered block flush with the grid — no detached buttons. Per-column filtering
   stays on the column header (revealed on hover, `suppressMenuHide:false`), so the
   toolbar carries no filter-visibility toggle.

## Contract

### `POST /api/tables/{domain}/rows` — add `search`

Request gains one optional field:

| field | type | rules |
|---|---|---|
| `search` | string \| null | `nullable, string, max:255` (`TableRowsRequest::SEARCH_MAX_LENGTH`) |

Applied server-side as a single **grouped OR-`LIKE`** over the definition's
`searchableColumnIds()` allow-list, **AND-combined** with `filterModel`. The term
is `trim()`-ed; blank ⇒ no search. Columns come exclusively from the definition
(never the request); the term is a LIKE-escaped **bound** parameter — never
interpolated (mirrors `FilterApplier` LIKE handling; correct on the MySQL prod
target where `\` is the default LIKE escape).

### `GET /api/tables/{domain}/columns` — add `searchable`

Config `data` gains:

| field | type | notes |
|---|---|---|
| `searchable` | string[] | searchable column ids; `[]` ⇒ no search box |

The frontend shows the search field only when non-empty and builds the
placeholder from those columns' localized labels (`Cerca nome/email…`).

### Definition surface

- `TableDefinition::searchableColumnIds(): array` — **real** base columns only
  (a bound `LIKE` runs on each). `AbstractTableDefinition` derives it from column
  declarations flagged `'searchable' => true`; `resolveConfig()` emits it.

## Frontend

- `TableView` fuses `TableToolbar` (new, presentational) above `DataTable` in a
  single `rounded-xl border` card; the grid drops its own wrapper border
  (`wrapperBorder:false`). Toolbar right side: reset-filters (only when active),
  saved views (icon), options menu (export = "soon", reset layout when
  customized), fullscreen. Left: search (⌘K focus) + live "N righe" counter.
- Client-only toolbar state (search term + ⌘K, fullscreen, row count) lives in
  `useTableToolbarState`. The applied search term is held in a ref read lazily by
  the SSRM datasource (`createSsrmDatasource(domain, getSearch)`); typing debounces
  a `refreshServerSide({ purge:true })` — the datasource is never rebuilt.
  Fullscreen locks background scroll and exits on Escape.
- `DataTable` gains `onRowCountChanged` (from `onModelUpdated`). Column filters
  stay on the header menu (hover), so no floating-filter row is added.

## Security

- Search columns are a server-side allow-list; the raw term never reaches SQL as
  an identifier and is a bound, LIKE-escaped parameter. Over-length ⇒ 422.
- No new authorization surface: rows still gated by the definition's `viewAny`.

## Tests (all executed green)

- Backend `TableRowsSearchTest`: OR across columns, AND with a column filter,
  blank-term no-op, over-length 422, roles-domain single column. `TableConfigTest`
  asserts `searchable = ['name','email']`. Full suite 613 passed / 1 skip.
- Frontend `ssrm-datasource.test` (search included/omitted), `table-toolbar.test`
  (render, search emit, filter toggle, reset gating, fullscreen). tsc + ESLint clean.
