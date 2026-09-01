# API Contract — Commission Configurator and Quote Commissions

> Implemented contract for spec
> [`0066`](../specs/0066-commission-configurator.md) and ADR
> [`0017`](../adr/0017-commission-engine-and-applied-commission-snapshots.md).
> Owner: Backend (implementation) / Documentation (contract reference).

---

## Overview

The Commission Configurator stores reusable rules. A Quote consumes those rules
by persisting independent snapshots under revenue lines. Editing a snapshot
never edits its source configuration, and later configuration changes do not
retroactively change saved Quotes.

All endpoints require `auth:sanctum`. Standard API responses use:

```json
{ "success": true, "message": "OK", "data": {} }
```

Configurator detail and write responses also include the standard top-level
`permissions` block. Validation failures are `422`; unauthenticated requests
are `401`; denied abilities are `403`; missing records are `404`.

## Enum values

| Concept | Accepted values |
|---|---|
| Recipient role | `COMMERCIAL`, `REPORTER`, `SUPERVISOR`, `SUPPLIER` |
| Application scope | `PRODUCT_CATEGORY`, `PRODUCT`, `RECIPIENT` (spec 0089) |
| Commission type | `FIXED_AMOUNT`, `PERCENTAGE` |
| Configuration status | `ACTIVE`, `SUSPENDED` |
| Applied origin | `PRODUCT`, `PRODUCT_CATEGORY`, `RECIPIENT` (spec 0089), `MANUAL_OVERRIDE` |
| Recipient type (read-only) | `referent`, `user`, `registry` — morph aliases, derived from the role (spec 0089) |

## Configurator CRUD

| Method and path | Ability | Result |
|---|---|---|
| `GET /api/commission-configurations/{id}` | `commission-configurations.view` | Detail plus effective permissions |
| `POST /api/commission-configurations` | `commission-configurations.create` | Created detail, `201` |
| `PUT\|PATCH /api/commission-configurations/{id}` | `commission-configurations.update` | Updated detail |
| `DELETE /api/commission-configurations/{id}` | `commission-configurations.delete` | Empty `204` |
| `GET /api/meta/commission-configurations` | `commission-configurations.viewAny` | Field catalogue and create-context permissions |

There is no dedicated list endpoint. Listing, filtering, sorting, search and
export use the generic infrastructure:

- `GET /api/tables/commission-configurations/columns`
- `POST /api/tables/commission-configurations/rows`
- `POST /api/tables/commission-configurations/values`
- `POST /api/tables/commission-configurations/bulk-delete`
- `POST /api/exports/commission-configurations`
- `GET /api/exports/commission-configurations/{exportRun}`
- `GET /api/exports/commission-configurations/{exportRun}/download`
- `GET /api/activity-log/commission-configurations/{id}`

See [`0002-generic-tables.md`](0002-generic-tables.md) for the shared table
request, pagination, filter and row-action contract. Export generation is
asynchronous and requires `commission-configurations.export`. Activity requires
both `commission-configurations.viewActivity` and record-level `view`.

### Create payload

```json
{
  "name": "Commercial product rule",
  "recipient_role": "COMMERCIAL",
  "application_scope": "PRODUCT",
  "product_category_id": null,
  "product_id": 42,
  "recipient_id": null,
  "commission_type": "PERCENTAGE",
  "value": "5.5000",
  "priority": 10,
  "valid_from": "2026-07-29",
  "valid_until": null,
  "status": "ACTIVE",
  "internal_note": "Internal use only"
}
```

`name`, `recipient_role`, `application_scope`, `commission_type`, `value`,
`priority`, `valid_from` and `status` are required on create. `PATCH` is
partial.

Validation rules:

- `name` is at most 191 characters;
- `value` is non-negative, has at most four decimals and is at most
  `99999999999.9999`;
- `priority` is a non-negative integer;
- dates use `YYYY-MM-DD`, and `valid_until` cannot precede `valid_from`;
- `PRODUCT` requires `product_id` and prohibits `product_category_id`;
- `PRODUCT_CATEGORY` requires `product_category_id` and prohibits `product_id`;
- `RECIPIENT` (spec 0089) requires `recipient_id` and prohibits both
  `product_id` and `product_category_id`;
- `recipient_id` is optional and orthogonal to the scope: a `PRODUCT` or
  `PRODUCT_CATEGORY` rule may also carry one. It must reference a row of the
  table the ROLE implies — `COMMERCIAL`/`REPORTER` a referent, `SUPERVISOR` a
  user, `SUPPLIER` a registry — otherwise the request is rejected;
- `recipient_type` is NEVER accepted from the payload: the server derives it
  from `recipient_role`. Sending it has no effect;
- changing `recipient_role` on a rule that already has a recipient of a
  different type, without resubmitting `recipient_id`, is rejected with
  `commission_configurations.recipient_role_changed`;
- `internal_note` is nullable and at most 5,000 characters;
- field permissions are enforced server-side for both create and update.

### Resource

```json
{
  "id": 18,
  "name": "Commercial product rule",
  "recipient_role": "COMMERCIAL",
  "application_scope": "PRODUCT",
  "product_category_id": null,
  "product_category": null,
  "product_id": 42,
  "product": { "id": 42, "name": "Example product" },
  "recipient_type": null,
  "recipient_id": null,
  "recipient": null,
  "commission_type": "PERCENTAGE",
  "value": "5.5000",
  "priority": 10,
  "valid_from": "2026-07-29",
  "valid_until": null,
  "status": "ACTIVE",
  "internal_note": "Internal use only",
  "created_at": "2026-07-29T10:00:00.000000Z",
  "updated_at": "2026-07-29T10:00:00.000000Z"
}
```

Hidden fields are omitted, not returned as `null`. Hiding `product_id` or
`product_category_id` also removes its related summary object; hiding
`recipient_id` removes `recipient_type` and `recipient` with it (spec 0089).
`recipient` is `{ "id": int, "name": string }` when the rule targets one. The same field
visibility filters Configurator table columns/rows and activity-log changes.

### Protected deletion

A configuration referenced by any applied Quote-line snapshot cannot be
deleted, including when that snapshot was later changed to
`MANUAL_OVERRIDE`. The API returns `409` with a localized explanatory message.
The generic bulk-delete path delegates to the same guard. A restrictive foreign
key provides database-level protection.

## Rule resolution and calculation

Resolution is internal; there is no public generic resolver endpoint.

For each requested role, the server considers only `ACTIVE` rules whose
inclusive validity interval contains the reference date. Since spec 0089 the
chain has five rungs, first hit wins — the recipient dimension dominates the
product one:

1. rule bound to THIS role's recipient, scope Product;
2. rule bound to THIS role's recipient, scope Product Category;
3. rule bound to THIS role's recipient, scope `RECIPIENT` (any product);
4. otherwise role-generic rule (`recipient_type IS NULL`), scope Product;
5. otherwise role-generic rule (`recipient_type IS NULL`), scope Product Category;
6. otherwise no result.

Rungs 1-3 are skipped when the role has no recipient. If any of them hits, the
role-generic rungs are never queried. Rungs 4-5 exclude recipient-bound rules
explicitly, so a rule addressed to somebody else can never win as a generic one.

An applied commission resolved through rungs 1-3 carries `origin: "RECIPIENT"`,
whatever the winning rule's scope; `commission_configuration_id` keeps the exact
source. Within the same rung, higher `priority` wins, then later `valid_from`,
then higher configuration ID. Roles resolve independently.

If the role has no recipient, no applied commission is returned, persisted or
summarized. Recipient sources are:

| Role | Recipient type | Source |
|---|---|---|
| Commercial | `referent` | Quote commercial |
| Reporter | `referent` | Quote reporter |
| Supervisor | `user` | Quote supervisor |
| Supplier | `registry` | Product supplier |

Percentage amounts are `line net amount × value / 100`, excluding VAT. Fixed
amounts apply once per line and are not multiplied by quantity. The server
rounds the result to two decimals using half-up rounding and is authoritative.

## Quote commission defaults

`POST /api/quotes/commission-defaults` returns non-persisted drafts for the
current Quote form.

Authorization:

- without `quote_id`: `quotes.create`;
- with `quote_id`: `quotes.update` on that Quote;
- in both cases, the Quote `commissions` field must be editable.

Configurator permissions are not required.

### Request

```json
{
  "quote_id": 91,
  "product_id": 42,
  "line_net_amount": "200.00",
  "commercial_id": 11,
  "reporter_id": null,
  "supervisor_id": 7,
  "reference_date": "2026-07-29"
}
```

| Field | Rules and semantics |
|---|---|
| `quote_id` | Optional existing Quote. Submitted recipient IDs take precedence over its persisted ones; the Quote fills in only the roles the payload omits. |
| `product_id` | Required existing Product. Its category and supplier are loaded server-side. |
| `line_net_amount` | Required, non-negative, at most two decimals. |
| `commercial_id`, `reporter_id` | Optional existing Referent IDs. The open form's current values, which may differ from what the Quote has persisted. |
| `supervisor_id` | Optional existing User ID. Same semantics as above. |
| `reference_date` | Optional date/time. Defaults to the server's current timestamp. |

### Response data

`data` is an array containing zero or one draft per recipient-backed role with
an applicable rule:

```json
[
  {
    "recipient_role": "COMMERCIAL",
    "recipient_type": "referent",
    "recipient_id": 11,
    "commission_type": "PERCENTAGE",
    "value": "5.5000",
    "calculated_amount": "11.00",
    "internal_note": "Internal use only",
    "origin": "PRODUCT",
    "commission_configuration_id": 18
  }
]
```

This endpoint is a UI aid. Quote persistence repeats recipient discovery,
resolution and calculation; clients cannot make a configuration-derived draft
authoritative.

## Quote commission recipients

`POST /api/quotes/commission-recipients` returns the ONE identity each role may
be commissioned to on a given line. The recipient of a commission is never
chosen by the user: it is whoever was selected upstream, per the source table
above. A role whose upstream selection is empty returns `null` and cannot be
commissioned at all.

Authorization is identical to `commission-defaults`, except that the Quote
`commissions` and `commission_recipient` fields need only be VISIBLE (this
endpoint reads identities, it writes nothing).

### Request

```json
{
  "quote_id": 91,
  "product_id": 42,
  "commercial_id": 11,
  "reporter_id": null,
  "supervisor_id": 7
}
```

Same fields and same precedence as `commission-defaults`, minus
`line_net_amount` and `reference_date`.

### Response data

`data` is an object keyed by role, every role always present:

```json
{
  "COMMERCIAL": { "type": "referent", "id": 11, "name": "Anna Bianchi" },
  "REPORTER": null,
  "SUPERVISOR": { "type": "user", "id": 7, "name": "Ivo Rossi" },
  "SUPPLIER": { "type": "registry", "id": 3, "name": "ACME Spa" }
}
```

The write contract below enforces the same rule server-side, so a client that
ignores this lock is rejected rather than obeyed.

## Quote write contract

Commissions are accepted only under `offer_lines`; they are prohibited under
`cost_lines`.

A submitted commission must name the recipient the role resolves to for that
line (see above), against the role IDs submitted in the same request — falling
back to the Quote's persisted ones for roles the payload omits. Otherwise:

- role with no admissible recipient → 422 on
  `offer_lines.{i}.commissions.{j}.recipient_role`;
- recipient other than the resolved one → 422 on
  `offer_lines.{i}.commissions.{j}.recipient_id`.

```json
{
  "offer_lines": [
    {
      "id": 301,
      "product_id": 42,
      "quantity": "2.00",
      "unit_price": "100.00",
      "commissions": [
        {
          "id": 801,
          "recipient_role": "COMMERCIAL",
          "recipient_type": "referent",
          "recipient_id": 11,
          "commission_type": "PERCENTAGE",
          "value": "6.0000",
          "internal_note": "Quote-only exception",
          "origin": "MANUAL_OVERRIDE",
          "commission_configuration_id": 18
        }
      ]
    }
  ]
}
```

Rules:

- at most four commissions are accepted per line;
- `recipient_role` must be distinct within the line;
- `calculated_amount` is prohibited input;
- manual recipients must match the role: Commercial/Reporter → `referent`,
  Supervisor → `user`, Supplier → `registry`;
- hidden or readonly nested fields are enforced server-side;
- for `PRODUCT` or `PRODUCT_CATEGORY` origins, submitted financial/source
  fields are not trusted: the server resolves the current applicable default;
- for `MANUAL_OVERRIDE`, authorized recipient, type, value and internal note
  changes apply only to that Quote line;
- an empty or stale submitted collection cannot suppress a currently
  applicable automatic rule;
- changing a line Product regenerates defaults and replaces previous automatic
  and manual values for that line;
- changing quantity or unit price recalculates persisted amounts;
- removing a revenue line cascades deletion to its applied commissions.

Quote line collections retain their existing full-replace semantics. Existing
line IDs are reconciled in place; omitted existing lines are deleted.

## Quote read contract and redaction

Each `offer_lines[]` resource can include:

```json
{
  "commissions": [
    {
      "id": 801,
      "recipient_role": "COMMERCIAL",
      "recipient_type": "referent",
      "recipient_id": 11,
      "recipient": { "id": 11, "name": "Example recipient" },
      "commission_type": "PERCENTAGE",
      "value": "6.0000",
      "calculated_amount": "12.00",
      "internal_note": "Quote-only exception",
      "origin": "MANUAL_OVERRIDE",
      "commission_configuration_id": 18,
      "created_at": "2026-07-29T10:00:00.000000Z",
      "updated_at": "2026-07-29T10:00:00.000000Z"
    }
  ]
}
```

`summary.commissions` contains all roles as decimal strings:

```json
{
  "commercial": "12.00",
  "reporter": "0.00",
  "supervisor": "0.00",
  "supplier": "0.00"
}
```

Quote field permissions redact data consistently from Quote detail, defaults
and aggregated activity:

| Hidden field permission | Omitted data |
|---|---|
| `commissions` | Entire line commission collection; commission summary is also omitted |
| `commission_recipient` | `recipient_type`, `recipient_id`, `recipient` |
| `commission_type` | `commission_type` |
| `commission_value` | `value`, `calculated_amount`; commission summary is also omitted |
| `commission_internal_note` | `internal_note` |

`recipient_role`, `origin` and `commission_configuration_id` remain visible
unless the entire collection is hidden. Readonly values remain visible but
changed manual input is rejected with `422`.

Applied snapshots are aggregated into
`GET /api/activity-log/quotes/{quoteId}`. Hidden commission collections remove
the child entries; hidden nested fields remove the corresponding changes.

## Permissions and rollout

Run after deployment:

```bash
cd backend
php artisan migrate --force
php artisan permissions:sync
```

The standard policy catalogue creates:

- `commission-configurations.viewAny`
- `commission-configurations.view`
- `commission-configurations.create`
- `commission-configurations.update`
- `commission-configurations.delete`
- `commission-configurations.export`
- `commission-configurations.import`
- `commission-configurations.viewActivity`

`import` is part of the shared policy catalogue but the Configurator currently
has no import workflow. Assign resource permissions and field-permission rows
to the intended roles after synchronization. Verify the queue worker because
Configurator exports are queue-backed.

Deploy the additive migrations before enabling navigation/UI. Existing Quotes
are not backfilled. For rollback, disable navigation and writes first; do not
drop the new tables until retention of applied snapshot data has been decided.

## Known limitations

- No retroactive population or recalculation of existing Quotes.
- No settlement, payment status, approval, reporting or accounting-export
  lifecycle.
- No generic external rule-resolution API.
- No tiers, thresholds, minimums, maximums or formulas beyond fixed/percentage.
- Quote collection updates remain last-write-wins; there is no optimistic
  locking for concurrent editors.
- Deleted child commission activity cannot be reconstructed by the current
  relation-walking activity aggregator.
- Saved snapshots intentionally retain their source configuration reference,
  so a later manual override can still prevent deletion of that configuration.

