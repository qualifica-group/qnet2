# Architecture Decision Record

## ADR ID

0011

## Title

`for-select` API standard for entity-backed selects (first application: users-for-select feeding the Role-form multi-select)

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

Frontend forms increasingly need to populate `select` / multi-select components
from **entity-backed** lists (users, and in the future any domain entity). The
concrete trigger is the **Role form**, which must let an operator assign users to
a role via a multi-select.

Two existing patterns on this backend look adjacent but do **not** fit:

- **`GET /api/config` (ADR 0008/0009)** serves *enum* options (locale,
  `contact_type`, personal-data types). These are small, fixed, presentation-only
  vocabularies with no pagination, no search, no authorization. Entity-backed
  selects are the opposite: potentially large, must be searched server-side,
  paginated, and **authorization-gated**. The user has explicitly scoped
  enum-from-config selects **out**: they stay on `/api/config` untouched.
- **The generic table framework `GET /api/tables/{domain}/rows|columns` +
  `optionsFor()`** is a heavy AG Grid SSRM contract (columns, filter models, sort
  models, row actions, export). Using it to feed a `<select>` would be massive
  over-fetching and a confusing coupling. The user has explicitly decided
  for-select is a **new, simpler, dedicated concern**, not the table framework.

Verified codebase constraints this ADR builds on (read, not assumed):

- **Response envelopes** — `BaseApiController` provides `ok($data)` →
  `{ success, message, data }` and `paginatedResponse($items, $total, $offset,
  $limit)` →
  `{ items, export_link, pagination: { total, offset, limit, total_pages } }`.
  Both are reused **as-is**. `MAX_LIMIT = 100`. `validateRequest()` already
  validates `offset >= 0` and `1 <= limit <= 100`.
- **Authorization** — `BasePolicy` maps `{resource}.{ability}`. `UserPolicy`
  resource = `users` (so `users.viewAny` exists and is synced by
  `permissions:sync`). `RolePolicy` resource = `roles` (so `roles.update`
  exists). `Gate::before` grants super-admin everything, which is why hard guards
  live in Services, not Policies.
- **Privilege-escalation guards already exist in `UserService`**:
  `assignableRoleNames(actor)`, the private `authorizedRoles(actor, requested)`
  re-filter, `guardLastSuperAdminRoleRemoval()`, `guardLastSuperAdminDeletion()`,
  and `PRIVILEGED_ROLE = 'super-admin'`. `RoleService::update()` wraps
  `DB::transaction` and calls `guardSystemRole()` (blocks any super-admin role
  mutation). Role↔User assignment is **currently done only from the user side**
  via `$user->syncRoles()`.
- **DTOs** — `final readonly` in `App\DataObjects`, built by
  `FormRequest::toData()`. `UpdateRoleData`/`CreateRoleData` already distinguish
  "key not submitted" (`null` → leave untouched) from "explicit list".
- **Resources** are explicit allowlist projections (`UserResource`,
  `RoleResource`). Serialization to the client is **snake_case** everywhere.
- **Routes** follow `/api/{resource}` REST under `auth:sanctum` with
  `throttle:60,1` on authenticated reads.

TDD + 85% coverage (Pest) and SDD (spec before code) are mandatory.

---

## Amendment — 2026-07-31: the authorization gate is dropped

**Supersedes §6 below and every "gated by `{resource}.viewAny`" statement in
this ADR.** Every `*/for-select` endpoint is now gated by **`auth:sanctum`
alone** — no per-resource permission — aligning the 26 entity endpoints with
the geo reference endpoints (`states/for-select`), which were already open.

Reason (user decision, 2026-07-31). `viewAny` was doing double duty: "may
browse this module" and "may resolve the options of a dropdown in a form I am
entitled to fill". A commercial role that must file a request holds no browse
right on Registries / Sources / Business functions / Users, so every select in
that form answered 403 and rendered empty. The gate did not protect the form —
it broke it, in every form, not just Request management.

Consequences accepted with the decision:

- Any authenticated user can enumerate `{id, label, subtitle}` of every
  for-select resource, including registries, referents, leads, companies and
  users (whose `subtitle` is the email). This is a real widening of read
  access and was chosen knowingly over the granular alternative (a separate
  `selectAny` ability per resource, assignable in the role matrix).
- Two collaborators that mirrored the gate had to follow, or the change would
  have been self-defeating: `RelationValueScopeChecker::inScope()` (dropped its
  `{resource}.viewAny` pre-check — it would have refused a value the picker
  itself offered) and `ResolvesEditableColumns` (dropped
  `mayPickRelationValue()`, which marked a relation cell read-only for exactly
  the actor this amendment unblocks).
- Writes are untouched. `{resource}.create/update/delete`, the quick-create
  button (`<Can permission=...>`), the role-assignment privilege guards and the
  per-field permission matrix all keep their own gates.

---

## Decision

Introduce a thin, reusable **`for-select` convention** plus a shared base
`ForSelectResource`, and apply it to exactly one endpoint now:
**`GET /api/users/for-select`**. Add a **`users` membership list** to the
existing Role create/update flow, routing it through `UserService`'s existing
privilege guards. No table-framework coupling, no new dependency.

### 1. Route shape (the standard)

```
GET /api/{resource}/for-select
```

- First and only implementation now: `GET /api/users/for-select`.
- Lives **inside `auth:sanctum`**, in the existing `users` `throttle:60,1` group
  in `routes/api.php`, registered **before** the `users/{user}` show route is
  irrelevant (literal `for-select` segment vs bound `{user}`); to be safe and
  explicit, declare `GET users/for-select` **above** `GET users/{user}` so the
  literal wins over the route-model-bound wildcard.

### 2. Query parameters (the standard)

| Param        | Type            | Rules                                   | Meaning |
|--------------|-----------------|-----------------------------------------|---------|
| `search`     | string, optional| `nullable string max:255`               | Server-side case-insensitive match. For users: `name` OR `email`. |
| `offset`     | int, optional   | `sometimes integer min:0` (default `0`) | Pagination offset (rows to skip). Reuses `validateRequest()` semantics. |
| `limit`      | int, optional   | `sometimes integer min:1 max:100`       | Page size. Default `25`. Hard cap `MAX_LIMIT = 100`. |
| `ids[]`      | int[], optional | `sometimes array` + `ids.* integer`     | **Hydration** of already-selected values (edit mode). |

**Hydration via `ids[]` (chosen).** When `ids[]` is present, the endpoint returns
those specific items **in addition to** the normal paginated/searched page,
**deduplicated**, so the frontend can render labels for currently-selected
members even when they fall outside the current search/page window. `ids[]` items
bypass the `search` filter (they are explicit) but are still subject to the same
authorization and the same item projection. They do **not** count toward
`pagination.total` (which reflects the searchable population), and are appended
once; the frontend merges by `id`. (Rejected `include[]` naming and a separate
hydration endpoint — see Alternatives.)

### 3. Exact JSON item shape (the standard)

A for-select item is the **minimal** projection — never a full resource:

```jsonc
{
  "id": 42,                       // required — the value submitted back
  "label": "Jane Doe",            // required — primary text shown in the option
  "subtitle": "jane@acme.test",   // optional — secondary line (nullable, omit-or-null)
  "avatar": "data:image/png;...", // optional — small visual (nullable)
  "meta": { }                     // optional — small, flat, presentation-only bag
}
```

Rules binding every for-select resource:

- Keys are **snake_case** (`subtitle`, `avatar`, `meta`) — consistent with every
  client-facing key on this backend.
- `id` + `label` are **mandatory**; `subtitle`, `avatar`, `meta` are optional and
  a resource only emits the ones it has.
- `meta` is a **small, flat, non-sensitive** presentation bag (e.g. a status
  flag a custom option renderer needs). It is **not** an escape hatch for full
  entity data; anything heavy or sensitive does **not** belong in a select.
- The item is produced by a **`ForSelectResource`** base
  (`App\Http\Resources\Abstracts\ForSelectResource`) that defines the envelope
  shape and is extended per entity. `UserForSelectResource` maps a `User` →
  `{ id, label: name, subtitle: email, avatar: avatarDataUri() }`.

> Avatar note: `User::avatarDataUri()` inlines a `data:` URI (already used by
> `UserResource`). For a paginated select this can be heavy. Decision: the
> users-for-select item **omits `avatar` by default** and exposes it only if a
> later need is proven, to keep the payload minimal (the whole point of
> for-select). Documented as a deliberate omission, not an oversight.

### 4. Paginated envelope reuse

The endpoint returns **`paginatedResponse($items, $total, $offset, $limit)`**
unchanged:

```jsonc
// GET /api/users/for-select?search=ja&offset=0&limit=25  → 200
{
  "items": [ { "id": 42, "label": "Jane Doe", "subtitle": "jane@acme.test" } ],
  "export_link": null,
  "pagination": { "total": 137, "offset": 0, "limit": 25, "total_pages": 6 }
}
```

`export_link` is structurally present (it is part of the shared helper) and is
always `null` for for-select — selects are not exportable. The frontend ignores
it. No new envelope is invented.

### 5. Backend reusability — thin convention, not a framework

To keep this trivial to extend **without** premature abstraction
(`decision-making.md` → Avoid Premature Abstraction), the reusable surface is
deliberately small:

- **`App\Http\Resources\Abstracts\ForSelectResource`** — abstract `JsonResource`
  base owning the `{ id, label, subtitle?, avatar?, meta? }` contract. Concrete
  resources implement the mapping only. This is the one shared artefact; it is
  what guarantees every future select is identical in shape.
- **A `UserForSelectController` action + `UserService` query method** — the
  controller validates (`UserForSelectRequest`), authorizes
  (`$this->authorize('viewAny', User::class)` → `users.viewAny`), calls a service
  method that runs the searched/paginated/hydrated query and returns a typed
  **`ForSelectResult`** DTO (`items` collection + `total`), then wraps it in
  `paginatedResponse(...)`. Business logic (the query, the search, the
  hydration merge) lives in the **Service**, not the controller
  (`architecture.md` → thin controllers).
- **`App\DataObjects\Shared\ForSelectQuery`** (built by the FormRequest) carries
  `search`, `offset`, `limit`, `ids` into the service as a typed DTO (no magic
  array across the layer boundary). **`App\DataObjects\Shared\ForSelectResult`**
  carries `items` + `total` back. Both `final readonly`.

No abstract base **controller** and no generic query engine are introduced now:
with a single implementation that would be speculative. The `ForSelectResource`
base + the DTO pair are enough to make the second entity a copy-paste-and-map
exercise. When a 2nd/3rd select lands and the query logic visibly repeats, an
optional `ForSelectAction`/trait can be extracted then (tracked as deferred).

### 6. Authorization — two distinct gates

> SUPERSEDED by the 2026-07-31 amendment at the top of this file: the
> for-select gate is now `auth:sanctum` alone. The `roles.update` gate on the
> membership WRITE below is unchanged and still in force. Kept for the record.

- **The users-for-select endpoint is gated by `users.viewAny`.** Listing users to
  pick from is "viewing the user collection"; it reuses the existing
  `UserPolicy::viewAny` / `users.viewAny` permission. No new permission is
  invented.
- **Assigning users to a role from the Role form is gated by `roles.update`**
  (the operator is editing the role). This is enforced where role mutation
  already is: `RoleController::update`/`store` already call
  `authorize('update'|'create', ...)`. The new `users` list rides that same
  authorization — no separate gate, no new permission.
- **Both gates are server-side and independent.** A user who can edit roles but
  lacks `users.viewAny` cannot enumerate users via the select endpoint, and the
  membership write is independently authorized by `roles.update`. The frontend is
  never trusted (`security-standards.md` → Trust Nothing / Authorization First).

### 7. Where role-membership update lives — extend the Role flow + reuse the guards

This is the security-critical part. The Role form will submit a `users` list
(array of user ids) to **the existing role create/update endpoints**:

- **`StoreRoleRequest` / `UpdateRoleRequest`** gain
  `users` → `['sometimes','array']`, `users.*` →
  `['integer', Rule::exists('users','id')]`.
- **`CreateRoleData` / `UpdateRoleData`** gain a nullable `?array $users`
  (null = key not submitted → leave membership untouched; `[]` = explicit "remove
  all members"), mirroring the existing `permissions` semantics, plus a
  `hasUsers()` helper.
- **`RoleService::create`/`update`** sync membership **inside the existing
  `DB::transaction`**, after the existing permission sync. Membership is written
  from the role side but **must reuse the user-side privilege guards** verbatim.
  Because those guards (`authorizedRoles`, `guardLastSuperAdminRoleRemoval`,
  `assignableRoleNames`) currently live `private` in `UserService` and are keyed
  on the **actor**, the implementation MUST:

  1. Pass the **acting user** into `RoleService::create/update` (the controller
     already has `$request->user()`), exactly as `UserService` does.
  2. Promote the reusable guard logic to a **callable surface shared by both
     services** — the cleanest option is to expose, on `UserService`, public
     methods the `RoleService` calls (e.g. `assertActorMayManageRoleMembership`
     /`authorizedUserIdsForRole`), or extract a dedicated
     `RoleAssignmentGuard`/policy object both services depend on. Either way **the
     guard logic is written once** and both the user-side and role-side flows go
     through it. Duplicating the guard in `RoleService` is **forbidden**
     (`coding-standards.md` → Avoid Duplication; it would create two divergent
     copies of a security control).

  Concretely, when syncing a role's members the guard must enforce, **per
  affected user**, the same invariants the user side enforces:
  - **A non-super-admin actor may not add a user to, or remove a user from, the
    `super-admin` role.** When the role being edited **is** `super-admin`, the
    membership change is only permitted for a super-admin actor; for any other
    actor the `users` list for that role is rejected/ignored exactly as
    `authorizedRoles` filters out `super-admin` on the user side.
  - **The last super-admin must be protected.** Removing a user from the
    `super-admin` role (membership shrink) must run the same
    last-super-admin guard (`superAdminCount() <= 1` → 422) that
    `guardLastSuperAdminRoleRemoval` enforces on the user side. Assigning the
    `super-admin` role to additional users is only allowed for a super-admin
    actor.
  - `guardSystemRole()` currently **blocks all mutation of the super-admin role**
    in `RoleService::update`. Membership management of `super-admin` from the role
    side is therefore only reachable by a super-admin actor and must **not** be
    silently swallowed by `guardSystemRole`: the guard scope stays "name/
    permission mutation"; membership for super-admin follows the actor-based
    rule above. The Backend Agent must reconcile these two guards explicitly in
    the spec (see Blocking Questions / Validation).

  Membership write uses Spatie from the role side via
  `$role->users()->sync($authorizedUserIds)` (or per-user `assignRole`/
  `removeRole`) on the `model_has_roles` pivot — **after** filtering the
  requested ids through the shared guard. Sync runs in the same transaction so a
  failure never half-applies.

---

## Alternatives Considered

- **Reuse `GET /api/tables/{domain}/rows` (+ `optionsFor`) to feed selects** —
  rejected: massive over-fetch (columns, filter/sort models, actions, export),
  wrong contract, confusing coupling. The user explicitly decided for-select is a
  separate, simpler concern.
- **Put users on `/api/config`** — rejected: entity lists are large, searchable,
  paginated and authorization-gated; `/api/config` is public, unauthenticated,
  fixed-vocabulary enum metadata. Different problem entirely.
- **A full generic for-select engine now** (abstract base controller +
  config-driven entity registry, like the table framework) — rejected as
  premature abstraction with a single implementation. The `ForSelectResource`
  base + DTO pair give 90% of the reuse value at 10% of the cost; the engine can
  be extracted on the 2nd/3rd entity when the repetition is real.
- **A dedicated membership endpoint** (`POST /api/roles/{role}/users`) instead of
  extending the role create/update flow — rejected for now: it splits one logical
  "save the role form" into two writes (atomicity/UX cost) and duplicates
  authorization. The role form already PUT/PATCHes the role; membership rides the
  same transaction and the same `roles.update` gate. (Revisit if membership ever
  needs to be edited independently of the role.)
- **Hydration via `include[]` or a second round-trip** — rejected: `ids[]` is the
  clearest name for "also return these specific values", keeps hydration in one
  request, and avoids a separate endpoint. `include[]` is overloaded
  (eager-loading connotation in many APIs).
- **Embedding the avatar `data:` URI in every option by default** — rejected as
  default: it bloats a paginated payload, contradicting the "minimal data" goal.
  Kept as an optional field the base supports but the users select omits for now.
- **Duplicating the privilege guards into `RoleService`** — rejected: a security
  control copied in two places will drift. The guard logic is written once and
  shared by both services.

---

## Trade-offs

- **Advantages**: minimal payload purpose-built for selects; reuses the existing
  paginated envelope, the existing `users.viewAny`/`roles.update` gates, and the
  existing privilege guards (no new security surface invented); one shared
  `ForSelectResource` makes future selects trivial and uniform; no new
  dependency, no table-framework coupling; membership write is atomic with the
  role save.
- **Disadvantages**: a new endpoint family to maintain; the role flow now carries
  a third concern (name, permissions, **members**); reusing the user-side guards
  from the role side requires promoting private guard logic to a shared surface
  (a small, deliberate refactor of `UserService`).
- **What we give up**: a fully generic for-select engine (deferred until a 2nd
  entity proves the pattern); independent membership editing (deferred — it rides
  the role save for now); avatars in the users select (deferred).

---

## Consequences

- Frontend gets a stable, reusable async-paginated-multiselect contract it can
  implement once and point at any future `/api/{resource}/for-select`.
- The Role form can assign users with the **same** privilege-escalation
  protection as the user form — no weaker path to `super-admin` is introduced.
- **Security — PII exposure (`users.viewAny`)**: the users-for-select item exposes
  each user's **email address** in `subtitle`. `users.viewAny` therefore grants
  read access to user email addresses through this endpoint and must **not** be
  granted casually — treat it as a PII-bearing permission, scope it to roles that
  legitimately need to pick users, and keep the projection minimal (no extra PII
  in `subtitle`/`meta`). This is the single source of truth for this note; the API
  contract (`docs/api/0005-for-select.md`) references it.
- `ForSelectResource` + `ForSelectQuery`/`ForSelectResult` DTOs become the
  organization standard; ADRs for future selects reference this one.
- **Technical debt, tracked here**: (a) the generic `ForSelectAction`/registry is
  intentionally deferred to the 2nd implementation; (b) the user-side guard logic
  must be promoted to a shared surface — until then it is a single, reviewed
  refactor, not duplication; (c) the search uses `LIKE` on `name`/`email` and
  relies on indexes (see Risks) — a future move to full-text search is out of
  scope.

---

## Affected Agents

- **Backend Agent** (next owner): `ForSelectResource` base,
  `UserForSelectResource`, `UserForSelectController` + `UserForSelectRequest`,
  `ForSelectQuery`/`ForSelectResult` DTOs, `UserService` query method + promotion
  of the shared role-membership guard, `RoleService::create/update` membership
  sync (actor-aware, in-transaction), Store/Update Role request + DTO changes,
  route registration, Pest tests (≥85%). Writes the SDD spec first.
- **Security Agent** (required reviewer): validates that the role-side membership
  write cannot escalate privilege (super-admin add/remove, last-super-admin
  protection) and that both gates (`users.viewAny`, `roles.update`) are enforced
  server-side. Touches privilege-escalation surface → Security is mandatory.
- **Frontend Agent**: implements the reusable async paginated multi-select
  against the contract; wires it into the Role form with edit-mode hydration via
  `ids[]`.
- **Reviewer Agent / QA Agent**: code review + functional/regression validation
  (especially the last-super-admin and non-super-admin paths).
- **Documentation Agent**: API doc under `docs/api/` for `GET
  /api/users/for-select` and the for-select standard.

---

## Risks

- **Privilege escalation (highest)**: assigning users to `super-admin` from the
  role side bypassing the user-side guards. Mitigation: route the membership sync
  through the **same** actor-keyed guard logic, written once and shared; reject/
  filter `super-admin` membership changes for non-super-admin actors; enforce the
  last-super-admin guard on membership shrink. This MUST be covered by explicit
  failing-first Pest tests.
- **N+1 / payload size**: mapping users to options must eager-load nothing heavy;
  roles are not needed in the option. Keep the projection to `id,name,email`
  (select only needed columns). Avatars omitted by default to bound payload.
- **Search performance / indexes**: `LIKE`-based search on `users.name` /
  `users.email` over a growing table needs indexes. `email` is already unique
  (indexed); confirm/add an index on `name` if missing before relying on search
  at scale. Trailing-wildcard `name LIKE 'x%'` can use the index; leading-wildcard
  cannot — Backend must choose the search semantics deliberately and document it.
- **Breaking change**: none to existing endpoints — the `users` field on the role
  flow is additive (`sometimes`), and `/api/users/for-select` is new. Existing
  user-side role assignment is untouched.
- **Guard reconciliation**: `RoleService::guardSystemRole()` blocks super-admin
  role mutation; membership management of super-admin from the role side must be
  reconciled with it (scope the guard to name/permissions, handle membership via
  the actor rule). If not reconciled, either super-admin membership is wrongly
  blocked for super-admins or wrongly allowed for others — Backend must resolve in
  the spec.

---

## References

- ADR 0008 — `docs/adr/0008-public-bootstrap-config-endpoint.md` (enum-from-config
  selects, explicitly out of scope here).
- ADR 0002 — `docs/adr/0002-generic-domain-driven-table-registry.md` (the table
  framework, explicitly NOT reused for selects).
- `standards/architecture.md` (thin controllers, Services own business logic,
  DTOs across boundaries, serialized-payload casing).
- `standards/security-standards.md` (Trust Nothing, Authorization First, least
  privilege).
- Current code: `app/Http/Controllers/Abstract/BaseApiController.php`
  (`paginatedResponse`, `ok`, `MAX_LIMIT`, `validateRequest`),
  `app/Services/UserService.php` (privilege guards to reuse),
  `app/Services/RoleService.php` (transaction + `guardSystemRole`),
  `app/Http/Resources/UserResource.php`, `routes/api.php`.
