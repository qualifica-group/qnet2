# QA Report

## Report Info

- Feature / Change: Commission Configurator and Quote Commission Integration (spec 0066 / ADR 0017)
- Version / Branch: current working tree on 2026-07-29
- Date: 2026-07-29
- Handoff received from: Backend, Frontend, Reviewer, and Security agents

## QA Summary

The Commission Configurator and Quote commission feature is **APPROVED WITH
WARNINGS** at feature scope. Its focused backend, frontend, reviewer, and
security suites pass. The central resolver, calculator, initializer, summary,
payload redaction, writer, configuration service, CRUD validation, Quote
persistence, missing-recipient behavior, field authorization, controllers,
request validation, and backend-driven table definition now meet the 85%
feature coverage threshold.

The repository release gate remains **REJECTED**. The complete frontend suite
is green, and the only direct spec 0066 failure found by the full backend run
was fixed and passes its focused rerun. However, the full backend suite still
contains 14 unrelated/concurrent baseline failures, and the repository-wide
Pest coverage baseline remains 7.75%, below the non-waivable 85% global gate.

## Commands and Results

### Backend

- Expanded Commission/Quote focused suite: **PASS — 59 tests, 316
  assertions**.
- Earlier Commission/Quote focused suite: **PASS — 171 tests, 795
  assertions**.
- Security-focused suite: **PASS — 28 tests, 180 assertions**.
- Reviewer verification suite: **PASS — 13 tests, 134 assertions**.
- Earlier broad focused command covering Commission Configurator and existing
  Quote workflows: **PASS — 65 tests, 322 assertions**.
- Focused coverage run:
  - `CommissionRuleResolver`: 97.30% lines
  - `CommissionCalculator`: 100% lines
  - `QuoteCommissionInitializer`: 100% lines
  - `QuoteCommissionSummaryCalculator`: 100% lines
  - `QuoteCommissionPayloadRedactor`: 90.91% lines
  - `QuoteLineCommissionWriter`: 86.60% lines
  - `CommissionConfigurationService`: 90.00% lines
  - `CommissionConfigurationController`: 100% lines
  - `QuoteCommissionDefaultsController`: 100% lines
  - `ValidatesQuoteLines`: 89.84% lines
  - `CommissionConfigurationsTableDefinition`: 93.04% lines
  - Repository total reported by the focused Pest run: **7.75% lines
    (2,303/29,698), 9.28% methods, 4.63% classes**. This is affected by the
    legacy repository baseline, but the configured global 85% gate still
    fails and cannot be waived by QA.
- Full backend suite: **FAIL — 4,282 tests; 4,266 passed, 15 failed, 1
  skipped**.
  - One direct spec 0066 failure concerned the Field Catalogue expected
    resource. It was fixed, and the affected file now passes **7 tests and 160
    assertions**.
  - The remaining 14 failures are outside the Commission feature: navigation
    expectations for concurrently moved modules, spec 0067 search-limit
    expectations, migration preview-description expectations, and a flaky VAT
    factory case.
- Migration status: the two new migrations were pending in the local
  development database.
- Isolated safe migration verification with SQLite `:memory:`:
  **PASS**, including both new commission migrations.

### Frontend

- Feature-scoped suite: **PASS — 12 files, 37 tests**.
- Feature-scoped coverage:
  - Statements: **97.29%**
  - Branches: **88.66%**
  - Functions: **92.59%**
  - Lines: **96.90%**
- TypeScript check: **PASS** under Node 22.
- ESLint: **PASS** under Node 22.
- Production build: **PASS** under Node 22.
- Full frontend suite after fixes: **PASS — 407 files, 2,781 tests**.
- The previously observed Quote confirmation-provider and locale-state
  regressions are fixed.
- The default shell selected Node 18.16 through `/usr/local/bin`, which is
  incompatible with the installed Vite/Vitest toolchain. Frontend validation
  was rerun with the repository workstation's Node 22.22 binary.

## Scenarios Verified

### Happy Path

- [x] Authorized Commission Configuration create, read, partial update, and
  referenced-delete protection
- [x] Metadata, required table columns, backend search, export dispatch, and
  activity-log endpoint registration
- [x] Product rule precedence over Category rule
- [x] Priority and reference-date selection in the covered resolver matrix
- [x] Revenue-line initialization and role-based persisted summary
- [x] Supplier recipient from Product
- [x] Percentage and fixed calculation behavior in the calculator suite
- [x] Manual override persistence and authoritative server recalculation
- [x] Cost lines excluded from commission persistence and summary
- [x] Production frontend build and feature-focused UI tests
- [x] Full frontend regression suite

### Error Handling

- [x] Scope inconsistency rejected
- [x] Client-submitted calculated amount rejected
- [x] Referenced configuration deletion returns HTTP 409 with explanatory copy
- [x] Missing recipients omitted from persistence and summary
- [x] Hidden and read-only nested commission inputs rejected or redacted
- [ ] Timeout/network-error behavior was not exercised end-to-end
- [ ] Unreferenced delete success was not explicitly evidenced by the reviewed
  CRUD test

### Permissions

- [x] CRUD/action authorization covered by focused backend/security suites
- [x] Commission Configuration field visibility redaction in detail, table,
  and activity responses
- [x] Quote commission hidden/read-only redaction in detail, defaults, summary,
  and activity responses
- [x] Server rejects attempted changes to locked commission values
- [x] Expanded focused backend suite covers the feature authorization and
  field-permission matrix

### Data Integrity

- [x] Applied commission values are persisted separately from source rules
- [x] Server recalculates authoritative amounts
- [x] Missing recipient assignment initializes the role later
- [x] Removing a recipient removes the automatic commission and summary amount
- [x] Existing automatic snapshot financial values remain stable after global
  rule changes
- [x] Safe fresh migration succeeds
- [x] Expanded focused suite covers product-change and ID-aware reconciliation
  contracts

### UI States

- [x] Configurator detail loading and error states are implemented
- [x] Configurator detail and schema focused tests pass
- [x] Commission summary renders all four roles
- [x] Quote line commission action is covered in focused component tests
- [x] Commission feature UI focused suite passes
- [ ] Responsive behavior was not browser-verified

### Edge Cases

- [x] Product-versus-Category specificity
- [x] Higher priority and supplied validity timestamp
- [x] Missing recipient omission and later initialization
- [x] Fixed amount once per line and percentage rounding
- [x] Client-calculated amount manipulation
- [x] Hidden/read-only field manipulation
- [x] Expanded focused suite covers resolver validity, fallback, and
  deterministic tie-break behavior
- [ ] Rapid repeated actions and concurrent Quote edits were not tested

## Issues Found

| # | Description | Severity | Notes |
|---|---|---|---|
| 1 | Repository-wide 85% coverage gate fails; the focused Pest report shows a 7.75% repository baseline. | High | The significant new Commission backend files and frontend feature now exceed 85%, but the formal global gate remains unsatisfied. |
| 2 | Full backend suite retains 14 unrelated/concurrent baseline failures. | High | Navigation for moved modules, spec 0067 search limit, migration preview description, and a flaky VAT factory. No remaining failure is attributed to spec 0066. |
| 3 | Local development database still reports the two feature migrations as pending. | Low | Fresh isolated SQLite migration passed; deployment must apply migrations before use. |

## Regression Risks

- ID-aware Quote full-replace reconciliation changes a financially sensitive,
  established persistence path.
- Shared table, export, activity-log, and authorization infrastructure changed
  for the new module and can affect unrelated resources.
- Concurrent module/navigation and spec 0067 changes keep the repository
  backend baseline red even though Commission-focused tests pass.

## Risks

- Financial calculations rely on decimal strings at persistence boundaries but
  use floating-point arithmetic internally; currently covered values pass,
  while larger and boundary-value matrices remain advisable.
- Product-change regeneration intentionally discards manual overrides after
  confirmation, so incomplete confirmation coverage presents a data-loss UX
  risk.
- The 409 service guard is covered for direct deletion; every generic bulk
  deletion path should retain equivalent automated evidence.

## Recommendation

**APPROVED WITH WARNINGS — feature scope**

Spec 0066 is accepted by QA based on its focused functional, authorization,
security, regression, and coverage evidence. No direct Commission failure
remains.

**REJECTED — repository release gate**

Do not release the repository until:

1. The remaining 14 unrelated backend baseline failures are fixed or formally
   dispositioned by their owning agents.
2. The mandatory repository-wide 85% coverage policy is met or formally
   changed by the authority that owns the quality standard.

## Structured Handoff

### Context

Final QA validation of spec 0066 and ADR 0017 across Commission Configurator,
Quote applied commissions, authorization, activity, tables, export, frontend
flows, migrations, build, and regressions.

### Assumptions

- The supplied focused Backend, Reviewer, Security, and Frontend results are
  from the same current feature working tree.
- Repository quality gates remain mandatory until explicitly changed by the
  project authority.

### Decision

Approve spec 0066 with warnings at feature scope. Reject repository release
readiness because the full backend baseline remains red and the mandatory
global coverage gate fails.

### Scope

Included functional, regression, permission, migration, build, lint, and
coverage validation. Excluded live browser/manual responsive testing and
production deployment.

### Files Impacted

- `docs/qa/0066-commission-configurator.md`

No application code was modified by QA.

### Risks

Unrelated backend baseline failures and repository-wide legacy coverage below
the mandatory threshold. The Commission feature's significant changed code
meets the feature coverage threshold.

### Validation Needed

- Fix or formally disposition the 14 unrelated backend failures
- Repository-wide coverage gate remediation
- Final release-level QA rerun

### Next Owner

Owners of the unrelated backend baseline failures and the quality-gate policy,
followed by QA for release readiness.

### Blocking Questions

None.
