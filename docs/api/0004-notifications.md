# API Contract — User Notifications

> Frozen contract for the user notification system (Laravel native `database`
> channel). Companion of `docs/adr/0005-database-notifications.md`.
> Owner: Backend (implementation) / Architect (contract).

---

## Overview

Endpoints let the **authenticated user** manage **their own** notifications. All
routes are behind `auth:sanctum` + `throttle:60,1` and are **self-scoped by
construction**: they always operate on `auth()->user()->notifications()`. The
client never supplies a user id, so there is no cross-user access path. A single
notification is resolved through the relationship, so a foreign / unknown uuid
returns **404**.

| Purpose | Method + Path | Body | Success |
|---|---|---|---|
| List | `GET /api/notifications` | — (query params) | `paginatedResponse()` |
| Unread count | `GET /api/notifications/unread-count` | — | `ok()`, `data.count` + `data.latest` |
| Mark one read | `PATCH /api/notifications/{notification}/read` | — | `ok()`, `data` = resource |
| Mark all read | `POST /api/notifications/read-all` | — | `ok()`, `data.marked` |

### Notification resource shape

```jsonc
{
  "id": "9b1f...-uuid",
  "type": "App\\Notifications\\GenericNotification",
  "data": { "title": "…", "message": "…", "level": "info", "action_url": null },
  "read_at": "2026-06-15T10:00:00+00:00", // null when unread
  "created_at": "2026-06-15T09:59:00+00:00"
}
```

`data` is **normalized server-side** (via the `NotificationData` value object),
so the contract is guaranteed rather than best-effort:

- The four keys `title`, `message`, `level`, `action_url` are **always present**.
- `level` is **always** one of `info | success | warning | error` (anything
  unknown/missing falls back to `info`).
- `title`, `message`, `action_url` are `string | null` — `null` when the
  producing notification omitted them; the client applies its own fallbacks.

This holds for every stored row, including legacy ones, because the resource
normalizes on read through the same value object used on write.

---

## 1. List — `GET /api/notifications`

Query params (validated):

| Param | Rules | Default | Meaning |
|---|---|---|---|
| `offset` | `integer min:0` | `0` | page offset (0-based) |
| `limit` | `integer min:1 max:100` | `15` | page size |
| `filter` | `in:all,unread` | `all` | restrict to unread only |

Ordered by `created_at desc`. Response is the standard paginated envelope:

```jsonc
{
  "items": [ /* notification resources */ ],
  "export_link": null,
  "pagination": { "total": 42, "offset": 0, "limit": 15, "total_pages": 3 }
}
```

## 2. Unread count — `GET /api/notifications/unread-count`

```jsonc
{
  "success": true,
  "message": "OK",
  "data": {
    "count": 7,
    "latest": { /* notification resource, the most recent UNREAD one */ }
  }
}
```

`latest` is the most recent unread notification (same resource shape as the
list), or `null` when `count` is `0`. It rides along with the count so the
client polls **once** for both the bell badge and the browser tab title, with a
single source of truth for the number.

Lightweight; intended for frequent polling: a count plus, only when there is
something unread, one indexed row.

## 3. Mark one read — `PATCH /api/notifications/{notification}/read`

`{notification}` is the notification uuid. Resolved via the user relationship;
foreign/unknown uuid → **404**. Idempotent: marking an already-read notification
returns it unchanged. Returns the updated resource in `data`.

## 4. Mark all read — `POST /api/notifications/read-all`

Marks every unread notification of the user as read.

```jsonc
{ "success": true, "message": "OK", "data": { "marked": 7 } }
```

---

## Error contract

| Condition | Status |
|---|---|
| Unauthenticated | **401** |
| `{notification}` not owned / unknown | **404** |
| Invalid query param (`offset`/`limit`/`filter`) | **422** |
| Rate limit exceeded | **429** |
| Unexpected server error | **500** (generic message) |

---

## Frontend integration

- API layer: `src/features/notifications/api.ts` (axios via `apiClient`).
- The list is consumed as **infinite scroll** (`useInfiniteQuery`): the panel
  loads pages of `limit=15` on demand, advancing `offset` from the response
  `pagination` (`getNextPageParam` returns the next offset until
  `offset + limit >= total`). A bottom `IntersectionObserver` sentinel inside the
  scroll container triggers the next page fetch.
- Polling cadence is environment-configurable:
  `VITE_NOTIFICATIONS_POLL_INTERVAL` (milliseconds, default `30000`). The
  unread-count is polled on this interval; the list (loaded pages) is refreshed
  on the same interval only while the panel is open. Polling is disabled when
  unauthenticated and paused when the tab is hidden.
