import type { QuoteLine, QuoteWorkflowStatusRef } from '@/features/quotes/types'

/**
 * The `open` row every resolved quote workflow set carries (spec 0083): the
 * status a freshly created quote lands on, and the one the opportunity falls
 * back to when it has no quotes at all.
 *
 * Shared by the quote test suites so the shape stays in one place — five
 * fixtures were repeating it verbatim, and every field added to
 * `QuoteWorkflowStatusRef` had to be pasted into all of them.
 */
export const WORKFLOW_STATUS_OPEN: QuoteWorkflowStatusRef = {
  id: 1,
  name: 'Bozza',
  color: 'slate',
  description: null,
  group: 'open',
  requires_note: false,
}

/** A row that DEMANDS a transition note (AC-023): the negative case of the same set. */
export const WORKFLOW_STATUS_REQUIRES_NOTE: QuoteWorkflowStatusRef = {
  id: 2,
  name: 'Accettata',
  color: 'green',
  description: null,
  group: 'closed_won',
  requires_note: true,
}

/**
 * A persisted `quote_lines` row (spec 0065), `offer_line_id: null` by default
 * (spec 0144 D-2: NULL on every REVENUE row, and on a COST row with no
 * association — a generic cost). Callers needing an association override it
 * explicitly.
 */
export function quoteLineFixture(overrides: Partial<QuoteLine> = {}): QuoteLine {
  return {
    id: 1,
    product_id: 1,
    product: { id: 1, code: 'PRD-0001', name: 'Prodotto', category: null, product_typology: null, business_function: null },
    quantity: '1.00',
    unit_of_measure: null,
    additional_description: null,
    unit_price: '10.00',
    vat_rate_id: null,
    vat_rate: null,
    net_amount: '10.00',
    vat_amount: '0.00',
    total_amount: '10.00',
    sort_order: 0,
    offer_line_id: null,
    ...overrides,
  }
}
