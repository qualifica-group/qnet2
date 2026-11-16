import { describe, expect, it } from 'vitest'
import axios from 'axios'
import { readWithOpportunityRefusal } from '@/features/quotes/with-opportunity/with-opportunity-errors'

function unprocessable(errors: Record<string, string[]>) {
  return new axios.AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { errors },
  } as never)
}

describe('readWithOpportunityRefusal', () => {
  it('is null for anything but a 422', () => {
    expect(readWithOpportunityRefusal(new Error('boom'))).toBeNull()
    expect(
      readWithOpportunityRefusal(new axios.AxiosError('x', '500', undefined, undefined, { status: 500, data: {} } as never)),
    ).toBeNull()
  })

  it('splits the prefixed keys per block and collapses row keys onto their collection', () => {
    const refusal = readWithOpportunityRefusal(
      unprocessable({
        'opportunity.name': ['Too long.'],
        'quote.offer_lines.0.product_id': ['Required.'],
        'quote.offer_lines.1.quantity': ['Invalid.'],
      }),
    )

    expect(refusal?.opportunity).toEqual([{ field: 'name', message: 'Too long.' }])
    expect(refusal?.quote).toEqual([{ field: 'offer_lines', message: 'Required.' }])
    expect(refusal?.duplicate).toBeNull()
  })

  it('reads the guard refusal as a duplicate and does not repeat it as a field error', () => {
    const refusal = readWithOpportunityRefusal(
      unprocessable({
        'opportunity.registry_id': ['Already open.'],
        'opportunity.existing_opportunity_id': ['41'],
      }),
    )

    expect(refusal?.duplicate).toEqual({ opportunityId: 41, message: 'Already open.' })
    expect(refusal?.opportunity).toEqual([])
  })

  it('keeps a plain registry_id refusal (no existing id) as a field error', () => {
    const refusal = readWithOpportunityRefusal(unprocessable({ 'opportunity.registry_id': ['Required.'] }))

    expect(refusal?.duplicate).toBeNull()
    expect(refusal?.opportunity).toEqual([{ field: 'registry_id', message: 'Required.' }])
  })
})
