import { describe, expect, it } from 'vitest'
import {
  lineClientKey,
  linesToFormValues,
  offerLineReferenceResolver,
  originalLineInputs,
  sameLines,
  sanitizeCostOfferLineKeys,
  toLineInputs,
} from '@/features/quotes/quote-line-values'
import { quoteLineFixture } from '@/features/quotes/quote-fixtures'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'

/**
 * Spec 0144 D-4/D-7/AC-012/AC-013/AC-014: the client-only bridge between a
 * cost row's `offer_line_key` and the wire's `offer_line_id`/`offer_line_index`.
 */

function offerRow(overrides: Partial<QuoteLineFormValues> = {}): QuoteLineFormValues {
  return {
    product_id: 1,
    quantity: 1,
    unit_price: 10,
    vat_rate_id: null,
    ...overrides,
  }
}

function costRow(overrides: Partial<QuoteLineFormValues> = {}): QuoteLineFormValues {
  return {
    product_id: 2,
    quantity: 1,
    unit_price: 5,
    vat_rate_id: null,
    ...overrides,
  }
}

describe('offerLineReferenceResolver', () => {
  it('resolves a cost pointing at a PERSISTED offer row to offer_line_id (AC-013)', () => {
    const offerLines = [offerRow({ id: 7, client_key: 'line-7' })]
    const resolve = offerLineReferenceResolver(offerLines)

    expect(resolve(costRow({ offer_line_key: 'line-7' }))).toEqual({ offer_line_id: 7 })
  })

  it('resolves a cost pointing at a NEW (not-yet-persisted) offer row to its position among the rows actually sent (AC-013)', () => {
    const offerLines = [
      offerRow({ id: 7, client_key: 'line-7' }),
      offerRow({ client_key: 'new-1' }),
    ]
    const resolve = offerLineReferenceResolver(offerLines)

    expect(resolve(costRow({ offer_line_key: 'new-1' }))).toEqual({ offer_line_index: 1 })
  })

  it('drops a pristine offer row from the index count, mirroring toLineInputs (AC-013)', () => {
    const offerLines = [
      { product_id: null, quantity: null, unit_price: null, vat_rate_id: null, client_key: 'pristine' },
      offerRow({ client_key: 'new-1' }),
    ]
    const resolve = offerLineReferenceResolver(offerLines)

    expect(resolve(costRow({ offer_line_key: 'new-1' }))).toEqual({ offer_line_index: 0 })
  })

  it('resolves to a generic cost (no key) when offer_line_key is null', () => {
    const resolve = offerLineReferenceResolver([offerRow({ id: 7, client_key: 'line-7' })])

    expect(resolve(costRow({ offer_line_key: null }))).toEqual({})
  })

  it('resolves to a generic cost when the referenced offer row no longer exists (defensive fallback)', () => {
    const resolve = offerLineReferenceResolver([offerRow({ id: 7, client_key: 'line-7' })])

    expect(resolve(costRow({ offer_line_key: 'line-gone' }))).toEqual({})
  })
})

describe('toLineInputs with a resolver', () => {
  it('never emits client_key/offer_line_key, only the resolved wire keys (AC-013)', () => {
    const offerLines = [offerRow({ id: 7, client_key: 'line-7' })]
    const resolve = offerLineReferenceResolver(offerLines)
    const costLines = [costRow({ client_key: 'line-c1', offer_line_key: 'line-7' })]

    const [wireCost] = toLineInputs(costLines, resolve)

    expect(wireCost).toEqual({
      product_id: 2,
      quantity: 1,
      unit_price: 5,
      vat_rate_id: null,
      sort_order: 0,
      offer_line_id: 7,
    })
  })

  it('omits both keys entirely for a generic cost, never sending null', () => {
    const [wireCost] = toLineInputs([costRow({ offer_line_key: null })], offerLineReferenceResolver([]))

    expect('offer_line_id' in wireCost).toBe(false)
    expect('offer_line_index' in wireCost).toBe(false)
  })
})

describe('sanitizeCostOfferLineKeys (AC-012)', () => {
  it('clears a stale offer_line_key not present in the valid set', () => {
    const rows = [costRow({ offer_line_key: 'line-gone' })]

    expect(sanitizeCostOfferLineKeys(rows, new Set(['line-7']))[0].offer_line_key).toBeNull()
  })

  it('leaves a valid offer_line_key untouched', () => {
    const rows = [costRow({ offer_line_key: 'line-7' })]

    expect(sanitizeCostOfferLineKeys(rows, new Set(['line-7']))[0].offer_line_key).toBe('line-7')
  })

  it('leaves a generic (null) cost untouched', () => {
    const rows = [costRow({ offer_line_key: null })]

    expect(sanitizeCostOfferLineKeys(rows, new Set())[0].offer_line_key).toBeNull()
  })
})

describe('linesToFormValues (edit hydration, spec 0144 D-7)', () => {
  it('derives client_key from the persisted id (line-<id>)', () => {
    const [formRow] = linesToFormValues([quoteLineFixture({ id: 42 })])

    expect(formRow.client_key).toBe('line-42')
  })

  it('derives offer_line_key from the persisted offer_line_id using the SAME scheme', () => {
    const [formRow] = linesToFormValues([quoteLineFixture({ id: 5, offer_line_id: 42 })])

    expect(formRow.offer_line_key).toBe('line-42')
  })

  it('leaves offer_line_key null for a generic (unassociated) cost', () => {
    const [formRow] = linesToFormValues([quoteLineFixture({ id: 5, offer_line_id: null })])

    expect(formRow.offer_line_key).toBeNull()
  })
})

describe('sameLines with the association keys (AC-014)', () => {
  it('is TRUE when the persisted association is resubmitted unchanged (buildUpdatePayload must not resend it)', () => {
    // Every field but the association must already match `costRow()`'s own
    // defaults (product_id 2, quantity 1, unit_price 5), or a mismatch there
    // would fail the comparison for a reason unrelated to what this test
    // actually exercises.
    const original = originalLineInputs([
      quoteLineFixture({ id: 2, product_id: 2, quantity: '1.00', unit_price: '5.00', offer_line_id: 7 }),
    ])
    const current = toLineInputs(
      [costRow({ id: 2, client_key: 'line-2', offer_line_key: 'line-7' })],
      offerLineReferenceResolver([offerRow({ id: 7, client_key: 'line-7' })]),
    )

    expect(sameLines(current, original)).toBe(true)
  })

  it('is FALSE when a generic cost becomes attributed via offer_line_index', () => {
    const original = originalLineInputs([
      quoteLineFixture({ id: 2, product_id: 2, quantity: '1.00', unit_price: '5.00', offer_line_id: null }),
    ])
    const current = toLineInputs(
      [costRow({ id: 2, client_key: 'line-2', offer_line_key: 'new-1' })],
      offerLineReferenceResolver([offerRow({ client_key: 'new-1' })]),
    )

    expect(sameLines(current, original)).toBe(false)
  })

  it('is FALSE when an attributed cost loses its association (offer row removed, AC-012)', () => {
    const original = originalLineInputs([
      quoteLineFixture({ id: 2, product_id: 2, quantity: '1.00', unit_price: '5.00', offer_line_id: 7 }),
    ])
    const current = toLineInputs(
      [costRow({ id: 2, client_key: 'line-2', offer_line_key: null })],
      offerLineReferenceResolver([]),
    )

    expect(sameLines(current, original)).toBe(false)
  })
})

describe('lineClientKey', () => {
  it('is deterministic for the same id', () => {
    expect(lineClientKey(9)).toBe(lineClientKey(9))
  })

  it('differs for different ids', () => {
    expect(lineClientKey(9)).not.toBe(lineClientKey(10))
  })
})

/** Spec 0306 D-3/AC-008: a saved manual Supplier commission is re-sent with the line's snapshot direction. */
describe('manual Supplier commission direction on the wire (spec 0306)', () => {
  const savedCommission = (origin: 'MANUAL_OVERRIDE' | 'PRODUCT_CATEGORY') => ({
    id: 5,
    recipient_role: 'SUPPLIER' as const,
    recipient_type: 'registry' as const,
    recipient_id: 7,
    commission_type: 'PERCENTAGE' as const,
    value: '10.0000',
    calculated_amount: '10.00',
    internal_note: null,
    origin,
    commission_configuration_id: null,
  })

  it('hydrates a saved manual Supplier commission with the snapshot and re-sends it', () => {
    const line = quoteLineFixture({ id: 1, supplier_commission_direction: 'RECEIVED', commissions: [savedCommission('MANUAL_OVERRIDE')] })

    const [row] = linesToFormValues([line])
    const [wire] = toLineInputs([row])

    expect(row.commissions?.[0].supplier_commission_direction).toBe('RECEIVED')
    expect(wire.commissions?.[0]).toMatchObject({ recipient_role: 'SUPPLIER', supplier_commission_direction: 'RECEIVED' })
  })

  it('never sends a direction on an automatic Supplier commission', () => {
    const line = quoteLineFixture({ id: 1, supplier_commission_direction: 'PAID', commissions: [savedCommission('PRODUCT_CATEGORY')] })

    const [wire] = toLineInputs(linesToFormValues([line]))

    expect(wire.commissions?.[0]).not.toHaveProperty('supplier_commission_direction')
  })

  it('is unchanged against the baseline until the direction changes', () => {
    const line = quoteLineFixture({ id: 1, supplier_commission_direction: 'RECEIVED', commissions: [savedCommission('MANUAL_OVERRIDE')] })
    const baseline = originalLineInputs([line])
    const [row] = linesToFormValues([line])

    expect(sameLines(toLineInputs([row]), baseline)).toBe(true)
    const changed = { ...row, commissions: row.commissions?.map((c) => ({ ...c, supplier_commission_direction: 'PAID' as const })) }
    expect(sameLines(toLineInputs([changed]), baseline)).toBe(false)
  })
})
