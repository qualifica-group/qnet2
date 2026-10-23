import { describe, expect, it } from 'vitest'
import { buildCommissionConfigurationSchema } from './commission-configuration-schema'

const t = ((key: string) => key) as never
const valid = {
  name: 'Sales product rule',
  recipient_role: 'COMMERCIAL' as const,
  application_scope: 'PRODUCT' as const,
  product_category_id: null,
  product_id: 10,
  recipient_type: 'referent' as const,
  recipient_id: null,
  commission_type: 'PERCENTAGE' as const,
  supplier_commission_direction: null,
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

  it('requires recipient_id for the RECIPIENT scope (AC-009)', () => {
    const result = buildCommissionConfigurationSchema(t).safeParse({
      ...valid,
      application_scope: 'RECIPIENT',
      product_id: null,
      recipient_id: null,
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'recipient_id')).toBe(true)
    }
  })

  it('accepts the RECIPIENT scope once recipient_id is set', () => {
    expect(
      buildCommissionConfigurationSchema(t).safeParse({
        ...valid,
        application_scope: 'RECIPIENT',
        product_id: null,
        recipient_id: 42,
      }).success,
    ).toBe(true)
  })

  it('admits recipient_id alongside PRODUCT/PRODUCT_CATEGORY scopes without requiring it (D-2)', () => {
    expect(
      buildCommissionConfigurationSchema(t).safeParse({ ...valid, recipient_id: 7 }).success,
    ).toBe(true)
    expect(
      buildCommissionConfigurationSchema(t).safeParse({ ...valid, recipient_id: null }).success,
    ).toBe(true)
  })

  it('accepts recipient_type "user" for a COMMERCIAL rule (AC-007, spec 0090 D-4)', () => {
    expect(
      buildCommissionConfigurationSchema(t).safeParse({ ...valid, recipient_type: 'user' }).success,
    ).toBe(true)
  })

  it('rejects a recipient_type outside the role allow-list (AC-008, spec 0090 D-4)', () => {
    const result = buildCommissionConfigurationSchema(t).safeParse({
      ...valid,
      recipient_role: 'SUPPLIER',
      recipient_type: 'user',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'recipient_type')).toBe(true)
    }
  })

  it('requires the direction for a SUPPLIER rule only (spec 0306 D-1)', () => {
    const schema = buildCommissionConfigurationSchema(t)
    const supplier = { ...valid, recipient_role: 'SUPPLIER' as const, recipient_type: 'registry' as const }

    const refused = schema.safeParse(supplier)
    expect(refused.success).toBe(false)
    expect(refused.error?.issues.some((issue) => issue.path.join('.') === 'supplier_commission_direction')).toBe(true)
    expect(schema.safeParse({ ...supplier, supplier_commission_direction: 'RECEIVED' }).success).toBe(true)
    expect(schema.safeParse(valid).success).toBe(true)
  })
})
