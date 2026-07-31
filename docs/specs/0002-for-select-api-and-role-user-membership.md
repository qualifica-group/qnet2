# Feature Specification

> SDD spec. Companion of `docs/adr/0011-for-select-api-standard.md` (Architect)
> and the frozen wire contract `docs/api/0005-for-select.md` (Documentation).
> Owner: Backend Agent.

---

## Feature Name

`for-select` API standard (first application: `GET /api/users/for-select`) +
privilege-safe role↔user membership on the Role create/update flow.

## Status

APPROVED (implements ADR 0011 verbatim)

---

## Summary

Two additive backend changes, no breaking change to existing endpoints:

1. A thin, reusable **for-select** convention (shared `ForSelectResource` base +
   `ForSelectQuery`/`ForSelectResult` DTOs) and its first endpoint
   `GET /api/users/for-select`, feeding the Role-form user multi-select.
2. A `users` membership list added to the existing Role create/update flow,
   written **inside the existing transaction** and routed through the **same**
   privilege-escalation guards the user side already uses (no duplicated security
   control).

---

## Endpoint contract — `GET /api/users/for-select`

- **Auth**: `auth:sanctum`, in the existing users `throttle:60,1` group,
  declared ABOVE `users/{user}` (literal segment wins over the bound wildcard).
- **Authorization**: `auth:sanctum` only. The original `users.viewAny` gate was
  removed on 2026-07-31 (see the amendment at the top of ADR 0011): it made the
  select unreadable to actors entitled to fill the form.
- **Query params**:

  | Param    | Rules                                       | Default |
  |----------|---------------------------------------------|---------|
  | `search` | `nullable string max:255`                   | —       |
  | `offset` | `sometimes integer min:0`                   | 0       |
  | `limit`  | `sometimes integer min:1 max:100`           | 25      |
  | `ids`    | `sometimes array`; `ids.*` `integer`        | []      |

- **Search**: case-insensitive match on `name` OR `email`, leading+trailing
  wildcard (`LIKE %term%`). Chosen deliberately for UX (typeahead matches
  substrings); documented as not index-optimal for the leading wildcard. A
  `name` index is added (email already unique-indexed) to bound the scan.
- **`ids[]` hydration**: the listed user ids are appended to the page,
  **deduplicated**, **bypass `search`**, and **do NOT inflate `pagination.total`**
  (total reflects only the searchable population). Edit-mode hydration so the
  frontend can label already-selected members outside the current window.
- **Projection**: only `id, name, email` selected (no N+1, no avatar, no roles).
- **Item shape** (snake_case, `id`+`label` mandatory, optionals omitted when
  null): `{ id, label: name, subtitle: email }`. No `avatar`/`meta` for users
  (minimal payload, per ADR).
- **Envelope**: `BaseApiController::paginatedResponse($items, $total, $offset,
  $limit)` unchanged → `{ items, export_link: null, pagination: { total, offset,
  limit, total_pages } }`.

### Example response

```jsonc
// GET /api/users/for-select?search=ja&offset=0&limit=25
{
  "items": [ { "id": 42, "label": "Jane Doe", "subtitle": "jane@acme.test" } ],
  "export_link": null,
  "pagination": { "total": 137, "offset": 0, "limit": 25, "total_pages": 6 }
}
```

---

## Role flow — `users` membership

- `StoreRoleRequest` / `UpdateRoleRequest` gain:
  `users` → `['sometimes','array']`; `users.*` → `['integer', exists:users,id]`.
- `CreateRoleData` / `UpdateRoleData` gain `?array $users` + `hasUsers()`
  (null = not submitted → leave membership untouched; `[]` = remove all members).
- `RoleController::store/update` pass `$request->user()` (actor) into the service.
- `RoleService::create/update` sync membership INSIDE the existing
  `DB::transaction`, AFTER permission sync, via the shared guard.

---

## Privilege-escalation guards — single source of truth

A dedicated `App\Services\RoleAssignmentGuard` collaborator holds the role-
assignment security logic **once**. Both `UserService` and `RoleService` depend
on it (constructor-injected). `UserService` keeps its public API
(`assignableRoleNames`, used by the user FormRequest) but delegates to the guard.

Guard responsibilities (the exact rules already enforced on the user side):

- `PRIVILEGED_ROLE = 'super-admin'`.
- `assignableRoleNames(actor)`: every role for a super-admin actor; everyone
  else gets every role EXCEPT `super-admin`.
- `authorizedRoles(actor, requested)`: re-filter requested role NAMES against the
  assignable set (user side).
- `authorizedUserIdsForRole(actor, role, requestedUserIds)`: role side. If the
  role is `super-admin` and the actor is NOT super-admin → return the role's
  CURRENT member ids unchanged (the requested change is rejected/ignored, exactly
  as `authorizedRoles` filters out super-admin on the user side). Otherwise the
  requested ids pass through.
- `guardLastSuperAdminMembershipShrink(role, newUserIds)`: when the role being
  synced IS `super-admin`, if any current super-admin would be removed and
  `superAdminCount() <= 1` → abort 422 (same last-super-admin protection).
- `guardLastSuperAdminRoleRemoval(user, newRoleNames)`: user side (moved verbatim).

### Reconciling `guardSystemRole` (Architect blocking question)

`RoleService::guardSystemRole()` is **scoped to name/permission mutation only**.
On update it aborts 422 ONLY when the super-admin role's name or permissions are
being changed. Membership-only updates of the super-admin role are allowed to
proceed and are then governed by the actor rule in the guard:

- super-admin actor → may add/remove super-admin members (last-super-admin
  protected);
- non-super-admin actor → `authorizedUserIdsForRole` returns the current member
  set, so no membership change is applied (no escalation, no error needed beyond
  the existing 403 for `roles.update` they may still hold for normal roles).

`delete()` still blocks deleting the super-admin role entirely (unchanged).

---

## Acceptance criteria (→ Pest tests)

**UserForSelectTest**
- 401 without auth.
- 200 without `users.viewAny` (amended 2026-07-31, ADR 0011 — was 403).
- 200 + pagination shape (total, offset, limit, total_pages).
- search by name; search by email.
- `ids[]` appends selected users even when filtered out by search, deduplicated,
  and does NOT inflate `pagination.total`.
- `limit` max 100 enforced (422 on 101).
- item shape `{ id, label, subtitle }`.

**RoleUserMembershipTest**
- create role with `users` syncs membership.
- update role with `users` syncs membership; `users: []` removes all; omitted
  leaves membership untouched.
- non-super-admin actor CANNOT add super-admin members via role membership.
- super-admin actor CAN add super-admin members.
- last-super-admin protection on super-admin membership shrink (422).
- `roles.update` / `roles.create` authorization enforced.

Coverage ≥ 85% on new code (Pest).
