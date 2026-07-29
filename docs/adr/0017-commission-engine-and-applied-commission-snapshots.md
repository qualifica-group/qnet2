# Architecture Decision Record

## ADR ID

0017

## Title

Reusable commission rules, applied Quote-line snapshots, and ID-aware
full-replace reconciliation

## Status

ACCEPTED — the user approved the product defaults on 2026-07-29 and explicitly
decided that a role without a recipient produces no applied commission.

## Date

2026-07-29

---

## Context

Spec 0066 introduces an independent Commission Configurator and makes Quotes
its first consumer. The module must follow the existing Laravel Layered Service
Architecture, resource/field authorization, generic backend-driven table,
export, and aggregated activity-log patterns.

The existing Quote write contract has two important constraints:

- `offer_lines` and `cost_lines` are independently full-replaced when their key
  is submitted;
- `QuoteLineWriter` currently implements full replacement by deleting and
  recreating every row of the submitted line type.

Applied commissions must instead retain historical values, manual overrides,
source provenance, recipient identity, and queryability. Deleting and
recreating every Quote line would unnecessarily change line and commission
identities and would make meaningful child activity history difficult to
retain.

The architecture must also avoid coupling the reusable rule resolver to Quotes.
Quote-specific recipient discovery and persistence belong in a Quote
integration layer, not in the rule engine.

The percentage base, fixed-amount behavior, eligible Quote line types, validity
date, priority direction/tie-break, missing-recipient behavior, product-change
behavior, and recipient-picker scope are not defined. They affect financial
results or destructive replacement behavior and therefore cannot be invented
by the Architect.

## Decision

### 1. Domain separation

Use two distinct domain concepts:

1. `CommissionConfiguration`: an independently managed reusable rule.
2. `QuoteLineCommission`: the persistent applied snapshot for one Quote line
   and one recipient role.

An applied snapshot never reads its financial values live from the source
configuration. Later configuration edits do not alter saved Quotes.

### 2. Minimum data schema

Create `commission_configurations` with:

- `id`
- `name`
- `recipient_role`
- `application_scope`
- nullable `product_category_id`
- nullable `product_id`
- `commission_type`
- `value` as `decimal(15, 4)`
- `priority` as integer
- `valid_from` as date
- nullable `valid_until` as date
- `status`
- nullable `internal_note`
- timestamps

Use string-backed PHP enums for:

- recipient role: `COMMERCIAL`, `REPORTER`, `SUPERVISOR`, `SUPPLIER`;
- application scope: `PRODUCT_CATEGORY`, `PRODUCT`;
- commission type: `FIXED_AMOUNT`, `PERCENTAGE`;
- configuration status: `ACTIVE`, `SUSPENDED`.

The application and database constraints must enforce:

- category scope: `product_category_id` is present and `product_id` is null;
- product scope: `product_id` is present and `product_category_id` is null;
- `value >= 0`;
- `valid_until` is null or not earlier than `valid_from`.

Create `quote_line_commissions` with:

- `id`
- `quote_line_id`
- nullable `commission_configuration_id`
- `recipient_role`
- nullable polymorphic `recipient_type` and `recipient_id`
- `commission_type`
- snapshot `value` as `decimal(15, 4)`
- authoritative `calculated_amount` as `decimal(15, 2)`
- nullable snapshot `internal_note`
- `origin`
- timestamps

`origin` is a string-backed enum: `PRODUCT`, `PRODUCT_CATEGORY`,
`MANUAL_OVERRIDE`.

There is at most one applied commission per Quote line and recipient role,
enforced by a unique constraint on
`(quote_line_id, recipient_role)`. A nullable
`commission_configuration_id` uses `restrictOnDelete`; it remains populated
after a manual override when that snapshot originally came from a rule. This
retains provenance and makes the required delete guard reliable. A fully
manual commission with no source rule may keep it null if product scope allows
creating such a row.

The polymorphic recipient is necessary because the existing role sources use
different models: Commercial and Reporter use `Referent`, Supervisor uses
`User`, and Supplier uses `Registry`. Only aliases in the enforced morph map
are accepted. FormRequest validation and the Quote application service must
enforce the allowed model type for each role and verify the referenced row.

### 3. Indexes

Add candidate-resolution indexes:

- `(recipient_role, application_scope, status, product_id, priority,
  valid_from, valid_until, id)`;
- `(recipient_role, application_scope, status, product_category_id, priority,
  valid_from, valid_until, id)`.

Add applied-snapshot indexes:

- unique `(quote_line_id, recipient_role)`;
- `(commission_configuration_id)`;
- `(recipient_type, recipient_id, recipient_role)`;
- `(recipient_role, calculated_amount)`.

Querying by Quote uses the existing indexed `quote_lines.quote_id` relation and
then the indexed child FK; duplicating `quote_id` on the snapshot is rejected
for the MVP because it creates a consistency invariant without a demonstrated
query need.

### 4. Service boundaries

Use explicit immutable DTOs across every layer boundary.

- `CommissionConfigurationService`
  - owns configuration create, update, detail loading, and protected delete;
  - contains no Quote behavior.
- `CommissionRuleResolver`
  - accepts a typed context containing Product, Product Category, requested
    roles, and reference date;
  - returns zero or one typed match per role;
  - filters active and valid rules;
  - evaluates Product candidates before Product Category candidates;
  - contains no HTTP, recipient, Quote, persistence, or amount-calculation
    behavior.
- `CommissionCalculator`
  - is the single server-side arithmetic authority;
  - accepts a typed calculation input and returns the rounded amount;
  - contains no configuration lookup or persistence behavior.
- `QuoteCommissionInitializer`
  - is Quote-specific;
  - obtains Commercial, Reporter, and Supervisor from Quote header snapshots
    and Supplier from Product;
  - converts resolver matches into applied snapshot DTOs;
  - contains no controller behavior.
- `QuoteLineCommissionWriter`
  - validates/reconciles the submitted applied snapshots for one persisted
    Quote line;
  - never trusts submitted `calculated_amount`;
  - recalculates it using `CommissionCalculator`;
  - for a submitted Product/Category origin, re-resolves the rule and uses
    server-owned type, value, note, origin, and configuration ID;
  - for a manual origin, accepts only authorized editable snapshot fields.
- `QuoteCommissionSummaryCalculator`
  - sums persisted `calculated_amount` by role for one Quote;
  - returns all four roles, including zero totals.

`QuoteService` remains the transaction owner. It coordinates Quote-line
reconciliation, applied-commission reconciliation, economic totals, and
commission summary persistence/readback in one database transaction.

### 5. Quote full-replace integration

Keep the public collection semantics: submitting `offer_lines` or `cost_lines`
still authoritatively replaces that complete line-type collection, and omitting
the key still leaves it untouched.

Change the internal algorithm from delete-all/reinsert-all to ID-aware
reconciliation:

- an update payload may include the existing line `id`;
- the server verifies that every submitted ID belongs to the current Quote and
  submitted line type;
- rows with a valid ID are updated in place;
- rows without an ID are created;
- existing rows omitted from the submitted collection are deleted;
- duplicate, foreign, or wrong-type IDs are rejected.

Applied commissions are nested under each submitted line and reconciled by
their unique role within the same transaction. Existing commission IDs may be
carried for optimistic identity, but ownership is determined server-side by
the line and role, never by trusting a client ID.

This preserves stable identities and meaningful activity updates while
retaining the established full-replace API behavior. Cascading deletion from a
removed Quote line to its applied commissions remains correct.

When a new line omits its nested commission set, the server initializes it from
the resolver. When an existing line collection is resubmitted, the frontend
must include its complete nested commission snapshot set; this preserves manual
overrides while price, quantity, or ordering changes trigger server-side
recalculation.

### 6. API contract

The Commission Configurator uses the existing response envelope and metadata
patterns:

- `GET /api/commission-configurations/{commissionConfiguration}`
- `POST /api/commission-configurations`
- `PATCH /api/commission-configurations/{commissionConfiguration}`
- `DELETE /api/commission-configurations/{commissionConfiguration}`
- `GET /api/meta/commission-configurations`
- generic table endpoints under
  `/api/tables/commission-configurations/*`
- generic export endpoints under
  `/api/exports/commission-configurations/*`
- `GET /api/activity-log/commission-configurations/{id}`

There is no dedicated list endpoint because the generic table framework owns
backend-driven listing. There is no public generic rule-resolution endpoint in
the MVP.

For immediate Quote-form initialization, add a Quote-owned command endpoint:

- `POST /api/quotes/commission-defaults`

Its typed request supplies the current Quote context required for create/edit,
the Product, and the reference date once product decides that date. It returns
non-persisted applied-commission drafts for the four roles. It is authorized
through Quote create/update capability and Quote commission-field permission,
not through Configurator view permission; operators need applied defaults, not
access to the rule catalogue.

Extend Quote create/update line input with:

```text
offer_lines[] / cost_lines[]:
  id?                 # update only
  existing line fields
  commissions[]:
    id?               # update only
    recipient_role
    recipient_type?
    recipient_id?
    commission_type
    value
    internal_note?
    origin
    commission_configuration_id?
```

`calculated_amount` is prohibited input. Quote resources return each line's
applied commissions including authoritative `calculated_amount`, and add
`summary.commissions` with `commercial`, `reporter`, `supervisor`, and
`supplier`.

The defaults endpoint is a UX aid only. Quote persistence repeats resolution,
validation, and calculation server-side, so a stale or modified client draft
cannot impersonate a configuration-derived result.

### 7. Authorization and activity log

Add a `CommissionConfigurationPolicy` extending `BasePolicy`, resource
authorization metadata, field catalogue, table registration, authorization
registration, and permission synchronization under the resource key
`commission-configurations`.

Quote applied-commission editing is governed by the Quote policy and nested
Quote field permissions, not Configurator update permission. Add the nested
commission collection as a Quote authorization field. Internal notes and
recipient/value fields must be independently hideable only if the existing
field framework is extended with explicitly named top-level metadata keys; no
hidden nested-field convention may be invented in the frontend.

`CommissionConfiguration` and `QuoteLineCommission` use
`LogsModelActivity`. Register the Configurator root in the activity registry.
Aggregate `lines.commissions` under the Quote activity resource so manual
updates to persistent snapshots appear in the Quote timeline.

ID-aware reconciliation is required for this aggregation to record updates
rather than turning every line save into delete/create noise. Deletion audit
for child rows that no longer exist cannot be reconstructed by the current
relation-walking aggregator; if product requires deleted-child events to remain
visible in the Quote timeline, that requires a separate explicit root-linked
activity event and is outside this ADR's existing-framework reuse.

### 8. Protected deletion

`CommissionConfigurationService::delete()` checks for applied snapshots before
deleting and returns a localized HTTP 409 conflict when referenced. The
`restrictOnDelete` foreign key is defense in depth and protects every future
consumer that references the configuration through the same FK.

All generic bulk-delete paths must delegate to this same service guard through
the table definition. No controller or frontend-only guard is sufficient.

### 9. Rollout

Deploy additively:

1. Add enums, rule and snapshot tables, morph-map aliases, models, factories,
   policies, authorization/activity/table registrations, and tests.
2. Add resolver, calculator, CRUD service/API, delete guard, and generic table
   integration.
3. Add ID-aware Quote-line reconciliation and regression tests before adding
   nested commissions.
4. Add Quote initialization, nested persistence, resources, activity
   aggregation, and summary.
5. Add Configurator UI, Quote modal, live preview, translations, and
   responsive/loading/error/empty states.
6. Run permission synchronization after deployment.

No existing Quote is retroactively populated. Existing rows return an empty
commission collection and zero role totals until explicitly edited under a
future product-approved backfill/recalculation workflow.

Rollback must remove UI/navigation first, stop writes, then roll back the
additive tables only after verifying no applied snapshot data must be retained.

## Resolved product decisions

1. Only revenue/offer lines carry commissions.
2. Percentage commissions use the persisted line `net_amount`, excluding VAT.
3. Fixed commissions apply once per line and are not multiplied by quantity.
4. A higher integer priority wins, then later `valid_from`, then higher ID.
5. Rule validity is evaluated at the initialization/application date.
6. If a role has no recipient, no commission is created, calculated, persisted,
   or included in the summary. Assigning a recipient later initializes that
   role automatically.
7. Changing a Product requires confirmation and then regenerates all four
   role commissions, including prior manual overrides.
8. A recipient may be changed to any record compatible with the role's model
   type.
9. An authorized operator may create a manual commission when no rule exists.

## Alternatives Considered

- Store commissions as JSON on `quote_lines` — rejected because it weakens
  queryability, indexing, referential provenance, field-level auditing, and
  future reporting.
- Recalculate saved commissions live from configurations — rejected because
  configuration changes would rewrite Quote history implicitly.
- Put Quote recipient and persistence logic inside the reusable resolver —
  rejected because it couples the independent module to its first consumer.
- Keep delete-all/reinsert-all Quote-line writes — rejected because it destroys
  stable identities and creates noisy or unreachable child activity.
- Add a public generic resolver endpoint — rejected for the MVP because it
  exposes internal rules and authorization concerns unnecessarily; each
  consumer should own its application command while reusing the service.
- Duplicate `quote_id` on every applied snapshot — rejected for the MVP because
  the indexed QuoteLine relation already supports the required lookup.
- Use four role-specific recipient foreign keys — rejected because it couples
  the snapshot table to today's roles and prevents reuse; a strict morph map
  plus role/type validation provides a controlled generic recipient reference.

## Trade-offs

- Advantages
  - Rules remain reusable and independent from Quotes.
  - Saved Quotes are insulated from later rule changes.
  - Applied commissions are queryable and indexed.
  - Full-replace API compatibility is retained.
  - Stable IDs improve audit quality and future extensibility.
  - Server-side calculation and origin verification prevent client authority
    over financial results.
- Disadvantages
  - ID-aware reconciliation is more complex than delete/reinsert.
  - Polymorphic recipients cannot have a conventional database foreign key to
    three target tables.
  - Nested field authorization needs explicit metadata design if permissions
    must differ within one commission row.
  - The client maintains an immediate calculation preview that must be tested
    against the server calculator.
- What we give up
  - Automatic retroactive rule updates.
  - A generic external rules API.
  - Settlement and payment lifecycle fields in the MVP.

## Consequences

- `QuoteLineWriter` changes implementation but preserves its external
  full-replace semantics.
- Quote payloads and resources gain nested applied commissions and stable line
  IDs on update input.
- Configurator deletion becomes intentionally restrictive once any snapshot
  references the rule, including a snapshot later manually overridden.
- Existing Quotes remain valid and expose zero commission totals.
- Future settlement/reporting can query the snapshot table without changing
  the rule engine.
- No new third-party dependency is required.

## Affected Agents

- Product Manager — resolves blocking financial and workflow semantics.
- Backend — schema, DTOs, services, API, authorization, tables, export, and
  activity integration.
- Frontend — Configurator screens, nested Quote form state, modal, preview, and
  summary.
- UI Design — modal, summary, responsive behavior, and design-system review.
- Reviewer — architecture and maintainability review.
- QA — rule matrix, persistence, permissions, regressions, and rounding.
- Security — authorization, field visibility, morph-type allow-listing, and
  untrusted calculated/origin input.
- DevOps — migrations, permission sync, queue-backed export verification, and
  rollback.
- Documentation — API and user documentation after the contract is frozen.

## Risks

- Incorrect unresolved financial semantics would produce incorrect money.
- Invalid morph type/ID pairs could bypass role expectations without strict
  validation and enforced aliases.
- Concurrent Quote edits can overwrite a full collection; the implementation
  should use the existing Quote update concurrency convention, and a future
  optimistic-lock ADR is required if none exists.
- Resolver queries may become hot; the proposed indexes should be verified with
  representative `EXPLAIN` plans before release.
- Nested commission data can expose internal notes unless API Resources and
  field permissions omit hidden values server-side.
- Retaining a source FK after manual override intentionally makes more
  configurations undeletable.
- Deleted child activity is not retained in the current aggregated timeline
  merely by registering a relation.

## References

- `docs/specs/0066-commission-configurator.md`
- `docs/specs/0065-quotes-module.xml`
- `docs/adr/0001-backend-driven-datatable-ag-grid-ssrm.md`
- `docs/adr/0002-generic-domain-driven-table-registry.md`
- `docs/adr/0011-for-select-api-standard.md`
- `docs/adr/0016-starter-layered-service-architecture-and-ui-tooling.md`
- `standards/architecture.md`
- `standards/security-standards.md`
- `standards/quality-gates.md`
