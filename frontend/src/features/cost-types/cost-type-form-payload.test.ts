import { describe, expect, it } from 'vitest'
import { buildCostTypePayload } from '@/features/cost-types/cost-type-form-payload'

describe('buildCostTypePayload (spec 0210)', () => {
  it('builds the full payload shape, trimming the name', () => {
    expect(
      buildCostTypePayload({ name: ' Fitti ', description: 'Rents', sort_order: 2, is_active: false }),
    ).toEqual({ name: 'Fitti', description: 'Rents', sort_order: 2, is_active: false })
  })

  it('sends a blank description as null', () => {
    expect(
      buildCostTypePayload({ name: 'Fitti', description: '   ', sort_order: 0, is_active: true }).description,
    ).toBeNull()
  })
})
