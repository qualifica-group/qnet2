import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import type { QuoteLine } from '@/features/quotes/types'

/**
 * Spec 0072 BR-7/AC-046: this component is shared, unmodified, between the
 * Offer detail view (spec 0065) and the Contract detail view — read-only
 * rendering of persisted quote lines, no edit controls, no addable rows.
 */

function lineFixture(overrides: Partial<QuoteLine> = {}): QuoteLine {
  return {
    id: 1,
    product_id: 42,
    product: { id: 42, code: 'PRD-0042', name: 'Widget Pro', category: null, business_function: null },
    quantity: '2.00',
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
    expect(screen.getByText('292.80')).toBeInTheDocument()
  })

  it('hides the commissions action when showCommissions is false (default)', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} />)
    expect(screen.queryByRole('button', { name: /commission/i })).not.toBeInTheDocument()
  })

  it('shows the commissions action when showCommissions is true', () => {
    render(<QuoteLinesReadOnlyList lines={[lineFixture()]} showCommissions />)
    expect(screen.getByRole('button', { name: /commission/i })).toBeInTheDocument()
  })
})
