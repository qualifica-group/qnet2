# Feature Specification

> Owner: Product Manager Agent.
> Source request: “Commission Configurator module”, 2026-07-29.

## Feature Name

Commission Configurator and Quote Commission Integration

## Status

DRAFT — product scope is defined. The blocking questions below must be resolved before the technical contract is frozen and implementation begins.

## Priority

High

## Product Summary

Introduce an independent, reusable module for configuring commission rules by recipient role and product scope. The Quote module is the first consumer: applicable rules initialize persistent commissions on individual quote product lines, while authorized users can make quote-line-specific overrides without changing the source configuration.

The module must reuse the existing application patterns for CRUD resources, resource and field authorization, backend-driven tables, exports, metadata-driven forms, and activity logs. All user-facing copy must use English source keys with Italian translations provided through the existing i18n systems.

## Problem Statement

The application has no centralized, configurable source for commissions. Calculating or copying commissions manually into Quotes would increase operating time, inconsistent rule selection, calculation errors, and difficulty tracing where an amount came from.

The product must keep two concepts separate:

- the reusable rule administered in the Commission Configurator;
- the commission applied to one specific Quote line, persisted as a historical snapshot and editable only in that line’s context.

## Target Users

- Authorized administrators or managers who maintain commission rules.
- Sales operators who create or edit Quotes and need correct initial commissions without manual calculations.
- Authorized managers who review recipients, amounts, sources, and internal notes.
- Future administrative processes that will query persisted commissions.

## User Value

- Reduces repetitive entry on Quote lines.
- Applies the most specific valid rule consistently.
- Supports local exceptions without contaminating global configuration.
- Makes each applied commission queryable and traceable to its source.
- Prepares reliable data for future settlements, reporting, and accounting integrations without adding those workflows to the MVP.

## Product Decisions

1. The Commission Configurator is an independent module; Quotes are its first consumer, not the owner of its rules.
2. Rule specificity follows the required order: Product → Product Category → no configuration.
3. Automatic resolution runs independently for the Commercial, Reporter, Supervisor, and Supplier roles.
4. An applied line commission is persistent and retains at least recipient, type, value, calculated amount, internal note, and source.
5. Configurator values are initial values. A manual change remains isolated to that Quote and Quote line and never updates the source rule.
6. Suspended rules and rules outside their validity interval are not applicable.
7. The initial Supplier comes from the Product relation. Commercial, Reporter, and Supervisor come from the existing Quote header snapshots.
8. The commission summary aggregates persisted calculated amounts across the Quote, grouped by role.
9. Settlement, payment, and accounting are not part of the MVP. The MVP must nevertheless persist commissions as domain data rather than client-only presentation state.

## MVP Scope

### Included

#### Commission Configurator module

- Complete resource structure following existing standards: migration, model, factory, request validation, policy, field authorization metadata, resource, controller, service, and full REST CRUD API.
- Standard CRUD, export, and activity-log permissions plus field-level permissions.
- Functional fields:
  - Configuration name.
  - Recipient role: Commercial, Reporter, Supervisor, or Supplier.
  - Application scope: Product Category or Product.
  - Product Category or Product, consistent with the selected scope.
  - Commission type: Fixed Amount or Percentage.
  - Commission value.
  - Rule priority.
  - Valid from date.
  - Optional valid until date.
  - Active or Suspended status.
  - Internal service note.
- Cross-field validation: a Category rule requires a category; a Product rule requires a product; irrelevant scope relations must not be accepted.
- A backend-driven table showing at least Name, Role, Scope, Category, Product, Type, Value, Priority, Status, and Last Updated.
- Backend-driven filters, sorting, search, and export through the existing generic framework.
- Create, Edit, and View forms consistent with the design system and governed by authorization metadata.
- Complete activity logging for resource operations and field changes, accessible only through the relevant permission.
- Protected deletion: a configuration referenced by another module is not deleted and the API returns a localized explanatory message.

#### Rule resolution engine

- A centralized, reusable service that receives a commission context and resolves the applicable rule for each requested role.
- Only active rules valid on the reference date are candidates.
- A Product rule always takes precedence over a Product Category rule.
- No automatic commission is returned for a role when no applicable configuration exists.
- The result supplies the consumer with commission type, value, internal note, and Product/Category source.
- Selection is deterministic when multiple rules are candidates, once the tie-break decision in the blocking questions is resolved.

#### Quote integration

- Adding a relevant product line automatically invokes the engine for all four roles.
- Initial recipients are resolved from the Quote context and the Product supplier.
- Applied commissions are persisted against the specific Quote line.
- A Commissions action appears next to the line delete action.
- The action opens a modal that displays and, when authorized, edits for every role:
  - commission recipient;
  - commission type;
  - value;
  - calculated amount;
  - internal service note;
  - source: Product, Category, or Manual Override.
- Changing recipient, type, value, or note marks that applied line commission as Manual Override and does not change the Configurator.
- A centralized calculator recalculates the amount when a relevant line or commission input changes.
- Commission data is saved and read with the Quote context and never relies exclusively on client state.
- Deleting a Quote line removes only that line’s applied commissions, not its source configurations.
- Manual applied-commission changes appear in the Quote’s aggregated activity log through the existing mechanism.

#### Commission Summary

- Add a “Commission Summary” panel beside the existing economic summary panels.
- Show separate totals for Commercial, Reporter, Supervisor, and Supplier.
- Update the client preview when product, quantity, price, commission, or another calculation input changes.
- Persist and recalculate authoritative values server-side on save; client calculation is immediate preview only.

#### Minimum release quality

- Backend and frontend tests mapped to the acceptance criteria using test-first development.
- Explicit coverage of CRUD, export, activity-log, and field permissions.
- Quote workflow regression coverage.
- Essential technical and user documentation for rule resolution and module use.
- At least 85% backend and frontend coverage for new significant code.

### Excluded

- Commission settlement.
- Payment status, due dates, or approval workflows.
- Dedicated per-role reports and aggregate analytics beyond a single Quote summary.
- Accounting exports.
- Invoice or accounting-entry generation.
- Bulk retroactive recalculation of existing Quotes.
- User-facing configuration version management.
- Tiered commissions, thresholds, minimums, maximums, or formulas beyond fixed amount and percentage.
- Currencies beyond the Quote module’s existing currency behavior.
- Notifications, approvals, or payment automation.

## User Flow

### Configure a rule

1. An authorized user opens the Commission Configurator.
2. The user searches or filters existing configurations.
3. The user creates a configuration and selects role, scope, type, value, priority, validity, status, and internal note.
4. The system validates cross-field consistency and saves the rule.
5. The user can view, edit, suspend, or export configurations according to permissions.
6. If the user attempts to delete a referenced configuration, the system blocks the operation and explains why.

### Apply rules to a Quote

1. The operator adds a product to a relevant Quote line.
2. The system resolves each role’s recipient from the Quote and Product contexts.
3. The engine looks for a valid Product rule first, then a Product Category rule.
4. For each role with a valid rule, the system initializes a persistable line commission.
5. The system calculates amounts and updates the Commission Summary.

### Override one line

1. The operator opens the line’s Commissions modal.
2. The modal shows all associated commissions and their sources.
3. The operator changes permitted fields.
4. The system recalculates the amount and changes that applied commission’s source to Manual Override.
5. Saving updates only that line commission and the Quote summary.

## Acceptance Criteria

### Configurator

- [ ] An authorized user can create, read, update, and delete an unreferenced configuration.
- [ ] A user without the relevant ability is denied server-side and does not see the corresponding UI action.
- [ ] Field permissions govern visibility, editability, and required state in both the form and server-side validation.
- [ ] The API rejects a Category rule without a category, a Product rule without a product, and inconsistent scope fields.
- [ ] A valid-until date before valid-from is rejected.
- [ ] The table exposes every required column and executes search, filtering, and sorting on the backend.
- [ ] Export contains authorized records and requires the export permission.
- [ ] Create, update, status change, and delete operations produce activity events without exposing hidden fields.
- [ ] Deleting a referenced configuration fails without data loss and returns a localized explanatory message.

### Resolution

- [ ] For the same role, a valid Product rule always wins over a valid Category rule.
- [ ] If no valid Product rule exists but a valid Category rule does, the Category rule is returned.
- [ ] If no rule applies, the engine returns no configuration for that role.
- [ ] Suspended, future, and expired rules are not applied.
- [ ] Commercial, Reporter, Supervisor, and Supplier are resolved independently.
- [ ] Identical context and reference date always produce the same result.

### Persistence and isolation

- [ ] Adding a product initializes type, value, note, and source for every applicable role.
- [ ] The initial Supplier recipient matches the Product supplier.
- [ ] Initial Commercial, Reporter, and Supervisor recipients match the Quote header snapshots.
- [ ] Applied commissions are persistently queryable by Quote, line, role, and recipient.
- [ ] Editing an applied commission never updates or creates a Configurator rule.
- [ ] Reopening a saved Quote preserves manual values and Manual Override source.
- [ ] Later changes to a global rule do not automatically change commissions already persisted on Quotes.
- [ ] Deleting a Quote line deletes only its applied commissions.

### Calculation and summary

- [ ] A percentage commission uses the approved calculation base and the Quote module’s approved monetary rounding.
- [ ] A fixed commission uses the approved per-line or per-unit behavior.
- [ ] Changing quantity, price, commission type, or commission value updates the line preview and totals by role.
- [ ] On save, the server recalculates amounts and does not trust client-calculated amounts as authoritative input.
- [ ] The Commission Summary always displays all four role totals, including zero values.
- [ ] Each role total equals the sum of that role’s persisted applied commissions across the Quote.

### UX and regression

- [ ] The Commissions action is located next to the line delete action and respects user permissions.
- [ ] The modal supports Create/Edit/View contexts: View is read-only, and Edit enables only authorized fields.
- [ ] Forms, modal, loading skeletons, error/empty states, and summary follow the existing design system and responsive breakpoints.
- [ ] User-facing source copy is English and Italian is supplied through backend/frontend i18n resources; no Italian copy is hardcoded.
- [ ] Existing Quote create, edit, view, and delete workflows continue to work.

## Success Criteria

- During acceptance testing, 100% of the Product/Category/Role resolution matrix returns the expected configuration.
- During acceptance testing, 100% of role totals equal the server-side recomputed sum of line commissions.
- Zero unintended updates to global configurations during Quote override tests.
- Reduced time to prepare commissions for a new Quote compared with the current manual baseline; the pilot must establish the baseline and numeric target.
- No critical or high-severity defects in authorization, data isolation, protected deletion, or Quote regressions at release.

## Future Enhancements

- Settlement and payment status.
- Commission approval workflows.
- Reports and dashboards for Commercial, Reporter, Supervisor, and Supplier.
- Aggregate and time-series analytics.
- Accounting exports and invoicing integrations.
- Explicit configuration history and version management.
- Controlled recalculation or realignment of already applied commissions.
- Advanced rules with thresholds, tiers, minimums, maximums, and composite formulas after demonstrated demand.

## Dependencies

- Existing Quote module (`quotes`, `quote_lines`) and its line replacement workflow.
- Product module, Product → Category relation, and Product → Supplier relation.
- Existing Commercial, Reporter, and Supervisor snapshots on the Quote header.
- Existing policy/permission sync, field-permission, and authorization-metadata frameworks.
- Existing backend-driven table, advanced-filter, search, and export frameworks.
- Existing aggregated activity-log framework.
- Existing design system, metadata-driven forms, modal primitives, loading skeletons, and summary components.

## Risks

- An undefined percentage base or fixed-amount meaning could generate financially incorrect values.
- Multiple valid rules for the same role and specificity need an explicit deterministic tie-break.
- It is not defined whether commissions apply only to revenue lines or also to cost lines.
- Changing a Product on a line with manual overrides can conflict with the existing snapshot.
- Missing recipients on the Quote or Product need explicit validation and UX behavior.
- The Quote line full-replace workflow can break commission history or identifiers unless the technical contract preserves associations.
- Protected deletion and future references require a clear boundary between source configuration and applied snapshot.
- Internal notes and commission amounts may need stricter field visibility than other Quote data.
- “Complete activity log” must include applied commission changes without duplicating or overwhelming the Quote log.

## Open Product Decisions / Blocking Questions

1. Do commissions apply only to revenue lines (`offer_lines`), or also to cost lines (`cost_lines`)?
2. Is a percentage calculated from the line net amount (`quantity × unit_price`, excluding VAT), or another amount?
3. Is a fixed amount applied once per line or multiplied by quantity?
4. When multiple valid rules share the same role and scope, does a higher or lower numeric priority win? If priority is also tied, must creation be prevented or is another tie-break required?
5. Which date controls validity: Quote date, line creation date, or the current date when defaults are resolved?
6. If a role has no recipient, should the system omit the commission, persist it without a recipient and flag it, or block saving?
7. If an operator changes the Product on a line with manual overrides, should the system preserve overrides, request confirmation, or regenerate commissions?
8. In the modal, does an editable recipient allow any role-compatible record, or only the subject already present in the Quote/Product context?

## Next Owner

Architect Agent — assess cross-layer impact and propose the technical contract. Application implementation must not begin until the blocking product decisions are resolved.
