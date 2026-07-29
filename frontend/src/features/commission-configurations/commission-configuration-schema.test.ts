import { describe, expect, it } from 'vitest'
import { buildCommissionConfigurationSchema } from './commission-configuration-schema'

const t = ((key: string) => key) as never
const valid = {
  name: 'Sales product rule',
  recipient_role: 'COMMERCIAL' as const,
  application_scope: 'PRODUCT' as const,
  product_category_id: null,
  product_id: 10,
  commission_type: 'PERCENTAGE' as const,
  value: 5,
  priority: 10,
  valid_from: '2026-07-29',
  valid_until: null,
  status: 'ACTIVE' as const,
  internal_note: null,
}

describe('commission configuration schema', () => {
  it('requires the relation selected by scope', () => {
    expect(
      buildCommissionConfigurationSchema(t).safeParse({ ...valid, product_id: null }).success,
    ).toBe(false)
    expect(
      buildCommissionConfigurationSchema(t).safeParse({
        ...valid,
        application_scope: 'PRODUCT_CATEGORY',
        product_id: null,
        product_category_id: null,
      }).success,
    ).toBe(false)
  })

  it('rejects an end date before the start date', () => {
    expect(
      buildCommissionConfigurationSchema(t).safeParse({
        ...valid,
        valid_until: '2026-07-28',
      }).success,
    ).toBe(false)
  })
})
