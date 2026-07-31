# API Contract — `for-select` standard (entity-backed selects)

> Frozen contract for the org-wide **for-select** convention and its first
> endpoint `GET /api/users/for-select`. Companion of
> `docs/adr/0011-for-select-api-standard.md` (rationale, alternatives, trade-offs)
> and `docs/specs/0002-for-select-api-and-role-user-membership.md` (SDD spec).
> Owner: Backend (implementation) / Architect (contract).
> Confine backend ↔ frontend: this document is the shared source of truth for the
> wire shape. It does **not** restate the ADR — read the ADR for *why*.

---

## When to use for-select (vs `/api/config` vs the table framework)

Pick the right tool before adding a select. These three never overlap:

| Need | Use | Why |
|---|---|---|
| Populate a `select` / multi-select from a **large, searchable, authorization-gated entity list** (users, and future entities) | **for-select** (`GET /api/{resource}/for-select`) | Paginated, server-side search, `ids[]` hydration, gated, minimal projection. |
| Populate from a **small, fixed, presentation-only enum** (locale, `contact_type`, personal-data types) | **`GET /api/config`** (ADR 0008/0009) | Public, unauthenticated, no pagination/search. Different problem. |
| Render a full **data grid** (columns, filters, sort, row actions, export) | **table framework** (`GET/POST /api/tables/{domain}/…`, ADR 0002) | AG Grid SSRM contract; feeding a `<select>` with it is massive over-fetch. |

If the list is an entity, must be searched, and must be authorized → for-select.
Anything else → one of the other two.

---

## Endpoint shape (the standard)

```
GET /api/{resource}/for-select
```

- Lives **inside `auth:sanctum`** in the resource's existing `throttle:60,1` group.
- For route-model-bound resources, declare the literal `for-select` route
  **above** the `{model}` show route so the literal segment wins.
- First and only implementation now: **`GET /api/users/for-select`**.

### Query parameters

| Param | Rules | Default | Meaning |
|---|---|---|---|
| `search` | `nullable string max:255` | — | Server-side, case-insensitive match. For users: `name` OR `email` (`LIKE %term%`). |
| `offset` | `sometimes integer min:0` | `0` | Pagination offset (rows to skip). |
| `limit` | `sometimes integer min:1 max:100` | `25` | Page size. Hard cap `MAX_LIMIT = 100`. |
| `ids[]` | `sometimes array`; `ids.*` `integer` | `[]` | **Hydration** of already-selected values (edit mode). |

### `ids[]` hydration semantics

When `ids[]` is present, the listed items are returned **in addition to** the
normal searched/paginated page, and:

- they **bypass the `search` filter** (they are explicit selections);
- they are **deduplicated** against the page (merge by `id`);
- they are still subject to the **same authorization** and the **same item
  projection**;
- they do **not** count toward `pagination.total` (total reflects the searchable
  population only).

This lets the frontend render labels for currently-selected values even when
those values fall outside the current search/page window.

### Item shape

The minimal projection — never a full resource. Keys are **snake_case**:

```jsonc
{
  "id": 42,                       // required — the value submitted back
  "label": "Jane Doe",            // required — primary option text
  "subtitle": "jane@acme.test",   // optional — secondary line (omitted when null)
  "avatar": "data:image/png;...", // optional — small visual (omitted when null)
  "meta": { }                     // optional — small, flat, non-sensitive bag
}
```

- `id` + `label` are **mandatory**; `subtitle`, `avatar`, `meta` are optional and
  a resource emits only the ones it has (null optionals are **omitted**, not
  serialized as `null`).
- `meta` is a **small, flat, non-sensitive** presentation bag (e.g. a status flag
  a custom option renderer needs). It is **not** an escape hatch for full or
  sensitive entity data.

### Envelope

Reuses `BaseApiController::paginatedResponse($items, $total, $offset, $limit)`
**unchanged**. `export_link` is always `null` for selects (not exportable).

```jsonc
// GET /api/users/for-select?search=ja&offset=0&limit=25 → 200
{
  "items": [ { "id": 42, "label": "Jane Doe", "subtitle": "jane@acme.test" } ],
  "export_link": null,
  "pagination": { "total": 137, "offset": 0, "limit": 25, "total_pages": 6 }
}
```

### Error contract

| Condition | Status |
|---|---|
| Unauthenticated | **401** |
| Invalid query param (`offset`/`limit`/`search`/`ids.*`) | **422** |
| Rate limit exceeded | **429** |

There is no **403** row: since the 2026-07-31 amendment to ADR 0011 the only
gate is `auth:sanctum` (see "Security rule" below).

---

## `GET /api/users/for-select` (first implementation)

- **Authorization**: `auth:sanctum` only (amended 2026-07-31 — the former
  `users.viewAny` gate is gone).
- **Search**: case-insensitive on `name` OR `email`, substring (`LIKE %term%`).
  A `name` index is added (`email` already unique-indexed) to bound the scan;
  the leading wildcard is deliberately accepted for typeahead UX and is not
  index-optimal — see ADR 0011 Risks.
- **Projection**: only `id, name, email` selected — no roles, no avatar, no N+1.
- **Item**: `{ id, label: name, subtitle: email }`. No `avatar`/`meta`
  (minimal payload, deliberate per ADR 0011).

> **Security — PII exposure.** `subtitle` carries each user's **email address**,
> so since the 2026-07-31 amendment every authenticated user can read the user
> directory's emails through this endpoint. Accepted knowingly with that
> decision; the authoritative note lives in **ADR 0011 → Amendment**.

---

## Backend recipe (adding a new for-select endpoint)

The reusable surface is intentionally thin (no generic engine yet — see ADR 0011):

1. **Resource** — extend `App\Http\Resources\Abstracts\ForSelectResource` and
   implement `forSelectItem(Request): array` returning the
   `{ id, label, subtitle?, avatar?, meta? }` mapping. The base owns the envelope
   and the null-omission rule, so every select is identical in shape.
   Reference: `App\Http\Resources\UserForSelectResource`.
2. **Request** — a FormRequest validating the standard params and building the
   query DTO. Reference: `App\Http\Requests\Users\UserForSelectRequest`.
3. **DTOs** — carry input/output across the layer boundary (no magic arrays):
   `App\DataObjects\Shared\ForSelectQuery` (`search`, `offset`, `limit`, `ids`) in,
   `App\DataObjects\Shared\ForSelectResult` (`items` + `total`) out. Both
   `final readonly`.
4. **Controller** — thin: validate (FormRequest), **`authorize(...)`** the gate
   server-side, call the service, wrap the result in `paginatedResponse(...)`.
   Reference: `App\Http\Controllers\Users\UserForSelectController`.
5. **Service** — owns the business logic: the searched/paginated/hydrated query
   (select only the needed columns; merge `ids[]`; exclude them from `total`),
   returning a `ForSelectResult`. Reference: the for-select query method on
   `App\Services\UserService`.
6. **Route** — register `GET {resource}/for-select` inside `auth:sanctum` +
   `throttle:60,1`, above any `{model}` show route.

---

## Frontend recipe

- **`useForSelect`** (`src/features/for-select/use-for-select.ts`) — a reusable
  `useInfiniteQuery` over any `/{resource}/for-select` endpoint. Offset-based
  pagination derived from the envelope; selected `ids` hydrate **only the first
  page** and are intentionally excluded from the query key so a selection change
  never refetches the list. `flattenForSelectPages` de-duplicates loaded pages by
  `id`.
- **`AsyncPaginatedMultiSelect`**
  (`src/components/ui/async-paginated-multi-select.tsx`) — the reusable
  async-paginated multi-select UI bound to that hook (debounced search, infinite
  scroll, edit-mode hydration via `ids[]`).
- **Usage** — the Role form (`src/features/roles/role-form.tsx`) wires the
  multi-select to its `users` field, passing the current member ids as `ids` for
  hydration. The users endpoint API layer is
  `src/features/users/for-select-api.ts`.

---

## Security rule (binding for every for-select endpoint)

1. **`auth:sanctum` is the whole read gate** (amended 2026-07-31, ADR 0011). A
   for-select endpoint carries **no** per-resource permission check: the option
   list of a module must answer even to an actor who cannot browse that module,
   otherwise every form they are entitled to fill renders empty selects. Do not
   re-add an `authorize(...)` call to a `*ForSelectController` — and do not
   mirror one in a collaborator either (see `RelationValueScopeChecker`).
   Authentication is still mandatory: these endpoints are never public.
2. **Minimal projection.** Emit only what the option needs (`id`, `label`, and at
   most a non-sensitive `subtitle`/`avatar`/`meta`). Do not leak entity internals
   or extra PII through a select.
3. **PII awareness.** If a `subtitle` (or any field) carries PII — as `email`
   does for users — every authenticated user can now read it through this
   endpoint. Weigh that before adding a PII-bearing field to an item; the
   projection is the only remaining control. See ADR 0011 → Amendment.

---

## Related role↔user membership

Assigning users to a role consumes this endpoint from the Role form but is a
**separate write**, gated by `roles.update` and routed through the shared
privilege-escalation guards. `GET /api/roles/{id}` returns `users: number[]`
(member ids) and the role create/update flow accepts an optional `users: number[]`
(omitted = untouched, `[]` = clear). Full contract and guard rules:
`docs/specs/0002-for-select-api-and-role-user-membership.md` and ADR 0011 §6–§7.
