import { describe, expect, it } from 'vitest'
import { quoteLineFixture, WORKFLOW_STATUS_OPEN } from '@/features/quotes/quote-fixtures'
import { buildCreatePayload, buildUpdatePayload } from '@/features/quotes/quote-form-payload'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetail } from '@/features/quotes/types'

/**
 * Spec 0144 AC-013/AC-014: the cost row's client-only `offer_line_key`
 * resolves onto the wire's `offer_line_id`/`offer_line_index`, never onto the
 * client_key itself. Split out of `quote-form-payload.test.ts` (engineering.md
 * §6 size limit) rather than growing that file past 500 lines.
 */

function formValues(overrides: Partial<QuoteFormValues> = {}): QuoteFormValues {
  return {
    code: 'QUO-0001',
    title: 'Offerta cliente Acme',
    opportunity_id: 10,
    quote_workflow_status_id: 1,
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
    quote_workflow_status_id: 1,
    quote_workflow_status: WORKFLOW_STATUS_OPEN,
    quote_workflow_statuses: [WORKFLOW_STATUS_OPEN],
    applicable_attributes: [],
    attribute_layout: null,
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
    rewards: [],
    attribute_values: {},
    offer_lines: [],
    cost_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
      product_typologies: [],
    },
    created_at: '2026-07-29T00:00:00Z',
    updated_at: '2026-07-29T00:00:00Z',
    ...overrides,
  }
}

describe('create: cost line associations (spec 0144)', () => {
  it('a cost on a PERSISTED-looking offer row (has an id in this same payload) resolves to offer_line_id', () => {
    const payload = buildCreatePayload(
      formValues({
        offer_lines: [{ product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null, id: 3, client_key: 'line-3' }],
        cost_lines: [{ product_id: 9, quantity: 1, unit_price: 2, vat_rate_id: null, offer_line_key: 'line-3' }],
      }),
    )

    expect(payload.cost_lines?.[0].offer_line_id).toBe(3)
    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_index')
  })

  it('a cost on a NEW offer row (no id yet) resolves to its index among the rows sent', () => {
    const payload = buildCreatePayload(
      formValues({
        offer_lines: [
          { product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'new-a' },
          { product_id: 8, quantity: 1, unit_price: 20, vat_rate_id: null, client_key: 'new-b' },
        ],
        cost_lines: [{ product_id: 9, quantity: 1, unit_price: 2, vat_rate_id: null, offer_line_key: 'new-b' }],
      }),
    )

    expect(payload.cost_lines?.[0].offer_line_index).toBe(1)
    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_id')
  })

  it('a generic cost (no offer_line_key) carries neither key', () => {
    const payload = buildCreatePayload(
      formValues({ cost_lines: [{ product_id: 9, quantity: 1, unit_price: 2, vat_rate_id: null }] }),
    )

    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_id')
    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_index')
  })

  it('never leaks client_key/offer_line_key onto the wire', () => {
    const payload = buildCreatePayload(
      formValues({
        offer_lines: [{ product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'new-a' }],
        cost_lines: [
          { product_id: 9, quantity: 1, unit_price: 2, vat_rate_id: null, client_key: 'cost-1', offer_line_key: 'new-a' },
        ],
      }),
    )

    expect(payload.offer_lines?.[0]).not.toHaveProperty('client_key')
    expect(payload.cost_lines?.[0]).not.toHaveProperty('client_key')
    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_key')
  })
})

describe('update: cost line associations (spec 0144)', () => {
  it('a quote loaded and NOT modified sends neither offer_lines nor cost_lines (AC-014)', () => {
    const original = detail({
      offer_lines: [quoteLineFixture({ id: 1, product_id: 7 })],
      cost_lines: [quoteLineFixture({ id: 2, product_id: 9, offer_line_id: 1 })],
    })
    const payload = buildUpdatePayload(
      formValues({
        offer_lines: [{ id: 1, product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-1' }],
        cost_lines: [
          { id: 2, product_id: 9, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-2', offer_line_key: 'line-1' },
        ],
      }),
      original,
    )

    expect(payload).toEqual({})
  })

  it('attributing a persisted cost to a persisted offer row sends cost_lines only (AC-003 mirror)', () => {
    const original = detail({
      offer_lines: [quoteLineFixture({ id: 1, product_id: 7 })],
      cost_lines: [quoteLineFixture({ id: 2, product_id: 9, offer_line_id: null })],
    })
    const payload = buildUpdatePayload(
      formValues({
        offer_lines: [{ id: 1, product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-1' }],
        cost_lines: [
          { id: 2, product_id: 9, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-2', offer_line_key: 'line-1' },
        ],
      }),
      original,
    )

    expect(payload.offer_lines).toBeUndefined()
    expect(payload.cost_lines?.[0].offer_line_id).toBe(1)
  })

  it('a cost attributed to a BRAND-NEW offer row forces offer_lines to travel too (the row set changed)', () => {
    const original = detail({
      offer_lines: [quoteLineFixture({ id: 1, product_id: 7 })],
      cost_lines: [quoteLineFixture({ id: 2, product_id: 9, offer_line_id: null })],
    })
    const payload = buildUpdatePayload(
      formValues({
        offer_lines: [
          { id: 1, product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-1' },
          { product_id: 8, quantity: 1, unit_price: 20, vat_rate_id: null, client_key: 'new-a' },
        ],
        cost_lines: [
          { id: 2, product_id: 9, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-2', offer_line_key: 'new-a' },
        ],
      }),
      original,
    )

    expect(payload.offer_lines).toBeDefined()
    expect(payload.cost_lines?.[0].offer_line_index).toBe(1)
  })

  it('removing the associated offer row sends cost_lines with the association dropped (AC-012/AC-008 mirror)', () => {
    const original = detail({
      offer_lines: [quoteLineFixture({ id: 1, product_id: 7 })],
      cost_lines: [quoteLineFixture({ id: 2, product_id: 9, offer_line_id: 1 })],
    })
    const payload = buildUpdatePayload(
      formValues({
        offer_lines: [],
        cost_lines: [
          { id: 2, product_id: 9, quantity: 1, unit_price: 10, vat_rate_id: null, client_key: 'line-2', offer_line_key: null },
        ],
      }),
      original,
    )

    expect(payload.offer_lines).toEqual([])
    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_id')
    expect(payload.cost_lines?.[0]).not.toHaveProperty('offer_line_index')
  })
})
