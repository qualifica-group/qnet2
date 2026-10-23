import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteCommissionSummary } from '@/features/quotes/quote-commission-summary'
import { QuoteSummary } from '@/features/quotes/quote-summary'
import { receivedSupplierCommission, type ProductMarginRow } from '@/features/quotes/quote-product-margins-calc'

const MINUS = '−'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function rowText(label: string): string {
  return screen.getByText(label).closest('div')!.textContent ?? ''
}

function marginRow(overrides: Partial<ProductMarginRow>): ProductMarginRow {
  return {
    key: 'line-1',
    productName: 'Product',
    rowNumber: 1,
    revenueNet: 0,
    costNet: 0,
    commissionsNet: 0,
    supplierCommissionDirection: null,
    margin: 0,
    lineNet: 0,
    supplierCommissionNet: 0,
    ...overrides,
  }
}

/** User request 2026-10-23: each commission shows whether it comes in (+) or goes out (-). */
describe('QuoteCommissionSummary signs', () => {
  it('marks Commercial, Reporter and Supervisor as going out and a zero without sign', () => {
    render(
      <QuoteCommissionSummary
        totals={{ commercial: 30, reporter: 12.5, supervisor: 0, supplier: 0 }}
        receivedSupplier={0}
      />,
    )

    expect(rowText('Commercial')).toBe(`Commercial${MINUS}30.00`)
    expect(rowText('Reporter')).toBe(`Reporter${MINUS}12.50`)
    expect(rowText('Supervisor')).toBe('Supervisor0.00')
    expect(rowText('Supplier')).toBe('Supplier0.00')
    expect(screen.getByText('+ commissions coming in, − commissions going out.')).toBeInTheDocument()
  })

  it('marks a received Supplier commission as coming in', () => {
    render(<QuoteCommissionSummary totals={{ commercial: 0, reporter: 0, supervisor: 0, supplier: 200 }} receivedSupplier={200} />)

    expect(rowText('Supplier')).toBe('Supplier+200.00')
  })

  it('marks a paid Supplier commission as going out', () => {
    render(<QuoteCommissionSummary totals={{ commercial: 0, reporter: 0, supervisor: 0, supplier: 90 }} receivedSupplier={0} />)

    expect(rowText('Supplier')).toBe(`Supplier${MINUS}90.00`)
  })

  it('splits a quote with both Supplier directions into received and paid rows', () => {
    render(<QuoteCommissionSummary totals={{ commercial: 0, reporter: 0, supervisor: 0, supplier: 290 }} receivedSupplier={200} />)

    expect(rowText('Supplier (received)')).toBe('Supplier (received)+200.00')
    expect(rowText('Supplier (paid)')).toBe(`Supplier (paid)${MINUS}90.00`)
    expect(screen.queryByText('Supplier')).not.toBeInTheDocument()
  })
})

describe('receivedSupplierCommission', () => {
  it('sums the Supplier commission of RECEIVED rows only', () => {
    const rows = [
      marginRow({ key: 'a', supplierCommissionDirection: 'RECEIVED', supplierCommissionNet: 200.1 }),
      marginRow({ key: 'b', supplierCommissionDirection: 'PAID', supplierCommissionNet: 90 }),
      marginRow({ key: 'c', supplierCommissionDirection: 'RECEIVED', supplierCommissionNet: 0.2 }),
      marginRow({ key: 'd', supplierCommissionDirection: null, supplierCommissionNet: 0 }),
    ]

    expect(receivedSupplierCommission(rows)).toBe(200.3)
  })
})

describe('QuoteSummary commission card wiring', () => {
  it('reads the received Supplier part from the per-product margins', () => {
    render(
      <QuoteSummary
        totals={{ revenue: { net: 3000, vat: 0, gross: 3000 }, cost: { net: 0, vat: 0, gross: 0 }, margin: { net: 1110 } }}
        commissionTotals={{ commercial: 0, reporter: 0, supervisor: 0, supplier: 290 }}
        productMargins={{
          rows: [
            marginRow({ key: 'a', supplierCommissionDirection: 'RECEIVED', supplierCommissionNet: 200 }),
            marginRow({ key: 'b', supplierCommissionDirection: 'PAID', supplierCommissionNet: 90 }),
          ],
          genericCostNet: 0,
        }}
      />,
    )

    expect(rowText('Supplier (received)')).toBe('Supplier (received)+200.00')
    expect(rowText('Supplier (paid)')).toBe(`Supplier (paid)${MINUS}90.00`)
  })
})
