import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { QuoteLiveSummary, QuoteSummary, totalsFromPersistedSummary } from '@/features/quotes/quote-summary'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteSummary as QuoteSummaryData } from '@/features/quotes/types'

/**
 * Spec 0065 AC-071: the live summary recomputes on every quantity/price edit,
 * with ZERO network calls (client speculative replica of the server's own
 * calculation, D-9/D-12).
 */

// Revenue carries a 22% VAT rate and a cost row is seeded too, so every
// rendered amount (net/vat/gross per side, plus the margin) is a DISTINCT
// number — otherwise a zero-VAT, zero-cost scenario renders the very same
// "10.00" in the revenue net/gross AND the margin cells, making a plain
// `getByText` ambiguous.
const EMPTY_VALUES: QuoteFormValues = {
  code: 'QUO-0001',
  title: 'Test quote',
  opportunity_id: 1,
  quote_workflow_status_id: null,
  note: null,
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  manager_slots: [],
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  layout_id: null,
  payment_method_id: null,
  internal_notes: null,
  rewards: [],
  attribute_values: {},
  offer_lines: [{ product_id: 1, quantity: 1, unit_price: 10, vat_rate_id: 5 }],
  cost_lines: [{ product_id: 2, quantity: 1, unit_price: 3, vat_rate_id: null }],
}

function Harness() {
  const form = useForm<QuoteFormValues>({ defaultValues: EMPTY_VALUES })
  return (
    <>
      <input
        aria-label="Quantity"
        type="number"
        {...form.register('offer_lines.0.quantity', { valueAsNumber: true })}
      />
      <QuoteLiveSummary
        control={form.control}
        vatRatePercentFor={(id) => (id === 5 ? 22 : null)}
        productTypologyIdFor={() => null}
      />
    </>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLiveSummary (spec 0065 AC-071)', () => {
  it('renders the initial revenue net/vat/gross and margin from the seeded rows', () => {
    render(<Harness />)

    // Spec 0144: with a single (generic-cost) product row, its own "Margine
    // per prodotto" net/margin cells are numerically IDENTICAL to the
    // aggregate revenue net (both read straight off the one row) — 3 nodes,
    // not 1: the aggregate card, the margin block's revenue cell and its
    // margin cell (the cost stays generic, so the row's own margin equals
    // its own revenue).
    expect(screen.getAllByText('10.00')).toHaveLength(3)
    expect(screen.getByText('2.20')).toBeInTheDocument() // revenue vat
    expect(screen.getByText('12.20')).toBeInTheDocument() // revenue gross
    expect(screen.getByText('7.00')).toBeInTheDocument() // margin: 10.00 - 3.00
  })

  it('updates the summary the moment quantity changes, without any network call', () => {
    const getSpy = vi.spyOn(apiClient, 'get')
    const postSpy = vi.spyOn(apiClient, 'post')

    render(<Harness />)

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Quantity' }), { target: { value: '3' } })

    expect(screen.getAllByText('30.00')).toHaveLength(3) // see comment above
    expect(screen.getByText('6.60')).toBeInTheDocument() // revenue vat
    expect(screen.getByText('27.00')).toBeInTheDocument() // margin: 30.00 - 3.00
    expect(getSpy).not.toHaveBeenCalled()
    expect(postSpy).not.toHaveBeenCalled()

    getSpy.mockRestore()
    postSpy.mockRestore()
  })

  // Spec 0144 AC-015: the block sits right below the aggregate summary,
  // showing the generic cost bucket for a cost the seeded fixture never
  // associates.
  it('renders the Margin per product block with a generic-cost row', () => {
    render(<Harness />)

    const marginsTable = within(screen.getByRole('table'))
    expect(screen.getByText('Margin per product')).toBeInTheDocument()
    expect(marginsTable.getByText('Generic costs')).toBeInTheDocument()
    expect(marginsTable.getByText('3.00')).toBeInTheDocument() // the cost row's own net, unattributed
  })
})

describe('totalsFromPersistedSummary (spec 0065 D-9)', () => {
  it('maps the persisted decimal-string summary onto numeric totals', () => {
    const summary: QuoteSummaryData = {
      revenue: { net: '30.00', vat: '6.60', gross: '36.60' },
      cost: { net: '10.00', vat: '2.20', gross: '12.20' },
      margin: { net: '20.00' },
    product_typologies: [],
    }

    render(<QuoteSummary totals={totalsFromPersistedSummary(summary)} />)

    expect(screen.getByText('36.60')).toBeInTheDocument()
    expect(screen.getByText('20.00')).toBeInTheDocument()
  })

  it('renders a negative margin as-is (AC-043), not clamped to zero', () => {
    const summary: QuoteSummaryData = {
      revenue: { net: '10.00', vat: '2.20', gross: '12.20' },
      cost: { net: '30.00', vat: '6.60', gross: '36.60' },
      margin: { net: '-20.00' },
    product_typologies: [],
    }

    render(<QuoteSummary totals={totalsFromPersistedSummary(summary)} />)

    expect(screen.getByText('-20.00')).toBeInTheDocument()
  })
})

/** Spec 0145 (AC-009): Ricavi, Costi, Commissioni, Margine atteso, Tipologia — in this order, form and detail alike. */
describe('QuoteSummary block order (spec 0145 D-8/AC-009)', () => {
  it('renders the blocks in the order Revenue, Cost, Commissions, Margin, Typology', () => {
    render(
      <QuoteSummary
        totals={{ revenue: { net: 100, vat: 0, gross: 100 }, cost: { net: 10, vat: 0, gross: 10 }, margin: { net: 60 } }}
        commissionTotals={{ commercial: 30, reporter: 0, supervisor: 0, supplier: 0 }}
      />,
    )

    const revenue = screen.getByText(i18n.t('quotes.form.summary.revenue'))
    const cost = screen.getByText(i18n.t('quotes.form.summary.cost'))
    const commissions = screen.getByText(i18n.t('quotes.form.summary.commissions'))
    const margin = screen.getByText(i18n.t('quotes.form.summary.margin'))
    const typology = screen.getByText(i18n.t('quotes.form.summary.productTypologies'))

    for (const [earlier, later] of [
      [revenue, cost],
      [cost, commissions],
      [commissions, margin],
      [margin, typology],
    ] as const) {
      expect(earlier.compareDocumentPosition(later) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    }
  })

  it('updates the margin hint to reflect the commissions subtraction (D-8)', () => {
    render(<QuoteSummary totals={{ revenue: { net: 0, vat: 0, gross: 0 }, cost: { net: 0, vat: 0, gross: 0 }, margin: { net: 0 } }} />)
    expect(screen.getByText('Net revenue minus net cost minus commissions.')).toBeInTheDocument()
  })
})

/** Spec 0145 (D-1/D-3/AC-010): the live form's own commission preview reduces both the shown role totals and the margin, with zero network calls. */
describe('QuoteLiveSummary — commissions on margin (spec 0145 AC-010)', () => {
  const COMMISSION_VALUES: QuoteFormValues = {
    ...EMPTY_VALUES,
    offer_lines: [{
      product_id: 1,
      quantity: 1,
      unit_price: 1000,
      vat_rate_id: null,
      client_key: 'row-1',
      commissions: [{
        recipient_role: 'COMMERCIAL',
        recipient_type: 'user',
        recipient_id: 1,
        commission_type: 'PERCENTAGE',
        value: 10,
        internal_note: null,
        origin: 'MANUAL_OVERRIDE',
        commission_configuration_id: null,
      }],
    }],
    // Imputed to the offer row above (spec 0144 D-1): the commission base
    // becomes 1000 - 400 = 600, not the row's raw 1000 net.
    cost_lines: [{ product_id: 2, quantity: 1, unit_price: 400, vat_rate_id: null, offer_line_key: 'row-1' }],
  }

  function CommissionHarness() {
    const form = useForm<QuoteFormValues>({ defaultValues: COMMISSION_VALUES })
    return (
      <QuoteLiveSummary control={form.control} vatRatePercentFor={() => null} productTypologyIdFor={() => null} />
    )
  }

  it('bases the live commission on the row net minus its imputed cost, and nets the margin by it', () => {
    render(<CommissionHarness />)

    // Scoped to each card: the "Margine per prodotto" block below renders the
    // SAME two numbers on this single-row fixture (a row fully covered by its
    // own imputed cost), so an unscoped query would be ambiguous.
    const commissionsCard = screen.getByText(i18n.t('quotes.form.summary.commissions')).closest('div')!.parentElement!
    const marginCard = screen.getByText(i18n.t('quotes.form.summary.margin')).closest('div')!.parentElement!

    // Base = 1000 - 400 = 600; 10% commercial commission = 60.00.
    expect(within(commissionsCard).getByText('60.00')).toBeInTheDocument()
    // Margin = revenue.net (1000) - cost.net (400) - commissions (60) = 540.00.
    expect(within(marginCard).getByText('540.00')).toBeInTheDocument()
  })
})
