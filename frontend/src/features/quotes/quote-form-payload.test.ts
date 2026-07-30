import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetail } from '@/features/quotes/types'

/** Spec 0065 AC-076: the payload builder never emits calculated amounts. */

function formValues(overrides: Partial<QuoteFormValues> = {}): QuoteFormValues {
  return {
    code: 'QUO-0001',
    title: 'Offerta cliente Acme',
    opportunity_id: 10,
    // Matches `detail()`'s default below (quote_status_id is a required FK,
    // never null) so the "unchanged" fixtures below are genuinely unchanged.
    quote_status_id: 1,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    company_id: null,
    company_site_id: null,
    operational_site_id: null,
    layout_id: null,
    payment_method_id: null,
    internal_notes: null,
    offer_lines: [],
    cost_lines: [],
    ...overrides,
  }
}

function detail(overrides: Partial<QuoteDetail> = {}): QuoteDetail {
  return {
    id: 5,
    code: 'QUO-0001',
    title: 'Offerta cliente Acme',
    opportunity_id: 10,
    opportunity: { id: 10, name: 'Deal Acme' },
    quote_status_id: 1,
    quote_status: { id: 1, name: 'Bozza', color: 'slate', group: 'open' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    company_id: null,
    company: null,
    company_site_id: null,
    company_site: null,
    operational_site_id: null,
    operational_site: null,
    layout_id: null,
    layout: null,
    payment_method_id: null,
    payment_method: null,
    internal_notes: null,
    offer_lines: [],
    cost_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
    },
    created_at: '2026-07-29T00:00:00Z',
    updated_at: '2026-07-29T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('includes code when set', () => {
    const payload = buildCreatePayload(formValues({ code: 'QUO-0001' }))
    expect(payload.code).toBe('QUO-0001')
  })

  it('omits code when blank (server generates the sequential value)', () => {
    const payload = buildCreatePayload(formValues({ code: '   ' }))
    expect(payload.code).toBeUndefined()
  })

  it('sends opportunity_id on create', () => {
    const payload = buildCreatePayload(formValues({ opportunity_id: 42 }))
    expect(payload.opportunity_id).toBe(42)
  })

  it('sends layout_id on create (AC-311)', () => {
    const payload = buildCreatePayload(formValues({ layout_id: 4 }))
    expect(payload.layout_id).toBe(4)
  })

  it('emits only the contract fields for each line, never the calculated amounts', () => {
    const payload = buildCreatePayload(
      formValues({
        offer_lines: [{ product_id: 7, quantity: 3, unit_price: 10, vat_rate_id: 2 }],
      }),
    )
    expect(payload.offer_lines).toEqual([
      { product_id: 7, quantity: 3, unit_price: 10, vat_rate_id: 2, sort_order: 0 },
    ])
    const line = payload.offer_lines?.[0] as unknown as Record<string, unknown>
    expect(line.net_amount).toBeUndefined()
    expect(line.vat_amount).toBeUndefined()
    expect(line.total_amount).toBeUndefined()
  })

  it('assigns sort_order from array position', () => {
    const payload = buildCreatePayload(
      formValues({
        cost_lines: [
          { product_id: 1, quantity: 1, unit_price: 1, vat_rate_id: null },
          { product_id: 2, quantity: 2, unit_price: 2, vat_rate_id: null },
        ],
      }),
    )
    expect(payload.cost_lines?.map((line) => line.sort_order)).toEqual([0, 1])
  })

  it('always sends offer_lines/cost_lines in full, even empty', () => {
    const payload = buildCreatePayload(formValues())
    expect(payload.offer_lines).toEqual([])
    expect(payload.cost_lines).toEqual([])
  })
})

describe('buildUpdatePayload', () => {
  it('never includes opportunity_id (immutable, AC-025)', () => {
    const payload = buildUpdatePayload(formValues({ title: 'Nuovo titolo' }), detail())
    expect('opportunity_id' in payload).toBe(false)
  })

  it('never includes code (immutable, AC-069) even at the type level', () => {
    const payload = buildUpdatePayload(formValues(), detail())
    expect('code' in payload).toBe(false)
  })

  it('omits every unchanged field', () => {
    const payload = buildUpdatePayload(formValues(), detail())
    expect(payload).toEqual({})
  })

  it('includes only the field that changed', () => {
    const payload = buildUpdatePayload(formValues({ title: 'Titolo aggiornato' }), detail())
    expect(payload).toEqual({ title: 'Titolo aggiornato' })
  })

  it('includes commercial_id only when it changed from the original', () => {
    const payload = buildUpdatePayload(
      formValues({ commercial_id: 9 }),
      detail({ commercial_id: null, commercial: null }),
    )
    expect(payload.commercial_id).toBe(9)
  })

  it('includes layout_id only when it changed from the original (AC-311)', () => {
    const original = detail({ layout_id: 3, layout: { id: 3, name: 'Layout A' } })

    const unchanged = buildUpdatePayload(formValues({ layout_id: 3 }), original)
    expect('layout_id' in unchanged).toBe(false)

    const changed = buildUpdatePayload(formValues({ layout_id: 7 }), original)
    expect(changed.layout_id).toBe(7)
  })

  it('includes layout_id: null when clearing a persisted layout (AC-215/AC-311)', () => {
    const original = detail({ layout_id: 3, layout: { id: 3, name: 'Layout A' } })
    const payload = buildUpdatePayload(formValues({ layout_id: null }), original)
    expect(payload.layout_id).toBeNull()
  })

  it('omits offer_lines when the row set is unchanged (D-8/AC-037)', () => {
    const original = detail({
      offer_lines: [
        {
          id: 1,
          product_id: 7,
          product: { id: 7, code: 'PRD-0001', name: 'Servizio A', category: null, business_function: null },
          quantity: '3.00',
          unit_price: '10.00',
          vat_rate_id: null,
          vat_rate: null,
          net_amount: '30.00',
          vat_amount: '0.00',
          total_amount: '30.00',
          sort_order: 0,
        },
      ],
    })
    const payload = buildUpdatePayload(
      formValues({ offer_lines: [{ product_id: 7, quantity: 3, unit_price: 10, vat_rate_id: null }] }),
      original,
    )
    expect(payload.offer_lines).toBeUndefined()
  })

  it('includes offer_lines as a full replace when the row set changed', () => {
    const original = detail({
      offer_lines: [
        {
          id: 1,
          product_id: 7,
          product: { id: 7, code: 'PRD-0001', name: 'Servizio A', category: null, business_function: null },
          quantity: '3.00',
          unit_price: '10.00',
          vat_rate_id: null,
          vat_rate: null,
          net_amount: '30.00',
          vat_amount: '0.00',
          total_amount: '30.00',
          sort_order: 0,
        },
      ],
    })
    const payload = buildUpdatePayload(
      formValues({ offer_lines: [{ product_id: 7, quantity: 5, unit_price: 10, vat_rate_id: null }] }),
      original,
    )
    expect(payload.offer_lines).toEqual([
      { product_id: 7, quantity: 5, unit_price: 10, vat_rate_id: null, sort_order: 0 },
    ])
  })

  it('leaves cost_lines untouched when only offer_lines changed', () => {
    const original = detail({
      cost_lines: [
        {
          id: 2,
          product_id: 3,
          product: { id: 3, code: 'PRD-0002', name: 'Servizio B', category: null, business_function: null },
          quantity: '1.00',
          unit_price: '5.00',
          vat_rate_id: null,
          vat_rate: null,
          net_amount: '5.00',
          vat_amount: '0.00',
          total_amount: '5.00',
          sort_order: 0,
        },
      ],
    })
    const payload = buildUpdatePayload(
      formValues({
        offer_lines: [{ product_id: 9, quantity: 1, unit_price: 1, vat_rate_id: null }],
        cost_lines: [{ product_id: 3, quantity: 1, unit_price: 5, vat_rate_id: null }],
      }),
      original,
    )
    expect(payload.offer_lines).toBeDefined()
    expect(payload.cost_lines).toBeUndefined()
  })
})
