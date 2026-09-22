import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import {
  computeProductMargins,
  costLinesFromFormCostLines,
  costLinesFromPersistedCostLines,
  productLinesFromFormOfferLines,
  productLinesFromPersistedOfferLines,
  type ProductMarginCostInput,
  type ProductMarginProductInput,
} from '@/features/quotes/quote-product-margins-calc'
import { QuoteProductMargins } from '@/features/quotes/quote-product-margins'
import { quoteLineFixture } from '@/features/quotes/quote-fixtures'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'

/**
 * Spec 0144 D-6/AC-015: for each OFFER row, net revenue - net cost imputed to
 * it = its margin; every cost with no (or a stale) association falls into
 * "Costi generici". Invariant: sum(margins) - genericCostNet always equals
 * revenue.net - cost.net (the header's own margin), whatever the association
 * state of any individual cost.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('computeProductMargins', () => {
  it('attributes a cost to its product row and computes the margin', () => {
    const productLines: ProductMarginProductInput[] = [{ key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 0 }]
    const costLines: ProductMarginCostInput[] = [{ offerLineKey: 'a', net: 30 }]

    const { rows, genericCostNet } = computeProductMargins(productLines, costLines)

    expect(rows).toEqual([{ key: 'a', productName: 'Widget', rowNumber: 1, revenueNet: 100, costNet: 30, commissionsNet: 0, margin: 70 }])
    expect(genericCostNet).toBe(0)
  })

  it('buckets an unassociated cost as generic', () => {
    const productLines: ProductMarginProductInput[] = [{ key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 0 }]
    const costLines: ProductMarginCostInput[] = [{ offerLineKey: null, net: 40 }]

    const { rows, genericCostNet } = computeProductMargins(productLines, costLines)

    expect(rows[0]).toMatchObject({ costNet: 0, commissionsNet: 0, margin: 100 })
    expect(genericCostNet).toBe(40)
  })

  it('falls back a cost pointing at an unknown key to generic (defensive)', () => {
    const productLines: ProductMarginProductInput[] = [{ key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 0 }]
    const costLines: ProductMarginCostInput[] = [{ offerLineKey: 'gone', net: 15 }]

    const { rows, genericCostNet } = computeProductMargins(productLines, costLines)

    expect(rows[0]).toMatchObject({ costNet: 0, commissionsNet: 0, margin: 100 })
    expect(genericCostNet).toBe(15)
  })

  it('renders a negative margin when the imputed costs exceed the revenue', () => {
    const productLines: ProductMarginProductInput[] = [{ key: 'a', productName: 'Widget', rowNumber: 1, net: 20, commissionsNet: 0 }]
    const costLines: ProductMarginCostInput[] = [{ offerLineKey: 'a', net: 35 }]

    expect(computeProductMargins(productLines, costLines).rows[0].margin).toBe(-15)
  })

  it('sums several costs attributed to the same product row', () => {
    const productLines: ProductMarginProductInput[] = [{ key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 0 }]
    const costLines: ProductMarginCostInput[] = [
      { offerLineKey: 'a', net: 10 },
      { offerLineKey: 'a', net: 15 },
    ]

    expect(computeProductMargins(productLines, costLines).rows[0]).toMatchObject({ costNet: 25, commissionsNet: 0, margin: 75 })
  })

  it('invariant: sum(margins) - genericCostNet == revenue.net - cost.net, whatever the mix', () => {
    const productLines: ProductMarginProductInput[] = [
      { key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 0 },
      { key: 'b', productName: 'Gadget', rowNumber: 2, net: 50, commissionsNet: 0 },
    ]
    const costLines: ProductMarginCostInput[] = [
      { offerLineKey: 'a', net: 30 },
      { offerLineKey: 'b', net: 10 },
      { offerLineKey: null, net: 5 },
      { offerLineKey: 'gone', net: 7 },
    ]

    const { rows, genericCostNet } = computeProductMargins(productLines, costLines)
    const revenueNet = productLines.reduce((sum, line) => sum + line.net, 0)
    const costNet = costLines.reduce((sum, line) => sum + line.net, 0)
    const marginSum = rows.reduce((sum, row) => sum + row.margin, 0)

    expect(marginSum - genericCostNet).toBeCloseTo(revenueNet - costNet, 2)
  })

  it('returns no rows and zero generic cost with no product lines at all', () => {
    expect(computeProductMargins([], [{ offerLineKey: null, net: 10 }])).toEqual({
      rows: [],
      genericCostNet: 10,
    })
  })

  /** Spec 0145 (D-9): the row's margin nets out its own commissions too. */
  it('subtracts commissionsNet from the row margin when provided', () => {
    const productLines: ProductMarginProductInput[] = [
      { key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 15 },
    ]
    const costLines: ProductMarginCostInput[] = [{ offerLineKey: 'a', net: 30 }]

    expect(computeProductMargins(productLines, costLines).rows[0]).toMatchObject({
      costNet: 30,
      commissionsNet: 15,
      margin: 55,
    })
  })

  /** Spec 0145 (D-9): sum(margins) - genericCostNet == revenue.net - cost.net - totalCommissions, whatever the mix. */
  it('invariant holds with commissions too: sum(margins) - genericCostNet == revenue.net - cost.net - totalCommissions', () => {
    const productLines: ProductMarginProductInput[] = [
      { key: 'a', productName: 'Widget', rowNumber: 1, net: 100, commissionsNet: 10 },
      { key: 'b', productName: 'Gadget', rowNumber: 2, net: 50, commissionsNet: 5 },
    ]
    const costLines: ProductMarginCostInput[] = [
      { offerLineKey: 'a', net: 30 },
      { offerLineKey: null, net: 5 },
    ]

    const { rows, genericCostNet } = computeProductMargins(productLines, costLines)
    const revenueNet = productLines.reduce((sum, line) => sum + line.net, 0)
    const costNet = costLines.reduce((sum, line) => sum + line.net, 0)
    const totalCommissions = productLines.reduce((sum, line) => sum + (line.commissionsNet ?? 0), 0)
    const marginSum = rows.reduce((sum, row) => sum + row.margin, 0)

    expect(marginSum - genericCostNet).toBeCloseTo(revenueNet - costNet - totalCommissions, 2)
  })
})

function formOfferRow(overrides: Partial<QuoteLineFormValues> = {}): QuoteLineFormValues {
  return { product_id: 1, quantity: 1, unit_price: 10, vat_rate_id: null, ...overrides }
}

describe('productLinesFromFormOfferLines / costLinesFromFormCostLines', () => {
  it('skips a row with no product picked yet', () => {
    const rows = productLinesFromFormOfferLines(
      [{ product_id: null, quantity: null, unit_price: null, vat_rate_id: null }],
      [],
      () => null,
    )
    expect(rows).toEqual([])
  })

  it('resolves the product name through the caller-provided cache, with zero commissions when the row carries none', () => {
    const rows = productLinesFromFormOfferLines(
      [formOfferRow({ product_id: 7, client_key: 'line-7' })],
      [],
      (id) => (id === 7 ? 'Widget Pro' : null),
    )
    expect(rows).toEqual([{ key: 'line-7', productName: 'Widget Pro', rowNumber: 1, net: 10, commissionsNet: 0 }])
  })

  /**
   * Spec 0145 (D-1/AC-010): the row's own commission is recomputed on the D-1
   * base (net minus the SAME `costLines`' imputed net), not on the raw net.
   */
  it('recomputes the row commission on the D-1 base (net minus its own imputed cost)', () => {
    const rows = productLinesFromFormOfferLines(
      [formOfferRow({
        product_id: 7,
        client_key: 'line-7',
        quantity: 1,
        unit_price: 1000,
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
      })],
      [{ product_id: 2, quantity: 1, unit_price: 400, vat_rate_id: null, offer_line_key: 'line-7' }],
      (id) => (id === 7 ? 'Widget Pro' : null),
    )
    expect(rows[0]).toMatchObject({ net: 1000, commissionsNet: 60 })
  })

  it('drops a pristine cost row (never touched)', () => {
    const rows = costLinesFromFormCostLines([
      { product_id: null, quantity: null, unit_price: null, vat_rate_id: null, commissions: [] },
    ])
    expect(rows).toEqual([])
  })
})

describe('productLinesFromPersistedOfferLines / costLinesFromPersistedCostLines', () => {
  it('uses the CONGEALED net_amount, never recomputes from quantity*price', () => {
    const rows = productLinesFromPersistedOfferLines([
      quoteLineFixture({ id: 3, product_id: 7, quantity: '2.00', unit_price: '1.00', net_amount: '999.00' }),
    ])
    expect(rows[0]).toMatchObject({ key: 'line-3', net: 999 })
  })

  /** Spec 0145 (D-9): sums the row's already server-computed `calculated_amount`, never recomputed. */
  it('sums the persisted calculated_amount of every commission on the row', () => {
    const rows = productLinesFromPersistedOfferLines([
      quoteLineFixture({
        id: 3,
        net_amount: '600.00',
        commissions: [
          {
            recipient_role: 'COMMERCIAL',
            recipient_type: 'user',
            recipient_id: 1,
            commission_type: 'PERCENTAGE',
            value: '10.0000',
            calculated_amount: '60.00',
            internal_note: null,
            origin: 'MANUAL_OVERRIDE',
            commission_configuration_id: null,
          },
          {
            recipient_role: 'SUPPLIER',
            recipient_type: 'registry',
            recipient_id: 2,
            commission_type: 'FIXED_AMOUNT',
            value: '5.0000',
            calculated_amount: '5.00',
            internal_note: null,
            origin: 'PRODUCT',
            commission_configuration_id: null,
          },
        ],
      }),
    ])
    expect(rows[0]).toMatchObject({ commissionsNet: 65 })
  })

  it('resolves offer_line_id onto the SAME line-<id> scheme as the offer row key', () => {
    const costs = costLinesFromPersistedCostLines([quoteLineFixture({ offer_line_id: 3, net_amount: '5.00' })])
    expect(costs).toEqual([{ offerLineKey: 'line-3', net: 5 }])
  })

  it('maps a generic cost (offer_line_id null) to offerLineKey null', () => {
    const costs = costLinesFromPersistedCostLines([quoteLineFixture({ offer_line_id: null, net_amount: '5.00' })])
    expect(costs).toEqual([{ offerLineKey: null, net: 5 }])
  })
})

describe('QuoteProductMargins (component)', () => {
  it('renders nothing when there are no product rows (AC-015)', () => {
    const { container } = render(<QuoteProductMargins rows={[]} genericCostNet={0} />)
    expect(container).toBeEmptyDOMElement()
  })

  it('renders one row per product plus the generic-costs row, negative margin highlighted', () => {
    render(
      <QuoteProductMargins
        rows={[
          { key: 'a', productName: 'Widget', rowNumber: 1, revenueNet: 100, costNet: 30, commissionsNet: 0, margin: 70 },
          { key: 'b', productName: 'Gadget', rowNumber: 2, revenueNet: 20, costNet: 35, commissionsNet: 0, margin: -15 },
        ]}
        genericCostNet={12}
      />,
    )

    expect(screen.getByText('Margin per product')).toBeInTheDocument()
    expect(screen.getByText('Widget (row 1)')).toBeInTheDocument()
    expect(screen.getByText('Gadget (row 2)')).toBeInTheDocument()
    expect(screen.getByText('-15.00')).toHaveClass('text-destructive')
    expect(screen.getByText('Generic costs')).toBeInTheDocument()
    expect(screen.getByText('12.00')).toBeInTheDocument()
  })

  it('falls back to a generic product label when the name could not be resolved', () => {
    render(
      <QuoteProductMargins
        rows={[{ key: 'a', productName: null, rowNumber: 1, revenueNet: 10, costNet: 0, commissionsNet: 0, margin: 10 }]}
        genericCostNet={0}
      />,
    )
    expect(screen.getByText('Selected product (row 1)')).toBeInTheDocument()
  })

  /** Spec 0145 (D-9, AC-011): the "Commissions" column, shown by default (live form always knows its own commissions). */
  it('shows the Commissions column and value by default', () => {
    render(
      <QuoteProductMargins
        rows={[{ key: 'a', productName: 'Widget', rowNumber: 1, revenueNet: 100, costNet: 30, commissionsNet: 7, margin: 63 }]}
        genericCostNet={0}
      />,
    )
    expect(screen.getByText('Commissions')).toBeInTheDocument()
    expect(screen.getByText('7.00')).toBeInTheDocument()
    expect(screen.getByText('63.00')).toBeInTheDocument()
  })

  /** User decision 2026-09-22: without the `commissions` permission the row margins cannot match the net Margine atteso, so the block is hidden. */
  it('renders nothing when showCommissions is false (no commissions permission)', () => {
    const { container } = render(
      <QuoteProductMargins
        rows={[{ key: 'a', productName: 'Widget', rowNumber: 1, revenueNet: 100, costNet: 30, commissionsNet: 7, margin: 63 }]}
        genericCostNet={0}
        showCommissions={false}
      />,
    )
    expect(container).toBeEmptyDOMElement()
  })
})
