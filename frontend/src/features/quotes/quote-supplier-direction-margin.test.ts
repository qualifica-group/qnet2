import { describe, expect, it } from 'vitest'
import {
  calculateLineMarginBeforeCosts,
  calculateMarginBeforeCosts,
} from '@/features/quotes/commission-calculator'
import {
  computeProductMargins,
  costLinesFromFormCostLines,
  costLinesFromPersistedCostLines,
  productLinesFromFormOfferLines,
  productLinesFromPersistedOfferLines,
} from '@/features/quotes/quote-product-margins-calc'
import { computeQuoteTotals, round2 } from '@/features/quotes/quote-totals'
import { quoteLineFixture } from '@/features/quotes/quote-fixtures'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLineCommission } from '@/features/quotes/types'

/**
 * Spec 0202 AC-005/AC-006: RECEIVED row (n 2000, c 0, s 10% = 200, p 50),
 * PAID row (n 1000, c 100, s 10% of 900 = 90, p 0) and 30 of generic costs
 * give a margin of (200 - 0 - 50) + (1000 - 100 - 0 - 90) - 30 = 930.00,
 * while the expected revenue stays 3000 net (D-9).
 */

const EXPECTED_MARGIN = 930
const RECEIVED_KEY = 'received'
const PAID_KEY = 'paid'

const OTHER_COMMISSION = {
  recipient_role: 'COMMERCIAL',
  recipient_type: 'user',
  recipient_id: 1,
  commission_type: 'FIXED_AMOUNT',
  value: 50,
  internal_note: null,
  origin: 'MANUAL_OVERRIDE',
  commission_configuration_id: null,
} as const

const SUPPLIER_COMMISSION = {
  ...OTHER_COMMISSION,
  recipient_role: 'SUPPLIER',
  recipient_type: 'registry',
  recipient_id: 2,
  commission_type: 'PERCENTAGE',
  value: 10,
} as const

const FORM_OFFER_LINES: QuoteLineFormValues[] = [
  {
    product_id: 1,
    client_key: RECEIVED_KEY,
    quantity: 1,
    unit_price: 2000,
    vat_rate_id: null,
    supplier_commission_direction: 'RECEIVED',
    commissions: [SUPPLIER_COMMISSION, OTHER_COMMISSION],
  },
  {
    product_id: 2,
    client_key: PAID_KEY,
    quantity: 1,
    unit_price: 1000,
    vat_rate_id: null,
    supplier_commission_direction: 'PAID',
    commissions: [SUPPLIER_COMMISSION],
  },
]

const FORM_COST_LINES: QuoteLineFormValues[] = [
  { product_id: 3, quantity: 1, unit_price: 100, vat_rate_id: null, offer_line_key: PAID_KEY },
  { product_id: 4, quantity: 1, unit_price: 30, vat_rate_id: null, offer_line_key: null },
]

function persistedCommission(base: typeof SUPPLIER_COMMISSION | typeof OTHER_COMMISSION, amount: string): QuoteLineCommission {
  return { ...base, value: String(base.value), calculated_amount: amount }
}

describe('calculateLineMarginBeforeCosts (D-8)', () => {
  it.each([
    ['RECEIVED', 200 - 50],
    ['PAID', 2000 - 50 - 200],
    [null, 2000 - 50],
  ] as const)('direction %s', (direction, expected) => {
    expect(calculateLineMarginBeforeCosts(2000, 200, 50, direction)).toBe(expected)
  })
})

describe('live preview margin (AC-005/AC-006, new rows)', () => {
  it('sums the row margins by direction and subtracts every cost row', () => {
    const offer = FORM_OFFER_LINES.map((row) => ({ ...row, quantity: row.quantity ?? 0, unitPrice: row.unit_price ?? 0 }))
    const totals = computeQuoteTotals(
      offer.map((row) => ({ quantity: row.quantity, unitPrice: row.unitPrice, vatRatePercent: null })),
      FORM_COST_LINES.map((row) => ({ quantity: row.quantity ?? 0, unitPrice: row.unit_price ?? 0, vatRatePercent: null })),
    )

    const margin = round2(calculateMarginBeforeCosts(FORM_OFFER_LINES, FORM_COST_LINES) - totals.cost.net)

    expect(margin).toBe(EXPECTED_MARGIN)
    expect(totals.revenue.net).toBe(3000)
  })

  it('treats a row without commissions block as having no Supplier commission', () => {
    const rows: QuoteLineFormValues[] = [
      { product_id: 1, quantity: 1, unit_price: 500, vat_rate_id: null, supplier_commission_direction: 'RECEIVED' },
    ]
    expect(calculateMarginBeforeCosts(rows, [])).toBe(500)
  })
})

describe('Margin per product (AC-006)', () => {
  it('matches the server for new rows', () => {
    const summary = computeProductMargins(
      productLinesFromFormOfferLines(FORM_OFFER_LINES, FORM_COST_LINES, () => 'P'),
      costLinesFromFormCostLines(FORM_COST_LINES),
    )

    expect(summary.rows.map((row) => row.margin)).toEqual([150, 810])
    expect(summary.rows.map((row) => row.supplierCommissionDirection)).toEqual(['RECEIVED', 'PAID'])
    expect(round2(summary.rows.reduce((sum, row) => sum + row.margin, 0) - summary.genericCostNet)).toBe(EXPECTED_MARGIN)
  })

  it('matches the server for saved rows', () => {
    const offer = [
      quoteLineFixture({
        id: 1,
        quantity: '1.00',
        unit_price: '2000.00',
        net_amount: '2000.00',
        supplier_commission_direction: 'RECEIVED',
        commissions: [persistedCommission(SUPPLIER_COMMISSION, '200.00'), persistedCommission(OTHER_COMMISSION, '50.00')],
      }),
      quoteLineFixture({
        id: 2,
        quantity: '1.00',
        unit_price: '1000.00',
        net_amount: '1000.00',
        supplier_commission_direction: 'PAID',
        commissions: [persistedCommission(SUPPLIER_COMMISSION, '90.00')],
      }),
    ]
    const costs = [
      quoteLineFixture({ id: 3, net_amount: '100.00', offer_line_id: 2 }),
      quoteLineFixture({ id: 4, net_amount: '30.00', offer_line_id: null }),
    ]

    const summary = computeProductMargins(
      productLinesFromPersistedOfferLines(offer),
      costLinesFromPersistedCostLines(costs),
    )

    expect(summary.rows.map((row) => row.margin)).toEqual([150, 810])
    expect(summary.genericCostNet).toBe(30)
    expect(round2(summary.rows.reduce((sum, row) => sum + row.margin, 0) - summary.genericCostNet)).toBe(EXPECTED_MARGIN)
  })

  it('keeps the legacy margin for a row without direction', () => {
    const summary = computeProductMargins(
      productLinesFromPersistedOfferLines([
        quoteLineFixture({ id: 1, net_amount: '1000.00', commissions: [persistedCommission(OTHER_COMMISSION, '50.00')] }),
      ]),
      [],
    )
    expect(summary.rows[0].margin).toBe(950)
  })
})
