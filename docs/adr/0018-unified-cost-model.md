# Architecture Decision Record

## ADR ID

0018

## Title

Unified cost model: cost types as the single cost taxonomy, read-side work order
actuals, line-level passive invoices, VAT computed per rate

## Status

ACCEPTED — decisions taken by the user on 2026-10-13 (audit "Acquisti, Costi e
Attiva", wave 3: COS-01, COS-02, COS-04, COS-09, COS-16, ATT-06).

## Date

2026-10-13

---

## Context

The audit of 2026-10-13 found two disconnected cost models:

- **Product categories** (`product_categories`, a tree) classify products, quote
  lines and the manual work order costs (`work_order_costs`, spec 0190). They also
  drive sales behaviour: attribute contexts, competence, workflows, commissions,
  reports (about 200 backend files depend on them).
- **Cost types** (`cost_types`, flat, with `nature` and `color` since spec 0249)
  classify purchase order lines (required, per line) and passive invoices (header,
  nullable).

As a result:

- the work order actuals (`WorkOrderCostOverviewBuilder`) only sum the manual
  `work_order_costs`; purchase orders and passive invoices are excluded (spec 0210
  D-7), so the same expense is typed twice and the two numbers can diverge;
- no report can show "costs by type" across work orders, purchase orders and
  passive invoices;
- passive invoices have no lines and cannot be created in the application (spec
  0211, phase 1), so an invoice covering several work orders or cost types loses
  that information (COS-04) and the same supplier invoice can be registered twice
  (COS-16);
- three amount engines coexist (float in `QuoteTotalsCalculator` /
  `WorkOrderCostWriter`, bcmath with 3-decimal quantities in purchase orders, bcmath
  with 2-decimal quantities in active invoices); active invoices round VAT per line,
  while the electronic invoice computes VAT per rate (ATT-06).

---

## Decision

1. **Cost types are the single taxonomy for costs.** Product categories stay the
   sales taxonomy and are not changed. Products used as costs get a default
   `cost_type_id`; `work_order_costs` and every passive document line carry a
   `cost_type_id`. The legacy "Costi" category branch (spec 0174) is mapped to cost
   types by a data migration.
2. **Work order actuals are computed on read (hybrid).** The overview keeps the
   manual `work_order_costs` (costs without a purchase document: expense reports,
   internal costs) and adds the passive cycle: purchase order lines of the work order
   count as *committed*; once covered by a passive invoice line, the invoice amount
   replaces the order amount (no double counting). No rows are generated into
   `work_order_costs`. Totals are broken down by cost type.
3. **Passive invoices get lines (phase 2 of the passive cycle).** Invoices are
   created and edited in the application; each line carries `cost_type_id` and
   `work_order_id`, the header values become defaults. The supplier document number
   is unique per (supplier, year).
4. **One bcmath amount engine for document lines; VAT per rate on active invoices.**
   Line net amounts are rounded per line; VAT is computed once per rate on the sum
   of the taxable amounts of that rate, as the electronic invoice does. Quantity and
   amount precision are aligned with new migrations.

Delivery order (one spec each): passive invoice phase 2 (0250) → cost taxonomy on
products and work order costs (0251) → hybrid work order actuals by cost type
(0252) → unified amount engine and VAT per rate (0253).

---

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Product categories as the single taxonomy | Purchase order lines can have no product; the tree is built for sales and carries competence/workflow side effects; would rework required `cost_type_id` columns, guards (0246) and the cost type UI. |
| Keep both taxonomies, linked | Two sources of truth for the same report; every cost report must reconcile them. |
| Generate `work_order_costs` rows from purchase orders / invoices | Needs nullable product, wider quantity, sync rules against the full-replace PUT of the cost editor, and creates duplicated data to keep aligned. |
| Keep VAT per line on active invoices | Cent-level differences with the electronic invoice; the fiscal document is the reference. |

---

## Trade-offs

- Reading actuals on the fly costs a few aggregate queries per overview instead of a
  single table scan; acceptable at work order granularity.
- The hybrid model relies on users not typing manually a cost that also has a
  purchase document; the UI must make the passive cycle amounts visible next to the
  manual ones.
- Phase 2 of passive invoices is a sizeable feature that must land before the
  actuals can use invoice lines.

---

## Consequences

- Spec 0210 D-7 (purchase orders excluded from work order actuals) is superseded by
  this ADR once spec 0252 lands.
- Spec 0249 D-2 (flat list) stays valid; if the "Costi" branch mapping needs more
  depth, it is revisited in spec 0251.
- Reports "costs by type / by nature" become possible across all cost sources.

---

## Affected Agents

`backend`, `database`, `frontend`, `verifier`.

---

## Risks

- Data migration of the "Costi" branch to cost types must be reviewed with the user
  before running in production.
- Changing VAT rounding alters totals of new active invoices by up to a few cents;
  registered invoices are locked (spec 0243) and are not recalculated.

---

## References

- Audit "Acquisti, Costi e Attiva" 2026-10-13 (COS-01, COS-02, COS-04, COS-09, COS-16, ATT-06)
- Specs 0174, 0190, 0194, 0210, 0211, 0243, 0249
