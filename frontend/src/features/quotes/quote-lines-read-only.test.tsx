import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import type { QuoteLine } from '@/features/quotes/types'

/**
 * Spec 0072 BR-7/AC-046: this component is shared, unmodified, between the
 * Offer detail view (spec 0065) and the Contract detail view — read-only
 * rendering of persisted quote lines, no edit controls, no addable rows. Since
 * 2026-10-23 (requirement changed, user directive) each row is the line
 * editor's card, not a table row.
 */

function lineFixture(overrides: Partial<QuoteLine> = {}): QuoteLine {
  return {
    id: 1,
    product_id: 42,
    product: { id: 42, code: 'PRD-0042', name: 'Widget Pro', category: null, product_typology: null, business_function: null },
    quantity: '2.00',
    unit_of_measure: { id: 1, name: 'Kilogram', symbol: 'kg' },
    additional_description: null,
    unit_price: '120.00',
    vat_rate_id: 7,
    vat_rate: { id: 7, name: 'IVA 22%', rate: '22.00' },
    net_amount: '240.00',
    vat_amount: '52.80',
    total_amount: '292.80',
    sort_order: 0,
    commissions: [],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLinesReadOnlyList', () => {
  it('shows the empty-state message when there are no lines', () => {
    render(<QuoteLinesReadOnlyList lines={[]} />)
    expect(screen.getByText(i18n.t('quotes.detail.linesEmpty'))).toBeInTheDocument()
  })

  it('renders one row per line with product, code and amounts', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} />)
    expect(screen.getByText('Widget Pro')).toBeInTheDocument()
    expect(screen.getByText('PRD-0042')).toBeInTheDocument()
    expect(within(screen.getByRole('listitem')).getByText('292.80')).toBeInTheDocument()
  })

  it('shows the unit of measure symbol next to the quantity (spec 0088 AC-061)', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} />)
    expect(screen.getByText('kg')).toBeInTheDocument()
  })

  it('shows the line additional description under the row when present', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ additional_description: 'Montaggio incluso' })]} />)
    expect(screen.getByText('Montaggio incluso')).toBeInTheDocument()
  })

  it('shows the quantity alone for a legacy line with no frozen unit of measure', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ unit_of_measure: null })]} />)
    expect(screen.getByText('2.00')).toBeInTheDocument()
    expect(screen.queryByText('kg')).not.toBeInTheDocument()
  })

  it('falls back to a placeholder for a line with no VAT rate', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ vat_rate_id: null, vat_rate: null })]} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('hides the commissions action when showCommissions is false (default)', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} />)
    expect(screen.queryByRole('button', { name: /commission/i })).not.toBeInTheDocument()
  })

  it('shows the commissions action when showCommissions is true', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} showCommissions />)
    expect(screen.getByRole('button', { name: /commission/i })).toBeInTheDocument()
  })

  it('renders each line as a card like the open editor: position, typology and category', () => {
    render(
      <QuoteLinesReadOnlyList
        lines={[
          lineFixture({
            product: {
              id: 42,
              code: 'PRD-0042',
              name: 'Widget Pro',
              category: { id: 3, name: 'Corsi' },
              product_typology: { id: 5, name: 'Formazione', color: 'blue' },
              business_function: null,
            },
          }),
        ]}
      />,
    )
    const card = screen.getByRole('listitem')
    expect(within(card).getByText('1')).toBeInTheDocument()
    expect(within(card).getByText('Formazione')).toBeInTheDocument()
    expect(within(card).getByText('Corsi')).toBeInTheDocument()
  })

  it('titles the rows and totals them like the editor frame', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture(), lineFixture({ id: 2 })]} />)
    expect(screen.getByRole('region', { name: i18n.t('quotes.lineEditor.rows.titleRevenue') })).toBeInTheDocument()
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
    expect(screen.getByText(i18n.t('quotes.lineEditor.rows.totalRevenue'))).toBeInTheDocument()
    expect(screen.getByText('585.60')).toBeInTheDocument()
  })
})

/** Spec 0144 AC-016: the Cost variant's "Associated product" value. */
describe('QuoteLinesReadOnlyList — Associated product (cost variant)', () => {
  it('shows the associated offer row label resolved from offer_line_id', () => {
    render(
      <QuoteLinesReadOnlyList
        lines={[lineFixture({ offer_line_id: 7 })]}
        variant="cost"
        offerLineLabelsById={{ 7: 'Widget Pro (row 1)' }}
      />,
    )
    expect(screen.getByText('Widget Pro (row 1)')).toBeInTheDocument()
  })

  it('shows "Generic cost" for a cost with no association', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ offer_line_id: null })]} variant="cost" />)
    expect(screen.getByText('Generic cost')).toBeInTheDocument()
  })

  it('falls back to "Generic cost" when the referenced offer row label is unavailable', () => {
    render(
      <QuoteLinesReadOnlyList lines={[lineFixture({ offer_line_id: 99 })]} variant="cost" offerLineLabelsById={{}} />,
    )
    expect(screen.getByText('Generic cost')).toBeInTheDocument()
  })

  it('never shows the Associated product value on the revenue variant', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} />)
    expect(screen.queryByText('Associated product')).not.toBeInTheDocument()
  })

  it('shows the Associated product label on the cost variant', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ offer_line_id: null })]} variant="cost" />)
    expect(screen.getByText('Associated product')).toBeInTheDocument()
  })
})

describe('QuoteLinesReadOnlyList — fornitore (spec 0320)', () => {
  it('shows the supplier of the line', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ supplier_id: 5, supplier: { id: 5, name: 'Acme Srl' } })]} />)

    expect(screen.getByText(i18n.t('quotes.form.lineSupplierHeader'))).toBeInTheDocument()
    expect(screen.getByText('Acme Srl')).toBeInTheDocument()
  })

  it('shows nothing for a line without supplier', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture({ supplier_id: null, supplier: null })]} />)

    expect(screen.queryByText(i18n.t('quotes.form.lineSupplierHeader'))).not.toBeInTheDocument()
  })
})
