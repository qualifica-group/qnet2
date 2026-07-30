import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
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
  quote_status_id: null,
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  internal_notes: null,
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
      <QuoteLiveSummary control={form.control} vatRatePercentFor={(id) => (id === 5 ? 22 : null)} />
    </>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLiveSummary (spec 0065 AC-071)', () => {
  it('renders the initial revenue net/vat/gross and margin from the seeded rows', () => {
    render(<Harness />)

    expect(screen.getByText('10.00')).toBeInTheDocument() // revenue net
    expect(screen.getByText('2.20')).toBeInTheDocument() // revenue vat
    expect(screen.getByText('12.20')).toBeInTheDocument() // revenue gross
    expect(screen.getByText('7.00')).toBeInTheDocument() // margin: 10.00 - 3.00
  })

  it('updates the summary the moment quantity changes, without any network call', () => {
    const getSpy = vi.spyOn(apiClient, 'get')
    const postSpy = vi.spyOn(apiClient, 'post')

    render(<Harness />)

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Quantity' }), { target: { value: '3' } })

    expect(screen.getByText('30.00')).toBeInTheDocument() // revenue net
    expect(screen.getByText('6.60')).toBeInTheDocument() // revenue vat
    expect(screen.getByText('27.00')).toBeInTheDocument() // margin: 30.00 - 3.00
    expect(getSpy).not.toHaveBeenCalled()
    expect(postSpy).not.toHaveBeenCalled()

    getSpy.mockRestore()
    postSpy.mockRestore()
  })
})

describe('totalsFromPersistedSummary (spec 0065 D-9)', () => {
  it('maps the persisted decimal-string summary onto numeric totals', () => {
    const summary: QuoteSummaryData = {
      revenue: { net: '30.00', vat: '6.60', gross: '36.60' },
      cost: { net: '10.00', vat: '2.20', gross: '12.20' },
      margin: { net: '20.00' },
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
    }

    render(<QuoteSummary totals={totalsFromPersistedSummary(summary)} />)

    expect(screen.getByText('-20.00')).toBeInTheDocument()
  })
})
