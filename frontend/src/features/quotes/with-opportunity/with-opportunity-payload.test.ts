import { describe, expect, it } from 'vitest'
import { createValues } from '@/features/opportunities/opportunity-form-payload-fixtures'
import { createDefaults } from '@/features/quotes/quote-form-defaults'
import { buildWithOpportunityPayload } from '@/features/quotes/with-opportunity/with-opportunity-payload'

/** Spec 0396 D-3/D-4 request body: the two existing create builders, minus what the server owns. */
describe('buildWithOpportunityPayload', () => {
  const quoteValues = {
    ...createDefaults({ type: 'create' }, 'QUO-0007'),
    opportunity_id: null,
    offer_lines: [{ product_id: 9, quantity: 1, unit_of_measure: null, unit_price: 100, vat_rate_id: 1, commissions: [] }],
  }

  it('wraps the opportunity and the quote bodies in one object', () => {
    const payload = buildWithOpportunityPayload(createValues({ registry_id: 7 }), quoteValues, 'QUO-0007')

    expect(payload.opportunity).toMatchObject({ registry_id: 7, supervisor_id: 9 })
    expect(payload.quote.offer_lines).toHaveLength(1)
  })

  it('never carries lead_id, quote.opportunity_id or the G.A. promotion flag', () => {
    const payload = buildWithOpportunityPayload(createValues(), quoteValues, 'QUO-0007')

    expect(payload.opportunity).not.toHaveProperty('lead_id')
    expect(payload.quote).not.toHaveProperty('opportunity_id')
    expect(payload.quote).not.toHaveProperty('promote_managers_to_opportunity')
  })

  it('omits the offer code while it is still the next-code suggestion', () => {
    const payload = buildWithOpportunityPayload(createValues(), quoteValues, 'QUO-0007')

    expect(payload.quote).not.toHaveProperty('code')
  })
})
